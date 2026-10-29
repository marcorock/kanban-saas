<?php

declare(strict_types=1);

namespace App\Services;

use App\Policies\ProjectPolicy;
use App\Repositories\ProjectRepository;
use PDO;
use Pecee\SimpleRouter\Exceptions\HttpException;
use Rakit\Validation\Validator;
use Throwable;

final class ProjectMemberService
{
    public function __construct(private PDO $pdo) {}

    public function overview(int $actorId, int $projectId, int $page): array
    {
        $project = (new ProjectRepository($this->pdo))->find($actorId, $projectId);
        $canManage = ProjectPolicy::canManage($project, $actorId);
        $params = [$project['tenant_id'], $projectId];
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM project_members WHERE tenant_id = ? AND project_id = ?');
        $statement->execute($params);
        $memberCount = (int) $statement->fetchColumn();
        $pending = ' FROM project_invitations WHERE tenant_id = ? AND project_id = ? AND accepted_at IS NULL AND cancelled_at IS NULL AND expires_at > UTC_TIMESTAMP()';
        $invitationCount = 0;
        if ($canManage) {
            $statement = $this->pdo->prepare('SELECT COUNT(*)' . $pending);
            $statement->execute($params);
            $invitationCount = (int) $statement->fetchColumn();
        }
        $pages = max(1, (int) ceil(max($memberCount, $invitationCount) / 25));
        $page = max(1, min($page, $pages));
        $offset = ($page - 1) * 25;
        $emailField = $canManage ? ', u.email' : '';
        $statement = $this->pdo->prepare("SELECT pm.user_id, pm.role, u.name{$emailField} FROM project_members pm JOIN users u ON u.id = pm.user_id WHERE pm.tenant_id = ? AND pm.project_id = ? ORDER BY pm.role, pm.user_id LIMIT 25 OFFSET {$offset}");
        $statement->execute($params);
        $members = $statement->fetchAll();
        $invitations = [];
        if ($canManage) {
            $statement = $this->pdo->prepare('SELECT id, email, role, expires_at' . $pending . " ORDER BY id DESC LIMIT 25 OFFSET {$offset}");
            $statement->execute($params);
            $invitations = $statement->fetchAll();
        }
        return compact('project', 'canManage', 'members', 'invitations', 'page', 'pages');
    }

    private function manageable(int $actorId, int $projectId): array
    {
        $project = (new ProjectRepository($this->pdo))->find($actorId, $projectId, true);
        ProjectPolicy::authorizeMembers($project, $actorId);
        return $project;
    }

    private function canAssign(array $project, int $actorId, string $role): bool
    {
        return in_array($role, ['admin', 'member', 'viewer'], true)
            && ($role !== 'admin' || ProjectPolicy::isOwner($project, $actorId));
    }

    public function invite(int $actorId, int $projectId, string $email, string $role, Mailer $mailer, string $appUrl): bool
    {
        $email = strtolower(trim($email));
        if ((new Validator())->validate(['email' => $email], ['email' => 'required|email|max:254'])->fails()) {
            throw new HttpException('Informe um e-mail válido.', 422);
        }
        $url = parse_url($appUrl);
        if (!$url || !in_array($url['scheme'] ?? '', ['http', 'https'], true) || empty($url['host']) || isset($url['query']) || isset($url['fragment']) || isset($url['user']) || isset($url['pass'])) {
            throw new \InvalidArgumentException('APP_URL inválida.');
        }
        $raw = bin2hex(random_bytes(32));
        $this->pdo->beginTransaction();
        try {
            $project = $this->manageable($actorId, $projectId);
            if (!$this->canAssign($project, $actorId, $role)) { throw new HttpException('Papel não permitido.', 403); }
            $statement = $this->pdo->prepare('SELECT pm.user_id FROM project_members pm JOIN users u ON u.id = pm.user_id WHERE pm.tenant_id = ? AND pm.project_id = ? AND u.email = ?');
            $statement->execute([$project['tenant_id'], $projectId, $email]);
            if ($statement->fetchColumn()) { throw new HttpException('O usuário já participa do projeto.', 409); }
            $statement = $this->pdo->prepare('SELECT id, role FROM project_invitations WHERE tenant_id = ? AND project_id = ? AND email = ? FOR UPDATE');
            $statement->execute([$project['tenant_id'], $projectId, $email]);
            $previous = $statement->fetch();
            // Um administrador não pode substituir um convite de administrador.
            if ($previous && $previous['role'] === 'admin' && !ProjectPolicy::isOwner($project, $actorId)) {
                throw new HttpException('Somente o proprietário gerencia convites de administrador.', 403);
            }
            $this->pdo->prepare('INSERT INTO project_invitations (tenant_id, project_id, invited_by, email, role, token_hash, expires_at) VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 7 DAY)) ON DUPLICATE KEY UPDATE invited_by = VALUES(invited_by), role = VALUES(role), token_hash = VALUES(token_hash), expires_at = VALUES(expires_at), accepted_at = NULL, cancelled_at = NULL, updated_at = UTC_TIMESTAMP()')->execute([$project['tenant_id'], $projectId, $actorId, $email, $role, hash('sha256', $raw)]);
            (new ActivityLogger($this->pdo))->record($project, $actorId, $previous ? 'invitation.reissued' : 'invitation.created', $previous ? ['email' => $email, 'role' => $previous['role']] : null, ['email' => $email, 'role' => $role]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
        $link = rtrim($appUrl, '/') . '/invitations/accept?token=' . $raw;
        try {
            $mailer->send($email, 'Convite para participar de um projeto', "Você recebeu um convite para o projeto {$project['name']}.\n\n{$link}\n\nO link expira em 7 dias. Entre ou crie uma conta com este mesmo e-mail e confirme seu endereço antes de aceitar. Se não reconhecer o convite, ignore esta mensagem.");
            return true;
        } catch (Throwable) {
            error_log('[kanbansaas] Falha no envio SMTP de convite.');
            return false;
        }
    }

    public function cancel(int $actorId, int $projectId, int $invitationId): void
    {
        $this->pdo->beginTransaction();
        try {
            $project = $this->manageable($actorId, $projectId);
            $statement = $this->pdo->prepare('SELECT id, email, role FROM project_invitations WHERE tenant_id = ? AND project_id = ? AND id = ? AND accepted_at IS NULL AND cancelled_at IS NULL FOR UPDATE');
            $statement->execute([$project['tenant_id'], $projectId, $invitationId]);
            $invitation = $statement->fetch();
            if (!$invitation) { throw new HttpException('Convite indisponível.', 404); }
            if (!$this->canAssign($project, $actorId, $invitation['role'])) { throw new HttpException('Permissão insuficiente.', 403); }
            $this->pdo->prepare('UPDATE project_invitations SET cancelled_at = UTC_TIMESTAMP() WHERE tenant_id = ? AND project_id = ? AND id = ?')->execute([$project['tenant_id'], $projectId, $invitationId]);
            (new ActivityLogger($this->pdo))->record($project, $actorId, 'invitation.cancelled', ['email' => $invitation['email'], 'role' => $invitation['role']], null);
            $this->pdo->commit();
        } catch (Throwable $exception) { $this->pdo->rollBack(); throw $exception; }
    }

    public function member(int $actorId, int $projectId, int $targetId, string $action, string $role = ''): void
    {
        if (!in_array($action, ['role', 'remove', 'transfer'], true)) { throw new \InvalidArgumentException('Ação inválida.'); }
        $this->pdo->beginTransaction();
        try {
            $project = $this->manageable($actorId, $projectId);
            $statement = $this->pdo->prepare('SELECT pm.role, u.email_verified_at FROM project_members pm JOIN users u ON u.id = pm.user_id WHERE pm.tenant_id = ? AND pm.project_id = ? AND pm.user_id = ? FOR UPDATE');
            $statement->execute([$project['tenant_id'], $projectId, $targetId]);
            $target = $statement->fetch();
            if (!$target) { throw new HttpException('Participante indisponível.', 404); }
            if ($targetId === (int) $project['owner_id'] || $target['role'] === 'owner') { throw new HttpException('O proprietário não pode ser removido ou rebaixado.', 403); }
            $owner = ProjectPolicy::isOwner($project, $actorId);
            if (!$owner && ($target['role'] === 'admin' || !$this->canAssign($project, $actorId, $role ?: $target['role']))) {
                throw new HttpException('Permissão insuficiente.', 403);
            }
            $before = ['user_id' => $targetId, 'role' => $target['role']];
            $logger = new ActivityLogger($this->pdo);
            if ($action === 'transfer') {
                if (!$owner || $target['email_verified_at'] === null) { throw new HttpException('Transferência não permitida.', 403); }
                $this->pdo->prepare('UPDATE projects SET owner_id = ? WHERE tenant_id = ? AND id = ?')->execute([$targetId, $project['tenant_id'], $projectId]);
                $this->pdo->prepare("UPDATE project_members SET role = 'admin' WHERE tenant_id = ? AND project_id = ? AND user_id = ?")->execute([$project['tenant_id'], $projectId, $actorId]);
                $this->pdo->prepare("UPDATE project_members SET role = 'owner' WHERE tenant_id = ? AND project_id = ? AND user_id = ?")->execute([$project['tenant_id'], $projectId, $targetId]);
                $logger->record($project, $actorId, 'project.ownership_transferred', ['owner_id' => $actorId], ['owner_id' => $targetId, 'previous_owner_role' => 'admin']);
            } elseif ($action === 'remove') {
                $statement = $this->pdo->prepare('SELECT task_id FROM task_assignees WHERE tenant_id = ? AND project_id = ? AND user_id = ? ORDER BY task_id');
                $statement->execute([$project['tenant_id'], $projectId, $targetId]);
                foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $taskId) {
                    $previous = (new TaskService($this->pdo))->assignees($project, (int) $taskId);
                    $this->pdo->prepare('DELETE FROM task_assignees WHERE tenant_id = ? AND project_id = ? AND task_id = ? AND user_id = ?')->execute([$project['tenant_id'], $projectId, $taskId, $targetId]);
                    $logger->record($project, $actorId, 'task.assignees_changed', ['user_ids' => $previous], ['user_ids' => array_values(array_filter($previous, static fn (int $id): bool => $id !== $targetId))], (int) $taskId);
                }
                $this->pdo->prepare('DELETE FROM project_members WHERE tenant_id = ? AND project_id = ? AND user_id = ?')->execute([$project['tenant_id'], $projectId, $targetId]);
                $logger->record($project, $actorId, 'member.removed', $before, null);
            } else {
                if (!$this->canAssign($project, $actorId, $role)) { throw new HttpException('Papel não permitido.', 403); }
                if ($target['role'] !== $role) {
                    $this->pdo->prepare('UPDATE project_members SET role = ? WHERE tenant_id = ? AND project_id = ? AND user_id = ?')->execute([$role, $project['tenant_id'], $projectId, $targetId]);
                    $logger->record($project, $actorId, 'member.role_changed', $before, ['user_id' => $targetId, 'role' => $role]);
                }
            }
            $this->pdo->prepare('UPDATE projects SET board_version = board_version + 1 WHERE tenant_id = ? AND id = ?')->execute([$project['tenant_id'], $projectId]);
            // Mantém tenant_users: ele pode sustentar outros projetos ou ser o tenant pessoal.
            $this->pdo->commit();
        } catch (Throwable $exception) { $this->pdo->rollBack(); throw $exception; }
    }

    private function pending(string $raw): array|false
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $raw)) { return false; }
        $statement = $this->pdo->prepare('SELECT i.* FROM project_invitations i JOIN projects p ON p.id = i.project_id AND p.tenant_id = i.tenant_id WHERE i.token_hash = ? AND i.accepted_at IS NULL AND i.cancelled_at IS NULL AND i.expires_at > UTC_TIMESTAMP() AND p.deleted_at IS NULL AND p.archived_at IS NULL');
        $statement->execute([hash('sha256', $raw)]);
        return $statement->fetch();
    }

    private function inviterAllowed(array $invitation): bool
    {
        try {
            $project = (new ProjectRepository($this->pdo))->find((int) $invitation['invited_by'], (int) $invitation['project_id']);
            return ProjectPolicy::canManage($project, (int) $invitation['invited_by']) && $this->canAssign($project, (int) $invitation['invited_by'], $invitation['role']);
        } catch (HttpException) { return false; }
    }

    public function preview(int $userId, string $raw): array|false
    {
        $invitation = $this->pending($raw);
        if (!$invitation || !$this->inviterAllowed($invitation)) { return false; }
        $user = (new AuthService($this->pdo))->user($userId);
        if (!$user || $user['email_verified_at'] === null || strcasecmp($user['email'], $invitation['email']) !== 0) { return false; }
        $statement = $this->pdo->prepare('SELECT name FROM projects WHERE tenant_id = ? AND id = ?');
        $statement->execute([$invitation['tenant_id'], $invitation['project_id']]);
        return ['name' => $statement->fetchColumn(), 'role' => $invitation['role']];
    }

    public function accept(int $userId, string $raw): int
    {
        $invitation = $this->pending($raw);
        if (!$invitation) { throw new HttpException('Convite inválido ou expirado.', 422); }
        $this->pdo->beginTransaction();
        try {
            // Serializa aceite, cancelamento, alterações de papéis e estado do projeto.
            $statement = $this->pdo->prepare('SELECT * FROM projects WHERE tenant_id = ? AND id = ? FOR UPDATE');
            $statement->execute([$invitation['tenant_id'], $invitation['project_id']]);
            $project = $statement->fetch();
            $statement = $this->pdo->prepare('SELECT * FROM project_invitations WHERE id = ? AND token_hash = ? AND accepted_at IS NULL AND cancelled_at IS NULL AND expires_at > UTC_TIMESTAMP() FOR UPDATE');
            $statement->execute([$invitation['id'], hash('sha256', $raw)]);
            $invitation = $statement->fetch();
            if (!$project || $project['deleted_at'] !== null || $project['archived_at'] !== null || !$invitation || !$this->inviterAllowed($invitation)) { throw new HttpException('Convite indisponível.', 422); }
            $statement = $this->pdo->prepare('SELECT email, email_verified_at FROM users WHERE id = ? FOR UPDATE');
            $statement->execute([$userId]);
            $user = $statement->fetch();
            if (!$user || $user['email_verified_at'] === null || strcasecmp($user['email'], $invitation['email']) !== 0) { throw new HttpException('Entre com a conta verificada correspondente ao convite.', 403); }
            $statement = $this->pdo->prepare('SELECT role FROM project_members WHERE tenant_id = ? AND project_id = ? AND user_id = ?');
            $statement->execute([$project['tenant_id'], $project['id'], $userId]);
            if ($statement->fetchColumn()) { throw new HttpException('Usuário já participa do projeto.', 409); }
            $this->pdo->prepare('INSERT INTO tenant_users (tenant_id, user_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE tenant_id = VALUES(tenant_id)')->execute([$project['tenant_id'], $userId]);
            $this->pdo->prepare('INSERT INTO project_members (tenant_id, project_id, user_id, role) VALUES (?, ?, ?, ?)')->execute([$project['tenant_id'], $project['id'], $userId, $invitation['role']]);
            $this->pdo->prepare('UPDATE project_invitations SET accepted_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$invitation['id']]);
            $logger = new ActivityLogger($this->pdo);
            $logger->record($project, $userId, 'invitation.accepted', null, ['email' => $user['email'], 'role' => $invitation['role']]);
            $logger->record($project, $userId, 'member.added', null, ['user_id' => $userId, 'role' => $invitation['role']]);
            $this->pdo->prepare('UPDATE projects SET board_version = board_version + 1 WHERE tenant_id = ? AND id = ?')->execute([$project['tenant_id'], $project['id']]);
            $this->pdo->commit();
            return (int) $project['id'];
        } catch (Throwable $exception) { $this->pdo->rollBack(); throw $exception; }
    }
}
