<?php

declare(strict_types=1);

use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

date_default_timezone_set('UTC');
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

return require dirname(__DIR__) . '/config/app.php';
