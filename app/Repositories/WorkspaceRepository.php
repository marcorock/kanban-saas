<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class WorkspaceRepository
{
    public function __construct(private PDO $pdo) {}

    public function find(int $userId, int $tenantId): array|false
    {
        // A propriedade não substitui o vínculo, nem concede acesso a projetos.
        $statement = $this->pdo->prepare('SELECT t.*, tu.user_id, tu.role FROM tenants t JOIN tenant_users tu ON tu.tenant_id = t.id JOIN tenant_users owner_membership ON owner_membership.tenant_id = t.id AND owner_membership.user_id = t.owner_id WHERE t.id = ? AND tu.user_id = ?');
        $statement->execute([$tenantId, $userId]);
        return $statement->fetch();
    }

    public function defaultForUser(int $userId): array|false
    {
        $statement = $this->pdo->prepare('SELECT default_tenant_id FROM users WHERE id = ?');
        $statement->execute([$userId]);
        $tenantId = $statement->fetchColumn();
        return $tenantId ? $this->find($userId, (int) $tenantId) : false;
    }
}
