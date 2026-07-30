<?php

namespace Tests\Unit\Stream;

use MonkeysLegion\SSH\Exceptions\ConnectionException;
use MonkeysLegion\SSH\Stream\CommandChannel;
use MonkeysLegion\SSH\Stream\StreamHandler;
use PHPUnit\Framework\TestCase;

class CommandChannelTest extends TestCase
{
    public function test_constructor_rejects_non_resource(): void
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Command channel must be a valid stream resource.');

        new CommandChannel('not-a-resource', new StreamHandler(), static fn (): bool => true);
    }

    public function test_read_delegates_to_stream_handler(): void
    {
        $channel = \fopen('php://temp', 'rb+');
        $this->assertIsResource($channel);
        \fwrite($channel, "output data\n");
        \rewind($channel);

        $streamHandler = $this->createMock(StreamHandler::class);
        $streamHandler->expects($this->once())->method('read')
            ->with($channel)
            ->willReturn("output data\n");

        $cmdChannel = new CommandChannel($channel, $streamHandler, static fn (): bool => true);
        $this->assertSame("output data\n", $cmdChannel->read());
    }

    public function test_readError_delegates_to_stream_handler(): void
    {
        $channel = \fopen('php://temp', 'rb+');
        $this->assertIsResource($channel);

        $streamHandler = $this->createMock(StreamHandler::class);
        $streamHandler->expects($this->once())->method('readError')
            ->with($channel)
            ->willReturn("error data\n");

        $cmdChannel = new CommandChannel($channel, $streamHandler, static fn (): bool => true);
        $this->assertSame("error data\n", $cmdChannel->readError());
    }

    public function test_getExitStatus_delegates_to_stream_handler(): void
    {
        $channel = \fopen('php://temp', 'rb+');
        $this->assertIsResource($channel);

        $streamHandler = $this->createMock(StreamHandler::class);
        $streamHandler->expects($this->once())->method('getExitStatus')
            ->with($channel)
            ->willReturn(42);

        $cmdChannel = new CommandChannel($channel, $streamHandler, static fn (): bool => true);
        $this->assertSame(42, $cmdChannel->getExitStatus());
    }

    public function test_close_closes_channel(): void
    {
        $channel = \fopen('php://temp', 'rb+');
        $this->assertIsResource($channel);

        $closed = false;
        $cmdChannel = new CommandChannel(
            $channel,
            new StreamHandler(),
            static function (mixed $res) use (&$closed): bool {
                $closed = true;
                return true;
            },
        );

        $this->assertTrue($cmdChannel->isOpen());
        $cmdChannel->close();
        $this->assertFalse($cmdChannel->isOpen());
        $this->assertTrue($closed);
    }

    public function test_close_is_idempotent(): void
    {
        $channel = \fopen('php://temp', 'rb+');
        $this->assertIsResource($channel);

        $callCount = 0;
        $cmdChannel = new CommandChannel(
            $channel,
            new StreamHandler(),
            static function (mixed $res) use (&$callCount): bool {
                ++$callCount;
                return true;
            },
        );

        $cmdChannel->close();
        $cmdChannel->close();
        $this->assertSame(1, $callCount);
    }

    public function test_read_throws_when_channel_closed(): void
    {
        $channel = \fopen('php://temp', 'rb+');
        $this->assertIsResource($channel);

        $cmdChannel = new CommandChannel($channel, new StreamHandler(), static fn (): bool => true);
        $cmdChannel->close();

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Command channel is closed.');
        $cmdChannel->read();
    }

    public function test_readError_throws_when_channel_closed(): void
    {
        $channel = \fopen('php://temp', 'rb+');
        $this->assertIsResource($channel);

        $cmdChannel = new CommandChannel($channel, new StreamHandler(), static fn (): bool => true);
        $cmdChannel->close();

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Command channel is closed.');
        $cmdChannel->readError();
    }

    public function test_getExitStatus_throws_when_channel_closed(): void
    {
        $channel = \fopen('php://temp', 'rb+');
        $this->assertIsResource($channel);

        $cmdChannel = new CommandChannel($channel, new StreamHandler(), static fn (): bool => true);
        $cmdChannel->close();

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Command channel is closed.');
        $cmdChannel->getExitStatus();
    }

    public function test_isOpen_returns_false_after_close(): void
    {
        $channel = \fopen('php://temp', 'rb+');
        $this->assertIsResource($channel);

        $cmdChannel = new CommandChannel($channel, new StreamHandler(), static fn (): bool => true);
        $this->assertTrue($cmdChannel->isOpen());
        $cmdChannel->close();
        $this->assertFalse($cmdChannel->isOpen());
    }

    public function test_destructor_closes_channel(): void
    {
        $channel = \fopen('php://temp', 'rb+');
        $this->assertIsResource($channel);

        $closed = false;
        $cmdChannel = new CommandChannel(
            $channel,
            new StreamHandler(),
            static function (mixed $res) use (&$closed): bool {
                $closed = true;
                return true;
            },
        );

        $cmdChannel->__destruct();
        $this->assertTrue($closed);
    }
}
