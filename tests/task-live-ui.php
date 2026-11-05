<?php

declare(strict_types=1);
require __DIR__.'/members-ui.php';
$boards=new App\Services\BoardService($pdo);$owner=$users['owner']['id'];$member=$users['member']['id'];$project=$ids['active'];$state=$boards->state($owner,$project);
function liveChange(string $action,array $data):array{global $boards,$owner,$project,$state;$state=$boards->mutate($owner,$project,$state['version'],$action,$data);return $state;}
liveChange('task.create',['column_id'=>$state['columns'][0]['id'],'title'=>'Tarefa em tempo real']);$task=end($state['columns'][0]['tasks'])['id'];
liveChange('task.update',['task_id'=>$task,'title'=>'Tarefa em tempo real','description'=>'Texto inicial','priority'=>'normal','starts_on'=>'','due_on'=>'','assignee_ids'=>[$member]]);
liveChange('checklist.create',['task_id'=>$task,'title'=>'Verificações']);$details=$boards->task($owner,$project,$task)['details'];$checklist=$details['checklists'][0]['id'];
for($i=1;$i<=3;$i++)liveChange('item.create',['task_id'=>$task,'checklist_id'=>$checklist,'title'=>'Etapa '.$i]);
for($i=1;$i<=22;$i++)liveChange('comment.create',['task_id'=>$task,'body'=>'Comentário inicial '.$i]);
$details=$boards->task($owner,$project,$task)['details'];$item=$details['checklists'][0]['items'][0]['id'];
(new App\Services\NotificationWorker($pdo))->run(new DateTimeImmutable('+1 second',new DateTimeZone('UTC')),500);
$notifications=new App\Services\NotificationService($pdo);$unread=$notifications->unread($member);$q=$pdo->prepare('SELECT id FROM notifications WHERE user_id=? AND read_at IS NULL ORDER BY id');$q->execute([$member]);$notificationIds=$q->fetchAll(PDO::FETCH_COLUMN);
$f=json_decode(file_get_contents($directory.'/members-fixture.json'),true,512,JSON_THROW_ON_ERROR);$f['live']=compact('task','checklist','item','unread','notificationIds');file_put_contents($directory.'/members-fixture.json',json_encode($f,JSON_THROW_ON_ERROR));
echo "OK: fixture de painel, paginação e sino com worker real em banco descartável.\n";
