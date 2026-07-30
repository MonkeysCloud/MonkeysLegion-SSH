<?php

namespace Tests\Unit;

use MonkeysLegion\SSH\Core\ConnectionBuilder;
use MonkeysLegion\SSH\Core\SSHManager;
use MonkeysLegion\SSH\SSHServiceProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

class SSHServiceProviderTest extends TestCase
{
    public function test_get_definitions_returns_correct_service_ids(): void
    {
        $provider = new SSHServiceProvider();
        $definitions = $provider->getDefinitions();

        $this->assertArrayHasKey(SSHManager::class, $definitions);
        $this->assertArrayHasKey(ConnectionBuilder::class, $definitions);
    }

    public function test_get_definitions_ssh_manager_is_callable(): void
    {
        $provider = new SSHServiceProvider();
        $definitions = $provider->getDefinitions();

        $this->assertIsCallable($definitions[SSHManager::class]);
    }

    public function test_get_definitions_connection_builder_is_callable(): void
    {
        $provider = new SSHServiceProvider();
        $definitions = $provider->getDefinitions();

        $this->assertIsCallable($definitions[ConnectionBuilder::class]);
    }

    public function test_ssh_manager_definition_creates_instance(): void
    {
        $provider = new SSHServiceProvider();
        $definitions = $provider->getDefinitions();

        $factory = $definitions[SSHManager::class];
        if (\is_callable($factory)) {
            $instance = $factory();
            $this->assertInstanceOf(SSHManager::class, $instance);
        } else {
            $this->fail('SSHManager definition is not callable.');
        }
    }

    public function test_connection_builder_definition_creates_instance(): void
    {
        $provider = new SSHServiceProvider();
        $definitions = $provider->getDefinitions();

        $factory = $definitions[ConnectionBuilder::class];
        if (\is_callable($factory)) {
            $instance = $factory();
            $this->assertInstanceOf(ConnectionBuilder::class, $instance);
        } else {
            $this->fail('ConnectionBuilder definition is not callable.');
        }
    }

    public function test_provides_returns_class_name_strings(): void
    {
        $provider = new SSHServiceProvider();
        $services = $provider->provides();

        $this->assertContains(SSHManager::class, $services);
        $this->assertContains(ConnectionBuilder::class, $services);
        $this->assertCount(2, $services);
    }

    public function test_context_returns_all(): void
    {
        $provider = new SSHServiceProvider();

        $this->assertSame('all', $provider->context());
    }

    public function test_is_deferred_returns_false(): void
    {
        $provider = new SSHServiceProvider();

        $this->assertFalse($provider->isDeferred());
    }

    public function test_boot_does_not_throw(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $provider = new SSHServiceProvider();

        $provider->boot($container);
        $this->expectNotToPerformAssertions();
    }
}
