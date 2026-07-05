<?php

namespace Tests\Unit\Core;

use MonkeysLegion\SSH\Authentication\PasswordAuthentication;
use MonkeysLegion\SSH\Core\CommandPipeline;
use MonkeysLegion\SSH\Core\SSHConnection;
use MonkeysLegion\SSH\Exceptions\ConnectionException;
use MonkeysLegion\SSH\Exceptions\ConnectionRefusedException;
use MonkeysLegion\SSH\Stream\StreamHandler;
use PHPUnit\Framework\TestCase;

class SSHConnectionTest extends TestCase
{
    public function test_connection_can_be_instantiated(): void
    {
        $auth = new PasswordAuthentication('password');
        $connection = new SSHConnection($auth, 'remote-user');

        $this->assertInstanceOf(SSHConnection::class, $connection);
    }

    public function test_connect_throws_when_connector_returns_false(): void
    {
        $auth = new PasswordAuthentication('password', static fn (): bool => true);
        $connection = new SSHConnection(
            $auth,
            'remote-user',
            new StreamHandler(),
            static fn (): bool => false
        );

        $this->expectException(ConnectionRefusedException::class);
        $connection->connect('127.0.0.1', 22, 1);
    }

    public function test_execute_returns_command_result(): void
    {
        $resource = \fopen('php://temp', 'rb+');
        $channel = \fopen('php://temp', 'rb+');
        $this->assertIsResource($resource);
        $this->assertIsResource($channel);

        $streamHandler = $this->createMock(StreamHandler::class);
        $streamHandler->expects($this->once())->method('read')->with($channel)->willReturn("out\n");
        $streamHandler->expects($this->once())->method('readError')->with($channel)->willReturn("err\n");
        $streamHandler->expects($this->once())->method('getExitStatus')->with($channel)->willReturn(17);

        $auth = new PasswordAuthentication('password', static fn (): bool => true);
        $connection = new SSHConnection(
            $auth,
            'remote-user',
            $streamHandler,
            static fn () => $resource,
            static fn () => $channel,
            static fn (): bool => true
        );
        $connection->connect('localhost');

        $result = $connection->execute('echo test');
        $this->assertSame("out\n", $result->output);
        $this->assertSame("err\n", $result->error);
        $this->assertSame(17, $result->exitCode);
        $this->assertTrue($connection->isConnected());
    }

    public function test_execute_throws_when_not_connected(): void
    {
        $auth = new PasswordAuthentication('password');
        $connection = new SSHConnection($auth, 'remote-user');

        $this->expectException(ConnectionException::class);
        $connection->execute('echo test');
    }

    public function test_pipeline_executes_commands_and_halts_on_failure(): void
    {
        $resource = \fopen('php://temp', 'rb+');
        $this->assertIsResource($resource);

        $commands = [];
        $channels = [];
        $streamHandler = $this->createMock(StreamHandler::class);
        $streamHandler->method('read')->willReturn("ok\n");
        $streamHandler->method('readError')->willReturn('');
        $streamHandler->method('getExitStatus')->willReturnOnConsecutiveCalls(0, 1);

        $auth = new PasswordAuthentication('password', static fn (): bool => true);
        $connection = new SSHConnection(
            $auth,
            'remote-user',
            $streamHandler,
            static fn () => $resource,
            static function (mixed $session, string $command) use (&$commands): mixed {
                $commands[] = $command;
                return \fopen('php://temp', 'rb+');
            },
            static function (mixed $channel) use (&$channels): bool {
                $channels[] = $channel;
                return true;
            }
        );
        $connection->connect('localhost');

        $result = $connection->pipeline(static function (CommandPipeline $pipeline): void {
            $pipeline->add('echo one')->add('echo two')->add('echo three');
        });

        $this->assertTrue($result->halted);
        $this->assertSame(1, $result->failedStep);
        $this->assertCount(2, $commands);
        $this->assertCount(2, $channels);
    }

    public function test_sftp_throws_when_not_connected(): void
    {
        $auth = new PasswordAuthentication('password');
        $connection = new SSHConnection($auth, 'remote-user');

        $this->expectException(ConnectionException::class);
        $connection->sftp();
    }
}
