<?php

namespace Tests\Unit\SFTP;

use MonkeysLegion\SSH\SFTP\SFTPClient;
use MonkeysLegion\SSH\SFTP\SFTPTransferMetrics;
use PHPUnit\Framework\TestCase;

class FakeSftpStreamWrapper
{
    /** @var array<string, string> */
    private static array $files = [];

    /** @var array<string, true> */
    private static array $dirs = [];

    /** @var list<string> */
    private static array $currentListing = [];

    public mixed $context;

    private int $position = 0;
    private string $path = '';
    private int $dirIndex = 0;

    public static function reset(): void
    {
        self::$files = [];
        self::$dirs = [];
        self::$currentListing = [];
    }

    public static function has(string $path): bool
    {
        return isset(self::$files[$path]);
    }

    public static function content(string $path): ?string
    {
        return self::$files[$path] ?? null;
    }

    public static function set(string $path, string $content): void
    {
        self::$files[$path] = $content;
        $dir = \dirname($path);
        if ($dir !== '.') {
            self::$dirs[$dir] = true;
        }
    }

    public static function addDir(string $path): void
    {
        self::$dirs[$path] = true;
    }

    public static function hasDir(string $path): bool
    {
        return isset(self::$dirs[$path]);
    }

    private function extractPath(string $url): string
    {
        return \preg_replace('#^sftp-fake://\d+#', '', $url) ?? $url;
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        $this->path = $this->extractPath($path);
        $this->position = 0;

        if (\str_contains($mode, 'w')) {
            self::$files[$this->path] = '';
            $dir = \dirname($this->path);
            if ($dir !== '.') {
                self::$dirs[$dir] = true;
            }
        } elseif (!isset(self::$files[$this->path])) {
            return false;
        }

        return true;
    }

    public function stream_read(int $count): string|false
    {
        $content = self::$files[$this->path] ?? '';
        $chunk = \substr($content, $this->position, $count);
        $this->position += \strlen($chunk);
        return $chunk;
    }

    public function stream_write(string $data): int
    {
        $current = self::$files[$this->path] ?? '';
        self::$files[$this->path] = \substr($current, 0, $this->position) . $data . \substr($current, $this->position + \strlen($data));
        $this->position += \strlen($data);
        return \strlen($data);
    }

    public function stream_eof(): bool
    {
        return $this->position >= \strlen(self::$files[$this->path] ?? '');
    }

    public function stream_close(): void
    {
    }

    /**
     * @return array<string, int>|false
     */
    public function stream_stat(): array|false
    {
        $size = \strlen(self::$files[$this->path] ?? '');
        return [
            'size' => $size,
            'mode' => 0o100644,
        ];
    }

    /**
     * @return array<string, int>|false
     */
    public function url_stat(string $path, int $flags): array|false
    {
        $ourPath = $this->extractPath($path);

        if (isset(self::$files[$ourPath])) {
            return [
                'size' => \strlen(self::$files[$ourPath]),
                'mode' => 0o100644,
            ];
        }

        if (isset(self::$dirs[$ourPath])) {
            return [
                'size' => 0,
                'mode' => 0o40755,
            ];
        }

        if ($flags & \STREAM_URL_STAT_QUIET) {
            return false;
        }

        return false;
    }

    public function dir_opendir(string $path, int $options): bool
    {
        $ourPath = $this->extractPath($path);

        if (!isset(self::$dirs[$ourPath]) && !isset(self::$files[$ourPath])) {
            if ($ourPath === '' || $ourPath === '/') {
                self::$dirs['/'] = true;
            } else {
                return false;
            }
        }

        if ($ourPath === '' || $ourPath === '/') {
            $ourPath = '';
        }

        self::$currentListing = [];

        foreach (self::$files as $filePath => $content) {
            $parent = \dirname($filePath);
            if ($parent === $ourPath || ($parent === '/' && $ourPath === '')) {
                $name = \basename($filePath);
                if (!\in_array($name, self::$currentListing, true)) {
                    self::$currentListing[] = $name;
                }
            }
        }

        foreach (self::$dirs as $dirPath => $_) {
            $parent = \dirname($dirPath);
            if (($parent === $ourPath || ($parent === '/' && $ourPath === '')) && $dirPath !== $ourPath) {
                $name = \basename($dirPath);
                if (!\in_array($name, self::$currentListing, true)) {
                    self::$currentListing[] = $name;
                }
            }
        }

        $this->dirIndex = 0;
        \sort(self::$currentListing);
        return true;
    }

    public function dir_readdir(): string|false
    {
        if ($this->dirIndex >= \count(self::$currentListing)) {
            return false;
        }
        return self::$currentListing[$this->dirIndex++];
    }

    public function dir_closedir(): bool
    {
        self::$currentListing = [];
        $this->dirIndex = 0;
        return true;
    }

    public function dir_rewinddir(): bool
    {
        $this->dirIndex = 0;
        return true;
    }

    public function mkdir(string $path, int $mode, int $options): bool
    {
        $ourPath = $this->extractPath($path);
        self::$dirs[$ourPath] = true;
        return true;
    }

    public function rmdir(string $path, int $options): bool
    {
        $ourPath = $this->extractPath($path);
        unset(self::$dirs[$ourPath]);
        return true;
    }

    public function rename(string $from, string $to): bool
    {
        $fromPath = $this->extractPath($from);
        $toPath = $this->extractPath($to);

        if (isset(self::$files[$fromPath])) {
            self::$files[$toPath] = self::$files[$fromPath];
            unset(self::$files[$fromPath]);
            $dir = \dirname($toPath);
            if ($dir !== '.') {
                self::$dirs[$dir] = true;
            }
            return true;
        }

        if (isset(self::$dirs[$fromPath])) {
            self::$dirs[$toPath] = true;
            unset(self::$dirs[$fromPath]);

            $movedPattern = \strlen($fromPath) + 1;
            foreach (self::$files as $filePath => $content) {
                if (\str_starts_with($filePath, $fromPath . '/')) {
                    $newPath = $toPath . '/' . \substr($filePath, $movedPattern);
                    self::$files[$newPath] = $content;
                    unset(self::$files[$filePath]);
                }
            }
            foreach (self::$dirs as $dirPath => $val) {
                if (\str_starts_with($dirPath, $fromPath . '/')) {
                    $newPath = $toPath . '/' . \substr($dirPath, $movedPattern);
                    self::$dirs[$newPath] = $val;
                    unset(self::$dirs[$dirPath]);
                }
            }
            return true;
        }

        return false;
    }

    public function unlink(string $path): bool
    {
        $ourPath = $this->extractPath($path);
        if (isset(self::$files[$ourPath])) {
            unset(self::$files[$ourPath]);
            return true;
        }
        return false;
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
        if (!\in_array('sftp-fake', \stream_get_wrappers(), true)) {
            \stream_wrapper_register('sftp-fake', FakeSftpStreamWrapper::class);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (\file_exists($file)) {
                \unlink($file);
            }
        }
        $this->tempFiles = [];
    }

    /**
     * @param array<string, callable> $nativeCallbacks
     */
    private function createClient(
        ?SFTPTransferMetrics $metrics = null,
        ?callable $initializer = null,
        array $nativeCallbacks = [],
    ): SFTPClient {
        $session = \fopen('php://temp', 'rb+');
        return new SFTPClient($session, $metrics, $initializer, 'sftp-fake', $nativeCallbacks);
    }

    // ----- Instantiation / metrics -----

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

    // ----- upload -----

    public function test_upload_throws_when_local_file_does_not_exist(): void
    {
        $client = $this->createClient();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Local file does not exist');
        $client->upload('/nonexistent/local/file.txt', '/remote/file.txt');
    }

    public function test_upload_transfers_file_and_records_metrics(): void
    {
        $handle = \fopen('php://temp', 'rb+');
        $metrics = new SFTPTransferMetrics();
        $client = $this->createClient($metrics, static fn (): mixed => $handle);

        $localFile = $this->createTempFile('upload-content-here');
        $remotePath = '/remote/uploaded.txt';

        $bytes = $client->upload($localFile, $remotePath);

        $this->assertSame(\strlen('upload-content-here'), $bytes);
        $this->assertSame($bytes, $metrics->uploadedBytes());
        $this->assertSame(1, $metrics->uploadCount());
        $this->assertTrue(FakeSftpStreamWrapper::has($remotePath));
        $this->assertSame('upload-content-here', FakeSftpStreamWrapper::content($remotePath));
    }

    // ----- download -----

    public function test_download_throws_when_local_directory_does_not_exist(): void
    {
        $client = $this->createClient(initializer: static fn (): mixed => \fopen('php://temp', 'rb+'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Local directory does not exist');
        $client->download('/remote/file.txt', '/nonexistent/dir/file.txt');
    }

    public function test_download_transfers_file_and_records_metrics(): void
    {
        $handle = \fopen('php://temp', 'rb+');
        $metrics = new SFTPTransferMetrics();
        $client = $this->createClient($metrics, static fn (): mixed => $handle);

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
        $handle = \fopen('php://temp', 'rb+');
        $client = $this->createClient(initializer: static fn (): mixed => $handle);

        $localFile = \sys_get_temp_dir() . '/mlssh-download-fail-test.txt';
        $this->tempFiles[] = $localFile;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to read remote file via SFTP stream wrapper.');
        $client->download('/remote/missing.txt', $localFile);
    }

    // ----- handle initialization -----

    public function test_handle_throws_when_initializer_returns_false(): void
    {
        $client = $this->createClient(initializer: static fn (): bool => false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to initialize SFTP subsystem.');
        $client->download('/remote/file.txt', \sys_get_temp_dir() . '/downloaded.txt');
    }

    public function test_handle_throws_when_initializer_returns_null(): void
    {
        $client = $this->createClient(initializer: static fn (): null => null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to initialize SFTP subsystem.');
        $client->download('/remote/file.txt', \sys_get_temp_dir() . '/downloaded.txt');
    }

    public function test_handle_throws_when_initializer_returns_non_resource(): void
    {
        $client = $this->createClient(initializer: static fn (): string => 'not-a-resource');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to initialize SFTP subsystem.');
        $client->download('/remote/file.txt', \sys_get_temp_dir() . '/downloaded.txt');
    }

    public function test_handle_is_cached_after_first_initialization(): void
    {
        $handle = \fopen('php://temp', 'rb+');
        $initCalls = 0;
        $metrics = new SFTPTransferMetrics();
        $client = $this->createClient(
            $metrics,
            static function () use ($handle, &$initCalls): mixed {
                $initCalls++;
                return $handle;
            },
        );

        $localFile = $this->createTempFile('content-one');
        $client->upload($localFile, '/remote/one.txt');

        $localFile2 = $this->createTempFile('content-two');
        $client->upload($localFile2, '/remote/two.txt');

        $this->assertSame(1, $initCalls);
        $this->assertSame(2, $metrics->uploadCount());
    }

    // ----- ls / nlist -----

    public function test_ls_returns_list_of_files_in_directory(): void
    {
        $handle = \fopen('php://temp', 'rb+');
        $client = $this->createClient(initializer: static fn (): mixed => $handle);

        FakeSftpStreamWrapper::set('/files/a.txt', 'aaa');
        FakeSftpStreamWrapper::set('/files/b.txt', 'bbb');
        FakeSftpStreamWrapper::set('/files/sub/c.txt', 'ccc');

        $entries = $client->ls('/files');

        $this->assertSame(['a.txt', 'b.txt', 'sub'], $entries);
    }

    public function test_ls_returns_empty_array_for_empty_directory(): void
    {
        $handle = \fopen('php://temp', 'rb+');
        $client = $this->createClient(initializer: static fn (): mixed => $handle);

        FakeSftpStreamWrapper::addDir('/empty');

        $entries = $client->ls('/empty');
        $this->assertSame([], $entries);
    }

    public function test_ls_throws_for_nonexistent_directory(): void
    {
        $handle = \fopen('php://temp', 'rb+');
        $client = $this->createClient(initializer: static fn (): mixed => $handle);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to list remote directory');
        $client->ls('/does/not/exist');
    }

    public function test_nlist_is_alias_for_ls(): void
    {
        $handle = \fopen('php://temp', 'rb+');
        $client = $this->createClient(initializer: static fn (): mixed => $handle);

        FakeSftpStreamWrapper::set('/alias/x.txt', 'x');

        $this->assertSame($client->ls('/alias'), $client->nlist('/alias'));
    }

    public function test_ls_works_on_root_directory(): void
    {
        $handle = \fopen('php://temp', 'rb+');
        $client = $this->createClient(initializer: static fn (): mixed => $handle);

        FakeSftpStreamWrapper::set('/root_file.txt', 'root');

        $entries = $client->ls('/');
        $this->assertContains('root_file.txt', $entries);
    }

    // ----- rawlist -----

    public function test_rawlist_returns_stat_info_for_each_entry(): void
    {
        $handle = \fopen('php://temp', 'rb+');
        $client = $this->createClient(
            initializer: static fn (): mixed => $handle,
            nativeCallbacks: [
                'stat' => static function (mixed $h, string $path): array {
                    if (\str_ends_with($path, '/a.txt')) {
                        return ['size' => 10, 'mode' => 0o100644, 'uid' => 1000, 'gid' => 1000, 'atime' => 1, 'mtime' => 2];
                    }
                    if (\str_ends_with($path, '/b.txt')) {
                        return ['size' => 20, 'mode' => 0o100755, 'uid' => 1000, 'gid' => 1000, 'atime' => 3, 'mtime' => 4];
                    }
                    return ['size' => 0, 'mode' => 0o40755, 'uid' => 1000, 'gid' => 1000, 'atime' => 5, 'mtime' => 6];
                },
            ],
        );

        FakeSftpStreamWrapper::set('/raw/a.txt', '0123456789');
        FakeSftpStreamWrapper::set('/raw/b.txt', '01234567890123456789');

        $result = $client->rawlist('/raw');

        $this->assertArrayHasKey('a.txt', $result);
        $this->assertArrayHasKey('b.txt', $result);
        $this->assertSame(10, $result['a.txt']['size']);
        $this->assertSame(20, $result['b.txt']['size']);
    }

    public function test_rawlist_skips_entries_that_cannot_be_stated(): void
    {
        $handle = \fopen('php://temp', 'rb+');
        $client = $this->createClient(
            initializer: static fn (): mixed => $handle,
            nativeCallbacks: [
                'stat' => static fn (): false => false,
            ],
        );

        FakeSftpStreamWrapper::set('/raw2/a.txt', 'x');
        FakeSftpStreamWrapper::set('/raw2/b.txt', 'y');

        $result = $client->rawlist('/raw2');
        $this->assertSame([], $result);
    }

    // ----- mkdir -----

    public function test_mkdir_creates_remote_directory(): void
    {
        $handle = \fopen('php://temp', 'rb+');
        $client = $this->createClient(initializer: static fn (): mixed => $handle);

        $client->mkdir('/newdir', 0o755);

        $this->assertTrue(FakeSftpStreamWrapper::hasDir('/newdir'));
    }

    public function test_mkdir_does_not_throw_if_directory_already_exists(): void
    {
        $handle = \fopen('php://temp', 'rb+');
        $client = $this->createClient(initializer: static fn (): mixed => $handle);

        FakeSftpStreamWrapper::addDir('/exists');
        $client->mkdir('/exists', 0o755);
        $this->assertTrue(FakeSftpStreamWrapper::hasDir('/exists'));
    }

    // ----- chmod -----

    public function test_chmod_succeeds(): void
    {
        $handle = \fopen('php://temp', 'rb+');
        $chmodgedPath = null;
        $chmodgedMode = null;
        $client = $this->createClient(
            initializer: static fn (): mixed => $handle,
            nativeCallbacks: [
                'chmod' => static function (mixed $h, string $path, int $mode) use (&$chmodgedPath, &$chmodgedMode): bool {
                    $chmodgedPath = $path;
                    $chmodgedMode = $mode;
                    return true;
                },
            ],
        );

        $client->chmod('/chmod_test.txt', 0o644);

        $this->assertSame('/chmod_test.txt', $chmodgedPath);
        $this->assertSame(0o644, $chmodgedMode);
    }

    // ----- delete -----

    public function test_delete_removes_remote_file(): void
    {
        $handle = \fopen('php://temp', 'rb+');
        $client = $this->createClient(initializer: static fn (): mixed => $handle);

        FakeSftpStreamWrapper::set('/delete_me.txt', 'bye');

        $client->delete('/delete_me.txt');
        $this->assertFalse(FakeSftpStreamWrapper::has('/delete_me.txt'));
    }

    public function test_delete_throws_when_file_does_not_exist(): void
    {
        $handle = \fopen('php://temp', 'rb+');
        $client = $this->createClient(initializer: static fn (): mixed => $handle);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to delete remote path');
        $client->delete('/nonexistent.txt');
    }

    // ----- rename -----

    public function test_rename_moves_file(): void
    {
        $handle = \fopen('php://temp', 'rb+');
        $client = $this->createClient(
            initializer: static fn (): mixed => $handle,
            nativeCallbacks: [
                'rename' => static fn (mixed $h, string $from, string $to): bool => true,
            ],
        );

        $client->rename('/old.txt', '/new.txt');
        $this->assertTrue(true);
    }

    public function test_rename_throws_on_failure(): void
    {
        $handle = \fopen('php://temp', 'rb+');
        $client = $this->createClient(
            initializer: static fn (): mixed => $handle,
            nativeCallbacks: [
                'rename' => static fn (mixed $h, string $from, string $to): bool => false,
            ],
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to rename');
        $client->rename('/old.txt', '/new.txt');
    }

    // ----- stat -----

    public function test_stat_returns_file_info(): void
    {
        $handle = \fopen('php://temp', 'rb+');
        $client = $this->createClient(
            initializer: static fn (): mixed => $handle,
            nativeCallbacks: [
                'stat' => static fn (): array => [
                    'size' => 42,
                    'mode' => 0o100644,
                    'uid' => 1000,
                    'gid' => 1000,
                    'atime' => 100,
                    'mtime' => 200,
                ],
            ],
        );

        $info = $client->stat('/some/file.txt');

        $this->assertSame(42, $info['size']);
        $this->assertSame(0o100644, $info['mode']);
    }

    public function test_stat_throws_when_path_does_not_exist(): void
    {
        $handle = \fopen('php://temp', 'rb+');
        $client = $this->createClient(
            initializer: static fn (): mixed => $handle,
            nativeCallbacks: [
                'stat' => static fn (): false => false,
            ],
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to stat remote path');
        $client->stat('/missing.txt');
    }

    // ----- fileExists -----

    public function test_fileExists_returns_true_when_file_exists(): void
    {
        $handle = \fopen('php://temp', 'rb+');
        $client = $this->createClient(
            initializer: static fn (): mixed => $handle,
            nativeCallbacks: [
                'stat' => static fn (): array => ['size' => 1, 'mode' => 0o100644, 'uid' => 0, 'gid' => 0, 'atime' => 0, 'mtime' => 0],
            ],
        );

        $this->assertTrue($client->fileExists('/some/file.txt'));
    }

    public function test_fileExists_returns_false_when_file_does_not_exist(): void
    {
        $handle = \fopen('php://temp', 'rb+');
        $client = $this->createClient(
            initializer: static fn (): mixed => $handle,
            nativeCallbacks: [
                'stat' => static fn (): false => false,
            ],
        );

        $this->assertFalse($client->fileExists('/missing.txt'));
    }

    // ----- symlink -----

    public function test_symlink_creates_symlink(): void
    {
        $handle = \fopen('php://temp', 'rb+');
        $actualTarget = null;
        $actualLink = null;
        $client = $this->createClient(
            initializer: static fn (): mixed => $handle,
            nativeCallbacks: [
                'symlink' => static function (mixed $h, string $target, string $link) use (&$actualTarget, &$actualLink): bool {
                    $actualTarget = $target;
                    $actualLink = $link;
                    return true;
                },
            ],
        );

        $client->symlink('/real.txt', '/link.txt');

        $this->assertSame('/real.txt', $actualTarget);
        $this->assertSame('/link.txt', $actualLink);
    }

    public function test_symlink_throws_on_failure(): void
    {
        $handle = \fopen('php://temp', 'rb+');
        $client = $this->createClient(
            initializer: static fn (): mixed => $handle,
            nativeCallbacks: [
                'symlink' => static fn (): bool => false,
            ],
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to create symlink');
        $client->symlink('/target.txt', '/link.txt');
    }

    // ----- readlink -----

    public function test_readlink_returns_symlink_target(): void
    {
        $handle = \fopen('php://temp', 'rb+');
        $client = $this->createClient(
            initializer: static fn (): mixed => $handle,
            nativeCallbacks: [
                'readlink' => static fn (mixed $h, string $link): string => '/real/target.txt',
            ],
        );

        $target = $client->readlink('/symlink');
        $this->assertSame('/real/target.txt', $target);
    }

    public function test_readlink_throws_on_failure(): void
    {
        $handle = \fopen('php://temp', 'rb+');
        $client = $this->createClient(
            initializer: static fn (): mixed => $handle,
            nativeCallbacks: [
                'readlink' => static fn (): false => false,
            ],
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to read symlink');
        $client->readlink('/broken');
    }

    // ----- path normalization -----

    public function test_operations_work_with_relative_paths(): void
    {
        $handle = \fopen('php://temp', 'rb+');
        $client = $this->createClient(
            initializer: static fn (): mixed => $handle,
            nativeCallbacks: [
                'stat' => static fn (mixed $h, string $path): array => [
                    'size' => \strlen($path),
                    'mode' => 0o100644,
                    'uid' => 0, 'gid' => 0, 'atime' => 0, 'mtime' => 0,
                ],
            ],
        );

        FakeSftpStreamWrapper::set('/absolute/file.txt', 'abc');

        $entries = $client->ls('absolute');
        $this->assertSame(['file.txt'], $entries);

        $info = $client->stat('absolute/file.txt');
        $this->assertIsArray($info);
    }

    // ----- security: path traversal -----

    public function test_ls_rejects_path_traversal_attempt_as_nonexistent_directory(): void
    {
        $handle = \fopen('php://temp', 'rb+');
        $client = $this->createClient(initializer: static fn (): mixed => $handle);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to list remote directory');
        $client->ls('/../etc/passwd');
    }

    public function test_upload_rejects_path_traversal_in_remote_path(): void
    {
        $handle = \fopen('php://temp', 'rb+');
        $metrics = new SFTPTransferMetrics();
        $client = $this->createClient($metrics, static fn (): mixed => $handle);

        $localFile = $this->createTempFile('data');

        $bytes = $client->upload($localFile, '/../../etc/passwd');

        $this->assertSame(4, $bytes);
        $this->assertTrue(FakeSftpStreamWrapper::has('/../../etc/passwd'));
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
