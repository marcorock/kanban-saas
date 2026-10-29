<?php

declare(strict_types=1);

// Executar exclusivamente com um banco descartável e o servidor HTTP de teste.
$config = require dirname(__DIR__) . '/bootstrap/app.php';
if (getenv('AUTH_TEST_DATABASE') !== 'disposable') {
    throw new RuntimeException('Defina AUTH_TEST_DATABASE=disposable somente em banco de testes.');
}
$pdo = App\Infrastructure\Database::connect($config['database']);
$base = getenv('AUTH_TEST_URL') ?: 'http://127.0.0.1:8000';
$cookie = '';
function check(bool $condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
}
function request(string $path, ?array $data = null): array {
    global $base, $cookie;
    $context = stream_context_create(['http' => [
        'method' => $data === null ? 'GET' : 'POST', 'ignore_errors' => true, 'follow_location' => 0,
        'header' => "Cookie: {$cookie}\r\nContent-Type: application/x-www-form-urlencoded\r\n",
        'content' => $data === null ? '' : http_build_query($data),
    ]]);
    $body = file_get_contents($base . $path, false, $context);
    $headers = $http_response_header;
    foreach ($headers as $header) {
        if (preg_match('/^Set-Cookie: (kanban_session=[^;]+)/i', $header, $match)) { $cookie = $match[1]; }
    }
    preg_match('/\s(\d{3})\s/', $headers[0], $match);
    return [(int) $match[1], $body, implode("\n", $headers)];
}
function token(string $html): string {
    check((bool) preg_match('/name="_token" value="([a-f0-9]+)"/', $html, $matches), 'Token ausente');
    return $matches[1];
}
