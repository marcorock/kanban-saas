<?php

declare(strict_types=1);

require __DIR__ . '/board-http.php';

$pdo->exec('UPDATE auth_rate_limits SET expires_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 SECOND)');
$password = 'Senha-kanban-segura-123';
$auth = new App\Services\AuthService($pdo);
$suffix = bin2hex(random_bytes(6));
$users = [];
foreach (['owner', 'member', 'viewer', 'outsider'] as $name) {
    $email = 'board-' . $name . '-' . $suffix . '@example.com';
    $id = $auth->register($name, $email, $password);
    $pdo->prepare('UPDATE users SET email_verified_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$id]);
    $users[$name] = ['id' => $id, 'email' => $email];
}
$ownerId = $users['owner']['id'];
$projectService = new App\Services\ProjectService($pdo);
$fixture = ['name' => 'Quadro de teste', 'description' => '', 'starts_on' => '', 'due_on' => ''];
$projectId = $projectService->create($ownerId, $fixture);
$otherId = $projectService->create($ownerId, $fixture);
$service = new App\Services\BoardService($pdo);
$state = $service->state($ownerId, $projectId);
$other = $service->state($ownerId, $otherId);
$project = (new App\Repositories\ProjectRepository($pdo))->find($ownerId, $projectId);
$tenantId = (int) $project['tenant_id'];
foreach (['member', 'viewer'] as $role) {
    $pdo->prepare('INSERT INTO tenant_users (tenant_id, user_id) VALUES (?, ?)')->execute([$tenantId, $users[$role]['id']]);
    $pdo->prepare('INSERT INTO project_members (tenant_id, project_id, user_id, role) VALUES (?, ?, ?, ?)')->execute([$tenantId, $projectId, $users[$role]['id'], $role]);
}
check(count($state['columns']) === 5 && $state['columns'][4]['is_done'] === true && $state['version'] === 0, 'Colunas iniciais');
check(request('/projects/' . $projectId . '/board')[0] === 303, 'Quadro exige login');
check(boardRequest($projectId, [])[0] === 401, 'JSON não redireciona quando anônimo');
$csrf = boardLogin($users['owner']['email']);
$ownerCookie = $cookie; $ownerCsrf = $csrf;
check(request('/projects/' . $projectId . '/board')[0] === 200, 'Página Kanban');
check(boardRequest($projectId, ['action' => 'task.create'])[0] === 419, 'CSRF JSON');
check(boardRequest($projectId, [], 'text/plain')[0] === 415, 'Exigir JSON');
check(boardRequest($projectId, [], 'application/json', '{invalid')[0] === 422, 'Rejeitar JSON inválido');
$source = $state['columns'][0]['id']; $destination = $state['columns'][1]['id']; $done = $state['columns'][4]['id'];
mutateBoard('task.create', ['column_id' => $source, 'title' => ['invalid']], 422);
mutateBoard('task.create', ['column_id' => $source, 'title' => str_repeat('x', 201)], 422);
mutateBoard('task.create', ['column_id' => $other['columns'][0]['id'], 'title' => 'Alheia'], 404);
foreach (['A', 'B', 'C'] as $title) { mutateBoard('task.create', ['column_id' => $source, 'title' => $title]); }
$ids = array_column($state['columns'][0]['tasks'], 'id'); [$a, $b, $c] = $ids;
mutateBoard('task.move', ['task_id' => $c, 'column_id' => $source, 'position' => 0]);
check(array_column($state['columns'][0]['tasks'], 'id') === [$c, $a, $b] && array_column($state['columns'][0]['tasks'], 'position') === [0, 1, 2], 'Reordenar na mesma coluna');
mutateBoard('task.move', ['task_id' => $a, 'column_id' => $destination, 'position' => 0]);
check(array_column($state['columns'][0]['tasks'], 'id') === [$c, $b] && array_column($state['columns'][0]['tasks'], 'position') === [0, 1], 'Normalizar coluna de origem');
mutateBoard('task.move', ['task_id' => $a, 'column_id' => $done, 'position' => 0]);
$completed = $state['columns'][4]['tasks'][0]['completed_at']; check(is_string($completed), 'Registrar conclusão real');
mutateBoard('column.create', ['name' => 'Entregue', 'is_done' => true]);
$customDone = $state['columns'][5]['id'];
mutateBoard('task.move', ['task_id' => $a, 'column_id' => $customDone, 'position' => 0]);
check($state['columns'][5]['tasks'][0]['completed_at'] === $completed, 'Mover entre colunas concluídas preserva data');
mutateBoard('task.move', ['task_id' => $a, 'column_id' => $source, 'position' => 1]);
check($state['columns'][0]['tasks'][1]['completed_at'] === null, 'Reabrir tarefa');
mutateBoard('column.update', ['column_id' => $source, 'name' => 'Qualquer nome', 'is_done' => true]);
check(count(array_filter($state['columns'][0]['tasks'], static fn ($task) => $task['completed_at'] !== null)) === 3, 'Flag de conclusão independente do nome');
mutateBoard('column.update', ['column_id' => $source, 'name' => 'Concluído', 'is_done' => false]);
check(count(array_filter($state['columns'][0]['tasks'], static fn ($task) => $task['completed_at'] === null)) === 3, 'Nome não determina conclusão');
mutateBoard('column.delete', ['column_id' => $source], 409);
mutateBoard('task.move', ['task_id' => $a, 'column_id' => $source, 'position' => -1], 422);
mutateBoard('task.move', ['task_id' => $a, 'column_id' => $source, 'position' => 999], 422);
mutateBoard('task.move', ['task_id' => (string) $a, 'column_id' => $source, 'position' => 0], 422);
mutateBoard('task.move', ['task_id' => $a, 'column_id' => $other['columns'][0]['id'], 'position' => 0], 404);
mutateBoard('columns.reorder', ['column_ids' => [$source, $source]], 422);
$ordered = array_reverse(array_column($state['columns'], 'id'));
mutateBoard('columns.reorder', ['column_ids' => $ordered]);
check(array_column($state['columns'], 'id') === $ordered && array_column($state['columns'], 'position') === range(0, 5), 'Persistir ordem das colunas');
$title = '</script><script>alert(1)</script>';
mutateBoard('task.rename', ['task_id' => $a, 'title' => $title]);
$html = request('/projects/' . $projectId . '/board')[1];
check(!str_contains($html, $title) && str_contains($html, '\\u003C'), 'JSON e HTML escapados');
$beforeVersion = $state['version'];
mutateBoard('task.rename', ['task_id' => $a, 'title' => 'Não sobrescrever'], 409, $beforeVersion - 1);
check($service->state($ownerId, $projectId)['version'] === $beforeVersion, 'Conflito não altera versão');
$csrf = boardLogin($users['viewer']['email']);
mutateBoard('task.create', ['column_id' => $source, 'title' => 'Proibido'], 403);
check(!$service->state($users['viewer']['id'], $projectId)['can_write'], 'Visualizador somente leitura');
$csrf = boardLogin($users['member']['email']);
mutateBoard('column.create', ['name' => 'Proibida', 'is_done' => false], 403);
mutateBoard('task.create', ['column_id' => $destination, 'title' => 'Criada pelo membro']);
$csrf = boardLogin($users['outsider']['email']);
check(request('/projects/' . $projectId . '/board')[0] === 404, 'Isolamento de leitura');
mutateBoard('task.rename', ['task_id' => $a, 'title' => 'Proibido'], 404);
$cookie = $ownerCookie; $csrf = $ownerCsrf;
// Falha na auditoria reverte posição, conclusão e versão juntas.
$before = $service->state($ownerId, $projectId);
$pdo->exec("CREATE TRIGGER test_reject_board_log BEFORE INSERT ON activity_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'forced failure'");
try {
    mutateBoard('task.move', ['task_id' => $a, 'column_id' => $done, 'position' => 0], 500);
    check($service->state($ownerId, $projectId) === $before, 'Rollback da movimentação');
    mutateBoard('task.create', ['column_id' => $source, 'title' => 'Não persistir'], 500);
    check($service->state($ownerId, $projectId) === $before, 'Rollback da criação');
} finally { $pdo->exec('DROP TRIGGER test_reject_board_log'); }
// Duas alterações reais disputam a mesma versão: só uma pode vencer.
putenv('TEST_BOARD_PROJECT=' . $projectId); putenv('TEST_BOARD_USER=' . $ownerId); putenv('TEST_BOARD_VERSION=' . $state['version']); putenv('TEST_BOARD_TASK=' . $a);
$code = '$c=require "bootstrap/app.php"; $s=new App\\Services\\BoardService(App\\Infrastructure\\Database::connect($c["database"])); try {$s->mutate((int)getenv("TEST_BOARD_USER"),(int)getenv("TEST_BOARD_PROJECT"),(int)getenv("TEST_BOARD_VERSION"),"task.rename",["task_id"=>(int)getenv("TEST_BOARD_TASK"),"title"=>"Concorrente"]); echo "1";} catch(Pecee\\SimpleRouter\\Exceptions\\HttpException $e){echo $e->getCode()===409?"0":"error";}';
$processes = [];
for ($i = 0; $i < 2; $i++) { $process = proc_open([PHP_BINARY, '-r', $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__)); check(is_resource($process), 'Iniciar concorrência'); $processes[] = [$process, $pipes]; }
$results = [];
foreach ($processes as [$process, $pipes]) { $results[] = stream_get_contents($pipes[1]); $stderr = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); check(proc_close($process) === 0 && $stderr === '', 'Processo concorrente'); }
sort($results); check($results === ['0', '1'], 'Conflito de versão entre processos');
foreach (['TEST_BOARD_PROJECT', 'TEST_BOARD_USER', 'TEST_BOARD_VERSION', 'TEST_BOARD_TASK'] as $key) putenv($key);
$state = $service->state($ownerId, $projectId);
$projectService->change($ownerId, $projectId, 'archive');
check($service->state($ownerId, $projectId)['archived'] && !$service->state($ownerId, $projectId)['can_write'], 'Arquivado somente leitura');
mutateBoard('task.rename', ['task_id' => $a, 'title' => 'Arquivado'], 409);
$projectService->change($ownerId, $projectId, 'restore');
$state = $service->state($ownerId, $projectId);
mutateBoard('column.delete', ['column_id' => $customDone]);
// Integridade referencial impede coluna de outro projeto.
try {
    $pdo->prepare('INSERT INTO tasks (tenant_id, project_id, column_id, created_by, title, position) VALUES (?, ?, ?, ?, ?, 0)')->execute([$tenantId, $projectId, $other['columns'][0]['id'], $ownerId, 'Inválida']);
    check(false, 'FK deveria rejeitar coluna alheia');
} catch (PDOException) {}
foreach (array_slice($other['columns'], 1) as $column) { $other = $service->mutate($ownerId, $otherId, $other['version'], 'column.delete', ['column_id' => $column['id']]); }
try { $service->mutate($ownerId, $otherId, $other['version'], 'column.delete', ['column_id' => $other['columns'][0]['id']]); check(false, 'Última coluna protegida'); }
catch (Pecee\SimpleRouter\Exceptions\HttpException $exception) { check($exception->getCode() === 409, 'Última coluna'); }
$actions = $pdo->query("SELECT action FROM activity_logs WHERE project_id = {$projectId}")->fetchAll(PDO::FETCH_COLUMN);
foreach (['board.initialized', 'column.created', 'column.updated', 'column.deleted', 'columns.reordered', 'task.created', 'task.updated', 'task.moved', 'task.completed', 'task.reopened'] as $action) check(in_array($action, $actions, true), 'Evento do quadro');
check((int) $pdo->query("SELECT COUNT(*) FROM activity_logs WHERE project_id = {$projectId} AND action LIKE 'task.%' AND task_id IS NULL")->fetchColumn() === 0, 'Eventos vinculados à tarefa');
echo "OK: Kanban, ordenação, conclusão/reabertura, permissões, CSRF, isolamento, versão concorrente, rollback e auditoria.\n";
