<?php

declare(strict_types=1);

require __DIR__ . '/board-http.php';

$pdo->exec('UPDATE auth_rate_limits SET expires_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 SECOND)');
$password = 'Senha-detalhes-segura-123';
$auth = new App\Services\AuthService($pdo);
$projectService = new App\Services\ProjectService($pdo);
$service = new App\Services\BoardService($pdo);
$memberService = new App\Services\ProjectMemberService($pdo);
$suffix = bin2hex(random_bytes(6)); $users = [];
foreach (['owner', 'member', 'viewer', 'outside', 'pending'] as $name) {
    $email = 'tasks-' . $name . '-' . $suffix . '@example.com'; $id = $auth->register($name, $email, $password);
    if ($name !== 'pending') $pdo->prepare('UPDATE users SET email_verified_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$id]);
    $users[$name] = ['id' => $id, 'email' => $email];
}
$ownerId = $users['owner']['id'];
$projectId = $projectService->create($ownerId, ['name' => 'Detalhes', 'description' => '', 'starts_on' => '', 'due_on' => '']);
$otherProject = $projectService->create($ownerId, ['name' => 'Outro projeto', 'description' => '', 'starts_on' => '', 'due_on' => '']);
$project = (new App\Repositories\ProjectRepository($pdo))->find($ownerId, $projectId); $tenantId = (int) $project['tenant_id'];
foreach (['member', 'viewer', 'outside', 'pending'] as $role) {
    $pdo->prepare('INSERT INTO tenant_users (tenant_id, user_id) VALUES (?, ?)')->execute([$tenantId, $users[$role]['id']]);
    if ($role !== 'outside') $pdo->prepare('INSERT INTO project_members (tenant_id, project_id, user_id, role) VALUES (?, ?, ?, ?)')->execute([$tenantId, $projectId, $users[$role]['id'], $role === 'pending' ? 'member' : $role]);
}
$state = $service->state($ownerId, $projectId); $columnId = $state['columns'][0]['id'];
$csrf = boardLogin($users['owner']['email']); $ownerCookie = $cookie; $ownerCsrf = $csrf;
mutateBoard('task.create', ['column_id' => $columnId, 'title' => 'Detalhes A']); $taskId = $state['columns'][0]['tasks'][0]['id'];
mutateBoard('task.create', ['column_id' => $columnId, 'title' => 'Detalhes B']); $otherTask = $state['columns'][0]['tasks'][1]['id'];
function details(int $task, array $pages = [], ?int $project = null, int $expected = 200): array {
    global $projectId;
    [$status, $body] = request('/projects/' . ($project ?? $projectId) . '/tasks/' . $task . '?' . http_build_query($pages));
    check($status === $expected, 'Leitura de detalhes: esperado ' . $expected . ', recebido ' . $status);
    return json_decode($body, true, flags: JSON_THROW_ON_ERROR);
}
$data = ['task_id' => $taskId, 'title' => 'Tarefa <script>teste</script>', 'description' => 'Descrição com <b>texto</b>', 'priority' => 'urgent', 'starts_on' => '2000-01-01', 'started_at' => '2000-01-02 12:34:56', 'due_on' => '2000-01-10', 'assignee_ids' => [$ownerId, $users['member']['id']]];
mutateBoard('task.update', $data);
$read = details($taskId); $task = $read['details']['task'];
check($task['description'] === $data['description'] && $task['priority'] === 'urgent' && $task['started_at'] === $data['started_at'], 'Persistência dos detalhes');
check($task['assignee_ids'] === $data['assignee_ids'] && $read['board']['columns'][0]['tasks'][0]['overdue'], 'Múltiplos responsáveis e atraso');
check(!str_contains(json_encode($read), $users['member']['email']), 'Não expor e-mails');
foreach (['outside', 'pending'] as $invalid) { $bad = $data; $bad['assignee_ids'] = [$users[$invalid]['id']]; mutateBoard('task.update', $bad, 422); }
foreach (['priority' => 'unknown', 'starts_on' => '2000-02-30', 'due_on' => '1999-12-31', 'started_at' => '9999-01-01 00:00:00', 'description' => str_repeat('x', 20001)] as $key => $value) { $bad = $data; $bad[$key] = $value; mutateBoard('task.update', $bad, 422); }
$bad = $data; $bad['assignee_ids'] = [$ownerId, $ownerId]; mutateBoard('task.update', $bad, 422);
$bad['assignee_ids'] = [(string) $ownerId]; mutateBoard('task.update', $bad, 422);
$bad = $data; $bad['completed_at'] = '1990-01-01 00:00:00'; $bad['tenant_id'] = 99999; $bad['created_by'] = $users['outside']['id']; mutateBoard('task.update', $bad);
check(details($taskId)['details']['task']['completed_at'] === null && details($taskId)['details']['task']['created_by'] === $ownerId, 'Ignorar campos de autoridade e conclusão');
mutateBoard('checklist.create', ['task_id' => $taskId, 'title' => 'Lista A']);
$listId = details($taskId)['details']['checklists'][0]['id'];
mutateBoard('item.create', ['task_id' => $taskId, 'checklist_id' => $listId, 'title' => 'Primeira subtarefa']);
$itemId = details($taskId)['details']['checklists'][0]['items'][0]['id'];
mutateBoard('item.update', ['task_id' => $taskId, 'item_id' => $itemId, 'title' => 'Item pronto', 'is_done' => true]);
check(details($taskId)['details']['checklists'][0]['items'][0]['is_done'], 'Concluir item');
mutateBoard('checklist.update', ['task_id' => $taskId, 'checklist_id' => $listId, 'title' => 'Lista renomeada']);
mutateBoard('checklist.create', ['task_id' => $otherTask, 'title' => 'Lista B']);
$otherList = details($otherTask)['details']['checklists'][0]['id'];
mutateBoard('item.create', ['task_id' => $taskId, 'checklist_id' => $otherList, 'title' => 'IDOR'], 404);
mutateBoard('checklist.delete', ['task_id' => $taskId, 'checklist_id' => $otherList, 'confirm' => true], 404);
mutateBoard('item.update', ['task_id' => $otherTask, 'item_id' => $itemId, 'title' => 'IDOR', 'is_done' => false], 404);
mutateBoard('comment.create', ['task_id' => $taskId, 'body' => 'Comentário <script>alert(1)</script>']);
$commentId = (int) details($taskId)['details']['comments']['items'][0]['id'];
mutateBoard('comment.update', ['task_id' => $otherTask, 'comment_id' => $commentId, 'body' => 'IDOR'], 404);
mutateBoard('comment.update', ['task_id' => $taskId, 'comment_id' => $commentId, 'body' => 'Comentário editado']);
$csrf = boardLogin($users['member']['email']); $memberCookie = $cookie; $memberCsrf = $csrf;
mutateBoard('comment.update', ['task_id' => $taskId, 'comment_id' => $commentId, 'body' => 'Proibido'], 403);
mutateBoard('comment.delete', ['task_id' => $taskId, 'comment_id' => $commentId, 'confirm' => true], 403);
mutateBoard('comment.create', ['task_id' => $taskId, 'body' => 'Comentário do membro']);
$memberComment = (int) details($taskId)['details']['comments']['items'][0]['id'];
$csrf = boardLogin($users['viewer']['email']);
check(details($taskId)['details']['task']['title'] === $data['title'], 'Visualizador pode consultar detalhes');
mutateBoard('comment.create', ['task_id' => $taskId, 'body' => 'Proibido'], 403);
mutateBoard('task.update', $data, 403);
$csrf = boardLogin($users['outside']['email']);
details($taskId, [], null, 404);
mutateBoard('task.update', $data, 404);
$csrf = boardLogin($users['pending']['email']);
details($taskId, [], null, 403);
mutateBoard('task.update', $data, 403);
$cookie = $ownerCookie; $csrf = $ownerCsrf;
details($taskId, [], $otherProject, 404);
mutateBoard('comment.update', ['task_id' => $taskId, 'comment_id' => $memberComment, 'body' => 'Admin não edita autor alheio'], 403);
mutateBoard('comment.delete', ['task_id' => $taskId, 'comment_id' => $memberComment, 'confirm' => true]);
check((int) $pdo->query("SELECT COUNT(*) FROM task_comments WHERE id = {$memberComment} AND deleted_at IS NOT NULL")->fetchColumn() === 1, 'Exclusão lógica do comentário');
mutateBoard('comment.delete', ['task_id' => $taskId, 'comment_id' => $memberComment, 'confirm' => true], 404);
// Falhas de auditoria revertem detalhes, responsáveis, itens, comentários e versão.
$before = $service->task($ownerId, $projectId, $taskId);
$pdo->exec("CREATE TRIGGER test_reject_task_log BEFORE INSERT ON activity_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'forced failure'");
try {
    $bad = $data; $bad['title'] = 'Não persistir'; $bad['assignee_ids'] = [$ownerId]; mutateBoard('task.update', $bad, 500);
    mutateBoard('item.update', ['task_id' => $taskId, 'item_id' => $itemId, 'title' => 'Não persistir', 'is_done' => false], 500);
    mutateBoard('comment.create', ['task_id' => $taskId, 'body' => 'Não persistir'], 500);
    check($service->task($ownerId, $projectId, $taskId) === $before, 'Rollback integral dos detalhes');
    try { $memberService->member($ownerId, $projectId, $users['member']['id'], 'remove'); check(false, 'Falha esperada'); } catch (PDOException) {}
    check($service->task($ownerId, $projectId, $taskId) === $before, 'Rollback de remoção preserva atribuições e versão');
} finally { $pdo->exec('DROP TRIGGER test_reject_task_log'); }
$oldVersion = $state['version'];
$memberService->member($ownerId, $projectId, $users['member']['id'], 'remove');
$state = $service->state($ownerId, $projectId);
check(details($taskId)['details']['task']['assignee_ids'] === [$ownerId], 'Remoção limpa atribuições');
check($state['version'] > $oldVersion, 'Remoção invalida versões abertas');
mutateBoard('task.update', $data, 409, $oldVersion);
mutateBoard('task.update', $data, 422);
$cookie = $memberCookie; details($taskId, [], null, 404); $cookie = $ownerCookie;
// Confirmação obrigatória nas exclusões; estado arquivado bloqueia escrita.
mutateBoard('item.delete', ['task_id' => $taskId, 'item_id' => $itemId], 422);
mutateBoard('item.delete', ['task_id' => $taskId, 'item_id' => $itemId, 'confirm' => true]);
mutateBoard('checklist.delete', ['task_id' => $taskId, 'checklist_id' => $listId], 422);
mutateBoard('checklist.delete', ['task_id' => $taskId, 'checklist_id' => $listId, 'confirm' => true]);
check(details($taskId)['details']['checklists'] === [], 'Excluir lista');
$projectService->change($ownerId, $projectId, 'archive');
check(!details($taskId)['board']['can_write'], 'Arquivado somente leitura');
mutateBoard('comment.create', ['task_id' => $taskId, 'body' => 'Arquivado'], 409);
$projectService->change($ownerId, $projectId, 'restore');
// Comentários e histórico têm paginação independente.
for ($i = 0; $i < 21; $i++) $state = $service->mutate($ownerId, $projectId, $state['version'], 'comment.create', ['task_id' => $taskId, 'body' => 'Página ' . $i]);
$read = details($taskId, ['comments_page' => 2, 'activity_page' => 2]);
check($read['details']['comments']['page'] === 2 && count($read['details']['comments']['items']) === 2 && $read['details']['activity']['page'] === 2, 'Paginação dos detalhes');
$actions = $pdo->query("SELECT action FROM activity_logs WHERE task_id = {$taskId}")->fetchAll(PDO::FETCH_COLUMN);
foreach (['task.updated', 'task.assignees_changed', 'checklist.created', 'checklist.updated', 'checklist.deleted', 'checklist.item_created', 'checklist.item_updated', 'checklist.item_deleted', 'comment.created', 'comment.updated', 'comment.deleted'] as $event) check(in_array($event, $actions, true), 'Histórico obrigatório');
$pdo->exec("UPDATE users SET session_version = session_version + 1 WHERE id = {$ownerId}");
details($taskId, [], null, 401);
echo "OK: detalhes, múltiplos responsáveis, datas, checklists, comentários, autoria, IDOR, rollback, paginação e remoção de membro.\n";
