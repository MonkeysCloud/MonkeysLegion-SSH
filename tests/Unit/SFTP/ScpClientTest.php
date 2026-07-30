<?php

namespace Tests\Unit\SFTP;

use MonkeysLegion\SSH\SFTP\ScpClient;
use PHPUnit\Framework\TestCase;

class ScpClientTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (\file_exists($file)) {
                \unlink($file);
            }
        }
        $this->tempFiles = [];
    }

    private function fakeSession(): mixed
    {
        return \fopen('php://temp', 'rb+');
    }

    private function createTempFile(string $content): string
    {
        $file = \tempnam(\sys_get_temp_dir(), 'mlssh-scp-test-');
        if ($file === false) {
            throw new \RuntimeException('Unable to create temporary file.');
        }
        \file_put_contents($file, $content);
        $this->tempFiles[] = $file;
        return $file;
    }

    // ----- send -----

    public function test_send_calls_sender_and_succeeds(): void
    {
        $session = $this->fakeSession();
        $local = $this->createTempFile('scp-data');
        $sentArgs = [];
        $sender = static function (mixed $s, string $local, string $remote, int $mode) use (&$sentArgs): bool {
            $sentArgs = [$s, $local, $remote, $mode];
            return true;
        };

        $client = new ScpClient($session, 0o644, $sender);
        $client->send($local, '/remote/path.txt');

        $this->assertCount(4, $sentArgs);
        $this->assertSame($local, $sentArgs[1]);
        $this->assertSame('/remote/path.txt', $sentArgs[2]);
        $this->assertSame(0o644, $sentArgs[3]);
    }

    public function test_send_uses_custom_mode(): void
    {
        $session = $this->fakeSession();
        $local = $this->createTempFile('data');
        $sentMode = null;
        $sender = static function (mixed $s, string $local, string $remote, int $mode) use (&$sentMode): bool {
            $sentMode = $mode;
            return true;
        };

        $client = new ScpClient($session, 0o644, $sender);
        $client->send($local, '/remote/path.txt', 0o600);

        $this->assertSame(0o600, $sentMode);
    }

    public function test_send_throws_when_local_file_does_not_exist(): void
    {
        $session = $this->fakeSession();
        $sender = static fn (): bool => true;

        $client = new ScpClient($session, 0o644, $sender);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Local file does not exist');
        $client->send('/nonexistent/file.txt', '/remote/path.txt');
    }

    public function test_send_throws_when_sender_returns_false(): void
    {
        $session = $this->fakeSession();
        $local = $this->createTempFile('data');
        $sender = static fn (mixed $s, string $local, string $remote, int $mode): bool => false;

        $client = new ScpClient($session, 0o644, $sender);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to send file via SCP');
        $client->send($local, '/remote/path.txt');
    }

    // ----- receive -----

    public function test_receive_calls_receiver_and_succeeds(): void
    {
        $session = $this->fakeSession();
        $local = \sys_get_temp_dir() . '/mlssh-scp-recv-test.txt';
        $this->tempFiles[] = $local;
        $recvArgs = [];
        $receiver = static function (mixed $s, string $remote, string $local) use (&$recvArgs): bool {
            $recvArgs = [$s, $remote, $local];
            return true;
        };

        $client = new ScpClient($session, 0o644, null, $receiver);
        $client->receive('/remote/source.txt', $local);

        $this->assertCount(3, $recvArgs);
        $this->assertSame('/remote/source.txt', $recvArgs[1]);
        $this->assertSame($local, $recvArgs[2]);
    }

    public function test_receive_throws_when_local_directory_does_not_exist(): void
    {
        $session = $this->fakeSession();
        $receiver = static fn (): bool => true;

        $client = new ScpClient($session, 0o644, null, $receiver);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Local directory does not exist');
        $client->receive('/remote/file.txt', '/nonexistent/dir/file.txt');
    }

    public function test_receive_throws_when_receiver_returns_false(): void
    {
        $session = $this->fakeSession();
        $local = \sys_get_temp_dir() . '/mlssh-scp-fail.txt';
        $this->tempFiles[] = $local;
        $receiver = static fn (mixed $s, string $remote, string $local): bool => false;

        $client = new ScpClient($session, 0o644, null, $receiver);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to receive file via SCP');
        $client->receive('/remote/file.txt', $local);
    }
}
