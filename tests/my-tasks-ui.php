<?php

declare(strict_types=1);
require __DIR__ . '/http.php';
$auth = new App\Services\AuthService($pdo);
$projects = new App\Services\ProjectService($pdo);
$boards = new App\Services\BoardService($pdo);
$password = 'Senha-minhas-tarefas-123'; $suffix = bin2hex(random_bytes(5)); $users = [];
foreach (['owner' => 'Marina Costa', 'viewer' => 'Bruno Lima', 'empty' => 'Conta sem tarefas', 'outside' => 'Outra conta'] as $role => $name) {
    $email = 'my-tasks-' . $role . '-' . $suffix . '@example.com'; $id = $auth->register($name, $email, $password);
    $pdo->prepare('UPDATE users SET email_verified_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$id]);
    $users[$role] = ['id' => $id, 'email' => $email];
}
$ids = []; $tasks = [];
foreach (['active' => 'Lançamento do site', 'archived' => 'Acervo de entregas', 'private' => 'Projeto restrito'] as $state => $name) {
    $actor = $users[$state === 'private' ? 'outside' : 'owner']['id'];
    $projectId = $projects->create($actor, ['name' => $name, 'description' => '', 'starts_on' => '', 'due_on' => '']); $ids[$state] = $projectId;
    if ($state === 'active') {
        $tenant = (new App\Repositories\ProjectRepository($pdo))->find($actor, $projectId)['tenant_id'];
        $pdo->prepare('INSERT INTO tenant_users (tenant_id, user_id) VALUES (?, ?)')->execute([$tenant, $users['viewer']['id']]);
        $pdo->prepare("INSERT INTO project_members (tenant_id, project_id, user_id, role) VALUES (?, ?, ?, 'viewer')")->execute([$tenant, $projectId, $users['viewer']['id']]);
    }
    $board = $boards->state($actor, $projectId);
    for ($index = 0; $index < ($state === 'active' ? 30 : 1); $index++) {
        $title = match ($index) {0 => 'Organizar a entrega', 1 => 'Revisar os textos', 2 => '<script>window.myTasksXss=true</script> ' . str_repeat('Título longo ', 9), 28 => 'Tarefa de outro participante', 29 => 'Tarefa sem atribuição', default => 'Preparar etapa ' . $index};
        $board = $boards->mutate($actor, $projectId, $board['version'], 'task.create', ['column_id' => $board['columns'][0]['id'], 'title' => $title]);
        $task = end($board['columns'][0]['tasks']); $taskId = $task['id']; $tasks[$state][] = $taskId;
        $board = $boards->mutate($actor, $projectId, $board['version'], 'task.update', [
            'task_id' => $taskId, 'title' => $title, 'description' => '', 'priority' => $index === 1 ? 'urgent' : ($index === 3 ? 'high' : 'normal'),
            'due_on' => $index === 1 ? '2000-01-01' : ($index === 3 ? gmdate('Y-m-d', time() + 2 * 86400) : ''),
            'started_at' => $index === 1 ? gmdate('Y-m-d H:i:s', time() - 3600) : '',
            'assignee_ids' => $index === 29 ? [] : [$index === 28 ? $users['viewer']['id'] : $actor],
        ]);
        if ($index === 0 && $state === 'active') $board = $boards->mutate($actor, $projectId, $board['version'], 'task.move', ['task_id' => $taskId, 'column_id' => $board['columns'][4]['id'], 'position' => 0]);
    }
    if ($state === 'archived') $projects->change($actor, $projectId, 'archive');
}
$directory = dirname(__DIR__) . '/storage/previews'; if (!is_dir($directory)) mkdir($directory, 0775, true);
file_put_contents($directory . '/my-tasks-fixture.json', json_encode(['users' => $users, 'projects' => $ids, 'tasks' => $tasks], JSON_THROW_ON_ERROR));
echo "OK: fixture descartável de Minhas Tarefas, atribuições, papéis, arquivados, paginação e XSS.\n";
