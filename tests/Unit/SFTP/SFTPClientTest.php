<?php

namespace Tests\Unit\SFTP;

use MonkeysLegion\SSH\SFTP\SFTPClient;
use MonkeysLegion\SSH\SFTP\SFTPTransferMetrics;
use PHPUnit\Framework\TestCase;

/**
 * Custom stream wrapper that simulates the ssh2.sftp:// protocol for unit testing.
 *
 * It stores file contents in memory keyed by path so we can verify reads/writes.
 */
class FakeSftpStreamWrapper
{
    /** @var array<string, string> */
    private static array $storage = [];

    private int $position = 0;
    private string $path = '';

    public static function reset(): void
    {
        self::$storage = [];
    }

    public static function has(string $path): bool
    {
        return isset(self::$storage[$path]);
    }

    public static function content(string $path): ?string
    {
        return self::$storage[$path] ?? null;
    }

    public static function set(string $path, string $content): void
    {
        self::$storage[$path] = $content;
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        // Strip the ssh2.sftp://N prefix to get the remote path
        $this->path = \preg_replace('#^ssh2\.sftp://\d+#', '', $path) ?? $path;
        $this->position = 0;

        if (\str_contains($mode, 'w')) {
            self::$storage[$this->path] = '';
        } elseif (!isset(self::$storage[$this->path])) {
            return false; // File doesn't exist for read
        }

        return true;
    }

    public function stream_read(int $count): string|false
    {
        $content = self::$storage[$this->path] ?? '';
        $chunk = \substr($content, $this->position, $count);
        $this->position += \strlen($chunk);
        return $chunk;
    }

    public function stream_write(string $data): int
    {
        $current = self::$storage[$this->path] ?? '';
        self::$storage[$this->path] = \substr($current, 0, $this->position) . $data . \substr($current, $this->position + \strlen($data));
        $this->position += \strlen($data);
        return \strlen($data);
    }

    public function stream_eof(): bool
    {
        return $this->position >= \strlen(self::$storage[$this->path] ?? '');
    }

    public function stream_close(): void
    {
        // no-op
    }

    /**
     * @return array<string, int>|false
     */
    public function stream_stat(): array|false
    {
        $size = \strlen(self::$storage[$this->path] ?? '');
        return [
            'size' => $size,
            'mode' => 0100644,
        ];
    }

    /**
     * @return array<string, int>|false
     */
    public function url_stat(string $path, int $flags): array|false
    {
        $remotePath = \preg_replace('#^ssh2.sftp://\d+#', '', $path) ?? $path;
        if (!isset(self::$storage[$remotePath])) {
            return false;
        }
        return [
            'size' => \strlen(self::$storage[$remotePath]),
            'mode' => 0100644,
        ];
    }
}

class SFTPClientTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        FakeSftpStreamWrapper::reset();
        if (\in_array('ssh2.sftp', \stream_get_wrappers(), true)) {
            \stream_wrapper_unregister('ssh2.sftp');
        }
        \stream_wrapper_register('ssh2.sftp', FakeSftpStreamWrapper::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (\file_exists($file)) {
                \unlink($file);
            }
        }
        $this->tempFiles = [];

        if (\in_array('ssh2.sftp', \stream_get_wrappers(), true)) {
            \stream_wrapper_unregister('ssh2.sftp');
        }
    }

    public function test_sftp_client_can_be_instantiated(): void
    {
        $session = \fopen('php://temp', 'rb+');
        $this->assertIsResource($session);

        $client = new SFTPClient($session);
        $this->assertInstanceOf(SFTPClient::class, $client);
    }

    public function test_metrics_returns_transfer_metrics(): void
    {
        $session = \fopen('php://temp', 'rb+');
        $metrics = new SFTPTransferMetrics();
        $client = new SFTPClient($session, $metrics);

        $this->assertSame($metrics, $client->metrics());
    }

    public function test_upload_throws_when_local_file_does_not_exist(): void
    {
        $session = \fopen('php://temp', 'rb+');
        $client = new SFTPClient($session, null, static fn (): mixed => \fopen('php://temp', 'rb+'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Local file does not exist');
        $client->upload('/nonexistent/local/file.txt', '/remote/file.txt');
    }

    public function test_download_throws_when_local_directory_does_not_exist(): void
    {
        $session = \fopen('php://temp', 'rb+');
        $client = new SFTPClient($session, null, static fn (): mixed => \fopen('php://temp', 'rb+'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Local directory does not exist');
        $client->download('/remote/file.txt', '/nonexistent/dir/file.txt');
    }

    public function test_handle_throws_when_initializer_returns_false(): void
    {
        $session = \fopen('php://temp', 'rb+');
        $client = new SFTPClient($session, null, static fn (): bool => false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to initialize SFTP subsystem.');
        $client->download('/remote/file.txt', \sys_get_temp_dir() . '/downloaded.txt');
    }

    public function test_handle_throws_when_initializer_returns_null(): void
    {
        $session = \fopen('php://temp', 'rb+');
        $client = new SFTPClient($session, null, static fn (): null => null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to initialize SFTP subsystem.');
        $client->download('/remote/file.txt', \sys_get_temp_dir() . '/downloaded.txt');
    }

    public function test_handle_throws_when_initializer_returns_non_resource(): void
    {
        $session = \fopen('php://temp', 'rb+');
        $client = new SFTPClient($session, null, static fn (): string => 'not-a-resource');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to initialize SFTP subsystem.');
        $client->download('/remote/file.txt', \sys_get_temp_dir() . '/downloaded.txt');
    }

    public function test_upload_transfers_file_and_records_metrics(): void
    {
        $session = \fopen('php://temp', 'rb+');
        $handle = \fopen('php://temp', 'rb+');
        $metrics = new SFTPTransferMetrics();
        $client = new SFTPClient($session, $metrics, static fn (): mixed => $handle);

        $localFile = $this->createTempFile('upload-content-here');
        $remotePath = '/remote/uploaded.txt';

        $bytes = $client->upload($localFile, $remotePath);

        $this->assertSame(\strlen('upload-content-here'), $bytes);
        $this->assertSame($bytes, $metrics->uploadedBytes());
        $this->assertSame(1, $metrics->uploadCount());
        $this->assertTrue(FakeSftpStreamWrapper::has($remotePath));
        $this->assertSame('upload-content-here', FakeSftpStreamWrapper::content($remotePath));
    }

    public function test_download_transfers_file_and_records_metrics(): void
    {
        $session = \fopen('php://temp', 'rb+');
        $handle = \fopen('php://temp', 'rb+');
        $metrics = new SFTPTransferMetrics();
        $client = new SFTPClient($session, $metrics, static fn (): mixed => $handle);

        $remotePath = '/remote/source.txt';
        FakeSftpStreamWrapper::set($remotePath, 'download-content-here');

        $localFile = \sys_get_temp_dir() . '/mlssh-download-test.txt';
        $this->tempFiles[] = $localFile;

        $bytes = $client->download($remotePath, $localFile);

        $this->assertSame(\strlen('download-content-here'), $bytes);
        $this->assertSame($bytes, $metrics->downloadedBytes());
        $this->assertSame(1, $metrics->downloadCount());
        $this->assertFileExists($localFile);
        $this->assertStringEqualsFile($localFile, 'download-content-here');
    }

    public function test_download_throws_when_remote_file_does_not_exist(): void
    {
        $session = \fopen('php://temp', 'rb+');
        $handle = \fopen('php://temp', 'rb+');
        $client = new SFTPClient($session, null, static fn (): mixed => $handle);

        $localFile = \sys_get_temp_dir() . '/mlssh-download-fail-test.txt';
        $this->tempFiles[] = $localFile;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to read remote file via SFTP stream wrapper.');
        $client->download('/remote/missing.txt', $localFile);
    }

    public function test_handle_is_cached_after_first_initialization(): void
    {
        $session = \fopen('php://temp', 'rb+');
        $initCalls = 0;
        $handle = \fopen('php://temp', 'rb+');
        $metrics = new SFTPTransferMetrics();
        $client = new SFTPClient(
            $session,
            $metrics,
            static function () use ($handle, &$initCalls): mixed {
                $initCalls++;
                return $handle;
            }
        );

        $localFile = $this->createTempFile('content-one');
        $client->upload($localFile, '/remote/one.txt');

        $localFile2 = $this->createTempFile('content-two');
        $client->upload($localFile2, '/remote/two.txt');

        // The SFTP handle should only be initialized once
        $this->assertSame(1, $initCalls);
        $this->assertSame(2, $metrics->uploadCount());
    }

    private function createTempFile(string $content): string
    {
        $file = \tempnam(\sys_get_temp_dir(), 'mlssh-test-');
        if ($file === false) {
            throw new \RuntimeException('Unable to create temporary file.');
        }
        \file_put_contents($file, $content);
        $this->tempFiles[] = $file;
        return $file;
    }
}
