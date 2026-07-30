<?php

namespace MonkeysLegion\SSH\Stream;

use MonkeysLegion\SSH\Exceptions\ConnectionException;

class ShellSession
{
    private mixed $channel;

    /**
     * @var \Closure(mixed): bool
     */
    private \Closure $closer;

    private int $width;
    private int $height;

    /**
     * @param \Closure(mixed): bool $closer
     */
    public function __construct(
        mixed $channel,
        callable $closer,
        int $width = 80,
        int $height = 25,
    ) {
        if (!\is_resource($channel)) {
            throw new ConnectionException('Shell channel must be a valid stream resource.');
        }

        $this->channel = $channel;
        $this->closer = $closer(...);
        $this->width = $width;
        $this->height = $height;

        \stream_set_blocking($channel, false);
    }

    public function write(string $data): self
    {
        $channel = $this->channel;
        if (!\is_resource($channel)) {
            throw new ConnectionException('Shell session is closed.');
        }

        $written = @\fwrite($channel, $data);
        if ($written === false) {
            throw new ConnectionException('Failed to write to shell session.');
        }

        return $this;
    }

    public function read(int $length = 4096, int $timeout = 200000): string
    {
        $channel = $this->channel;
        if (!\is_resource($channel)) {
            throw new ConnectionException('Shell session is closed.');
        }

        if ($timeout > 0) {
            $read = [$channel];
            $write = null;
            $except = null;
            \stream_select($read, $write, $except, 0, $timeout);
        }

        $output = \stream_get_contents($channel, $length);
        return $output !== false ? $output : '';
    }

    public function readAll(int $length = 65536): string
    {
        $channel = $this->channel;
        if (!\is_resource($channel)) {
            throw new ConnectionException('Shell session is closed.');
        }

        $chunkSize = \max(1, $length);
        $output = '';
        $read = [$channel];
        $write = null;
        $except = null;

        while (\stream_select($read, $write, $except, 0, 200000) > 0) {
            $chunk = \fread($read[0], $chunkSize);
            if ($chunk === false || $chunk === '') {
                break;
            }

            $output .= $chunk;
            $read = [$channel];
        }

        return $output;
    }

    public function resize(int $width, int $height): self
    {
        $this->write("\e[8;{$height};{$width}t");
        $this->width = $width;
        $this->height = $height;

        return $this;
    }

    public function close(): void
    {
        if ($this->channel !== null) {
            ($this->closer)($this->channel);
            $this->channel = null;
        }
    }

    public function isRunning(): bool
    {
        if (!\is_resource($this->channel)) {
            return false;
        }

        return !\feof($this->channel);
    }

    public function width(): int
    {
        return $this->width;
    }

    public function height(): int
    {
        return $this->height;
    }

    public function __destruct()
    {
        $this->close();
    }
}
