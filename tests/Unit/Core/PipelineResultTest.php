<?php

namespace Tests\Unit\Core;

use MonkeysLegion\SSH\Core\PipelineResult;
use MonkeysLegion\SSH\Stream\CommandResult;
use PHPUnit\Framework\TestCase;

class PipelineResultTest extends TestCase
{
    public function test_empty_pipeline_succeeds_and_was_empty(): void
    {
        $result = new PipelineResult([], false, null, []);

        $this->assertTrue($result->succeeded());
        $this->assertFalse($result->failed());
        $this->assertTrue($result->wasEmpty());
    }

    public function test_all_successful_commands_succeed(): void
    {
        $results = [
            new CommandResult('output-1', '', 0),
            new CommandResult('output-2', '', 0),
        ];

        $result = new PipelineResult($results, false, null, []);

        $this->assertTrue($result->succeeded());
        $this->assertFalse($result->failed());
        $this->assertFalse($result->wasEmpty());
    }

    public function test_halted_pipeline_fails(): void
    {
        $results = [
            new CommandResult('output-1', '', 0),
            new CommandResult('', 'error', 1),
        ];

        $result = new PipelineResult($results, true, 1, []);

        $this->assertFalse($result->succeeded());
        $this->assertTrue($result->failed());
    }

    public function test_last_command_failure_without_halt_fails(): void
    {
        $results = [
            new CommandResult('output-1', '', 0),
            new CommandResult('', 'error', 1),
        ];

        // Not halted, but last command failed
        $result = new PipelineResult($results, false, null, []);

        $this->assertFalse($result->succeeded());
        $this->assertTrue($result->failed());
    }

    public function test_failure_in_middle_but_not_halted_with_successful_last_succeeds(): void
    {
        $results = [
            new CommandResult('output-1', '', 0),
            new CommandResult('', 'error', 1),  // middle failure, not halted
            new CommandResult('output-3', '', 0), // last succeeds
        ];

        $result = new PipelineResult($results, false, null, []);

        $this->assertTrue($result->succeeded());
        $this->assertFalse($result->failed());
    }

    public function test_state_is_preserved(): void
    {
        $state = ['target' => 'world', 'last_exit_code' => 0];

        $result = new PipelineResult([], false, null, $state);

        $this->assertSame($state, $result->state);
    }

    public function test_failed_step_is_accessible(): void
    {
        $result = new PipelineResult(
            [new CommandResult('', 'error', 1)],
            true,
            0,
            []
        );

        $this->assertSame(0, $result->failedStep);
    }

    public function test_failed_step_is_null_when_not_halted(): void
    {
        $result = new PipelineResult(
            [new CommandResult('ok', '', 0)],
            false,
            null,
            []
        );

        $this->assertNull($result->failedStep);
    }
}
