# Security Policy

## Supported Versions

We release patches for security vulnerabilities in the following versions:

| Version | Supported          |
| ------- | ------------------ |
| 0.x     | ✅ Active development |

## Reporting a Vulnerability

We take the security of **MonkeysLegion SSH** seriously. If you believe you have found a security vulnerability, please **do not** open a public issue.

Instead, report it privately via one of the following methods:

- **GitHub Security Advisory**: Navigate to the repository's **Security > Advisories** tab and submit a private advisory.
- **Email**: Send your report to **<security@monkeyscloud.com>** (or use the GitHub Security Advisories tab).

### What to include

When reporting a vulnerability, please include as much of the following as possible:

- Type of vulnerability (e.g., command injection, path traversal, RCE)
- Affected component(s) (SSHConnection, SFTPClient, ScpClient, etc.)
- Steps to reproduce the issue
- Proof of concept or exploit code (if available)
- Potential impact and attack surface

We will acknowledge receipt within **48 hours** and provide an initial assessment within **5 business days**. We will keep you informed throughout the fix and release process.

## Scope

The following are in scope for security reports:

- The core SSH library and all its components
- Authentication mechanisms (password, public key, agent)
- File transfer protocols (SFTP, SCP)
- Command execution and pipeline handling

The following are **out of scope**:

- The underlying `ext-ssh2` extension itself
- The PHP runtime or its bundled extensions
- Applications that consume this library
- Third-party dependencies listed in `require-dev`

## Security Best Practices When Using This Package

### Host Key Verification

Always verify SSH host keys in production environments to prevent man-in-the-middle attacks:

```php
$connection = SSH::runtime()
    ->to('192.168.1.10')
    ->as('deploy')
    ->withPassword('secret')
    ->withFingerprint('00:11:22:33:44:55:66:77:88:99:aa:bb:cc:dd:ee:ff:00:11:22:33')
    ->connect();
```

### Command Injection Prevention

The library uses `escapeshellarg()` on all command strings. However, avoid passing untrusted user input directly to `execute()` without validation:

```php
// Safe: escaped by the library
$result = SSH::execute('cat ' . escapeshellarg($userProvidedFilename));

// Dangerous: bypasses built-in shell wrapping
$result = SSH::execute('bash -c ' . escapeshellarg($userProvidedCommand));
```

### File Path Validation

SFTP and SCP operations accept arbitrary remote paths. In untrusted contexts, validate remote paths before passing them to transfer methods.

### Credential Management

Never hardcode SSH credentials in source code. Use environment variables or a secure configuration system:

```php
SSH::configure([
    'connections' => [
        'production' => [
            'host' => getenv('SSH_HOST'),
            'username' => getenv('SSH_USERNAME'),
            'password' => getenv('SSH_PASSWORD'),
        ],
    ],
]);
```

## Disclosure Policy

We follow a coordinated disclosure process:

1. **Report received** — acknowledged within 48 hours
2. **Investigation** — initial assessment within 5 business days
3. **Fix preparation** — patch developed and reviewed
4. **Release** — new version published with fix
5. **Public disclosure** — advisory published after release

We aim to complete this process within **14 days** for critical vulnerabilities.

## Recognition

We believe in crediting security researchers who help us improve our security. With your permission, we will acknowledge your contribution in our release notes and security advisories.

---

Thank you for helping keep **MonkeysLegion SSH** and its community safe.
