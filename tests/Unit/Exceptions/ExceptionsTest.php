<?php

namespace Tests\Unit\Exceptions;

use MonkeysLegion\SSH\Exceptions\AuthenticationFailedException;
use MonkeysLegion\SSH\Exceptions\ConnectionRefusedException;
use PHPUnit\Framework\TestCase;

class ExceptionsTest extends TestCase
{
    public function test_connection_refused_exception_contains_host_and_port(): void
    {
        $exception = ConnectionRefusedException::forHost('127.0.0.1', 2222);
        $this->assertStringContainsString('127.0.0.1:2222', $exception->getMessage());
    }

    public function test_authentication_failed_exception_for_password_contains_username(): void
    {
        $exception = AuthenticationFailedException::password('forge');
        $this->assertStringContainsString('[forge]', $exception->getMessage());
    }

    public function test_authentication_failed_exception_for_public_key_contains_key_path(): void
    {
        $exception = AuthenticationFailedException::publicKey('forge', '/keys/id_rsa');
        $this->assertStringContainsString('/keys/id_rsa', $exception->getMessage());
    }
}
