<?php

namespace Tests\Unit\Core;

use MonkeysLegion\SSH\Authentication\PasswordAuthentication;
use MonkeysLegion\SSH\Core\CommandPipeline;
use MonkeysLegion\SSH\Core\SSHConnection;
use MonkeysLegion\SSH\Exceptions\ConnectionException;
use MonkeysLegion\SSH\Exceptions\ConnectionRefusedException;
use MonkeysLegion\SSH\Exceptions\HostKeyMismatchException;
use MonkeysLegion\SSH\Stream\CommandChannel;
use MonkeysLegion\SSH\Stream\ShellSession;
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
            static fn (): bool => false,
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
            static fn (): bool => true,
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
            },
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

    public function test_connect_throws_when_fingerprint_does_not_match(): void
    {
        $resource = \fopen('php://temp', 'rb+');
        $this->assertIsResource($resource);

        $sessionClosed = false;
        $auth = new PasswordAuthentication('password', static fn (): bool => true);
        $connection = new SSHConnection(
            $auth,
            'remote-user',
            new StreamHandler(),
            static fn (): mixed => $resource,
            null,
            null,
            static function (mixed $res) use (&$sessionClosed): bool {
                $sessionClosed = true;
                return true;
            },
            static fn (): bool => false, // fingerprint mismatch
        );

        $this->expectException(HostKeyMismatchException::class);
        $connection->connect('10.0.0.1', 22, 1, 'expected:fingerprint');

        // Session should be closed on fingerprint mismatch
        $this->assertTrue($sessionClosed);
    }

    public function test_connect_succeeds_when_fingerprint_matches(): void
    {
        $resource = \fopen('php://temp', 'rb+');
        $this->assertIsResource($resource);

        $auth = new PasswordAuthentication('password', static fn (): bool => true);
        $connection = new SSHConnection(
            $auth,
            'remote-user',
            new StreamHandler(),
            static fn (): mixed => $resource,
            null,
            null,
            null,
            static fn (): bool => true, // fingerprint matches
        );

        $connection->connect('10.0.0.1', 22, 1, 'expected:fingerprint');
        $this->assertTrue($connection->isConnected());
        $connection->disconnect();
    }

    public function test_connect_throws_when_username_is_empty(): void
    {
        $auth = new PasswordAuthentication('password');
        $connection = new SSHConnection($auth, '');

        $this->expectException(\InvalidArgumentException::class);
        $connection->connect('127.0.0.1', 22, 1);
    }

    // ----- keepalive -----

    public function test_keepalive_sends_ping_when_idle_before_execute(): void
    {
        $resource = \fopen('php://temp', 'rb+');
        $channel = \fopen('php://temp', 'rb+');
        $this->assertIsResource($resource);
        $this->assertIsResource($channel);

        $pingCalled = false;
        $streamHandler = $this->createMock(StreamHandler::class);
        $streamHandler->method('read')->willReturn("out\n");
        $streamHandler->method('readError')->willReturn('');
        $streamHandler->method('getExitStatus')->willReturn(0);

        $auth = new PasswordAuthentication('password', static fn (): bool => true);
        $connection = new SSHConnection(
            $auth,
            'remote-user',
            $streamHandler,
            static fn () => $resource,
            static fn () => $channel,
            static fn (): bool => true,
            null,
            null,
            static function (mixed $res) use (&$pingCalled): bool {
                $pingCalled = true;
                return true;
            },
            keepaliveInterval: 1,
        );
        $connection->connect('localhost');

        // Wait for keepalive interval to elapse
        \sleep(1);

        $connection->execute('echo test');
        $this->assertTrue($pingCalled);
        $connection->disconnect();
    }

    public function test_keepalive_does_not_ping_before_interval(): void
    {
        $resource = \fopen('php://temp', 'rb+');
        $channel = \fopen('php://temp', 'rb+');
        $this->assertIsResource($resource);
        $this->assertIsResource($channel);

        $pingCalled = 0;
        $streamHandler = $this->createMock(StreamHandler::class);
        $streamHandler->method('read')->willReturn("out\n");
        $streamHandler->method('readError')->willReturn('');
        $streamHandler->method('getExitStatus')->willReturn(0);

        $auth = new PasswordAuthentication('password', static fn (): bool => true);
        $connection = new SSHConnection(
            $auth,
            'remote-user',
            $streamHandler,
            static fn () => $resource,
            static fn () => $channel,
            static fn (): bool => true,
            null,
            null,
            static function (mixed $res) use (&$pingCalled): bool {
                ++$pingCalled;
                return true;
            },
            keepaliveInterval: 3600, // 1 hour — won't trigger
        );
        $connection->connect('localhost');

        $connection->execute('echo test');
        $this->assertSame(0, $pingCalled);
        $connection->disconnect();
    }

    // ----- timeout propagation -----

    public function test_execute_passes_timeout_to_stream_handler(): void
    {
        $resource = \fopen('php://temp', 'rb+');
        $channel = \fopen('php://temp', 'rb+');
        $this->assertIsResource($resource);
        $this->assertIsResource($channel);

        $streamHandler = $this->createMock(StreamHandler::class);
        $streamHandler->expects($this->once())->method('read')
            ->with($channel, $this->anything(), 30, $this->anything())
            ->willReturn("ok\n");
        $streamHandler->expects($this->once())->method('readError')
            ->with($channel, $this->anything(), 30, $this->anything())
            ->willReturn('');
        $streamHandler->expects($this->once())->method('getExitStatus')
            ->with($channel)
            ->willReturn(0);

        $auth = new PasswordAuthentication('password', static fn (): bool => true);
        $connection = new SSHConnection(
            $auth,
            'remote-user',
            $streamHandler,
            static fn () => $resource,
            static fn () => $channel,
            static fn (): bool => true,
        );
        $connection->connect('localhost');

        $connection->execute('echo test', 30);
        $connection->disconnect();
    }

    public function test_execute_uses_default_command_timeout(): void
    {
        $resource = \fopen('php://temp', 'rb+');
        $channel = \fopen('php://temp', 'rb+');
        $this->assertIsResource($resource);
        $this->assertIsResource($channel);

        $streamHandler = $this->createMock(StreamHandler::class);
        $streamHandler->expects($this->once())->method('read')
            ->with($channel, $this->anything(), 60, $this->anything())
            ->willReturn("ok\n");
        $streamHandler->expects($this->once())->method('readError')
            ->with($channel, $this->anything(), 60, $this->anything())
            ->willReturn('');
        $streamHandler->expects($this->once())->method('getExitStatus')
            ->with($channel)
            ->willReturn(0);

        $auth = new PasswordAuthentication('password', static fn (): bool => true);
        $connection = new SSHConnection(
            $auth,
            'remote-user',
            $streamHandler,
            static fn () => $resource,
            static fn () => $channel,
            static fn (): bool => true,
            null,
            null,
            null,
            null,
            commandTimeout: 60,
        );
        $connection->connect('localhost');

        $connection->execute('echo test');
        $connection->disconnect();
    }

    public function test_execute_per_command_timeout_overrides_default(): void
    {
        $resource = \fopen('php://temp', 'rb+');
        $channel = \fopen('php://temp', 'rb+');
        $this->assertIsResource($resource);
        $this->assertIsResource($channel);

        $streamHandler = $this->createMock(StreamHandler::class);
        // Per-command timeout 15 should override default 60
        $streamHandler->expects($this->once())->method('read')
            ->with($channel, $this->anything(), 15, $this->anything())
            ->willReturn("ok\n");
        $streamHandler->expects($this->once())->method('readError')
            ->with($channel, $this->anything(), 15, $this->anything())
            ->willReturn('');
        $streamHandler->expects($this->once())->method('getExitStatus')
            ->with($channel)
            ->willReturn(0);

        $auth = new PasswordAuthentication('password', static fn (): bool => true);
        $connection = new SSHConnection(
            $auth,
            'remote-user',
            $streamHandler,
            static fn () => $resource,
            static fn () => $channel,
            static fn (): bool => true,
            null,
            null,
            null,
            null,
            commandTimeout: 60,
        );
        $connection->connect('localhost');

        $connection->execute('echo test', 15);
        $connection->disconnect();
    }

    // ----- channel (multiplexing) -----

    public function test_channel_returns_command_channel(): void
    {
        $resource = \fopen('php://temp', 'rb+');
        $channel = \fopen('php://temp', 'rb+');
        $this->assertIsResource($resource);
        $this->assertIsResource($channel);

        $auth = new PasswordAuthentication('password', static fn (): bool => true);
        $connection = new SSHConnection(
            $auth,
            'remote-user',
            new StreamHandler(),
            static fn () => $resource,
            static fn () => $channel,
            static fn (): bool => true,
        );
        $connection->connect('localhost');

        $cmdChannel = $connection->channel('echo hello');
        $this->assertInstanceOf(CommandChannel::class, $cmdChannel);
        $cmdChannel->close();
        $connection->disconnect();
    }

    public function test_channel_throws_when_not_connected(): void
    {
        $auth = new PasswordAuthentication('password');
        $connection = new SSHConnection($auth, 'remote-user');

        $this->expectException(ConnectionException::class);
        $connection->channel('echo test');
    }

    public function test_channel_throws_when_executor_returns_false(): void
    {
        $resource = \fopen('php://temp', 'rb+');
        $channel = \fopen('php://temp', 'rb+');
        $this->assertIsResource($resource);
        $this->assertIsResource($channel);

        $auth = new PasswordAuthentication('password', static fn (): bool => true);
        $connection = new SSHConnection(
            $auth,
            'remote-user',
            new StreamHandler(),
            static fn () => $resource,
            static fn (): false => false,
        );
        $connection->connect('localhost');

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Unable to execute SSH command.');
        $connection->channel('echo test');
        $connection->disconnect();
    }

    public function test_multiple_channels_can_be_opened_on_same_connection(): void
    {
        $resource = \fopen('php://temp', 'rb+');
        $channel1 = \fopen('php://temp', 'rb+');
        $channel2 = \fopen('php://temp', 'rb+');
        $this->assertIsResource($resource);
        $this->assertIsResource($channel1);
        $this->assertIsResource($channel2);

        $channelIndex = 0;
        $streamHandler = $this->createMock(StreamHandler::class);
        $streamHandler->method('read')->willReturn("ok\n");
        $streamHandler->method('readError')->willReturn('');
        $streamHandler->method('getExitStatus')->willReturn(0);

        $auth = new PasswordAuthentication('password', static fn (): bool => true);
        $connection = new SSHConnection(
            $auth,
            'remote-user',
            $streamHandler,
            static fn () => $resource,
            static function () use ($channel1, $channel2, &$channelIndex): mixed {
                return ++$channelIndex === 1 ? $channel1 : $channel2;
            },
            static fn (): bool => true,
        );
        $connection->connect('localhost');

        $cmd1 = $connection->channel('cmd1');
        $cmd2 = $connection->channel('cmd2');
        $this->assertTrue($cmd1->isOpen());
        $this->assertTrue($cmd2->isOpen());
        $this->assertNotSame($cmd1, $cmd2);

        $cmd1->close();
        $cmd2->close();
        $connection->disconnect();
    }

    // ----- shell -----

    public function test_shell_returns_shell_session(): void
    {
        $resource = \fopen('php://temp', 'rb+');
        $channel = \fopen('php://temp', 'rb+');
        $this->assertIsResource($resource);
        $this->assertIsResource($channel);

        $auth = new PasswordAuthentication('password', static fn (): bool => true);
        $connection = new SSHConnection(
            $auth,
            'remote-user',
            new StreamHandler(),
            static fn () => $resource,
            static fn () => $channel,
            static fn (): bool => true,
            null,
            null,
            null,
            static fn (): mixed => $channel,
        );
        $connection->connect('localhost');

        $shell = $connection->shell('xterm-256color', 120, 40);
        $this->assertInstanceOf(ShellSession::class, $shell);
        $this->assertSame(120, $shell->width());
        $this->assertSame(40, $shell->height());
        $shell->close();
        $connection->disconnect();
    }

    public function test_shell_uses_injectable_shell_opener(): void
    {
        $resource = \fopen('php://temp', 'rb+');
        $channel = \fopen('php://temp', 'rb+');
        $this->assertIsResource($resource);
        $this->assertIsResource($channel);

        $actualTermType = null;
        $actualWidth = null;
        $actualHeight = null;

        $auth = new PasswordAuthentication('password', static fn (): bool => true);
        $connection = new SSHConnection(
            $auth,
            'remote-user',
            new StreamHandler(),
            static fn () => $resource,
            static fn () => $channel,
            static fn (): bool => true,
            null,
            null,
            null,
            static function (mixed $res, ?string $termType, int $width, int $height) use (&$actualTermType, &$actualWidth, &$actualHeight, &$channel): mixed {
                $actualTermType = $termType;
                $actualWidth = $width;
                $actualHeight = $height;
                return $channel;
            },
        );
        $connection->connect('localhost');

        $shell = $connection->shell('xterm-256color', 80, 25);
        $this->assertSame('xterm-256color', $actualTermType);
        $this->assertSame(80, $actualWidth);
        $this->assertSame(25, $actualHeight);
        $shell->close();
        $connection->disconnect();
    }

    public function test_shell_returns_session_with_writeable_channel(): void
    {
        $resource = \fopen('php://temp', 'rb+');
        $channel = \fopen('php://temp', 'rb+');
        $this->assertIsResource($resource);
        $this->assertIsResource($channel);

        $auth = new PasswordAuthentication('password', static fn (): bool => true);
        $connection = new SSHConnection(
            $auth,
            'remote-user',
            new StreamHandler(),
            static fn () => $resource,
            static fn () => $channel,
            static fn (): bool => true,
            null,
            null,
            null,
            static fn (): mixed => $channel,
        );
        $connection->connect('localhost');

        $shell = $connection->shell();

        \fwrite($channel, "terminal output\n");
        \rewind($channel);

        $output = $shell->read(4096, 0);
        $this->assertStringContainsString('terminal output', $output);
        $shell->close();
        $connection->disconnect();
    }

    public function test_shell_throws_when_not_connected(): void
    {
        $auth = new PasswordAuthentication('password');
        $connection = new SSHConnection($auth, 'remote-user');

        $this->expectException(ConnectionException::class);
        $connection->shell();
    }

    public function test_shell_throws_when_opener_returns_false(): void
    {
        $resource = \fopen('php://temp', 'rb+');
        $channel = \fopen('php://temp', 'rb+');
        $this->assertIsResource($resource);
        $this->assertIsResource($channel);

        $auth = new PasswordAuthentication('password', static fn (): bool => true);
        $connection = new SSHConnection(
            $auth,
            'remote-user',
            new StreamHandler(),
            static fn () => $resource,
            static fn () => $channel,
            static fn (): bool => true,
            null,
            null,
            null,
            static fn (): false => false,
        );
        $connection->connect('localhost');

        $this->expectException(ConnectionException::class);
        $connection->shell();
        $connection->disconnect();
    }

    public function test_shell_with_keepalive_triggers_ping(): void
    {
        $resource = \fopen('php://temp', 'rb+');
        $channel = \fopen('php://temp', 'rb+');
        $this->assertIsResource($resource);
        $this->assertIsResource($channel);

        $pingCalled = 0;
        $auth = new PasswordAuthentication('password', static fn (): bool => true);
        $connection = new SSHConnection(
            $auth,
            'remote-user',
            new StreamHandler(),
            static fn () => $resource,
            static fn () => $channel,
            static fn (): bool => true,
            null,
            null,
            static function (mixed $res) use (&$pingCalled): bool {
                ++$pingCalled;
                return true;
            },
            static fn (): mixed => $channel,
            keepaliveInterval: 1,
        );
        $connection->connect('localhost');

        \sleep(1);

        $shell = $connection->shell();
        $this->assertInstanceOf(ShellSession::class, $shell);
        $this->assertSame(1, $pingCalled);
        $shell->close();
        $connection->disconnect();
    }

    public function test_disconnect_closes_session_resource(): void
    {
        $resource = \fopen('php://temp', 'rb+');
        $this->assertIsResource($resource);

        $sessionClosed = false;
        $auth = new PasswordAuthentication('password', static fn (): bool => true);
        $connection = new SSHConnection(
            $auth,
            'remote-user',
            new StreamHandler(),
            static fn (): mixed => $resource,
            null,
            null,
            static function (mixed $res) use (&$sessionClosed): bool {
                $sessionClosed = true;
                return true;
            },
        );
        $connection->connect('localhost');
        $connection->disconnect();

        $this->assertTrue($sessionClosed);
        $this->assertFalse($connection->isConnected());
    }

    public function test_keepalive_disabled_by_default(): void
    {
        $resource = \fopen('php://temp', 'rb+');
        $channel = \fopen('php://temp', 'rb+');
        $this->assertIsResource($resource);
        $this->assertIsResource($channel);

        $pingCalled = 0;
        $streamHandler = $this->createMock(StreamHandler::class);
        $streamHandler->method('read')->willReturn("out\n");
        $streamHandler->method('readError')->willReturn('');
        $streamHandler->method('getExitStatus')->willReturn(0);

        $auth = new PasswordAuthentication('password', static fn (): bool => true);
        $connection = new SSHConnection(
            $auth,
            'remote-user',
            $streamHandler,
            static fn () => $resource,
            static fn () => $channel,
            static fn (): bool => true,
        );
        $connection->connect('localhost');

        $connection->execute('echo test');
        $this->assertSame(0, $pingCalled);
        $connection->disconnect();
    }

}
