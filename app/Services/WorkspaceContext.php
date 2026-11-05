<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\WorkspaceRepository;
use PDO;
use Pecee\SimpleRouter\Exceptions\HttpException;

/** Contexto imutável da operação, independente da sessão e da preferência da conta. */
final class WorkspaceContext
{
    public function __construct(public readonly int $userId, public readonly int $tenantId)
    {
        if ($userId < 1 || $tenantId < 1) throw new HttpException('Área de Trabalho não encontrada.', 404);
    }

    public static function resolve(PDO $pdo, int $userId, mixed $tenantId): self
    {
        if (!is_string($tenantId) || !ctype_digit($tenantId) || strlen($tenantId) > 18 || (int) $tenantId < 1) {
            throw new HttpException('Área de Trabalho não encontrada.', 404);
        }
        $context = new self($userId, (int) $tenantId);
        $context->authorize($pdo, $userId);
        return $context;
    }

    /** Entrada das rotas sem contexto, até a navegação/padrão da etapa 5. */
    public static function initial(PDO $pdo, int $userId): self
    {
        $query = $pdo->prepare("SELECT t.id FROM tenants t JOIN tenant_users tu ON tu.tenant_id=t.id AND tu.user_id=? JOIN tenant_users owner_membership ON owner_membership.tenant_id=t.id AND owner_membership.user_id=t.owner_id ORDER BY (t.owner_id=? AND t.kind='personal') DESC, t.id LIMIT 1");
        $query->execute([$userId, $userId]);
        $id = $query->fetchColumn();
        if (!$id) throw new HttpException('Área de Trabalho não encontrada.', 404);
        return self::resolve($pdo, $userId, (string) $id);
    }

    public function authorize(PDO $pdo, int $userId, bool $lock = false): void
    {
        if ($userId !== $this->userId || !(new WorkspaceRepository($pdo))->find($userId, $this->tenantId, $lock)) {
            throw new HttpException('Área de Trabalho não encontrada.', 404);
        }
    }
}
