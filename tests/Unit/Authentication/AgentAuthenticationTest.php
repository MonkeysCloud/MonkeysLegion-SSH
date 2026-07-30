<?php

namespace Tests\Unit\Authentication;

use MonkeysLegion\SSH\Authentication\AgentAuthentication;
use MonkeysLegion\SSH\Exceptions\AuthenticationFailedException;
use PHPUnit\Framework\TestCase;

class AgentAuthenticationTest extends TestCase
{
    public function test_agent_authentication_can_be_instantiated(): void
    {
        $auth = new AgentAuthentication();
        $this->assertInstanceOf(AgentAuthentication::class, $auth);
    }

    public function test_authenticate_calls_custom_authenticator(): void
    {
        $calls = [];
        $auth = new AgentAuthentication(
            static function (mixed $resource, string $username) use (&$calls): bool {
                $calls[] = [$resource, $username];
                return true;
            },
        );

        $resource = \fopen('php://temp', 'rb+');
        $this->assertIsResource($resource);

        $this->assertTrue($auth->authenticate($resource, 'remote-user'));
        $this->assertSame([[$resource, 'remote-user']], $calls);
    }

    public function test_authenticate_throws_when_authentication_fails(): void
    {
        $auth = new AgentAuthentication(
            static fn (): bool => false,
        );

        $resource = \fopen('php://temp', 'rb+');
        $this->assertIsResource($resource);

        $this->expectException(AuthenticationFailedException::class);
        $auth->authenticate($resource, 'remote-user');
    }

    public function test_authenticate_exception_contains_username(): void
    {
        $auth = new AgentAuthentication(
            static fn (): bool => false,
        );

        $resource = \fopen('php://temp', 'rb+');
        $this->assertIsResource($resource);

        try {
            $auth->authenticate($resource, 'deploy-user');
            $this->fail('Expected AuthenticationFailedException was not thrown.');
        } catch (AuthenticationFailedException $e) {
            $this->assertStringContainsString('[deploy-user]', $e->getMessage());
        }
    }
}
