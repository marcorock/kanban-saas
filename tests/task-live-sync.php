<?php

declare(strict_types=1);
require __DIR__.'/board-sync.php';
$liveProject=$projectService->create($ownerId,['name'=>'Painel sincronizado','description'=>'','starts_on'=>'','due_on'=>'']);
foreach(['member'=>'member','viewer'=>'viewer'] as $name=>$role){$uid=$users[$name]['id'];$pdo->prepare('INSERT IGNORE INTO tenant_users(tenant_id,user_id) VALUES (?,?)')->execute([$tenantId,$uid]);$pdo->prepare('INSERT INTO project_members(tenant_id,project_id,user_id,role) VALUES (?,?,?,?)')->execute([$tenantId,$liveProject,$uid,$role]);}
$live=$service->state($ownerId,$liveProject);$live=$service->mutate($ownerId,$liveProject,$live['version'],'task.create',['column_id'=>$live['columns'][0]['id'],'title'=>'Tarefa viva']);$liveTask=$live['columns'][0]['tasks'][0]['id'];
$meta=$service->version($ownerId,$liveProject,$liveTask);check($meta['task']===['exists'=>true,'can_write'=>true,'can_delete'=>true,'archived'=>false],'Metadata confere existência/autoria/poderes');
$before=(int)$pdo->query("SHOW SESSION STATUS LIKE 'Com_select'")->fetch()['Value'];$service->version($ownerId,$liveProject,$liveTask);$after=(int)$pdo->query("SHOW SESSION STATUS LIKE 'Com_select'")->fetch()['Value'];check($after-$before===2,'Apenas dois SELECTs com tarefa, sem corpo/checklists/comentários');
function liveGet(int $id):array{global $liveProject;[$s,$b,$h]=request('/projects/'.$liveProject.'/board/version?task_id='.$id);return [$s,json_decode($b,true),$h];}
$csrf=boardLogin($users['owner']['email']);[$s,$m,$h]=liveGet($liveTask);check($s===200&&$m['token']===$live['sync_token']&&str_contains($h,'no-store')&&!isset($m['details']),'Endpoint leve privado');
$old=$m['token'];$live=$service->mutate($ownerId,$liveProject,$live['version'],'comment.create',['task_id'=>$liveTask,'body'=>'Mensagem remota']);check(liveGet($liveTask)[1]['token']!==$old,'Comentário muda versão');
$details=$service->task($ownerId,$liveProject,$liveTask);check($details['board']['sync_token']===liveGet($liveTask)[1]['token']&&$details['details']['comments']['items'][0]['body']==='Mensagem remota','Detalhes e token consistentes');
$live=$service->mutate($ownerId,$liveProject,$live['version'],'checklist.create',['task_id'=>$liveTask,'title'=>'Checklist remoto']);$list=$service->task($ownerId,$liveProject,$liveTask)['details']['checklists'][0]['id'];$live=$service->mutate($ownerId,$liveProject,$live['version'],'item.create',['task_id'=>$liveTask,'checklist_id'=>$list,'title'=>'Item remoto']);
$details=$service->task($ownerId,$liveProject,$liveTask);check(count($details['details']['checklists'][0]['items'])===1,'Snapshot inclui checklist');
$foreign=$projectService->create($ownerId,['name'=>'Outra tarefa','description'=>'','starts_on'=>'','due_on'=>'']);$other=$service->state($ownerId,$foreign);$other=$service->mutate($ownerId,$foreign,$other['version'],'task.create',['column_id'=>$other['columns'][0]['id'],'title'=>'SEGREDO-OUTRO-PROJETO']);$foreignTask=$other['columns'][0]['tasks'][0]['id'];check(!liveGet($foreignTask)[1]['task']['exists'],'Tarefa de outro projeto não identificada como existente');
$csrf=boardLogin($users['viewer']['email']);check(liveGet($liveTask)[1]['task']['can_write']===false&&!liveGet($liveTask)[1]['task']['can_delete'],'Visualizador não recebe poderes');
$csrf=boardLogin($users['member']['email']);$pdo->prepare("UPDATE project_members SET role='viewer' WHERE project_id=? AND user_id=?")->execute([$liveProject,$users['member']['id']]);check(!liveGet($liveTask)[1]['task']['can_write'],'Mudança de papel reconferida sem depender de board_version');
$pdo->prepare('DELETE FROM project_members WHERE project_id=? AND user_id=?')->execute([$liveProject,$users['member']['id']]);check(liveGet($liveTask)[0]===404,'Revogação impede metadata');
$csrf=boardLogin($users['owner']['email']);$projectService->change($ownerId,$liveProject,'archive');check(liveGet($liveTask)[1]['task']['archived']&&!liveGet($liveTask)[1]['task']['can_delete'],'Arquivo bloqueia até exclusão do autor');$projectService->change($ownerId,$liveProject,'restore');
check(request('/projects/'.$liveProject.'/board/version?task_id[]=1')[0]===422,'Array de tarefa inválido');
$live=$service->mutate($ownerId,$liveProject,$live['version'],'task.delete',['task_id'=>$liveTask,'confirm'=>true]);check(!liveGet($liveTask)[1]['task']['exists'],'Remoção detectada sem confundir com revogação do projeto');
$csrf=boardLogin($users['outsider']['email']);check(liveGet($liveTask)[0]===404,'Outro tenant não recebe estado');$cookie='';check(liveGet($liveTask)[0]===401,'Metadata exige autenticação');
echo "OK: painel ao vivo, metadata de tarefa em dois SELECTs, existência/autoria, tenant/projeto, versões, comentários/checklists, papéis, arquivo, remoção e autorização.\n";
