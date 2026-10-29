<?php

declare(strict_types=1);

return [
    'name' => $_ENV['APP_NAME'] ?? 'Kanban SaaS',
    'environment' => $_ENV['APP_ENV'] ?? 'local',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE),
    'secure_cookies' => strtolower((string) parse_url($_ENV['APP_URL'] ?? '', PHP_URL_SCHEME)) === 'https',
    'url' => $_ENV['APP_URL'] ?? 'http://localhost:8000',
    'mail' => [
        'host' => $_ENV['MAIL_HOST'] ?? '127.0.0.1',
        'port' => (int) ($_ENV['MAIL_PORT'] ?? '587'),
        'username' => $_ENV['MAIL_USERNAME'] ?? '',
        'password' => $_ENV['MAIL_PASSWORD'] ?? '',
        'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls',
        'from_address' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@example.com',
        'from_name' => $_ENV['MAIL_FROM_NAME'] ?? 'Kanban SaaS',
    ],
    'database' => [
        'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
        'port' => $_ENV['DB_PORT'] ?? '3306',
        'name' => $_ENV['DB_DATABASE'] ?? 'kanbansaas',
        'username' => $_ENV['DB_USERNAME'] ?? '',
        'password' => $_ENV['DB_PASSWORD'] ?? '',
    ],
];
