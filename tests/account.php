<?php

declare(strict_types=1);

require __DIR__ . '/http.php';

$pdo->exec('UPDATE auth_rate_limits SET expires_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 SECOND)');
$mailpit = getenv('MAILPIT_TEST_URL') ?: 'http://127.0.0.1:8025';
function messagesFor(string $email): array {
    global $mailpit;
    $data = json_decode(file_get_contents($mailpit . '/api/v1/messages?limit=100'), true, flags: JSON_THROW_ON_ERROR);
    return array_values(array_filter($data['messages'], static fn (array $message): bool => ($message['To'][0]['Address'] ?? '') === $email));
}
function mailToken(string $email, string $path): string {
    global $mailpit;
    $messages = messagesFor($email);
    check(count($messages) > 0, 'E-mail SMTP não recebido');
    $message = json_decode(file_get_contents($mailpit . '/api/v1/message/' . $messages[0]['ID']), true, flags: JSON_THROW_ON_ERROR);
    check((bool) preg_match('~' . preg_quote($path, '~') . '\?token=([a-f0-9]{64})~', $message['Text'], $match), 'Link não encontrado no e-mail');
    return $match[1];
}
$email = 'tokens-' . bin2hex(random_bytes(6)) . '@example.com';
$password = 'Senha-inicial-segura-123';
$newPassword = 'Nova-senha-segura-456';
$csrf = token(request('/register')[1]);
check(request('/register', ['_token' => $csrf, 'name' => 'Conta de teste', 'email' => $email, 'password' => $password, 'password_confirmation' => $password])[0] === 303, 'Cadastro');
check(request('/dashboard')[0] === 403, 'Bloquear usuário não verificado');
$verify = mailToken($email, '/email/verify');
$statement = $pdo->prepare('SELECT id, email_verified_at FROM users WHERE email = ?');
$statement->execute([$email]);
$user = $statement->fetch();
$id = (int) $user['id'];
$statement = $pdo->prepare('SELECT token_hash FROM email_verification_tokens WHERE user_id = ?');
$statement->execute([$id]);
check($statement->fetchColumn() === hash('sha256', $verify), 'Persistir somente hash');
[$status, $html, $headers] = request('/email/verify?token=' . $verify);
check($status === 200 && str_contains(strtolower($headers), 'referrer-policy: no-referrer'), 'Página de confirmação');
check($pdo->query("SELECT email_verified_at FROM users WHERE id = {$id}")->fetchColumn() === null, 'GET não consome token');
$csrf = token($html);
check(request('/email/verify', ['token' => $verify])[0] === 419, 'CSRF de confirmação');
check(request('/email/verify', ['_token' => $csrf, 'token' => ['invalid']])[0] === 422, 'Token em array');
$csrf = token(request('/email/resend')[1]);
check(request('/email/resend', ['_token' => $csrf, 'email' => $email])[0] === 200, 'Reenvio');
$reissued = mailToken($email, '/email/verify');
check($reissued !== $verify, 'Novo token aleatório');
check(request('/email/verify', ['_token' => $csrf, 'token' => $verify])[0] === 422, 'Reenvio invalida token anterior');
$pdo->exec("UPDATE email_verification_tokens SET expires_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 SECOND) WHERE user_id = {$id}");
check(request('/email/verify', ['_token' => $csrf, 'token' => $reissued])[0] === 422, 'Token expirado');
request('/email/resend', ['_token' => $csrf, 'email' => $email]);
$verify = mailToken($email, '/email/verify');
check(request('/email/verify', ['_token' => $csrf, 'token' => $verify])[0] === 200, 'Confirmar e-mail');
check(request('/email/verify', ['_token' => $csrf, 'token' => $verify])[0] === 422, 'Uso único');
check(request('/dashboard')[0] === 200, 'Painel após confirmação');
$count = count(messagesFor($email));
request('/email/resend', ['_token' => $csrf, 'email' => $email]);
check(count(messagesFor($email)) === $count, 'Conta verificada não recebe confirmação');
$oldSession = $cookie;
// Solicitação anônima deve servir tanto contas existentes quanto inexistentes.
$cookie = '';
$csrf = token(request('/password/forgot')[1]);
check(request('/password/forgot', ['email' => $email])[0] === 419, 'CSRF de recuperação');
check(request('/password/forgot', ['_token' => $csrf, 'email' => ['invalid']])[0] === 422, 'Validar e-mail');
$unknown = request('/password/forgot', ['_token' => $csrf, 'email' => 'missing-' . $email]);
$known = request('/password/forgot', ['_token' => $csrf, 'email' => $email]);
check($unknown[0] === $known[0] && $unknown[1] === $known[1], 'Resposta sem revelar conta');
$reset = mailToken($email, '/password/reset');
$statement = $pdo->prepare('SELECT token_hash FROM password_reset_tokens WHERE user_id = ?');
$statement->execute([$id]);
check($statement->fetchColumn() === hash('sha256', $reset), 'Hash de recuperação');
check(request('/email/verify', ['_token' => $csrf, 'token' => $reset])[0] === 422, 'Separação de finalidades');
$before = $pdo->query("SELECT password_hash FROM users WHERE id = {$id}")->fetchColumn();
request('/password/reset?token=' . $reset);
check($pdo->query("SELECT password_hash FROM users WHERE id = {$id}")->fetchColumn() === $before, 'GET não redefine senha');
$payload = ['_token' => $csrf, 'token' => $reset, 'password' => $newPassword, 'password_confirmation' => $newPassword];
$bad = $payload;
$bad['password_confirmation'] = 'diferente';
check(request('/password/reset', $bad)[0] === 422, 'Confirmação obrigatória');
$bad['password'] = str_repeat('á', 40);
$bad['password_confirmation'] = $bad['password'];
check(request('/password/reset', $bad)[0] === 422, 'Limite em bytes');
$pdo->exec("UPDATE password_reset_tokens SET expires_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 SECOND) WHERE user_id = {$id}");
check(request('/password/reset', $payload)[0] === 422, 'Reset expirado');
request('/password/forgot', ['_token' => $csrf, 'email' => $email]);
$previous = mailToken($email, '/password/reset');
request('/password/forgot', ['_token' => $csrf, 'email' => $email]);
$reset = mailToken($email, '/password/reset');
$payload['token'] = $previous;
check(request('/password/reset', $payload)[0] === 422, 'Reset anterior invalidado');
$count = count(messagesFor($email));
$limited = request('/password/forgot', ['_token' => $csrf, 'email' => $email]);
check($limited[1] === $known[1] && count(messagesFor($email)) === $count, 'Limite por e-mail sem enumeração');
$payload['token'] = $reset;
check(request('/password/reset', $payload)[0] === 200, 'Redefinir senha');
$csrf = token(request('/password/reset?token=' . $reset)[1]);
$payload['_token'] = $csrf;
check(request('/password/reset', $payload)[0] === 422, 'Reset de uso único');
check(request('/dashboard')[0] === 303, 'Reset não autentica automaticamente');
$cookie = $oldSession;
check(request('/dashboard')[0] === 303, 'Revogar sessão anterior após reset');
$csrf = token(request('/login')[1]);
check(request('/login', ['_token' => $csrf, 'email' => $email, 'password' => $password])[0] === 401, 'Senha antiga revogada');
check(request('/login', ['_token' => $csrf, 'email' => $email, 'password' => $newPassword])[0] === 303, 'Nova senha válida');
check(request('/dashboard')[0] === 200, 'Preservar confirmação após reset');
$cookie = '';
$csrf = token(request('/password/forgot')[1]);
$pdo->exec("UPDATE auth_rate_limits SET attempts = 20, expires_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 15 MINUTE) WHERE bucket = '" . hash('sha256', 'account-forgot-ip:127.0.0.1') . "'");
check(request('/password/forgot', ['_token' => $csrf, 'email' => $email])[0] === 429, 'Rate limit por IP');
// Consumo e alteração precisam reverter juntos se o banco falhar.
$raw = bin2hex(random_bytes(32));
$pdo->prepare('INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 MINUTE))')->execute([$id, hash('sha256', $raw)]);
$service = new App\Services\AccountTokenService($pdo, new App\Services\Mailer($config['mail']), $config['url']);
$pdo->exec("CREATE TRIGGER test_reject_password BEFORE UPDATE ON users FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'forced failure'");
try {
    try { $service->consume($raw, 'reset', $password); check(false, 'Falha esperada'); }
    catch (PDOException) {}
    check((int) $pdo->query("SELECT COUNT(*) FROM password_reset_tokens WHERE user_id = {$id}")->fetchColumn() === 1, 'Rollback preserva token');
} finally {
    $pdo->exec('DROP TRIGGER test_reject_password');
}
// Dois processos disputam o mesmo token; só um pode consumi-lo.
putenv('TEST_ACCOUNT_TOKEN=' . $raw);
$code = '$c=require "bootstrap/app.php"; $s=new App\\Services\\AccountTokenService(App\\Infrastructure\\Database::connect($c["database"]),new App\\Services\\Mailer($c["mail"]),$c["url"]); echo $s->consume(getenv("TEST_ACCOUNT_TOKEN"),"reset","Concorrencia-segura-123") ? "1" : "0";';
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
check($results === ['0', '1'], 'Consumo concorrente de uso único');
putenv('TEST_ACCOUNT_TOKEN');
// Falhas SMTP mantêm a resposta genérica e não registram dados da mensagem.
$failedMail = $config['mail'];
$failedMail['port'] = 1;
$failedMail['host'] = '127.0.0.1';
$log = tempnam(sys_get_temp_dir(), 'kanban-mail-test-');
$previousLog = ini_get('error_log');
try {
    ini_set('error_log', $log);
    (new App\Services\AccountTokenService($pdo, new App\Services\Mailer($failedMail), $config['url']))->request($email, 'reset');
    $content = file_get_contents($log);
    check(str_contains($content, 'Falha no envio SMTP') && !str_contains($content, $email) && !str_contains($content, 'token='), 'Log SMTP sem dados sensíveis');
} finally {
    ini_set('error_log', $previousLog);
    unlink($log);
}
echo "OK: SMTP, verificação, reenvio, recuperação, expiração, CSRF, uso único, concorrência, rollback e revogação de sessões.\n";
