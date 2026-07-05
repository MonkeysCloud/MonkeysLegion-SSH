<?php

namespace MonkeysLegion\SSH\Exceptions;

class ConnectionRefusedException extends ConnectionException
{
    public static function forHost(string $host, int $port): self
    {
        return new self(\sprintf('Unable to connect to %s:%d.', $host, $port));
    }
}
