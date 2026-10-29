<?php

declare(strict_types=1);

require __DIR__ . '/board-http.php';

use App\Services\BoardService;
use App\Services\ProjectService;
use App\Services\ReportingService;
use App\Validators\ReportFilters;

$pdo->exec('UPDATE auth_rate_limits SET expires_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 SECOND)');
$password = 'Senha-relatorios-segura-123';
$auth = new App\Services\AuthService($pdo); $boards = new BoardService($pdo); $projects = new ProjectService($pdo); $reports = new ReportingService($pdo);
$suffix = bin2hex(random_bytes(6)); $users = [];
foreach (['me', 'other', 'viewer', 'pending', 'empty'] as $name) {
    $email = 'reports-' . $name . '-' . $suffix . '@example.com'; $id = $auth->register($name, $email, $password);
    if ($name !== 'pending') $pdo->prepare('UPDATE users SET email_verified_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$id]);
    $users[$name] = ['id' => $id, 'email' => $email];
}
$me = $users['me']['id']; $other = $users['other']['id']; $viewer = $users['viewer']['id'];
function makeProject(int $owner, string $name): int { global $projects; return $projects->create($owner, ['name' => $name, 'description' => '', 'starts_on' => '', 'due_on' => '']); }
function joinProject(int $user, int $owner, int $project, string $role): void {
    global $pdo;
    $tenant = (int) $pdo->query("SELECT tenant_id FROM projects WHERE id = {$project}")->fetchColumn();
    $pdo->prepare('INSERT INTO tenant_users (tenant_id, user_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE tenant_id = VALUES(tenant_id)')->execute([$tenant, $user]);
    $pdo->prepare('INSERT INTO project_members (tenant_id, project_id, user_id, role) VALUES (?, ?, ?, ?)')->execute([$tenant, $project, $user, $role]);
}
function makeTask(int $actor, int $project, string $title, array $assigned, ?string $due = null, bool $completed = false, ?string $started = null, string $priority = 'normal'): int {
    global $boards;
    $board = $boards->state($actor, $project); $column = $board['columns'][$completed ? 4 : 0]['id'];
    $board = $boards->mutate($actor, $project, $board['version'], 'task.create', ['column_id' => $column, 'title' => $title]);
    $id = $board['columns'][$completed ? 4 : 0]['tasks'][count($board['columns'][$completed ? 4 : 0]['tasks']) - 1]['id'];
    $boards->mutate($actor, $project, $board['version'], 'task.update', ['task_id' => $id, 'title' => $title, 'description' => '', 'priority' => $priority, 'starts_on' => '', 'started_at' => $started, 'due_on' => $due, 'assignee_ids' => $assigned]);
    return $id;
}
$a = makeProject($me, 'Projeto A ' . $suffix); $shared = makeProject($other, 'Compartilhado ' . $suffix); $hidden = makeProject($other, 'PRIVADO ' . $suffix);
$archived = makeProject($me, 'Arquivado ' . $suffix); $deleted = makeProject($me, 'EXCLUIDO ' . $suffix); $emptyProject = makeProject($me, 'Vazio ' . $suffix);
joinProject($other, $me, $a, 'member'); joinProject($viewer, $me, $a, 'viewer'); joinProject($me, $other, $shared, 'member'); joinProject($me, $other, $hidden, 'member');
$today = $pdo->query('SELECT UTC_DATE()')->fetchColumn(); $tomorrow = (new DateTimeImmutable($today))->modify('+1 day')->format('Y-m-d');
$past = '2000-01-01';
$late = makeTask($me, $a, 'Atrasada <script>audit-xss</script>', [$me], $past, false, '2000-01-01 00:00:00', 'urgent');
$soon = makeTask($me, $a, 'Prazo próximo ' . $suffix, [$me, $other], $tomorrow);
$done = makeTask($me, $a, 'Finalizada ' . $suffix, [$me], $past, true);
$someoneElse = makeTask($other, $a, 'Só do outro ' . $suffix, [$other]);
$crossTenant = makeTask($me, $shared, 'Tarefa compartilhada ' . $suffix, [$me], $tomorrow);
$hiddenTask = makeTask($me, $hidden, 'SEGREDO ' . $suffix, [$me]);
$archivedTask = makeTask($me, $archived, 'Tarefa arquivada ' . $suffix, [$me], $past);
$deletedTask = makeTask($me, $deleted, 'Tarefa excluída ' . $suffix, [$me], $past);
$projects->change($me, $archived, 'archive'); $projects->change($me, $deleted, 'delete');
(new App\Services\ProjectMemberService($pdo))->member($other, $hidden, $me, 'remove');
$privateEmail = 'convite-privado-' . $suffix . '@example.com';
(new App\Services\ProjectMemberService($pdo))->invite($me, $a, $privateEmail, 'viewer', new App\Services\Mailer($config['mail']), $config['url']);
$dashboard = $reports->dashboard($me, 1);
check($dashboard['counts'] === ['pending' => 3, 'in_progress' => 1, 'completed' => 1, 'overdue' => 1, 'active_projects' => 3], 'Contagens sem duplicação, excluídos ou arquivados');
$progress = array_column($dashboard['projects']['items'], null, 'id');
check($progress[$a]['percentage'] === 25 && (int) $progress[$a]['total'] === 4 && $progress[$emptyProject]['percentage'] === 0, 'Progresso de todos os cards e projeto vazio');
check(count($dashboard['upcoming']) === 2, 'Próximos prazos somente atribuídos');
$tasks = $reports->tasks($me, ReportFilters::parse([]));
check($tasks['total'] === 3 && !in_array($someoneElse, array_column($tasks['items'], 'id')), 'Minhas tarefas pendentes');
check($reports->tasks($me, ReportFilters::parse(['status' => 'completed']))['total'] === 1, 'Filtro concluídas');
check($reports->tasks($me, ReportFilters::parse(['due' => 'overdue']))['total'] === 1, 'Filtro atrasadas');
check($reports->tasks($me, ReportFilters::parse(['due' => 'upcoming']))['total'] === 2, 'Filtro próximos prazos');
check($reports->tasks($me, ReportFilters::parse(['priority' => 'urgent']))['total'] === 1, 'Filtro prioridade');
check($reports->tasks($me, ReportFilters::parse(['archived' => 'archived']))['total'] === 1, 'Filtro arquivadas');
check($reports->tasks($me, ReportFilters::parse(['archived' => 'all', 'status' => 'all']))['total'] === 5, 'Todos os estados sem excluídos');
$personal = $reports->history($me, ReportFilters::parse([], true), true);
check(count(array_unique(array_column($personal['items'], 'actor_id'))) === 1 && (int) $personal['items'][0]['actor_id'] === $me, 'Meu histórico só contém ações próprias');
check(!str_contains(json_encode($personal), 'SEGREDO') && !str_contains(json_encode($personal), 'EXCLUIDO'), 'Histórico não expõe projetos inacessíveis');
$projectHistory = $reports->history($me, ReportFilters::parse(['project_id' => $a], true), false);
check(in_array($other, array_map('intval', array_column($projectHistory['items'], 'actor_id')), true), 'Histórico do projeto contém outros participantes');
$ownerInvites = $reports->history($me, ReportFilters::parse(['project_id' => $a, 'action' => 'invitation.created'], true), false);
check(str_contains(json_encode($ownerInvites), $privateEmail), 'Administração pode inspecionar convite');
$viewerInvites = $reports->history($viewer, ReportFilters::parse(['project_id' => $a, 'action' => 'invitation.created'], true), false);
check($viewerInvites['total'] === 1 && $viewerInvites['items'][0]['restricted'] && !str_contains(json_encode($viewerInvites), $privateEmail), 'Privacidade dos convites no histórico');
// Período inclui o último segundo e exclui o início do dia seguinte.
$event = (int) $pdo->query("SELECT id FROM activity_logs WHERE task_id = {$late} AND action = 'task.updated' LIMIT 1")->fetchColumn();
$pdo->exec("UPDATE activity_logs SET created_at = '2001-01-01 23:59:59' WHERE id = {$event}");
$period = $reports->history($me, ReportFilters::parse(['from' => '2001-01-01', 'to' => '2001-01-01'], true), true);
check($period['total'] === 1 && (int) $period['items'][0]['id'] === $event, 'Período inclusivo em UTC');
// As mesmas proteções devem valer nas páginas, filtros e detalhes de alterações.
check(request('/dashboard')[0] === 303 && request('/tasks')[0] === 303 && request('/history')[0] === 303, 'Relatórios exigem login');
$csrf = boardLogin($users['me']['email']); $meCookie = $cookie;
foreach (['/dashboard', '/tasks', '/history', '/projects/' . $a . '/history'] as $path) check(request($path)[0] === 200, 'Página de relatório funcional');
$html = request('/history?project_id=' . $a . '&action=task.updated')[1];
check(!str_contains($html, '<script>audit-xss</script>') && str_contains($html, '&lt;script&gt;audit-xss&lt;/script&gt;'), 'Escape de snapshots e títulos');
check(request('/tasks?project_id=' . $hidden)[0] === 404 && request('/history?project_id=' . $hidden)[0] === 404 && request('/projects/' . $hidden . '/history')[0] === 404, 'IDOR nos filtros e histórico');
foreach (['/history?action[]=task.created', '/tasks?project_id[]=1', '/tasks?priority=invalid', '/history?from=2000-02-30', '/history?from=2001-01-02&to=2001-01-01', '/tasks?page=0'] as $path) check(request($path)[0] === 422, 'Rejeitar filtros malformados');
check(!str_contains(request('/tasks?user_id=' . $other)[1], 'Só do outro'), 'Ignorar identidade forjada');
$csrf = boardLogin($users['viewer']['email']);
check(request('/projects/' . $a . '/history')[0] === 200 && !str_contains(request('/projects/' . $a . '/history')[1], $privateEmail), 'Histórico HTTP preserva privacidade');
check($reports->dashboard($viewer, 1)['counts']['pending'] === 0, 'Visualizador sem atribuições não herda contagens');
$csrf = boardLogin($users['pending']['email']);
foreach (['/dashboard', '/tasks', '/history', '/projects/' . $a . '/history'] as $path) check(request($path)[0] === 403, 'Relatórios exigem confirmação');
$cookie = $meCookie;
for ($i = 0; $i < 26; $i++) makeTask($me, $a, 'Paginação ' . $i, [$me]);
$paginated = $reports->tasks($me, ReportFilters::parse([]));
check($paginated['total'] === 29 && count($paginated['items']) === 25 && $paginated['pages'] === 2, 'Paginação de tarefas');
check(count($reports->tasks($me, ReportFilters::parse(['page' => '2']))['items']) === 4, 'Segunda página');
check(request('/tasks?page=2')[0] === 200 && request('/history?page=2')[0] === 200, 'Paginação HTTP');
for ($i = 0; $i < 11; $i++) makeProject($me, 'Página projeto ' . $i);
check($reports->dashboard($me, 2)['projects']['pages'] === 2 && count($reports->dashboard($me, 2)['projects']['items']) === 4, 'Paginação de progresso');
(new App\Services\ProjectMemberService($pdo))->member($other, $shared, $me, 'remove');
check(!str_contains(request('/tasks')[1], 'Tarefa compartilhada') && !str_contains(request('/history')[1], 'Tarefa compartilhada'), 'Remoção revoga relatórios');
check(request('/projects/' . $shared . '/history')[0] === 404, 'Revogar histórico de projeto');
$empty = $reports->dashboard($users['empty']['id'], 1);
check(array_sum($empty['counts']) === 0 && $empty['recent'] === [] && $empty['upcoming'] === [], 'Conta vazia sem vazamento');
$pdo->exec("UPDATE users SET session_version = session_version + 1 WHERE id = {$me}");
check(request('/history')[0] === 303 && request('/dashboard')[0] === 303, 'Revogação da sessão nos relatórios');
echo "OK: dashboard, métricas, tarefas/históricos consolidados, filtros, paginação, privacidade, XSS e revogação de acesso.\n";
