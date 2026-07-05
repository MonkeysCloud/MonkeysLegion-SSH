# Roadmap: Native PHP SSH Utility Library (PHP 8.4+)

This document outlines the architectural scope, technical constraints, and implementation phases for this native PHP SSH utility wrapper. The primary goal of this library is to transform the verbose and often clunky native `ext-ssh2` functions into a fluent, developer-centric API (DX-focused) without introducing external heavy-userland protocol implementations.

---

## Technical Constraints & Requirements

- **PHP Version:** `^8.4` (utilizing modern features such as property hooks, asymmetric visibility, and enhanced typing).
- **Required Extensions:**
  - `ext-ssh2`: Native C-bindings for `libssh2` to handle protocol execution.
  - `ext-sockets`: For granular socket-level management, timeouts, and keep-alive handling.
- **Operating System Focus:** Tailored for Linux/Unix targets. Windows client runtimes are secondary priorities.

---

## Architecture Design

The library uses a **Manager-backed Instance Architecture** with an optional static access layer (Facade pattern) to provide both builder-style configuration management and dynamic, ad-hoc runtime builders.

```text
┌─────────────────────────┐
│  Static Facade (SSH)    │
└────────────┬────────────┘
             │
             ▼
┌─────────────────────────────────────┐
│   SSHManager                        │
│   (Registry & Cache)                │◄── Reads ── config/ssh.php
└────┬──────────────┬─────────────────┘
     │              │
     │ Creates      │
     ▼              ▼
┌──────────────────┐  ┌─────────────────────────┐
│ ConnectionBuilder│──▶│  SSHConnection          │
│ (Fluent API)     │   │  (Resource Wrapper)     │
└──────────────────┘   └─────────────────────────┘
```

### Configuration Schema (`config/ssh.php`)

```php
return [
    'default' => 'production',

    'connections' => [
        'production' => [
            'host' => '127.0.0.1',
            'port' => 22,
            'username' => 'forge',
            'auth' => 'key', // options: 'password', 'key', 'agent'
            'private_key' => '/home/user/.ssh/id_rsa',
            'passphrase' => null,
            'timeout' => 10,
        ],
    ],
];
```

---

## Scope Boundary Matrix

| Feature | In Scope | Out of Scope |
|---------|----------|--------------|
| Fluent Connection Building | ✓ Object-oriented auth handling (Key, Password, Agent) | |
| Interactive TTY / Shells | | ✓ Persistent interactive terminal loops (e.g., live top) |
| Command Execution Engine | ✓ Separation of STDOUT and STDERR streams with exit code capturing | |
| Custom Crypto Handshakes | | ✓ Userland cryptography implementations (handled entirely by libssh2) |
| SFTP Stream Wrapper | ✓ High-level abstractions for transfers, structural changes (mkdir, chmod) | |
| Broad Multi-OS Exploits | | ✓ Workarounds for structural flaws within Windows-based SSH hosts |
| Command Pipelines | ✓ Sequential command chains with configurable halt-on-failure behavior | |
| Daemonization | | ✓ Long-running background workers managing multiplexed connection pools |

---

## Development Phases

### Phase 1: Core Engine & Foundations (Current Phase)

- [ ] Design the underlying `SSHConnection` class to encapsulate the native libssh2 resource.
- [ ] Implement basic authentication drivers: `PasswordAuthentication` and `PublicKeyAuthentication`.
- [ ] Build the raw `StreamHandler` to extract blocks securely from SSH channels.
- [ ] Implement an explicit `CommandResult` value object containing string buffers for output, error, and exit_code.

### Phase 2: Fluent Interface & Construction

- [ ] Implement the `ConnectionBuilder` pattern for runtime configuration:

  ```php
  SSH::runtime()->to($host)->as($user)->withKey($path)->connect();
  ```

- [ ] Build the `SSHManager` to parse configuration state arrays, register explicit connection profiles, and cache instantiated resources lazily.
- [ ] Finalize the global SSH static proxy gateway.

### Phase 3: Advanced Stream Utilities (SFTP & Pipelines)

- [ ] Create an explicit SFTP subsystem abstraction mapping to native stream protocols (`ssh2.sftp://`).
- [ ] Provide high-level file tracking metrics (upload, download sizes).
- [ ] Implement sequential pipelines (`$connection->pipeline(fn($pipe) => ...)`), allowing state propagation or execution halts upon non-zero exit codes.

### Phase 4: Developer Quality of Life (QoL)

- [ ] Introduce custom exception structures handling native connection failures gracefully (e.g., `ConnectionRefusedException`, `AuthenticationFailedException`).
- [ ] Add automated mocking/faking utilities for unit testing without hitting active socket infrastructure (`SSH::fake()`).
