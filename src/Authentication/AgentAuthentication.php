<?php

namespace MonkeysLegion\SSH\Authentication;

use MonkeysLegion\SSH\Exceptions\AuthenticationFailedException;

class AgentAuthentication implements AuthenticationDriver
{
    /**
     * @var \Closure(mixed, string): bool
     */
    private \Closure $authenticator;

    public function __construct(?callable $authenticator = null)
    {
        $this->authenticator = $authenticator !== null
            ? $authenticator(...)
            : $this->nativeAuthenticator(...);
    }

    public function authenticate(mixed $resource, string $username): bool
    {
        $authenticated = ($this->authenticator)($resource, $username);
        if ($authenticated) {
            return true;
        }

        throw AuthenticationFailedException::agent($username);
    }

    private function nativeAuthenticator(mixed $resource, string $username): bool
    {
        if (!\is_resource($resource)) {
            throw new \InvalidArgumentException('SSH resource must be a valid resource.');
        }

        return \ssh2_auth_agent($resource, $username);
    }
}
