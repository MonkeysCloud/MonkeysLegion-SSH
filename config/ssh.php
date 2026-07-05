<?php

return [
    'default' => 'production',

    'connections' => [
        'production' => [
            'host' => $_ENV['SSH_HOST'] ?? '127.0.0.1',
            'port' => $_ENV['SSH_PORT'] ?? 22,
            'username' => $_ENV['SSH_USERNAME'] ?? 'forge',
            'auth' => $_ENV['SSH_AUTH'] ?? 'key', // 'password', 'key', 'agent'
            'private_key' => $_ENV['SSH_PRIVATE_KEY'] ?? '/home/user/.ssh/id_rsa',
            'passphrase' => $_ENV['SSH_PASSPHRASE'] ?? null,
            'timeout' => $_ENV['SSH_TIMEOUT'] ?? 10,
        ],

        'development' => [
            'host' => '127.0.0.1',
            'port' => 22,
            'username' => 'dev',
            'auth' => 'password',
            'password' => $_ENV['SSH_PASSWORD'] ?? 'dev-password',
            'timeout' => $_ENV['SSH_TIMEOUT'] ?? 10,
        ],
    ],
];