<?php

namespace MonkeysLegion\SSH\Core;

use MonkeysLegion\SSH\Authentication\AgentAuthentication;
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
    private ?string $fingerprint = null;
    private ?int $commandTimeout = null;
    private int $maxOutputSize = 52428800; // 50 MB
    private int $keepaliveInterval = 0;
    private ?string $termType = null;
    private int $ptyWidth = 80;
    private int $ptyHeight = 25;

    /** @return array{term_type: ?string, width: int, height: int} */
    public function ptyConfig(): array
    {
        return [
            'term_type' => $this->termType,
            'width' => $this->ptyWidth,
            'height' => $this->ptyHeight,
        ];
    }

    public function to(string $host): self
    {
        $this->host = $host;
        return $this;
    }

    public function port(int $port): self
    {
        if ($port < 1 || $port > 65535) {
            throw new \InvalidArgumentException('Port must be between 1 and 65535.');
        }
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

    public function withAgent(): self
    {
        $this->auth = new AgentAuthentication();
        return $this;
    }

    public function timeout(int $seconds): self
    {
        if ($seconds < 1) {
            throw new \InvalidArgumentException('Timeout must be at least 1 second.');
        }
        $this->timeout = $seconds;
        return $this;
    }

    public function withFingerprint(string $fingerprint): self
    {
        $this->fingerprint = $fingerprint;
        return $this;
    }

    public function commandTimeout(?int $seconds): self
    {
        $this->commandTimeout = $seconds;
        return $this;
    }

    public function maxOutputSize(int $bytes): self
    {
        if ($bytes < 1) {
            throw new \InvalidArgumentException('Max output size must be at least 1 byte.');
        }
        $this->maxOutputSize = $bytes;
        return $this;
    }

    public function keepalive(int $seconds): self
    {
        if ($seconds < 1) {
            throw new \InvalidArgumentException('Keepalive interval must be at least 1 second.');
        }
        $this->keepaliveInterval = $seconds;
        return $this;
    }

    public function withPty(?string $termType = 'xterm-256color', int $width = 80, int $height = 25): self
    {
        if ($width < 1 || $height < 1) {
            throw new \InvalidArgumentException('PTY dimensions must be positive.');
        }

        $this->termType = $termType;
        $this->ptyWidth = $width;
        $this->ptyHeight = $height;

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

        $port = $this->coercePositiveInt($profile['port'] ?? 22, 'port');
        $timeout = $this->coercePositiveInt($profile['timeout'] ?? 10, 'timeout');

        $auth = $profile['auth'] ?? 'password';
        if (!\is_string($auth)) {
            throw new \InvalidArgumentException('Connection profile contains an invalid auth type.');
        }

        $this
            ->to($host)
            ->port($port)
            ->as($username)
            ->timeout($timeout);

        // Optional fingerprint for host key verification
        $fingerprint = $profile['fingerprint'] ?? null;
        if ($fingerprint !== null) {
            if (!\is_string($fingerprint) || $fingerprint === '') {
                throw new \InvalidArgumentException('Connection profile contains an invalid fingerprint.');
            }
            $this->withFingerprint($fingerprint);
        }

        // Optional command execution timeout
        $commandTimeout = $profile['command_timeout'] ?? null;
        if ($commandTimeout !== null) {
            $this->commandTimeout = $this->coercePositiveInt($commandTimeout, 'command_timeout');
        }

        // Optional max output size
        $maxOutput = $profile['max_output_size'] ?? null;
        if ($maxOutput !== null) {
            $this->maxOutputSize($this->coercePositiveInt($maxOutput, 'max_output_size'));
        }

        // Optional keepalive interval
        $keepalive = $profile['keepalive_interval'] ?? null;
        if ($keepalive !== null) {
            $this->keepalive($this->coercePositiveInt($keepalive, 'keepalive_interval'));
        }

        // Optional PTY settings
        $termType = $profile['term_type'] ?? null;
        if ($termType !== null) {
            if (!\is_string($termType)) {
                throw new \InvalidArgumentException('Connection profile contains an invalid term_type.');
            }

            $ptyWidth = isset($profile['pty_width']) ? $this->coercePositiveInt($profile['pty_width'], 'pty_width') : 80;
            $ptyHeight = isset($profile['pty_height']) ? $this->coercePositiveInt($profile['pty_height'], 'pty_height') : 25;

            $this->withPty($termType, $ptyWidth, $ptyHeight);
        }

        return match ($auth) {
            'key' => $this->configureKeyAuth($profile),
            'agent' => $this->withAgent(),
            default => $this->configurePasswordAuth($profile),
        };
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

        $connection = new SSHConnection(
            $this->auth,
            $this->username,
            commandTimeout: $this->commandTimeout,
            maxOutputSize: $this->maxOutputSize,
            keepaliveInterval: $this->keepaliveInterval,
        );
        $connection->connect($this->host, $this->port, $this->timeout, $this->fingerprint);

        return $connection;
    }

    /**
     * @param array<string, mixed> $profile
     */
    private function configureKeyAuth(array $profile): self
    {
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

    /**
     * @param array<string, mixed> $profile
     */
    private function configurePasswordAuth(array $profile): self
    {
        $password = $profile['password'] ?? '';
        if (!\is_string($password)) {
            throw new \InvalidArgumentException('Connection profile contains an invalid password value.');
        }

        return $this->withPassword($password);
    }

    /**
     * Coerce a value to a positive integer, rejecting floats/hex/non-numeric strings.
     */
    private function coercePositiveInt(mixed $value, string $field): int
    {
        if (\is_int($value)) {
            if ($value < 1) {
                throw new \InvalidArgumentException(\sprintf('Connection profile field [%s] must be at least 1.', $field));
            }
            return $value;
        }

        if (\is_string($value) && \ctype_digit($value)) {
            return (int) $value;
        }

        throw new \InvalidArgumentException(\sprintf('Connection profile field [%s] must be a positive integer.', $field));
    }
}
