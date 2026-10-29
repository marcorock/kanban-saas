<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use Throwable;

final class ProfileService
{
    public function __construct(private PDO $pdo) {}

    /** Revalida a sessão sob bloqueio para impedir gravações após revogação concorrente. */
    public function update(int $userId, int $version, string $action, array $data): int
    {
        $hash = $action === 'password' ? password_hash($data['password'], PASSWORD_DEFAULT) : null;
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare('SELECT password_hash, session_version FROM users WHERE id = ? FOR UPDATE');
            $statement->execute([$userId]);
            $user = $statement->fetch();
            if (!$user || (int) $user['session_version'] !== $version) {
                throw new \DomainException('Sua sessão foi revogada. Entre novamente.', 401);
            }
            if ($action === 'profile') {
                $this->pdo->prepare('UPDATE users SET name = ? WHERE id = ?')->execute([$data['name'], $userId]);
            } else {
                if (!password_verify($data['current_password'], $user['password_hash'])) {
                    throw new \DomainException('A senha atual não confere.', 422);
                }
                if ($action === 'password') {
                    $this->pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $userId]);
                    $this->pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = ?')->execute([$userId]);
                } elseif ($action !== 'sessions') {
                    throw new \InvalidArgumentException('Ação inválida.');
                }
                $this->pdo->prepare('UPDATE users SET session_version = session_version + 1 WHERE id = ?')->execute([$userId]);
                $version++;
            }
            $this->pdo->commit();
            return $version;
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }
}
