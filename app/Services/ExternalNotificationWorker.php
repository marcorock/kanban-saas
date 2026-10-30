<?php

declare(strict_types=1);
namespace App\Services;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Throwable;

final class ExternalNotificationWorker
{
    public function __construct(private PDO $pdo,private array $config,private ?PushGateway $push=null) {}

    public function run(DateTimeImmutable $now,int $limit=100): array
    {
        $stats=['delivered'=>0,'cancelled'=>0,'retry'=>0,'failed'=>0];$remaining=max(1,min(500,$limit));$seen=[];
        while ($remaining>0) {
            $candidate=$this->candidate($now,$seen);if (!$candidate) break;
            $user=(int)$candidate['user_id'];$channel=$candidate['channel'];$seen[]=$user.':'.$channel;
            // Serializa o destinatário/canal também durante SMTP/push, sem bloquear requisições com locks SQL.
            $lock='kanban-notify:'.hash('sha256',$this->config['database']['name'].':'.$user.':'.$channel);
            $lock=substr($lock,0,64);$q=$this->pdo->prepare('SELECT GET_LOCK(?,0)');$q->execute([$lock]);
            if ((int)$q->fetchColumn()!==1) continue;
            $rows=[];
            try {
                $rows=$this->claim($candidate,$now,min($remaining,$channel==='email'?100:20));$remaining-=count($rows);
                if (!$rows) continue;
                $valid=[];
                foreach($rows as $row) {
                    if ((int)$row['attempts']>NotificationWorker::MAX_ATTEMPTS) {$this->finish($row,'failed',$now);$stats['failed']++;}
                    elseif (!$this->current($row,$now)) {$this->finish($row,'cancelled',$now);$stats['cancelled']++;}
                    else $valid[]=$row;
                }
                if (!$valid) continue;
                if ($channel==='email') $this->email($valid,$now,$stats);
                else foreach($valid as $row) {
                    try {$state=$this->sendPush($row,$now);}
                    catch(Throwable) {$state=$this->retryState($row);}
                    $this->finish($row,$state,$now);$stats[$state]++;
                }
            } catch(Throwable) {
                if ($this->pdo->inTransaction()) $this->pdo->rollBack();
                foreach($rows as $row) {$state=$this->retryState($row);$this->finish($row,$state,$now);$stats[$state]++;}
            } finally {$q=$this->pdo->prepare('SELECT RELEASE_LOCK(?)');$q->execute([$lock]);}
        }
        return $stats;
    }
    private function candidate(DateTimeImmutable $now,array $seen): array|false
    {
        $exclude=$seen?' AND CONCAT(user_id,\':\',channel) NOT IN ('.implode(',',array_fill(0,count($seen),'?')).')':'';
        $q=$this->pdo->prepare("SELECT * FROM notification_outbox WHERE channel IN ('push','email') AND ((status IN ('pending','retry') AND available_at<=?) OR (status='processing' AND locked_until<=?))".$exclude.' ORDER BY id LIMIT 1');
        $q->execute([$this->utc($now),$this->utc($now),...$seen]);return $q->fetch();
    }
    private function claim(array $candidate,DateTimeImmutable $now,int $limit): array
    {
        $deadlines=$candidate['channel']==='email'&&in_array($candidate['kind'],['due_today','overdue'],true);
        $condition=$candidate['channel']==='push'?'':($deadlines?" AND kind IN ('due_today','overdue')":' AND id='.(int)$candidate['id']);
        $this->pdo->beginTransaction();
        try {
            $q=$this->pdo->prepare("SELECT * FROM notification_outbox WHERE user_id=? AND channel=? AND ((status IN ('pending','retry') AND available_at<=?) OR (status='processing' AND locked_until<=?))".$condition.' ORDER BY id LIMIT '.$limit.' FOR UPDATE SKIP LOCKED');
            $q->execute([$candidate['user_id'],$candidate['channel'],$this->utc($now),$this->utc($now)]);$rows=$q->fetchAll();
            foreach($rows as &$row) {
                $row['attempts']=(int)$row['attempts']+1;$row['lock_token']=bin2hex(random_bytes(16));
                $this->pdo->prepare("UPDATE notification_outbox SET status='processing',attempts=?,lock_token=?,locked_until=? WHERE id=?")->execute([$row['attempts'],$row['lock_token'],$this->utc($now->modify('+5 minutes')),$row['id']]);
            }unset($row);
            $this->pdo->commit();return $rows;
        }catch(Throwable $error){$this->pdo->rollBack();throw $error;}
    }
    private function current(array $row,DateTimeImmutable $now): array|false
    {
        $flag=$row['channel']==='push'?'push_enabled':'email_enabled';
        $q=$this->pdo->prepare("SELECT u.email FROM notification_outbox o JOIN users u ON u.id=o.user_id JOIN notification_preferences f ON f.user_id=o.user_id AND f.".$flag."=1 WHERE o.id=? AND o.lock_token=? AND o.status='processing' AND ".NotificationService::eligibleSql());
        $q->execute([$row['id'],$row['lock_token']]);$user=$q->fetch();
        $day=$now->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('Y-m-d');
        return $user&&($row['kind']!=='due_today'||$row['due_on']===$day)&&($row['kind']!=='overdue'||$row['due_on']<$day)?$user:false;
    }
    private function email(array $rows,DateTimeImmutable $now,array &$stats): void
    {
        $items=[];$valid=[];$email='';$service=new NotificationService($this->pdo);
        foreach($rows as $row) {
            $user=$this->current($row,$now);$content=$user?$service->content($row):null;
            if (!$content||!$content['links']) {$this->finish($row,'cancelled',$now);$stats['cancelled']++;continue;}
            $email=$user['email'];$items[]=$this->clean($content);$valid[]=$row;
        }
        if (!$valid) return;
        try {(new NotificationEmail($this->config))->send($email,$items,in_array($valid[0]['kind'],['due_today','overdue'],true));$success=true;}
        catch(Throwable){$success=false;}
        foreach($valid as $row) {$state=$success?'delivered':$this->retryState($row);$this->finish($row,$state,$now);$stats[$state]++;}
    }
    private function sendPush(array $row,DateTimeImmutable $now): string
    {
        $q=$this->pdo->prepare('SELECT * FROM push_subscriptions WHERE user_id=? ORDER BY id');$q->execute([$row['user_id']]);$subscriptions=$q->fetchAll();
        if (!$subscriptions) return 'cancelled';
        $failed=false;$delivered=false;
        foreach($subscriptions as $subscription) {
            if (!$this->current($row,$now)) return 'cancelled';
            $q=$this->pdo->prepare('SELECT 1 FROM notification_push_deliveries WHERE outbox_id=? AND subscription_id=?');$q->execute([$row['id'],$subscription['id']]);
            if ($q->fetchColumn()) {$delivered=true;continue;}
            $content=(new NotificationService($this->pdo))->content($row);if (!$content['links']) return 'cancelled';
            $content=$this->clean($content);$first=$content['links'][0];
            try {
                $this->push??=new WebPushGateway($this->config['push']);
                $status=$this->push->send($subscription,['title'=>$content['title'],'body'=>mb_substr($first['title'],0,180),'url'=>$content['more_url']??$first['url'],'tag'=>'kanban-'.$row['id'],'user_id'=>(int)$row['user_id']]);
                if (in_array($status,[404,410],true)) {$this->pdo->prepare('DELETE FROM push_subscriptions WHERE id=? AND user_id=?')->execute([$subscription['id'],$row['user_id']]);continue;}
                if ($status<200||$status>=300) {$failed=true;continue;}
                $this->pdo->prepare('INSERT IGNORE INTO notification_push_deliveries(outbox_id,subscription_id) VALUES (?,?)')->execute([$row['id'],$subscription['id']]);$delivered=true;
            }catch(Throwable){$failed=true;}
        }
        return $failed?$this->retryState($row):($delivered?'delivered':'cancelled');
    }
    private function clean(array $content): array
    {
        $plain=static fn(string $text): string=>trim(preg_replace('/[\x00-\x1f\x7f]/u',' ',$text));
        $content['title']=mb_substr($plain($content['title']),0,120);
        foreach($content['links'] as &$link)$link['title']=$plain($link['title']);unset($link);return $content;
    }
    private function retryState(array $row): string {return (int)$row['attempts']>=NotificationWorker::MAX_ATTEMPTS?'failed':'retry';}
    private function finish(array $row,string $state,DateTimeImmutable $now): void
    {
        $next=$state==='retry'?$now->modify('+'.min(3600,60*2**max(0,(int)$row['attempts']-1)).' seconds'):$now;
        $q=$this->pdo->prepare("UPDATE notification_outbox SET status=?,available_at=?,delivered_at=?,locked_until=NULL,lock_token=NULL,last_error=? WHERE id=? AND status='processing' AND lock_token=?");
        $q->execute([$state,$this->utc($next),$state==='delivered'?$this->utc($now):null,in_array($state,['retry','failed'],true)?'delivery_failed':null,$row['id'],$row['lock_token']]);
    }
    private function utc(DateTimeImmutable $now): string {return $now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');}
}
