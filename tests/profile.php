<?php

declare(strict_types=1);
require __DIR__ . '/http.php';
check(request('/settings')[0] === 303, 'Exige autenticação');
$password = 'Senha-perfil-segura-123';
$email = 'profile-' . bin2hex(random_bytes(6)) . '@example.com';
$auth = new App\Services\AuthService($pdo);
$id = $auth->register('Meu nome', $email, $password);
$other = $auth->register('Outra pessoa', 'other-' . $email, $password);
$csrf = token(request('/login')[1]);
request('/login', ['_token' => $csrf, 'email' => $email, 'password' => $password]);
check(request('/settings')[0] === 403, 'Exige confirmação');
$pdo->prepare('UPDATE users SET email_verified_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$id]);
$csrf = token(request('/settings')[1]);
check(request('/settings', ['action' => 'profile', 'name' => 'Inválido'])[0] === 419, 'CSRF');
check(request('/settings', ['_token' => $csrf, 'action' => ['profile']])[0] === 422, 'Array inválido');
check(request('/settings', ['_token' => $csrf, 'action' => 'profile', 'name' => 'A'])[0] === 422, 'Nome curto');
$name = '<script>alert(1)</script>';
check(request('/settings', ['_token' => $csrf, 'action' => 'profile', 'name' => $name, 'user_id' => $other, 'email' => 'changed@example.com', 'session_version' => 100])[0] === 303, 'Salvar perfil');
$html = request('/settings')[1];
check(str_contains($html, '&lt;script&gt;') && !str_contains($html, $name), 'Nome escapado');
check($auth->user($other)['name'] === 'Outra pessoa' && $auth->user($id)['email'] === $email, 'Identidade e campos protegidos');
$first = $cookie;
$cookie = '';
$csrf = token(request('/login')[1]);
request('/login', ['_token' => $csrf, 'email' => $email, 'password' => $password]);
$csrf = token(request('/settings')[1]);
$payload = ['_token' => $csrf, 'action' => 'password', 'current_password' => 'incorreta', 'password' => 'Nova-senha-perfil-456', 'password_confirmation' => 'Nova-senha-perfil-456'];
check(request('/settings', $payload)[0] === 422, 'Senha atual obrigatória');
$payload['current_password'] = $password;
$payload['password_confirmation'] = 'diferente';
check(request('/settings', $payload)[0] === 422, 'Confirmação');
$payload['password_confirmation'] = $payload['password'] = str_repeat('á', 40);
check(request('/settings', $payload)[0] === 422, 'Limite em bytes');
$payload['password_confirmation'] = $payload['password'] = 'Nova-senha-perfil-456';
$pdo->prepare('INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 MINUTE))')->execute([$id, hash('sha256', random_bytes(32))]);
$pdo->exec("CREATE TRIGGER test_profile_rollback BEFORE DELETE ON password_reset_tokens FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'forced failure'");
try {
    try { (new App\Services\ProfileService($pdo))->update($id, 1, 'password', $payload); check(false, 'Falha esperada'); } catch (PDOException) {}
    check($auth->authenticate($email, $password) !== null && (int) $auth->user($id)['session_version'] === 1, 'Rollback senha e versão');
} finally { $pdo->exec('DROP TRIGGER test_profile_rollback'); }
check(request('/settings', $payload)[0] === 303, 'Trocar senha');
check($auth->authenticate($email, $password) === null && $auth->authenticate($email, $payload['password']) !== null, 'Nova senha');
check((int) $pdo->query("SELECT COUNT(*) FROM password_reset_tokens WHERE user_id = {$id}")->fetchColumn() === 0, 'Tokens revogados');
check(request('/settings')[0] === 200, 'Sessão atual preservada');
$current = $cookie;
$cookie = $first;
check(request('/settings')[0] === 303, 'Outra sessão revogada');
$cookie = $current;
$csrf = token(request('/settings')[1]);
check(request('/settings', ['_token' => $csrf, 'action' => 'sessions', 'current_password' => $payload['password']])[0] === 303, 'Encerrar sessões');
check(request('/settings')[0] === 200 && (int) $auth->user($id)['session_version'] === 3, 'Renova sessão e versão');
check(request('/settings', ['_token' => $csrf, 'action' => 'profile', 'name' => 'Nome indevido'])[0] === 419, 'Rotaciona CSRF');
try { (new App\Services\ProfileService($pdo))->update($id, 2, 'profile', ['name' => 'Revogado']); check(false, 'Sessão obsoleta'); }
catch (DomainException $e) { check($e->getCode() === 401, 'Revalida sessão sob bloqueio'); }
$csrf = token(request('/settings')[1]);
$bucket = hash('sha256', 'profile-sessions:' . $id);
$pdo->prepare('UPDATE auth_rate_limits SET attempts = 99 WHERE bucket = ?')->execute([$bucket]);
check(request('/settings', ['_token' => $csrf, 'action' => 'sessions', 'current_password' => $payload['password']])[0] === 429, 'Limite de tentativas');
// Duas alterações sensíveis com a mesma versão: somente uma pode confirmar.
putenv('TEST_PROFILE_ID=' . $id);
putenv('TEST_PROFILE_PASSWORD=' . $payload['password']);
$code = '$c=require "bootstrap/app.php"; $s=new App\\Services\\ProfileService(App\\Infrastructure\\Database::connect($c["database"])); try { $s->update((int)getenv("TEST_PROFILE_ID"),3,"sessions",["current_password"=>getenv("TEST_PROFILE_PASSWORD")]); echo "1"; } catch (DomainException $e) { echo $e->getCode()===401 ? "0" : "error"; }';
$processes = [];
for ($i = 0; $i < 2; $i++) {
    $process = proc_open([PHP_BINARY, '-r', $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__));
    check(is_resource($process), 'Iniciar processo concorrente');
    $processes[] = [$process, $pipes];
}
$results = [];
foreach ($processes as [$process, $pipes]) {
    $results[] = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    check(proc_close($process) === 0 && $stderr === '', 'Processo concorrente');
}
sort($results);
check($results === ['0', '1'], 'Revogação concorrente rejeita sessão obsoleta');
putenv('TEST_PROFILE_ID'); putenv('TEST_PROFILE_PASSWORD');
echo "OK: perfil, CSRF, validação, isolamento, XSS, senha, rollback, tokens e revogação de sessões.\n";
