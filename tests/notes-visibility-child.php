<?php

declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('AUTH_TEST_DATABASE')!=='disposable')exit(1);
$config=require dirname(__DIR__).'/bootstrap/app.php';
$pdo=App\Infrastructure\Database::connect($config['database']);
try{(new App\Services\QuickNoteService($pdo))->update((int)$argv[1],(int)$argv[2],'Movida em concorrência',['project_id'=>(int)$argv[3],'revision'=>$argv[4]]);echo 'ok';}
catch(Pecee\SimpleRouter\Exceptions\HttpException $e){if($e->getCode()!==409)throw $e;echo 'conflict';}
