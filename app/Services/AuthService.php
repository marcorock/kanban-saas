<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use PDOException;
use Throwable;

final class AuthService
{
    public function __construct(private PDO $pdo) {}

    public function register(string $name, string $email, string $password): ?int
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)')->execute([$name, $email, $hash]);
            $userId = (int) $this->pdo->lastInsertId();
            $this->pdo->prepare('INSERT INTO tenants (name, owner_id) VALUES (?, ?)')->execute([$name, $userId]);
            $tenantId = (int) $this->pdo->lastInsertId();
            $this->pdo->prepare('INSERT INTO tenant_users (tenant_id, user_id) VALUES (?, ?)')->execute([$tenantId, $userId]);
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
