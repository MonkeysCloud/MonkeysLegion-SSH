<?php

namespace Tests\Unit\Core;

use MonkeysLegion\SSH\Core\ConnectionBuilder;
use PHPUnit\Framework\TestCase;

class ConnectionBuilderTest extends TestCase
{
    // ----------------------------------------------------------------
    // Basic chaining / builder methods
    // ----------------------------------------------------------------

    public function test_builder_can_be_chained(): void
    {
        $builder = (new ConnectionBuilder());
        $result = $builder->to('localhost')
            ->port(22)
            ->as('user')
            ->withPassword('password');

        $this->assertSame($builder, $result);
    }

    public function test_with_key_returns_self(): void
    {
        $builder = (new ConnectionBuilder());

        $this->assertSame($builder, $builder->withKey('/path/to/key'));
    }

    public function test_with_agent_returns_self(): void
    {
        $builder = (new ConnectionBuilder());

        $this->assertSame($builder, $builder->withAgent());
    }

    public function test_timeout_returns_self(): void
    {
        $builder = (new ConnectionBuilder());

        $this->assertSame($builder, $builder->timeout(30));
    }

    public function test_with_fingerprint_returns_self(): void
    {
        $builder = (new ConnectionBuilder());

        $this->assertSame($builder, $builder->withFingerprint('aa:bb:cc'));
    }

    public function test_command_timeout_returns_self(): void
    {
        $builder = (new ConnectionBuilder());

        $this->assertSame($builder, $builder->commandTimeout(60));
    }

    public function test_command_timeout_accepts_null(): void
    {
        $builder = (new ConnectionBuilder());

        $this->assertSame($builder, $builder->commandTimeout(null));
    }

    public function test_max_output_size_returns_self(): void
    {
        $builder = (new ConnectionBuilder());

        $this->assertSame($builder, $builder->maxOutputSize(1024));
    }

    // ----------------------------------------------------------------
    // Port validation
    // ----------------------------------------------------------------

    public function test_port_rejects_zero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Port must be between 1 and 65535.');

        new ConnectionBuilder()->port(0);
    }

    public function test_port_rejects_negative(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ConnectionBuilder()->port(-1);
    }

    public function test_port_rejects_above_65535(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Port must be between 1 and 65535.');

        new ConnectionBuilder()->port(65536);
    }

    public function test_port_accepts_boundary_values(): void
    {
        $builder = (new ConnectionBuilder());

        $this->assertSame($builder, $builder->port(1));
        $this->assertSame($builder, $builder->port(65535));
    }

    // ----------------------------------------------------------------
    // Timeout validation
    // ----------------------------------------------------------------

    public function test_timeout_rejects_zero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Timeout must be at least 1 second.');

        new ConnectionBuilder()->timeout(0);
    }

    public function test_timeout_rejects_negative(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ConnectionBuilder()->timeout(-5);
    }

    // ----------------------------------------------------------------
    // Max output size validation
    // ----------------------------------------------------------------

    public function test_max_output_size_rejects_zero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Max output size must be at least 1 byte.');

        new ConnectionBuilder()->maxOutputSize(0);
    }

    public function test_max_output_size_rejects_negative(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ConnectionBuilder()->maxOutputSize(-1);
    }

    // ----------------------------------------------------------------
    // connect() validation (before real connection attempt)
    // ----------------------------------------------------------------

    public function test_builder_requires_authentication(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Authentication method must be specified');

        new ConnectionBuilder()->to('localhost')->connect();
    }

    public function test_builder_requires_host(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Host must be specified');

        new ConnectionBuilder()->as('user')->withPassword('password')->connect();
    }

    public function test_builder_requires_username(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Username must be specified');

        new ConnectionBuilder()
            ->to('localhost')
            ->withPassword('password')
            ->connect();
    }

    // ----------------------------------------------------------------
    // fromProfile() — required fields
    // ----------------------------------------------------------------

    public function test_from_profile_requires_host(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Connection profile requires a valid host.');

        new ConnectionBuilder()->fromProfile([
            'username' => 'user',
            'auth' => 'password',
            'password' => 'password',
        ]);
    }

    public function test_from_profile_rejects_empty_host(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Connection profile requires a valid host.');

        new ConnectionBuilder()->fromProfile([
            'host' => '',
            'username' => 'user',
            'auth' => 'password',
            'password' => 'password',
        ]);
    }

    public function test_from_profile_requires_username(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Connection profile requires a valid username.');

        new ConnectionBuilder()->fromProfile([
            'host' => 'localhost',
            'auth' => 'password',
            'password' => 'password',
        ]);
    }

    public function test_from_profile_rejects_empty_username(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Connection profile requires a valid username.');

        new ConnectionBuilder()->fromProfile([
            'host' => 'localhost',
            'username' => '',
            'auth' => 'password',
            'password' => 'password',
        ]);
    }

    // ----------------------------------------------------------------
    // fromProfile() — auth configuration
    // ----------------------------------------------------------------

    public function test_from_profile_rejects_invalid_auth_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Connection profile contains an invalid auth type.');

        new ConnectionBuilder()->fromProfile([
            'host' => 'localhost',
            'username' => 'user',
            'auth' => 123,
        ]);
    }

    public function test_from_profile_defaults_to_password_auth(): void
    {
        // No explicit auth key — should default to password
        $builder = (new ConnectionBuilder());
        $result = $builder->fromProfile([
            'host' => 'localhost',
            'username' => 'user',
            'password' => 'secret',
        ]);

        $this->assertSame($builder, $result);
    }

    public function test_from_profile_with_password_auth_rejects_non_string_password(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Connection profile contains an invalid password value.');

        new ConnectionBuilder()->fromProfile([
            'host' => 'localhost',
            'username' => 'user',
            'auth' => 'password',
            'password' => 12345,
        ]);
    }

    public function test_from_profile_with_key_auth_requires_private_key(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Connection profile requires a valid private_key for key auth.');

        new ConnectionBuilder()->fromProfile([
            'host' => 'localhost',
            'username' => 'user',
            'auth' => 'key',
        ]);
    }

    public function test_from_profile_with_key_auth_rejects_empty_private_key(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Connection profile requires a valid private_key for key auth.');

        new ConnectionBuilder()->fromProfile([
            'host' => 'localhost',
            'username' => 'user',
            'auth' => 'key',
            'private_key' => '',
        ]);
    }

    public function test_from_profile_with_key_auth_rejects_non_string_private_key(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Connection profile requires a valid private_key for key auth.');

        new ConnectionBuilder()->fromProfile([
            'host' => 'localhost',
            'username' => 'user',
            'auth' => 'key',
            'private_key' => 123,
        ]);
    }

    public function test_from_profile_with_key_auth_rejects_non_string_passphrase(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Connection profile contains an invalid passphrase value.');

        new ConnectionBuilder()->fromProfile([
            'host' => 'localhost',
            'username' => 'user',
            'auth' => 'key',
            'private_key' => '/path/to/key',
            'passphrase' => 123,
        ]);
    }

    public function test_from_profile_with_key_auth_accepts_null_passphrase(): void
    {
        $builder = (new ConnectionBuilder());
        $result = $builder->fromProfile([
            'host' => 'localhost',
            'username' => 'user',
            'auth' => 'key',
            'private_key' => '/path/to/key',
            'passphrase' => null,
        ]);

        $this->assertSame($builder, $result);
    }

    public function test_from_profile_with_agent_auth(): void
    {
        $builder = (new ConnectionBuilder());
        $result = $builder->fromProfile([
            'host' => 'localhost',
            'username' => 'user',
            'auth' => 'agent',
        ]);

        $this->assertSame($builder, $result);
    }

    public function test_from_profile_with_unknown_auth_defaults_to_password(): void
    {
        $builder = (new ConnectionBuilder());
        $result = $builder->fromProfile([
            'host' => 'localhost',
            'username' => 'user',
            'auth' => 'unknown-type',
            'password' => 'secret',
        ]);

        $this->assertSame($builder, $result);
    }

    // ----------------------------------------------------------------
    // fromProfile() — optional fields (fingerprint, command_timeout, max_output_size)
    // ----------------------------------------------------------------

    public function test_from_profile_accepts_fingerprint(): void
    {
        $builder = (new ConnectionBuilder());
        $result = $builder->fromProfile([
            'host' => 'localhost',
            'username' => 'user',
            'auth' => 'password',
            'password' => 'secret',
            'fingerprint' => 'aa:bb:cc:dd',
        ]);

        $this->assertSame($builder, $result);
    }

    public function test_from_profile_rejects_non_string_fingerprint(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Connection profile contains an invalid fingerprint.');

        new ConnectionBuilder()->fromProfile([
            'host' => 'localhost',
            'username' => 'user',
            'auth' => 'password',
            'password' => 'secret',
            'fingerprint' => 123,
        ]);
    }

    public function test_from_profile_rejects_empty_fingerprint(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Connection profile contains an invalid fingerprint.');

        new ConnectionBuilder()->fromProfile([
            'host' => 'localhost',
            'username' => 'user',
            'auth' => 'password',
            'password' => 'secret',
            'fingerprint' => '',
        ]);
    }

    public function test_from_profile_accepts_command_timeout(): void
    {
        $builder = (new ConnectionBuilder());
        $result = $builder->fromProfile([
            'host' => 'localhost',
            'username' => 'user',
            'auth' => 'password',
            'password' => 'secret',
            'command_timeout' => 30,
        ]);

        $this->assertSame($builder, $result);
    }

    public function test_from_profile_accepts_command_timeout_as_string(): void
    {
        $builder = (new ConnectionBuilder());
        $result = $builder->fromProfile([
            'host' => 'localhost',
            'username' => 'user',
            'auth' => 'password',
            'password' => 'secret',
            'command_timeout' => '30',
        ]);

        $this->assertSame($builder, $result);
    }

    public function test_from_profile_rejects_invalid_command_timeout(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Connection profile field [command_timeout] must be a positive integer.');

        new ConnectionBuilder()->fromProfile([
            'host' => 'localhost',
            'username' => 'user',
            'auth' => 'password',
            'password' => 'secret',
            'command_timeout' => 'abc',
        ]);
    }

    public function test_from_profile_rejects_zero_command_timeout(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Connection profile field [command_timeout] must be at least 1.');

        new ConnectionBuilder()->fromProfile([
            'host' => 'localhost',
            'username' => 'user',
            'auth' => 'password',
            'password' => 'secret',
            'command_timeout' => 0,
        ]);
    }

    public function test_from_profile_accepts_max_output_size(): void
    {
        $builder = (new ConnectionBuilder());
        $result = $builder->fromProfile([
            'host' => 'localhost',
            'username' => 'user',
            'auth' => 'password',
            'password' => 'secret',
            'max_output_size' => 1048576,
        ]);

        $this->assertSame($builder, $result);
    }

    public function test_from_profile_accepts_max_output_size_as_string(): void
    {
        $builder = (new ConnectionBuilder());
        $result = $builder->fromProfile([
            'host' => 'localhost',
            'username' => 'user',
            'auth' => 'password',
            'password' => 'secret',
            'max_output_size' => '1048576',
        ]);

        $this->assertSame($builder, $result);
    }

    public function test_from_profile_rejects_invalid_max_output_size(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Connection profile field [max_output_size] must be a positive integer.');

        new ConnectionBuilder()->fromProfile([
            'host' => 'localhost',
            'username' => 'user',
            'auth' => 'password',
            'password' => 'secret',
            'max_output_size' => 3.14,
        ]);
    }

    // ----------------------------------------------------------------
    // fromProfile() — port and timeout coercion
    // ----------------------------------------------------------------

    public function test_from_profile_accepts_port_as_string(): void
    {
        $builder = (new ConnectionBuilder());
        $result = $builder->fromProfile([
            'host' => 'localhost',
            'username' => 'user',
            'auth' => 'password',
            'password' => 'secret',
            'port' => '2222',
        ]);

        $this->assertSame($builder, $result);
    }

    public function test_from_profile_rejects_non_numeric_port(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Connection profile field [port] must be a positive integer.');

        new ConnectionBuilder()->fromProfile([
            'host' => 'localhost',
            'username' => 'user',
            'auth' => 'password',
            'password' => 'secret',
            'port' => 'abc',
        ]);
    }

    public function test_from_profile_rejects_zero_port(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Connection profile field [port] must be at least 1.');

        new ConnectionBuilder()->fromProfile([
            'host' => 'localhost',
            'username' => 'user',
            'auth' => 'password',
            'password' => 'secret',
            'port' => 0,
        ]);
    }

    public function test_from_profile_accepts_timeout_as_string(): void
    {
        $builder = (new ConnectionBuilder());
        $result = $builder->fromProfile([
            'host' => 'localhost',
            'username' => 'user',
            'auth' => 'password',
            'password' => 'secret',
            'timeout' => '30',
        ]);

        $this->assertSame($builder, $result);
    }

    public function test_from_profile_rejects_non_numeric_timeout(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Connection profile field [timeout] must be a positive integer.');

        new ConnectionBuilder()->fromProfile([
            'host' => 'localhost',
            'username' => 'user',
            'auth' => 'password',
            'password' => 'secret',
            'timeout' => 'fast',
        ]);
    }

    public function test_from_profile_rejects_zero_timeout(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Connection profile field [timeout] must be at least 1.');

        new ConnectionBuilder()->fromProfile([
            'host' => 'localhost',
            'username' => 'user',
            'auth' => 'password',
            'password' => 'secret',
            'timeout' => 0,
        ]);
    }

    // ----------------------------------------------------------------
    // fromProfile() — uses defaults when port/timeout omitted
    // ----------------------------------------------------------------

    public function test_from_profile_uses_default_port_and_timeout(): void
    {
        $builder = (new ConnectionBuilder());
        $result = $builder->fromProfile([
            'host' => 'localhost',
            'username' => 'user',
            'auth' => 'password',
            'password' => 'secret',
        ]);

        $this->assertSame($builder, $result);
    }

    // ----------------------------------------------------------------
    // Full profile configuration (all optional fields set)
    // ----------------------------------------------------------------

    public function test_from_profile_with_all_optional_fields(): void
    {
        $builder = (new ConnectionBuilder());
        $result = $builder->fromProfile([
            'host' => 'production.example.com',
            'username' => 'deploy',
            'auth' => 'key',
            'private_key' => '/home/deploy/.ssh/id_rsa',
            'passphrase' => 'secret-pass',
            'port' => '2222',
            'timeout' => '20',
            'fingerprint' => 'aa:bb:cc:dd:ee:ff',
            'command_timeout' => '60',
            'max_output_size' => '10485760',
        ]);

        $this->assertSame($builder, $result);
    }

    // ----- keepalive -----

    public function test_keepalive_returns_self(): void
    {
        $builder = (new ConnectionBuilder());
        $this->assertSame($builder, $builder->keepalive(30));
    }

    public function test_keepalive_rejects_zero(): void
    {
        $builder = (new ConnectionBuilder());
        $this->expectException(\InvalidArgumentException::class);
        $builder->keepalive(0);
    }

    public function test_keepalive_rejects_negative(): void
    {
        $builder = (new ConnectionBuilder());
        $this->expectException(\InvalidArgumentException::class);
        $builder->keepalive(-1);
    }

    public function test_from_profile_accepts_keepalive_interval(): void
    {
        $builder = (new ConnectionBuilder());
        $result = $builder->fromProfile([
            'host' => 'host',
            'username' => 'user',
            'auth' => 'password',
            'password' => 'pass',
            'keepalive_interval' => '45',
        ]);

        $this->assertSame($builder, $result);
    }

    public function test_from_profile_rejects_non_numeric_keepalive_interval(): void
    {
        $builder = (new ConnectionBuilder());
        $this->expectException(\InvalidArgumentException::class);
        $builder->fromProfile([
            'host' => 'host',
            'username' => 'user',
            'auth' => 'password',
            'password' => 'pass',
            'keepalive_interval' => 'abc',
        ]);
    }
}
