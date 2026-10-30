<?php

declare(strict_types=1);
require __DIR__ . '/http.php';
$auth = new App\Services\AuthService($pdo); $projects = new App\Services\ProjectService($pdo);
$boards = new App\Services\BoardService($pdo); $members = new App\Services\ProjectMemberService($pdo);
$password = 'Senha-historico-ui-123'; $suffix = bin2hex(random_bytes(5)); $users = [];
foreach (['owner' => 'Marina Costa', 'admin' => 'Bruno Lima', 'member' => '<script>window.historyXss=true</script>', 'viewer' => 'Luiza Martins', 'empty' => 'Conta sem atividades', 'outside' => 'Outra conta'] as $role => $name) {
    $email = 'history-ui-' . $role . '-' . $suffix . '@example.com'; $id = $auth->register($name, $email, $password);
    $pdo->prepare('UPDATE users SET email_verified_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$id]); $users[$role] = ['id' => $id, 'email' => $email];
}
$ids = []; $tasks = [];
foreach (['active' => 'Lançamento do site', 'archived' => 'Arquivo de entregas', 'private' => 'Projeto restrito'] as $state => $name) {
    $actor = $users[$state === 'private' ? 'outside' : 'owner']['id'];
    $project = $projects->create($actor, ['name' => $name, 'description' => '', 'starts_on' => '', 'due_on' => '']); $ids[$state] = $project;
    if ($state === 'active') {
        $tenant = (new App\Repositories\ProjectRepository($pdo))->find($actor, $project)['tenant_id'];
        foreach (['admin' => 'admin', 'member' => 'admin', 'viewer' => 'viewer'] as $key => $role) {
            $pdo->prepare('INSERT INTO tenant_users (tenant_id, user_id) VALUES (?, ?)')->execute([$tenant, $users[$key]['id']]);
            $pdo->prepare('INSERT INTO project_members (tenant_id, project_id, user_id, role) VALUES (?, ?, ?, ?)')->execute([$tenant, $project, $users[$key]['id'], $role]);
        }
    }
    $board = $boards->state($actor, $project);
    $board = $boards->mutate($actor, $project, $board['version'], 'task.create', ['column_id' => $board['columns'][0]['id'], 'title' => 'Preparar lançamento']);
    $task = $board['columns'][0]['tasks'][0]['id']; $tasks[$state] = $task;
    for ($index = 0; $index < ($state === 'active' ? 27 : 1); $index++) {
        $board = $boards->mutate($actor, $project, $board['version'], 'task.update', ['task_id' => $task, 'title' => 'Preparar lançamento', 'description' => "<script>window.historyXss=true</script>\n" . str_repeat('TextoSemEspacos', 20) . "\nRevisão " . $index, 'priority' => $index % 2 ? 'high' : 'normal', 'assignee_ids' => [$actor]]);
    }
    if ($state === 'active') {
        $board = $boards->mutate($users['member']['id'], $project, $board['version'], 'comment.create', ['task_id' => $task, 'body' => '<img src=x onerror="window.historyXss=true"> Atualização literal']);
        $board = $boards->mutate($users['admin']['id'], $project, $board['version'], 'comment.create', ['task_id' => $task, 'body' => 'Revisão da equipe']);
        $privateEmail = 'confidencial-' . $suffix . '@example.com';
        $members->invite($users['member']['id'], $project, $privateEmail, 'viewer', new App\Services\Mailer($config['mail']), $config['url']);
        $members->member($actor, $project, $users['member']['id'], 'role', 'member');
    }
    if ($state === 'archived') $projects->change($actor, $project, 'archive');
}
$directory = dirname(__DIR__) . '/storage/previews'; if (!is_dir($directory)) mkdir($directory, 0775, true);
file_put_contents($directory . '/history-fixture.json', json_encode(['users' => $users, 'projects' => $ids, 'tasks' => $tasks, 'private_email' => $privateEmail], JSON_THROW_ON_ERROR));
echo "OK: fixture descartável de históricos, paginação, autores, privacidade, arquivados e XSS.\n";
