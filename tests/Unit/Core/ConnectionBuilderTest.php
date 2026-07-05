<?php

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use MonkeysLegion\SSH\Core\ConnectionBuilder;

class ConnectionBuilderTest extends TestCase
{
    public function test_builder_can_be_chained(): void
    {
        $builder = new ConnectionBuilder();
        $builder->to('localhost')
            ->port(22)
            ->as('user')
            ->withPassword('password');

        $this->assertTrue(true);
    }

    public function test_builder_requires_authentication(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $builder = new ConnectionBuilder();
        $builder->to('localhost')->connect();
    }

    public function test_builder_requires_username(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $builder = new ConnectionBuilder();
        $builder
            ->to('localhost')
            ->withPassword('password')
            ->connect();
    }

    public function test_from_profile_requires_host(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $builder = new ConnectionBuilder();
        $builder->fromProfile([
            'username' => 'user',
            'auth' => 'password',
            'password' => 'password',
        ]);
    }
}
