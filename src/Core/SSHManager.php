<?php

namespace MonkeysLegion\SSH\Core;

class SSHManager
{
    /**
     * @var array<string, SSHConnection>
     */
    private array $connections = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $profiles = [];

    /**
     * @var \Closure(array<string, mixed>): SSHConnection
     */
    private \Closure $connectionResolver;

    private string $defaultConnection = 'default';

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [], ?callable $connectionResolver = null)
    {
        $this->connectionResolver = $connectionResolver !== null
            ? $connectionResolver(...)
            : $this->defaultConnectionResolver(...);

        $defaultConnection = $config['default'] ?? 'default';
        if (!\is_string($defaultConnection) || $defaultConnection === '') {
            throw new \InvalidArgumentException('Default SSH connection name must be a non-empty string.');
        }
        $this->defaultConnection = $defaultConnection;

        $connections = $config['connections'] ?? [];
        if (!\is_array($connections)) {
            throw new \InvalidArgumentException('SSH connections configuration must be an array.');
        }

        foreach ($connections as $name => $profile) {
            if (!\is_string($name) || !\is_array($profile)) {
                throw new \InvalidArgumentException('SSH connections must be keyed by name and contain array profiles.');
            }

            /** @var array<string, mixed> $profile */
            $this->validateProfile($name, $profile);
            $this->profiles[$name] = $profile;
        }
    }

    public function connection(?string $name = null): SSHConnection
    {
        $name ??= $this->defaultConnection;

        // Return cached connection if available
        if (isset($this->connections[$name])) {
            return $this->connections[$name];
        }

        $config = $this->profiles[$name] ?? null;
        if (!\is_array($config)) {
            throw new \InvalidArgumentException(\sprintf('SSH connection [%s] is not configured.', (string) $name));
        }

        $connection = ($this->connectionResolver)($config);
        $this->connections[$name] = $connection;
        return $connection;
    }

    /**
     * @param array<string, mixed> $profile
     */
    public function register(string $name, array $profile): self
    {
        if ($name === '') {
            throw new \InvalidArgumentException('Connection name must be non-empty.');
        }

        $this->validateProfile($name, $profile);

        $this->profiles[$name] = $profile;
        unset($this->connections[$name]);

        return $this;
    }

    /**
     * @param array<array-key, mixed> $profiles
     */
    public function registerMany(array $profiles): self
    {
        foreach ($profiles as $name => $profile) {
            if (!\is_string($name) || !\is_array($profile)) {
                throw new \InvalidArgumentException('registerMany expects profiles keyed by connection name.');
            }

            /** @var array<string, mixed> $profile */
            $this->register($name, $profile);
        }

        return $this;
    }

    public function setDefaultConnection(string $name): self
    {
        if ($name === '') {
            throw new \InvalidArgumentException('Default connection name must be non-empty.');
        }

        $this->defaultConnection = $name;
        return $this;
    }

    public function forgetConnection(?string $name = null): void
    {
        if ($name === null) {
            $this->connections = [];
            return;
        }

        unset($this->connections[$name]);
    }

    public function runtime(): ConnectionBuilder
    {
        return new ConnectionBuilder();
    }

    /**
     * @param array<string, mixed> $profile
     */
    private function defaultConnectionResolver(array $profile): SSHConnection
    {
        return $this->runtime()
            ->fromProfile($profile)
            ->connect();
    }

    public function hasProfile(string $name): bool
    {
        return isset($this->profiles[$name]);
    }

    /**
     * Validate that a profile has the required fields for connection resolution.
     *
     * @param array<string, mixed> $profile
     */
    private function validateProfile(string $name, array $profile): void
    {
        $host = $profile['host'] ?? null;
        if (!\is_string($host) || $host === '') {
            throw new \InvalidArgumentException(\sprintf('Connection profile [%s] requires a non-empty host.', $name));
        }

        $username = $profile['username'] ?? null;
        if (!\is_string($username) || $username === '') {
            throw new \InvalidArgumentException(\sprintf('Connection profile [%s] requires a non-empty username.', $name));
        }

        $auth = $profile['auth'] ?? 'password';
        if (!\is_string($auth) || !\in_array($auth, ['password', 'key', 'agent'], true)) {
            throw new \InvalidArgumentException(\sprintf(
                'Connection profile [%s] auth must be one of: password, key, agent.',
                $name,
            ));
        }
    }
}
