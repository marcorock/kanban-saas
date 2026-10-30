<?php

declare(strict_types=1);
$config=require dirname(__DIR__).'/bootstrap/app.php';
if (PHP_SAPI!=='cli'||getenv('AUTH_TEST_DATABASE')!=='disposable') exit(1);
$pdo=App\Infrastructure\Database::connect($config['database']);
$now=new DateTimeImmutable($argv[1]??'now',new DateTimeZone('UTC'));
if (($argv[2]??'worker')==='deadlines') (new App\Services\DeadlineNotificationService($pdo))->generate($now);
elseif (($argv[2]??'')==='external') (new App\Services\ExternalNotificationWorker($pdo,$config))->run($now,500);
else (new App\Services\NotificationWorker($pdo))->run($now,500);
