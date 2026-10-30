<?php

declare(strict_types=1);
namespace App\Services;

use PDO;
use Throwable;

final class NotificationService
{
    public function __construct(private PDO $pdo) {}

    // Alias fixo o. Identidade vem da linha do destinatário, nunca de parâmetros do cliente.
    public static function eligibleSql(): string
    {
        return "(o.actor_id IS NULL OR o.actor_id <> o.user_id) AND (
            (o.kind = 'due_today' AND EXISTS (SELECT 1 FROM tasks dt
                JOIN projects dp ON dp.id=dt.project_id AND dp.tenant_id=dt.tenant_id
                JOIN task_assignees da ON da.task_id=dt.id AND da.project_id=dt.project_id AND da.tenant_id=dt.tenant_id AND da.user_id=o.user_id
                JOIN tenant_users du ON du.tenant_id=dp.tenant_id AND du.user_id=o.user_id
                JOIN project_members dm ON dm.project_id=dp.id AND dm.tenant_id=dp.tenant_id AND dm.user_id=o.user_id
                JOIN users dv ON dv.id=o.user_id AND dv.email_verified_at IS NOT NULL
                WHERE dp.deleted_at IS NULL AND dp.archived_at IS NULL AND dt.completed_at IS NULL AND dt.due_on=o.due_on))
            OR (o.kind='invited' AND EXISTS (SELECT 1 FROM project_invitations i
                JOIN users iu ON iu.id=o.user_id AND iu.email=i.email AND iu.email_verified_at IS NOT NULL
                JOIN projects ip ON ip.id=i.project_id AND ip.tenant_id=i.tenant_id
                JOIN project_members im ON im.project_id=ip.id AND im.tenant_id=ip.tenant_id AND im.user_id=i.invited_by
                JOIN tenant_users it ON it.tenant_id=ip.tenant_id AND it.user_id=i.invited_by
                WHERE o.dedup_key=BINARY SHA2(CONCAT('invitation:',i.id,':',i.token_hash,':',o.user_id,IF(o.channel='bell','',CONCAT(':',o.channel))),256) AND i.id=o.subject_id AND i.project_id=o.project_id AND i.tenant_id=o.tenant_id AND i.invited_by=o.actor_id
                AND i.accepted_at IS NULL AND i.cancelled_at IS NULL AND i.expires_at>UTC_TIMESTAMP()
                AND ip.deleted_at IS NULL AND ip.archived_at IS NULL
                AND (ip.owner_id=i.invited_by OR (im.role='admin' AND i.role IN ('member','viewer')))))
            OR (o.kind NOT IN ('due_today','invited') AND EXISTS (SELECT 1 FROM projects p
                JOIN tenant_users tu ON tu.tenant_id=p.tenant_id AND tu.user_id=o.user_id
                JOIN project_members pm ON pm.tenant_id=p.tenant_id AND pm.project_id=p.id AND pm.user_id=o.user_id
                JOIN users u ON u.id=o.user_id AND u.email_verified_at IS NOT NULL
                WHERE p.id=o.project_id AND p.tenant_id=o.tenant_id AND p.deleted_at IS NULL AND p.archived_at IS NULL
                AND ((o.kind='role_changed' AND pm.role=o.target_role) OR (o.kind IN ('comment','assigned','overdue') AND EXISTS (
                    SELECT 1 FROM tasks t JOIN task_assignees a ON a.task_id=t.id AND a.project_id=t.project_id AND a.tenant_id=t.tenant_id AND a.user_id=o.user_id
                    WHERE t.id=o.task_id AND t.project_id=p.id AND t.tenant_id=p.tenant_id
                    AND (o.kind <> 'overdue' OR (t.completed_at IS NULL AND t.due_on=o.due_on))
                    AND (o.kind <> 'comment' OR EXISTS (SELECT 1 FROM task_comments c WHERE c.id=o.subject_id AND c.task_id=t.id AND c.project_id=p.id AND c.tenant_id=p.tenant_id AND c.deleted_at IS NULL))
                )))))
        )";
    }

    public function preferences(int $userId): array
    {
        $query=$this->pdo->prepare('SELECT bell_enabled,push_enabled,email_enabled FROM notification_preferences WHERE user_id=?');
        $query->execute([$userId]);
        return array_map('boolval', $query->fetch() ?: ['bell_enabled'=>1,'push_enabled'=>0,'email_enabled'=>0]);
    }

    public function savePreferences(int $userId, int $version, array $flags): void
    {
        $this->pdo->beginTransaction();
        try {
            $query=$this->pdo->prepare('SELECT session_version,email_verified_at FROM users WHERE id=? FOR UPDATE');$query->execute([$userId]);$user=$query->fetch();
            if (!$user || !$user['email_verified_at'] || (int)$user['session_version'] !== $version) throw new \DomainException('Sua sessão expirou. Entre novamente.',401);
            $this->pdo->prepare('INSERT INTO notification_preferences(user_id,bell_enabled,push_enabled,email_enabled) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE bell_enabled=VALUES(bell_enabled),push_enabled=VALUES(push_enabled),email_enabled=VALUES(email_enabled)')->execute([$userId,(int)$flags['bell_enabled'],(int)$flags['push_enabled'],(int)$flags['email_enabled']]);
            $this->pdo->commit();
        } catch (Throwable $error) { $this->pdo->rollBack(); throw $error; }
    }

    public function unread(int $userId): int
    {
        $query=$this->pdo->prepare('SELECT COUNT(*) FROM notifications n JOIN notification_outbox o ON o.id=n.outbox_id AND o.user_id=n.user_id WHERE n.user_id=? AND n.read_at IS NULL AND '.self::eligibleSql());
        $query->execute([$userId]);return (int)$query->fetchColumn();
    }

    public function page(int $userId, int $page): array
    {
        $where=' FROM notifications n JOIN notification_outbox o ON o.id=n.outbox_id AND o.user_id=n.user_id WHERE n.user_id=? AND '.self::eligibleSql();
        $query=$this->pdo->prepare('SELECT COUNT(*)'.$where);$query->execute([$userId]);$total=(int)$query->fetchColumn();$pages=max(1,(int)ceil($total/20));$page=min($page,$pages);$offset=($page-1)*20;
        $query=$this->pdo->prepare('SELECT o.*,n.id AS notification_id,n.read_at,n.created_at AS notification_created_at'.$where.' ORDER BY n.id DESC LIMIT 20 OFFSET '.$offset);$query->execute([$userId]);
        $items=[];foreach ($query->fetchAll() as $row) { $content=$this->content($row);if ($content['links']) $items[]=$row+$content; }
        return compact('items','total','page','pages');
    }

    public function markRead(int $userId, int $id): bool
    {
        $where=' n JOIN notification_outbox o ON o.id=n.outbox_id AND o.user_id=n.user_id WHERE n.id=? AND n.user_id=? AND '.self::eligibleSql();
        $query=$this->pdo->prepare('SELECT n.id FROM notifications'.$where);$query->execute([$id,$userId]);if (!$query->fetchColumn()) return false;
        $query=$this->pdo->prepare('UPDATE notifications n JOIN notification_outbox o ON o.id=n.outbox_id AND o.user_id=n.user_id SET n.read_at=COALESCE(n.read_at,UTC_TIMESTAMP()) WHERE n.id=? AND n.user_id=? AND '.self::eligibleSql());$query->execute([$id,$userId]);return true;
    }

    // Conteúdo reconstruído com acesso atual; nunca usa corpo do comentário, descrição ou e-mail.
    public function content(array $row): array
    {
        if ($row['kind']==='due_today') {
            $query=$this->pdo->prepare('SELECT t.id,t.title,p.id AS project_id,p.name FROM tasks t JOIN projects p ON p.id=t.project_id AND p.tenant_id=t.tenant_id JOIN task_assignees a ON a.task_id=t.id AND a.project_id=p.id AND a.tenant_id=p.tenant_id JOIN tenant_users tu ON tu.user_id=a.user_id AND tu.tenant_id=p.tenant_id JOIN project_members pm ON pm.user_id=a.user_id AND pm.tenant_id=p.tenant_id AND pm.project_id=p.id WHERE a.user_id=? AND t.due_on=? AND t.completed_at IS NULL AND p.archived_at IS NULL AND p.deleted_at IS NULL ORDER BY p.id,t.id LIMIT 5');
            $query->execute([$row['user_id'],$row['due_on']]);$links=[];
            foreach ($query->fetchAll() as $task) $links[]=['title'=>$task['title'].' · '.$task['name'],'url'=>'/projects/'.$task['project_id'].'/board?task='.$task['id']];
            return ['title'=>'Prazos de '.date('d/m/Y',strtotime($row['due_on'])),'links'=>$links,'more_url'=>'/tasks?status=pending&from='.$row['due_on'].'&to='.$row['due_on']];
        }
        $query=$this->pdo->prepare('SELECT p.name,t.title FROM notification_outbox o JOIN projects p ON p.id=o.project_id AND p.tenant_id=o.tenant_id LEFT JOIN tasks t ON t.id=o.task_id AND t.project_id=p.id AND t.tenant_id=p.tenant_id WHERE o.id=? AND '.self::eligibleSql());$query->execute([$row['id']]);$context=$query->fetch();
        $role=['member'=>'Membro','viewer'=>'Visualizador','admin'=>'Administrador'][$row['target_role'] ?? ''] ?? '';
        $title=match($row['kind']) {'invited'=>'Convite para projeto','overdue'=>'Prazo vencido','comment'=>'Nova mensagem','assigned'=>'Tarefa atribuída','role_changed'=>'Papel alterado para '.$role};
        return ['title'=>$title,'links'=>$context ? [['title'=>$row['task_id'] ? $context['title'].' · '.$context['name'] : $context['name'],'url'=>$row['kind']==='invited'?'/invitations/accept?id='.$row['subject_id']:'/projects/'.$row['project_id'].($row['task_id'] ? '/board?task='.$row['task_id'] : '')]] : [],'more_url'=>null];
    }
}
