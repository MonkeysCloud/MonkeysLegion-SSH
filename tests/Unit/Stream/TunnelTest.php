<?php

namespace Tests\Unit\Stream;

use MonkeysLegion\SSH\Exceptions\ConnectionException;
use MonkeysLegion\SSH\Stream\Tunnel;
use PHPUnit\Framework\TestCase;

class TunnelTest extends TestCase
{
    public function test_constructor_rejects_non_resource(): void
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Tunnel stream must be a valid resource.');

        new Tunnel('not-a-resource');
    }

    public function test_read_returns_data_from_stream(): void
    {
        $stream = \fopen('php://temp', 'rb+');
        $this->assertIsResource($stream);
        \fwrite($stream, "tunnel data\n");
        \rewind($stream);

        $tunnel = new Tunnel($stream);
        $this->assertSame("tunnel data\n", $tunnel->read(4096));
    }

    public function test_write_sends_data_to_stream(): void
    {
        $stream = \fopen('php://temp', 'rb+');
        $this->assertIsResource($stream);

        $tunnel = new Tunnel($stream);
        $written = $tunnel->write("hello\n");
        $this->assertSame(6, $written);

        \rewind($stream);
        $this->assertSame("hello\n", \stream_get_contents($stream));
    }

    public function test_read_throws_when_tunnel_closed(): void
    {
        $stream = \fopen('php://temp', 'rb+');
        $this->assertIsResource($stream);

        $tunnel = new Tunnel($stream);
        $tunnel->close();

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Tunnel is closed.');
        $tunnel->read();
    }

    public function test_write_throws_when_tunnel_closed(): void
    {
        $stream = \fopen('php://temp', 'rb+');
        $this->assertIsResource($stream);

        $tunnel = new Tunnel($stream);
        $tunnel->close();

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Tunnel is closed.');
        $tunnel->write('data');
    }

    public function test_close_closes_stream(): void
    {
        $stream = \fopen('php://temp', 'rb+');
        $this->assertIsResource($stream);

        $tunnel = new Tunnel($stream);
        $this->assertTrue($tunnel->isOpen());
        $tunnel->close();
        $this->assertFalse($tunnel->isOpen());
    }

    public function test_close_is_idempotent(): void
    {
        $stream = \fopen('php://temp', 'rb+');
        $this->assertIsResource($stream);

        $tunnel = new Tunnel($stream);
        $tunnel->close();
        $tunnel->close();
        $this->assertFalse($tunnel->isOpen());
    }

    public function test_stream_returns_underlying_resource(): void
    {
        $stream = \fopen('php://temp', 'rb+');
        $this->assertIsResource($stream);

        $tunnel = new Tunnel($stream);
        $this->assertSame($stream, $tunnel->stream());
    }

    public function test_stream_returns_null_after_close(): void
    {
        $stream = \fopen('php://temp', 'rb+');
        $this->assertIsResource($stream);

        $tunnel = new Tunnel($stream);
        $tunnel->close();
        $this->assertNull($tunnel->stream());
    }

    public function test_destructor_closes_stream(): void
    {
        $stream = \fopen('php://temp', 'rb+');
        $this->assertIsResource($stream);

        $tunnel = new Tunnel($stream);
        $tunnel->__destruct();
        $this->assertFalse($tunnel->isOpen());
    }
}
