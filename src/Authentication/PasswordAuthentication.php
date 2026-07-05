<?php

namespace MonkeysLegion\SSH\Authentication;

use MonkeysLegion\SSH\Exceptions\AuthenticationFailedException;

class PasswordAuthentication implements AuthenticationDriver
{
    /**
     * @var \Closure(mixed, string, string): bool
     */
    private \Closure $authenticator;

    public function __construct(private string $password, ?callable $authenticator = null)
    {
        $this->authenticator = $authenticator !== null
            ? $authenticator(...)
            : $this->nativeAuthenticator(...);
    }

    public function authenticate(mixed $resource, string $username): bool
    {
        $authenticated = ($this->authenticator)($resource, $username, $this->password);
        if ($authenticated) {
            return true;
        }

        throw AuthenticationFailedException::password($username);
    }

    private function nativeAuthenticator(mixed $resource, string $username, string $password): bool
    {
        if (!\is_resource($resource)) {
            throw new \InvalidArgumentException('SSH resource must be a valid resource.');
        }

        return \ssh2_auth_password($resource, $username, $password);
    }
}
