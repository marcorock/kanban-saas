<?php

declare(strict_types=1);

use Pecee\SimpleRouter\Exceptions\HttpException;
use Pecee\SimpleRouter\SimpleRouter;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

try {
    $config = require dirname(__DIR__) . '/bootstrap/app.php';
    \App\Security\Session::start(($config['secure_cookies']) || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'));
    header('Cache-Control: no-store');
    header('Content-Type: text/html; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    $views = new Environment(new FilesystemLoader(dirname(__DIR__) . '/resources/views'), [
        'cache' => false,
        'debug' => false,
        'autoescape' => 'html',
        'strict_variables' => true,
    ]);
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
