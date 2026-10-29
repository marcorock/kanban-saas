<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class ActivityLogger
{
    public function __construct(private PDO $pdo) {}

    // A transação é controlada pelo serviço responsável pela operação.
    public function record(array $project, int $actorId, string $action, ?array $before, ?array $after): void
    {
        $this->pdo->prepare('INSERT INTO activity_logs (tenant_id, project_id, actor_id, action, before_data, after_data) VALUES (?, ?, ?, ?, ?, ?)')->execute([
            $project['tenant_id'], $project['id'], $actorId, $action,
            $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            $after === null ? null : json_encode($after, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ]);
    }
}
