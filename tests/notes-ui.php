<?php

declare(strict_types=1);
require __DIR__.'/http.php';
use App\Services\{AuthService,ProjectService,QuickNoteService,BoardService};
$auth=new AuthService($pdo);$projects=new ProjectService($pdo);$notes=new QuickNoteService($pdo);$suffix=bin2hex(random_bytes(6));$users=[];
foreach(['owner','other'] as $name){$email='notes-ui-'.$name.'-'.$suffix.'@example.com';$id=$auth->register($name,$email,'Senha-notas-ui-123');$pdo->prepare('UPDATE users SET email_verified_at=UTC_TIMESTAMP() WHERE id=?')->execute([$id]);$users[$name]=compact('id','email');}
$owner=$users['owner']['id'];$other=$users['other']['id'];$project=$projects->create($owner,['name'=>'Ideias para o site','description'=>'Um contexto para o projeto.','starts_on'=>'','due_on'=>'']);
$tenant=(int)(new App\Repositories\ProjectRepository($pdo))->find($owner,$project)['tenant_id'];
$pdo->prepare('INSERT INTO tenant_users(tenant_id,user_id) VALUES (?,?)')->execute([$tenant,$other]);$pdo->prepare("INSERT INTO project_members(tenant_id,project_id,user_id,role) VALUES (?,?,?,'member')")->execute([$tenant,$project,$other]);
$foreign=$projects->create($other,['name'=>'Projeto que não deve aparecer','description'=>'','starts_on'=>'','due_on'=>'']);
$notes->create($owner,'Uma ideia para começar',null);$notes->create($owner,"<script>window.noteXss=true</script>\n".str_repeat('ideia',80),null);$notes->create($owner,'Projeto: organizar o lançamento',$project);$notes->create($other,'NOTA-PRIVADA-OUTRO',null);
$state=(new BoardService($pdo))->state($owner,$project);
$viewerTask=$notes->convert($other,$notes->create($other,'Tarefa criada antes de virar visualizador',null),$project,$state['columns'][0]['id']);
$pdo->prepare("UPDATE project_members SET role='viewer' WHERE tenant_id=? AND project_id=? AND user_id=?")->execute([$tenant,$project,$other]);
$fixture=compact('users','project','foreign','viewerTask')+['column'=>$state['columns'][0]['id']];$path=dirname(__DIR__).'/storage/previews';if(!is_dir($path))mkdir($path,0775,true);file_put_contents($path.'/notes-fixture.json',json_encode($fixture,JSON_THROW_ON_ERROR));
echo "OK: fixture privada do Brainstorm em banco descartável.\n";
