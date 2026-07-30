<?php

namespace MonkeysLegion\SSH\Core;

use MonkeysLegion\SSH\Authentication\AuthenticationDriver;
use MonkeysLegion\SSH\Exceptions\AuthenticationFailedException;
use MonkeysLegion\SSH\Exceptions\ConnectionException;
use MonkeysLegion\SSH\Exceptions\ConnectionRefusedException;
use MonkeysLegion\SSH\Exceptions\HostKeyMismatchException;
use MonkeysLegion\SSH\SFTP\ScpClient;
use MonkeysLegion\SSH\SFTP\SFTPClient;
use MonkeysLegion\SSH\Stream\CommandChannel;
use MonkeysLegion\SSH\Stream\CommandResult;
use MonkeysLegion\SSH\Stream\ShellSession;
use MonkeysLegion\SSH\Stream\StreamHandler;
use MonkeysLegion\SSH\Stream\Tunnel;

class SSHConnection
{
    private mixed $resource = null;
    private StreamHandler $streamHandler;
    private ?SFTPClient $sftpClient = null;
    private ?ScpClient $scpClient = null;

    /**
     * @var \Closure(string, int, array<string, string>): mixed
     */
    private \Closure $connector;

    /**
     * @var \Closure(mixed, string): mixed
     */
    private \Closure $executor;

    /**
     * @var \Closure(mixed): bool
     */
    private \Closure $closer;

    /**
     * @var \Closure(mixed): bool
     */
    private \Closure $sessionCloser;

    /**
     * @var \Closure(mixed, string): bool
     */
    private \Closure $fingerprintChecker;

    /**
     * @var \Closure(mixed): bool
     */
    private \Closure $keepaliveSender;

    /**
     * @var \Closure(mixed, ?string, int, int): mixed
     */
    private \Closure $shellOpener;

    /**
     * @var \Closure(mixed, string, int): mixed
     */
    private \Closure $tunnelOpener;

    private ?int $commandTimeout;
    private int $maxOutputSize;
    private int $keepaliveInterval = 0;
    private int $lastActivity = 0;

    private ?self $bastionConnection = null;
    private string $bastionTargetHost = '';
    private int $bastionTargetPort = 22;

    public function __construct(
        private AuthenticationDriver $auth,
        private string $username = '',
        ?StreamHandler $streamHandler = null,
        ?callable $connector = null,
        ?callable $executor = null,
        ?callable $closer = null,
        ?callable $sessionCloser = null,
        ?callable $fingerprintChecker = null,
        ?callable $keepaliveSender = null,
        ?callable $shellOpener = null,
        ?callable $tunnelOpener = null,
        ?int $commandTimeout = null,
        int $maxOutputSize = 52428800,
        int $keepaliveInterval = 0,
    ) {
        $this->streamHandler = $streamHandler ?? new StreamHandler(maxOutputSize: $maxOutputSize);
        $this->connector = $connector !== null
            ? $connector(...)
            : $this->nativeConnector(...);
        $this->executor = $executor !== null
            ? $executor(...)
            : $this->nativeExecutor(...);
        $this->closer = $closer !== null
            ? $closer(...)
            : $this->nativeCloser(...);
        $this->sessionCloser = $sessionCloser !== null
            ? $sessionCloser(...)
            : $this->nativeSessionCloser(...);
        $this->fingerprintChecker = $fingerprintChecker !== null
            ? $fingerprintChecker(...)
            : $this->nativeFingerprintChecker(...);
        $this->keepaliveSender = $keepaliveSender !== null
            ? $keepaliveSender(...)
            : $this->nativeKeepaliveSender(...);
        $this->shellOpener = $shellOpener !== null
            ? $shellOpener(...)
            : $this->nativeShellOpener(...);
        $this->tunnelOpener = $tunnelOpener !== null
            ? $tunnelOpener(...)
            : $this->nativeTunnelOpener(...);
        $this->commandTimeout = $commandTimeout;
        $this->maxOutputSize = $maxOutputSize;
        $this->keepaliveInterval = $keepaliveInterval;
    }

    public function connect(string $host, int $port = 22, int $timeout = 10, ?string $expectedFingerprint = null): self
    {
        if ($this->username === '') {
            throw new \InvalidArgumentException('Username must be provided before connecting.');
        }

        $previousTimeout = \ini_get('default_socket_timeout');
        if (!\is_string($previousTimeout)) {
            $previousTimeout = '60';
        }

        \ini_set('default_socket_timeout', (string) $timeout);
        try {
            $resource = ($this->connector)($host, $port, ['hostkey' => 'ssh-rsa,ssh-ed25519,ecdsa-sha2-nistp256']);
        } finally {
            \ini_set('default_socket_timeout', $previousTimeout);
        }

        if ($resource === false || $resource === null) {
            throw ConnectionRefusedException::forHost($host, $port);
        }

        // Verify host key fingerprint if one was provided
        if ($expectedFingerprint !== null) {
            $matches = ($this->fingerprintChecker)($resource, $expectedFingerprint);
            if (!$matches) {
                ($this->sessionCloser)($resource);
                throw HostKeyMismatchException::forHost($host, $expectedFingerprint);
            }
        }

        try {
            $this->auth->authenticate($resource, $this->username);
        } catch (AuthenticationFailedException $exception) {
            ($this->sessionCloser)($resource);
            throw $exception;
        } catch (\Throwable $exception) {
            ($this->sessionCloser)($resource);
            throw new AuthenticationFailedException(
                'SSH authentication failed.',
                (int) $exception->getCode(),
                $exception,
            );
        }

        $this->resource = $resource;
        $this->updateActivity();
        return $this;
    }

    public function channel(string $command): CommandChannel
    {
        if ($this->bastionConnection !== null) {
            $wrappedCommand = \sprintf(
                'ssh -o StrictHostKeyChecking=no -p %d %s@%s %s',
                $this->bastionTargetPort,
                \escapeshellarg($this->username),
                \escapeshellarg($this->bastionTargetHost),
                \escapeshellarg($command),
            );

            return $this->bastionConnection->channel($wrappedCommand);
        }

        if (!$this->isConnected()) {
            throw new ConnectionException('SSH connection is not established.');
        }

        $this->ensureAlive();

        $channel = ($this->executor)($this->resource, $command);
        if ($channel === false || $channel === null) {
            throw new ConnectionException('Unable to execute SSH command.');
        }

        $this->updateActivity();
        return new CommandChannel($channel, $this->streamHandler, $this->closer, $this->maxOutputSize);
    }

    public function execute(string $command, ?int $timeout = null): CommandResult
    {
        // Generate a random exit-code marker per command to prevent false matches
        $exitMarker = '__MLSSH_EXIT_' . \bin2hex(\random_bytes(8)) . '__';
        $wrappedCommand = \sprintf(
            'sh -lc %s',
            \escapeshellarg($command . '; printf "\n' . $exitMarker . '%s" "$?"'),
        );

        $cmdChannel = $this->channel($wrappedCommand);

        $effectiveTimeout = $timeout ?? $this->commandTimeout;
        $output = $cmdChannel->read($effectiveTimeout);
        $error = $cmdChannel->readError($effectiveTimeout);
        $exitCode = $this->extractExitCode($output, $exitMarker);
        if ($exitCode === null) {
            try {
                $exitCode = $cmdChannel->getExitStatus();
            } catch (\RuntimeException) {
                $exitCode = -1;
            }
        }

        $cmdChannel->close();
        $this->updateActivity();
        return new CommandResult($output, $error, $exitCode);
    }

    public function disconnect(): void
    {
        if ($this->bastionConnection !== null) {
            $this->bastionConnection = null;
            return;
        }

        if ($this->resource !== null) {
            ($this->sessionCloser)($this->resource);
            $this->resource = null;
            $this->sftpClient = null;
            $this->scpClient = null;
        }
    }

    public function isConnected(): bool
    {
        if ($this->bastionConnection !== null) {
            return $this->bastionConnection->isConnected();
        }

        return $this->resource !== null;
    }

    public function sftp(): SFTPClient
    {
        if (!$this->isConnected()) {
            throw new ConnectionException('SSH connection is not established.');
        }

        $this->ensureAlive();

        if ($this->sftpClient !== null) {
            return $this->sftpClient;
        }

        $this->updateActivity();
        return $this->sftpClient = new SFTPClient($this->resource);
    }

    public function scp(): ScpClient
    {
        if (!$this->isConnected()) {
            throw new ConnectionException('SSH connection is not established.');
        }

        $this->ensureAlive();

        if ($this->scpClient !== null) {
            return $this->scpClient;
        }

        $this->updateActivity();
        return $this->scpClient = new ScpClient($this->resource);
    }

    public function shell(?string $termType = 'xterm-256color', int $width = 80, int $height = 25): ShellSession
    {
        if (!$this->isConnected()) {
            throw new ConnectionException('SSH connection is not established.');
        }

        $this->ensureAlive();

        $channel = ($this->shellOpener)($this->resource, $termType, $width, $height);
        if ($channel === false || $channel === null) {
            throw new ConnectionException('Unable to open interactive shell session.');
        }

        $this->updateActivity();

        return new ShellSession($channel, $this->closer, $width, $height);
    }

    public function tunnel(string $host, int $port): Tunnel
    {
        if (!$this->isConnected()) {
            throw new ConnectionException('SSH connection is not established.');
        }

        $this->ensureAlive();

        $stream = ($this->tunnelOpener)($this->resource, $host, $port);
        if ($stream === false || $stream === null) {
            throw new ConnectionException(
                \sprintf('Unable to open tunnel to %s:%d.', $host, $port),
            );
        }

        $this->updateActivity();
        return new Tunnel($stream);
    }

    public function proxyTo(string $targetHost, string $targetUser, int $targetPort = 22): self
    {
        if (!$this->isConnected()) {
            throw new ConnectionException('SSH connection is not established.');
        }

        $proxy = new self(
            $this->auth,
            $targetUser,
            maxOutputSize: $this->maxOutputSize,
            commandTimeout: $this->commandTimeout,
        );

        $proxy->bastionConnection = $this;
        $proxy->bastionTargetHost = $targetHost;
        $proxy->bastionTargetPort = $targetPort;

        return $proxy;
    }

    public function pipeline(callable $configure, bool $haltOnFailure = true): PipelineResult
    {
        $pipeline = new CommandPipeline();
        $configured = $configure($pipeline);
        if ($configured instanceof CommandPipeline) {
            $pipeline = $configured;
        }

        return $pipeline->run(fn (string $command): CommandResult => $this->execute($command), $haltOnFailure);
    }

    private function ensureAlive(): void
    {
        if ($this->keepaliveInterval < 1) {
            return;
        }

        if (\time() - $this->lastActivity < $this->keepaliveInterval) {
            return;
        }

        ($this->keepaliveSender)($this->resource);
        $this->updateActivity();
    }

    private function updateActivity(): void
    {
        $this->lastActivity = \time();
    }

    private function nativeShellOpener(mixed $resource, ?string $termType, int $width, int $height): mixed
    {
        if (!\is_resource($resource)) {
            throw new ConnectionException('SSH connection resource is invalid.');
        }

        return \ssh2_shell($resource, $termType);
    }

    private function nativeTunnelOpener(mixed $resource, string $host, int $port): mixed
    {
        if (!\is_resource($resource)) {
            throw new ConnectionException('SSH connection resource is invalid.');
        }

        return \ssh2_tunnel($resource, $host, $port);
    }

    private function nativeKeepaliveSender(mixed $resource): bool
    {
        if (!\is_resource($resource)) {
            return false;
        }

        $channel = \ssh2_exec($resource, 'echo ping');
        if ($channel === false || $channel === null || !\is_resource($channel)) {
            return false;
        }

        \stream_get_contents($channel);
        \fclose($channel);

        return true;
    }

    /**
     * @param array<string, string> $methods
     */
    private function nativeConnector(string $host, int $port, array $methods): mixed
    {
        return \ssh2_connect($host, $port, $methods);
    }

    private function nativeExecutor(mixed $resource, string $command): mixed
    {
        if (!\is_resource($resource)) {
            throw new ConnectionException('SSH connection resource is invalid.');
        }

        return \ssh2_exec($resource, $command);
    }

    private function nativeCloser(mixed $stream): bool
    {
        if (\is_resource($stream)) {
            return \fclose($stream);
        }

        return true;
    }

    private function nativeSessionCloser(mixed $resource): bool
    {
        if (\is_resource($resource)) {
            return \ssh2_disconnect($resource);
        }

        return true;
    }

    private function nativeFingerprintChecker(mixed $resource, string $expected): bool
    {
        if (!\is_resource($resource)) {
            return false;
        }

        if (!\function_exists('ssh2_fingerprint')) {
            throw new \RuntimeException('ssh2_fingerprint is not available; cannot verify host key.');
        }

        /** @var string|false $fingerprint */
        $fingerprint = \ssh2_fingerprint($resource, \SSH2_FINGERPRINT_SHA1 | \SSH2_FINGERPRINT_HEX);
        if ($fingerprint === false) {
            throw new \RuntimeException('Unable to retrieve SSH host key fingerprint.');
        }

        return \hash_equals($expected, $fingerprint);
    }

    private function extractExitCode(string &$output, string $marker): ?int
    {
        if (!\preg_match('/\n' . \preg_quote($marker, '/') . '(\\d+)$/', $output, $matches)) {
            return null;
        }

        $output = (string) \preg_replace('/\n' . \preg_quote($marker, '/') . '\\d+$/', '', $output);
        return (int) $matches[1];
    }
}
