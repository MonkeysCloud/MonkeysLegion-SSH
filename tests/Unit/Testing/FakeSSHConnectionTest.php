<?php

namespace Tests\Unit\Testing;

use MonkeysLegion\SSH\Core\PipelineResult;
use MonkeysLegion\SSH\Exceptions\ConnectionException;
use MonkeysLegion\SSH\SFTP\SFTPClient;
use MonkeysLegion\SSH\Stream\CommandResult;
use MonkeysLegion\SSH\Testing\FakeSSHConnection;
use MonkeysLegion\SSH\Testing\SSHFakeRegistry;
use PHPUnit\Framework\TestCase;

class FakeSSHConnectionTest extends TestCase
{
    public function test_connect_returns_self_and_sets_connected_state(): void
    {
        $registry = new SSHFakeRegistry();
        $connection = new FakeSSHConnection($registry);

        $this->assertSame($connection, $connection->connect('127.0.0.1'));
        $this->assertTrue($connection->isConnected());
    }

    public function test_is_connected_is_true_by_default(): void
    {
        $registry = new SSHFakeRegistry();
        $connection = new FakeSSHConnection($registry);

        $this->assertTrue($connection->isConnected());
    }

    public function test_disconnect_sets_connected_to_false(): void
    {
        $registry = new SSHFakeRegistry();
        $connection = new FakeSSHConnection($registry);

        $connection->disconnect();

        $this->assertFalse($connection->isConnected());
    }

    public function test_reconnect_after_disconnect(): void
    {
        $registry = new SSHFakeRegistry();
        $connection = new FakeSSHConnection($registry);

        $connection->disconnect();
        $this->assertFalse($connection->isConnected());

        $connection->connect('127.0.0.1');
        $this->assertTrue($connection->isConnected());
    }

    public function test_execute_returns_stubbed_response(): void
    {
        $registry = new SSHFakeRegistry();
        $registry->replyWith('whoami', 'deploy-user', '', 0);

        $connection = new FakeSSHConnection($registry);

        $result = $connection->execute('whoami');

        $this->assertSame('deploy-user', $result->output);
        $this->assertSame(0, $result->exitCode);
    }

    public function test_execute_returns_default_response_for_unstubbed_commands(): void
    {
        $registry = new SSHFakeRegistry();
        $registry->setDefault(new CommandResult('', 'not found', 127));

        $connection = new FakeSSHConnection($registry);

        $result = $connection->execute('unknown-command');

        $this->assertSame(127, $result->exitCode);
    }

    public function test_execute_records_command_in_registry(): void
    {
        $registry = new SSHFakeRegistry();
        $connection = new FakeSSHConnection($registry);

        $connection->execute('whoami');
        $connection->execute('ls -la');

        $this->assertTrue($registry->wasExecuted('whoami'));
        $this->assertTrue($registry->wasExecuted('ls -la'));
        $this->assertSame(['whoami', 'ls -la'], $registry->executedCommands());
    }

    public function test_execute_throws_when_disconnected(): void
    {
        $registry = new SSHFakeRegistry();
        $connection = new FakeSSHConnection($registry);

        $connection->disconnect();

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('SSH fake connection is disconnected.');
        $connection->execute('whoami');
    }

    public function test_sftp_throws_when_no_fake_sftp_set(): void
    {
        $registry = new SSHFakeRegistry();
        $connection = new FakeSSHConnection($registry);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SFTP is not available in fake mode');
        $connection->sftp();
    }

    public function test_sftp_throws_when_disconnected(): void
    {
        $registry = new SSHFakeRegistry();
        $connection = new FakeSSHConnection($registry);

        $connection->disconnect();

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('SSH fake connection is disconnected.');
        $connection->sftp();
    }

    public function test_sftp_returns_fake_sftp_when_set(): void
    {
        $registry = new SSHFakeRegistry();
        $connection = new FakeSSHConnection($registry);

        $fakeSftp = $this->createMock(SFTPClient::class);
        $connection->setFakeSftp($fakeSftp);

        $this->assertSame($fakeSftp, $connection->sftp());
    }

    public function test_set_fake_sftp_can_be_cleared_with_null(): void
    {
        $registry = new SSHFakeRegistry();
        $connection = new FakeSSHConnection($registry);

        $connection->setFakeSftp($this->createMock(SFTPClient::class));
        $connection->setFakeSftp(null);

        $this->expectException(\RuntimeException::class);
        $connection->sftp();
    }

    public function test_pipeline_executes_commands_in_order(): void
    {
        $registry = new SSHFakeRegistry();
        $registry->replyWith('echo one', 'one');
        $registry->replyWith('echo two', 'two');
        $registry->replyWith('echo three', 'three');

        $connection = new FakeSSHConnection($registry);

        $result = $connection->pipeline(static function ($pipeline): void {
            $pipeline->add('echo one')->add('echo two')->add('echo three');
        });

        $this->assertTrue($result->succeeded());
        $this->assertFalse($result->halted);
        $this->assertCount(3, $result->results);
        $this->assertSame('one', $result->results[0]->output);
        $this->assertSame('two', $result->results[1]->output);
        $this->assertSame('three', $result->results[2]->output);
    }

    public function test_pipeline_halts_on_failure_by_default(): void
    {
        $registry = new SSHFakeRegistry();
        $registry->replyWith('echo one', 'one');
        $registry->replyWith('false', '', '', 1);
        $registry->replyWith('echo three', 'three');

        $connection = new FakeSSHConnection($registry);

        $result = $connection->pipeline(static function ($pipeline): void {
            $pipeline->add('echo one')->add('false')->add('echo three');
        });

        $this->assertTrue($result->halted);
        $this->assertFalse($result->succeeded());
        $this->assertSame(1, $result->failedStep);
        $this->assertCount(2, $result->results);
    }

    public function test_pipeline_continues_on_failure_when_halt_disabled(): void
    {
        $registry = new SSHFakeRegistry();
        $registry->replyWith('echo one', 'one');
        $registry->replyWith('false', '', '', 1);
        $registry->replyWith('echo three', 'three');

        $connection = new FakeSSHConnection($registry);

        $result = $connection->pipeline(
            static function ($pipeline): void {
                $pipeline->add('echo one')->add('false')->add('echo three');
            },
            false,
        );

        $this->assertFalse($result->halted);
        $this->assertCount(3, $result->results);
    }

    public function test_pipeline_with_closure_steps(): void
    {
        $registry = new SSHFakeRegistry();
        $registry->replyWith('echo hello', 'hello');
        $registry->replyWith('echo world', 'world');

        $connection = new FakeSSHConnection($registry);

        $result = $connection->pipeline(static function ($pipeline): void {
            $pipeline
                ->add('echo hello')
                ->add(static fn (): string => 'echo world');
        });

        $this->assertTrue($result->succeeded());
        $this->assertCount(2, $result->results);
        $this->assertSame('world', $result->results[1]->output);
    }

    public function test_pipeline_records_all_executed_commands(): void
    {
        $registry = new SSHFakeRegistry();
        $connection = new FakeSSHConnection($registry);

        $connection->pipeline(static function ($pipeline): void {
            $pipeline->add('cmd-a')->add('cmd-b');
        });

        $this->assertSame(['cmd-a', 'cmd-b'], $registry->executedCommands());
    }

    public function test_pipeline_returns_pipeline_result_instance(): void
    {
        $registry = new SSHFakeRegistry();
        $connection = new FakeSSHConnection($registry);

        $result = $connection->pipeline(static function ($pipeline): void {
            $pipeline->add('echo test');
        });

        $this->assertInstanceOf(PipelineResult::class, $result);
    }
}
