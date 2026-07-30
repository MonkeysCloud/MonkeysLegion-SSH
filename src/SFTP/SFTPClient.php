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

    /**
     * @var \Closure(mixed, string): (array<int|string, int>|false)
     */
    private \Closure $statCallback;

    /**
     * @var \Closure(mixed, string, string): bool
     */
    private \Closure $renameCallback;

    /**
     * @var \Closure(mixed, string, string): bool
     */
    private \Closure $symlinkCallback;

    /**
     * @var \Closure(mixed, string): (string|false)
     */
    private \Closure $readlinkCallback;

    /**
     * @var \Closure(mixed, string, int): bool
     */
    private \Closure $chmodCallback;

    /**
     * @param array{
     *     stat?: callable,
     *     rename?: callable,
     *     symlink?: callable,
     *     readlink?: callable,
     *     chmod?: callable,
     * } $nativeCallbacks
     */
    public function __construct(
        private mixed $session,
        ?SFTPTransferMetrics $metrics = null,
        ?callable $initializer = null,
        private string $streamScheme = 'ssh2.sftp',
        array $nativeCallbacks = [],
    ) {
        $this->metrics = $metrics ?? new SFTPTransferMetrics();
        $this->initializer = $initializer !== null
            ? $initializer(...)
            : $this->nativeInitializer(...);
        $this->statCallback = isset($nativeCallbacks['stat'])
            ? $nativeCallbacks['stat'](...)
            : $this->nativeStat(...);
        $this->renameCallback = isset($nativeCallbacks['rename'])
            ? $nativeCallbacks['rename'](...)
            : $this->nativeRename(...);
        $this->symlinkCallback = isset($nativeCallbacks['symlink'])
            ? $nativeCallbacks['symlink'](...)
            : $this->nativeSymlink(...);
        $this->readlinkCallback = isset($nativeCallbacks['readlink'])
            ? $nativeCallbacks['readlink'](...)
            : $this->nativeReadlink(...);
        $this->chmodCallback = isset($nativeCallbacks['chmod'])
            ? $nativeCallbacks['chmod'](...)
            : $this->nativeChmod(...);
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
        $normalized = $this->normalizePath($remotePath);

        $success = ($this->chmodCallback)($this->handle(), $normalized, $mode);

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

    /**
     * @return list<string>
     */
    public function ls(string $remotePath): array
    {
        $entries = [];
        $path = $this->streamPath($remotePath);
        $handle = @\opendir($path);
        if ($handle === false) {
            throw new \RuntimeException(\sprintf('Unable to list remote directory: %s', $remotePath));
        }
        while (($entry = \readdir($handle)) !== false) {
            if ($entry !== '.' && $entry !== '..') {
                $entries[] = $entry;
            }
        }
        \closedir($handle);
        \sort($entries);
        return $entries;
    }

    /**
     * @return list<string>
     */
    public function nlist(string $remotePath): array
    {
        return $this->ls($remotePath);
    }

    /**
     * @return array<string, array<int|string, int>>
     */
    public function rawlist(string $remotePath): array
    {
        $files = $this->ls($remotePath);
        $handle = $this->handle();
        $normalized = $this->normalizePath($remotePath);

        $result = [];
        foreach ($files as $file) {
            $fullPath = $normalized . '/' . $file;
            $stats = ($this->statCallback)($handle, $fullPath);
            if ($stats !== false) {
                $result[$file] = $stats;
            }
        }
        return $result;
    }

    public function rename(string $from, string $to): void
    {
        $handle = $this->handle();
        $normalizedFrom = $this->normalizePath($from);
        $normalizedTo = $this->normalizePath($to);

        $success = ($this->renameCallback)($handle, $normalizedFrom, $normalizedTo);
        if (!$success) {
            throw new \RuntimeException(\sprintf('Unable to rename remote path from %s to %s', $from, $to));
        }
    }

    /**
     * @return array<int|string, int>
     */
    public function stat(string $remotePath): array
    {
        $handle = $this->handle();
        $normalized = $this->normalizePath($remotePath);

        $stats = ($this->statCallback)($handle, $normalized);
        if ($stats === false) {
            throw new \RuntimeException(\sprintf('Unable to stat remote path: %s', $remotePath));
        }
        return $stats;
    }

    public function fileExists(string $remotePath): bool
    {
        try {
            $this->stat($remotePath);
            return true;
        } catch (\RuntimeException) {
            return false;
        }
    }

    public function symlink(string $target, string $link): void
    {
        $handle = $this->handle();
        $normalizedTarget = $this->normalizePath($target);
        $normalizedLink = $this->normalizePath($link);

        $success = ($this->symlinkCallback)($handle, $normalizedTarget, $normalizedLink);
        if (!$success) {
            throw new \RuntimeException(\sprintf('Unable to create symlink from %s to %s', $link, $target));
        }
    }

    public function readlink(string $link): string
    {
        $handle = $this->handle();
        $normalized = $this->normalizePath($link);

        $target = ($this->readlinkCallback)($handle, $normalized);

        /** @var string|false $target */
        if ($target === false) {
            throw new \RuntimeException(\sprintf('Unable to read symlink: %s', $link));
        }
        return $target;
    }

    public function metrics(): SFTPTransferMetrics
    {
        return $this->metrics;
    }

    private function normalizePath(string $remotePath): string
    {
        return \str_starts_with($remotePath, '/') ? $remotePath : '/' . $remotePath;
    }

    private function streamPath(string $remotePath): string
    {
        $handle = $this->handle();
        return \sprintf('%s://%d%s', $this->streamScheme, (int) $handle, $this->normalizePath($remotePath));
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

    /**
     * @return array<int|string, int>|false
     */
    private function nativeStat(mixed $handle, string $path): array|false
    {
        if (!\is_resource($handle)) {
            throw new \RuntimeException('SFTP resource is invalid.');
        }

        return \ssh2_sftp_stat($handle, $path);
    }

    private function nativeRename(mixed $handle, string $from, string $to): bool
    {
        if (!\is_resource($handle)) {
            throw new \RuntimeException('SFTP resource is invalid.');
        }

        return \ssh2_sftp_rename($handle, $from, $to);
    }

    private function nativeSymlink(mixed $handle, string $target, string $link): bool
    {
        if (!\is_resource($handle)) {
            throw new \RuntimeException('SFTP resource is invalid.');
        }

        return \ssh2_sftp_symlink($handle, $target, $link);
    }

    private function nativeReadlink(mixed $handle, string $link): string|false
    {
        if (!\is_resource($handle)) {
            throw new \RuntimeException('SFTP resource is invalid.');
        }

        return \ssh2_sftp_readlink($handle, $link);
    }

    private function nativeChmod(mixed $handle, string $path, int $mode): bool
    {
        if (!\is_resource($handle)) {
            throw new \RuntimeException('SFTP resource is invalid.');
        }

        return \ssh2_sftp_chmod($handle, $path, $mode);
    }
}
