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
}
