# Testing Documentation

This document outlines all testing workflows for the MonkeysLegion SSH library.

## Quick Test Commands

### Run All Tests (Unit + Feature, no integration)

```bash
composer test
```

### Run with Full Integration Coverage (Docker-backed SSH server)

```bash
docker compose -f docker-compose.integration.yml up -d
RUN_INTEGRATION_TESTS=1 composer test
docker compose -f docker-compose.integration.yml down
```

### Run Static Analysis (PHPStan Level 9)

```bash
composer phpstan
```

### Code Style Check

```bash
composer cs-check
```

### Fix Code Style Issues

```bash
composer cs-fix
```

---

## Test Structure

### Unit Tests

- **Location:** `tests/Unit/`
- **Coverage:**
  - `Authentication/` - Password and public key authentication drivers
  - `Core/` - Connection building, manager, pipelines
  - `Facades/` - Static SSH gateway
  - `Exceptions/` - Exception factory methods
  - `Stream/` - Command result and stream handling
  - `SFTP/` (when added) - SFTP metrics and client

**Run unit tests only:**

```bash
vendor/bin/phpunit tests/Unit
```

### Feature Tests

- **Location:** `tests/Feature/`
- **Coverage:** End-to-end SSH flows (requires real or fake SSH server)
  - Connection + command execution
  - Pipeline halt-on-failure behavior
  - SFTP upload/download with metrics
  - Error handling and exceptions

**Run feature tests without integration:**

```bash
vendor/bin/phpunit tests/Feature --exclude-group integration
```

**Run feature tests WITH integration:**

```bash
docker compose -f docker-compose.integration.yml up -d
RUN_INTEGRATION_TESTS=1 vendor/bin/phpunit tests/Feature
docker compose -f docker-compose.integration.yml down
```

---

## Integration Test Setup

The Docker-backed integration test environment requires:

- Docker and Docker Compose installed
- Port 2222 available on localhost
- `openssh-server` Linux image (pre-configured)

### Startup Sequence

```bash
docker compose -f docker-compose.integration.yml up -d
sleep 5  # Wait for SSH service to be ready
```

### Teardown

```bash
docker compose -f docker-compose.integration.yml down
```

### Manual SSH Verification (for debugging)

```bash
ssh -p 2222 integration@127.0.0.1
# Password: integration-password
```

### Integration Test Environment Variables

- `INTEGRATION_SSH_HOST` (default: `127.0.0.1`)
- `INTEGRATION_SSH_PORT` (default: `2222`)
- `INTEGRATION_SSH_USERNAME` (default: `integration`)
- `INTEGRATION_SSH_PASSWORD` (default: `integration-password`)
- `RUN_INTEGRATION_TESTS` (set to `1` to enable)

---

## Fake/Mock Testing (No Sockets Required)

The library provides utilities for testing SSH interactions without real socket connections.

### Basic Fake Test Example

```php
use MonkeysLegion\SSH\Facades\SSH;
use MonkeysLegion\SSH\Stream\CommandResult;

// Enable fake mode
SSH::fake();

// Define responses
SSH::fakeCommand('whoami', new CommandResult('forge', '', 0));
SSH::fakeCommand('pwd', new CommandResult('/home/forge', '', 0));

// Execute commands (no socket connection needed)
$result = SSH::execute('whoami');
assert($result->output === 'forge');

// Verify execution
SSH::assertExecuted('whoami');
```

### Fake with Default Response

```php
SSH::fake();

// Set fallback for unmapped commands
SSH::fakeDefault(new CommandResult('', 'command not found', 127));

// Unmapped command uses default
$result = SSH::execute('unknown-cmd');
assert($result->exitCode === 127);
```

### Fake with Initial Responses

```php
SSH::fake([
    'uname -a' => new CommandResult('Linux x86_64', '', 0),
    'echo test' => new CommandResult('test', '', 0),
]);

$result = SSH::execute('uname -a');
assert($result->output === 'Linux x86_64');
```

---

## Static Analysis (PHPStan Level 9)

The library uses PHPStan at **level 9** (strictest) with custom stubs for `ext-ssh2`.

### Configuration

- **Config file:** `phpstan.neon`
- **Stubs:** `stubs/ssh2.stub.php` (native function signatures)
- **Coverage:** `src/` and `tests/`

### Run Analysis

```bash
composer phpstan
```

### Fix Common Issues

- Type missing on array keys: `@param array<string, mixed> $config`
- Resource type narrowing: Use `$this->requireResource($var)` pattern
- Closure type hints: Use generic `/** @var \Closure */` for flexibility

---

## Continuous Integration Workflow

For CI/CD pipelines, use this sequence:

```bash
#!/bin/bash
set -e

# 1. Install dependencies
composer install --no-interaction --prefer-dist

# 2. Run unit tests
composer test

# 3. Run static analysis
composer phpstan

# 4. Optional: Run with integration
if [ "$RUN_INTEGRATION_TESTS" = "1" ]; then
    docker compose -f docker-compose.integration.yml up -d
    sleep 5
    RUN_INTEGRATION_TESTS=1 composer test
    docker compose -f docker-compose.integration.yml down
fi

echo "All tests passed!"
```

---

## Test Coverage Report

To generate HTML coverage report:

```bash
composer test-coverage
```

This generates `coverage/` directory with HTML reports.

---

## Debugging Failed Tests

### Enable Verbose Output

```bash
vendor/bin/phpunit --verbose --debug
```

### Run Specific Test

```bash
vendor/bin/phpunit tests/Unit/Core/ConnectionBuilderTest.php::ConnectionBuilderTest::test_builder_can_be_chained
```

### Check Integration Environment

```bash
docker logs monkeyslegion-ssh-test-server
docker compose -f docker-compose.integration.yml ps
```

### Manual SSH Debug (with Docker container running)

```bash
# List executed commands in container
ssh -p 2222 integration@127.0.0.1 -c "id; pwd; ls -la"
```

---

## Test Maintenance

- Update `SSHFakeRegistry` responses when new commands are introduced
- Keep integration tests focused on Phase 3+ features (SFTP, pipelines)
- Add unit tests for new authentication drivers and exception factories
- Ensure PHPStan level 9 compliance on all new code
