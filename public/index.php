<?php

declare(strict_types=1);

use Pecee\SimpleRouter\Exceptions\HttpException;
use Pecee\SimpleRouter\SimpleRouter;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

header('Cache-Control: no-store');
header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

try {
    $config = require dirname(__DIR__) . '/bootstrap/app.php';
    \App\Security\Session::start(($config['secure_cookies']) || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'));
    if ($config['environment'] === 'production' && $config['secure_cookies']) {
        header('Strict-Transport-Security: max-age=31536000');
    }
    $views = new Environment(new FilesystemLoader(dirname(__DIR__) . '/resources/views'), [
        'cache' => false,
        'debug' => false,
        'autoescape' => 'html',
        'strict_variables' => true,
    ]);
    // Pecee 5 tenta interpretar o corpo antes do controller; valide JSON antes do roteador.
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
        && strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) === 'application/json') {
        $raw = file_get_contents('php://input', false, null, 0, 65537);
        $status = 0;
        $message = '';
        if (strlen($raw) > 65536) {
            $status = 413; $message = 'Solicitação muito grande.';
        } else {
            try {
                $decoded = json_decode($raw, false, 32, JSON_THROW_ON_ERROR);
                if (!$decoded instanceof \stdClass) { $status = 422; $message = 'Envie um objeto JSON válido.'; }
            } catch (\JsonException) { $status = 422; $message = 'JSON inválido.'; }
        }
        if ($status) {
            http_response_code($status);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['ok' => false, 'message' => $message], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    require dirname(__DIR__) . '/routes/web.php';
    SimpleRouter::start();
} catch (Throwable $exception) {
    $status = $exception instanceof HttpException ? (int) $exception->getCode() : 500;
    if ($status < 400 || $status > 599) {
        $status = 500;
    }
    http_response_code($status);
    // Não registrar mensagens, parâmetros ou stack traces que possam conter credenciais.
    if ($status >= 500) {
        error_log(sprintf('[kanbansaas] %s em %s:%d', $exception::class, basename($exception->getFile()), $exception->getLine()));
    }
    echo $status === 404 ? 'Página não encontrada.' : 'Não foi possível processar a solicitação.';
}
