# Contributing to MonkeysLegion SSH

First off, thank you for considering contributing! 🎉

This is a community-driven project and we welcome all forms of contributions — whether it's a new feature, a bug fix, documentation improvement, or a feature request.

## 📋 Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Development Environment](#development-environment)
- [PHP Version & Standards](#php-version--standards)
- [Architecture Overview](#architecture-overview)
- [Testing](#testing)
- [Static Analysis](#static-analysis)
- [Code Style](#code-style)
- [Pull Request Process](#pull-request-process)
- [Questions?](#questions)

---

## Code of Conduct

This project and everyone participating in it is governed by the [Code of Conduct](CODE_OF_CONDUCT.md). By participating, you are expected to uphold this code. Please report unacceptable behavior by opening a [GitHub Issue](https://github.com/monkeyscloud/monkeyslegion-ssh/issues).

---

## Getting Started

1. **Fork** the repository on GitHub.
2. **Clone** your fork locally:

   ```bash
   git clone https://github.com/your-username/monkeyslegion-ssh.git
   cd monkeyslegion-ssh
   ```

3. **Install dependencies**:

   ```bash
   composer install
   ```

4. **Create a branch** for your changes:

   ```bash
   git checkout -b feature/my-feature
   ```

---

## Development Environment

- **PHP 8.4+** is required — the package uses PHP 8.4 features (property hooks, `#[Override]`, `readonly` classes, etc.)
- **`ext-ssh2`** is required for real SSH connections (unit tests use fakes)
- **Composer 2.x** is required
- **Docker** is required for integration tests

### Quick validation

Run all quality checks with a single command:

```bash
composer check
```

This runs:

1. `composer cs-check` — PSR-12 code style
2. `composer phpstan` — PHPStan Level 9 static analysis
3. `composer infection` — Mutation testing (Infection, 79% MSI threshold)
4. `composer test` — PHPUnit test suite

---

## PHP Version & Standards

| Requirement | Standard |
|-------------|----------|
| **PHP Version** | 8.4+ only |
| **Code Style** | [PSR-12](https://www.php-fig.org/psr/psr-12/) |
| **Autoloading** | [PSR-4](https://www.php-fig.org/psr/psr-4/) |
| **Static Analysis** | PHPStan Level 9 |
| **Mutation Testing** | Infection, 79% min MSI threshold |
| **Testing** | PHPUnit 11.x |
| **Type System** | Strict types everywhere, native PHP 8.4 features preferred |

### PHP 8.4 Features We Embrace

- `readonly` classes for immutable value objects
- Property hooks (`get`, `set`) for computed properties
- `#[Override]` attribute for interface implementations
- Named arguments for methods with 3+ parameters
- `match` expressions over `switch` statements

---

## Architecture Overview

```
SSH::fake()          SSH Facade            ConnectionBuilder
   │                     │                       │
   └────────────┬────────┘───────────┬───────────┘
                │                    │
          SSHManager           SSHConnection
         (Profiles)        (exec / channel / shell
                           tunnel / sftp / scp /
                           pipeline / proxyTo)
                              │
            ┌────────┬────────┼────────┬────────┐
            │        │        │        │        │
        SFTPClient  SCP  CommandChannel  Shell  Tunnel
                   Client              Session
```

The library wraps `ext-ssh2` native functions behind injectable callbacks, enabling unit testing without real SSH connections. Every native function (connect, exec, sftp, scp, shell, tunnel) has a corresponding injectable closure.

---

## Testing

- **All tests must pass** before a PR is merged
- Run tests with: `composer test`
- **Unit tests** use socket-free fakes — no SSH server required
- **Integration tests** require Docker — see [TESTING.md](TESTING.md) for setup
- **Edge cases are highly valued**: empty inputs, non-existent paths, permission errors, path traversal attempts, large outputs

### Test conventions

- Test files go in `tests/Unit/` (unit) or `tests/Feature/` (integration)
- Namespace: `Tests\Unit\*` or `Tests\Feature\*`
- Filename: `*Test.php`
- Mock native SSH2 functions via injectable callbacks rather than function mocks

---

## Static Analysis

We enforce **PHPStan Level 9** — no exceptions. Run before submitting:

```bash
composer phpstan
```

This ensures:

- Strict return type declarations
- Proper handling of nullable values
- No unused or uninitialized properties
- Template/generic array shape verification

---

## Code Style

We follow **PSR-12** with the following additional conventions:

- **Strict types**: No `declare(strict_types=1)` — enforced by project conventions
- **No `echo` or `var_dump`** in library code
- **Named arguments** preferred for clarity in method calls with 3+ parameters
- **`match` expressions** preferred over `switch` statements

Auto-fix code style with:

```bash
composer cs-fix
```

---

## Pull Request Process

1. **Before submitting**, run `composer check` and ensure everything passes.
2. **Keep PRs focused** — one feature or fix per PR. Large PRs are harder to review.
3. **Update documentation** — if you change behavior, update the README and relevant docblocks.
4. **Add tests** — new features require tests. Bug fixes require a regression test.
5. **Update ROADMAP.md** — if your PR completes a roadmap item, mark it accordingly.
6. **Describe your changes** — provide a clear summary and motivation in the PR description.

### PR checklist

- [ ] I have run `composer check` and all checks pass
- [ ] I have run `composer infection` and not lowered the MSI
- [ ] I have added/updated tests to cover my changes
- [ ] I have updated documentation (README, docblocks) as needed
- [ ] My code follows PSR-12 and project conventions

---

## Questions?

- Open a [Discussion](https://github.com/monkeyscloud/monkeyslegion-ssh/discussions) for questions
- Open an [Issue](https://github.com/monkeyscloud/monkeyslegion-ssh/issues) for bug reports or feature requests
- Check the [ROADMAP](ROADMAP.md) for planned features

Thank you for contributing! 🚀
