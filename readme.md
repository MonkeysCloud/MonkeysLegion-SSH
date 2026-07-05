# MonkeysLegion SSH

## Quick Runtime Usage

```php
use MonkeysLegion\SSH\Facades\SSH;

$result = SSH::runtime()
    ->to('127.0.0.1')
    ->port(22)
    ->as('forge')
    ->withPassword('secret')
    ->timeout(10)
    ->connect()
    ->execute('uname -a');
```

## Manager + Profile Registry

```php
use MonkeysLegion\SSH\Core\SSHManager;

$manager = new SSHManager([
    'default' => 'production',
    'connections' => [
        'production' => [
            'host' => '127.0.0.1',
            'port' => 22,
            'username' => 'forge',
            'auth' => 'password',
            'password' => 'secret',
            'timeout' => 10,
        ],
    ],
]);

// Explicit runtime registration
$manager->register('staging', [
    'host' => '10.0.0.10',
    'port' => 22,
    'username' => 'deploy',
    'auth' => 'key',
    'private_key' => '/home/user/.ssh/id_rsa',
    'passphrase' => null,
    'timeout' => 10,
]);

// Lazy connection creation + cache
$prod = $manager->connection('production');
$prodAgain = $manager->connection('production'); // returns cached instance
```

## Static Facade Gateway

```php
use MonkeysLegion\SSH\Facades\SSH;

SSH::configure(require __DIR__ . '/config/ssh.php');
SSH::register('hotfix', [
    'host' => '127.0.0.1',
    'port' => 22,
    'username' => 'ops',
    'auth' => 'password',
    'password' => 'ops-secret',
    'timeout' => 10,
]);
SSH::useDefaultConnection('hotfix');

// Static proxy to default connection
$isConnected = SSH::isConnected();
$result = SSH::execute('whoami');
```

## Phase 2 Deliverables Completed

1. **Fluent runtime builder** (`ConnectionBuilder`) supports direct chain usage and array-profile parsing through `fromProfile()`.
2. **Manager-backed profile system** (`SSHManager`) parses config arrays, supports explicit profile registration (`register`, `registerMany`), default profile selection, lazy connection resolution, and cache invalidation (`forgetConnection`).
3. **Global static gateway** (`SSH` facade) now supports configuration, profile registration, default connection switching, cache forgetting, runtime builder access, and static proxy forwarding (`__callStatic`) to default connection methods.

## Phase 3 Deliverables Completed

1. **SFTP subsystem abstraction** (`SFTPClient`) using native wrapper paths (`ssh2.sftp://`) for file operations plus structural changes (`mkdir`, `chmod`, `delete`).
2. **Transfer metrics** (`SFTPTransferMetrics`) with tracked upload/download byte totals and operation counts.
3. **Sequential pipelines** (`CommandPipeline` + `PipelineResult`) integrated into `SSHConnection::pipeline(...)` with configurable halt-on-failure behavior and state-aware closure steps.

## Phase 4 Deliverables Completed

1. **Exception QoL improvements** with dedicated factories:
   - `ConnectionRefusedException::forHost($host, $port)`
   - `AuthenticationFailedException::password($username)`
   - `AuthenticationFailedException::publicKey($username, $privateKeyPath)`
2. **Testing fake utilities** through `SSH::fake()` with runtime helpers:
   - `SSH::fakeCommand($command, $result)`
   - `SSH::fakeDefault($result)`
   - `SSH::assertExecuted($command)`
3. **Socket-free test execution path** via `FakeSSHConnection` and `SSHFakeRegistry`, enabling deterministic command stubbing without active SSH infrastructure.

## Testing and Validation

```bash
# Unit + feature tests (integration tests skipped unless enabled)
composer test

# Full integration flow
docker compose -f docker-compose.integration.yml up -d
RUN_INTEGRATION_TESTS=1 composer test
docker compose -f docker-compose.integration.yml down

# Static analysis (level 9)
composer phpstan
```
