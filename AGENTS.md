# MonkeysLegion SSH — Agent Instructions

## Project
Native PHP 8.4+ SSH library wrapping `ext-ssh2`. Namespace: `MonkeysLegion\SSH\`.

## Must Follow

### Code Style
- **PSR-12** enforced by `@PHP84Migration` ruleset
- **No comments** — zero. Not docblocks, not inline. The code is the documentation
- **Strict types**: no `declare(strict_types=1)` (project convention)
- **Native function calls** prefixed with `\` (e.g. `\strlen`, `\fopen`)
- **Single quotes** for strings, trailing commas in multiline arrays/arguments/parameters
- **Ordered imports**, no unused imports, no empty phpdoc
- **Return types** on all methods, `void` return on mutators

### PHP 8.4
- `readonly` classes for immutable value objects
- Property hooks (`get`, `set`) where appropriate
- `#[Override]` attribute on overriding methods
- Named arguments in calls with 3+ params

### Architecture
- **Injectable callbacks**: every native SSH2 function is behind a constructor-injectable closure (`$connector`, `$executor`, `$nativeCallbacks`, `$sender`, `$receiver`)
- **No real SSH connections in unit tests** — use `FakeSftpStreamWrapper` or mock callbacks
- **`SSHConnection` is the single entry point** for all operations (exec, sftp, scp)

### Testing
- **Unit tests** (in `tests/Unit/`) use fakes — no SSH server
- **Integration tests** (in `tests/Feature/`) need `RUN_INTEGRATION_TESTS=1` + Docker
- **Pattern**: create client with `$this->createClient(initializer: …, nativeCallbacks: […])`
- **Assert callback args** using `&$actual` references to verify the right values were passed
- No tautological assertions like `assertTrue(true)`

### Quality Gates
Run `composer quality-report` to check everything:
- `cs-check` — PSR-12, zero violations
- `phpstan` — Level 9, zero errors (config: `phpstan.neon`)
- `test` — PHPUnit 11.x, 212+ tests, all pass
- `infection` — MSI 80% min, currently 81%

### Commit Style
- No emojis in commit messages
- Present tense imperative: "Add X", "Fix Y", "Refactor Z"
- Squash before merge — one logical commit per change

## Key Files
- `src/Core/SSHConnection.php` — main connection class
- `src/Core/ConnectionBuilder.php` — fluent builder
- `src/SFTP/SFTPClient.php` — SFTP file operations
- `src/SFTP/ScpClient.php` — SCP send/receive
- `stubs/ssh2.stub.php` — PHPStan stubs for ext-ssh2
- `infection.json5` — mutation testing config

## Quick Reference
- Run all checks: `composer check` (cs + phpstan + test)
- Full report: `composer quality-report`
- Mutation: `composer infection`
- Coverage: `composer test:coverage`
