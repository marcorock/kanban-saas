<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use PDOException;
use Throwable;

final class AuthService
{
    public function __construct(private PDO $pdo) {}

    public function register(string $name, string $email, string $password, ?string $workspaceName, ?string $invitationToken = null, bool $projectInvitation = false): ?int
    {
        if ($workspaceName !== null) $workspaceName = WorkspaceService::validateName($workspaceName);
        elseif (!$invitationToken || !($projectInvitation ? (new ProjectMemberService($this->pdo))->registrationInvitation($invitationToken) : (new WorkspaceMemberService($this->pdo))->validInvitation($invitationToken))) throw new \DomainException('Convite indisponível.',422);
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)')->execute([$name, $email, $hash]);
            $userId = (int) $this->pdo->lastInsertId();
            if ($workspaceName !== null) {
                $this->pdo->prepare('INSERT INTO tenants (name, owner_id) VALUES (?, ?)')->execute([$workspaceName, $userId]);
                $tenantId = (int) $this->pdo->lastInsertId();
                $this->pdo->prepare('INSERT INTO tenant_users (tenant_id, user_id) VALUES (?, ?)')->execute([$tenantId, $userId]);
                $this->pdo->prepare('UPDATE users SET default_tenant_id=? WHERE id=?')->execute([$tenantId, $userId]);
            } else {
                $invite=$projectInvitation ? (new ProjectMemberService($this->pdo))->registrationInvitation($invitationToken) : (new WorkspaceMemberService($this->pdo))->validInvitation($invitationToken);
                if (!$invite || strcasecmp($invite['email'],$email)!==0) throw new \DomainException('Use o e-mail convidado.',422);
            }
            $this->pdo->commit();
            return $userId;
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            if ($exception instanceof PDOException && ($exception->errorInfo[1] ?? null) === 1062) {
                return null;
            }
            throw $exception;
        }
    }

    public function authenticate(string $email, string $password): ?array
    {
        $statement = $this->pdo->prepare('SELECT id, password_hash, session_version FROM users WHERE email = ?');
        $statement->execute([$email]);
        $user = $statement->fetch();
        // Hash válido de custo padrão para reduzir diferenças em contas inexistentes.
        $hash = $user ? $user['password_hash'] : '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        $valid = password_verify($password, $hash);
        if (!$user || !$valid) {
            return null;
        }
        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            $this->pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ? AND password_hash = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $user['id'], $hash]);
        }
        return ['id' => (int) $user['id'], 'session_version' => (int) $user['session_version']];
    }

    public function currentUser(): array|false
    {
        if (!isset($_SESSION['user_id'])) { return false; }
        $user = $this->user((int) $_SESSION['user_id']);
        if (!$user || (int) $user['session_version'] !== ($_SESSION['session_version'] ?? null)) {
            \App\Security\Session::clear();
            return false;
        }
        return $user;
    }

    public function user(int $id): array|false
    {
        $statement = $this->pdo->prepare('SELECT id, name, email, email_verified_at, session_version FROM users WHERE id = ?');
        $statement->execute([$id]);
        return $statement->fetch();
    }
}
