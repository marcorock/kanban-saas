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
$email = 'test-' . bin2hex(random_bytes(6)) . '@example.com';
$password = 'Senha-teste-segura-123';
[$status, , $headers] = request('/dashboard');
check($status === 303, 'Dashboard deve exigir login');
check(str_contains(strtolower($headers), 'httponly') && str_contains(strtolower($headers), 'samesite=lax'), 'Cookies seguros');
[$status, $html, $headers] = request('/register');
check($status === 200, 'Formulário de cadastro');
$csrf = token($html);
check(request('/register', ['email' => $email])[0] === 419, 'CSRF obrigatório');
check(request('/register', ['_token' => $csrf, 'email' => ['invalid']])[0] === 422, 'Rejeitar arrays');
check(request('/register', ['_token' => $csrf, 'email' => $email, 'password' => 'curta'])[0] === 422, 'Validar cadastro');
$data = ['_token' => $csrf, 'name' => '<script>alert(1)</script>', 'email' => strtoupper($email), 'password' => $password, 'password_confirmation' => $password];
$oldCookie = $cookie;
check(request('/register', $data)[0] === 303 && $cookie !== $oldCookie, 'Cadastro e rotação de sessão');
[$status, $html] = request('/dashboard');
check($status === 200 && str_contains($html, '&lt;script&gt;') && !str_contains($html, '<script>'), 'Dashboard e escape');
$statement = $pdo->prepare('SELECT u.id, u.password_hash, t.id AS tenant_id FROM users u JOIN tenants t ON t.owner_id = u.id JOIN tenant_users tu ON tu.user_id = u.id AND tu.tenant_id = t.id WHERE u.email = ?');
$statement->execute([$email]);
$user = $statement->fetch();
check((bool) $user && password_verify($password, $user['password_hash']), 'Hash e tenant pessoal');
check(request('/logout', ['_token' => $csrf])[0] === 419, 'Token antigo invalidado');
check(request('/logout', ['_token' => token($html)])[0] === 303, 'Logout');
check(request('/dashboard')[0] === 303, 'Logout revoga acesso');
$csrf = token(request('/login')[1]);
check(request('/login', ['_token' => $csrf, 'email' => $email, 'password' => 'errada'])[0] === 401, 'Senha incorreta');
check(request('/login', ['_token' => $csrf, 'email' => $email, 'password' => $password])[0] === 303, 'Login válido');
request('/logout', ['_token' => token(request('/dashboard')[1])]);
$csrf = token(request('/register')[1]);
$data['_token'] = $csrf;
check(request('/register', $data)[0] === 422, 'E-mail duplicado');
$statement = $pdo->prepare('SELECT COUNT(*) FROM tenants WHERE owner_id = ?');
$statement->execute([$user['id']]);
check((int) $statement->fetchColumn() === 1, 'Duplicação não cria tenant');
$csrf = token(request('/login')[1]);
for ($i = 0; $i < 8; $i++) {
    check(request('/login', ['_token' => $csrf, 'email' => $email, 'password' => 'errada'])[0] === 401, 'Tentativa antes do limite');
}
check(request('/login', ['_token' => $csrf, 'email' => $email, 'password' => $password])[0] === 429, 'Limite persistente');
$cookie = '';
$csrf = token(request('/login')[1]);
check(request('/login', ['_token' => $csrf, 'email' => $email, 'password' => $password])[0] === 429, 'Limite entre sessões');
$auth = new App\Services\AuthService($pdo);
$rollbackEmail = 'rollback-' . $email;
$pdo->exec("CREATE TRIGGER test_reject_tenant BEFORE INSERT ON tenants FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'forced failure'");
try {
    try { $auth->register('Rollback', $rollbackEmail, $password); check(false, 'Falha esperada'); }
    catch (PDOException) {}
    $statement = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
    $statement->execute([$rollbackEmail]);
    check((int) $statement->fetchColumn() === 0, 'Rollback integral');
} finally {
    $pdo->exec('DROP TRIGGER test_reject_tenant');
}
// Janela vencida deve voltar a aceitar tentativas.
$pdo->exec("UPDATE auth_rate_limits SET expires_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 SECOND)");
check(request('/login', ['_token' => $csrf, 'email' => $email, 'password' => $password])[0] === 303, 'Expiração do limite');
echo "OK: cadastro, login, logout, CSRF, sessão, escape, validação, tenant, rollback e rate limit.\n";
