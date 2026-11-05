<?php

declare(strict_types=1);

require __DIR__ . '/http.php';

use App\Infrastructure\Database;
use App\Repositories\WorkspaceRepository;
use App\Services\{AuthService, WorkspaceContext, WorkspaceService};
use Pecee\SimpleRouter\Exceptions\HttpException;

if (!str_starts_with($config['database']['name'], 'kanban_workspace_')) throw new RuntimeException('Use banco descartável kanban_workspace_*.');
function denied(callable $operation, int $code): void {
    try { $operation(); } catch (HttpException|DomainException $error) { check($error->getCode() === $code, 'Status esperado'); return; }
    throw new RuntimeException('Operação indevida aceita');
}
$suffix = bin2hex(random_bytes(5)); $email = "navigation-$suffix@example.com"; $password = 'Workspace-navegacao-123';
$csrf = token(request('/register')[1]);
$data = ['_token'=>$csrf, 'name'=>'Navegação', 'email'=>$email, 'password'=>$password, 'password_confirmation'=>$password];
foreach (['', ' ', 'a', str_repeat('a',121), "nome\ninválido", ['array']] as $name) {
    check(request('/register', $data + ['workspace_name'=>$name])[0] === 422, 'Nome obrigatório e validado');
}
[$status,,$headers] = request('/register', $data + ['workspace_name'=>"Primeiro-$suffix"]);
check($status===303, 'Cadastro comum');
$q=$pdo->prepare('SELECT id, default_tenant_id FROM users WHERE email=?');$q->execute([$email]);$user=$q->fetch();$uid=(int)$user['id'];$a=(int)$user['default_tenant_id'];
check(str_contains($headers,"Location: /workspaces/$a/dashboard"),'Destino explícito no cadastro');
check(request("/workspaces/$a/dashboard")[0]===403 && request('/workspaces/new')[0]===403, 'Verificação de e-mail preservada');
$pdo->prepare('UPDATE users SET email_verified_at=UTC_TIMESTAMP() WHERE id=?')->execute([$uid]);
$html=request("/workspaces/$a/dashboard")[1];$csrf=token($html);
check(str_contains($html,"Primeiro-$suffix"),'Workspace ativo identificado');
check(request('/workspaces', ['workspace_name'=>'Sem CSRF'])[0]===419,'CSRF criação');
check(request('/settings/workspace', ['workspace_id'=>(string)$a])[0]===419,'CSRF padrão');
check(request('/workspaces',['_token'=>$csrf,'workspace_name'=>['array']])[0]===422,'Formato inválido criação');
[$status,,$headers]=request('/workspaces',['_token'=>$csrf,'workspace_name'=>"Segundo-$suffix"]);
check($status===303 && preg_match('~Location: /workspaces/(\d+)/dashboard~',$headers,$m),'Criar e navegar sem JS');$b=(int)$m[1];
$repo=new WorkspaceRepository($pdo);$service=new WorkspaceService($pdo);
check((int)$repo->find($uid,$b)['owner_id']===$uid,'Proprietário com vínculo');
check((int)$repo->defaultForUser($uid)['id']===$a,'Criação preserva padrão');
check(count($repo->forUser($uid))===2,'Lista todos os vínculos');
check(request('/settings/workspace',['_token'=>$csrf,'workspace_id'=>(string)$b])[0]===303,'Alterar padrão sem JS');
check(WorkspaceContext::initial($pdo,$uid)->tenantId===$b,'Entrada usa padrão');
check(str_contains(request('/settings')[1],'value="'.$b.'" selected'),'Configurações globais mostram padrão');
check(str_contains(request("/workspaces/$a/tasks")[1], 'data-workspace-path="/workspaces/'.$a.'"'),'Outra aba mantém contexto A');
check(str_contains(request("/workspaces/$b/tasks")[1], 'data-workspace-path="/workspaces/'.$b.'"'),'Outra aba mantém contexto B');
// HTML e formulário do seletor funcionam sem JavaScript, sem estado mutável na sessão.
$html=request("/workspaces/$a/dashboard")[1];
check(str_contains($html,"href=\"/workspaces/$b/dashboard\"") && str_contains($html,"Primeiro-$suffix"),'Seletor acessível por links');
request('/logout',['_token'=>$csrf]);$csrf=token(request('/login')[1]);
[$status,,$headers]=request('/login',['_token'=>$csrf,'email'=>$email,'password'=>$password]);
check($status===303 && str_contains($headers,"Location: /workspaces/$b/dashboard"),'Login abre padrão');
$csrf=token(request('/settings')[1]);
$outside=(new AuthService($pdo))->register('Externo',"outside-navigation-$suffix@example.com",$password,'Privado');
$c=(int)$repo->defaultForUser($outside)['id'];
check(request('/settings/workspace',['_token'=>$csrf,'workspace_id'=>(string)$c])[0]===404,'Padrão externo bloqueado');
check(request("/workspaces/$c/dashboard")[0]===404,'Workspace externo bloqueado');
check(request('/settings/workspace',['_token'=>$csrf,'workspace_id'=>[$b]])[0]===404,'ID malformado bloqueado');
denied(fn()=>$service->create($uid,999,'Sessão revogada'),401);
denied(fn()=>$service->setDefault($uid,999,$a),401);
check((int)$repo->defaultForUser($uid)['id']===$b && !$pdo->inTransaction(),'Sessão revogada não escreve e libera transação');
// Falha após criar o tenant precisa desfazer toda a criação.
$before=count($repo->forUser($uid));
$pdo->exec("CREATE TRIGGER test_workspace_navigation_fail BEFORE INSERT ON tenant_users FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='forced failure'");
try {
    try { $service->create($uid,1,'Rollback'); throw new RuntimeException('Falha esperada'); } catch (PDOException) {}
    check(count($repo->forUser($uid))===$before,'Rollback do vínculo e Workspace');
    $q=$pdo->prepare("SELECT COUNT(*) FROM tenants WHERE owner_id=? AND name='Rollback'");$q->execute([$uid]);check((int)$q->fetchColumn()===0,'Sem Workspace órfão');
} finally { $pdo->exec('DROP TRIGGER test_workspace_navigation_fail'); }
// Padrão e revogação serializam no vínculo; proprietário não recebe projetos privados.
$pdo->prepare('INSERT INTO tenant_users(tenant_id,user_id) VALUES (?,?)')->execute([$c,$uid]);
$second=Database::connect($config['database']);$second->exec('SET SESSION innodb_lock_wait_timeout=1');
$pdo->beginTransaction();
try {
    (new WorkspaceContext($uid,$c))->authorize($pdo,$uid,true);
    try { $second->prepare('DELETE FROM tenant_users WHERE tenant_id=? AND user_id=?')->execute([$c,$uid]);throw new RuntimeException('Revogação não esperou'); }
    catch (PDOException $e) { check((int)$e->errorInfo[1]===1205,'Bloqueio do vínculo'); }
} finally { $pdo->rollBack(); }
$second->beginTransaction();$second->prepare('DELETE FROM tenant_users WHERE tenant_id=? AND user_id=?')->execute([$c,$uid]);
try {
    $pdo->exec('SET SESSION innodb_lock_wait_timeout=1');
    try { $service->setDefault($uid,1,$c); throw new RuntimeException('Padrão ignorou revogação em andamento'); }
    catch (PDOException $e) { check((int)$e->errorInfo[1]===1205 && !$pdo->inTransaction(),'Preferência aguarda revogação e reverte'); }
} finally { $second->commit(); }
denied(fn()=>$service->setDefault($uid,1,$c),404);
check(request('/settings/workspace',['_token'=>$csrf,'workspace_id'=>(string)$c])[0]===404,'Vínculo revogado recusado por HTTP');
check((int)$repo->defaultForUser($uid)['id']===$b,'Falhas preservam padrão');
// Nome é sempre escapado no seletor e em Configurações.
$xss=$service->create($uid,1,'<script>workspace-xss</script>');
$html=request("/workspaces/$xss/dashboard")[1];
check(str_contains($html,'&lt;script&gt;workspace-xss&lt;/script&gt;') && !str_contains($html,'<script>workspace-xss'),'Escape do nome');
echo "OK: cadastro, criação, padrão/login, seletor sem JS, abas, CSRF, sessão, isolamento, revogação concorrente, rollback e XSS.\n";
