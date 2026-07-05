<?php

namespace MonkeysLegion\SSH\Authentication;

interface AuthenticationDriver
{
    public function authenticate(mixed $resource, string $username): bool;
}
