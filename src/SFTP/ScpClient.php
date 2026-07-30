<?php

namespace MonkeysLegion\SSH\SFTP;

class ScpClient
{
    /**
     * @var \Closure(mixed, string, string, int): bool
     */
    private \Closure $sender;

    /**
     * @var \Closure(mixed, string, string): bool
     */
    private \Closure $receiver;

    public function __construct(
        private mixed $session,
        private int $mode = 0o644,
        ?callable $sender = null,
        ?callable $receiver = null,
    ) {
        $this->sender = $sender !== null
            ? $sender(...)
            : $this->nativeSender(...);
        $this->receiver = $receiver !== null
            ? $receiver(...)
            : $this->nativeReceiver(...);
    }

    public function send(string $localPath, string $remotePath, ?int $mode = null): void
    {
        if (!\is_file($localPath)) {
            throw new \InvalidArgumentException(\sprintf('Local file does not exist: %s', $localPath));
        }

        $success = ($this->sender)($this->session, $localPath, $remotePath, $mode ?? $this->mode);
        if (!$success) {
            throw new \RuntimeException(\sprintf('Unable to send file via SCP to: %s', $remotePath));
        }
    }

    public function receive(string $remotePath, string $localPath): void
    {
        $dir = \dirname($localPath);
        if (!\is_dir($dir)) {
            throw new \RuntimeException(\sprintf('Local directory does not exist: %s', $dir));
        }

        $success = ($this->receiver)($this->session, $remotePath, $localPath);
        if (!$success) {
            throw new \RuntimeException(\sprintf('Unable to receive file via SCP from: %s', $remotePath));
        }
    }

    private function nativeSender(mixed $session, string $localPath, string $remotePath, int $mode): bool
    {
        if (!\is_resource($session)) {
            throw new \RuntimeException('SSH session resource is invalid.');
        }

        return \ssh2_scp_send($session, $localPath, $remotePath, $mode);
    }

    private function nativeReceiver(mixed $session, string $remotePath, string $localPath): bool
    {
        if (!\is_resource($session)) {
            throw new \RuntimeException('SSH session resource is invalid.');
        }

        return \ssh2_scp_recv($session, $remotePath, $localPath);
    }
}
