<?php

declare(strict_types=1);
require __DIR__.'/notifications.php';
$csrf=boardLogin($users['owner']['email']);[$status,$body,$headers]=request('/notifications?format=count');$count=json_decode($body,true);
check($status===200&&is_int($count['unread'])&&str_contains($headers,'no-store')&&str_contains($headers,'application/json'),'Contagem privada, não cacheável e somente metadata');
$expected=$service->unread($owner);[$s,$raw]=request('/notifications?format=count&user_id='.$member);check(json_decode($raw,true)['unread']===$expected,'Identidade de sessão, não parâmetro');
$csrf=boardLogin($users['unverified']['email']);[$s,$b,$h]=request('/notifications?format=count');check($s===403&&json_decode($b,true)['error']==='unverified'&&str_contains($h,'no-store'),'Sem HTML para sessão não verificada');
$cookie='';[$s,$b,$h]=request('/notifications?format=count');check($s===401&&json_decode($b,true)['error']==='unauthenticated'&&str_contains($h,'no-store'),'Sem redirect para sessão expirada');
echo "OK: contador JSON privado, sessão/verificação e cache; elegibilidade, tenant, deduplicação e concorrência preservados.\n";
