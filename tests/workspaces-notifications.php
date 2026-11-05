<?php

declare(strict_types=1);

require __DIR__.'/board-http.php';
require __DIR__.'/mailpit.php';

use App\Services\{AuthService,BoardService,DeadlineNotificationService,ExternalNotificationWorker,NotificationOutbox,NotificationService,NotificationWorker,ProjectService,PushGateway,WorkspaceContext};
use Pecee\SimpleRouter\Exceptions\HttpException;

if (!str_starts_with($config['database']['name'],'kanban_workspace_')) throw new RuntimeException('Use banco descartável kanban_workspace_*.');
$password='Workspace-notificacoes-123';$suffix=bin2hex(random_bytes(6));$auth=new AuthService($pdo);
$email="notify-workspace-$suffix@example.com";
$user=$auth->register('Destinatário',$email,$password);$owner=$auth->register('Proprietário',"notify-owner-$suffix@example.com",$password);
$pdo->prepare('UPDATE users SET email_verified_at=UTC_TIMESTAMP() WHERE id IN (?,?)')->execute([$user,$owner]);
$a=WorkspaceContext::initial($pdo,$user);$ownerB=WorkspaceContext::initial($pdo,$owner);
$pdo->prepare("INSERT INTO tenant_users(tenant_id,user_id,role) VALUES (?,?,'member')")->execute([$ownerB->tenantId,$user]);
$b=new WorkspaceContext($user,$ownerB->tenantId);
$now=new DateTimeImmutable('2035-01-10T11:00:00Z');$day='2035-01-10';
$global=new NotificationService($pdo);$global->savePreferences($user,1,['bell_enabled'=>true,'email_enabled'=>true,'push_enabled'=>true]);
$projects=[];$tasks=[];
foreach ([$a,$b] as $context) {
    $id=(new ProjectService($pdo,$context))->create($user,['name'=>'Workspace-'.$context->tenantId,'description'=>'','starts_on'=>'','due_on'=>'']);
    $board=new BoardService($pdo,$context);$state=$board->state($user,$id);
    $state=$board->mutate($user,$id,$state['version'],'task.create',['column_id'=>$state['columns'][0]['id'],'title'=>'Entrega-'.$context->tenantId]);$task=$state['columns'][0]['tasks'][0]['id'];
    $board->mutate($user,$id,$state['version'],'task.update',['task_id'=>$task,'title'=>'Entrega-'.$context->tenantId,'description'=>'','priority'=>'normal','due_on'=>$day,'assignee_ids'=>[$user]]);
    $projects[$context->tenantId]=$id;$tasks[$context->tenantId]=$task;
}
$deadlines=new DeadlineNotificationService($pdo);$deadlines->generate($now);$deadlines->generate($now);
$q=$pdo->prepare("SELECT tenant_id,COUNT(*) AS amount FROM notification_outbox WHERE user_id=? AND kind='due_today' GROUP BY tenant_id");$q->execute([$user]);$rows=$q->fetchAll();
check(count($rows)===2 && array_column($rows,'amount')===[3,3],'Deduplicação diária por Workspace e canal');
$pdo->prepare("UPDATE notification_outbox SET available_at='2099-01-01' WHERE user_id<>? AND status IN ('pending','retry')")->execute([$user]);
$bell=new NotificationWorker($pdo);$bell->run($now,500);
$sa=new NotificationService($pdo,$a);$sb=new NotificationService($pdo,$b);
check($sa->unread($user)===1 && $sb->unread($user)===1,'Contadores independentes');
$ia=$sa->page($user,1)['items'][0];$ib=$sb->page($user,1)['items'][0];
foreach ([[$a,$ia],[$b,$ib]] as [$context,$item]) {
    check(count($item['links'])===1 && str_contains($item['links'][0]['url'],'/workspaces/'.$context->tenantId.'/'),'Resumo e links delimitados');
    check(str_starts_with($item['more_url'],'/workspaces/'.$context->tenantId.'/tasks?'),'Link de todas as tarefas contextualizado');
}
check(!$sa->markRead($user,(int)$ib['notification_id']),'Leitura cruzada recusada');
check($sa->markRead($user,(int)$ia['notification_id']) && $sa->markRead($user,(int)$ia['notification_id']),'Leitura local idempotente');
check($sa->unread($user)===0 && $sb->unread($user)===1,'Leitura não afeta outro Workspace');
$csrf=boardLogin($email);$rootA='/workspaces/'.$a->tenantId;$rootB='/workspaces/'.$b->tenantId;
[$status,$html]=request($rootB.'/notifications');check($status===200 && str_contains($html,$rootB.'/notifications/'.$ib['notification_id'].'/read') && !str_contains($html,'Entrega-'.$a->tenantId),'Central sem JS e formulário contextualizado');
check(request($rootA.'/notifications/'.$ib['notification_id'].'/read',['_token'=>$csrf])[0]===404,'ID cruzado HTTP');
check(request($rootB.'/notifications/'.$ib['notification_id'].'/read',[])[0]===419,'CSRF');
check(json_decode(request($rootA.'/notifications?format=count')[1],true)['unread']===0 && json_decode(request($rootB.'/notifications?format=count')[1],true)['unread']===1,'Contagem HTTP por contexto');
// Os canais externos não dependem da sessão nem do Workspace aberto no navegador.
$pdo->prepare('INSERT INTO push_subscriptions(user_id,endpoint,endpoint_hash,public_key,auth_token) VALUES (?,?,?,?,?)')->execute([$user,'https://fcm.googleapis.com/fcm/send/'.$suffix,hash('sha256',$suffix),'test','test']);
$push=new class implements PushGateway {
    public array $payloads=[];
    public function send(array $subscription,array $payload): int {$this->payloads[]=$payload;return 201;}
};
$external=new ExternalNotificationWorker($pdo,$config,$push);$before=count(messagesFor($email));$external->run($now,500);
$messages=messagesFor($email);check(count($messages)===$before+2,'Um e-mail diário por Workspace');
foreach (array_slice($messages,0,2) as $message) {
    $mail=json_decode(file_get_contents($mailpit.'/api/v1/message/'.$message['ID']),true,flags:JSON_THROW_ON_ERROR)['Text'];
    check(str_contains($mail,$rootA.'/') xor str_contains($mail,$rootB.'/'),'E-mail não mistura Workspaces');
}
check(count($push->payloads)===2,'Push por Workspace em inscrição global');
check(array_unique(array_column($push->payloads,'url'))===[$ia['more_url'],$ib['more_url']],'Push mantém contexto do evento');
// Revogar um vínculo não afeta o outro, inclusive com pendências de todos os canais.
$outbox=new NotificationOutbox($pdo);
foreach ([$a,$b] as $context) $outbox->enqueue($user,'assigned',$day,['tenant_id'=>$context->tenantId,'project_id'=>$projects[$context->tenantId],'task_id'=>$tasks[$context->tenantId]],'revocation');
// Fixture transfere a propriedade e remove dependências existentes antes do vínculo.
$pdo->prepare("INSERT INTO project_members(tenant_id,project_id,user_id,role) VALUES (?,?,?,'admin')")->execute([$b->tenantId,$projects[$b->tenantId],$owner]);
$pdo->prepare('UPDATE projects SET owner_id=? WHERE id=?')->execute([$owner,$projects[$b->tenantId]]);
$pdo->prepare('DELETE FROM task_assignees WHERE tenant_id=? AND user_id=?')->execute([$b->tenantId,$user]);
$pdo->prepare('DELETE FROM project_members WHERE tenant_id=? AND user_id=?')->execute([$b->tenantId,$user]);
$pdo->prepare('DELETE FROM tenant_users WHERE tenant_id=? AND user_id=?')->execute([$b->tenantId,$user]);
foreach ([fn()=>$sb->page($user,1),fn()=>$sb->unread($user),fn()=>$sb->markRead($user,(int)$ib['notification_id'])] as $operation) {
    try {$operation();throw new RuntimeException('Revogação ignorada');}catch(HttpException $e){check($e->getCode()===404,'Revogação retorna 404');}
}
check($global->content($ib)['links']===[],'Conteúdo revalida revogação');
check(request($rootB.'/notifications')[0]===404 && request($rootB.'/notifications?format=count')[0]===404,'Revogação HTTP e JSON');
$before=count($push->payloads);$beforeMail=count(messagesFor($email));$bell->run($now,500);$external->run($now,500);
check(count($push->payloads)===$before+1 && count(messagesFor($email))===$beforeMail+1,'Revogação impede Push e SMTP apenas no Workspace removido');
$q=$pdo->prepare("SELECT COUNT(*) FROM notification_outbox WHERE user_id=? AND tenant_id=? AND status='cancelled' AND kind='assigned'");$q->execute([$user,$b->tenantId]);check((int)$q->fetchColumn()===3,'Três canais cancelados após revogação');
check($sa->preferences($user)===$sb->preferences($user),'Preferências permanecem globais');
// A leitura segue a mesma ordem de bloqueio do vínculo antes de escrever.
$pdo->beginTransaction();
(new WorkspaceContext($user,$a->tenantId))->authorize($pdo,$user,true);
$child=proc_open([PHP_BINARY,__DIR__.'/notify-worker-child.php',$now->format('c'),'read',(string)$user,(string)$a->tenantId,(string)$ia['notification_id']],[0=>['pipe','r'],1=>['pipe','w'],2=>STDERR],$pipes,dirname(__DIR__));
check(is_resource($child) && trim(fgets($pipes[1]))==='ready','Processo de leitura pronto');
usleep(200000);check(proc_get_status($child)['running'],'Leitura aguarda bloqueio do Workspace');
$pdo->commit();fclose($pipes[0]);fclose($pipes[1]);check(proc_close($child)===0,'Leitura conclui após liberar o vínculo');
// A estrutura rejeita resumo sem Workspace e tenant inexistente.
foreach ([null,PHP_INT_MAX] as $invalidTenant) {
    try {$outbox->enqueue($user,'due_today',$day,['tenant_id'=>$invalidTenant,'due_on'=>$day],'invalid-scope');throw new RuntimeException('Escopo inválido aceito');}
    catch(PDOException $e){check($e->getCode()==='23000','CHECK/FK protege escopo');}
}

echo "OK: central, HTTP, CSRF, leitura, deduplicação, prazos, SMTP, Push, revogação e preferências por Workspace.\n";
