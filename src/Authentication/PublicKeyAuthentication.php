<?php

namespace MonkeysLegion\SSH\Authentication;

use MonkeysLegion\SSH\Exceptions\AuthenticationFailedException;

class PublicKeyAuthentication implements AuthenticationDriver
{
    /**
     * @var \Closure(mixed, string, string, string, string): bool
     */
    private \Closure $authenticator;

    public function __construct(
        private string $privateKeyPath,
        private ?string $passphrase = null,
        private ?string $publicKeyPath = null,
        ?callable $authenticator = null
    ) {
        $this->authenticator = $authenticator !== null
            ? $authenticator(...)
            : $this->nativeAuthenticator(...);
    }

    public function authenticate(mixed $resource, string $username): bool
    {
        $privateKeyPath = $this->privateKeyPath;
        $publicKeyPath = $this->publicKeyPath ?? ($privateKeyPath . '.pub');
        $passphrase = $this->passphrase ?? '';

        if (!\is_file($privateKeyPath)) {
            throw new AuthenticationFailedException(\sprintf('Private key file does not exist: %s', $privateKeyPath));
        }

        if (!\is_file($publicKeyPath)) {
            throw new AuthenticationFailedException(\sprintf('Public key file does not exist: %s', $publicKeyPath));
        }

        $authenticated = ($this->authenticator)($resource, $username, $publicKeyPath, $privateKeyPath, $passphrase);
        if ($authenticated) {
            return true;
        }

        throw AuthenticationFailedException::publicKey($username, $privateKeyPath);
    }

    private function nativeAuthenticator(
        mixed $resource,
        string $username,
        string $publicKeyPath,
        string $privateKeyPath,
        string $passphrase
    ): bool {
        if (!\is_resource($resource)) {
            throw new \InvalidArgumentException('SSH resource must be a valid resource.');
        }

        return \ssh2_auth_pubkey_file($resource, $username, $publicKeyPath, $privateKeyPath, $passphrase);
    }
}
