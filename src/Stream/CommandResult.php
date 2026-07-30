<?php

namespace MonkeysLegion\SSH\Stream;

class CommandResult
{
    public function __construct(
        public readonly string $output,
        public readonly string $error,
        public readonly int $exitCode,
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->exitCode === 0;
    }

    public function failed(): bool
    {
        return !$this->isSuccessful();
    }
}
