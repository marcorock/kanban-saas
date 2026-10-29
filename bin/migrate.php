<?php

declare(strict_types=1);

use App\Infrastructure\Database;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$config = require dirname(__DIR__) . '/bootstrap/app.php';
$locked = false;
try {
    $pdo = Database::connect($config['database']);
    $locked = (int) $pdo->query("SELECT GET_LOCK('kanbansaas_migrations', 10)")->fetchColumn() === 1;
    if (!$locked) {
        throw new RuntimeException('Migration em execução.');
    }
    $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(190) PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
    $applied = $pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
    foreach (glob(dirname(__DIR__) . '/database/migrations/*.sql') as $file) {
        $version = basename($file);
        if (in_array($version, $applied, true)) {
            continue;
        }
        $pdo->exec(file_get_contents($file));
        $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (?)')->execute([$version]);
        echo "Aplicada: {$version}\n";
    }
} catch (Throwable $exception) {
    fwrite(STDERR, "Falha nas migrations. Verifique conexão e estado do schema antes de tentar novamente.\n");
    exit(1);
} finally {
    if ($locked) {
        $pdo->query("SELECT RELEASE_LOCK('kanbansaas_migrations')");
    }
}
