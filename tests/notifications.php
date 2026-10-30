<?php

declare(strict_types=1);
require __DIR__.'/board-http.php';
use App\Services\{AuthService,ProjectService,BoardService,NotificationService,NotificationWorker,NotificationOutbox,DeadlineNotificationService,ProjectMemberService};
$password='Senha-notificacao-segura-123';$auth=new AuthService($pdo);$projects=new ProjectService($pdo);$boards=new BoardService($pdo);$members=new ProjectMemberService($pdo);$service=new NotificationService($pdo);$worker=new NotificationWorker($pdo);
$suffix=bin2hex(random_bytes(5));$users=[];
foreach (['owner','member','viewer','outside','unverified'] as $name) {
    $email='notify-'.$name.'-'.$suffix.'@example.com';$id=$auth->register($name,$email,$password);
    if ($name!=='unverified') $pdo->prepare('UPDATE users SET email_verified_at=UTC_TIMESTAMP() WHERE id=?')->execute([$id]);
    $users[$name]=['id'=>$id,'email'=>$email];
}
$owner=$users['owner']['id'];$member=$users['member']['id'];$viewer=$users['viewer']['id'];$outside=$users['outside']['id'];
$projectId=$projects->create($owner,['name'=>'Projeto de avisos','description'=>'Não notificar descrição','starts_on'=>'','due_on'=>'']);
$project=(new App\Repositories\ProjectRepository($pdo))->find($owner,$projectId);$tenant=(int)$project['tenant_id'];
foreach (['member'=>'member','viewer'=>'viewer','outside'=>null] as $key=>$role) {
    $pdo->prepare('INSERT INTO tenant_users(tenant_id,user_id) VALUES (?,?)')->execute([$tenant,$users[$key]['id']]);
    if ($role) $pdo->prepare('INSERT INTO project_members(tenant_id,project_id,user_id,role) VALUES (?,?,?,?)')->execute([$tenant,$projectId,$users[$key]['id'],$role]);
}
$state=$boards->state($owner,$projectId);
function change(string $action,array $data): array {global $state,$boards,$owner,$projectId;$state=$boards->mutate($owner,$projectId,$state['version'],$action,$data);return $state;}
function amount(int $user,string $kind=''): int {global $pdo;$q=$pdo->prepare('SELECT COUNT(*) FROM notifications n JOIN notification_outbox o ON o.id=n.outbox_id WHERE n.user_id=?'.($kind!==''?' AND o.kind=?':''));$q->execute($kind!==''?[$user,$kind]:[$user]);return (int)$q->fetchColumn();}
function flags(int $user,bool $bell,bool $push=false,bool $email=false): void {global $service,$auth;$service->savePreferences($user,(int)$auth->user($user)['session_version'],['bell_enabled'=>$bell,'push_enabled'=>$push,'email_enabled'=>$email]);}
function updateTask(int $id,string $due,array $assigned): void {change('task.update',['task_id'=>$id,'title'=>'Entrega <script>window.noticeXss=true</script>','description'=>'SEGREDO-DESCRICAO','priority'=>'normal','due_on'=>$due,'assignee_ids'=>$assigned]);}
$now=new DateTimeImmutable('2035-01-10T11:00:00Z');
$initialNotifications=(int)$pdo->query('SELECT COUNT(*) FROM notifications')->fetchColumn();
check($service->preferences($member)===['bell_enabled'=>true,'push_enabled'=>false,'email_enabled'=>false],'Padrões dos canais');
change('task.create',['column_id'=>$state['columns'][0]['id'],'title'=>'Entrega']);$task=$state['columns'][0]['tasks'][0]['id'];
flags($viewer,false);updateTask($task,'2035-01-10',[$owner,$member,$viewer]);
check((int)$pdo->query('SELECT COUNT(*) FROM notifications')->fetchColumn()===$initialNotifications,'Requisição não entrega');
$worker->run($now,500);check(amount($member,'assigned')===1&&amount($viewer)===0&&amount($owner)===0,'Atribuição exceto ator; canal desligado');
$state=$boards->mutate($member,$projectId,$state['version'],'comment.create',['task_id'=>$task,'body'=>'SEGREDO-COMENTARIO convite@example.com']);
$worker->run($now,500);check(amount($owner,'comment')===1&&amount($member,'comment')===0,'Comentário não notifica autor');
change('comment.create',['task_id'=>$task,'body'=>'Outro comentário']);change('comment.create',['task_id'=>$task,'body'=>'Mais um comentário']);
$worker->run($now,500);check(amount($member,'comment')===2,'Mensagens distintas não colapsam no mesmo dia');
$event=$pdo->query("SELECT id FROM activity_logs WHERE project_id={$projectId} AND action='comment.created' ORDER BY id DESC LIMIT 1")->fetchColumn();
$comment=$pdo->query("SELECT id FROM task_comments WHERE task_id={$task} ORDER BY id DESC LIMIT 1")->fetchColumn();
$pdo->beginTransaction();(new NotificationOutbox($pdo))->capture($project,$owner,'comment.created',null,['comment_id'=>(int)$comment],$task,(int)$event);$pdo->commit();
$worker->run($now,500);check(amount($member,'comment')===2,'Reprocessamento do mesmo evento deduplicado');
(new NotificationOutbox($pdo))->enqueue($member,'comment','2035-02-01',['tenant_id'=>$tenant,'project_id'=>$projectId,'task_id'=>$task,'actor_id'=>$owner,'event_id'=>(int)$event,'subject_id'=>(int)$comment],'event:'.$event);$worker->run($now,500);check(amount($member,'comment')===2,'Replay do evento em outro dia deduplicado');
$members->member($owner,$projectId,$member,'role','admin');$state=$boards->state($owner,$projectId);$worker->run($now,500);check(amount($member,'role_changed')===1,'Papel alterado');
// A outbox acompanha rollback do evento e rollback da transação de negócio.
$before=(int)$pdo->query('SELECT COUNT(*) FROM notification_outbox')->fetchColumn();
$beforeLog=(int)$pdo->query("SELECT COUNT(*) FROM activity_logs WHERE project_id={$projectId}")->fetchColumn();
$pdo->exec("CREATE TRIGGER reject_notify_outbox BEFORE INSERT ON notification_outbox FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='test failure'");
try {try {change('comment.create',['task_id'=>$task,'body'=>'NAO-PERSISTIR']);check(false,'Falha esperada');}catch(PDOException){}check((int)$pdo->query("SELECT COUNT(*) FROM task_comments WHERE body='NAO-PERSISTIR'")->fetchColumn()===0,'Evento/comentário/outbox atômicos');}finally{$pdo->exec('DROP TRIGGER reject_notify_outbox');}
check((int)$pdo->query('SELECT COUNT(*) FROM notification_outbox')->fetchColumn()===$before,'Rollback da outbox');check((int)$pdo->query("SELECT COUNT(*) FROM activity_logs WHERE project_id={$projectId}")->fetchColumn()===$beforeLog,'Rollback da auditoria');$state=$boards->state($owner,$projectId);
// Reconferir atribuição e tenant no envio e na central.
change('comment.create',['task_id'=>$task,'body'=>'Antes da remoção']);updateTask($task,'2035-01-10',[$owner,$viewer]);$worker->run($now,500);
check(amount($member,'comment')===2&&$service->unread($member)===1,'Notificações de tarefa ocultas após remover atribuição; papel ainda visível');
(new NotificationOutbox($pdo))->enqueue($outside,'assigned','2035-01-10',['tenant_id'=>$tenant,'project_id'=>$projectId,'task_id'=>$task,'actor_id'=>$owner],'invalid-membership');$worker->run($now,500);check(amount($outside)===0,'Tenant sem projeto não autoriza entrega');
updateTask($task,'2035-01-10',[$owner,$member,$viewer]);$worker->run($now,500);
$members->member($owner,$projectId,$member,'remove');$state=$boards->state($owner,$projectId);
$pdo->prepare('DELETE FROM tenant_users WHERE tenant_id=? AND user_id=?')->execute([$tenant,$member]);change('comment.create',['task_id'=>$task,'body'=>'Sem tenant']);$worker->run($now,500);check($service->unread($member)===0,'Tenant/projeto removidos ocultam a central');
$pdo->prepare('INSERT INTO tenant_users(tenant_id,user_id) VALUES (?,?)')->execute([$tenant,$member]);
$pdo->prepare("INSERT INTO project_members(tenant_id,project_id,user_id,role) VALUES (?,?,?,'admin')")->execute([$tenant,$projectId,$member]);
updateTask($task,'2035-01-10',[$owner,$member,$viewer]);$worker->run($now,500);
flags($member,false,true,true);change('comment.create',['task_id'=>$task,'body'=>'Canais desligados']);$before=amount($member);$worker->run($now,500);check(amount($member)===$before,'Preferência no envio; A não entrega push/email');flags($member,true);
// Geração às 08h locais, fuso/virada e um vencido por tarefa/prazo.
change('task.create',['column_id'=>$state['columns'][0]['id'],'title'=>'Já concluída']);$completed=end($state['columns'][0]['tasks'])['id'];updateTask($completed,'2035-01-09',[$member]);change('task.move',['task_id'=>$completed,'column_id'=>$state['columns'][4]['id'],'position'=>0]);
$deadlines=new DeadlineNotificationService($pdo);$deadlines->generate(new DateTimeImmutable('2035-01-10T10:59:59Z'));
check((int)$pdo->query("SELECT COUNT(*) FROM notification_outbox WHERE kind='due_today' AND user_id={$member}")->fetchColumn()===0,'Antes de 08h São Paulo');
$generators=[];for($i=0;$i<2;$i++)$generators[]=proc_open([PHP_BINARY,__DIR__.'/notify-worker-child.php',$now->format('c'),'deadlines'],[STDIN,STDOUT,STDERR],$pipes,dirname(__DIR__));foreach($generators as $job)check(is_resource($job)&&proc_close($job)===0,'Gerador concorrente');
$deadlines->generate($now);do{$worker->run($now,500);}while((int)$pdo->query("SELECT COUNT(*) FROM notification_outbox WHERE status='pending' AND channel='bell'")->fetchColumn()>0);check(amount($member,'due_today')===1,'Resumo diário deduplicado');
change('task.create',['column_id'=>$state['columns'][0]['id'],'title'=>'Outra entrega']);$second=end($state['columns'][0]['tasks'])['id'];updateTask($second,'2035-01-10',[$member]);$worker->run($now,500);$deadlines->generate($now);$worker->run($now,500);check(amount($member,'due_today')===1,'Um resumo por usuário/dia, várias tarefas');
$dayAfter=new DateTimeImmutable('2035-01-11T03:00:00Z');$deadlines->generate($dayAfter);$deadlines->generate($dayAfter);do{$worker->run($dayAfter,500);}while((int)$pdo->query("SELECT COUNT(*) FROM notification_outbox WHERE status='pending' AND channel='bell'")->fetchColumn()>0);check(amount($member,'overdue')===2,'Primeiro ciclo após vencer na virada local');
updateTask($second,'2035-01-09',[$member]);$deadlines->generate($dayAfter);$worker->run($dayAfter,500);check(amount($member,'overdue')===3,'Novo prazo permite novo vencido');
// Dois workers, leases abandonados e falha com retentativa limitada.
change('comment.create',['task_id'=>$task,'body'=>'Concorrência']);
$jobs=[];for($i=0;$i<2;$i++)$jobs[]=proc_open([PHP_BINARY,__DIR__.'/notify-worker-child.php',$dayAfter->format('c')],[STDIN,STDOUT,STDERR],$pipes,dirname(__DIR__));
foreach($jobs as $job)check(is_resource($job)&&proc_close($job)===0,'Worker concorrente');
check((int)$pdo->query('SELECT COUNT(*)-COUNT(DISTINCT outbox_id) FROM notifications')->fetchColumn()===0,'Entrega única concorrente');
change('comment.create',['task_id'=>$task,'body'=>'Lease']);$lease=(int)$pdo->query("SELECT MAX(id) FROM notification_outbox WHERE user_id={$member}")->fetchColumn();
$pdo->prepare("UPDATE notification_outbox SET status='processing',attempts=1,lock_token=?,locked_until='2035-01-10 00:00:00' WHERE id=?")->execute([str_repeat('a',32),$lease]);$worker->run($dayAfter,500);check((int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE outbox_id={$lease}")->fetchColumn()===1,'Lease expirado recuperado');
change('comment.create',['task_id'=>$task,'body'=>'Retentar']);$retry=(int)$pdo->query("SELECT MAX(id) FROM notification_outbox WHERE user_id={$member}")->fetchColumn();
$pdo->exec("CREATE TRIGGER reject_notification_delivery BEFORE INSERT ON notifications FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='test failure'");
try {for($i=0;$i<5;$i++)$worker->run($dayAfter->modify('+'.($i*2).' days'),500);check($pdo->query("SELECT status FROM notification_outbox WHERE id={$retry}")->fetchColumn()==='failed','Limite de cinco tentativas');}finally{$pdo->exec('DROP TRIGGER reject_notification_delivery');}
$rolesBefore=amount($member,'role_changed');
foreach (['viewer','member','admin'] as $role) {$members->member($owner,$projectId,$member,'role',$role);$worker->run($dayAfter,500);}
check(amount($member,'role_changed')===$rolesBefore+3,'Membro, Visualizador e Administrador');
// Autorizações HTTP, preferências, leitura idempotente e conteúdo sem dados sensíveis.
$csrf=boardLogin($users['member']['email']);$result=$service->page($member,1);$notice=$result['items'][0]['notification_id'];
check(request('/notifications')[0]===200,'Central autenticada');$html=request('/notifications')[1];check(!str_contains($html,'SEGREDO-DESCRICAO')&&!str_contains($html,'SEGREDO-COMENTARIO')&&!str_contains($html,'convite@example.com'),'Texto mínimo sem conteúdo privado');check(!str_contains($html,'<script>window.noticeXss'),'Escape dos títulos');
check(request('/notifications/'.$notice.'/read',[])[0]===419,'CSRF de leitura');check(request('/notifications/'.$notice.'/read',['_token'=>$csrf])[0]===303,'Marcar lida');check(request('/notifications/'.$notice.'/read',['_token'=>$csrf])[0]===303,'Leitura idempotente');
check(request('/settings/notifications',['_token'=>$csrf,'bell_enabled'=>['1']])[0]===422,'Preferência inválida');check(request('/settings/notifications',[])[0]===419,'CSRF de preferência');
check(request('/settings/notifications',['_token'=>$csrf,'push_enabled'=>'1','email_enabled'=>'1','user_id'=>$owner])[0]===303,'Preferências da própria sessão');check(!$service->preferences($member)['bell_enabled']&&$service->preferences($owner)['bell_enabled'],'Isolamento de preferências');flags($member,true);
$csrf=boardLogin($users['outside']['email']);check(request('/notifications/'.$notice.'/read',['_token'=>$csrf])[0]===404,'IDOR não marca outra conta');check(json_decode(request('/notifications?format=count&user_id='.$member)[1],true)['unread']===0,'Contador não aceita identidade externa');
$csrf=boardLogin($users['unverified']['email']);check(request('/notifications')[0]===403,'Exige e-mail confirmado');$cookie='';check(request('/notifications')[0]===303&&request('/notifications?format=count')[0]===401,'Exige sessão');
$projects->change($owner,$projectId,'archive');check($service->unread($member)===0,'Arquivado oculta avisos');
echo "OK: notificações, 5 eventos, transação, isolamento, preferências, deduplicação, fuso/08h, concorrência, lease, retries, CSRF, IDOR e conteúdo mínimo.\n";
