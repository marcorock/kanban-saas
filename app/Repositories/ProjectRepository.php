<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use Pecee\SimpleRouter\Exceptions\HttpException;

final class ProjectRepository
{
    private const ACCESS = ' FROM projects p JOIN project_members pm ON pm.project_id = p.id AND pm.tenant_id = p.tenant_id JOIN tenant_users tu ON tu.tenant_id = p.tenant_id AND tu.user_id = pm.user_id WHERE pm.user_id = ? AND p.deleted_at IS NULL';

    public function __construct(private PDO $pdo) {}

    public function find(int $userId, int $projectId, bool $lock = false): array
    {
        $statement = $this->pdo->prepare('SELECT p.*, pm.role' . self::ACCESS . ' AND p.id = ?' . ($lock ? ' FOR UPDATE' : ''));
        $statement->execute([$userId, $projectId]);
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
