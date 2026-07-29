<?php

/**
 * Helper: read an environment variable with a fallback.
 * Uses getenv() which works regardless of variables_order / E in php.ini.
 */
$env = static fn (string $key, string|int|null $fallback = null): string|int|null => ($val = \getenv($key)) !== false && $val !== '' ? $val : $fallback;

return [
    'default' => $env('SSH_DEFAULT_CONNECTION', 'production'),

    'connections' => [
        'production' => [
            'host' => $env('SSH_HOST', '127.0.0.1'),
            'port' => (int) $env('SSH_PORT', 22),
            'username' => $env('SSH_USERNAME', 'forge'),
            'auth' => $env('SSH_AUTH', 'key'), // 'password', 'key', 'agent'
            'private_key' => $env('SSH_PRIVATE_KEY', '/home/user/.ssh/id_rsa'),
            'passphrase' => $env('SSH_PASSPHRASE'),
            'timeout' => (int) $env('SSH_TIMEOUT', 10),
        ],

        'development' => [
            'host' => '127.0.0.1',
            'port' => 22,
            'username' => 'dev',
            'auth' => 'password',
            'password' => $env('SSH_PASSWORD', 'dev-password'),
            'timeout' => (int) $env('SSH_TIMEOUT', 10),
        ],
    ],
];
