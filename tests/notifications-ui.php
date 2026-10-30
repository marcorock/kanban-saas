<?php

declare(strict_types=1);
require __DIR__.'/http.php';
$auth=new App\Services\AuthService($pdo);$projects=new App\Services\ProjectService($pdo);$boards=new App\Services\BoardService($pdo);$suffix=bin2hex(random_bytes(5));$users=[];
foreach (['owner'=>'Marina Costa','member'=>'Bruno Lima'] as $role=>$name) {$email='notice-ui-'.$role.'-'.$suffix.'@example.com';$id=$auth->register($name,$email,'Senha-notificacao-ui-123');$pdo->prepare('UPDATE users SET email_verified_at=UTC_TIMESTAMP() WHERE id=?')->execute([$id]);$users[$role]=['id'=>$id,'email'=>$email];}
$owner=$users['owner']['id'];$member=$users['member']['id'];$id=$projects->create($owner,['name'=>'Lançamento do site','description'=>'PRIVADO-DESCRICAO','starts_on'=>'','due_on'=>'']);$tenant=(new App\Repositories\ProjectRepository($pdo))->find($owner,$id)['tenant_id'];
$pdo->prepare('INSERT INTO tenant_users(tenant_id,user_id) VALUES (?,?)')->execute([$tenant,$member]);$pdo->prepare("INSERT INTO project_members(tenant_id,project_id,user_id,role) VALUES (?,?,?,'member')")->execute([$tenant,$id,$member]);
$state=$boards->state($owner,$id);$state=$boards->mutate($owner,$id,$state['version'],'task.create',['column_id'=>$state['columns'][0]['id'],'title'=>'Preparar a próxima entrega']);$task=$state['columns'][0]['tasks'][0]['id'];
$state=$boards->mutate($owner,$id,$state['version'],'task.update',['task_id'=>$task,'title'=>'Preparar a próxima entrega','description'=>'PRIVADO-DESCRICAO','priority'=>'normal','due_on'=>gmdate('Y-m-d',time()-86400),'assignee_ids'=>[$member]]);
for($i=1;$i<=21;$i++)$state=$boards->mutate($owner,$id,$state['version'],'comment.create',['task_id'=>$task,'body'=>'PRIVADO-COMENTARIO-'.$i]);
$now=new DateTimeImmutable('now',new DateTimeZone('UTC'));(new App\Services\DeadlineNotificationService($pdo))->generate($now);(new App\Services\NotificationWorker($pdo))->run($now->modify('+10 seconds'),500);
$directory=dirname(__DIR__).'/storage/previews';if(!is_dir($directory))mkdir($directory,0775,true);
file_put_contents($directory.'/notifications-fixture.json',json_encode(['users'=>$users,'project_id'=>$id,'task_id'=>$task],JSON_THROW_ON_ERROR));
echo "OK: fixture descartável da central, atribuição/comentários/prazo e paginação.\n";
