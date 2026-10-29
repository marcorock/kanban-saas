<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use Throwable;

final class AccountTokenService
{
    public function __construct(private PDO $pdo, private Mailer $mailer, private string $appUrl) {}

    private function table(string $purpose): string
    {
        return match ($purpose) {
            'verify' => 'email_verification_tokens',
            'reset' => 'password_reset_tokens',
            default => throw new \InvalidArgumentException('Finalidade de token inválida.'),
        };
    }

    public function request(string $email, string $purpose): void
    {
        $table = $this->table($purpose);
        $url = parse_url($this->appUrl);
        if (!$url || !in_array($url['scheme'] ?? '', ['http', 'https'], true) || empty($url['host']) || isset($url['query']) || isset($url['fragment']) || isset($url['user']) || isset($url['pass'])) {
            throw new \InvalidArgumentException('APP_URL inválida.');
        }
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $this->pdo->beginTransaction();
        try {
            // Todas as operações de tokens bloqueiam primeiro o usuário, na mesma ordem.
            $statement = $this->pdo->prepare('SELECT id, email_verified_at FROM users WHERE email = ? FOR UPDATE');
            $statement->execute([$email]);
            $user = $statement->fetch();
            if (!$user || ($purpose === 'verify' && $user['email_verified_at'] !== null)) {
                $this->pdo->commit();
                return;
            }
            $minutes = $purpose === 'verify' ? 1440 : 30;
            $statement = $this->pdo->prepare("INSERT INTO {$table} (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? MINUTE)) ON DUPLICATE KEY UPDATE token_hash = VALUES(token_hash), expires_at = VALUES(expires_at), updated_at = UTC_TIMESTAMP()");
            $statement->execute([$user['id'], $hash, $minutes]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
        $path = $purpose === 'verify' ? '/email/verify' : '/password/reset';
        $link = rtrim($this->appUrl, '/') . $path . '?token=' . $token;
        $subject = $purpose === 'verify' ? 'Confirme seu e-mail' : 'Redefina sua senha';
        $body = $purpose === 'verify'
            ? "Confirme seu e-mail no link abaixo. Ele expira em 24 horas.\n\n{$link}\n\nSe você não criou esta conta, ignore esta mensagem."
            : "Redefina sua senha no link abaixo. Ele expira em 30 minutos.\n\n{$link}\n\nSe você não solicitou a alteração, ignore esta mensagem. Sua senha permanece a mesma.";
        // Nenhum conteúdo da mensagem ou exceção SMTP deve ir para os logs.
        try {
            $this->mailer->send($email, $subject, $body);
        } catch (Throwable) {
            error_log('[kanbansaas] Falha no envio SMTP de mensagem de conta.');
        }
    }

    public function consume(string $token, string $purpose, ?string $password = null): bool
    {
        $table = $this->table($purpose);
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) {
            return false;
        }
        if ($purpose === 'reset' && ($password === null || strlen($password) > 72 || mb_strlen($password) < 12 || str_contains($password, "\0"))) {
            return false;
        }
        $hash = hash('sha256', $token);
        $statement = $this->pdo->prepare("SELECT user_id FROM {$table} WHERE token_hash = ?");
        $statement->execute([$hash]);
        $userId = $statement->fetchColumn();
        if ($userId === false) {
            return false;
        }
        // Hash fora da transação reduz o tempo de bloqueio do usuário.
        $passwordHash = $purpose === 'reset' ? password_hash($password, PASSWORD_DEFAULT) : null;
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE');
            $statement->execute([$userId]);
            if (!$statement->fetchColumn()) {
                $this->pdo->rollBack();
                return false;
            }
            $statement = $this->pdo->prepare("SELECT user_id FROM {$table} WHERE user_id = ? AND token_hash = ? AND expires_at > UTC_TIMESTAMP() FOR UPDATE");
            $statement->execute([$userId, $hash]);
            if (!$statement->fetchColumn()) {
                $this->pdo->rollBack();
                return false;
            }
            if ($purpose === 'verify') {
                $this->pdo->prepare('UPDATE users SET email_verified_at = COALESCE(email_verified_at, UTC_TIMESTAMP()) WHERE id = ?')->execute([$userId]);
            } else {
                $this->pdo->prepare('UPDATE users SET password_hash = ?, session_version = session_version + 1 WHERE id = ?')->execute([$passwordHash, $userId]);
            }
            $this->pdo->prepare("DELETE FROM {$table} WHERE user_id = ?")->execute([$userId]);
            $this->pdo->commit();
            return true;
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }
}
