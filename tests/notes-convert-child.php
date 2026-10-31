<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('AUTH_TEST_DATABASE')!=='disposable')exit(1);
$c=require dirname(__DIR__).'/bootstrap/app.php';$p=App\Infrastructure\Database::connect($c['database']);
try{(new App\Services\QuickNoteService($p))->convert((int)$argv[1],(int)$argv[2],(int)$argv[3],(int)$argv[4],'Conversão única',1);echo "201\n";}catch(Pecee\SimpleRouter\Exceptions\HttpException $e){if(!in_array($e->getCode(),[404,409],true))throw $e;echo "404\n";}
