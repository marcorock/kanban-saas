<?php

declare(strict_types=1);

namespace App\Security;

final class Session
{
    public static function start(bool $secure): void
    {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_name('kanban_session');
        session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
        if (!session_start()) {
            throw new \RuntimeException('Falha ao iniciar sessão.');
        }
        $now = time();
        if (isset($_SESSION['user_id']) && ($now - ($_SESSION['last_seen'] ?? 0) > 1800 || $now - ($_SESSION['authenticated_at'] ?? 0) > 43200)) {
            self::clear();
        }
        $_SESSION['last_seen'] = $now;
        $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }

    public static function login(int $userId): void
    {
        self::clear();
        $_SESSION['user_id'] = $userId;
        $_SESSION['authenticated_at'] = time();
        $_SESSION['last_seen'] = time();
    }

    public static function clear(): void
    {
        $_SESSION = [];
        if (!session_regenerate_id(true)) {
            throw new \RuntimeException('Falha ao renovar sessão.');
        }
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    public static function verifyCsrf(mixed $token): bool
    {
        return is_string($token) && hash_equals($_SESSION['csrf'], $token);
    }
}
