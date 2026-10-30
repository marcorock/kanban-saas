<?php

declare(strict_types=1);
if (PHP_SAPI !== 'cli' || getenv('AUTH_TEST_DATABASE') !== 'disposable') {
    fwrite(STDERR, "Execute por CLI com AUTH_TEST_DATABASE=disposable somente em banco de testes.\n"); exit(1);
}
// Processos sequenciais: fixtures e triggers de rollback não podem disputar o mesmo banco.
foreach (['installation', 'session', 'auth', 'account', 'projects', 'projects-list', 'members', 'board', 'tasks', 'reports', 'profile', 'shell', 'security', 'preflight', 'performance'] as $suite) {
    echo "Teste: {$suite}\n";
    $process = proc_open([PHP_BINARY, __DIR__ . '/' . $suite . '.php'], [STDIN, STDOUT, STDERR], $pipes, dirname(__DIR__));
    if (!is_resource($process) || proc_close($process) !== 0) exit(1);
}
echo "OK: todas as suítes PHP.\n";
