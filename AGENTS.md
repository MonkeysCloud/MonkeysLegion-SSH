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
- **Injectable callbacks**: every native SSH2 function is behind a constructor-injectable closure (`$connector`, `$executor`, `$shellOpener`, `$closer`, `$sessionCloser`, `$keepaliveSender`)
- **No real SSH connections in unit tests** — use `CommandChannel` with mock `StreamHandler` or injectable closures
- **`SSHConnection` is the single entry point** for all operations (exec, channel, shell, sftp, scp, pipeline)
- **`CommandChannel`** wraps a single exec stream for multiplexing; `ShellSession` wraps a PTY stream

### Testing
- **Unit tests** (in `tests/Unit/`) use mocks / fakes — no SSH server
- **Integration tests** (in `tests/Feature/`) need `RUN_INTEGRATION_TESTS=1` + Docker
- **Pattern**: create `SSHConnection` with injectable `$connector`, `$executor`, mock `StreamHandler`, and verify callback args via `&$actual` references
- No tautological assertions like `assertTrue(true)`

### Quality Gates
Run `composer quality-report` to check everything:
- `cs-check` — PSR-12, zero violations
- `phpstan` — Level 9, zero errors (config: `phpstan.neon`)
- `test` — PHPUnit 11.x, 273+ tests, all pass
- `infection` — MSI 79% min threshold (some default-value mutants unavoidably escape)

### Commit Style
- No emojis in commit messages
- Present tense imperative: "Add X", "Fix Y", "Refactor Z"
- Squash before merge — one logical commit per change

## Key Files
- `src/Core/SSHConnection.php` — main connection class
- `src/Core/ConnectionBuilder.php` — fluent builder
- `src/SFTP/SFTPClient.php` — SFTP file operations
- `src/SFTP/ScpClient.php` — SCP send/receive
- `src/Stream/CommandChannel.php` — multiplexed exec channel
- `src/Stream/ShellSession.php` — interactive PTY shell
- `src/Stream/CommandResult.php` — command execution result value object
- `src/Stream/StreamHandler.php` — SSH stream read/write abstraction
- `stubs/ssh2.stub.php` — PHPStan stubs for ext-ssh2
- `infection.json5` — mutation testing config
- `demo.php` — interactive shell demo against Docker test container

## Quick Reference
- Run all checks: `composer check` (cs + phpstan + test)
- Full report: `composer quality-report`
- Mutation: `composer infection`
- Coverage: `composer test:coverage`
