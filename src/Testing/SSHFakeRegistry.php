<?php

namespace MonkeysLegion\SSH\Testing;

use MonkeysLegion\SSH\Stream\CommandResult;

class SSHFakeRegistry
{
    /**
     * @var array<string, CommandResult>
     */
    private array $responses = [];

    /**
     * @var list<string>
     */
    private array $executedCommands = [];

    private CommandResult $defaultResponse;

    public function __construct()
    {
        $this->defaultResponse = new CommandResult('', '', 0);
    }

    public function reply(string $command, CommandResult $result): self
    {
        $this->responses[$command] = $result;
        return $this;
    }

    public function replyWith(string $command, string $output = '', string $error = '', int $exitCode = 0): self
    {
        return $this->reply($command, new CommandResult($output, $error, $exitCode));
    }

    public function setDefault(CommandResult $result): self
    {
        $this->defaultResponse = $result;
        return $this;
    }

    public function record(string $command): void
    {
        $this->executedCommands[] = $command;
    }

    public function resultFor(string $command): CommandResult
    {
        return $this->responses[$command] ?? $this->defaultResponse;
    }

    /**
     * @return list<string>
     */
    public function executedCommands(): array
    {
        return $this->executedCommands;
    }

    public function wasExecuted(string $command): bool
    {
        return \in_array($command, $this->executedCommands, true);
    }

    public function clearExecutedCommands(): void
    {
        $this->executedCommands = [];
    }
}
