<?php

declare(strict_types=1);

require __DIR__ . '/http.php';
require __DIR__ . '/mailpit.php';

$pdo->exec('UPDATE auth_rate_limits SET expires_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 SECOND)');
$password = 'Senha-equipe-segura-123';
$auth = new App\Services\AuthService($pdo);
$service = new App\Services\ProjectMemberService($pdo);
$mailer = new App\Services\Mailer($config['mail']);
$suffix = bin2hex(random_bytes(6));
$users = [];
foreach (['owner', 'admin', 'guest', 'wrong', 'rollback', 'concurrent', 'revoked', 'expired'] as $name) {
    $email = $name . '-' . $suffix . '@example.com';
    $id = $auth->register($name, $email, $password);
    $pdo->prepare('UPDATE users SET email_verified_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$id]);
    $users[$name] = ['id' => $id, 'email' => $email];
}
function loginMember(string $email, bool $newSession = true): string {
    global $cookie, $password;
    if ($newSession) { $cookie = ''; }
    $csrf = token(request('/login')[1]);
    check(request('/login', ['_token' => $csrf, 'email' => $email, 'password' => $password])[0] === 303, 'Login da equipe');
    return token(request('/dashboard')[1]);
}
function openInvitation(string $raw): string {
    check(request('/invitations/accept?token=' . $raw)[0] === 303, 'Limpar token da URL');
    return request('/invitations/accept')[1];
}
$owner = $users['owner']['id'];
$projectService = new App\Services\ProjectService($pdo);
$fixture = ['name' => 'Equipe <b>' . $suffix . '</b>', 'description' => '', 'starts_on' => '', 'due_on' => ''];
$projectId = $projectService->create($owner, $fixture);
$secondId = $projectService->create($owner, $fixture + ['ignored' => '']);
$project = (new App\Repositories\ProjectRepository($pdo))->find($owner, $projectId);
$tenantId = (int) $project['tenant_id'];
check(request('/projects/' . $projectId . '/members')[0] === 303, 'Participantes exigem login');
$ownerCsrf = loginMember($users['owner']['email']);
$ownerCookie = $cookie;
$path = '/projects/' . $projectId;
check(request($path . '/members')[0] === 200, 'Tela de participantes');
check(request($path . '/invitations', ['email' => $users['guest']['email'], 'role' => 'viewer'])[0] === 419, 'CSRF de convite');
check(request($path . '/invitations', ['_token' => $ownerCsrf, 'email' => [], 'role' => 'viewer'])[0] === 422, 'Validar e-mail');
check(request($path . '/invitations', ['_token' => $ownerCsrf, 'email' => $users['guest']['email'], 'role' => 'owner'])[0] === 403, 'Não convidar proprietário');
check(request($path . '/invitations', ['_token' => $ownerCsrf, 'email' => $users['owner']['email'], 'role' => 'member'])[0] === 409, 'Não convidar participante existente');
$inviteData = ['_token' => $ownerCsrf, 'email' => $users['guest']['email'], 'role' => 'viewer'];
check(request($path . '/invitations', $inviteData)[0] === 303, 'Enviar convite via HTTP');
$first = mailToken($users['guest']['email'], '/invitations/accept');
$statement = $pdo->prepare('SELECT token_hash FROM project_invitations WHERE project_id = ? AND email = ?');
$statement->execute([$projectId, $users['guest']['email']]);
check($statement->fetchColumn() === hash('sha256', $first), 'Guardar somente hash');
check((int) $pdo->query("SELECT COUNT(*) FROM project_members WHERE project_id = {$projectId}")->fetchColumn() === 1, 'Convite não concede acesso');
check(!str_contains(request($path . '/members')[1], $first), 'Não expor token na tela');
check(request($path . '/invitations', $inviteData)[0] === 303, 'Reenviar');
$raw = mailToken($users['guest']['email'], '/invitations/accept');
check($raw !== $first && $service->preview($users['guest']['id'], $first) === false, 'Reenvio invalida token anterior');
$wrongCsrf = loginMember($users['wrong']['email']);
$wrongHtml = openInvitation($raw);
check(!str_contains($wrongHtml, $suffix . '&lt;/b&gt;'), 'Conta errada não vê projeto');
check(request('/invitations/accept', ['_token' => $wrongCsrf])[0] === 403, 'E-mail diferente não aceita');
check(request($path . '/members')[0] === 404, 'IDOR de participantes');
check(request($path . '/invitations', ['_token' => $wrongCsrf, 'email' => $users['guest']['email'], 'role' => 'member'])[0] === 404, 'IDOR de convite');
$cookie = '';
$anonymous = openInvitation($raw);
check(str_contains($anonymous, 'Criar conta') && !str_contains($anonymous, $suffix), 'Convite público não revela projeto');
$preLoginCookie = $cookie;
$guestCsrf = loginMember($users['guest']['email'], false);
check($preLoginCookie !== $cookie, 'Sessão renovada preserva convite');
$preview = request('/invitations/accept')[1];
check(str_contains($preview, 'Equipe &lt;b&gt;'), 'Destinatário vê preview escapado');
check(request($path)[0] === 404, 'Sem acesso antes do aceite');
check(request('/invitations/accept', [])[0] === 419, 'CSRF de aceite');
check(request('/invitations/accept', ['_token' => $guestCsrf, 'role' => 'admin', 'user_id' => $owner])[0] === 303, 'Aceite');
$accepted = (new App\Repositories\ProjectRepository($pdo))->find($users['guest']['id'], $projectId);
check($accepted['role'] === 'viewer', 'Ignorar elevação de papel no aceite');
check(request('/projects/' . $secondId)[0] === 404, 'Aceite não dá acesso a outros projetos do tenant');
check(request($path . '/members')[0] === 200 && !str_contains(request($path . '/members')[1], $users['owner']['email']), 'Visualizador não vê e-mails nem convites');
openInvitation($raw);
check(request('/invitations/accept', ['_token' => $guestCsrf])[0] === 422, 'Aceite de uso único');
check(request($path . '/invitations', ['_token' => $guestCsrf, 'email' => $users['wrong']['email'], 'role' => 'member'])[0] === 403, 'Visualizador não convida');
$guestCookie = $cookie;
$cookie = $ownerCookie;
check(request($path . '/members/role', ['_token' => $ownerCsrf, 'user_id' => $users['guest']['id'], 'role' => 'member'])[0] === 303, 'Alterar papel');
check(request($path . '/members/remove', ['_token' => $ownerCsrf, 'user_id' => $owner, 'confirm' => 'remove'])[0] === 403, 'Proteger proprietário');
// Administrador é convidado e aceita pelo fluxo real.
check($service->invite($owner, $projectId, $users['admin']['email'], 'admin', $mailer, $config['url']), 'Convidar administrador');
$adminRaw = mailToken($users['admin']['email'], '/invitations/accept');
$adminCsrf = loginMember($users['admin']['email']);
openInvitation($adminRaw);
check(request('/invitations/accept', ['_token' => $adminCsrf])[0] === 303, 'Aceitar administrador');
$adminCookie = $cookie;
check(request($path . '/invitations', ['_token' => $adminCsrf, 'email' => $users['wrong']['email'], 'role' => 'admin'])[0] === 403, 'Admin não convida admin');
check(request($path . '/members/role', ['_token' => $adminCsrf, 'user_id' => $users['guest']['id'], 'role' => 'admin'])[0] === 403, 'Admin não promove admin');
check(request($path . '/members/role', ['_token' => $adminCsrf, 'user_id' => $users['admin']['id'], 'role' => 'viewer'])[0] === 403, 'Admin não altera administrador');
check(request($path . '/members/role', ['_token' => $adminCsrf, 'user_id' => $users['guest']['id'], 'role' => 'viewer'])[0] === 303, 'Admin altera membro/visualizador');
check(request($path . '/members/remove', ['_token' => $adminCsrf, 'user_id' => $users['guest']['id']])[0] === 422, 'Confirmar remoção');
check(request($path . '/members/remove', ['_token' => $adminCsrf, 'user_id' => $users['guest']['id'], 'confirm' => 'remove'])[0] === 303, 'Admin remove membro');
$cookie = $guestCookie;
check(request($path)[0] === 404, 'Remoção revoga acesso imediatamente');
// Cancelamento e expiração.
$cookie = $ownerCookie;
$service->invite($owner, $projectId, $users['expired']['email'], 'admin', $mailer, $config['url']);
$expiredRaw = mailToken($users['expired']['email'], '/invitations/accept');
$invitationId = (int) $pdo->query("SELECT id FROM project_invitations WHERE token_hash = '" . hash('sha256', $expiredRaw) . "'")->fetchColumn();
$cookie = $adminCookie;
check(request($path . '/invitations/cancel', ['_token' => $adminCsrf, 'invitation_id' => $invitationId])[0] === 403, 'Admin não cancela convite de admin');
$cookie = $ownerCookie;
check(request($path . '/invitations/cancel', ['_token' => $ownerCsrf, 'invitation_id' => $invitationId])[0] === 303, 'Cancelar convite');
check($service->preview($users['expired']['id'], $expiredRaw) === false, 'Cancelamento invalida link');
$service->invite($owner, $projectId, $users['expired']['email'], 'member', $mailer, $config['url']);
$expiredRaw = mailToken($users['expired']['email'], '/invitations/accept');
$pdo->exec("UPDATE project_invitations SET expires_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 SECOND) WHERE id = {$invitationId}");
check($service->preview($users['expired']['id'], $expiredRaw) === false, 'Convite expira');
try { $service->accept($users['expired']['id'], $expiredRaw); check(false, 'Deveria falhar'); } catch (Pecee\SimpleRouter\Exceptions\HttpException $e) { check($e->getCode() === 422, 'Aceite expirado'); }
// IDs de convites de outro projeto não podem ser usados mesmo pelo mesmo proprietário.
check(request('/projects/' . $secondId . '/invitations/cancel', ['_token' => $ownerCsrf, 'invitation_id' => $invitationId])[0] === 404, 'Convite pertence ao projeto informado');
// Limites de reenvio são persistentes por projeto e e-mail.
$limitedEmail = 'limited-' . $suffix . '@example.com';
for ($attempt = 0; $attempt < 3; $attempt++) {
    check(request($path . '/invitations', ['_token' => $ownerCsrf, 'email' => $limitedEmail, 'role' => 'viewer'])[0] === 303, 'Envio antes do limite');
}
check(request($path . '/invitations', ['_token' => $ownerCsrf, 'email' => $limitedEmail, 'role' => 'viewer'])[0] === 429, 'Limite de envio');
check(count(messagesFor($limitedEmail)) === 3, 'Não enviar após limite');
// Novo cadastro preserva convite e exige confirmação.
$newEmail = 'new-' . $suffix . '@example.com';
$service->invite($owner, $projectId, $newEmail, 'member', $mailer, $config['url']);
$newRaw = mailToken($newEmail, '/invitations/accept');
$cookie = ''; openInvitation($newRaw);
$newCsrf = token(request('/register')[1]);
check(request('/register', ['_token' => $newCsrf, 'name' => 'Novo participante', 'email' => $newEmail, 'password' => $password, 'password_confirmation' => $password])[0] === 303, 'Cadastrar convidado');
check(request('/invitations/accept')[0] === 403, 'Convidado exige confirmação');
$verifyRaw = mailToken($newEmail, '/email/verify');
$newCsrf = token(request('/email/verify?token=' . $verifyRaw)[1]);
check(request('/email/verify', ['_token' => $newCsrf, 'token' => $verifyRaw])[0] === 200, 'Confirmar convidado');
check(str_contains(request('/invitations/accept')[1], 'Aceitar convite'), 'Convite preservado no cadastro');
check(request('/invitations/accept', ['_token' => $newCsrf])[0] === 303, 'Aceitar após cadastro');
// Perda de autoridade do remetente invalida pendências.
$service->invite($users['admin']['id'], $projectId, $users['revoked']['email'], 'member', $mailer, $config['url']);
$revokedRaw = mailToken($users['revoked']['email'], '/invitations/accept');
$service->member($owner, $projectId, $users['admin']['id'], 'role', 'member');
check($service->preview($users['revoked']['id'], $revokedRaw) === false, 'Remetente sem permissão');
try { $service->accept($users['revoked']['id'], $revokedRaw); check(false, 'Deveria falhar'); } catch (Pecee\SimpleRouter\Exceptions\HttpException $e) { check($e->getCode() === 422, 'Revalidar remetente no aceite'); }
$service->member($owner, $projectId, $users['admin']['id'], 'role', 'admin');
// Rollback do aceite desfaz vínculo, token e eventos.
$service->invite($owner, $projectId, $users['rollback']['email'], 'member', $mailer, $config['url']);
$rollbackRaw = mailToken($users['rollback']['email'], '/invitations/accept');
$pdo->exec("CREATE TRIGGER test_reject_member_log BEFORE INSERT ON activity_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'forced failure'");
try {
    try { $service->accept($users['rollback']['id'], $rollbackRaw); check(false, 'Falha esperada'); } catch (PDOException) {}
    $targetId = $users['rollback']['id'];
    check((int) $pdo->query("SELECT COUNT(*) FROM project_members WHERE project_id = {$projectId} AND user_id = {$targetId}")->fetchColumn() === 0, 'Rollback do participante');
    check((int) $pdo->query("SELECT COUNT(*) FROM tenant_users WHERE tenant_id = {$tenantId} AND user_id = {$targetId}")->fetchColumn() === 0, 'Rollback do vínculo ao tenant');
    check($service->preview($targetId, $rollbackRaw) !== false, 'Rollback não consome token');
} finally { $pdo->exec('DROP TRIGGER test_reject_member_log'); }
// Dois aceites simultâneos só geram um vínculo e um evento.
$service->invite($owner, $projectId, $users['concurrent']['email'], 'viewer', $mailer, $config['url']);
$concurrentRaw = mailToken($users['concurrent']['email'], '/invitations/accept');
putenv('TEST_INVITATION_TOKEN=' . $concurrentRaw); putenv('TEST_INVITATION_USER=' . $users['concurrent']['id']);
$code = '$c=require "bootstrap/app.php"; $s=new App\\Services\\ProjectMemberService(App\\Infrastructure\\Database::connect($c["database"])); try {$s->accept((int)getenv("TEST_INVITATION_USER"),getenv("TEST_INVITATION_TOKEN")); echo "1";} catch (Pecee\\SimpleRouter\\Exceptions\\HttpException $e) {echo "0";}';
$processes = [];
for ($i = 0; $i < 2; $i++) { $process = proc_open([PHP_BINARY, '-r', $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__)); check(is_resource($process), 'Iniciar processo'); $processes[] = [$process, $pipes]; }
$results = [];
foreach ($processes as [$process, $pipes]) {
    $results[] = stream_get_contents($pipes[1]); $stderr = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    check(proc_close($process) === 0 && $stderr === '', 'Processo concorrente de aceite');
}
sort($results); check($results === ['0', '1'], 'Aceite concorrente de uso único');
putenv('TEST_INVITATION_TOKEN'); putenv('TEST_INVITATION_USER');
// Projeto arquivado não aceita convites ou gerenciamento.
$projectService->change($owner, $projectId, 'archive');
$cookie = $ownerCookie;
check(request($path . '/invitations', ['_token' => $ownerCsrf, 'email' => 'archived@example.com', 'role' => 'member'])[0] === 409, 'Arquivado não convida');
check($service->preview($users['rollback']['id'], $rollbackRaw) === false, 'Arquivado não aceita');
$projectService->change($owner, $projectId, 'restore');
// Falha na auditoria deve reverter transferência e remoção integralmente.
$pdo->exec("CREATE TRIGGER test_reject_member_log BEFORE INSERT ON activity_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'forced failure'");
try {
    try { $service->member($owner, $projectId, $users['admin']['id'], 'transfer'); check(false, 'Falha esperada'); } catch (PDOException) {}
    $sameProject = (new App\Repositories\ProjectRepository($pdo))->find($owner, $projectId);
    check((int) $sameProject['owner_id'] === $owner && $sameProject['role'] === 'owner', 'Rollback preserva proprietário');
    check((new App\Repositories\ProjectRepository($pdo))->find($users['admin']['id'], $projectId)['role'] === 'admin', 'Rollback preserva papéis');
    try { $service->member($owner, $projectId, $users['concurrent']['id'], 'remove'); check(false, 'Falha esperada'); } catch (PDOException) {}
    check((new App\Repositories\ProjectRepository($pdo))->find($users['concurrent']['id'], $projectId)['role'] === 'viewer', 'Rollback de remoção');
} finally { $pdo->exec('DROP TRIGGER test_reject_member_log'); }
// Transferência é exclusiva do proprietário e preserva o tenant.
check(request($path . '/transfer', ['_token' => $ownerCsrf, 'user_id' => $users['admin']['id']])[0] === 422, 'Confirmação explícita da transferência');
check(request($path . '/transfer', ['_token' => $ownerCsrf, 'user_id' => $users['admin']['id'], 'confirm' => 'transfer'])[0] === 303, 'Transferir propriedade');
$newProject = (new App\Repositories\ProjectRepository($pdo))->find($users['admin']['id'], $projectId);
check((int) $newProject['owner_id'] === $users['admin']['id'] && (int) $newProject['tenant_id'] === $tenantId && $newProject['role'] === 'owner', 'Novo proprietário, mesmo tenant');
check((new App\Repositories\ProjectRepository($pdo))->find($owner, $projectId)['role'] === 'admin', 'Antigo proprietário vira admin');
check((int) $pdo->query("SELECT COUNT(*) FROM project_members WHERE project_id = {$projectId} AND role = 'owner'")->fetchColumn() === 1, 'Exatamente um proprietário');
check(request($path . '/archive', ['_token' => $ownerCsrf])[0] === 403, 'Antigo proprietário perde ações exclusivas');
// Falha SMTP não concede acesso e permite reenvio posterior.
$failedMail = $config['mail']; $failedMail['host'] = '127.0.0.1'; $failedMail['port'] = 1;
$failedEmail = 'failed-' . $suffix . '@example.com';
$log = tempnam(sys_get_temp_dir(), 'kanban-invite-test-'); $previousLog = ini_get('error_log');
try {
    ini_set('error_log', $log);
    check(!$service->invite($users['admin']['id'], $projectId, $failedEmail, 'member', new App\Services\Mailer($failedMail), $config['url']), 'Retornar falha SMTP');
    $content = file_get_contents($log);
    check(str_contains($content, 'Falha no envio SMTP de convite') && !str_contains($content, $failedEmail) && !str_contains($content, 'token='), 'Log SMTP sem dados sensíveis');
} finally { ini_set('error_log', $previousLog); unlink($log); }
// Auditoria não deve conter token ou hash.
$logs = $pdo->query("SELECT action, before_data, after_data FROM activity_logs WHERE project_id = {$projectId}")->fetchAll();
$actions = array_column($logs, 'action');
foreach (['invitation.created', 'invitation.reissued', 'invitation.cancelled', 'invitation.accepted', 'member.added', 'member.role_changed', 'member.removed', 'project.ownership_transferred'] as $action) { check(in_array($action, $actions, true), 'Evento de auditoria obrigatório'); }
$serialized = json_encode($logs);
check(!str_contains($serialized, $raw) && !str_contains($serialized, hash('sha256', $raw)), 'Auditoria sem tokens');
echo "OK: convites SMTP, aceite/cadastro, CSRF, isolamento, papéis, revogação, expiração, concorrência, rollback e transferência.\n";
