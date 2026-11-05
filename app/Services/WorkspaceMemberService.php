<?php

declare(strict_types=1);

namespace App\Services;

use App\Policies\WorkspacePolicy;
use App\Repositories\WorkspaceRepository;
use PDO;
use Pecee\SimpleRouter\Exceptions\HttpException;
use Throwable;

final class WorkspaceMemberService
{
    public function __construct(private PDO $pdo) {}

    private function workspace(int $actor, int $tenant, bool $lock = false): array
    {
        $workspace = (new WorkspaceRepository($this->pdo))->find($actor, $tenant, $lock);
        if (!$workspace) throw new HttpException('Área de Trabalho não encontrada.', 404);
        return $workspace;
    }

    private function permit(array $workspace, int $actor, string $action, ?array $target = null, string $role = 'member'): void
    {
        if (!WorkspacePolicy::can($workspace, $actor, $action, $target, $role)) throw new HttpException('Permissão insuficiente.', 403);
    }

    private function transaction(callable $operation): mixed
    {
        $this->pdo->beginTransaction();
        try { $result = $operation(); $this->pdo->commit(); return $result; }
        catch (Throwable $error) { $this->pdo->rollBack(); throw $error; }
    }

    public function overview(int $actor, int $tenant, int $page = 1): array
    {
        $workspace = $this->workspace($actor, $tenant);
        $manager = WorkspacePolicy::can($workspace, $actor, 'invite');
        $q = $this->pdo->prepare('SELECT COUNT(*) FROM tenant_users WHERE tenant_id=?'); $q->execute([$tenant]);
        $memberCount = (int)$q->fetchColumn();
        $invitationCount = 0;
        if ($manager) {
            $q = $this->pdo->prepare('SELECT COUNT(*) FROM workspace_invitations WHERE tenant_id=? AND accepted_at IS NULL AND cancelled_at IS NULL AND expires_at>UTC_TIMESTAMP()');
            $q->execute([$tenant]); $invitationCount = (int)$q->fetchColumn();
        }
        $pages = max(1, (int) ceil(max($memberCount,$invitationCount)/25));
        $page = max(1, min($page, $pages)); $offset = ($page-1)*25;
        $q = $this->pdo->prepare('SELECT tu.user_id, tu.role, u.name' . ($manager ? ', u.email' : '') . " FROM tenant_users tu JOIN users u ON u.id=tu.user_id WHERE tu.tenant_id=? ORDER BY tu.user_id LIMIT 25 OFFSET $offset");
        $q->execute([$tenant]); $members = $q->fetchAll();
        $invitations = [];
        if ($manager) {
            $q = $this->pdo->prepare('SELECT id,email,role,expires_at FROM workspace_invitations WHERE tenant_id=? AND accepted_at IS NULL AND cancelled_at IS NULL AND expires_at>UTC_TIMESTAMP() ORDER BY id DESC LIMIT 25 OFFSET ' . $offset);
            $q->execute([$tenant]); $invitations = $q->fetchAll();
        }
        return compact('workspace', 'manager', 'members', 'invitations', 'page', 'pages');
    }

    public function invite(int $actor, int $tenant, string $email, string $role, Mailer $mailer, string $appUrl): bool
    {
        $email = strtolower(trim($email));
        if (strlen($email)>254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new HttpException('Informe um e-mail válido.', 422);
        $url = parse_url($appUrl);
        if (!$url || !in_array($url['scheme'] ?? '', ['http','https'], true) || empty($url['host']) || isset($url['query']) || isset($url['fragment']) || isset($url['user']) || isset($url['pass'])) throw new \InvalidArgumentException('APP_URL inválida.');
        $raw = bin2hex(random_bytes(32));
        $workspace = $this->transaction(function () use ($actor, $tenant, $email, $role, $raw) {
            $workspace = $this->workspace($actor, $tenant, true);
            $this->permit($workspace, $actor, 'invite', null, $role);
            $q = $this->pdo->prepare('SELECT 1 FROM tenant_users tu JOIN users u ON u.id=tu.user_id WHERE tu.tenant_id=? AND u.email=?'); $q->execute([$tenant,$email]);
            if ($q->fetchColumn()) throw new HttpException('O usuário já participa desta Área de Trabalho.',409);
            $q = $this->pdo->prepare('SELECT role FROM workspace_invitations WHERE tenant_id=? AND email=? FOR UPDATE'); $q->execute([$tenant,$email]);
            $previous = $q->fetchColumn();
            if ($previous) $this->permit($workspace,$actor,'invite',null,$previous);
            $this->pdo->prepare('INSERT INTO workspace_invitations(tenant_id,invited_by,email,role,token_hash,expires_at) VALUES (?,?,?,?,?,DATE_ADD(UTC_TIMESTAMP(), INTERVAL 7 DAY)) ON DUPLICATE KEY UPDATE invited_by=VALUES(invited_by),role=VALUES(role),token_hash=VALUES(token_hash),expires_at=VALUES(expires_at),accepted_at=NULL,cancelled_at=NULL')->execute([$tenant,$actor,$email,$role,hash('sha256',$raw)]);
            return $workspace;
        });
        try {
            $mailer->send($email,'Convite para uma Área de Trabalho',"Você recebeu um convite para a Área de Trabalho {$workspace['name']}.\n\n" . rtrim($appUrl,'/') . '/workspace-invitations/accept?token=' . $raw . "\n\nO link expira em 7 dias. Entre ou crie uma conta com este e-mail e confirme seu endereço antes de aceitar.");
            return true;
        } catch (Throwable) { error_log('[kanbansaas] Falha no envio SMTP de convite de Workspace.'); return false; }
    }

    public function cancel(int $actor, int $tenant, int $id): void
    {
        $this->transaction(function () use ($actor,$tenant,$id) {
            $workspace=$this->workspace($actor,$tenant,true);
            $q=$this->pdo->prepare('SELECT role FROM workspace_invitations WHERE tenant_id=? AND id=? AND accepted_at IS NULL AND cancelled_at IS NULL FOR UPDATE');$q->execute([$tenant,$id]);$role=$q->fetchColumn();
            if (!$role) throw new HttpException('Convite indisponível.',404);
            $this->permit($workspace,$actor,'invite',null,$role);
            $this->pdo->prepare('UPDATE workspace_invitations SET cancelled_at=UTC_TIMESTAMP() WHERE tenant_id=? AND id=?')->execute([$tenant,$id]);
        });
    }

    public function manage(int $actor, int $tenant, string $action, int $target = 0, string $value = ''): void
    {
        $this->transaction(function () use ($actor,$tenant,$action,$target,$value) {
            $workspace=$this->workspace($actor,$tenant,true);
            if ($action==='rename') {
                $this->permit($workspace,$actor,'rename');
                $this->pdo->prepare('UPDATE tenants SET name=? WHERE id=?')->execute([WorkspaceService::validateName($value),$tenant]);
                return;
            }
            if (!in_array($action,['role','transfer'],true)) throw new HttpException('Ação inválida.',422);
            $q=$this->pdo->prepare('SELECT tu.user_id,tu.role,u.email_verified_at FROM tenant_users tu JOIN users u ON u.id=tu.user_id WHERE tu.tenant_id=? AND tu.user_id=? FOR UPDATE');$q->execute([$tenant,$target]);$member=$q->fetch();
            if (!$member) throw new HttpException('Membro indisponível.',404);
            if ($action==='transfer') {
                $this->permit($workspace,$actor,'transfer_ownership');
                if ($target===$actor || !$member['email_verified_at']) throw new HttpException('Transferência não permitida.',422);
                $this->pdo->prepare('UPDATE tenants SET owner_id=? WHERE id=?')->execute([$target,$tenant]);
                $this->pdo->prepare("UPDATE tenant_users SET role='admin' WHERE tenant_id=? AND user_id=?")->execute([$tenant,$actor]);
            } else {
                $this->permit($workspace,$actor,'change_role',['id'=>$tenant]+$member,$value);
                $this->pdo->prepare('UPDATE tenant_users SET role=? WHERE tenant_id=? AND user_id=?')->execute([$value,$tenant,$target]);
            }
        });
    }

    public function pending(string|int $raw, bool $lock = false): array|false
    {
        if (!is_int($raw) && !preg_match('/^[a-f0-9]{64}$/D',$raw)) return false;
        $q=$this->pdo->prepare('SELECT * FROM workspace_invitations WHERE ' . (is_int($raw) ? 'id' : 'token_hash') . '=? AND accepted_at IS NULL AND cancelled_at IS NULL AND expires_at>UTC_TIMESTAMP()' . ($lock ? ' FOR UPDATE' : ''));
        $q->execute([is_int($raw) ? $raw : hash('sha256',$raw)]);return $q->fetch();
    }

    public function validInvitation(string $raw): array|false
    {
        $invitation=$this->pending($raw);
        if (!$invitation) return false;
        $workspace=(new WorkspaceRepository($this->pdo))->find((int)$invitation['invited_by'],(int)$invitation['tenant_id']);
        return $workspace && WorkspacePolicy::can($workspace,(int)$invitation['invited_by'],'invite',null,$invitation['role']) ? $invitation : false;
    }

    public function accept(int $userId, string|int $raw): int
    {
        $initial=$this->pending($raw);
        if (!$initial) throw new HttpException('Convite indisponível.',422);
        return $this->transaction(function () use ($userId,$raw,$initial) {
            $workspace=$this->workspace((int)$initial['invited_by'],(int)$initial['tenant_id'],true);
            $invitation=$this->pending($raw,true);
            if (!$invitation) throw new HttpException('Convite indisponível.',422);
            $this->permit($workspace,(int)$invitation['invited_by'],'invite',null,$invitation['role']);
            $q=$this->pdo->prepare('SELECT email,email_verified_at FROM users WHERE id=? FOR UPDATE');$q->execute([$userId]);$user=$q->fetch();
            if (!$user || !$user['email_verified_at'] || strcasecmp($user['email'],$invitation['email'])!==0) throw new HttpException('Entre com a conta verificada convidada.',403);
            // Aceitar não promove um vínculo que já existe nem substitui o padrão.
            $this->pdo->prepare('INSERT INTO tenant_users(tenant_id,user_id,role) VALUES (?,?,?) ON DUPLICATE KEY UPDATE tenant_id=VALUES(tenant_id)')->execute([$invitation['tenant_id'],$userId,$invitation['role']]);
            $this->pdo->prepare('UPDATE users SET default_tenant_id=? WHERE id=? AND default_tenant_id IS NULL')->execute([$invitation['tenant_id'],$userId]);
            $this->pdo->prepare('UPDATE workspace_invitations SET accepted_at=UTC_TIMESTAMP() WHERE id=?')->execute([$invitation['id']]);
            return (int)$invitation['tenant_id'];
        });
    }

    public function forEmail(string $email): array
    {
        $q=$this->pdo->prepare('SELECT id FROM workspace_invitations WHERE email=? AND accepted_at IS NULL AND cancelled_at IS NULL AND expires_at>UTC_TIMESTAMP()');$q->execute([$email]);return $q->fetchAll();
    }
}
