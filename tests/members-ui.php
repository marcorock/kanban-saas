<?php

declare(strict_types=1);
require __DIR__ . '/http.php';
$auth = new App\Services\AuthService($pdo); $projects = new App\Services\ProjectService($pdo);
$password = 'Senha-equipe-ui-segura-123'; $suffix = bin2hex(random_bytes(5)); $users = [];
foreach (['owner' => 'Marina Costa', 'admin' => 'Bruno Lima', 'member' => 'Luiza Martins', 'viewer' => 'Rafael Alves', 'recipient' => '<script>window.memberXss=true</script>', 'wrong' => 'Outra conta'] as $role => $name) {
    $email = 'team-ui-' . $role . '-' . $suffix . '@example.com'; $id = $auth->register($name, $email, $password);
    $pdo->prepare('UPDATE users SET email_verified_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$id]);
    $users[$role] = ['id' => $id, 'email' => $email];
}
$ids = [];
foreach (['active', 'archived'] as $state) {
    $project = $projects->create($users['owner']['id'], ['name' => 'Lançamento do site', 'description' => '', 'starts_on' => '', 'due_on' => '']);
    $ids[$state] = $project;
    $tenant = (new App\Repositories\ProjectRepository($pdo))->find($users['owner']['id'], $project)['tenant_id'];
    foreach (['admin', 'member', 'viewer'] as $role) {
        $id = $users[$role]['id'];
        $pdo->prepare('INSERT INTO tenant_users (tenant_id, user_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)')->execute([$tenant, $id]);
        $pdo->prepare('INSERT INTO project_members (tenant_id, project_id, user_id, role) VALUES (?, ?, ?, ?)')->execute([$tenant, $project, $id, $role]);
    }
    if ($state === 'archived') $projects->change($users['owner']['id'], $project, 'archive');
}
$members = new App\Services\ProjectMemberService($pdo);
$pending = 'convite-' . $suffix . '@example.com';
$members->invite($users['owner']['id'], $ids['active'], $pending, 'admin', new App\Services\Mailer($config['mail']), $config['url']);
$directory = dirname(__DIR__) . '/storage/previews'; if (!is_dir($directory)) mkdir($directory, 0775, true);
file_put_contents($directory . '/members-fixture.json', json_encode(['users' => $users, 'projects' => $ids, 'pending' => $pending], JSON_THROW_ON_ERROR));
echo "OK: fixture de equipe/convites descartável preparada para Chromium.\n";
