<?php

namespace MonkeysLegion\SSH\Stream;

use MonkeysLegion\SSH\Exceptions\ConnectionException;

class CommandChannel
{
    private mixed $channel;
    private StreamHandler $streamHandler;
    private \Closure $closer;
    private int $maxOutputSize;

    public function __construct(
        mixed $channel,
        StreamHandler $streamHandler,
        callable $closer,
        int $maxOutputSize = 52428800,
    ) {
        if (!\is_resource($channel)) {
            throw new ConnectionException('Command channel must be a valid stream resource.');
        }

        $this->channel = $channel;
        $this->streamHandler = $streamHandler;
        $this->closer = $closer(...);
        $this->maxOutputSize = $maxOutputSize;
    }

    public function read(?int $timeout = null): string
    {
        if (!\is_resource($this->channel)) {
            throw new ConnectionException('Command channel is closed.');
        }

        return $this->streamHandler->read($this->channel, timeout: $timeout, maxSize: $this->maxOutputSize);
    }

    public function readError(?int $timeout = null): string
    {
        if (!\is_resource($this->channel)) {
            throw new ConnectionException('Command channel is closed.');
        }

        return $this->streamHandler->readError($this->channel, timeout: $timeout, maxSize: $this->maxOutputSize);
    }

    public function getExitStatus(): int
    {
        if (!\is_resource($this->channel)) {
            throw new ConnectionException('Command channel is closed.');
        }

        return $this->streamHandler->getExitStatus($this->channel);
    }

    public function close(): void
    {
        if ($this->channel !== null) {
            ($this->closer)($this->channel);
            $this->channel = null;
        }
    }

    public function isOpen(): bool
    {
        return \is_resource($this->channel);
    }

    public function __destruct()
    {
        $this->close();
    }
}
