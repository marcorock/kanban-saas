<?php

declare(strict_types=1);
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
$config=require dirname(__DIR__).'/bootstrap/app.php';
try {
    $options=getopt('',['limit:']);$limit=$options['limit']??'100';
    if (!is_string($limit)||!ctype_digit($limit)||(int)$limit<1||(int)$limit>500) throw new InvalidArgumentException('Limite inválido.');
    $pdo=App\Infrastructure\Database::connect($config['database']);$now=new DateTimeImmutable('now',new DateTimeZone('UTC'));
    (new App\Services\DeadlineNotificationService($pdo))->generate($now);
    $stats=(new App\Services\NotificationWorker($pdo))->run(new DateTimeImmutable('now',new DateTimeZone('UTC')),(int)$limit);
    $stats['external']=(new App\Services\ExternalNotificationWorker($pdo,$config))->run(new DateTimeImmutable('now',new DateTimeZone('UTC')),(int)$limit);
    echo json_encode($stats,JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $error) {fwrite(STDERR,"Falha no worker de notificações. Verifique banco/migrations e tente novamente.\n");exit(1);}
