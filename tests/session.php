<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Security\Session;

function checkSession(bool $condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
}
Session::start(true);
$params = session_get_cookie_params();
checkSession($params['secure'] && $params['httponly'] && $params['samesite'] === 'Lax', 'Configuração dos cookies');
$oldId = session_id();
$oldToken = $_SESSION['csrf'];
Session::login(123);
checkSession(session_id() !== $oldId && !Session::verifyCsrf($oldToken), 'Rotação no login');
$_SESSION['last_seen'] = time() - 1801;
session_write_close();
Session::start(true);
checkSession(!isset($_SESSION['user_id']), 'Expiração por inatividade');
Session::login(123);
$_SESSION['authenticated_at'] = time() - 43201;
session_write_close();
Session::start(true);
checkSession(!isset($_SESSION['user_id']), 'Expiração absoluta');
session_destroy();
echo "OK: cookies, renovação e expiração de sessão.\n";
