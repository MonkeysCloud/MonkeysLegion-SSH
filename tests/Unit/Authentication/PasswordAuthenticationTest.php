<?php

namespace Tests\Unit\Authentication;

use MonkeysLegion\SSH\Exceptions\AuthenticationFailedException;
use MonkeysLegion\SSH\Authentication\PasswordAuthentication;
use PHPUnit\Framework\TestCase;

class PasswordAuthenticationTest extends TestCase
{
    public function test_password_authentication_can_be_instantiated(): void
    {
        $auth = new PasswordAuthentication('test-password');
        $this->assertInstanceOf(PasswordAuthentication::class, $auth);
    }

    public function test_authenticate_calls_native_authenticator(): void
    {
        $calls = [];
        $auth = new PasswordAuthentication(
            'test-password',
            static function (mixed $resource, string $username, string $password) use (&$calls): bool {
                $calls[] = [$resource, $username, $password];
                return true;
            }
        );

        $resource = \fopen('php://temp', 'rb+');
        $this->assertIsResource($resource);

        $this->assertTrue($auth->authenticate($resource, 'remote-user'));
        $this->assertSame([[$resource, 'remote-user', 'test-password']], $calls);
    }

    public function test_authenticate_throws_when_authentication_fails(): void
    {
        $auth = new PasswordAuthentication(
            'test-password',
            static fn (): bool => false
        );

        $resource = \fopen('php://temp', 'rb+');
        $this->assertIsResource($resource);

        $this->expectException(AuthenticationFailedException::class);
        $auth->authenticate($resource, 'remote-user');
    }
}
