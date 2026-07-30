<?php

namespace MonkeysLegion\SSH\SFTP;

class SFTPClient
{
    private mixed $sftpHandle = null;
    private SFTPTransferMetrics $metrics;

    /**
     * @var \Closure(mixed): mixed
     */
    private \Closure $initializer;

    public function __construct(
        private mixed $session,
        ?SFTPTransferMetrics $metrics = null,
        ?callable $initializer = null,
        private string $streamScheme = 'ssh2.sftp',
    ) {
        $this->metrics = $metrics ?? new SFTPTransferMetrics();
        $this->initializer = $initializer !== null
            ? $initializer(...)
            : $this->nativeInitializer(...);
    }

    public function upload(string $localPath, string $remotePath): int
    {
        if (!\is_file($localPath)) {
            throw new \InvalidArgumentException(\sprintf('Local file does not exist: %s', $localPath));
        }

        $content = @\file_get_contents($localPath);
        if ($content === false) {
            throw new \RuntimeException('Unable to read local file for upload.');
        }

        $bytes = @\file_put_contents($this->streamPath($remotePath), $content);
        if ($bytes === false) {
            throw new \RuntimeException('Unable to upload file via SFTP stream wrapper.');
        }

        $this->metrics->recordUpload($bytes);
        return $bytes;
    }

    public function download(string $remotePath, string $localPath): int
    {
        $dir = \dirname($localPath);
        if (!\is_dir($dir)) {
            throw new \RuntimeException(\sprintf('Local directory does not exist: %s', $dir));
        }

        $content = @\file_get_contents($this->streamPath($remotePath));
        if ($content === false) {
            throw new \RuntimeException('Unable to read remote file via SFTP stream wrapper.');
        }

        $bytes = @\file_put_contents($localPath, $content);
        if ($bytes === false) {
            throw new \RuntimeException('Unable to write downloaded SFTP file locally.');
        }

        $this->metrics->recordDownload($bytes);
        return $bytes;
    }

    public function mkdir(string $remotePath, int $mode = 0o775, bool $recursive = false): void
    {
        $success = @\mkdir($this->streamPath($remotePath), $mode, $recursive);
        if (!$success && !\is_dir($this->streamPath($remotePath))) {
            throw new \RuntimeException(\sprintf('Unable to create remote directory: %s', $remotePath));
        }
    }

    public function chmod(string $remotePath, int $mode): void
    {
        $normalized = \str_starts_with($remotePath, '/') ? $remotePath : '/' . $remotePath;

        if (\function_exists('ssh2_sftp_chmod')) {
            $success = \ssh2_sftp_chmod($this->handle(), $normalized, $mode);
        } else {
            $success = \chmod($this->streamPath($normalized), $mode);
        }

        if (!$success) {
            throw new \RuntimeException(\sprintf('Unable to chmod remote path: %s', $remotePath));
        }
    }

    public function delete(string $remotePath): void
    {
        $success = @\unlink($this->streamPath($remotePath));
        if (!$success) {
            throw new \RuntimeException(\sprintf('Unable to delete remote path: %s', $remotePath));
        }
    }

    public function metrics(): SFTPTransferMetrics
    {
        return $this->metrics;
    }

    private function streamPath(string $remotePath): string
    {
        $normalized = \str_starts_with($remotePath, '/') ? $remotePath : '/' . $remotePath;
        $handle = $this->handle();
        return \sprintf('%s://%d%s', $this->streamScheme, (int) $handle, $normalized);
    }

    /**
     * @return resource
     */
    private function handle(): mixed
    {
        if ($this->sftpHandle !== null) {
            return $this->requireResource($this->sftpHandle);
        }

        $handle = ($this->initializer)($this->session);
        if ($handle === false || $handle === null || !\is_resource($handle)) {
            throw new \RuntimeException('Unable to initialize SFTP subsystem.');
        }

        $this->sftpHandle = $handle;
        return $this->requireResource($handle);
    }

    private function nativeInitializer(mixed $session): mixed
    {
        if (!\is_resource($session)) {
            throw new \RuntimeException('SSH session resource is invalid.');
        }

        return \ssh2_sftp($session);
    }

    /**
     * @return resource
     */
    private function requireResource(mixed $resource): mixed
    {
        if (!\is_resource($resource)) {
            throw new \RuntimeException('SFTP resource is invalid.');
        }

        return $resource;
    }
}
