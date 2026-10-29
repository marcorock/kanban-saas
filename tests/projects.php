<?php

declare(strict_types=1);

require __DIR__ . '/http.php';

$pdo->exec('UPDATE auth_rate_limits SET expires_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 SECOND)');
$password = 'Senha-projetos-segura-123';
$auth = new App\Services\AuthService($pdo);
$suffix = bin2hex(random_bytes(6));
$ownerEmail = 'owner-' . $suffix . '@example.com';
$otherEmail = 'other-' . $suffix . '@example.com';
$unverifiedEmail = 'pending-' . $suffix . '@example.com';
$ownerId = $auth->register('Proprietário', $ownerEmail, $password);
$otherId = $auth->register('Outro usuário', $otherEmail, $password);
$pendingId = $auth->register('Sem confirmação', $unverifiedEmail, $password);
$pdo->prepare('UPDATE users SET email_verified_at = UTC_TIMESTAMP() WHERE id IN (?, ?)')->execute([$ownerId, $otherId]);
check(request('/projects')[0] === 303, 'Listagem exige login');
check(request('/projects', ['name' => 'Inválido'])[0] === 303, 'Criação exige login');
$csrf = token(request('/login')[1]);
check(request('/login', ['_token' => $csrf, 'email' => $ownerEmail, 'password' => $password])[0] === 303, 'Login do proprietário');
[$status, $html] = request('/projects/new');
check($status === 200, 'Formulário de criação');
$csrf = token($html);
check(request('/projects', ['name' => 'Sem CSRF'])[0] === 419, 'CSRF obrigatório');
$data = ['_token' => $csrf, 'name' => '<script>Projeto</script>', 'description' => 'Descrição <b>texto</b>', 'starts_on' => '2026-10-29', 'due_on' => '2026-11-30'];
$bad = $data; $bad['name'] = [];
check(request('/projects', $bad)[0] === 422, 'Rejeitar campos em array');
$bad = $data; $bad['name'] = '  ';
check(request('/projects', $bad)[0] === 422, 'Nome obrigatório');
$bad = $data; $bad['description'] = str_repeat('x', 5001);
check(request('/projects', $bad)[0] === 422, 'Limite de descrição');
$bad = $data; $bad['starts_on'] = "2026-01-01\0";
check(request('/projects', $bad)[0] === 422, 'Data malformada');
$bad = $data; $bad['starts_on'] = '2026-02-30';
check(request('/projects', $bad)[0] === 422, 'Data inexistente');
$bad = $data; $bad['due_on'] = '2026-01-01';
check(request('/projects', $bad)[0] === 422, 'Ordem de datas');
$otherTenant = (int) $pdo->query("SELECT id FROM tenants WHERE owner_id = {$otherId}")->fetchColumn();
$data['tenant_id'] = $otherTenant;
$data['owner_id'] = $otherId;
$data['role'] = 'admin';
[$status, , $headers] = request('/projects', $data);
check($status === 303 && preg_match('~Location: /projects/(\d+)~i', $headers, $match), 'Criação e redirecionamento');
$id = (int) $match[1];
$project = (new App\Repositories\ProjectRepository($pdo))->find($ownerId, $id);
$tenantId = (int) $project['tenant_id'];
check($tenantId !== $otherTenant && (int) $project['owner_id'] === $ownerId && $project['role'] === 'owner', 'Ignorar tenant/proprietário fornecidos pelo cliente');
[$status, $html] = request('/projects/' . $id);
check($status === 200 && str_contains($html, '&lt;script&gt;Projeto&lt;/script&gt;') && !str_contains($html, '<script>Projeto'), 'Consulta e escape');
check(request('/projects/abc')[0] === 404 && request('/projects/9999999999999999999')[0] === 404, 'Identificadores inválidos');
check(str_contains(request('/projects')[1], '&lt;script&gt;Projeto&lt;/script&gt;'), 'Projeto listado');
$ownerCookie = $cookie;
$cookie = '';
$otherCsrf = token(request('/login')[1]);
request('/login', ['_token' => $otherCsrf, 'email' => $otherEmail, 'password' => $password]);
$otherCsrf = token(request('/projects/new')[1]);
check(request('/projects/' . $id)[0] === 404, 'Isolamento entre tenants');
check(!str_contains(request('/projects')[1], '&lt;script&gt;Projeto'), 'Não listar projetos alheios');
$pdo->prepare('INSERT INTO tenant_users (tenant_id, user_id) VALUES (?, ?)')->execute([$tenantId, $otherId]);
check(request('/projects/' . $id)[0] === 404, 'Vínculo no tenant não concede projeto');
foreach (['', '/archive', '/restore', '/delete'] as $path) {
    check(request('/projects/' . $id . $path, ['_token' => $otherCsrf, 'confirm' => 'delete'] + $data)[0] === 404, 'IDOR em alterações');
}
$pdo->prepare("INSERT INTO project_members (tenant_id, project_id, user_id, role) VALUES (?, ?, ?, 'viewer')")->execute([$tenantId, $id, $otherId]);
check(request('/projects/' . $id)[0] === 200 && str_contains(request('/projects')[1], 'Compartilhado comigo'), 'Projeto compartilhado acessível');
foreach (['viewer', 'member'] as $role) {
    $pdo->prepare('UPDATE project_members SET role = ? WHERE project_id = ? AND user_id = ?')->execute([$role, $id, $otherId]);
    check(request('/projects/' . $id . '/edit')[0] === 403, 'Membro/visualizador não editam projeto');
    foreach (['', '/archive', '/restore', '/delete'] as $path) {
        check(request('/projects/' . $id . $path, ['_token' => $otherCsrf, 'confirm' => 'delete'] + $data)[0] === 403, 'Proibir alteração por membro/visualizador');
    }
}
$pdo->prepare("UPDATE project_members SET role = 'admin' WHERE project_id = ? AND user_id = ?")->execute([$id, $otherId]);
check(request('/projects/' . $id . '/edit')[0] === 200, 'Administrador pode editar');
$edited = $data; $edited['_token'] = $otherCsrf; $edited['name'] = 'Projeto editado pelo administrador';
check(request('/projects/' . $id, $edited)[0] === 303, 'Atualização por administrador');
foreach (['/archive', '/restore', '/delete'] as $path) {
    check(request('/projects/' . $id . $path, ['_token' => $otherCsrf, 'confirm' => 'delete'])[0] === 403, 'Somente proprietário gerencia estado');
}
$project = (new App\Repositories\ProjectRepository($pdo))->find($ownerId, $id);
check((int) $project['owner_id'] === $ownerId, 'Atualização não transfere propriedade');
$cookie = $ownerCookie;
check(request('/projects/' . $id . '/archive', ['_token' => $csrf])[0] === 303, 'Arquivar');
check(request('/projects/' . $id . '/archive', ['_token' => $csrf])[0] === 303, 'Arquivamento idempotente');
check(!str_contains(request('/projects')[1], 'Projeto editado') && str_contains(request('/projects?status=archived')[1], 'Projeto editado'), 'Filtro de arquivados');
check(request('/projects/' . $id, $data)[0] === 409, 'Arquivado não pode ser editado');
check(request('/projects/' . $id . '/restore', ['_token' => $csrf])[0] === 303, 'Restaurar');
check(request('/projects/' . $id . '/delete', ['_token' => $csrf])[0] === 422, 'Exclusão exige confirmação');
check(request('/projects/' . $id . '/delete', ['_token' => $csrf, 'confirm' => 'delete'])[0] === 303, 'Exclusão lógica');
check(request('/projects/' . $id)[0] === 404, 'Excluído indisponível');
check((int) $pdo->query("SELECT COUNT(*) FROM projects WHERE id = {$id} AND deleted_at IS NOT NULL")->fetchColumn() === 1, 'Preservar registro excluído');
$events = $pdo->query("SELECT action FROM activity_logs WHERE project_id = {$id} AND action LIKE 'project.%' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
check($events === ['project.created', 'project.updated', 'project.archived', 'project.restored', 'project.deleted'], 'Auditoria de todo ciclo sem duplicação');
$statement = $pdo->query("SELECT tenant_id, actor_id, before_data, after_data FROM activity_logs WHERE project_id = {$id} AND action = 'project.updated'");
$event = $statement->fetch();
check((int) $event['tenant_id'] === $tenantId && (int) $event['actor_id'] === $otherId && json_decode($event['before_data'], true)['name'] === $data['name'] && json_decode($event['after_data'], true)['name'] === $edited['name'], 'Auditoria com autor, tenant e valores anteriores/novos');
// Integridade no próprio banco: projeto e participante não podem misturar tenants.
$service = new App\Services\ProjectService($pdo);
$fixture = ['name' => 'Projeto para integridade', 'description' => '', 'starts_on' => '', 'due_on' => ''];
$activeId = $service->create($ownerId, $fixture);
try {
    $pdo->prepare("INSERT INTO project_members (tenant_id, project_id, user_id, role) VALUES (?, ?, ?, 'viewer')")->execute([$otherTenant, $activeId, $otherId]);
    check(false, 'FK deveria rejeitar tenant cruzado');
} catch (PDOException) {}
// Uma falha na auditoria deve desfazer também projeto e vínculo, ou a edição.
$beforeCount = (int) $pdo->query('SELECT COUNT(*) FROM projects')->fetchColumn();
$membersCount = (int) $pdo->query('SELECT COUNT(*) FROM project_members')->fetchColumn();
$pdo->exec("CREATE TRIGGER test_reject_project_log BEFORE INSERT ON activity_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'forced failure'");
try {
    try { $service->create($ownerId, $fixture); check(false, 'Falha esperada'); } catch (PDOException) {}
    check((int) $pdo->query('SELECT COUNT(*) FROM projects')->fetchColumn() === $beforeCount && (int) $pdo->query('SELECT COUNT(*) FROM project_members')->fetchColumn() === $membersCount, 'Rollback de criação e vínculo');
    $changed = $fixture; $changed['name'] = 'Não deve persistir';
    try { $service->change($ownerId, $activeId, 'update', $changed); check(false, 'Falha esperada'); } catch (PDOException) {}
    check((new App\Repositories\ProjectRepository($pdo))->find($ownerId, $activeId)['name'] === $fixture['name'], 'Rollback da edição');
} finally { $pdo->exec('DROP TRIGGER test_reject_project_log'); }
for ($i = 0; $i < 26; $i++) { $fixture['name'] = 'Página projeto ' . $i; $service->create($ownerId, $fixture); }
$result = (new App\Repositories\ProjectRepository($pdo))->page($ownerId, false, 1);
check(count($result['items']) === 25 && $result['pages'] === 2 && $result['total'] === 27, 'Paginação');
check(request('/projects?page=2')[0] === 200 && request('/projects?page[]=invalid')[0] === 200, 'Paginação HTTP');
$cookie = '';
$pendingCsrf = token(request('/login')[1]);
request('/login', ['_token' => $pendingCsrf, 'email' => $unverifiedEmail, 'password' => $password]);
foreach (['/projects', '/projects/new', '/projects/' . $activeId] as $path) { check(request($path)[0] === 403, 'Exigir confirmação nas rotas de projetos'); }
check(request('/projects', ['_token' => $pendingCsrf] + $data)[0] === 403, 'Não criar sem confirmação');
$cookie = $ownerCookie;
$pdo->exec("UPDATE users SET session_version = session_version + 1 WHERE id = {$ownerId}");
check(request('/projects')[0] === 303, 'Revogação de sessão nas rotas novas');
echo "OK: projetos, permissões, isolamento por tenant/projeto, CSRF, validação, paginação, auditoria e rollback.\n";
