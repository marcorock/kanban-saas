<?php

declare(strict_types=1);
require __DIR__ . '/http.php';
$pdo->exec('UPDATE auth_rate_limits SET expires_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 SECOND)');
$auth = new App\Services\AuthService($pdo);
$service = new App\Services\ProjectService($pdo);
$password = 'Senha-listagem-segura-123';
$suffix = bin2hex(random_bytes(5)); $users = [];
foreach (['owner' => 'Marina Costa', 'other' => 'Bruno Lima', 'empty' => 'Conta nova', 'pager' => 'Paginação', 'xss' => 'Teste de texto'] as $key => $name) {
    $email = 'projects-ui-' . $key . '-' . $suffix . '@example.com'; $id = $auth->register($name, $email, $password);
    $pdo->prepare('UPDATE users SET email_verified_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$id]);
    $users[$key] = ['id' => $id, 'email' => $email];
}
function listProject(int $owner, string $name, string $description = '', string $due = ''): int {
    global $service; return $service->create($owner, ['name' => $name, 'description' => $description, 'starts_on' => '', 'due_on' => $due]);
}
function listLogin(string $key): void {
    global $cookie, $users, $password; $cookie = '';
    $csrf = token(request('/login')[1]);
    check(request('/login', ['_token' => $csrf, 'email' => $users[$key]['email'], 'password' => $password])[0] === 303, 'Login da listagem');
}
$owner = $users['owner']['id']; $other = $users['other']['id'];
$due = (new DateTimeImmutable('today', new DateTimeZone('UTC')))->modify('+7 days')->format('Y-m-d');
$own = listProject($owner, 'Lançamento do site', 'Do planejamento aos últimos detalhes da nossa próxima entrega.', $due);
listProject($owner, 'Ideias para o próximo trimestre', 'Um espaço para organizar possibilidades e escolher o próximo passo.');
listProject($owner, 'Rotina da equipe', 'Pequenas entregas, acompanhamento e trabalho em conjunto.', $due);
$tenant = $pdo->query('SELECT id FROM tenants WHERE owner_id = ' . $other . " AND kind = 'personal'")->fetchColumn();
$pdo->prepare('INSERT INTO tenant_users (tenant_id, user_id) VALUES (?, ?)')->execute([$tenant, $owner]);
foreach (['admin' => 'Identidade da marca', 'member' => 'Planejamento de conteúdo', 'viewer' => 'Pesquisa com clientes'] as $role => $name) {
    $id = listProject($other, $name, 'Projeto compartilhado para acompanhar e construir em equipe.');
    $pdo->prepare('INSERT INTO project_members (tenant_id, project_id, user_id, role) VALUES (?, ?, ?, ?)')->execute([$tenant, $id, $owner, $role]);
}
$archived = listProject($owner, 'Primeiro ciclo de entregas', 'Um registro do trabalho que já ganhou forma.');
$service->change($owner, $archived, 'archive');
$sharedArchive = listProject($other, 'Pesquisa anterior');
$pdo->prepare("INSERT INTO project_members (tenant_id, project_id, user_id, role) VALUES (?, ?, ?, 'viewer')")->execute([$tenant, $sharedArchive, $owner]);
$service->change($other, $sharedArchive, 'archive');
$hidden = listProject($other, 'PRIVADO NÃO MOSTRAR');
$deleted = listProject($owner, 'EXCLUIDO NÃO MOSTRAR'); $service->change($owner, $deleted, 'delete');
for ($i = 0; $i < 26; $i++) {
    listProject($users['pager']['id'], 'Projeto ativo ' . $i);
    $id = listProject($users['pager']['id'], 'Projeto arquivado ' . $i); $service->change($users['pager']['id'], $id, 'archive');
}
$payload = '<script>window.projectsXss=true</script>' . str_repeat('x', 75);
$xss = listProject($users['xss']['id'], $payload, '<img src=x onerror=window.projectsXss=true>' . str_repeat('y', 500));
listLogin('owner');
$html = request('/projects')[1];
check(substr_count($html, '<article class="pl-card') === 6, 'Seis projetos acessíveis');
foreach (['Proprietário', 'Administrador', 'Membro', 'Visualizador', 'Projeto próprio', 'Compartilhado comigo', 'Sem prazo definido'] as $text) check(str_contains($html, $text), 'Contexto do projeto: ' . $text);
check(!str_contains($html, 'PRIVADO NÃO MOSTRAR') && !str_contains($html, 'EXCLUIDO NÃO MOSTRAR'), 'Lista não expõe projetos inacessíveis/excluídos');
check(request('/projects/' . $hidden)[0] === 404 && request('/projects/' . $hidden . '/board')[0] === 404, 'Atalho não contorna o escopo');
$html = request('/projects?status=archived')[1];
check(substr_count($html, '<article class="pl-card') === 2 && str_contains($html, 'Consultar quadro'), 'Arquivados acessíveis');
check(request('/projects/' . $sharedArchive . '/board')[0] === 200, 'Quadro arquivado para viewer');
listLogin('pager');
foreach (['active', 'archived'] as $status) {
    $html = request('/projects?status=' . $status . '&page=2')[1];
    check(substr_count($html, '<article class="pl-card') === 1 && str_contains($html, 'Página 2 de 2'), 'Página dois: ' . $status);
    check(str_contains($html, '/projects?status=' . $status . '&amp;page=1'), 'Paginação preserva filtro');
}
listLogin('empty');
foreach (['/projects', '/projects?status=archived'] as $path) check(str_contains(request($path)[1], 'class="pl-empty"'), 'Estado vazio');
listLogin('xss'); $html = request('/projects')[1];
check(!str_contains($html, '<script>window.projectsXss') && str_contains($html, '&lt;script&gt;window.projectsXss'), 'Nome escapado');
check(!str_contains($html, '<img src=x') && str_contains($html, '&lt;img src=x'), 'Descrição escapada');
$directory = dirname(__DIR__) . '/storage/previews';
if (!is_dir($directory)) mkdir($directory, 0775, true);
file_put_contents($directory . '/projects-fixture.json', json_encode(['users' => $users, 'own' => $own, 'archived' => $archived, 'sharedArchive' => $sharedArchive, 'hidden' => $hidden, 'xss' => $xss], JSON_THROW_ON_ERROR));
echo "OK: listagem, papéis, próprios/compartilhados, arquivados, paginação, escopo, XSS e vazios; fixture descartável para Chromium preparada.\n";
