<?php

declare(strict_types=1);
namespace App\Services;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class NotificationOutbox
{
    public function __construct(private PDO $pdo) {}

    public function enqueue(int $userId, string $kind, string $day, array $scope, string $origin): void
    {
        if (($scope['actor_id'] ?? null)===$userId) return;
        $preferences=(new NotificationService($this->pdo))->preferences($userId);
        foreach (['bell','push','email'] as $channel) {
            if ($kind==='invited' && $channel!=='bell') continue;
            if ($channel!=='bell' && !$preferences[$channel.'_enabled']) continue;
            $key=hash('sha256',implode('|',[$userId,$kind,$scope['task_id']??0,$scope['due_on']??$day,$origin,$channel]));
            if ($kind==='invited') $key=hash('sha256','invitation:'.$scope['subject_id'].':'.$scope['invitation_hash'].':'.$userId.($channel==='bell'?'':':'.$channel));
            $this->pdo->prepare('INSERT INTO notification_outbox(user_id,tenant_id,project_id,task_id,actor_id,event_id,subject_id,kind,event_day,due_on,target_role,dedup_key,channel) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id=id')->execute([$userId,$scope['tenant_id']??null,$scope['project_id']??null,$scope['task_id']??null,$scope['actor_id']??null,$scope['event_id']??null,$scope['subject_id']??null,$kind,$day,$scope['due_on']??null,$scope['target_role']??null,$key,$channel]);
        }
    }

    public function capture(array $project,int $actor,string $action,?array $before,?array $after,?int $taskId,int $eventId): void
    {
        if (!in_array($action,['comment.created','task.assignees_changed','member.role_changed','invitation.created','invitation.reissued'],true)) return;
        if (!$this->pdo->inTransaction()) throw new \LogicException('A outbox exige a transação do evento.');
        $day=(new DateTimeImmutable('now',new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
        $scope=['tenant_id'=>(int)$project['tenant_id'],'project_id'=>(int)$project['id'],'task_id'=>$taskId,'actor_id'=>$actor,'event_id'=>$eventId];
        if (in_array($action,['invitation.created','invitation.reissued'],true)) {
            $query=$this->pdo->prepare('SELECT u.id AS user_id,i.id,i.token_hash FROM project_invitations i JOIN users u ON u.email=i.email WHERE i.tenant_id=? AND i.project_id=? AND i.email=? AND i.accepted_at IS NULL AND i.cancelled_at IS NULL AND i.expires_at>UTC_TIMESTAMP()');
            $query->execute([$project['tenant_id'],$project['id'],$after['email']]);
            if ($invitation=$query->fetch()) $this->enqueue((int)$invitation['user_id'],'invited',$day,$scope+['subject_id'=>(int)$invitation['id'],'invitation_hash'=>$invitation['token_hash']],'event:'.$eventId);
            return;
        }
        if ($action==='member.role_changed') {
            if (in_array($after['role']??null,['member','viewer','admin'],true)) $this->enqueue((int)$after['user_id'],'role_changed',$day,$scope+['target_role'=>$after['role']],'event:'.$eventId);
            return;
        }
        $query=$this->pdo->prepare('SELECT a.user_id FROM task_assignees a JOIN tenant_users tu ON tu.tenant_id=a.tenant_id AND tu.user_id=a.user_id JOIN project_members pm ON pm.tenant_id=a.tenant_id AND pm.project_id=a.project_id AND pm.user_id=a.user_id JOIN users u ON u.id=a.user_id AND u.email_verified_at IS NOT NULL WHERE a.tenant_id=? AND a.project_id=? AND a.task_id=? AND a.user_id<>?');
        $query->execute([$project['tenant_id'],$project['id'],$taskId,$actor]);
        $added=$action==='task.assignees_changed' ? array_diff($after['user_ids']??[],$before['user_ids']??[]) : null;
        foreach ($query->fetchAll(PDO::FETCH_COLUMN) as $user) {
            if ($added!==null && !in_array((int)$user,$added,true)) continue;
            $this->enqueue((int)$user,$action==='comment.created'?'comment':'assigned',$day,$scope+['subject_id'=>$after['comment_id']??null],'event:'.$eventId);
        }
    }
}
