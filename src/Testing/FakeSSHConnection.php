<?php

namespace MonkeysLegion\SSH\Testing;

use MonkeysLegion\SSH\Core\CommandPipeline;
use MonkeysLegion\SSH\Core\PipelineResult;
use MonkeysLegion\SSH\Core\SSHConnection;
use MonkeysLegion\SSH\Exceptions\ConnectionException;
use MonkeysLegion\SSH\SFTP\ScpClient;
use MonkeysLegion\SSH\SFTP\SFTPClient;
use MonkeysLegion\SSH\Stream\CommandChannel;
use MonkeysLegion\SSH\Stream\CommandResult;
use MonkeysLegion\SSH\Stream\ShellSession;
use MonkeysLegion\SSH\Stream\Tunnel;

class FakeSSHConnection extends SSHConnection
{
    private bool $connected = true;
    private ?SFTPClient $fakeSftp = null;

    public function __construct(private SSHFakeRegistry $registry)
    {
    }

    public function connect(string $host, int $port = 22, int $timeout = 10, ?string $expectedFingerprint = null): self
    {
        $this->connected = true;
        return $this;
    }

    public function execute(string $command, ?int $timeout = null): CommandResult
    {
        if (!$this->connected) {
            throw new ConnectionException('SSH fake connection is disconnected.');
        }

        $this->registry->record($command);
        return $this->registry->resultFor($command);
    }

    public function disconnect(): void
    {
        $this->connected = false;
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * Override sftp() to avoid calling the parent's broken implementation.
     *
     * If a fake SFTP client has been set via setFakeSftp(), it is returned.
     * Otherwise a clear exception is thrown explaining that SFTP faking
     * is not configured.
     */
    public function sftp(): SFTPClient
    {
        if (!$this->connected) {
            throw new ConnectionException('SSH fake connection is disconnected.');
        }

        if ($this->fakeSftp !== null) {
            return $this->fakeSftp;
        }

        throw new \RuntimeException(
            'SFTP is not available in fake mode. Call setFakeSftp() with a mock SFTPClient instance to enable SFTP faking.',
        );
    }

    public function setFakeSftp(?SFTPClient $sftp): void
    {
        $this->fakeSftp = $sftp;
    }

    public function channel(string $command): CommandChannel
    {
        throw new ConnectionException('Channel is not available in fake mode.');
    }

    public function shell(?string $termType = null, int $width = 80, int $height = 25): ShellSession
    {
        throw new ConnectionException('Shell is not available in fake mode.');
    }

    public function tunnel(string $host, int $port): Tunnel
    {
        throw new ConnectionException('Tunnel is not available in fake mode.');
    }

    public function scp(): ScpClient
    {
        if (!$this->connected) {
            throw new ConnectionException('SSH fake connection is disconnected.');
        }

        throw new \RuntimeException(
            'SCP is not available in fake mode. Instantiate ScpClient directly with a mock session resource.',
        );
    }

    public function proxyTo(string $targetHost, string $targetUser, int $targetPort = 22): SSHConnection
    {
        throw new ConnectionException('Proxy is not available in fake mode. Use SSHConnection with a real bastion connection.');
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
}
