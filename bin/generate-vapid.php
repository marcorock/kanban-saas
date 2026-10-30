<?php

declare(strict_types=1);
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
$config=require dirname(__DIR__).'/bootstrap/app.php';
try {
    $path=dirname(__DIR__).'/.env';
    // Protege um arquivo novo sem alterar permissões/ACL de uma configuração existente.
    $mask=umask(0077);
    try {$handle=fopen($path,'c+');} finally {umask($mask);}
    if(!$handle||!flock($handle,LOCK_EX))throw new RuntimeException();
    $contents=stream_get_contents($handle);
    if (!empty($config['push']['public_key'])&&!empty($config['push']['private_key'])&&!empty($config['push']['subject'])) {fwrite(STDOUT,"VAPID já existe; nenhuma chave alterada.\n");exit;}
    // Não sobrescreve entradas, mesmo incompletas, sem intenção explícita do operador.
    if (preg_match('/^VAPID_(PUBLIC_KEY|PRIVATE_KEY)\s*=\s*[\x22\x27]?[A-Za-z0-9_-]+/m',$contents)) throw new RuntimeException();
    $keys=Minishlink\WebPush\VAPID::createVapidKeys();
    $contents=preg_replace('/^VAPID_(PUBLIC_KEY|PRIVATE_KEY|SUBJECT)=.*\R?/m','',$contents);
    $subject=$config['push']['subject']?:$config['url'];
    if (!preg_match('~^(https://|mailto:)[^\s\x22\x27]+$~D',$subject)) $subject='mailto:admin@localhost.invalid';
    rewind($handle);ftruncate($handle,0);fwrite($handle,rtrim($contents)."\n\nVAPID_PUBLIC_KEY=".$keys['publicKey']."\nVAPID_PRIVATE_KEY=".$keys['privateKey']."\nVAPID_SUBJECT=".$subject."\n");fflush($handle);flock($handle,LOCK_UN);fclose($handle);
    echo "VAPID configurado no .env local. Chaves não exibidas. Preserve este par.\n";
}catch(Throwable){fwrite(STDERR,"Não foi possível configurar VAPID. Confira permissões e entradas existentes no .env.\n");exit(1);}
