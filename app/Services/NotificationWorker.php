<?php

declare(strict_types=1);
namespace App\Services;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Throwable;

final class NotificationWorker
{
    public const MAX_ATTEMPTS=5;
    public function __construct(private PDO $pdo) {}

    public function run(DateTimeImmutable $now,int $limit=100): array
    {
        $stats=['delivered'=>0,'cancelled'=>0,'retry'=>0,'failed'=>0];
        for ($index=0;$index<max(1,min(500,$limit));$index++) {
            $row=$this->claim($now);if (!$row) break;
            if (!empty($row['_exhausted'])) { $stats['failed']++;continue; }
            try { $stats[$this->deliver($row,$now)]++; }
            catch (Throwable $error) {
                if ($this->pdo->inTransaction()) $this->pdo->rollBack();
                $state=(int)$row['attempts']>=self::MAX_ATTEMPTS?'failed':'retry';
                $next=$now->modify('+'.min(3600,60*(2**((int)$row['attempts']-1))).' seconds')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
                $this->pdo->prepare('UPDATE notification_outbox SET status=?,available_at=?,locked_until=NULL,lock_token=NULL,last_error=? WHERE id=? AND lock_token=? AND status=\'processing\'')->execute([$state,$next,'delivery_failed',$row['id'],$row['lock_token']]);$stats[$state]++;
            }
        }
        return $stats;
    }

    private function claim(DateTimeImmutable $now): ?array
    {
        $utc=$now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $this->pdo->beginTransaction();
        try {
            $query=$this->pdo->prepare("SELECT * FROM notification_outbox WHERE channel='bell' AND ((status IN ('pending','retry') AND available_at<=?) OR (status='processing' AND locked_until<=?)) ORDER BY id LIMIT 1 FOR UPDATE SKIP LOCKED");$query->execute([$utc,$utc]);$row=$query->fetch();
            if (!$row) {$this->pdo->commit();return null;}
            if ((int)$row['attempts']>=self::MAX_ATTEMPTS) { $this->pdo->prepare("UPDATE notification_outbox SET status='failed',lock_token=NULL,locked_until=NULL WHERE id=?")->execute([$row['id']]);$this->pdo->commit();$row['_exhausted']=true;return $row; }
            $row['lock_token']=bin2hex(random_bytes(16));$row['attempts']=(int)$row['attempts']+1;
            $lease=$now->modify('+5 minutes')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            $this->pdo->prepare("UPDATE notification_outbox SET status='processing',attempts=?,lock_token=?,locked_until=? WHERE id=?")->execute([$row['attempts'],$row['lock_token'],$lease,$row['id']]);$this->pdo->commit();return $row;
        } catch (Throwable $error) { $this->pdo->rollBack();throw $error; }
    }

    private function deliver(array $row,DateTimeImmutable $now): string
    {
        $this->pdo->beginTransaction();
        // Preferência linearizada antes da entrega. Os serviços de projeto também bloqueiam projects.
        $this->pdo->prepare('INSERT INTO notification_preferences(user_id) VALUES (?) ON DUPLICATE KEY UPDATE user_id=user_id')->execute([$row['user_id']]);
        $query=$this->pdo->prepare('SELECT bell_enabled FROM notification_preferences WHERE user_id=? FOR UPDATE');$query->execute([$row['user_id']]);$enabled=(bool)$query->fetchColumn();
        if ($row['project_id']) {
            $query=$this->pdo->prepare('SELECT id FROM projects WHERE tenant_id=? AND id=? FOR UPDATE');$query->execute([$row['tenant_id'],$row['project_id']]);
        } else {
            $query=$this->pdo->prepare('SELECT p.id FROM projects p WHERE p.deleted_at IS NULL AND p.archived_at IS NULL AND EXISTS (SELECT 1 FROM tasks t JOIN task_assignees a ON a.task_id=t.id AND a.project_id=t.project_id AND a.tenant_id=t.tenant_id WHERE t.project_id=p.id AND t.tenant_id=p.tenant_id AND t.due_on=? AND a.user_id=?) ORDER BY p.id FOR UPDATE');$query->execute([$row['due_on'],$row['user_id']]);
        }
        $query->fetchAll();
        $query=$this->pdo->prepare("SELECT o.id FROM notification_outbox o WHERE o.id=? AND o.lock_token=? AND o.status='processing' AND ".NotificationService::eligibleSql().' FOR UPDATE');$query->execute([$row['id'],$row['lock_token']]);$eligible=(bool)$query->fetchColumn();
        $day=$now->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('Y-m-d');
        $state=$enabled && $eligible && ($row['kind']!=='due_today'||$row['due_on']===$day) && ($row['kind']!=='overdue'||$row['due_on']<$day)?'delivered':'cancelled';
        if ($state==='delivered') $this->pdo->prepare('INSERT INTO notifications(outbox_id,user_id) VALUES (?,?) ON DUPLICATE KEY UPDATE outbox_id=outbox_id')->execute([$row['id'],$row['user_id']]);
        $query=$this->pdo->prepare('UPDATE notification_outbox SET status=?,delivered_at=?,locked_until=NULL,lock_token=NULL,last_error=NULL WHERE id=? AND lock_token=? AND status=\'processing\'');$query->execute([$state,$state==='delivered'?$now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'):null,$row['id'],$row['lock_token']]);
        $this->pdo->commit();return $state;
    }
}
