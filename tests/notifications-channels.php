<?php

declare(strict_types=1);
require __DIR__.'/board-http.php';require __DIR__.'/mailpit.php';
use App\Services\{AuthService,ProjectService,BoardService,NotificationService,PushSubscriptionService,ExternalNotificationWorker,NotificationOutbox,WebPushGateway};
use Minishlink\WebPush\VAPID;
use GuzzleHttp\{Client,HandlerStack};use GuzzleHttp\Handler\MockHandler;use GuzzleHttp\Psr7\Response;
// Cada suíte cria suas identidades; pendências externas de fixtures anteriores ficam fora deste relógio.
$pdo->exec("UPDATE notification_outbox SET available_at='2099-01-01' WHERE channel IN ('push','email') AND status IN ('pending','retry')");
$auth=new AuthService($pdo);$projects=new ProjectService($pdo);$boards=new BoardService($pdo);$service=new NotificationService($pdo);$devices=new PushSubscriptionService($pdo);
$password='Senha-canais-segura-123';$suffix=bin2hex(random_bytes(6));$users=[];
foreach(['owner','member','other'] as $name){$email=$name.'-'.$suffix.'@example.com';$id=$auth->register($name,$email,$password);$pdo->prepare('UPDATE users SET email_verified_at=UTC_TIMESTAMP() WHERE id=?')->execute([$id]);$users[$name]=compact('id','email');}
$owner=$users['owner']['id'];$member=$users['member']['id'];$other=$users['other']['id'];$email=$users['member']['email'];
$projectId=$projects->create($owner,['name'=>'Projeto multicanal','description'=>'SEGREDO-PROJETO','starts_on'=>'','due_on'=>'']);
$project=(new App\Repositories\ProjectRepository($pdo))->find($owner,$projectId);$tenant=(int)$project['tenant_id'];
$pdo->prepare('INSERT INTO tenant_users(tenant_id,user_id) VALUES (?,?)')->execute([$tenant,$member]);$pdo->prepare("INSERT INTO project_members(tenant_id,project_id,user_id,role) VALUES (?,?,?,'member')")->execute([$tenant,$projectId,$member]);
function channelFlags(bool $push,bool $mail): void{global $service,$member;$service->savePreferences($member,1,['bell_enabled'=>false,'push_enabled'=>$push,'email_enabled'=>$mail]);}
function channelChange(string $action,array $data):void{global $boards,$owner,$projectId,$state;$state=$boards->mutate($owner,$projectId,$state['version'],$action,$data);}
function channelTask(string $title,string $due):int{global $state,$member;channelChange('task.create',['column_id'=>$state['columns'][0]['id'],'title'=>$title]);$task=end($state['columns'][0]['tasks'])['id'];channelChange('task.update',['task_id'=>$task,'title'=>$title,'description'=>'SEGREDO-DESCRICAO','priority'=>'normal','due_on'=>$due,'assignee_ids'=>[$member]]);return $task;}
function deviceData(string $suffix):array{$keys=VAPID::createVapidKeys();return ['endpoint'=>'https://fcm.googleapis.com/fcm/send/'.$suffix,'keys'=>['p256dh'=>$keys['publicKey'],'auth'=>rtrim(strtr(base64_encode(random_bytes(16)),'+/','-_'),'=')]];}
function clockNow():DateTimeImmutable{return new DateTimeImmutable('+1 second',new DateTimeZone('UTC'));}
function externalDrain(ExternalNotificationWorker $worker):void{for($i=0;$i<5;$i++)$worker->run(clockNow(),500);}
$keys=VAPID::createVapidKeys();$pushConfig=['public_key'=>$keys['publicKey'],'private_key'=>$keys['privateKey'],'subject'=>'mailto:test@example.com'];
$mock=new MockHandler([new Response(410)]);$gateway=new WebPushGateway($pushConfig,new Client(['handler'=>HandlerStack::create($mock)]));$worker=new ExternalNotificationWorker($pdo,$config,$gateway);
channelFlags(true,true);$first=deviceData($suffix.'-expired');$devices->save($member,1,$first);$state=$boards->state($owner,$projectId);$today=clockNow()->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('Y-m-d');$task=channelTask('Entrega multicanal',$today);
check(count(messagesFor($email))===0,'Requisição não envia SMTP');externalDrain($worker);
check((int)$pdo->query("SELECT COUNT(*) FROM push_subscriptions WHERE user_id={$member}")->fetchColumn()===0,'410 remove assinatura');check(count(messagesFor($email))===1,'SMTP real no Mailpit mesmo com sino desligado');
$second=deviceData($suffix.'-one');$third=deviceData($suffix.'-two');$devices->save($member,1,$second);$devices->save($member,1,$third);
$csrf=boardLogin($users['member']['email']);check(request('/settings/notifications/push',$second)[0]===419,'CSRF de assinatura');
check(request('/settings/notifications/push',['_token'=>$csrf,'endpoint'=>'https://127.0.0.1/private','keys'=>$second['keys']])[0]===422,'Endpoint interno recusado');
check(request('/settings/notifications/push',['_token'=>$csrf,'endpoint'=>$second['endpoint'],'keys'=>['p256dh'=>'invalida','auth'=>'a']])[0]===422,'Chaves inválidas recusadas');
check(request('/settings/notifications/push/remove',['_token'=>$csrf,'endpoint'=>'https://fcm.googleapis.com/fcm/send/not-owned','user_id'=>$other])[0]===200,'Remoção idempotente da própria conta');
check((int)$pdo->query("SELECT COUNT(*) FROM push_subscriptions WHERE user_id={$member}")->fetchColumn()===2,'Outras assinaturas preservadas');
check(json_decode(request('/notifications/push-context')[1],true)['user_id']===$member,'Contexto somente da sessão');
$csrf=boardLogin($users['other']['email']);check(request('/settings/notifications/push/remove',['_token'=>$csrf,'endpoint'=>$second['endpoint']])[0]===200,'Remoção alheia idempotente sem exclusão');
check((int)$pdo->query("SELECT COUNT(*) FROM push_subscriptions WHERE user_id={$member}")->fetchColumn()===2,'IDOR de exclusão bloqueado');
try{$devices->save($other,1,$second);check(false,'Outra conta não captura assinatura');}catch(DomainException $e){check($e->getCode()===409,'Propriedade por dispositivo');}
$mock->append(new Response(201),new Response(503),new Response(201));channelChange('comment.create',['task_id'=>$task,'body'=>'SEGREDO-COMENTARIO']);$worker->run(clockNow(),500);
$q=$pdo->query("SELECT id,status FROM notification_outbox WHERE user_id={$member} AND channel='push' AND kind='comment' ORDER BY id DESC LIMIT 1");$notice=$q->fetch();check($notice['status']==='retry','Falha parcial retenta');
check((int)$pdo->query('SELECT COUNT(*) FROM notification_push_deliveries WHERE outbox_id='.(int)$notice['id'])->fetchColumn()===1,'Dispositivo bem-sucedido registrado');
$worker->run(clockNow()->modify('+2 minutes'),500);check((int)$pdo->query('SELECT COUNT(*) FROM notification_push_deliveries WHERE outbox_id='.(int)$notice['id'])->fetchColumn()===2,'Retry só no dispositivo restante');check(count($mock)===0,'410/201/503 e retry usaram apenas transportes simulados');
$before=count(messagesFor($email));channelChange('comment.create',['task_id'=>$task,'body'=>'Canal desligado antes de enviar']);channelFlags(false,false);externalDrain($worker);check(count(messagesFor($email))===$before,'Desligar antes do envio cancela');
channelFlags(false,true);channelChange('comment.create',['task_id'=>$task,'body'=>'Acesso revogado']);$pdo->prepare('DELETE FROM task_assignees WHERE tenant_id=? AND project_id=? AND task_id=? AND user_id=?')->execute([$tenant,$projectId,$task,$member]);externalDrain($worker);check(count(messagesFor($email))===$before,'Reconferir atribuição antes de SMTP');
$yesterday=clockNow()->setTimezone(new DateTimeZone('America/Sao_Paulo'))->modify('-1 day')->format('Y-m-d');$one=channelTask('Primeiro prazo',$yesterday);$two=channelTask('Segundo prazo',$yesterday);externalDrain($worker);$before=count(messagesFor($email));
$outbox=new NotificationOutbox($pdo);foreach([$one,$two] as $id)$outbox->enqueue($member,'overdue',$today,['tenant_id'=>$tenant,'project_id'=>$projectId,'task_id'=>$id,'due_on'=>$yesterday],'deadline');
$jobs=[];for($i=0;$i<2;$i++)$jobs[]=proc_open([PHP_BINARY,__DIR__.'/notify-worker-child.php',clockNow()->format('c'),'external'],[STDIN,STDOUT,STDERR],$pipes,dirname(__DIR__));foreach($jobs as $job)check(is_resource($job)&&proc_close($job)===0,'Workers externos concorrentes');
check(count(messagesFor($email))===$before+1,'Prazos agregados por usuário/ciclo, concorrência sem duplicar');
$messages=messagesFor($email);$message=json_decode(file_get_contents($mailpit.'/api/v1/message/'.$messages[0]['ID']),true);check(str_contains($message['Text'],'Primeiro prazo')&&str_contains($message['Text'],'Segundo prazo'),'Resumo inclui ambos os prazos');
check(!str_contains($message['Text'],'SEGREDO-')&&str_contains($message['Text'],'/projects/'.$projectId.'/board?task='),'SMTP com títulos/links, sem descrição/corpo');
// 404 também remove endpoint; sem inscrições válidas, push não fica retentando.
channelFlags(true,false);$mock->append(new Response(404),new Response(410));$outbox->enqueue($member,'overdue',$today,['tenant_id'=>$tenant,'project_id'=>$projectId,'task_id'=>$one,'due_on'=>$yesterday],'push-expired');externalDrain($worker);check((int)$pdo->query("SELECT COUNT(*) FROM push_subscriptions WHERE user_id={$member}")->fetchColumn()===0,'404/410 removem todos os dispositivos inválidos');
// Recuperação de lease e esgotamento SMTP sem rede externa.
channelFlags(false,true);$outbox->enqueue($member,'assigned',$today,['tenant_id'=>$tenant,'project_id'=>$projectId,'task_id'=>$one],'lease-smtp');
$lease=(int)$pdo->query("SELECT MAX(id) FROM notification_outbox WHERE user_id={$member} AND channel='email'")->fetchColumn();
$pdo->prepare("UPDATE notification_outbox SET status='processing',attempts=1,locked_until=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 6 MINUTE),lock_token=? WHERE id=?")->execute([str_repeat('b',32),$lease]);
externalDrain($worker);check($pdo->query("SELECT status FROM notification_outbox WHERE id={$lease}")->fetchColumn()==='delivered','Lease SMTP expirado recuperado');
$outbox->enqueue($member,'assigned',$today,['tenant_id'=>$tenant,'project_id'=>$projectId,'task_id'=>$one],'smtp-failure');
$failure=(int)$pdo->query("SELECT MAX(id) FROM notification_outbox WHERE user_id={$member} AND channel='email'")->fetchColumn();
$bad=$config;$bad['mail']['port']=1;$broken=new ExternalNotificationWorker($pdo,$bad,$gateway);
foreach([0,2,6,14,30] as $minutes)$broken->run(clockNow()->modify('+'.$minutes.' minutes'),500);
$row=$pdo->query("SELECT status,attempts,last_error FROM notification_outbox WHERE id={$failure}")->fetch();
check($row['status']==='failed'&&(int)$row['attempts']===5&&$row['last_error']==='delivery_failed','SMTP falha limitada, sem dados sensíveis no erro');
$cookie='';check(request('/notifications/push-context')[0]===401,'Contexto exige sessão');
echo "OK: SMTP/Mailpit, resumo de prazos, preferências por canal, autorização, 404/410, falha parcial, retry por dispositivo e concorrência.\n";
