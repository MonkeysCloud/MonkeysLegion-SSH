<?php

namespace MonkeysLegion\SSH\Core;

use MonkeysLegion\SSH\Authentication\AuthenticationDriver;
use MonkeysLegion\SSH\Exceptions\AuthenticationFailedException;
use MonkeysLegion\SSH\Exceptions\ConnectionException;
use MonkeysLegion\SSH\Exceptions\ConnectionRefusedException;
use MonkeysLegion\SSH\SFTP\SFTPClient;
use MonkeysLegion\SSH\Stream\CommandResult;
use MonkeysLegion\SSH\Stream\StreamHandler;

class SSHConnection
{
    private mixed $resource = null;
    private StreamHandler $streamHandler;
    private ?SFTPClient $sftpClient = null;

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

    public function __construct(
        private AuthenticationDriver $auth,
        private string $username = '',
        ?StreamHandler $streamHandler = null,
        ?callable $connector = null,
        ?callable $executor = null,
        ?callable $closer = null
    ) {
        $this->streamHandler = $streamHandler ?? new StreamHandler();
        $this->connector = $connector !== null
            ? $connector(...)
            : $this->nativeConnector(...);
        $this->executor = $executor !== null
            ? $executor(...)
            : $this->nativeExecutor(...);
        $this->closer = $closer !== null
            ? $closer(...)
            : $this->nativeCloser(...);
    }

    public function connect(string $host, int $port = 22, int $timeout = 10): self
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

        try {
            $this->auth->authenticate($resource, $this->username);
        } catch (AuthenticationFailedException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new AuthenticationFailedException(
                'SSH authentication failed.',
                (int) $exception->getCode(),
                $exception
            );
        }

        $this->resource = $resource;
        return $this;
    }

    public function execute(string $command): CommandResult
    {
        if (!$this->isConnected()) {
            throw new ConnectionException('SSH connection is not established.');
        }

        $exitMarker = '__MLSSH_EXIT_CODE__';
        $wrappedCommand = \sprintf(
            'sh -lc %s',
            \escapeshellarg($command . '; printf "\n' . $exitMarker . '%s" "$?"')
        );

        $channel = ($this->executor)($this->resource, $wrappedCommand);
        if ($channel === false || $channel === null) {
            throw new ConnectionException('Unable to execute SSH command.');
        }

        $output = $this->streamHandler->read($channel);
        $error = $this->streamHandler->readError($channel);
        $exitCode = $this->extractExitCode($output, $exitMarker);
        if ($exitCode === null) {
            try {
                $exitCode = $this->streamHandler->getExitStatus($channel);
            } catch (\RuntimeException) {
                $exitCode = -1;
            }
        }

        ($this->closer)($channel);
        return new CommandResult($output, $error, $exitCode);
    }

    public function disconnect(): void
    {
        if ($this->resource !== null) {
            ($this->closer)($this->resource);
            $this->resource = null;
            $this->sftpClient = null;
        }
    }

    public function isConnected(): bool
    {
        return $this->resource !== null;
    }

    public function sftp(): SFTPClient
    {
        if (!$this->isConnected()) {
            throw new ConnectionException('SSH connection is not established.');
        }

        if ($this->sftpClient !== null) {
            return $this->sftpClient;
        }

        $this->sftpClient = new SFTPClient($this->resource);
        return $this->sftpClient;
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

    private function extractExitCode(string &$output, string $marker): ?int
    {
        if (!\preg_match('/\n' . \preg_quote($marker, '/') . '(\\d+)$/', $output, $matches)) {
            return null;
        }

        $output = (string) \preg_replace('/\n' . \preg_quote($marker, '/') . '\\d+$/', '', $output);
        return (int) $matches[1];
    }
}
