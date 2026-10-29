<?php

declare(strict_types=1);
require __DIR__ . '/http.php';
check(!str_contains(request('/')[1], 'class="app-sidebar"'), 'Sem navegação privada na página pública');
check(request('/settings')[0] === 303, 'Shell não concede acesso anônimo');
$email = 'shell-' . bin2hex(random_bytes(6)) . '@example.com';
$password = 'Senha-shell-segura-123';
$auth = new App\Services\AuthService($pdo);
$id = $auth->register('<script>shell-xss</script>', $email, $password);
$pdo->prepare('UPDATE users SET email_verified_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$id]);
$csrf = token(request('/login')[1]);
check(request('/login', ['_token' => $csrf, 'email' => $email, 'password' => $password])[0] === 303, 'Login');
foreach (['/dashboard' => 'dashboard', '/projects' => 'projects', '/tasks' => 'tasks', '/history' => 'history', '/settings' => 'settings'] as $path => $section) {
    [$status, $html] = request($path);
    check($status === 200, 'Página acessível: ' . $path);
    check(str_contains($html, 'class="app-sidebar"') && str_contains($html, 'class="app-bottom-nav"'), 'Estrutura compartilhada');
    check(str_contains($html, '&lt;script&gt;shell-xss&lt;/script&gt;') && !str_contains($html, '<script>shell-xss</script>'), 'Nome escapado');
    check(str_contains($html, 'href="' . $path . '" aria-current="page"'), 'Destino atual: ' . $path);
    check(App\Services\NavigationContext::forPath($path)['section'] === $section, 'Seção correta');
}
$project = (new App\Services\ProjectService($pdo))->create($id, ['name' => 'Projeto do shell', 'description' => '', 'starts_on' => '', 'due_on' => '']);
$html = request('/projects/' . $project . '/history')[1];
check(str_contains($html, 'href="/projects" aria-current="location"') && str_contains($html, '<h1>Histórico do projeto</h1>'), 'Histórico do projeto mantém o módulo Projetos');
check(!str_contains($html, 'href="/history" aria-current="page"'), 'Não confunde histórico próprio com projeto');
check(request('/invitations/accept')[0] === 200 && str_contains(request('/invitations/accept')[1], 'class="app-sidebar"'), 'Convite autenticado integra navegação');
check(request('/logout', ['_token' => 'invalid'])[0] === 419, 'Saída exige CSRF');
check(request('/logout', ['_token' => token(request('/settings')[1])])[0] === 303, 'Sair funciona');
check(request('/dashboard')[0] === 303, 'Sessão encerrada');
echo "OK: shell autenticado, contexto, estado ativo, histórico de projeto, XSS, convite e logout com CSRF.\n";
