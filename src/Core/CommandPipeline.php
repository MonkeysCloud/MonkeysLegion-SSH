<?php

namespace MonkeysLegion\SSH\Core;

use MonkeysLegion\SSH\Stream\CommandResult;

class CommandPipeline
{
    /**
     * @var array<int, string|\Closure(?CommandResult, array<string, mixed>): string>
     */
    private array $steps = [];

    /**
     * @var array<string, mixed>
     */
    private array $state = [];

    /**
     * @param \Closure(?CommandResult, array<string, mixed>): string|string $step
     */
    public function add(string|\Closure $step): self
    {
        $this->steps[] = $step;
        return $this;
    }

    public function withState(string $key, mixed $value): self
    {
        $this->state[$key] = $value;
        return $this;
    }

    public function run(callable $executor, bool $haltOnFailure = true): PipelineResult
    {
        $results = [];
        $previous = null;

        foreach ($this->steps as $index => $step) {
            $command = \is_string($step) ? $step : $step($previous, $this->state);
            if ($command === '') {
                throw new \InvalidArgumentException('Pipeline step resolved to an empty command.');
            }

            /** @var CommandResult $result */
            $result = $executor($command);
            $results[] = $result;
            $previous = $result;
            $this->state['last_command'] = $command;
            $this->state['last_exit_code'] = $result->exitCode;

            if ($haltOnFailure && $result->failed()) {
                return new PipelineResult($results, true, $index, $this->state);
            }
        }

        return new PipelineResult($results, false, null, $this->state);
    }
}
