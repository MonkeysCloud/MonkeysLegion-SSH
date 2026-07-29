<?php

namespace Tests\Unit\Testing;

use MonkeysLegion\SSH\Stream\CommandResult;
use MonkeysLegion\SSH\Testing\SSHFakeRegistry;
use PHPUnit\Framework\TestCase;

class SSHFakeRegistryTest extends TestCase
{
    public function test_default_response_is_successful_empty_result(): void
    {
        $registry = new SSHFakeRegistry();

        $result = $registry->resultFor('any-command');

        $this->assertTrue($result->isSuccessful());
        $this->assertSame('', $result->output);
        $this->assertSame('', $result->error);
        $this->assertSame(0, $result->exitCode);
    }

    public function test_reply_stores_command_specific_result(): void
    {
        $registry = new SSHFakeRegistry();
        $expected = new CommandResult('root', '', 0);

        $registry->reply('whoami', $expected);

        $this->assertSame($expected, $registry->resultFor('whoami'));
    }

    public function test_reply_with_creates_command_result_from_parameters(): void
    {
        $registry = new SSHFakeRegistry();

        $registry->replyWith('ls -la', 'file1.txt', 'warning', 0);

        $result = $registry->resultFor('ls -la');
        $this->assertSame('file1.txt', $result->output);
        $this->assertSame('warning', $result->error);
        $this->assertSame(0, $result->exitCode);
    }

    public function test_reply_with_defaults_to_empty_successful_result(): void
    {
        $registry = new SSHFakeRegistry();

        $registry->replyWith('echo hello');

        $result = $registry->resultFor('echo hello');
        $this->assertSame('', $result->output);
        $this->assertSame('', $result->error);
        $this->assertSame(0, $result->exitCode);
    }

    public function test_set_default_overrides_default_response(): void
    {
        $registry = new SSHFakeRegistry();
        $default = new CommandResult('', 'command not found', 127);

        $registry->setDefault($default);

        $result = $registry->resultFor('unstubbed-command');
        $this->assertSame(127, $result->exitCode);
        $this->assertSame('command not found', $result->error);
    }

    public function test_command_specific_reply_takes_precedence_over_default(): void
    {
        $registry = new SSHFakeRegistry();
        $default = new CommandResult('', 'not found', 127);
        $specific = new CommandResult('root', '', 0);

        $registry->setDefault($default);
        $registry->reply('whoami', $specific);

        $this->assertSame($specific, $registry->resultFor('whoami'));
        $this->assertSame($default, $registry->resultFor('other-command'));
    }

    public function test_record_tracks_executed_commands(): void
    {
        $registry = new SSHFakeRegistry();

        $registry->record('whoami');
        $registry->record('ls -la');
        $registry->record('whoami'); // duplicate

        $this->assertSame(['whoami', 'ls -la', 'whoami'], $registry->executedCommands());
    }

    public function test_was_executed_returns_true_for_recorded_commands(): void
    {
        $registry = new SSHFakeRegistry();
        $registry->record('whoami');

        $this->assertTrue($registry->wasExecuted('whoami'));
    }

    public function test_was_executed_returns_false_for_unrecorded_commands(): void
    {
        $registry = new SSHFakeRegistry();

        $this->assertFalse($registry->wasExecuted('whoami'));
    }

    public function test_clear_executed_commands_resets_history(): void
    {
        $registry = new SSHFakeRegistry();
        $registry->record('whoami');
        $registry->record('ls');

        $registry->clearExecutedCommands();

        $this->assertSame([], $registry->executedCommands());
        $this->assertFalse($registry->wasExecuted('whoami'));
    }

    public function test_clear_executed_commands_does_not_clear_responses(): void
    {
        $registry = new SSHFakeRegistry();
        $registry->replyWith('whoami', 'root');
        $registry->record('whoami');

        $registry->clearExecutedCommands();

        // Responses should still be available
        $result = $registry->resultFor('whoami');
        $this->assertSame('root', $result->output);
    }

    public function test_reply_returns_self_for_chaining(): void
    {
        $registry = new SSHFakeRegistry();

        $this->assertSame($registry, $registry->reply('cmd', new CommandResult('', '', 0)));
    }

    public function test_reply_with_returns_self_for_chaining(): void
    {
        $registry = new SSHFakeRegistry();

        $this->assertSame($registry, $registry->replyWith('cmd'));
    }

    public function test_set_default_returns_self_for_chaining(): void
    {
        $registry = new SSHFakeRegistry();

        $this->assertSame($registry, $registry->setDefault(new CommandResult('', '', 0)));
    }

    public function test_chaining_multiple_replies(): void
    {
        $registry = new SSHFakeRegistry();

        $registry
            ->replyWith('whoami', 'root')
            ->replyWith('pwd', '/home/user')
            ->setDefault(new CommandResult('', 'unknown', 127));

        $this->assertSame('root', $registry->resultFor('whoami')->output);
        $this->assertSame('/home/user', $registry->resultFor('pwd')->output);
        $this->assertSame(127, $registry->resultFor('unknown')->exitCode);
    }
}
