<?php

namespace MonkeysLegion\SSH\Facades;

use MonkeysLegion\SSH\Core\ConnectionBuilder;
use MonkeysLegion\SSH\Core\SSHConnection;
use MonkeysLegion\SSH\Core\SSHManager;
use MonkeysLegion\SSH\SFTP\SFTPClient;
use MonkeysLegion\SSH\Stream\CommandResult;
use MonkeysLegion\SSH\Testing\FakeSSHConnection;
use MonkeysLegion\SSH\Testing\SSHFakeRegistry;

/**
 * @method static bool isConnected()
 * @method static CommandResult execute(string $command, ?int $timeout = null)
 * @method static SFTPClient sftp()
 * @method static void disconnect()
 */
class SSH
{
    private static ?SSHManager $manager = null;
    private static ?SSHFakeRegistry $fakeRegistry = null;

    public static function setManager(SSHManager $manager): void
    {
        self::$fakeRegistry = null;
        self::$manager = $manager;
    }

    public static function manager(): SSHManager
    {
        if (!self::$manager) {
            self::$manager = new SSHManager();
        }
        return self::$manager;
    }

    public static function connection(?string $name = null): SSHConnection
    {
        return self::manager()->connection($name);
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function configure(array $config): void
    {
        self::$fakeRegistry = null;
        self::$manager = new SSHManager($config);
    }

    /**
     * @param array<string, mixed> $profile
     */
    public static function register(string $name, array $profile): void
    {
        self::manager()->register($name, $profile);
    }

    public static function useDefaultConnection(string $name): void
    {
        self::manager()->setDefaultConnection($name);
    }

    public static function forgetConnection(?string $name = null): void
    {
        self::manager()->forgetConnection($name);
    }

    public static function runtime(): ConnectionBuilder
    {
        return self::manager()->runtime();
    }

    /**
     * @param array<int, mixed> $arguments
     */
    public static function __callStatic(string $method, array $arguments): mixed
    {
        $connection = self::connection();
        if (!\method_exists($connection, $method)) {
            throw new \BadMethodCallException(\sprintf('Method [%s] does not exist on SSH connection.', $method));
        }

        $result = $connection->{$method}(...$arguments);
        return $result;
    }

    /**
     * @param array<string, CommandResult> $responses
     */
    public static function fake(array $responses = []): SSHFakeRegistry
    {
        $registry = new SSHFakeRegistry();
        foreach ($responses as $command => $result) {
            $registry->reply($command, $result);
        }

        self::$fakeRegistry = $registry;
        self::$manager = new SSHManager(
            [
                'default' => 'fake',
                'connections' => [
                    'fake' => [
                        'host' => 'fake-host',
                        'username' => 'fake-user',
                        'auth' => 'password',
                        'password' => 'fake-password',
                    ],
                ],
            ],
            static fn (): SSHConnection => new FakeSSHConnection($registry),
        );

        return $registry;
    }

    public static function fakeCommand(string $command, CommandResult $result): void
    {
        self::fakeRegistry()->reply($command, $result);
    }

    public static function fakeDefault(CommandResult $result): void
    {
        self::fakeRegistry()->setDefault($result);
    }

    public static function assertExecuted(string $command): void
    {
        if (!self::fakeRegistry()->wasExecuted($command)) {
            throw new \RuntimeException(\sprintf('Expected command [%s] to be executed by SSH fake.', $command));
        }
    }

    private static function fakeRegistry(): SSHFakeRegistry
    {
        if (self::$fakeRegistry === null) {
            throw new \RuntimeException('SSH fake is not active. Call SSH::fake() before using fake helpers.');
        }

        return self::$fakeRegistry;
    }
}
