<?php

declare(strict_types=1);

use App\Infrastructure\Database;
use App\Policies\WorkspacePolicy;
use App\Repositories\WorkspaceRepository;
use App\Repositories\ProjectRepository;
use App\Services\AuthService;
use Pecee\SimpleRouter\Exceptions\HttpException;

$config = require dirname(__DIR__) . '/bootstrap/app.php';
if (getenv('AUTH_TEST_DATABASE') !== 'disposable' || !str_starts_with($config['database']['name'], 'kanban_workspace_')) {
    throw new RuntimeException('Execute exclusivamente em banco descartável kanban_workspace_*.');
}
$pdo = Database::connect($config['database']);
function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
function rejects(PDO $pdo, string $sql, array $params, int $code): void {
    try { $pdo->prepare($sql)->execute($params); }
    catch (PDOException $error) { check((int) $error->errorInfo[1] === $code, 'Código SQL inesperado'); return; }
    throw new RuntimeException('SQL inválido foi aceito');
}
$auth = new AuthService($pdo);
$suffix = bin2hex(random_bytes(5));
$users = [];
foreach (['owner', 'admin', 'member', 'outside'] as $name) {
    $users[$name] = $auth->register($name, "$name-$suffix@example.com", 'Senha-workspace-123');
}
$owner = $users['owner']; $admin = $users['admin']; $member = $users['member']; $outside = $users['outside'];
$tenant = (int) $pdo->query("SELECT id FROM tenants WHERE owner_id=$owner")->fetchColumn();
$otherTenant = (int) $pdo->query("SELECT id FROM tenants WHERE owner_id=$outside")->fetchColumn();
$repository = new WorkspaceRepository($pdo);
$workspace = $repository->find($owner, $tenant);
check($workspace !== false && $workspace['role'] === 'member' && WorkspacePolicy::isOwner($workspace, $owner), 'Proprietário deriva de owner_id com vínculo válido');
check($repository->defaultForUser($owner) === false, 'Padrão ainda não definido, sem preenchimento retroativo');
$pdo->prepare("INSERT INTO tenant_users(tenant_id,user_id,role) VALUES (?,?,'admin'),(?,?,'member')")->execute([$tenant,$admin,$tenant,$member]);
$a = $repository->find($admin, $tenant); $m = $repository->find($member, $tenant);
foreach ([$workspace,$a,$m] as $row) check(WorkspacePolicy::can($row,(int)$row['user_id'],'create_project'), 'Todo membro pode criar projeto');
check(WorkspacePolicy::can($workspace,$owner,'rename') && WorkspacePolicy::can($workspace,$owner,'transfer_ownership'), 'Poderes exclusivos do proprietário');
check(!WorkspacePolicy::can($a,$admin,'rename') && !WorkspacePolicy::can($a,$admin,'transfer_ownership'), 'Administrador não assume propriedade');
check(WorkspacePolicy::can($a,$admin,'invite') && !WorkspacePolicy::can($a,$admin,'invite',null,'admin'), 'Admin convida apenas membro');
check(WorkspacePolicy::can($workspace,$owner,'invite',null,'admin'), 'Proprietário convida administrador');
check(WorkspacePolicy::can($a,$admin,'remove_member',$m) && !WorkspacePolicy::can($a,$admin,'remove_member',$workspace) && !WorkspacePolicy::can($a,$admin,'remove_member',$a), 'Admin gerencia somente membros comuns');
check(!WorkspacePolicy::can($a,$admin,'change_role',$m,'admin') && WorkspacePolicy::can($workspace,$owner,'change_role',$m,'admin'), 'Somente proprietário promove administradores');
check(!WorkspacePolicy::can($workspace,$owner,'remove_member',$workspace) && !WorkspacePolicy::can($workspace,$owner,'change_role',$workspace), 'Proprietário não pode ser removido ou rebaixado');
check(!WorkspacePolicy::can($m,$member,'invite') && !WorkspacePolicy::can($workspace,$outside,'rename') && !WorkspacePolicy::can($workspace,$owner,'unknown'), 'Negação de poderes indevidos');
$foreign = $repository->find($outside,$otherTenant);
check(!WorkspacePolicy::can($workspace,$owner,'remove_member',$foreign), 'Alvo de outro Workspace recusado');
check($repository->find($outside,$tenant) === false, 'Sem vínculo não acessa Workspace');
rejects($pdo,'UPDATE tenant_users SET role=? WHERE tenant_id=? AND user_id=?',['owner',$tenant,$admin],1265);
rejects($pdo,'UPDATE users SET default_tenant_id=? WHERE id=?',[$otherTenant,$owner],1452);
$pdo->prepare('UPDATE users SET default_tenant_id=? WHERE id=?')->execute([$tenant,$member]);
check((int)$repository->defaultForUser($member)['id'] === $tenant, 'Padrão acessível');
rejects($pdo,'DELETE FROM tenant_users WHERE tenant_id=? AND user_id=?',[$tenant,$member],1451);
// A FK serializa preferência e revogação concorrentes; não deixa padrão órfão.
$second = Database::connect($config['database']);
$second->exec('SET SESSION innodb_lock_wait_timeout=1');
$pdo->beginTransaction();
try {
    $pdo->prepare('UPDATE users SET default_tenant_id=NULL WHERE id=?')->execute([$member]);
    rejects($second,'DELETE FROM tenant_users WHERE tenant_id=? AND user_id=?',[$tenant,$member],1205);
} finally { $pdo->rollBack(); }
$pdo->prepare('UPDATE users SET default_tenant_id=NULL WHERE id=?')->execute([$member]);
$pdo->prepare('DELETE FROM tenant_users WHERE tenant_id=? AND user_id=?')->execute([$tenant,$member]);
check($repository->find($member,$tenant) === false && $repository->defaultForUser($member) === false, 'Revogação e limpeza explícita do padrão');
// Convites próprios, token somente em hash, unicidade e integridade de referências.
$invite = 'INSERT INTO workspace_invitations(tenant_id,invited_by,email,token_hash,expires_at) VALUES (?,?,?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY))';
$hash = hash('sha256', random_bytes(32));
$pdo->prepare($invite)->execute([$tenant,$owner,"invite-$suffix@example.com",$hash]);
rejects($pdo,$invite,[$tenant,$owner,"invite-$suffix@example.com",hash('sha256','other')],1062);
rejects($pdo,$invite,[$otherTenant,$outside,"other-$suffix@example.com",$hash],1062);
rejects($pdo,$invite,[PHP_INT_MAX,$owner,"invalid-$suffix@example.com",hash('sha256','invalid')],1452);
$pdo->prepare("INSERT INTO projects(tenant_id,owner_id,name,description) VALUES (?,?,?,'')")->execute([$tenant,$admin,'Privado']);
$project = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO project_members(tenant_id,project_id,user_id,role) VALUES (?,?,?,'owner')")->execute([$tenant,$project,$admin]);
try { (new ProjectRepository($pdo))->find($owner,$project); throw new RuntimeException('Propriedade do Workspace concedeu projeto privado'); }
catch (HttpException $error) { check($error->getCode() === 404, 'Projeto privado oculto'); }
$pdo->prepare('INSERT INTO tenant_users(tenant_id,user_id) VALUES (?,?)')->execute([$tenant,$outside]);
$pdo->prepare("INSERT INTO projects(tenant_id,owner_id,name,description) VALUES (?,?,?,'')")->execute([$tenant,$outside,'Outro privado']);
$privateProject = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO project_members(tenant_id,project_id,user_id,role) VALUES (?,?,?,'owner')")->execute([$tenant,$privateProject,$outside]);
try { (new ProjectRepository($pdo))->find($admin,$privateProject); throw new RuntimeException('Admin do Workspace acessou projeto privado'); }
catch (HttpException $error) { check($error->getCode() === 404, 'Admin sem participação não acessa projeto'); }
$pdo->prepare("INSERT INTO project_members(tenant_id,project_id,user_id,role) VALUES (?,?,?,'viewer')")->execute([$tenant,$privateProject,$admin]);
$viewerProject = (new ProjectRepository($pdo))->find($admin,$privateProject);
check(!App\Policies\ProjectPolicy::canManage($viewerProject,$admin), 'Admin de Workspace como viewer não administra projeto');
$pdo->prepare('UPDATE tenants SET owner_id=? WHERE id=?')->execute([$owner,$otherTenant]);
check($repository->find($outside,$otherTenant) === false, 'Workspace com proprietário sem vínculo não concede permissões');
$pdo->prepare('UPDATE tenants SET owner_id=? WHERE id=?')->execute([$outside,$otherTenant]);
check($auth->register('Duplicado', "owner-$suffix@example.com", 'Senha-workspace-123') === null, 'Cadastro duplicado mantém transação');
$pdo->exec("CREATE TRIGGER test_workspace_reject_membership BEFORE INSERT ON tenant_users FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='forced failure'");
try {
    try { $auth->register('Rollback', "rollback-$suffix@example.com", 'Senha-workspace-123'); throw new RuntimeException('Falha esperada'); }
    catch (PDOException) {}
    $q=$pdo->prepare('SELECT COUNT(*) FROM users WHERE email=?');$q->execute(["rollback-$suffix@example.com"]);
    check((int)$q->fetchColumn() === 0, 'Falha de vínculo reverte usuário e Workspace');
} finally { $pdo->exec('DROP TRIGGER test_workspace_reject_membership'); }
echo "OK: modelo, matriz de permissões, padrão/FKs, concorrência, convites, isolamento de projetos e rollback.\n";
