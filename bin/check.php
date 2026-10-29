<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (array_diff(array_slice($argv, 1), ['--production'])) {
    fwrite(STDERR, "Uso: php bin/check.php [--production]\n"); exit(2);
}
try {
    $config = require dirname(__DIR__) . '/bootstrap/app.php';
    $checks = App\Infrastructure\InstallationCheck::run($config, dirname(__DIR__), in_array('--production', $argv, true));
    $failed = false;
    foreach ($checks as $check) {
        echo ($check['ok'] ? '[OK] ' : '[FALHA] ') . $check['message'] . "\n";
        $failed = $failed || !$check['ok'];
    }
    echo "Verificação somente de leitura; não testa entrega SMTP, HTTPS externo ou configuração do servidor web.\n";
    exit($failed ? 1 : 0);
} catch (Throwable) {
    fwrite(STDERR, "Falha ao carregar a instalação. Confira dependências, extensões e configuração; nenhum segredo foi exibido.\n");
    exit(1);
}
