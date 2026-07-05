<?php

namespace Tests\Unit\Facades;

use MonkeysLegion\SSH\Core\ConnectionBuilder;
use MonkeysLegion\SSH\Core\SSHConnection;
use MonkeysLegion\SSH\Core\SSHManager;
use MonkeysLegion\SSH\Facades\SSH;
use MonkeysLegion\SSH\Stream\CommandResult;
use PHPUnit\Framework\TestCase;

class SSHTest extends TestCase
{
    protected function tearDown(): void
    {
        SSH::setManager(new SSHManager());
    }

    public function test_runtime_returns_connection_builder(): void
    {
        SSH::setManager(new SSHManager());

        $builder = SSH::runtime();
        $this->assertInstanceOf(ConnectionBuilder::class, $builder);
    }

    public function test_register_registers_profile_in_manager(): void
    {
        $manager = new SSHManager();
        SSH::setManager($manager);

        SSH::register('runtime', [
            'host' => '127.0.0.1',
            'username' => 'runtime',
            'auth' => 'password',
            'password' => 'secret',
        ]);

        $this->assertTrue($manager->hasProfile('runtime'));
    }

    public function test_static_proxy_forwards_calls_to_default_connection(): void
    {
        $connection = $this->createMock(SSHConnection::class);
        $connection
            ->expects($this->once())
            ->method('isConnected')
            ->willReturn(true);

        $manager = $this->createMock(SSHManager::class);
        $manager
            ->expects($this->once())
            ->method('connection')
            ->with(null)
            ->willReturn($connection);

        SSH::setManager($manager);
        $this->assertTrue(SSH::isConnected());
    }

    public function test_fake_allows_command_stubbing_without_socket_connection(): void
    {
        SSH::fake();
        SSH::fakeCommand('whoami', new CommandResult('fake-user', '', 0));

        $result = SSH::execute('whoami');
        $this->assertSame('fake-user', $result->output);
        $this->assertSame(0, $result->exitCode);

        SSH::assertExecuted('whoami');
    }

    public function test_fake_default_response_is_used_when_command_is_not_stubbed(): void
    {
        SSH::fake();
        SSH::fakeDefault(new CommandResult('', 'missing', 127));

        $result = SSH::execute('unknown-command');
        $this->assertSame(127, $result->exitCode);
        $this->assertSame('missing', $result->error);
    }
}
