<?php

namespace MonkeysLegion\SSH\Stream;

use MonkeysLegion\SSH\Exceptions\ConnectionException;

class Tunnel
{
    private mixed $stream;

    public function __construct(
        mixed $stream,
    ) {
        if (!\is_resource($stream)) {
            throw new ConnectionException('Tunnel stream must be a valid resource.');
        }

        $this->stream = $stream;
    }

    public function read(int $length = 4096): string
    {
        if (!\is_resource($this->stream)) {
            throw new ConnectionException('Tunnel is closed.');
        }

        $result = @\fread($this->stream, \max(1, $length));
        if ($result === false) {
            throw new ConnectionException('Failed to read from tunnel.');
        }

        return $result;
    }

    public function write(string $data): int
    {
        if (!\is_resource($this->stream)) {
            throw new ConnectionException('Tunnel is closed.');
        }

        $written = @\fwrite($this->stream, $data);
        if ($written === false) {
            throw new ConnectionException('Failed to write to tunnel.');
        }

        return $written;
    }

    public function close(): void
    {
        if (\is_resource($this->stream)) {
            @\fclose($this->stream);
        }

        $this->stream = null;
    }

    public function isOpen(): bool
    {
        return \is_resource($this->stream);
    }

    public function stream(): mixed
    {
        return $this->stream;
    }

    public function __destruct()
    {
        $this->close();
    }
}
