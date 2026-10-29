<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class RateLimiter
{
    public function __construct(private PDO $pdo) {}

    public function allow(string $scope, string $identity, int $limit, int $seconds = 900): bool
    {
        $key = hash('sha256', $scope . ':' . $identity);
        // Uma atualização atômica mantém contagem também entre sessões e processos.
        $statement = $this->pdo->prepare('INSERT INTO auth_rate_limits (bucket, attempts, expires_at) VALUES (?, 1, DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? SECOND)) ON DUPLICATE KEY UPDATE attempts = IF(expires_at <= UTC_TIMESTAMP(), 1, attempts + 1), expires_at = IF(expires_at <= UTC_TIMESTAMP(), VALUES(expires_at), expires_at)');
        $statement->execute([$key, $seconds]);
        $statement = $this->pdo->prepare('SELECT attempts FROM auth_rate_limits WHERE bucket = ?');
        $statement->execute([$key]);
        return (int) $statement->fetchColumn() <= $limit;
    }
}
