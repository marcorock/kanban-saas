<?php

declare(strict_types=1);
// Somente arquivos temporários: não lê nem altera o .env/banco de uso diário.
function vapidCheck(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$root=sys_get_temp_dir().'/kanban-vapid-'.bin2hex(random_bytes(6));mkdir($root,0700);mkdir($root.'/bin');mkdir($root.'/bootstrap');
$autoload=var_export(dirname(__DIR__).'/vendor/autoload.php',true);
$bootstrap="<?php require {$autoload}; \$values=is_file(dirname(__DIR__).'/.env')?Dotenv\\Dotenv::parse(file_get_contents(dirname(__DIR__).'/.env')):[]; return ['url'=>'https://kanban.example','push'=>['public_key'=>\$values['VAPID_PUBLIC_KEY']??'','private_key'=>\$values['VAPID_PRIVATE_KEY']??'','subject'=>\$values['VAPID_SUBJECT']??'']];";
file_put_contents($root.'/bootstrap/app.php',$bootstrap);copy(dirname(__DIR__).'/bin/generate-vapid.php',$root.'/bin/generate-vapid.php');
function runVapid(string $root):int{$p=proc_open([PHP_BINARY,$root.'/bin/generate-vapid.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);vapidCheck(is_resource($p),'Processo do gerador');fclose($pipes[0]);stream_get_contents($pipes[1]);fclose($pipes[1]);stream_get_contents($pipes[2]);fclose($pipes[2]);return proc_close($p);}
try {
    file_put_contents($root.'/.env',"DB_PASSWORD=credencial-ficticia\n");chmod($root.'/.env',0640);clearstatcache();$before=stat($root.'/.env');
    vapidCheck(runVapid($root)===0,'Gerar com configuração existente');clearstatcache();$after=stat($root.'/.env');
    vapidCheck(($after['mode']&0777)===0640&&$after['uid']===$before['uid']&&$after['gid']===$before['gid'],'Permissões/proprietário/grupo preservados para o servidor HTTP');
    $content=file_get_contents($root.'/.env');vapidCheck(str_contains($content,'DB_PASSWORD=credencial-ficticia'),'Configuração existente preservada');$hash=hash('sha256',$content);
    vapidCheck(runVapid($root)===0&&hash_file('sha256',$root.'/.env')===$hash,'Segunda execução preserva chaves e configuração');
    unlink($root.'/.env');vapidCheck(runVapid($root)===0,'Gerar arquivo novo');clearstatcache();vapidCheck((fileperms($root.'/.env')&0777)===0600,'Arquivo novo nasce restrito');
    echo "OK: VAPID preserva permissões, proprietário, grupo, configuração e chaves; arquivo novo restrito.\n";
}finally{foreach(['.env','bin/generate-vapid.php','bootstrap/app.php'] as $file)if(is_file($root.'/'.$file))unlink($root.'/'.$file);rmdir($root.'/bin');rmdir($root.'/bootstrap');rmdir($root);}
