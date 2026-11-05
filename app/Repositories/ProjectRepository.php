<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use Pecee\SimpleRouter\Exceptions\HttpException;

final class ProjectRepository
{
    private const ACCESS = ' FROM projects p JOIN project_members pm ON pm.project_id = p.id AND pm.tenant_id = p.tenant_id JOIN tenant_users tu ON tu.tenant_id = p.tenant_id AND tu.user_id = pm.user_id WHERE pm.user_id = ? AND p.deleted_at IS NULL';

    public function __construct(private PDO $pdo, private ?\App\Services\WorkspaceContext $context = null) {}

    public function find(int $userId, int $projectId, bool $lock = false): array
    {
        if ($lock && !$this->pdo->inTransaction()) throw new \LogicException('Bloqueio exige transação.');
        $context = $this->context;
        if (!$context) {
            $tenant = $this->pdo->prepare('SELECT tenant_id FROM projects WHERE id = ? AND deleted_at IS NULL');
            $tenant->execute([$projectId]);
            $tenantId = $tenant->fetchColumn();
            if (!$tenantId) throw new HttpException('Projeto não encontrado.', 404);
            $context = new \App\Services\WorkspaceContext($userId, (int) $tenantId);
        }
        $context->authorize($this->pdo, $userId, $lock);
        $statement = $this->pdo->prepare('SELECT p.*, pm.role' . self::ACCESS . ' AND p.id = ?' . ' AND p.tenant_id = ?' . ($lock ? ' FOR UPDATE' : ''));
        try {
            $statement->execute([$userId, $projectId, $context->tenantId]);
        } catch (\PDOException $exception) {
            // MariaDB pode detectar mudança entre a resolução do tenant e o bloqueio do projeto.
            if ((int) ($exception->errorInfo[1] ?? 0) === 1020) {
                throw new HttpException('O projeto foi alterado. Recarregue a página.', 409);
            }
            throw $exception;
        }
        $project = $statement->fetch();
        if (!$project) { throw new HttpException('Projeto não encontrado.', 404); }
        return $project;
    }

    public function page(int $userId, bool $archived, int $page): array
    {
        $filter = $archived ? ' AND p.archived_at IS NOT NULL' : ' AND p.archived_at IS NULL';
        $statement = $this->pdo->prepare('SELECT COUNT(*)' . self::ACCESS . $filter);
        $statement->execute([$userId]);
        $total = (int) $statement->fetchColumn();
        $pages = max(1, (int) ceil($total / 25));
        $page = max(1, min($page, $pages));
        $statement = $this->pdo->prepare('SELECT p.*, pm.role' . self::ACCESS . $filter . ' ORDER BY p.id DESC LIMIT ? OFFSET ?');
        $statement->bindValue(1, $userId, PDO::PARAM_INT);
        $statement->bindValue(2, 25, PDO::PARAM_INT);
        $statement->bindValue(3, ($page - 1) * 25, PDO::PARAM_INT);
        $statement->execute();
        return ['items' => $statement->fetchAll(), 'page' => $page, 'pages' => $pages, 'total' => $total];
    }
}
