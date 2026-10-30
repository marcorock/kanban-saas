<?php

declare(strict_types=1);
namespace App\Services;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class DeadlineNotificationService
{
    public function __construct(private PDO $pdo) {}

    public function generate(DateTimeImmutable $now,int $limit=1000): void
    {
        $local=$now->setTimezone(new DateTimeZone('America/Sao_Paulo'));$day=$local->format('Y-m-d');$limit=max(1,min(1000,$limit));
        $joins=' FROM tasks t JOIN projects p ON p.id=t.project_id AND p.tenant_id=t.tenant_id JOIN task_assignees a ON a.task_id=t.id AND a.project_id=p.id AND a.tenant_id=p.tenant_id JOIN tenant_users tu ON tu.tenant_id=p.tenant_id AND tu.user_id=a.user_id JOIN project_members pm ON pm.tenant_id=p.tenant_id AND pm.project_id=p.id AND pm.user_id=a.user_id JOIN users u ON u.id=a.user_id AND u.email_verified_at IS NOT NULL WHERE p.deleted_at IS NULL AND p.archived_at IS NULL AND t.completed_at IS NULL';
        $outbox=new NotificationOutbox($this->pdo);
        $query=$this->pdo->prepare('SELECT t.id,t.tenant_id,t.project_id,t.due_on,a.user_id'.$joins." AND t.due_on < ? AND NOT EXISTS (SELECT 1 FROM notification_outbox o WHERE o.kind='overdue' AND o.channel='bell' AND o.user_id=a.user_id AND o.task_id=t.id AND o.due_on=t.due_on) ORDER BY t.due_on,t.id,a.user_id LIMIT ".$limit);
        $query->execute([$day]);
        foreach ($query->fetchAll() as $task) $outbox->enqueue((int)$task['user_id'],'overdue',$day,['tenant_id'=>(int)$task['tenant_id'],'project_id'=>(int)$task['project_id'],'task_id'=>(int)$task['id'],'due_on'=>$task['due_on']],'deadline');
        if ($local->format('H:i')<'08:00') return;
        $query=$this->pdo->prepare('SELECT DISTINCT a.user_id'.$joins." AND t.due_on=? AND NOT EXISTS (SELECT 1 FROM notification_outbox o WHERE o.kind='due_today' AND o.channel='bell' AND o.user_id=a.user_id AND o.due_on=?) ORDER BY a.user_id LIMIT ".$limit);
        $query->execute([$day,$day]);
        foreach ($query->fetchAll(PDO::FETCH_COLUMN) as $user) $outbox->enqueue((int)$user,'due_today',$day,['due_on'=>$day],'daily');
    }
}
