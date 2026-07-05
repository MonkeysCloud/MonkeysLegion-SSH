<?php

namespace MonkeysLegion\SSH\Testing;

use MonkeysLegion\SSH\Core\CommandPipeline;
use MonkeysLegion\SSH\Core\PipelineResult;
use MonkeysLegion\SSH\Core\SSHConnection;
use MonkeysLegion\SSH\Exceptions\ConnectionException;
use MonkeysLegion\SSH\Stream\CommandResult;

class FakeSSHConnection extends SSHConnection
{
    private bool $connected = true;

    public function __construct(private SSHFakeRegistry $registry)
    {
    }

    public function connect(string $host, int $port = 22, int $timeout = 10): self
    {
        $this->connected = true;
        return $this;
    }

    public function execute(string $command): CommandResult
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
