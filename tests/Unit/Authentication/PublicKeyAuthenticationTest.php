<?php

namespace Tests\Unit\Authentication;

use MonkeysLegion\SSH\Authentication\PublicKeyAuthentication;
use MonkeysLegion\SSH\Exceptions\AuthenticationFailedException;
use PHPUnit\Framework\TestCase;

class PublicKeyAuthenticationTest extends TestCase
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

    public function test_public_key_authentication_can_be_instantiated(): void
    {
        $auth = new PublicKeyAuthentication('/path/to/key');
        $this->assertInstanceOf(PublicKeyAuthentication::class, $auth);
    }

    public function test_public_key_authentication_accepts_passphrase(): void
    {
        $auth = new PublicKeyAuthentication('/path/to/key', 'passphrase');
        $this->assertInstanceOf(PublicKeyAuthentication::class, $auth);
    }

    public function test_authenticate_uses_private_key_pub_suffix_when_public_key_is_not_provided(): void
    {
        $privateKeyPath = $this->createTempKeyFile('private-key');
        $publicKeyPath = $privateKeyPath . '.pub';
        \file_put_contents($publicKeyPath, 'public-key');
        $this->tempFiles[] = $publicKeyPath;

        $calls = [];
        $auth = new PublicKeyAuthentication(
            $privateKeyPath,
            'passphrase',
            null,
            static function (
                mixed $resource,
                string $username,
                string $publicKey,
                string $privateKey,
                string $passphrase,
            ) use (&$calls): bool {
                $calls[] = [$resource, $username, $publicKey, $privateKey, $passphrase];
                return true;
            },
        );

        $resource = \fopen('php://temp', 'rb+');
        $this->assertIsResource($resource);
        $this->assertTrue($auth->authenticate($resource, 'remote-user'));

        $this->assertSame([[$resource, 'remote-user', $publicKeyPath, $privateKeyPath, 'passphrase']], $calls);
    }

    public function test_authenticate_throws_when_private_key_file_does_not_exist(): void
    {
        $auth = new PublicKeyAuthentication('/path/to/missing-key');
        $resource = \fopen('php://temp', 'rb+');
        $this->assertIsResource($resource);

        $this->expectException(AuthenticationFailedException::class);
        $auth->authenticate($resource, 'remote-user');
    }

    private function createTempKeyFile(string $content): string
    {
        $file = \tempnam(\sys_get_temp_dir(), 'ssh-key-');
        if ($file === false) {
            throw new \RuntimeException('Unable to create temporary key file.');
        }

        \file_put_contents($file, $content);
        $this->tempFiles[] = $file;

        return $file;
    }
}
