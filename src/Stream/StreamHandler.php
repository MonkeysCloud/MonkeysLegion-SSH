<?php

namespace MonkeysLegion\SSH\Stream;

class StreamHandler
{
    /** @var \Closure */
    private \Closure $setBlocking;

    /** @var \Closure */
    private \Closure $readBlock;

    /** @var \Closure */
    private \Closure $fetchStream;

    /** @var \Closure */
    private \Closure $getExitStatusFn;

    /** @var \Closure */
    private \Closure $writeBlock;

    /** @var \Closure */
    private \Closure $flush;

    public function __construct(
        ?callable $setBlocking = null,
        ?callable $readBlock = null,
        ?callable $fetchStream = null,
        ?callable $getExitStatusFn = null,
        ?callable $writeBlock = null,
        ?callable $flush = null
    ) {
        $this->setBlocking = $setBlocking !== null
            ? $setBlocking(...)
            : $this->nativeSetBlocking(...);
        $this->readBlock = $readBlock !== null
            ? $readBlock(...)
            : $this->nativeReadBlock(...);
        $this->fetchStream = $fetchStream !== null
            ? $fetchStream(...)
            : $this->nativeFetchStream(...);
        $this->getExitStatusFn = $getExitStatusFn !== null
            ? $getExitStatusFn(...)
            : $this->nativeGetExitStatus(...);
        $this->writeBlock = $writeBlock !== null
            ? $writeBlock(...)
            : $this->nativeWriteBlock(...);
        $this->flush = $flush !== null
            ? $flush(...)
            : $this->nativeFlush(...);
    }

    public function read(mixed $channel, int $blockSize = 8192): string
    {
        $this->setReadableBlockingMode($channel);
        return $this->readFromStream($channel, $blockSize);
    }

    public function readError(mixed $channel, int $blockSize = 8192): string
    {
        $stderr = ($this->fetchStream)($channel, \SSH2_STREAM_STDERR);
        if ($stderr === false) {
            return '';
        }

        $this->setReadableBlockingMode($stderr);
        return $this->readFromStream($stderr, $blockSize);
    }

    public function write(mixed $channel, string $data): int
    {
        $written = ($this->writeBlock)($channel, $data);
        if ($written === false) {
            throw new \RuntimeException('Unable to write to SSH stream.');
        }

        ($this->flush)($channel);
        return $written;
    }

    public function getExitStatus(mixed $channel): int
    {
        $status = ($this->getExitStatusFn)($channel);
        if ($status === false) {
            throw new \RuntimeException('Unable to read SSH channel exit status.');
        }

        return $status;
    }

    private function setReadableBlockingMode(mixed $stream): void
    {
        $success = ($this->setBlocking)($stream, true);
        if (!$success) {
            throw new \RuntimeException('Unable to enable blocking mode for SSH stream.');
        }
    }

    private function readFromStream(mixed $stream, int $blockSize): string
    {
        $resource = $this->requireResource($stream);
        $buffer = '';
        while (!\feof($resource)) {
            $chunk = ($this->readBlock)($resource, $blockSize);
            if ($chunk === false) {
                throw new \RuntimeException('Unable to read from SSH stream.');
            }

            if ($chunk === '') {
                break;
            }

            $buffer .= $chunk;
        }

        return $buffer;
    }

    private function nativeSetBlocking(mixed $stream, bool $shouldBlock): bool
    {
        return \stream_set_blocking($this->requireResource($stream), $shouldBlock);
    }

    private function nativeReadBlock(mixed $stream, int $length): string|false
    {
        $readLength = \max(1, $length);
        return \fread($this->requireResource($stream), $readLength);
    }

    private function nativeFetchStream(mixed $channel, int $streamId): mixed
    {
        if (!\is_resource($channel)) {
            throw new \RuntimeException('SSH channel is invalid.');
        }

        return \ssh2_fetch_stream($channel, $streamId);
    }

    private function nativeGetExitStatus(mixed $channel): int|false
    {
        if (!\is_resource($channel) && !\is_object($channel)) {
            throw new \RuntimeException('SSH channel is invalid.');
        }

        if (\function_exists('ssh2_channel_get_exit_status')) {
            /** @var int|false $status */
            $status = \ssh2_channel_get_exit_status($channel);
            return $status;
        }

        if (\function_exists('ssh2_get_exit_status')) {
            /** @var int|false $status */
            $status = \ssh2_get_exit_status($channel);
            return $status;
        }

        return false;
    }

    private function nativeWriteBlock(mixed $stream, string $data): int|false
    {
        return \fwrite($this->requireResource($stream), $data);
    }

    private function nativeFlush(mixed $stream): bool
    {
        return \fflush($this->requireResource($stream));
    }

    /**
     * @return resource
     */
    private function requireResource(mixed $stream)
    {
        if (!\is_resource($stream)) {
            throw new \RuntimeException('SSH stream is invalid.');
        }

        return $stream;
    }
}
