<?php

declare(strict_types=1);

use App\Infrastructure\Database;
use App\Repositories\ProjectRepository;
use App\Services\{AuthService,BoardService,ProjectService,QuickNoteService,ReportingService,WorkspaceContext};
use App\Validators\ReportFilters;
use Pecee\SimpleRouter\Exceptions\HttpException;

require __DIR__ . '/http.php';
if (!str_starts_with($config['database']['name'], 'kanban_workspace_')) throw new RuntimeException('Use banco descartável kanban_workspace_*.');
function blocked(callable $operation, int $status=404): void {
    try { $operation(); } catch (HttpException $e) { check($e->getCode()===$status,'Status esperado: '.$e->getCode()); return; }
    throw new RuntimeException('Operação fora do contexto aceita');
}
$suffix=bin2hex(random_bytes(5)); $password='Workspaces-modulos-123';
$auth=new AuthService($pdo); $email="modules-$suffix@example.com";
$me=$auth->register('Módulos',$email,$password, 'Workspace de testes'); $other=$auth->register('Outro',"other-modules-$suffix@example.com",$password, 'Workspace de testes');
$pdo->prepare('UPDATE users SET email_verified_at=UTC_TIMESTAMP() WHERE id IN (?,?)')->execute([$me,$other]);
$a=WorkspaceContext::initial($pdo,$me); $otherContext=WorkspaceContext::initial($pdo,$other);
$pdo->prepare("INSERT INTO tenant_users(tenant_id,user_id,role) VALUES (?,?,'member')")->execute([$otherContext->tenantId,$me]);
$b=WorkspaceContext::resolve($pdo,$me,(string)$otherContext->tenantId);
$data=['name'=>"Projeto-A-$suffix",'description'=>'','starts_on'=>'','due_on'=>''];
$pa=(new ProjectService($pdo,$a))->create($me,$data);
$pb=(new ProjectService($pdo,$b))->create($me,array_replace($data,['name'=>"Projeto-B-$suffix"]));
$private=(new ProjectService($pdo,$otherContext))->create($other,array_replace($data,['name'=>"Privado-$suffix"]));
check((int)(new ProjectRepository($pdo,$b))->find($me,$pb)['tenant_id']===$b->tenantId,'Membro cria no Workspace ativo');
foreach ([$a,$b] as $context) {
    $project=$context===$a?$pa:$pb; $foreign=$context===$a?$pb:$pa;
    $repo=new ProjectRepository($pdo,$context);
    check(array_column($repo->page($me,false,1)['items'],'id')===[$project],'Listagem delimitada');
    blocked(fn()=>$repo->find($me,$foreign)); blocked(fn()=>$repo->find($me,$private));
    $board=new BoardService($pdo,$context); $state=$board->state($me,$project);
    $state=$board->mutate($me,$project,$state['version'],'task.create',['column_id'=>$state['columns'][0]['id'],'title'=>"Tarefa-$project-$suffix"]);
    $task=$state['columns'][0]['tasks'][0]['id'];
    $board->mutate($me,$project,$state['version'],'task.update',['task_id'=>$task,'title'=>"Tarefa-$project-$suffix",'description'=>'','priority'=>'normal','starts_on'=>'','due_on'=>gmdate('Y-m-d'),'assignee_ids'=>[$me]]);
    $reports=new ReportingService($pdo,$context);
    check(array_column($reports->projects($me),'id')===[$project],'Destinos de relatório delimitados');
    $dash=$reports->dashboard($me,1);
    check($dash['counts']['pending']===1 && $dash['counts']['active_projects']===1,'Indicadores delimitados');
    check(array_column($dash['projects']['items'],'id')===[$project] && count($dash['upcoming'])===1,'Progresso e prazos delimitados');
    check(array_unique(array_column($dash['recent'],'project_id'))===[$project],'Atividades delimitadas');
    check($reports->tasks($me,ReportFilters::parse([]))['total']===1,'Tarefas delimitadas');
    blocked(fn()=>$reports->tasks($me,ReportFilters::parse(['project_id'=>(string)$foreign])));
    blocked(fn()=>$reports->history($me,ReportFilters::parse(['project_id'=>(string)$foreign],true),true));
    check(array_unique(array_column($reports->history($me,ReportFilters::parse([],true),true)['items'],'project_id'))===[$project],'Histórico delimitado');
}
$notesA=new QuickNoteService($pdo,$a); $notesB=new QuickNoteService($pdo,$b);
$na=$notesA->create($me,"Inbox-A-$suffix",null); $nb=$notesB->create($me,"Inbox-B-$suffix",null);
check(array_column($notesA->page($me,null)['items'],'id')===[$na] && array_column($notesB->page($me,null)['items'],'id')===[$nb],'Inbox por Workspace');
blocked(fn()=>$notesA->create($me,'fora',$pb)); blocked(fn()=>$notesA->page($me,$pb));
blocked(fn()=>$notesA->update($me,$nb,'fora')); blocked(fn()=>$notesA->delete($me,$nb));
blocked(fn()=>$notesA->update($me,$na,'fora',['project_id'=>$pb,'revision'=>1]));
blocked(fn()=>$notesA->convert($me,$na,$pb,1,'fora',1)); blocked(fn()=>$notesA->recipients($me,$nb));
check($notesB->page($me,null)['items'][0]['revision']==1,'Tentativas não alteram notas');
$notesA->update($me,$na,'<script>nota-segura</script>',['project_id'=>$pa,'revision'=>1]);
$notesA->update($me,$na,'retorno',['project_id'=>null,'revision'=>2]);
check($notesA->page($me,null)['total']===1,'Movimentação local preservada');
check(array_column($notesA->projects($me),'id')===[$pa],'Destinos de notas delimitados');
$reportsA=new ReportingService($pdo,$a); $version=$reportsA->dashboardVersion($me,1);
$notesToken=$notesA->sync($me,null)['token'];
(new ProjectService($pdo,$b))->change($me,$pb,'update',array_replace($data,['name'=>'Alterado em B']));
$notesB->update($me,$nb,'Alterado em B',['revision'=>1]);
check($reportsA->dashboardVersion($me,1)===$version && $notesA->sync($me,null)['token']===$notesToken,'Versões independentes');
check($version!==(new ReportingService($pdo,$b))->dashboardVersion($me,1),'Versões incluem contexto');
$csrf=token(request('/login')[1]); check(request('/login',['_token'=>$csrf,'email'=>$email,'password'=>$password])[0]===303,'Login');
$rootA='/workspaces/'.$a->tenantId; $rootB='/workspaces/'.$b->tenantId;
foreach (['/projects','/projects/new','/dashboard','/tasks','/history','/notes/new',"/projects/$pa", "/projects/$pa/board", "/projects/$pa/history"] as $path) {
    [$status,$html]=request($rootA.$path); check($status===200,'HTTP '.$path.': '.$status);
    check(str_contains($html,'data-workspace-path="'.$rootA.'"'),'Contexto do documento');
    check(!str_contains($html,"Projeto-B-$suffix") && !str_contains($html,"Inbox-B-$suffix"),'HTML não mistura Workspaces');
    check(str_contains($html,'href="'.$rootA.'/tasks"'),'Navegação preserva contexto');
}
$newForm=request($rootB.'/projects/new')[1];
check(str_contains($newForm,'action="'.$rootB.'/projects"'),'Formulário de criação preserva Workspace sem JS');
$csrf=token($newForm);
check(request($rootB.'/projects',[])[0]===419,'CSRF criação');
[$status,,$headers]=request($rootB.'/projects',['_token'=>$csrf]+array_replace($data,['name'=>'Criado HTTP em B']));
check($status===303 && str_contains($headers,'Location: '.$rootB.'/projects/'),'Criação e redirecionamento contextual');
check(request($rootA.'/notes',[])[0]===419,'CSRF notas');
check(request($rootA.'/notes',['_token'=>$csrf,'body'=>'Ataque','project_id'=>(string)$pb])[0]===404,'POST destino de outro Workspace');
check(request($rootA.'/notes/'.$nb.'/delete',['_token'=>$csrf,'revision'=>'2'])[0]===404,'POST ID de outro Workspace');
[$status,$json]=request('/workspaces/999999999/notes/state');
check($status===404 && isset(json_decode($json,true)['message']),'API de notas preserva JSON em contexto indisponível');
foreach (['/tasks?project_id='.$pb,'/history?project_id='.$pb,'/notes/columns?project_id='.$pb,'/notes/state?project_id='.$pb] as $path) check(request($rootA.$path)[0]===404,'Filtro fora do contexto');
foreach (['/dashboard?sync=version','/dashboard?sync=state','/notes/version','/notes/state','/notes/projects'] as $path) {
    [$status,$json]=request($rootA.$path); check($status===200 && is_array(json_decode($json,true)),'Sync JSON '.$path);
    check(!str_contains($json,"Inbox-B-$suffix"),'Sync não mistura dados');
}
// Revogação espera a operação e bloqueia novas leituras/escritas, inclusive Inbox.
$second=Database::connect($config['database']); $second->exec('SET SESSION innodb_lock_wait_timeout=1');
$pdo->beginTransaction();
try {
    $b->authorize($pdo,$me,true);
    try { $second->prepare('DELETE FROM tenant_users WHERE tenant_id=? AND user_id=?')->execute([$b->tenantId,$me]); throw new RuntimeException('Vínculo não bloqueado'); }
    catch(PDOException $e) { check((int)$e->errorInfo[1]===1205,'Bloqueio concorrente'); }
} finally { $pdo->rollBack(); }
// Preparação da fixture, sem implementar a saída/transferência da etapa 7.
$pdo->beginTransaction();
$pdo->prepare('UPDATE projects SET owner_id=? WHERE tenant_id=? AND owner_id=?')->execute([$other,$b->tenantId,$me]);
$pdo->prepare('UPDATE quick_notes SET user_id=? WHERE tenant_id=? AND user_id=?')->execute([$other,$b->tenantId,$me]);
$pdo->prepare('DELETE FROM task_assignees WHERE tenant_id=? AND user_id=?')->execute([$b->tenantId,$me]);
$pdo->prepare('DELETE FROM project_members WHERE tenant_id=? AND user_id=?')->execute([$b->tenantId,$me]);
$pdo->prepare('DELETE FROM tenant_users WHERE tenant_id=? AND user_id=?')->execute([$b->tenantId,$me]);
$pdo->commit();
blocked(fn()=>$notesB->page($me,null)); blocked(fn()=>$notesB->create($me,'Revogado',null));
blocked(fn()=>(new ProjectService($pdo,$b))->create($me,$data));
blocked(fn()=>(new ReportingService($pdo,$b))->dashboardVersion($me,1));
foreach (['/projects','/dashboard?sync=state','/notes/state','/tasks','/history'] as $path) check(request($rootB.$path)[0]===404,'Revogação HTTP '.$path);
check(request($rootA.'/tasks')[0]===200 && !$pdo->inTransaction(),'Outro Workspace preservado e rollback');
echo "OK: módulos e HTTP isolados, criação por membro, Inbox, movimentação, versões, CSRF, revogação e bloqueio concorrente.\n";
