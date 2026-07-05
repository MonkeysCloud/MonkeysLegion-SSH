<?php

namespace MonkeysLegion\SSH\Core;

use MonkeysLegion\SSH\Authentication\AuthenticationDriver;
use MonkeysLegion\SSH\Authentication\PasswordAuthentication;
use MonkeysLegion\SSH\Authentication\PublicKeyAuthentication;

class ConnectionBuilder
{
    private string $host = '';
    private int $port = 22;
    private string $username = '';
    private int $timeout = 10;
    private ?AuthenticationDriver $auth = null;

    public function to(string $host): self
    {
        $this->host = $host;
        return $this;
    }

    public function port(int $port): self
    {
        $this->port = $port;
        return $this;
    }

    public function as(string $username): self
    {
        $this->username = $username;
        return $this;
    }

    public function withPassword(string $password): self
    {
        $this->auth = new PasswordAuthentication($password);
        return $this;
    }

    public function withKey(string $privateKeyPath, ?string $passphrase = null): self
    {
        $this->auth = new PublicKeyAuthentication($privateKeyPath, $passphrase);
        return $this;
    }

    public function timeout(int $seconds): self
    {
        $this->timeout = $seconds;
        return $this;
    }

    /**
     * @param array<string, mixed> $profile
     */
    public function fromProfile(array $profile): self
    {
        $host = $profile['host'] ?? null;
        if (!\is_string($host) || $host === '') {
            throw new \InvalidArgumentException('Connection profile requires a valid host.');
        }

        $username = $profile['username'] ?? null;
        if (!\is_string($username) || $username === '') {
            throw new \InvalidArgumentException('Connection profile requires a valid username.');
        }

        $port = $profile['port'] ?? 22;
        if (!\is_int($port) && !\is_numeric($port)) {
            throw new \InvalidArgumentException('Connection profile contains an invalid port.');
        }

        $timeout = $profile['timeout'] ?? 10;
        if (!\is_int($timeout) && !\is_numeric($timeout)) {
            throw new \InvalidArgumentException('Connection profile contains an invalid timeout.');
        }

        $auth = $profile['auth'] ?? 'password';
        if (!\is_string($auth)) {
            throw new \InvalidArgumentException('Connection profile contains an invalid auth type.');
        }

        $this
            ->to($host)
            ->port((int) $port)
            ->as($username)
            ->timeout((int) $timeout);

        if ($auth === 'key') {
            $privateKey = $profile['private_key'] ?? null;
            if (!\is_string($privateKey) || $privateKey === '') {
                throw new \InvalidArgumentException('Connection profile requires a valid private_key for key auth.');
            }

            $passphrase = $profile['passphrase'] ?? null;
            if ($passphrase !== null && !\is_string($passphrase)) {
                throw new \InvalidArgumentException('Connection profile contains an invalid passphrase value.');
            }

            return $this->withKey($privateKey, $passphrase);
        }

        $password = $profile['password'] ?? '';
        if (!\is_string($password)) {
            throw new \InvalidArgumentException('Connection profile contains an invalid password value.');
        }

        return $this->withPassword($password);
    }

    public function connect(): SSHConnection
    {
        if (!$this->auth) {
            throw new \InvalidArgumentException('Authentication method must be specified');
        }

        if ($this->host === '') {
            throw new \InvalidArgumentException('Host must be specified');
        }

        if ($this->username === '') {
            throw new \InvalidArgumentException('Username must be specified');
        }

        $connection = new SSHConnection($this->auth, $this->username);
        $connection->connect($this->host, $this->port, $this->timeout);

        return $connection;
    }
}
