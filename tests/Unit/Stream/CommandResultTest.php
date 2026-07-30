<?php

namespace Tests\Unit\Stream;

use MonkeysLegion\SSH\Stream\CommandResult;
use PHPUnit\Framework\TestCase;

class CommandResultTest extends TestCase
{
    public function test_command_result_with_zero_exit_code_is_successful(): void
    {
        $result = new CommandResult('output', '', 0);
        $this->assertTrue($result->isSuccessful());
        $this->assertFalse($result->failed());
    }

    public function test_command_result_with_non_zero_exit_code_failed(): void
    {
        $result = new CommandResult('', 'error', 1);
        $this->assertTrue($result->failed());
        $this->assertFalse($result->isSuccessful());
    }
}
