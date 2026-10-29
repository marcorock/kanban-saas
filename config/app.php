<?php

declare(strict_types=1);

return [
    'name' => $_ENV['APP_NAME'] ?? 'Kanban SaaS',
    'secure_cookies' => strtolower((string) parse_url($_ENV['APP_URL'] ?? '', PHP_URL_SCHEME)) === 'https',
    'database' => [
        'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
        'port' => $_ENV['DB_PORT'] ?? '3306',
        'name' => $_ENV['DB_DATABASE'] ?? 'kanbansaas',
        'username' => $_ENV['DB_USERNAME'] ?? '',
        'password' => $_ENV['DB_PASSWORD'] ?? '',
    ],
];
