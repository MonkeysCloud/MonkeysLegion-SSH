# MonkeysLegion SSH

**Native PHP 8.4+ SSH library** with a fluent, type-safe API wrapping `ext-ssh2`. Built for modern server management, deployment pipelines, and secure remote operations.

[![PHP Version](https://img.shields.io/badge/PHP-8.4%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/releases/8.4/)
[![Latest Stable Version](https://img.shields.io/packagist/v/monkeyscloud/monkeyslegion-ssh?logo=packagist&logoColor=white)](https://packagist.org/packages/monkeyscloud/monkeyslegion-ssh)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
[![CS: PSR-12](https://img.shields.io/badge/Code%20Style-PSR--12-ff69b4)](https://www.php-fig.org/psr/psr-12/)
[![PHPStan Level 9](https://img.shields.io/badge/PHPStan-Level%209-brightgreen)](https://phpstan.org/)
[![Infection MSI](https://img.shields.io/badge/Infection-MSI%2079%25-purple)](https://infection.github.io/)
[![CI](https://github.com/monkeyscloud/monkeyslegion-ssh/actions/workflows/ci.yml/badge.svg)](https://github.com/monkeyscloud/monkeyslegion-ssh/actions/workflows/ci.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/monkeyscloud/monkeyslegion-ssh)](https://packagist.org/packages/monkeyscloud/monkeyslegion-ssh)

---

## ✨ Features

- **🚀 Fluent API** — builder pattern for one-off connections and reusable profiles
- **🔐 3 auth methods** — password, public key (with passphrase), and SSH agent
- **📂 SFTP file operations** — upload, download, mkdir, chmod, delete, rename, symlinks, directory listing
- **📦 SCP support** — send/receive files with configurable permissions
- **⛓️ Command pipelines** — sequential execution with state sharing and halt-on-failure
- **💓 Connection keepalive** — automatic heartbeat to prevent stale connections
- **🖥️ Interactive PTY shell** — `ssh2_shell` with read/write/resize for interactive sessions
- **🔀 Channel multiplexing** — multiple concurrent exec channels on a single connection
- **⏱️ Per-channel command timeouts** — override timeout per `execute()` call
- **🔌 TCP tunnel** — direct TCP forwarding through SSH via `ssh2_tunnel`
- **🪜 Bastion proxy** — jump host support via `proxyTo()` for multi-hop connections
- **🪞 Fake/mock mode** — socket-free testing without real SSH servers
- **🔍 Host key verification** — SHA1 fingerprint matching against expected values
- **📊 Transfer metrics** — track uploaded/downloaded bytes and operation counts
- **🧪 PHPStan Level 9** — maximum static analysis rigor
- **🧬 Mutation testing (Infection)** — 79% covered MSI threshold
- **🆕 PHP 8.4 native** — property hooks, `#[Override]`, `readonly` classes, and more

---

## 📦 Installation

```bash
composer require monkeyscloud/monkeyslegion-ssh
```

> **Requires PHP 8.4+** and the [`ext-ssh2`](https://www.php.net/manual/en/book.ssh2.php) extension.

---

## 🚀 Quick Start

### One-off Command

```php
use MonkeysLegion\SSH\Facades\SSH;

$result = SSH::runtime()
    ->to('192.168.1.10')
    ->port(22)
    ->as('deploy')
    ->withPassword('secret')
    ->timeout(10)
    ->connect()
    ->execute('uname -a');

echo $result->output;   // Linux hostname 6.8.0 ...
echo $result->exitCode; // 0
```

### Connection Manager (Multi-Server)

```php
SSH::configure([
    'default' => 'production',
    'connections' => [
        'production' => [
            'host' => '192.168.1.10',
            'username' => 'deploy',
            'auth' => 'password',
            'password' => 'secure-password',
            'timeout' => 30,
        ],
        'staging' => [
            'host' => '192.168.1.20',
            'username' => 'deploy',
            'auth' => 'key',
            'private_key' => '/home/user/.ssh/id_rsa',
            'timeout' => 30,
        ],
    ],
]);

$result = SSH::execute('ls -la /var/www');
SSH::useDefaultConnection('staging');
$result = SSH::connection('production')->execute('whoami');
```

### SFTP File Transfer

```php
$sftp = SSH::sftp();
$sftp->upload('/local/file.txt', '/remote/file.txt');
$sftp->download('/remote/backup.sql', '/local/backup.sql');
$sftp->mkdir('/var/www/uploads', 0755, recursive: true);
$sftp->ls('/var/www');       // list files
$sftp->stat('/remote/file.txt');  // size, mode, timestamps
$sftp->rename('/old.txt', '/new.txt');
$sftp->symlink('/target', '/link');
```

### SCP Transfer

```php
$scp = SSH::scp();
$scp->send('/local/file.txt', '/remote/file.txt');
$scp->send('/local/script.sh', '/remote/script.sh', 0755);
$scp->receive('/remote/source.dat', '/local/source.dat');
```

---

## 🏗️ Architecture

```
┌──────────────┐     ┌────────────────┐     ┌─────────────────┐
│  SSH::fake() │     │  SSH Facade    │     │  ConnectionBuilder │
│  (Testing)   │     │  (Entry Point) │────▶│  (Fluent Builder)  │
└──────────────┘     └───────┬────────┘     └────────┬────────┘
                             │                        │
                    ┌────────▼────────┐               │
                    │  SSHManager     │               │
                    │  (Profiles)     │               │
                    └────────┬────────┘               │
                             │                        │
                    ┌────────▼────────────────────────▼──────────┐
                    │           SSHConnection                     │
                    │  connect / execute / channel / shell        │
                    │  pipeline / sftp / scp                      │
                    └────┬─────┬──────┬──────┬──────┬──────────┘
                         │     │      │      │      │
                    ┌────▼──┐ │ ┌────▼──┐ ┌─▼───┐  │
                    │ SFTP  │ │ │  SCP  │ │Tunnel│  │
                    │Client │ │ │Client │ │      │  │
                    └───────┘ │ └───────┘ └─────┘  │
                         ┌────▼──────┐   ┌────▼───┐ │
                         │  Shell    │   │Command │ │
                         │ Session   │   │Channel │ │
                         └───────────┘   └───┬────┘ │
                                         ┌───▼──┐   │
                                         │Command│   │
                                         │Pipeline│  │
                                         └───────┘   │
                                                 ┌────▼───────┐
                                                 │StreamHandler│
                                                 └────────────┘
```

### Core Components

| Component | Description |
|-----------|-------------|
| `SSH` (Facade) | Static entry point for all operations |
| `SSHManager` | Connection profile registry with lazy caching |
| `SSHConnection` | Authenticated SSH session — execute, pipeline, SFTP, SCP |
| `ConnectionBuilder` | Fluent builder for ad-hoc connections |
| `SFTPClient` | File operations over SFTP (upload, download, ls, mkdir, etc.) |
| `ScpClient` | File transfers via SCP protocol |
| `CommandPipeline` | Sequential command execution with shared state |
| `CommandResult` | Value object with output, error, and exit code |
| `CommandChannel` | Multiplexed exec channel with read/readError/getExitStatus |
| `ShellSession` | Interactive PTY shell with bidirectional read/write/resize |
| `Tunnel` | Direct TCP tunnel stream through SSH with read/write/close |
| `StreamHandler` | Low-level SSH stream read/write abstraction with timeout support |
| `SSHFakeRegistry` | Command stub registry for deterministic testing |
| `SSHServiceProvider` | Service provider for MonkeysLegion DI container integration |

---

## 📋 API Reference

### Connection Methods

#### `connect() → void`

Establishes the SSH connection.

```php
$connection = SSH::runtime()
    ->to('192.168.1.10')
    ->as('deploy')
    ->withPassword('secret')
    ->connect();
```

#### `execute(string $command) → CommandResult`

Runs a single command on the open connection.

```php
$result = SSH::execute('whoami');
echo $result->output;   // 'deploy'
echo $result->exitCode; // 0
echo $result->error;    // ''
```

#### `isConnected() → bool`

Checks if the connection is open.

```php
if (SSH::isConnected()) {
    echo "Connected!";
}
```

#### `disconnect() → void`

Closes the connection and releases resources.

```php
SSH::execute('ls');
SSH::disconnect();
```

#### `channel(string $command) → CommandChannel`

Opens a multiplexed exec channel without immediately reading. Multiple channels can be opened concurrently on one connection.

```php
$channel1 = $connection->channel('long-running-build');
$channel2 = $connection->channel('quick-check');

$output1 = $channel1->read(timeout: 120);
$output2 = $channel2->read(timeout: 10);

$channel1->close();
$channel2->close();
```

#### `shell(?string $termType, int $width, int $height) → ShellSession`

Opens an interactive PTY shell session for bidirectional read/write with terminal resize support.

```php
$shell = $connection->shell('xterm-256color', 120, 40);
$shell->write("cd /var/www && ls -la\n");
$output = $shell->readAll();
$shell->close();
```

#### `tunnel(string $host, int $port) → Tunnel`

Opens a direct TCP tunnel through the SSH connection to a remote host:port. Returns a `Tunnel` stream for bidirectional data transfer.

```php
$tunnel = $connection->tunnel('internal-db.local', 3306);
$tunnel->write("SELECT 1\n");
$response = $tunnel->read(4096);
$tunnel->close();
```

#### `proxyTo(string $host, string $user, int $port = 22) → SSHConnection`

Returns a proxied `SSHConnection` that routes all commands through the current connection as a bastion/jump host. The returned connection reuses the bastion's session and executes commands via `ssh <user>@<host> '<command>'`.

```php
$bastion = SSH::runtime()
    ->to('bastion.example.com')
    ->as('jump-user')
    ->withPassword('secret')
    ->connect();

$target = $bastion->proxyTo('internal-host.local', 'deploy', 2222);
$result = $target->execute('whoami'); // runs via bastion
$target->disconnect();                // does NOT close the bastion
$bastion->disconnect();
```

#### `pipeline(callable $configure, bool $haltOnFailure = true) → PipelineResult`

Executes multiple commands in sequence with state sharing. Each step receives the previous `CommandResult` and a shared state array.

```php
$result = SSH::pipeline(function (CommandPipeline $pipeline) {
    $pipeline
        ->add('cd /var/www')
        ->add('git pull')
        ->withState('branch', 'main')
        ->add(function (?CommandResult $prev, array &$state) {
            return 'git checkout ' . $state['branch'];
        })
        ->add('composer install');
}, haltOnFailure: true);

if ($result->halted) {
    echo "Pipeline stopped at step: " . $result->failedStep;
}
```

#### `sftp() → SFTPClient`

Opens an SFTP channel for file operations.

```php
$sftp = SSH::sftp();

// Transfer
$sftp->upload('/local/file.txt', '/remote/file.txt');
$sftp->download('/remote/backup.sql', '/local/backup.sql');

// Directories
$sftp->mkdir('/var/www/uploads', 0755, recursive: true);
$sftp->ls('/var/www');
$sftp->nlist('/var/www');
$sftp->rawlist('/var/www');

// Metadata
$sftp->stat('/remote/file.txt');
$sftp->fileExists('/remote/file.txt');
$sftp->chmod('/remote/file.txt', 0644);

// Modify
$sftp->rename('/old.txt', '/new.txt');
$sftp->delete('/remote/temp.log');

// Symlinks
$sftp->symlink('/target/path', '/link/path');
$sftp->readlink('/link/path');

// Metrics
echo $sftp->metrics()->uploadedBytes();
echo $sftp->metrics()->downloadedBytes();
echo $sftp->metrics()->uploadCount();
echo $sftp->metrics()->downloadCount();
```

#### `scp() → ScpClient`

Opens an SCP channel for file transfers.

```php
$scp = SSH::scp();
$scp->send('/local/file.txt', '/remote/file.txt', 0644);
$scp->receive('/remote/source.dat', '/local/source.dat');
```

### Facade Methods

#### `SSH::configure(array $config) → void`

Sets up the connection manager with profiles. Wipes any existing connections.

| Key | Type | Description |
|-----|------|-------------|
| `default` | `string` | Profile name to use by default |
| `connections` | `array` | Profile name → config array mapping |

```php
SSH::configure([
    'default' => 'prod',
    'connections' => [
        'prod' => ['host' => '192.168.1.10', 'username' => 'deploy', ...],
    ],
]);
```

#### `SSH::register(string $name, array $profile) → void`

Registers a connection profile without resetting existing ones.

```php
SSH::register('backup-server', [
    'host' => '192.168.1.50',
    'username' => 'backup',
    'auth' => 'key',
    'private_key' => '/home/user/.ssh/backup_key',
]);
```

#### `SSH::useDefaultConnection(string $name) → void`

Switches the default connection used by static `SSH::execute()` calls.

```php
SSH::useDefaultConnection('staging');
SSH::execute('whoami');
```

#### `SSH::connection(?string $name = null) → SSHConnection`

Retrieves a connection by name. Returns the default connection if `$name` is null.

```php
$prod = SSH::connection('production');
$result = $prod->execute('whoami');
```

#### `SSH::forgetConnection(?string $name = null) → void`

Closes and removes a cached connection. Clears all if `$name` is null.

```php
SSH::forgetConnection('staging');
SSH::forgetConnection(); // closes all
```

#### `SSH::runtime() → ConnectionBuilder`

Returns a fluent builder for one-off connections without profiles.

```php
$result = SSH::runtime()
    ->to('example.com')
    ->as('user')
    ->withPassword('pass')
    ->connect()
    ->execute('whoami');
```

### Testing Utilities

The library provides socket-free fakes for deterministic testing without real SSH servers.

#### `SSH::fake(array $responses = []) → SSHFakeRegistry`

Enables fake mode — commands return stubbed responses without network access.

```php
SSH::fake([
    'whoami' => new CommandResult('forge', '', 0),
    'pwd' => new CommandResult('/home/forge', '', 0),
]);

$result = SSH::execute('whoami');
assert($result->output === 'forge');
```

#### `SSH::fakeCommand(string $command, CommandResult $result) → void`

Adds or overrides a command stub while in fake mode.

```php
SSH::fake();
SSH::fakeCommand('git status', new CommandResult('On branch main', '', 0));
```

#### `SSH::fakeDefault(CommandResult $result) → void`

Sets a fallback response for unmapped commands.

```php
SSH::fake();
SSH::fakeDefault(new CommandResult('', 'command not found', 127));
```

#### `SSH::assertExecuted(string $command) → void`

Verifies a command was executed. Throws if not found.

```php
SSH::fake();
SSH::fakeCommand('backup', new CommandResult('OK', '', 0));
SSH::execute('backup');
SSH::assertExecuted('backup'); // passes
```

### Exception Factories

```php
use MonkeysLegion\SSH\Exceptions\ConnectionRefusedException;
use MonkeysLegion\SSH\Exceptions\AuthenticationFailedException;

throw ConnectionRefusedException::forHost('192.168.1.10', 22);
throw AuthenticationFailedException::password('deploy');
throw AuthenticationFailedException::publicKey('deploy', '/home/.ssh/key');
throw AuthenticationFailedException::agent('deploy');
```

---

## 📄 Configuration

Create `config/ssh.php`:

```php
return [
    'default' => 'production',
    'connections' => [
        'production' => [
            'host' => env('SSH_HOST', '127.0.0.1'),
            'port' => env('SSH_PORT', 22),
            'username' => env('SSH_USERNAME', 'forge'),
            'auth' => env('SSH_AUTH', 'password'),
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

---

## 🧑‍💻 Development

```bash
git clone https://github.com/monkeyscloud/monkeyslegion-ssh.git
cd monkeyslegion-ssh
composer install

# Run all quality checks
composer check

# Or individual checks
composer test               # PHPUnit (292+ tests)
composer phpstan            # PHPStan Level 9
composer cs-check           # PSR-12 code style
composer infection          # Mutation testing (Infection, 79% MSI threshold)
```

### Quality Gates

| Gate | Requirement |
|------|-------------|
| **PHPStan** | Level 9, zero errors |
| **PHP-CS-Fixer** | PSR-12, zero errors |
| **Infection (MSI)** | 79% min (some default-value mutants unavoidably escape) |
| **Test coverage** | All unit + integration tests pass |
| **Integration tests** | Docker-backed SSH server |

### Running Integration Tests

Integration tests require Docker. Start the SSH server and run:

```bash
docker compose -f docker-compose.integration.yml up -d
RUN_INTEGRATION_TESTS=1 composer test
```

---

## 🤝 Contributing

We welcome contributions! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for detailed guidelines.

### Quick summary

- **PHP 8.4+ only** — embrace modern features
- **PSR-12** coding style + **PHPStan Level 9** static analysis
- **Tests required** — unit tests for every new method, edge cases, and error paths
- **One feature per PR** — keep changes focused

---

## 📄 License

This project is licensed under the MIT License — see [LICENSE](LICENSE) for details.

---

## 🔒 Security

Found a vulnerability? Please see [SECURITY.md](SECURITY.md) for our disclosure process. **Do not** open public issues for security reports.

---

## 🛣️ Roadmap

See [ROADMAP.md](ROADMAP.md) for planned features and milestones.

---

## 📚 Further Reading

- [Testing Guide](TESTING.md) — comprehensive test documentation
- [Contributing Guidelines](CONTRIBUTING.md) — how to contribute
- [Code of Conduct](CODE_OF_CONDUCT.md) — community standards

---

*Built for modern PHP server management and deployment workflows.*
