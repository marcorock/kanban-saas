<?php

declare(strict_types=1);

use App\Infrastructure\Database;
use App\Repositories\ProjectRepository;
use App\Services\AuthService;
use App\Services\BoardService;
use App\Services\ProjectService;
use App\Services\WorkspaceContext;
use Pecee\SimpleRouter\Exceptions\HttpException;

require __DIR__ . '/http.php';
if (!str_starts_with($config['database']['name'], 'kanban_workspace_')) throw new RuntimeException('Use banco descartável kanban_workspace_*.');
function denied(callable $operation, int $status = 404): void {
    try { $operation(); } catch (HttpException $error) { check($error->getCode() === $status, 'Status de autorização'); return; }
    throw new RuntimeException('Operação indevida aceita');
}
$password = 'Workspace-contexto-123';
$suffix = bin2hex(random_bytes(5));
$auth = new AuthService($pdo);
$email = "context-$suffix@example.com";
$user = $auth->register('Contexto', $email, $password);
$other = $auth->register('Outro', "other-context-$suffix@example.com", $password);
$pdo->prepare('UPDATE users SET email_verified_at=UTC_TIMESTAMP() WHERE id IN (?,?)')->execute([$user,$other]);
$data = ['name'=>'Contexto', 'description'=>'', 'starts_on'=>'', 'due_on'=>''];
$projectA = (new ProjectService($pdo))->create($user,$data);
$projectB = (new ProjectService($pdo))->create($other,$data);
$tenantA = (int)(new ProjectRepository($pdo))->find($user,$projectA)['tenant_id'];
$tenantB = (int)(new ProjectRepository($pdo))->find($other,$projectB)['tenant_id'];
$pdo->prepare("INSERT INTO tenant_users(tenant_id,user_id,role) VALUES (?,?,'admin')")->execute([$tenantB,$user]);
$contextA = WorkspaceContext::resolve($pdo,$user,(string)$tenantA);
$contextB = WorkspaceContext::resolve($pdo,$user,(string)$tenantB);
denied(fn() => (new ProjectRepository($pdo,$contextB))->find($user,$projectB));
$pdo->prepare("INSERT INTO project_members(tenant_id,project_id,user_id,role) VALUES (?,?,?,'viewer')")->execute([$tenantB,$projectB,$user]);
check((int)(new ProjectRepository($pdo,$contextB))->find($user,$projectB)['id'] === $projectB, 'Viewer com vínculo acessa');
denied(fn() => (new ProjectRepository($pdo,$contextA))->find($user,$projectB));
denied(fn() => (new ProjectRepository($pdo,$contextB))->find($user,$projectA));
denied(fn() => (new ProjectRepository($pdo,$contextA))->find($other,$projectA));
denied(fn() => (new ProjectService($pdo,$contextB))->change($user,$projectB,'update',$data),403);
foreach ([null, [], '0', '-1', '1x', str_repeat('9',19)] as $invalid) denied(fn() => WorkspaceContext::resolve($pdo,$user,$invalid));
// Alterar a preferência e a sessão não altera o contexto explícito de uma operação.
$pdo->prepare('UPDATE users SET default_tenant_id=? WHERE id=?')->execute([$tenantB,$user]);
check((int)(new ProjectRepository($pdo,$contextA))->find($user,$projectA)['tenant_id'] === $tenantA, 'Contexto independente do padrão');
$csrf = token(request('/login')[1]);
check(request('/login',['_token'=>$csrf,'email'=>$email,'password'=>$password])[0] === 303,'Login');
$csrf = token(request('/projects/new')[1]);
$pathA = "/workspaces/$tenantA/projects/$projectA";
$wrong = "/workspaces/$tenantA/projects/$projectB";
check(request($pathA)[0] === 200,'Rota explícita');
foreach (['/edit','/members','/history','/board'] as $suffixPath) {
    [$status,$html] = request($pathA.$suffixPath);
    check($status === 200 && str_contains($html,$pathA),'Contexto nos links/formulários: '.$suffixPath);
}
check(str_contains(request($pathA.'/edit')[1],'action="'.$pathA.'"'),'Formulário preserva contexto sem JS');
check(str_contains(request($pathA.'/board')[1],'data-endpoint="'.$pathA.'/board"'),'API do quadro preserva contexto');
$body = json_encode(['_token'=>$csrf,'version'=>0,'action'=>'column.create','name'=>'Bloqueada']);
$http = stream_context_create(['http'=>['method'=>'POST','ignore_errors'=>true,'header'=>"Cookie: $cookie\r\nContent-Type: application/json\r\n",'content'=>$body]]);
$json = file_get_contents($base.$wrong.'/board',false,$http);
check(str_contains($http_response_header[0],'404') && json_decode($json,true)['ok'] === false,'Mutação JSON em contexto incorreto');
foreach (['','/edit','/members','/history','/board','/board/version','/board/state','/tasks/1'] as $suffixPath) check(request($wrong.$suffixPath)[0] === 404,'Recurso fora do contexto: '.$suffixPath);
foreach (['','/archive','/restore','/delete','/invitations','/members/remove','/transfer'] as $suffixPath) check(request($wrong.$suffixPath,['_token'=>$csrf,'confirm'=>'delete']+$data)[0] === 404,'Escrita fora do contexto: '.$suffixPath);
check(request($pathA.'/archive',[])[0] === 419,'CSRF mantido');
check(request($pathA.'/archive',['_token'=>$csrf])[0] === 303,'Mutação autorizada');
check(request($pathA.'/restore',['_token'=>$csrf])[0] === 303,'Restauração autorizada');
check(request("/workspaces/$tenantB/projects/$projectB/board/state")[0] === 200,'API explícita autorizada');
check(request("/workspaces/$tenantB/projects/$projectA/board/state")[0] === 404,'Abas independentes');
// O bloqueio do vínculo dura até o fim da operação; uma revogação concorrente espera.
$second = Database::connect($config['database']);
$second->exec('SET SESSION innodb_lock_wait_timeout=1');
$pdo->beginTransaction();
try {
    (new ProjectRepository($pdo,$contextB))->find($user,$projectB,true);
    try {
        $second->prepare('DELETE FROM tenant_users WHERE tenant_id=? AND user_id=?')->execute([$tenantB,$user]);
        throw new RuntimeException('Revogação ignorou operação em andamento');
    } catch (PDOException $error) { check((int)$error->errorInfo[1] === 1205,'Revogação serializada'); }
} finally { $pdo->rollBack(); }
$pdo->prepare('UPDATE users SET default_tenant_id=NULL WHERE id=?')->execute([$user]);
$second->beginTransaction();
$second->prepare('DELETE FROM project_members WHERE tenant_id=? AND user_id=?')->execute([$tenantB,$user]);
$second->prepare('DELETE FROM tenant_users WHERE tenant_id=? AND user_id=?')->execute([$tenantB,$user]);
$second->commit();
denied(fn() => (new ProjectRepository($pdo,$contextB))->find($user,$projectB));
denied(fn() => (new BoardService($pdo,$contextB))->state($user,$projectB));
denied(fn() => (new ProjectService($pdo,$contextB))->change($user,$projectB,'archive'));
check(!$pdo->inTransaction(),'Falhas revertem transações');
check(request("/workspaces/$tenantB/projects/$projectB/board/state")[0] === 404,'Revogação HTTP imediata');
check(request("/projects/$projectB")[0] === 404,'Rota existente também respeita revogação');
check(request($pathA)[0] === 200,'Outro Workspace preservado');
echo "OK: contexto explícito, dois Workspaces, projetos privados, papéis, APIs, CSRF, revogação e concorrência.\n";
