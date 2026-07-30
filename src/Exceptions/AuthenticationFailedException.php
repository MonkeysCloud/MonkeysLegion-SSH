<?php

namespace MonkeysLegion\SSH\Exceptions;

class AuthenticationFailedException extends AuthenticationException
{
    public static function password(string $username): self
    {
        return new self(\sprintf('Password authentication failed for user [%s].', $username));
    }

    public static function publicKey(string $username, string $privateKeyPath): self
    {
        return new self(\sprintf(
            'Public key authentication failed for user [%s] using key [%s].',
            $username,
            $privateKeyPath,
        ));
    }

    public static function agent(string $username): self
    {
        return new self(\sprintf('SSH agent authentication failed for user [%s].', $username));
    }
}
