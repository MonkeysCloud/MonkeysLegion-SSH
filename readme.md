# MonkeysLegion SSH

A fluent, developer-friendly PHP library for SSH operations. Wraps the native `ext-ssh2` extension with a modern API, full type safety (PHPStan level 9), and comprehensive testing utilities.

## Installation

```bash
composer require monkeyscloud/monkeyslegion-ssh
```

## Quick Start

### One-off Command Execution

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

echo $result->output;  // Command output
echo $result->exitCode; // Exit code (0 = success)
```

### Using a Connection Manager

For multi-server workflows, define connection profiles once and reuse them:

```php
use MonkeysLegion\SSH\Facades\SSH;

// Define your servers
SSH::configure([
    'default' => 'production',
    'connections' => [
        'production' => [
            'host' => '192.168.1.10',
            'port' => 22,
            'username' => 'deploy',
            'auth' => 'password',
            'password' => 'secure-password',
            'timeout' => 30,
        ],
        'staging' => [
            'host' => '192.168.1.20',
            'port' => 22,
            'username' => 'deploy',
            'auth' => 'key',
            'private_key' => '/home/user/.ssh/id_rsa',
            'passphrase' => 'key-passphrase', // optional
            'timeout' => 30,
        ],
    ],
]);

// Use the default connection
$result = SSH::execute('ls -la /var/www');

// Switch to staging
SSH::useDefaultConnection('staging');
$result = SSH::execute('ls -la /var/www');

// Or run commands on a specific connection
$result = SSH::connection('production')->execute('whoami');
```

### Register Connections Dynamically

```php
SSH::register('hotfix-server', [
    'host' => '192.168.1.30',
    'username' => 'ops',
    'auth' => 'password',
    'password' => 'ops-password',
    'timeout' => 15,
]);

$result = SSH::connection('hotfix-server')->execute('systemctl status app');
```

## API Reference

### Connection Methods

All methods below operate on a connection obtained via `SSH::connection()` or `SSH::runtime()`.

#### `connect() → void`

**Establishes the SSH connection.** This must be called before executing commands.

- ✅ **Establishes connection:** Yes
- ❌ **Closes connection on return:** No — the connection remains open for subsequent operations
- **When to use:** Explicitly called at the end of a fluent builder chain

```php
$connection = SSH::runtime()
    ->to('192.168.1.10')
    ->as('deploy')
    ->withPassword('secret')
    ->connect(); // Connection is now open
```

#### `execute(string $command) → CommandResult`

**Runs a single command on the open connection.**

- ❌ **Establishes connection:** No — requires an already-open connection
- ❌ **Closes connection on return:** No — connection stays open for more commands
- **Returns:** `CommandResult` with `output`, `error`, and `exitCode` properties

```php
$result = SSH::execute('whoami');
echo $result->output;   // 'deploy'
echo $result->exitCode; // 0
echo $result->error;    // '' (empty if no error)
```

#### `isConnected() → bool`

**Checks if the connection is currently open.**

- ❌ **Establishes connection:** No
- ❌ **Closes connection on return:** No
- **Returns:** `true` if connected, `false` otherwise

```php
if (SSH::isConnected()) {
    echo "Connected!";
}
```

#### `disconnect() → void`

**Closes the SSH connection and releases resources.**

- ❌ **Establishes connection:** No
- ✅ **Closes connection on return:** Yes — connection is terminated
- **When to use:** Clean up after you're done with the connection

```php
SSH::execute('ls');
SSH::disconnect(); // Connection closed
```

#### `pipeline(...$steps) → PipelineResult`

**Executes multiple commands in sequence with state sharing.** If one command fails (non-zero exit code), the pipeline can stop immediately or continue based on configuration.

- ❌ **Establishes connection:** No — requires an already-open connection
- ❌ **Closes connection on return:** No — connection stays open
- **Returns:** `PipelineResult` with execution history and metrics

```php
$result = SSH::pipeline(
    'cd /var/www',
    'git pull',
    'composer install',
    'php artisan migrate'
);

if ($result->halted) {
    echo "Pipeline stopped at: " . $result->failedAt;
}

foreach ($result->results as $cmd => $output) {
    echo "$cmd: $output\n";
}
```

#### `sftp() → SFTPClient`

**Opens an SFTP channel for file operations (upload, download, mkdir, chmod, delete).** Returns an `SFTPClient` instance with transfer metrics.

- ❌ **Establishes connection:** No — reuses the existing SSH connection
- ❌ **Closes connection on return:** No — SFTP session stays open
- **Returns:** `SFTPClient` with transfer and directory methods

```php
$sftp = SSH::sftp();

// Upload a file
$sftp->upload('/local/file.txt', '/remote/file.txt');

// Download a file
$sftp->download('/remote/backup.sql', '/local/backup.sql');

// Create directories
$sftp->mkdir('/var/www/uploads');

// Set permissions (octal)
$sftp->chmod('/var/www/uploads', 0755);

// Delete a file
$sftp->delete('/var/www/temp.log');

// Access metrics
echo $sftp->metrics->uploadedBytes;
echo $sftp->metrics->downloadedBytes;
echo $sftp->metrics->operationCount;
```

### Facade Methods

Static methods on the `SSH` class for global configuration and connection management.

#### `SSH::configure(array $config) → void`

**Sets up the connection manager with profiles.** Wipes any existing connections.

- **Config keys:**
  - `default` (string): Which profile to use by default
  - `connections` (array): Profile name → config array mapping

```php
SSH::configure([
    'default' => 'prod',
    'connections' => [
        'prod' => ['host' => '192.168.1.10', 'username' => 'deploy', ...],
    ],
]);
```

#### `SSH::register(string $name, array $profile) → void`

**Registers a new connection profile without resetting existing ones.**

```php
SSH::register('backup-server', [
    'host' => '192.168.1.50',
    'username' => 'backup',
    'auth' => 'key',
    'private_key' => '/home/user/.ssh/backup_key',
]);
```

#### `SSH::useDefaultConnection(string $name) → void`

**Switches the default connection.** Affects all static `SSH::execute()` calls.

```php
SSH::useDefaultConnection('staging');
SSH::execute('whoami'); // Runs on staging
```

#### `SSH::connection(?string $name = null) → SSHConnection`

**Retrieves a connection by name.** If `$name` is null, returns the default connection.

```php
$prod = SSH::connection('production');
$result = $prod->execute('whoami');
```

#### `SSH::forgetConnection(?string $name = null) → void`

**Closes and removes a cached connection.** If `$name` is null, clears all cached connections.

```php
SSH::forgetConnection('staging'); // Closes staging
SSH::forgetConnection();            // Closes all
```

#### `SSH::runtime() → ConnectionBuilder`

**Returns a fluent builder for one-off connections without profiles.**

```php
$result = SSH::runtime()
    ->to('example.com')
    ->as('user')
    ->withPassword('pass')
    ->connect()
    ->execute('whoami');
```

### Testing Utilities

The library provides socket-free fakes for deterministic testing.

#### `SSH::fake(array $responses = []) → SSHFakeRegistry`

**Enables fake mode.** Subsequent commands don't touch the network; responses are stubbed.

```php
SSH::fake([
    'whoami' => new CommandResult('forge', '', 0),
    'pwd' => new CommandResult('/home/forge', '', 0),
]);

$result = SSH::execute('whoami');
assert($result->output === 'forge');
```

#### `SSH::fakeCommand(string $command, CommandResult $result) → void`

**Adds or overrides a command stub while in fake mode.**

```php
SSH::fake();
SSH::fakeCommand('git status', new CommandResult('On branch main', '', 0));
```

#### `SSH::fakeDefault(CommandResult $result) → void`

**Sets a fallback response for unmapped commands in fake mode.**

```php
SSH::fake();
SSH::fakeDefault(new CommandResult('', 'command not found', 127));

$unknownCmd = SSH::execute('unknown-cmd');
assert($unknownCmd->exitCode === 127);
```

#### `SSH::assertExecuted(string $command) → void`

**Verifies a command was executed while in fake mode.** Throws if not found.

```php
SSH::fake();
SSH::fakeCommand('backup', new CommandResult('OK', '', 0));
SSH::execute('backup');

SSH::assertExecuted('backup'); // Passes
SSH::assertExecuted('other');  // Throws RuntimeException
```

### Exception Factories

Quick constructors for common SSH errors.

```php
use MonkeysLegion\SSH\Exceptions\ConnectionRefusedException;
use MonkeysLegion\SSH\Exceptions\AuthenticationFailedException;

// Connection refused
throw ConnectionRefusedException::forHost('192.168.1.10', 22);

// Auth failures
throw AuthenticationFailedException::password('deploy');
throw AuthenticationFailedException::publicKey('deploy', '/home/.ssh/key');
```

## Configuration

Create a config file at `config/ssh.php`:

```php
return [
    'default' => 'production',
    'connections' => [
        'production' => [
            'host' => env('SSH_HOST', '127.0.0.1'),
            'port' => env('SSH_PORT', 22),
            'username' => env('SSH_USERNAME', 'forge'),
            'auth' => env('SSH_AUTH', 'password'), // 'password' or 'key'
            'password' => env('SSH_PASSWORD', ''),
            'private_key' => env('SSH_PRIVATE_KEY', ''),
            'passphrase' => env('SSH_PASSPHRASE', ''),
            'timeout' => env('SSH_TIMEOUT', 30),
        ],
    ],
];
```

Then load it:

```php
SSH::configure(require __DIR__ . '/config/ssh.php');
```

## Testing

See [TESTING.md](TESTING.md) for comprehensive test documentation, including Docker-backed integration testing, PHPStan level 9 analysis, and fake mode usage.
