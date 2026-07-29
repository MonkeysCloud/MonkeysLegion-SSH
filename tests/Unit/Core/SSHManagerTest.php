<?php

namespace Tests\Unit\Core;

use MonkeysLegion\SSH\Core\ConnectionBuilder;
use MonkeysLegion\SSH\Core\SSHManager;
use MonkeysLegion\SSH\Core\SSHConnection;
use PHPUnit\Framework\TestCase;

class SSHManagerTest extends TestCase
{
    /**
     * Helper: a resolver that returns stub connections.
     */
    private function stubResolver(int &$count = 0): callable
    {
        return static function () use (&$count): SSHConnection {
            $count++;
            /** @var SSHConnection $connection */
            $connection = (new \ReflectionClass(SSHConnection::class))->newInstanceWithoutConstructor();
            return $connection;
        };
    }

    // ----------------------------------------------------------------
    // Constructor / config validation
    // ----------------------------------------------------------------

    public function test_constructor_rejects_empty_default_connection_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Default SSH connection name must be a non-empty string.');

        new SSHManager(['default' => '']);
    }

    public function test_constructor_rejects_non_string_default_connection_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SSHManager(['default' => 123]);
    }

    public function test_constructor_rejects_non_array_connections(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SSH connections configuration must be an array.');

        new SSHManager(['connections' => 'not-an-array']);
    }

    public function test_constructor_rejects_non_string_connection_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SSH connections must be keyed by name and contain array profiles.');

        new SSHManager([
            'connections' => [
                0 => ['host' => 'localhost', 'username' => 'user', 'auth' => 'password', 'password' => 'secret'],
            ],
        ]);
    }

    public function test_constructor_rejects_non_array_profile(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SSH connections must be keyed by name and contain array profiles.');

        new SSHManager([
            'connections' => [
                'main' => 'not-an-array',
            ],
        ]);
    }

    public function test_constructor_validates_profiles_during_construction(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Connection profile [bad] requires a non-empty host.');

        new SSHManager([
            'connections' => [
                'bad' => ['username' => 'user', 'auth' => 'password', 'password' => 'secret'],
            ],
        ]);
    }

    // ----------------------------------------------------------------
    // Connection caching / lazy resolution
    // ----------------------------------------------------------------

    public function test_connection_uses_default_profile_and_caches_lazily(): void
    {
        $resolved = 0;
        $manager = new SSHManager(
            [
                'default' => 'main',
                'connections' => [
                    'main' => [
                        'host' => '127.0.0.1',
                        'username' => 'test',
                        'auth' => 'password',
                        'password' => 'secret',
                    ],
                ],
            ],
            $this->stubResolver($resolved)
        );

        $first = $manager->connection();
        $second = $manager->connection('main');

        $this->assertSame($first, $second);
        $this->assertSame(1, $resolved);
    }

    public function test_connection_caches_different_profiles_separately(): void
    {
        $resolved = 0;
        $manager = new SSHManager(
            [
                'default' => 'main',
                'connections' => [
                    'main' => [
                        'host' => '127.0.0.1',
                        'username' => 'test',
                        'auth' => 'password',
                        'password' => 'secret',
                    ],
                    'secondary' => [
                        'host' => '127.0.0.2',
                        'username' => 'test2',
                        'auth' => 'password',
                        'password' => 'secret2',
                    ],
                ],
            ],
            $this->stubResolver($resolved)
        );

        $first = $manager->connection('main');
        $second = $manager->connection('secondary');

        $this->assertNotSame($first, $second);
        $this->assertSame(2, $resolved);
    }

    public function test_connection_throws_for_unknown_profile(): void
    {
        $manager = new SSHManager();

        $this->expectException(\InvalidArgumentException::class);
        $manager->connection('unknown');
    }

    // ----------------------------------------------------------------
    // register()
    // ----------------------------------------------------------------

    public function test_register_adds_connection_profile(): void
    {
        $manager = new SSHManager([], $this->stubResolver());

        $manager->register('runtime', [
            'host' => '127.0.0.1',
            'username' => 'runtime',
            'auth' => 'password',
            'password' => 'runtime-pass',
        ]);

        $this->assertTrue($manager->hasProfile('runtime'));
    }

    public function test_register_returns_self(): void
    {
        $manager = new SSHManager();

        $this->assertSame($manager, $manager->register('x', [
            'host' => '127.0.0.1',
            'username' => 'user',
            'auth' => 'password',
            'password' => 'secret',
        ]));
    }

    public function test_register_rejects_empty_name(): void
    {
        $manager = new SSHManager();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Connection name must be non-empty.');

        $manager->register('', [
            'host' => '127.0.0.1',
            'username' => 'user',
            'auth' => 'password',
            'password' => 'secret',
        ]);
    }

    public function test_register_validates_profile_host(): void
    {
        $manager = new SSHManager();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Connection profile [bad] requires a non-empty host.');

        $manager->register('bad', ['username' => 'user', 'auth' => 'password', 'password' => 'secret']);
    }

    public function test_register_validates_profile_username(): void
    {
        $manager = new SSHManager();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Connection profile [bad] requires a non-empty username.');

        $manager->register('bad', ['host' => 'localhost', 'auth' => 'password', 'password' => 'secret']);
    }

    public function test_register_validates_profile_auth_type(): void
    {
        $manager = new SSHManager();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Connection profile [bad] auth must be one of: password, key, agent.');

        $manager->register('bad', ['host' => 'localhost', 'username' => 'user', 'auth' => 'invalid']);
    }

    public function test_register_accepts_key_auth_profile(): void
    {
        $manager = new SSHManager();

        $manager->register('key-conn', [
            'host' => 'localhost',
            'username' => 'user',
            'auth' => 'key',
            'private_key' => '/path/to/key',
        ]);

        $this->assertTrue($manager->hasProfile('key-conn'));
    }

    public function test_register_accepts_agent_auth_profile(): void
    {
        $manager = new SSHManager();

        $manager->register('agent-conn', [
            'host' => 'localhost',
            'username' => 'user',
            'auth' => 'agent',
        ]);

        $this->assertTrue($manager->hasProfile('agent-conn'));
    }

    public function test_register_defaults_auth_to_password_when_omitted(): void
    {
        $manager = new SSHManager();

        $manager->register('conn', [
            'host' => 'localhost',
            'username' => 'user',
            'password' => 'secret',
        ]);

        $this->assertTrue($manager->hasProfile('conn'));
    }

    public function test_register_clears_cached_connection_for_profile(): void
    {
        $resolved = 0;
        $manager = new SSHManager(
            [
                'default' => 'main',
                'connections' => [
                    'main' => [
                        'host' => '127.0.0.1',
                        'username' => 'test',
                        'auth' => 'password',
                        'password' => 'secret',
                    ],
                ],
            ],
            $this->stubResolver($resolved)
        );

        // Resolve and cache
        $first = $manager->connection('main');
        $this->assertSame(1, $resolved);

        // Re-register the profile (should clear cache)
        $manager->register('main', [
            'host' => '127.0.0.1',
            'username' => 'test',
            'auth' => 'password',
            'password' => 'new-secret',
        ]);

        // Re-resolving should create a new connection
        $second = $manager->connection('main');
        $this->assertNotSame($first, $second);
        $this->assertSame(2, $resolved);
    }

    // ----------------------------------------------------------------
    // registerMany()
    // ----------------------------------------------------------------

    public function test_register_many_adds_multiple_profiles(): void
    {
        $manager = new SSHManager();

        $manager->registerMany([
            'web' => ['host' => 'web.example.com', 'username' => 'deploy', 'auth' => 'password', 'password' => 'secret'],
            'db' => ['host' => 'db.example.com', 'username' => 'admin', 'auth' => 'password', 'password' => 'secret'],
        ]);

        $this->assertTrue($manager->hasProfile('web'));
        $this->assertTrue($manager->hasProfile('db'));
    }

    public function test_register_many_returns_self(): void
    {
        $manager = new SSHManager();

        $this->assertSame($manager, $manager->registerMany([]));
    }

    public function test_register_many_rejects_non_string_key(): void
    {
        $manager = new SSHManager();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('registerMany expects profiles keyed by connection name.');

        $manager->registerMany([
            0 => ['host' => 'localhost', 'username' => 'user', 'auth' => 'password', 'password' => 'secret'],
        ]);
    }

    public function test_register_many_rejects_non_array_profile(): void
    {
        $manager = new SSHManager();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('registerMany expects profiles keyed by connection name.');

        $manager->registerMany(['bad' => 'not-an-array']);
    }

    // ----------------------------------------------------------------
    // setDefaultConnection()
    // ----------------------------------------------------------------

    public function test_set_default_connection_changes_default(): void
    {
        $resolved = 0;
        $manager = new SSHManager(
            [
                'default' => 'main',
                'connections' => [
                    'main' => ['host' => '127.0.0.1', 'username' => 'test', 'auth' => 'password', 'password' => 'secret'],
                    'alt' => ['host' => '127.0.0.2', 'username' => 'test2', 'auth' => 'password', 'password' => 'secret2'],
                ],
            ],
            $this->stubResolver($resolved)
        );

        $manager->setDefaultConnection('alt');

        $connection = $manager->connection();
        $this->assertSame(1, $resolved);
    }

    public function test_set_default_connection_returns_self(): void
    {
        $manager = new SSHManager();

        $this->assertSame($manager, $manager->setDefaultConnection('x'));
    }

    public function test_set_default_connection_rejects_empty_name(): void
    {
        $manager = new SSHManager();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Default connection name must be non-empty.');

        $manager->setDefaultConnection('');
    }

    // ----------------------------------------------------------------
    // forgetConnection()
    // ----------------------------------------------------------------

    public function test_forget_connection_removes_specific_cached_connection(): void
    {
        $resolved = 0;
        $manager = new SSHManager(
            [
                'default' => 'main',
                'connections' => [
                    'main' => ['host' => '127.0.0.1', 'username' => 'test', 'auth' => 'password', 'password' => 'secret'],
                ],
            ],
            $this->stubResolver($resolved)
        );

        // Resolve and cache
        $first = $manager->connection('main');
        $this->assertSame(1, $resolved);

        // Forget the cached connection
        $manager->forgetConnection('main');

        // Re-resolving should create a new connection
        $second = $manager->connection('main');
        $this->assertNotSame($first, $second);
        $this->assertSame(2, $resolved);
    }

    public function test_forget_connection_with_null_clears_all_cached(): void
    {
        $resolved = 0;
        $manager = new SSHManager(
            [
                'default' => 'main',
                'connections' => [
                    'main' => ['host' => '127.0.0.1', 'username' => 'test', 'auth' => 'password', 'password' => 'secret'],
                    'alt' => ['host' => '127.0.0.2', 'username' => 'test2', 'auth' => 'password', 'password' => 'secret2'],
                ],
            ],
            $this->stubResolver($resolved)
        );

        // Resolve both
        $main1 = $manager->connection('main');
        $alt1 = $manager->connection('alt');
        $this->assertSame(2, $resolved);

        // Forget all
        $manager->forgetConnection();

        // Re-resolving should create new connections for both
        $main2 = $manager->connection('main');
        $alt2 = $manager->connection('alt');
        $this->assertNotSame($main1, $main2);
        $this->assertNotSame($alt1, $alt2);
        $this->assertSame(4, $resolved);
    }

    // ----------------------------------------------------------------
    // runtime()
    // ----------------------------------------------------------------

    public function test_runtime_returns_connection_builder(): void
    {
        $manager = new SSHManager();

        $this->assertInstanceOf(ConnectionBuilder::class, $manager->runtime());
    }

    // ----------------------------------------------------------------
    // hasProfile()
    // ----------------------------------------------------------------

    public function test_has_profile_returns_false_for_nonexistent(): void
    {
        $manager = new SSHManager();

        $this->assertFalse($manager->hasProfile('nonexistent'));
    }
}
