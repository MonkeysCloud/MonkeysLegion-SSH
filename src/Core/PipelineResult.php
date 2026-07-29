<?php

namespace MonkeysLegion\SSH\Core;

use MonkeysLegion\SSH\Stream\CommandResult;

class PipelineResult
{
    /**
     * @param array<int, CommandResult> $results
     * @param array<string, mixed> $state
     */
    public function __construct(
        public readonly array $results,
        public readonly bool $halted,
        public readonly ?int $failedStep,
        public readonly array $state
    ) {
    }

    public function succeeded(): bool
    {
        if ($this->results === []) {
            return true;
        }

        $last = $this->results[\count($this->results) - 1];
        return !$this->halted && $last->isSuccessful();
    }

    public function failed(): bool
    {
        return !$this->succeeded();
    }

    /**
     * Returns true when the pipeline contained zero steps.
     */
    public function wasEmpty(): bool
    {
        return $this->results === [];
    }
}
