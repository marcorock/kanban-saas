<?php

declare(strict_types=1);

require __DIR__.'/http.php';
require __DIR__.'/mailpit.php';

use App\Infrastructure\Database;
use App\Repositories\{ProjectRepository, WorkspaceRepository};
use App\Services\{AuthService, Mailer, ProjectMemberService, ProjectService, WorkspaceContext, WorkspaceMemberService};
use Pecee\SimpleRouter\Exceptions\HttpException;

if (!str_starts_with($config['database']['name'],'kanban_workspace_')) throw new RuntimeException('Use banco descartável kanban_workspace_*.');
function denied(callable $operation, int $code): void {
    try { $operation(); } catch (HttpException|DomainException $e) { check($e->getCode()===$code,'Status de recusa');return; }
    throw new RuntimeException('Operação indevida aceita');
}
$suffix=bin2hex(random_bytes(5));$password='Workspace-convite-123';
$auth=new AuthService($pdo);$service=new WorkspaceMemberService($pdo);$repo=new WorkspaceRepository($pdo);$mailer=new Mailer($config['mail']);
$users=[];
foreach (['owner','admin','member','outside'] as $name) {
    $id=$auth->register($name,"$name-$suffix@example.com",$password,'Área '.$name);
    $pdo->prepare('UPDATE users SET email_verified_at=UTC_TIMESTAMP() WHERE id=?')->execute([$id]);$users[$name]=$id;
}
[$owner,$admin,$member,$outside]=array_values($users);$tenant=(int)$repo->defaultForUser($owner)['id'];$other=(int)$repo->defaultForUser($outside)['id'];
$pdo->prepare("INSERT INTO tenant_users(tenant_id,user_id,role) VALUES (?,?,'admin'),(?,?,'member')")->execute([$tenant,$admin,$tenant,$member]);
function loginWorkspace(string $email): string {
    global $cookie,$password;$cookie='';$csrf=token(request('/login')[1]);check(request('/login',['_token'=>$csrf,'email'=>$email,'password'=>$password])[0]===303,'Login');return token(request('/settings')[1]);
}
$csrf=loginWorkspace("owner-$suffix@example.com");$path="/workspaces/$tenant/members";
check(request($path)[0]===200,'Membros HTTP');
foreach (['invite','cancel','role','transfer','rename'] as $action) check(request($path.'/'.$action,[])[0]===419,'CSRF '.$action);
check(request("/workspaces/$other/members")[0]===404,'Isolamento de membros');
check(request($path.'/role',['_token'=>$csrf,'user_id'=>[$admin],'role'=>'admin'])[0]===404,'ID inválido');
denied(fn()=>$service->manage($admin,$tenant,'role',$member,'admin'),403);
denied(fn()=>$service->manage($admin,$tenant,'role',$owner,'member'),403);
denied(fn()=>$service->manage($member,$tenant,'rename',0,'Novo nome'),403);
denied(fn()=>$service->manage($admin,$tenant,'transfer',$member),403);
denied(fn()=>$service->manage($owner,$tenant,'transfer',$outside),404);
denied(fn()=>$service->invite($admin,$tenant,"admin-invite-$suffix@example.com",'admin',$mailer,$config['url']),403);
denied(fn()=>$service->invite($member,$tenant,"member-invite-$suffix@example.com",'member',$mailer,$config['url']),403);
$email="invited-$suffix@example.com";
check(request($path.'/invite',['_token'=>$csrf,'email'=>$email,'role'=>'admin'])[0]===303,'Envio HTTP');$raw=mailToken($email,'/workspace-invitations/accept');
check($service->validInvitation($raw)!==false,'Token válido');
denied(fn()=>$service->invite($admin,$tenant,$email,'member',$mailer,$config['url']),403);
$invite=$service->pending($raw);denied(fn()=>$service->cancel($admin,$tenant,(int)$invite['id']),403);
$cookie='';check(request('/workspace-invitations/accept?token='.$raw)[0]===303,'Token removido da URL');
check(request('/workspace-invitations/accept')[0]===200,'Convite antes de login');
$html=request('/register')[1];check(!str_contains($html,'name="workspace_name"'),'Cadastro convidado não exige Área');$csrf=token($html);
$data=['_token'=>$csrf,'name'=>'Convidado','email'=>$email,'password'=>$password,'password_confirmation'=>$password];
check(request('/register',$data)[0]===303,'Cadastro convidado');
$q=$pdo->prepare('SELECT id,default_tenant_id FROM users WHERE email=?');$q->execute([$email]);$guest=$q->fetch();$guestId=(int)$guest['id'];
check($guest['default_tenant_id']===null && !$repo->forUser($guestId),'Sem Workspace nem vínculo antes de aceite');
check(request('/workspace-invitations/accept')[0]===403,'Verificação obrigatória');
$verify=mailToken($email,'/email/verify');$csrf=token(request('/email/verify?token='.$verify)[1]);
check(request('/email/verify',['_token'=>$csrf,'token'=>$verify])[0]===200,'Verificação SMTP real');
$html=request('/dashboard');check($html[0]===303 && str_contains($html[2],'/workspace-invitations/accept'),'Retomar convite após verificação');
$csrf=token(request('/workspace-invitations/accept')[1]);
check(request('/workspace-invitations/accept',['_token'=>$csrf])[0]===303,'Aceite HTTP');
check((int)$repo->defaultForUser($guestId)['id']===$tenant && $repo->find($guestId,$tenant)['role']==='admin','Padrão inicial e papel');
denied(fn()=>$service->accept($guestId,$raw),422);
// Reenvio, cancelamento, e-mail incorreto, expiração e preservação de padrão existente.
$emailOutside="outside-$suffix@example.com";$oldDefault=$repo->defaultForUser($outside)['id'];
check($service->invite($owner,$tenant,$emailOutside,'member',$mailer,$config['url']),'Enviar convite SMTP');$old=mailToken($emailOutside,'/workspace-invitations/accept');
check($service->invite($owner,$tenant,$emailOutside,'member',$mailer,$config['url']),'Reenviar convite');$new=mailToken($emailOutside,'/workspace-invitations/accept');
check($old!==$new && !$service->pending($old),'Reenvio invalida token');
denied(fn()=>$service->accept($member,$new),403);
$pdo->prepare('UPDATE workspace_invitations SET expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE token_hash=?')->execute([hash('sha256',$new)]);
denied(fn()=>$service->accept($outside,$new),422);
$service->invite($owner,$tenant,$emailOutside,'member',$mailer,$config['url']);$new=mailToken($emailOutside,'/workspace-invitations/accept');$i=$service->pending($new);
$service->cancel($admin,$tenant,(int)$i['id']);denied(fn()=>$service->accept($outside,$new),422);
$service->invite($owner,$tenant,$emailOutside,'member',$mailer,$config['url']);$new=mailToken($emailOutside,'/workspace-invitations/accept');
$service->accept($outside,$new);check($repo->defaultForUser($outside)['id']===$oldDefault,'Padrão existente preservado');
// Direitos de Workspace não dão acesso a projetos privados.
$project=(new ProjectService($pdo,new WorkspaceContext($member,$tenant)))->create($member,['name'=>'Privado','description'=>'','starts_on'=>'','due_on'=>'']);
denied(fn()=>(new ProjectRepository($pdo))->find($owner,$project),404);
// Rollback de transferência e aceite, e bloqueio de operações concorrentes.
$pdo->exec("CREATE TRIGGER workspace_transfer_fail BEFORE UPDATE ON tenant_users FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='forced failure'");
try { try { $service->manage($owner,$tenant,'transfer',$admin);throw new RuntimeException('Falha esperada'); } catch (PDOException) {} check((int)$repo->find($owner,$tenant)['owner_id']===$owner,'Transferência reverte propriedade'); }
finally { $pdo->exec('DROP TRIGGER workspace_transfer_fail'); }
$second=Database::connect($config['database']);$second->exec('SET SESSION innodb_lock_wait_timeout=1');$pdo->beginTransaction();
try {
    (new WorkspaceContext($owner,$tenant))->authorize($pdo,$owner,true);
    foreach ([fn()=>(new WorkspaceMemberService($second))->manage($owner,$tenant,'transfer',$admin),fn()=>(new WorkspaceMemberService($second))->manage($owner,$tenant,'role',$member,'admin')] as $operation) {
        try { $operation(); throw new RuntimeException('Ignorou bloqueio'); } catch (PDOException $e) { check((int)$e->errorInfo[1]===1205 && !$second->inTransaction(),'Concorrência espera e reverte'); }
    }
} finally { $pdo->rollBack(); }
$service->manage($owner,$tenant,'role',$member,'admin');check($repo->find($member,$tenant)['role']==='admin','Proprietário promove');
$service->manage($owner,$tenant,'transfer',$admin);check((int)$repo->find($admin,$tenant)['owner_id']===$admin && $repo->find($owner,$tenant)['role']==='admin','Proprietário com vínculo e anterior administrador');
denied(fn()=>$service->manage($owner,$tenant,'transfer',$member),403);
// Convite de administrador perde validade quando remetente perde propriedade.
$service->invite($admin,$tenant,"revoked-$suffix@example.com",'admin',$mailer,$config['url']);$revoked=mailToken("revoked-$suffix@example.com",'/workspace-invitations/accept');
$service->manage($admin,$tenant,'transfer',$member);check(!$service->validInvitation($revoked),'Permissão do remetente revalidada');
$service->manage($member,$tenant,'rename',0,'<b>Área segura</b>');
$csrf=loginWorkspace("member-$suffix@example.com");$html=request($path)[1];check(str_contains($html,'&lt;b&gt;Área segura&lt;/b&gt;') && !str_contains($html,'<b>Área segura</b>'),'Escape de nome');
check(request('/workspace-invitations')[0]===200,'Entrada global');
// Aceite serializa com cancelamento, transferência e revogação do vínculo do remetente.
$concurrentEmail="concurrent-$suffix@example.com";
$service->invite($member,$tenant,$concurrentEmail,'member',$mailer,$config['url']);
$concurrentRaw=mailToken($concurrentEmail,'/workspace-invitations/accept');
$concurrentId=$auth->register('Concorrente',$concurrentEmail,$password,null,$concurrentRaw);
$pdo->prepare('UPDATE users SET email_verified_at=UTC_TIMESTAMP() WHERE id=?')->execute([$concurrentId]);
$pdo->beginTransaction();
try {
    (new WorkspaceContext($member,$tenant))->authorize($pdo,$member,true);
    try { (new WorkspaceMemberService($second))->accept($concurrentId,$concurrentRaw); throw new RuntimeException('Aceite ignorou bloqueio'); }
    catch (PDOException $e) { check((int)$e->errorInfo[1]===1205 && !$second->inTransaction(),'Aceite aguarda Workspace e reverte'); }
} finally { $pdo->rollBack(); }
$pending=$service->pending($concurrentRaw);
$pdo->exec("CREATE TRIGGER workspace_accept_fail BEFORE UPDATE ON workspace_invitations FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='forced failure'");
try {
    try { $service->accept($concurrentId,$concurrentRaw);throw new RuntimeException('Falha esperada'); } catch (PDOException) {}
    check(!$repo->forUser($concurrentId) && !$repo->defaultForUser($concurrentId) && $service->pending($concurrentRaw)!==false,'Aceite reverte vínculo, padrão e estado');
} finally { $pdo->exec('DROP TRIGGER workspace_accept_fail'); }
$second->beginTransaction();
$second->prepare('UPDATE workspace_invitations SET cancelled_at=UTC_TIMESTAMP() WHERE id=?')->execute([$pending['id']]);
$pdo->exec('SET SESSION innodb_lock_wait_timeout=1');
try {
    try { $service->accept($concurrentId,$concurrentRaw); throw new RuntimeException('Aceite ignorou cancelamento'); }
    catch (PDOException $e) { check((int)$e->errorInfo[1]===1205 && !$pdo->inTransaction(),'Aceite aguarda cancelamento'); }
} finally { $second->commit(); }
denied(fn()=>$service->accept($concurrentId,$concurrentRaw),422);
$csrf=loginWorkspace($concurrentEmail);
check(request('/settings')[0]===200 && request('/workspaces/new')[0]===200 && request('/workspace-invitations')[0]===200,'Conta sem Workspace mantém rotas globais');
check(request('/workspaces',['_token'=>$csrf,'workspace_name'=>'Recuperação'])[0]===303 && $repo->defaultForUser($concurrentId)!==false,'Criar primeira Área após convite cancelado define padrão');
// Cadastro por convite de projeto também evita Área extra e concede apenas membro no Workspace.
$projectEmail="project-invited-$suffix@example.com";
$projectMembers=new ProjectMemberService($pdo);
check($projectMembers->invite($member,$project,$projectEmail,'admin',$mailer,$config['url']),'Convite de projeto');
$projectRaw=mailToken($projectEmail,'/invitations/accept');
$cookie='';request('/invitations/accept?token='.$projectRaw);
$csrf=token(request('/register')[1]);
check(!str_contains(request('/register')[1],'name="workspace_name"'),'Cadastro convidado por projeto');
check(request('/register',['_token'=>$csrf,'name'=>'Projeto convidado','email'=>$projectEmail,'password'=>$password,'password_confirmation'=>$password])[0]===303,'Cadastro de projeto HTTP');
$q=$pdo->prepare('SELECT id FROM users WHERE email=?');$q->execute([$projectEmail]);$projectUser=(int)$q->fetchColumn();
check(!$repo->forUser($projectUser),'Projeto não vincula antes de verificar e aceitar');
$pdo->prepare('UPDATE users SET email_verified_at=UTC_TIMESTAMP() WHERE id=?')->execute([$projectUser]);
$csrf=token(request('/invitations/accept')[1]);check(request('/invitations/accept',['_token'=>$csrf])[0]===303,'Aceite de projeto HTTP');
check($repo->find($projectUser,$tenant)['role']==='member' && (int)$repo->defaultForUser($projectUser)['id']===$tenant,'Admin de projeto recebe apenas membro e primeiro padrão');
echo "OK: convites SMTP/HTTP, cadastro e verificação, padrão, permissões, isolamento privado, CSRF, XSS, expiração, reenvio, cancelamento, transferência, rollback e concorrência.\n";
