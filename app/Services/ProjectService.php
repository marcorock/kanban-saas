<?php

declare(strict_types=1);

namespace App\Services;

use App\Policies\ProjectPolicy;
use App\Repositories\ProjectRepository;
use PDO;
use Pecee\SimpleRouter\Exceptions\HttpException;
use Throwable;

final class ProjectService
{
    public function __construct(private PDO $pdo) {}

    public function create(int $userId, array $data): int
    {
        $this->pdo->beginTransaction();
        try {
            // Neste incremento a criação utiliza somente o tenant pessoal do proprietário.
            $statement = $this->pdo->prepare("SELECT t.id FROM tenants t JOIN tenant_users tu ON tu.tenant_id = t.id AND tu.user_id = t.owner_id WHERE t.owner_id = ? AND t.kind = 'personal' ORDER BY t.id LIMIT 1 FOR UPDATE");
            $statement->execute([$userId]);
            $tenantId = $statement->fetchColumn();
            if ($tenantId === false) { throw new HttpException('Tenant pessoal indisponível.', 403); }
            $this->pdo->prepare('INSERT INTO projects (tenant_id, owner_id, name, description, starts_on, due_on) VALUES (?, ?, ?, ?, ?, ?)')->execute([$tenantId, $userId, $data['name'], $data['description'], $data['starts_on'] ?: null, $data['due_on'] ?: null]);
            $id = (int) $this->pdo->lastInsertId();
            $this->pdo->prepare("INSERT INTO project_members (tenant_id, project_id, user_id, role) VALUES (?, ?, ?, 'owner')")->execute([$tenantId, $id, $userId]);
            $project = (new ProjectRepository($this->pdo))->find($userId, $id);
            (new ActivityLogger($this->pdo))->record($project, $userId, 'project.created', null, $this->snapshot($project));
            $this->pdo->commit();
            return $id;
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function change(int $userId, int $id, string $action, array $data = []): void
    {
        if (!in_array($action, ['update', 'archive', 'restore', 'delete'], true)) {
            throw new \InvalidArgumentException('Ação de projeto inválida.');
        }
        $this->pdo->beginTransaction();
        try {
            $repository = new ProjectRepository($this->pdo);
            $project = $repository->find($userId, $id, true);
            ProjectPolicy::authorize($project, $userId, $action);
            $before = $this->snapshot($project);
            if ($action === 'update') {
                $this->pdo->prepare('UPDATE projects SET name = ?, description = ?, starts_on = ?, due_on = ? WHERE tenant_id = ? AND id = ?')->execute([$data['name'], $data['description'], $data['starts_on'] ?: null, $data['due_on'] ?: null, $project['tenant_id'], $id]);
            } else {
                $assignment = match ($action) {
                    'archive' => 'archived_at = COALESCE(archived_at, UTC_TIMESTAMP())',
                    'restore' => 'archived_at = NULL',
                    'delete' => 'deleted_at = UTC_TIMESTAMP()',
                };
                $this->pdo->prepare("UPDATE projects SET {$assignment} WHERE tenant_id = ? AND id = ?")->execute([$project['tenant_id'], $id]);
            }
            $statement = $this->pdo->prepare('SELECT * FROM projects WHERE tenant_id = ? AND id = ?');
            $statement->execute([$project['tenant_id'], $id]);
            $after = $this->snapshot($statement->fetch());
            if ($before !== $after) {
                $event = match ($action) { 'update' => 'updated', 'archive' => 'archived', 'restore' => 'restored', 'delete' => 'deleted' };
                (new ActivityLogger($this->pdo))->record($project, $userId, 'project.' . $event, $before, $after);
            }
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    private function snapshot(array $project): array
    {
        return array_intersect_key($project, array_flip(['name', 'description', 'owner_id', 'starts_on', 'due_on', 'archived_at', 'deleted_at']));
    }

}
