<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use DomainException;
use Throwable;

final class WorkspaceService
{
    public function __construct(private PDO $pdo) {}

    public static function validateName(string $name): string
    {
        $name = trim($name);
        if (!mb_check_encoding($name, 'UTF-8') || mb_strlen($name) < 2 || mb_strlen($name) > 120 || preg_match('/[\x00-\x1f\x7f]/', $name)) {
            throw new DomainException('Informe o nome da Área de Trabalho com 2 a 120 caracteres, sem caracteres de controle.');
        }
        return $name;
    }

    private function lockUser(int $userId, int $sessionVersion): void
    {
        $query = $this->pdo->prepare('SELECT session_version, email_verified_at FROM users WHERE id=? FOR UPDATE');
        $query->execute([$userId]);
        $user = $query->fetch();
        if (!$user || (int) $user['session_version'] !== $sessionVersion) throw new DomainException('Entre novamente.', 401);
        if (!$user['email_verified_at']) throw new DomainException('Confirme seu e-mail.', 403);
    }

    public function create(int $userId, int $sessionVersion, string $name): int
    {
        $name = self::validateName($name);
        $this->pdo->beginTransaction();
        try {
            $this->lockUser($userId, $sessionVersion);
            $this->pdo->prepare("INSERT INTO tenants(name,owner_id,kind) VALUES (?,?,'organization')")->execute([$name, $userId]);
            $tenantId = (int) $this->pdo->lastInsertId();
            $this->pdo->prepare('INSERT INTO tenant_users(tenant_id,user_id) VALUES (?,?)')->execute([$tenantId, $userId]);
            // Uma conta convidada pode ainda não ter padrão; preferências existentes são preservadas.
            $this->pdo->prepare('UPDATE users SET default_tenant_id=? WHERE id=? AND default_tenant_id IS NULL')->execute([$tenantId, $userId]);
            $this->pdo->commit();
            return $tenantId;
        } catch (Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }

    public function setDefault(int $userId, int $sessionVersion, int $tenantId): void
    {
        $this->pdo->beginTransaction();
        try {
            // Mesma ordem de bloqueios das operações delimitadas pelo contexto.
            (new WorkspaceContext($userId, $tenantId))->authorize($this->pdo, $userId, true);
            $this->lockUser($userId, $sessionVersion);
            $this->pdo->prepare('UPDATE users SET default_tenant_id=? WHERE id=?')->execute([$tenantId, $userId]);
            $this->pdo->commit();
        } catch (Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }
}
