<?php

declare(strict_types=1);

namespace App\Infrastructure;

use PDO;
use InvalidArgumentException;

final class Database
{
    public static function connect(array $config): PDO
    {
        foreach (['host', 'port', 'name'] as $key) {
            if (!preg_match('/^[a-zA-Z0-9_.:-]+$/D', $config[$key])) {
                throw new InvalidArgumentException('Configuração de banco inválida.');
            }
        }
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['host'], $config['port'], $config['name']),
            $config['username'],
            $config['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        );
        $pdo->exec("SET time_zone = '+00:00'");
        return $pdo;
    }
}
