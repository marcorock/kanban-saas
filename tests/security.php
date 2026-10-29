<?php

declare(strict_types=1);
require __DIR__ . '/http.php';
foreach (['/login', '/not-found-security-test', '/settings'] as $path) {
    [$status, $body, $headers] = request($path);
    check(in_array($status, [200, 303, 404], true), 'Resposta prevista');
    $headers = strtolower($headers);
    foreach (["cache-control: no-store", 'x-content-type-options: nosniff', 'x-frame-options: deny', "script-src 'self'", "object-src 'none'", "base-uri 'none'", "frame-ancestors 'none'", "form-action 'self'", 'permissions-policy: camera=(), microphone=(), geolocation=()'] as $expected) {
        check(str_contains($headers, $expected), 'Cabeçalho: ' . $expected);
    }
    check(!str_contains($headers, "script-src 'self' 'unsafe-inline'") && !str_contains($headers, 'unsafe-eval'), 'Scripts sem inline/eval');
    check(!str_contains($body, 'Stack trace') && !str_contains($body, '/app/'), 'Erro sem detalhes internos');
}
[, , $headers] = request('/email/verify?token=' . str_repeat('a', 64));
check(str_contains(strtolower($headers), 'referrer-policy: no-referrer'), 'Token sem referrer');
foreach (['/.env', '/composer.json', '/composer.lock', '/bin/check.php', '/database/migrations/001_identity.sql', '/storage/logs/'] as $path) {
    check(request($path)[0] === 404, 'Arquivo privado fora da raiz pública: ' . $path);
}
// Endpoints de alteração continuam inacessíveis sem token de sessão.
foreach (['/register', '/login', '/logout', '/password/forgot', '/password/reset', '/email/verify', '/email/resend'] as $path) {
    check(request($path, ['_token' => 'invalid'])[0] === 419, 'CSRF: ' . $path);
}
// Outra instância descartável exercita a política HTTPS sem mudar o .env local.
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
check(is_resource($socket), 'Reservar porta de teste');
$address = stream_socket_get_name($socket, false); fclose($socket);
$process = proc_open([PHP_BINARY, '-S', $address, '-t', 'public'], [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, dirname(__DIR__), array_merge(getenv(), ['APP_ENV' => 'production', 'APP_URL' => 'https://kanban.example.org']));
check(is_resource($process), 'Servidor de teste para política HTTPS');
try {
    fclose($pipes[0]); $ready = false;
    for ($i = 0; $i < 40; $i++) {
        $connection = @stream_socket_client('tcp://' . $address, $errno, $error, 0.05);
        if (is_resource($connection)) { fclose($connection); $ready = true; break; }
        usleep(50000);
    }
    check($ready, 'Servidor HTTPS simulado disponível');
    file_get_contents('http://' . $address . '/login');
    $headers = strtolower(implode("\n", $http_response_header));
    check(str_contains($headers, 'strict-transport-security: max-age=31536000'), 'HSTS em configuração de produção HTTPS');
    check((bool) preg_match('/set-cookie: kanban_session=[^\r\n]+; secure; httponly; samesite=lax/', $headers), 'Cookie Secure/HttpOnly/SameSite na configuração HTTPS');
} finally {
    proc_terminate($process); proc_close($process);
}
echo "OK: cabeçalhos de segurança em sucesso/erro/redirecionamento, privacidade de tokens e CSRF.\n";
