<?php

namespace MonkeysLegion\SSH\Exceptions;

class HostKeyMismatchException extends ConnectionException
{
    public static function forHost(string $host, string $expectedFingerprint): self
    {
        return new self(\sprintf(
            'Host key fingerprint mismatch for host [%s]. Expected fingerprint: %s',
            $host,
            $expectedFingerprint
        ));
    }
}
