<?php

declare(strict_types=1);

require __DIR__ . '/http.php';

function boardLogin(string $email): string {
    global $cookie, $password;
    $cookie = ''; $csrf = token(request('/login')[1]);
    check(request('/login', ['_token' => $csrf, 'email' => $email, 'password' => $password])[0] === 303, 'Login Kanban');
    return token(request('/dashboard')[1]);
}
function boardRequest(int $projectId, array $data, string $contentType = 'application/json', ?string $raw = null): array {
    global $cookie, $base;
    $context = stream_context_create(['http' => ['method' => 'POST', 'ignore_errors' => true, 'follow_location' => 0,
        'header' => "Cookie: {$cookie}\r\nContent-Type: {$contentType}\r\n",
        'content' => $raw ?? json_encode($data ?: new stdClass()),
    ]]);
    $body = file_get_contents($base . '/projects/' . $projectId . '/board', false, $context);
    preg_match('/\s(\d{3})\s/', $http_response_header[0], $match);
    return [(int) $match[1], json_decode($body, true, flags: JSON_THROW_ON_ERROR)];
}
function mutateBoard(string $action, array $data = [], int $expected = 200, ?int $version = null): array {
    global $projectId, $state, $csrf;
    [$status, $result] = boardRequest($projectId, ['_token' => $csrf, 'version' => $version ?? $state['version'], 'action' => $action] + $data);
    check($status === $expected, 'Mutação ' . $action . ': esperado ' . $expected . ', recebido ' . $status);
    check($result['ok'] === ($expected === 200), 'Contrato JSON');
    if ($status === 200) $state = $result['board'];
    return $result;
}
