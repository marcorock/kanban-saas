<?php

declare(strict_types=1);
require __DIR__ . '/http.php';

function checkCli(array $environment, array $args = []): array {
    $process = proc_open([PHP_BINARY, 'bin/check.php', ...$args], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__), $environment);
    check(is_resource($process), 'Executar verificação CLI');
    $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    return [proc_close($process), $output];
}
$environment = getenv();
check(checkCli($environment)[0] === 0, 'Verificação local');
check(checkCli($environment, ['--invalid'])[0] === 2, 'Opção desconhecida');
[$status, $output] = checkCli($environment, ['--production']);
check($status === 1 && str_contains($output, 'Produção exige'), 'Rejeitar configuração local como produção');
$login = 'check_' . bin2hex(random_bytes(5));
$password = bin2hex(random_bytes(20));
// Usuário sem INSERT/UPDATE/DELETE/DDL comprova que o verificador é somente leitura.
$pdo->exec('CREATE USER ' . $pdo->quote($login) . "@'%' IDENTIFIED BY " . $pdo->quote($password));
try {
    $database = $config['database']['name'];
    check((bool) preg_match('/^[a-zA-Z0-9_]+$/D', $database), 'Identificador seguro do banco descartável');
    $pdo->exec('GRANT SELECT ON `' . $database . '`.* TO ' . $pdo->quote($login) . "@'%'");
    $production = array_merge($environment, ['DB_USERNAME' => $login, 'DB_PASSWORD' => $password, 'APP_ENV' => 'production', 'APP_DEBUG' => 'false', 'APP_URL' => 'https://kanban.example.org', 'MAIL_ENCRYPTION' => 'tls']);
    [$status, $output] = checkCli($production, ['--production']);
    check($status === 0 && !str_contains($output, $password) && !str_contains($output, $login), 'Produção válida e leitura sem segredos');
    $version = $pdo->query('SELECT version FROM schema_migrations ORDER BY version DESC LIMIT 1')->fetchColumn();
    $pdo->prepare('DELETE FROM schema_migrations WHERE version = ?')->execute([$version]);
    try {
        [$status, $output] = checkCli($production, ['--production']);
        check($status === 1 && str_contains($output, '[FALHA] Migrations'), 'Detectar migration pendente');
    } finally {
        $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (?)')->execute([$version]);
    }
} finally {
    $pdo->exec('DROP USER ' . $pdo->quote($login) . "@'%'");
}
echo "OK: preflight CLI, códigos de saída, produção, migrations e usuário SQL somente leitura.\n";
