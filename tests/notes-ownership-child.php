<?php

declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('AUTH_TEST_DATABASE')!=='disposable')exit(1);
$config=require dirname(__DIR__).'/bootstrap/app.php';$pdo=App\Infrastructure\Database::connect($config['database']);$service=new App\Services\QuickNoteService($pdo);
try{if($argv[1]==='transfer')$service->transfer((int)$argv[2],(int)$argv[3],(int)$argv[4],true,$argv[6]);else $service->convert((int)$argv[2],(int)$argv[3],(int)$argv[4],(int)$argv[5],'Conversão concorrente confirmada',$argv[6]);echo 'ok';}
catch(Pecee\SimpleRouter\Exceptions\HttpException $e){if(!in_array($e->getCode(),[404,409],true))throw $e;echo 'conflict';}
