<?php

namespace Tests\Unit\Core;

use MonkeysLegion\SSH\Core\CommandPipeline;
use MonkeysLegion\SSH\Stream\CommandResult;
use PHPUnit\Framework\TestCase;

class CommandPipelineTest extends TestCase
{
    public function test_pipeline_halts_on_failure_when_enabled(): void
    {
        $pipeline = new CommandPipeline()
            ->add('echo one')
            ->add('false')
            ->add('echo three');

        $result = $pipeline->run(
            static fn (string $command): CommandResult => $command === 'false'
                ? new CommandResult('', 'failed', 1)
                : new CommandResult('ok', '', 0),
            true,
        );

        $this->assertTrue($result->halted);
        $this->assertSame(1, $result->failedStep);
        $this->assertCount(2, $result->results);
    }

    public function test_pipeline_closure_step_receives_previous_result_and_state(): void
    {
        $pipeline = new CommandPipeline()
            ->withState('target', 'world')
            ->add('echo hello')
            ->add(static function (?CommandResult $previous, array $state): string {
                return $previous?->isSuccessful() === true
                    ? 'echo ' . $state['target']
                    : 'echo fallback';
            });

        $commands = [];
        $result = $pipeline->run(static function (string $command) use (&$commands): CommandResult {
            $commands[] = $command;
            return new CommandResult('ok', '', 0);
        }, false);

        $this->assertSame(['echo hello', 'echo world'], $commands);
        $this->assertFalse($result->halted);
        $this->assertSame(0, $result->state['last_exit_code']);
    }
}
