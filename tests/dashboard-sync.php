<?php

declare(strict_types=1);

require __DIR__ . '/board-http.php';

use App\Services\AuthService;
use App\Services\BoardService;
use App\Services\ProjectService;
use App\Services\ProjectMemberService;
use App\Services\ReportingService;

$password = 'Senha-dashboard-sync-123';
$auth = new AuthService($pdo); $projects = new ProjectService($pdo); $boards = new BoardService($pdo); $reports = new ReportingService($pdo);
$suffix = bin2hex(random_bytes(6)); $users = [];
foreach (['owner', 'member', 'outsider', 'pending'] as $role) {
    $email = "dashboard-sync-{$role}-{$suffix}@example.com";
    $id = $auth->register($role, $email, $password);
    if ($role !== 'pending') $pdo->prepare('UPDATE users SET email_verified_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$id]);
    $users[$role] = compact('id', 'email');
}
$owner = $users['owner']['id']; $member = $users['member']['id'];
$project = $projects->create($owner, ['name' => 'Dashboard compartilhado', 'description' => '', 'starts_on' => '', 'due_on' => '']);
$tenant = $pdo->query("SELECT tenant_id FROM projects WHERE id = {$project}")->fetchColumn();
$pdo->prepare('INSERT INTO tenant_users (tenant_id, user_id) VALUES (?, ?)')->execute([$tenant, $member]);
$pdo->prepare("INSERT INTO project_members (tenant_id, project_id, user_id, role) VALUES (?, ?, ?, 'member')")->execute([$tenant, $project, $member]);
foreach (['version', 'state'] as $mode) check(request('/dashboard?sync=' . $mode)[0] === 401, 'Sync exige login');
boardLogin($users['pending']['email']);
check(request('/dashboard?sync=state')[0] === 403, 'Sync exige confirmação');
boardLogin($users['member']['email']);
function dashboardSync(string $mode = 'version', string $query = ''): array {
    global $tenant;
    [$status, $body, $headers] = request('/workspaces/'.$tenant.'/dashboard?sync=' . $mode . $query);
    check($status === 200 && str_contains($headers, 'no-store'), 'JSON sem cache');
    return json_decode($body, true, flags: JSON_THROW_ON_ERROR);
}
$initial = dashboardSync();
check(array_keys($initial) === ['version'] && $initial === dashboardSync(), 'Versão estável sem HTML');
$outsiderVersion = $reports->dashboardVersion($users['outsider']['id'], 1);
$board = $boards->state($owner, $project);
$board = $boards->mutate($owner, $project, $board['version'], 'task.create', ['column_id' => $board['columns'][0]['id'], 'title' => '<script>syncXss=true</script>']);
$task = $board['columns'][0]['tasks'][0]['id'];
$board = $boards->mutate($owner, $project, $board['version'], 'task.update', ['task_id' => $task, 'title' => '<script>syncXss=true</script>', 'description' => '', 'priority' => 'normal', 'starts_on' => '', 'started_at' => null, 'due_on' => gmdate('Y-m-d'), 'assignee_ids' => [$member]]);
check($initial['version'] !== dashboardSync()['version'], 'Mutação por outro participante invalida versão');
check($outsiderVersion === $reports->dashboardVersion($users['outsider']['id'], 1), 'Projeto alheio não invalida versão');
$state = dashboardSync('state', '&user_id=' . $owner);
check(str_contains($state['html'], 'data-metric="pending">1<') && str_contains($state['html'], '&lt;script&gt;syncXss=true&lt;/script&gt;') && !str_contains($state['html'], '<script>'), 'Contagem pessoal, prazo e Twig escapado');
check($state['version'] === dashboardSync()['version'], 'Estado acompanha versão');
check(request('/dashboard?sync=state&page[]=1')[0] === 422, 'Paginação inválida rejeitada');
check(dashboardSync('version', '&page=2')['version'] !== $state['version'], 'Versão inclui página solicitada');
$boards->mutate($owner, $project, $board['version'], 'task.move', ['task_id' => $task, 'column_id' => $board['columns'][4]['id'], 'position' => 0]);
check(str_contains(dashboardSync('state')['html'], 'data-metric="completed">1<'), 'Conclusão atualiza indicador e progresso');
$beforeAuthor = dashboardSync()['version'];
$pdo->prepare('UPDATE users SET name = ? WHERE id = ?')->execute(['Autor atualizado', $owner]);
check($beforeAuthor !== dashboardSync()['version'] && str_contains(dashboardSync('state')['html'], 'Autor atualizado'), 'Nome do autor invalida atividades');
$extraProjects = [];
for ($i = 0; $i < 10; $i++) $extraProjects[] = (new ProjectService($pdo,new App\Services\WorkspaceContext($member,(int)$tenant)))->create($member, ['name' => 'Paginação sync ' . $i, 'description' => '', 'starts_on' => '', 'due_on' => '']);
$pageTwo = dashboardSync('state', '&page=2');
check(str_contains($pageTwo['html'], 'Página 2 de 2') && str_contains($pageTwo['html'], 'Dashboard compartilhado'), 'Estado mantém segunda página de progresso');
foreach ($extraProjects as $extra) $projects->change($member, $extra, 'archive');
check(!str_contains(dashboardSync('state', '&page=2')['html'], 'Página 2 de 2'), 'Página é limitada novamente após arquivamento');
$projects->change($owner, $project, 'archive');
check(str_contains(dashboardSync('state')['html'], 'data-metric="active_projects">0<'), 'Arquivamento remove progresso ativo');
$projects->change($owner, $project, 'restore');
(new ProjectMemberService($pdo))->member($owner, $project, $member, 'remove');
check(!str_contains(dashboardSync('state')['html'], 'Dashboard compartilhado'), 'Remoção revoga fragmentos');
$pdo->prepare('UPDATE users SET session_version = session_version + 1 WHERE id = ?')->execute([$member]);
check(request('/dashboard?sync=state')[0] === 401, 'Sessão revogada interrompe sync');
// Fixture independente para validação direcionada em duas sessões de navegador.
$fixtureProject = $projects->create($owner, ['name' => 'Dashboard navegador', 'description' => '', 'starts_on' => '', 'due_on' => '']);
$pdo->prepare("INSERT INTO project_members (tenant_id, project_id, user_id, role) VALUES (?, ?, ?, 'member')")->execute([$tenant, $fixtureProject, $member]);
file_put_contents('/tmp/kanban-dashboard-sync.json', json_encode(['workspaceId' => (int)$tenant, 'users' => $users, 'project' => $fixtureProject, 'password' => $password], JSON_THROW_ON_ERROR));
echo "OK: versão/estado, mutação remota, indicadores/progresso, XSS, paginação, arquivamento, isolamento, remoção e sessão revogada.\n";
