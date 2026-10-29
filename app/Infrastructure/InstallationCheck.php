<?php

declare(strict_types=1);

namespace App\Infrastructure;

use Throwable;

/** Verificações somente de leitura; mensagens nunca incluem valores de configuração. */
final class InstallationCheck
{
    public static function configurationErrors(array $config, bool $production): array
    {
        $errors = [];
        $url = parse_url($config['url']);
        if (filter_var($config['url'], FILTER_VALIDATE_URL) === false || !$url || !in_array($url['scheme'] ?? '', ['http', 'https'], true) || empty($url['host'])
            || isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment'])
            || !in_array($url['path'] ?? '', ['', '/'], true)) {
            $errors[] = 'APP_URL deve ser uma URL HTTP(S) na raiz do domínio, sem credenciais, query ou fragmento.';
        }
        if ($config['debug'] === null || ($production && $config['debug'] !== false)) {
            $errors[] = 'APP_DEBUG deve ser booleano válido e false em produção.';
        }
        $mail = $config['mail'];
        if (trim($mail['host']) === '' || $mail['port'] < 1 || $mail['port'] > 65535
            || !in_array($mail['encryption'], ['none', 'tls', 'ssl'], true)
            || filter_var($mail['from_address'], FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'Configuração SMTP inválida: confira host, porta, criptografia e remetente.';
        }
        if ($production) {
            if ($config['environment'] !== 'production') $errors[] = 'APP_ENV deve ser production.';
            if (($url['scheme'] ?? '') !== 'https') $errors[] = 'Produção exige APP_URL com HTTPS.';
            if (!in_array($mail['encryption'], ['tls', 'ssl'], true)) $errors[] = 'Produção exige SMTP com tls ou ssl.';
            if ($mail['username'] !== '' && $mail['password'] === '') $errors[] = 'SMTP autenticado exige MAIL_PASSWORD.';
            if ($config['database']['username'] === '' || strtolower($config['database']['username']) === 'root' || $config['database']['password'] === '') {
                $errors[] = 'Produção exige usuário próprio do banco e senha não vazia.';
            }
        }
        return $errors;
    }

    public static function run(array $config, string $root, bool $production): array
    {
        $checks = [];
        $record = static function (bool $ok, string $message) use (&$checks): void {
            $checks[] = ['ok' => $ok, 'message' => $message];
        };
        $record(PHP_VERSION_ID >= 80300 && PHP_VERSION_ID < 90000, 'PHP compatível com ^8.3 (8.3 até 8.x).');
        foreach (['ctype', 'filter', 'mbstring', 'openssl', 'pdo_mysql', 'session'] as $extension) {
            $record(extension_loaded($extension), 'Extensão PHP: ' . $extension . '.');
        }
        $errors = self::configurationErrors($config, $production);
        if (!$errors) $record(true, $production ? 'Configuração de produção válida.' : 'Configuração local válida.');
        foreach ($errors as $error) $record(false, $error);
        foreach (['assets/brand/mark.svg', 'assets/css/design-tokens.css', 'assets/css/auth.css', 'assets/css/app-shell.css', 'assets/css/legacy-layout.css', 'assets/js/app-shell.js', 'assets/js/auth.js', 'assets/css/bootstrap.min.css', 'assets/css/board.css', 'assets/css/reports.css', 'assets/vendor/bootstrap/bootstrap.bundle.min.js', 'assets/vendor/sortablejs/Sortable.min.js', 'assets/js/board.js'] as $asset) {
            $record(is_readable($root . '/public/' . $asset), 'Asset local: ' . $asset . '.');
        }
        try {
            $pdo = Database::connect($config['database']);
            $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
            $record(stripos($version, 'MariaDB') !== false && (int) $version >= 11, 'MariaDB 11 ou superior acessível.');
            $record($pdo->query('SELECT @@session.time_zone')->fetchColumn() === '+00:00', 'Conexão do banco em UTC.');
            $applied = $pdo->query('SELECT version FROM schema_migrations')->fetchAll(\PDO::FETCH_COLUMN);
            $expected = array_map('basename', glob($root . '/database/migrations/*.sql'));
            $record(!array_diff($expected, $applied) && !array_diff($applied, $expected), 'Migrations do banco correspondem aos arquivos desta versão.');
        } catch (Throwable) {
            $record(false, 'Não foi possível verificar banco/migrations. Confira conexão, permissões e schema.');
        }
        return $checks;
    }
}
