<?php

namespace Tests\Unit\Stream;

use MonkeysLegion\SSH\Exceptions\ConnectionException;
use MonkeysLegion\SSH\Stream\ShellSession;
use PHPUnit\Framework\TestCase;

class ShellSessionTest extends TestCase
{
    public function test_write_writes_data_to_channel(): void
    {
        $channel = $this->createChannel();
        $session = new ShellSession($channel, static fn (): bool => true);
        $session->write('ls -la');

        \rewind($channel);
        $this->assertSame('ls -la', \stream_get_contents($channel));
        $session->close();
    }

    public function test_write_throws_when_channel_is_closed(): void
    {
        $session = new ShellSession($this->createChannel(), static fn (): bool => true);
        $session->close();

        $this->expectException(ConnectionException::class);
        $session->write('data');
    }

    public function test_read_returns_channel_content(): void
    {
        $channel = $this->createChannel();
        \fwrite($channel, "hello\nworld\n");
        \rewind($channel);

        $session = new ShellSession($channel, static fn (): bool => true);
        $output = $session->read(4096, 0);

        $this->assertStringContainsString('hello', $output);
        $this->assertStringContainsString('world', $output);
        $session->close();
    }

    public function test_read_throws_when_channel_is_closed(): void
    {
        $session = new ShellSession($this->createChannel(), static fn (): bool => true);
        $session->close();

        $this->expectException(ConnectionException::class);
        $session->read();
    }

    public function test_readAll_returns_all_available_data(): void
    {
        $channel = $this->createChannel();
        \fwrite($channel, 'chunk1chunk2chunk3');
        \rewind($channel);

        $session = new ShellSession($channel, static fn (): bool => true);
        $output = $session->readAll();

        $this->assertStringContainsString('chunk1', $output);
        $this->assertStringContainsString('chunk2', $output);
        $session->close();
    }

    public function test_readAll_returns_empty_string_when_no_data(): void
    {
        $session = new ShellSession($this->createChannel(), static fn (): bool => true);
        $this->assertSame('', $session->readAll());
        $session->close();
    }

    public function test_resize_sends_escape_sequence(): void
    {
        $channel = $this->createChannel();
        $session = new ShellSession($channel, static fn (): bool => true);
        $session->resize(120, 40);

        \rewind($channel);
        $this->assertStringContainsString("\e[8;40;120t", \stream_get_contents($channel));
        $this->assertSame(120, $session->width());
        $this->assertSame(40, $session->height());
        $session->close();
    }

    public function test_close_closes_channel(): void
    {
        $closed = 0;
        $session = new ShellSession(
            $this->createChannel(),
            static function (mixed $ch) use (&$closed): bool {
                ++$closed;
                return true;
            },
        );
        $session->close();

        $this->assertSame(1, $closed);
    }

    public function test_isRunning_returns_true_when_open(): void
    {
        $session = new ShellSession($this->createChannel(), static fn (): bool => true);
        $this->assertTrue($session->isRunning());
        $session->close();
    }

    public function test_isRunning_returns_false_when_closed(): void
    {
        $session = new ShellSession($this->createChannel(), static fn (): bool => true);
        $session->close();
        $this->assertFalse($session->isRunning());
    }

    public function test_constructor_throws_when_channel_is_not_a_resource(): void
    {
        $this->expectException(ConnectionException::class);
        new ShellSession(null, static fn (): bool => true);
    }

    public function test_dimensions_are_returned(): void
    {
        $session = new ShellSession($this->createChannel(), static fn (): bool => true, 120, 40);
        $this->assertSame(120, $session->width());
        $this->assertSame(40, $session->height());
        $session->close();
    }

    public function test_destructor_closes_channel(): void
    {
        $closed = 0;

        $session = new ShellSession(
            $this->createChannel(),
            static function (mixed $ch) use (&$closed): bool {
                ++$closed;
                return true;
            },
        );

        unset($session);
        $this->assertSame(1, $closed);
    }

    public function test_default_dimensions(): void
    {
        $session = new ShellSession($this->createChannel(), static fn (): bool => true);
        $this->assertSame(80, $session->width());
        $this->assertSame(25, $session->height());
        $session->close();
    }

    public function test_readAll_throws_when_channel_closed(): void
    {
        $session = new ShellSession($this->createChannel(), static fn (): bool => true);
        $session->close();

        $this->expectException(ConnectionException::class);
        $session->readAll();
    }

    public function test_readAll_with_zero_length_reads_all_data(): void
    {
        $channel = $this->createChannel();
        \fwrite($channel, 'test data');
        \rewind($channel);

        $session = new ShellSession($channel, static fn (): bool => true);
        $output = @$session->readAll(0);

        $this->assertStringContainsString('test data', $output);
        $session->close();
    }

    public function test_readAll_accumulates_multiple_chunks(): void
    {
        $channel = $this->createChannel();
        \fwrite($channel, 'abc');
        \rewind($channel);

        $session = new ShellSession($channel, static fn (): bool => true);
        $output = @$session->readAll(1); // 1-byte chunks force multiple reads

        $this->assertSame('abc', $output);
        $session->close();
    }

    /**
     * @return resource
     */
    private function createChannel(): mixed
    {
        $channel = \fopen('php://temp', 'r+b');
        $this->assertIsResource($channel);
        return $channel;
    }
}
