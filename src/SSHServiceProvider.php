<?php

namespace MonkeysLegion\SSH;

use MonkeysLegion\Contracts\ServiceProviderInterface;
use MonkeysLegion\SSH\Core\ConnectionBuilder;
use MonkeysLegion\SSH\Core\SSHManager;
use MonkeysLegion\SSH\Facades\SSH;
use Psr\Container\ContainerInterface;

class SSHServiceProvider implements ServiceProviderInterface
{
    public function getDefinitions(): array
    {
        return [
            SSHManager::class => static fn (): SSHManager => SSH::manager(),
            ConnectionBuilder::class => static fn (): ConnectionBuilder => new ConnectionBuilder(),
        ];
    }

    public function provides(): array
    {
        return [
            SSHManager::class,
            ConnectionBuilder::class,
        ];
    }

    public function context(): string
    {
        return 'all';
    }

    public function isDeferred(): bool
    {
        return false;
    }

    public function boot(ContainerInterface $container): void
    {
    }
}
