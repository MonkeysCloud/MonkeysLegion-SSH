<?php

namespace Tests\Unit\Core;

use MonkeysLegion\SSH\Core\SSHManager;
use MonkeysLegion\SSH\Core\SSHConnection;
use PHPUnit\Framework\TestCase;

class SSHManagerTest extends TestCase
{
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
            static function () use (&$resolved): SSHConnection {
                $resolved++;
                /** @var SSHConnection $connection */
                $connection = (new \ReflectionClass(SSHConnection::class))->newInstanceWithoutConstructor();
                return $connection;
            }
        );

        $first = $manager->connection();
        $second = $manager->connection('main');

        $this->assertSame($first, $second);
        $this->assertSame(1, $resolved);
    }

    public function test_register_adds_connection_profile(): void
    {
        $manager = new SSHManager([], static function (): SSHConnection {
            /** @var SSHConnection $connection */
            $connection = (new \ReflectionClass(SSHConnection::class))->newInstanceWithoutConstructor();
            return $connection;
        });

        $manager->register('runtime', [
            'host' => '127.0.0.1',
            'username' => 'runtime',
            'auth' => 'password',
            'password' => 'runtime-pass',
        ]);

        $this->assertTrue($manager->hasProfile('runtime'));
    }

    public function test_connection_throws_for_unknown_profile(): void
    {
        $manager = new SSHManager();

        $this->expectException(\InvalidArgumentException::class);
        $manager->connection('unknown');
    }
}
