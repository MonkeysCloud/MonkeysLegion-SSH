<?php

namespace Tests\Unit\Stream;

use MonkeysLegion\SSH\Stream\StreamHandler;
use PHPUnit\Framework\TestCase;

class StreamHandlerTest extends TestCase
{
    public function test_read_returns_stream_content(): void
    {
        $stream = \fopen('php://temp', 'rb+');
        $this->assertIsResource($stream);
        \fwrite($stream, "line-1\nline-2");
        \rewind($stream);

        $handler = new StreamHandler();
        $this->assertSame("line-1\nline-2", $handler->read($stream));
    }

    public function test_read_error_uses_stderr_stream(): void
    {
        $stderr = \fopen('php://temp', 'rb+');
        $this->assertIsResource($stderr);
        \fwrite($stderr, 'stderr-content');
        \rewind($stderr);

        $handler = new StreamHandler(
            null,
            null,
            static fn (): mixed => $stderr,
        );

        $channel = \fopen('php://temp', 'rb+');
        $this->assertIsResource($channel);
        $this->assertSame('stderr-content', $handler->readError($channel));
    }

    public function test_write_returns_number_of_written_bytes(): void
    {
        $stream = \fopen('php://temp', 'rb+');
        $this->assertIsResource($stream);

        $handler = new StreamHandler();
        $this->assertSame(5, $handler->write($stream, '12345'));
    }

    public function test_get_exit_status_returns_exit_code(): void
    {
        $handler = new StreamHandler(
            null,
            null,
            null,
            static fn (): int => 42,
        );

        $channel = \fopen('php://temp', 'rb+');
        $this->assertIsResource($channel);
        $this->assertSame(42, $handler->getExitStatus($channel));
    }

    public function test_set_max_output_size_returns_self(): void
    {
        $handler = new StreamHandler();
        $this->assertSame($handler, $handler->setMaxOutputSize(1024));
    }

    public function test_set_max_output_size_rejects_zero(): void
    {
        $handler = new StreamHandler();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Max output size must be at least 1 byte.');
        $handler->setMaxOutputSize(0);
    }

    public function test_set_max_output_size_rejects_negative(): void
    {
        $handler = new StreamHandler();

        $this->expectException(\InvalidArgumentException::class);
        $handler->setMaxOutputSize(-1);
    }

    public function test_read_throws_on_set_blocking_failure(): void
    {
        $stream = \fopen('php://temp', 'rb+');
        $this->assertIsResource($stream);

        $handler = new StreamHandler(
            static fn (mixed $s, bool $b): bool => false,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to enable blocking mode for SSH stream.');
        $handler->read($stream);
    }

    public function test_read_throws_on_read_failure(): void
    {
        $stream = \fopen('php://temp', 'rb+');
        $this->assertIsResource($stream);
        \fwrite($stream, "data\n");
        \rewind($stream);

        $handler = new StreamHandler(
            static fn (mixed $s, bool $b): bool => true,
            static fn (mixed $s, int $l): false => false,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to read from SSH stream.');
        $handler->read($stream);
    }

    public function test_read_error_returns_empty_on_false(): void
    {
        $handler = new StreamHandler(
            null,
            null,
            static fn (mixed $ch, int $id): false => false,
        );

        $channel = \fopen('php://temp', 'rb+');
        $this->assertIsResource($channel);
        $this->assertSame('', $handler->readError($channel));
    }

    public function test_write_throws_on_failure(): void
    {
        $handler = new StreamHandler(
            null,
            null,
            null,
            null,
            static fn (mixed $s, string $d): false => false,
            static fn (mixed $s): bool => true,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to write to SSH stream.');
        $handler->write(\fopen('php://temp', 'rb+'), 'data');
    }

    public function test_get_exit_status_throws_on_false(): void
    {
        $handler = new StreamHandler(
            null,
            null,
            null,
            static fn (mixed $ch): false => false,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to read SSH channel exit status.');
        $handler->getExitStatus(\fopen('php://temp', 'rb+'));
    }
}
