<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
use App\Infrastructure\InstallationCheck;

function expect(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$config = [
    'url' => 'https://kanban.example.org', 'environment' => 'production', 'debug' => false,
    'database' => ['username' => 'kanban_runtime', 'password' => 'test-only'],
    'mail' => ['host' => 'smtp.example.org', 'port' => 587, 'encryption' => 'tls', 'from_address' => 'kanban@example.org', 'username' => 'test-only', 'password' => 'test-only'],
];
expect(InstallationCheck::configurationErrors($config, true) === [], 'Configuração de produção válida');
foreach (['http://kanban.example.org', 'https://user:secret@kanban.example.org', 'https://kanban.example.org/subdirectory', 'https://kanban.example.org/?token=secret', 'https://kanban.example.org/#fragment', 'https://invalid host/', 'invalid'] as $url) {
    $bad = $config; $bad['url'] = $url;
    expect(InstallationCheck::configurationErrors($bad, true) !== [], 'Rejeitar URL insegura ou incompatível');
}
foreach ([true, null] as $debug) {
    $bad = $config; $bad['debug'] = $debug;
    expect(InstallationCheck::configurationErrors($bad, true) !== [], 'Debug desabilitado');
}
$bad = $config; $bad['environment'] = 'local'; $bad['mail']['encryption'] = 'none'; $bad['database']['username'] = 'root';
$errors = InstallationCheck::configurationErrors($bad, true);
expect(count($errors) === 3 && !str_contains(implode(' ', $errors), 'test-only'), 'Falhas de produção sem segredos');
$bad['url'] = 'http://localhost:8000';
expect(InstallationCheck::configurationErrors($bad, false) === [], 'Preserva configuração local Mailpit');
foreach ([0, 65536] as $port) {
    $bad = $config; $bad['mail']['port'] = $port;
    expect(InstallationCheck::configurationErrors($bad, false) !== [], 'Porta válida');
}
echo "OK: configuração local/produção, URLs, debug, SMTP e banco sem exposição de segredos.\n";
