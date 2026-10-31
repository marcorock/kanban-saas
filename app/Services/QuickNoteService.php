<?php

declare(strict_types=1);
namespace App\Services;
use App\Repositories\ProjectRepository;
use PDO;
use Throwable;
use Pecee\SimpleRouter\Exceptions\HttpException;

final class QuickNoteService
{
    public function __construct(private PDO $pdo) {}
    public function projects(int $user,bool $writable=false):array
    {
        $q=$this->pdo->prepare("SELECT p.id,p.name FROM projects p JOIN project_members m ON m.project_id=p.id AND m.tenant_id=p.tenant_id JOIN tenant_users t ON t.tenant_id=p.tenant_id AND t.user_id=m.user_id WHERE m.user_id=? AND p.deleted_at IS NULL AND p.archived_at IS NULL".($writable?" AND m.role IN ('owner','admin','member')":'').' ORDER BY p.name,p.id');$q->execute([$user]);return $q->fetchAll();
    }
    private function personal(int $user):int
    {
        $q=$this->pdo->prepare("SELECT t.id FROM tenants t JOIN tenant_users u ON u.tenant_id=t.id AND u.user_id=t.owner_id WHERE t.owner_id=? AND t.kind='personal' ORDER BY t.id LIMIT 1");$q->execute([$user]);$id=$q->fetchColumn();if(!$id)throw new HttpException('Inbox indisponível.',404);return (int)$id;
    }
    private function project(int $user,int $id,bool $write=false):array
    {
        $p=(new ProjectRepository($this->pdo))->find($user,$id,$this->pdo->inTransaction());
        if($write&&$p['archived_at'])throw new HttpException('O projeto está arquivado.',409);
        if($write&&!in_array($p['role'],['owner','admin','member'],true))throw new HttpException('Seu papel não permite criar tarefas.',403);
        return $p;
    }
    public function columns(int $user,int $project):array
    {
        $p=$this->project($user,$project,true);$q=$this->pdo->prepare('SELECT id,name,is_done FROM kanban_columns WHERE tenant_id=? AND project_id=? ORDER BY position,id');$q->execute([$p['tenant_id'],$project]);return $q->fetchAll();
    }
    private function body(mixed $body):string
    {
        if(!is_string($body)||!mb_check_encoding($body,'UTF-8')||trim($body)===''||mb_strlen(trim($body))>5000||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$body))throw new HttpException('Escreva uma nota de até 5.000 caracteres.',422);
        return trim($body);
    }
    private function title(mixed $title):string
    {
        if(!is_string($title)||!mb_check_encoding($title,'UTF-8')||mb_strlen(trim($title))>200||preg_match('/[\x00-\x1f\x7f]/',$title))throw new HttpException('Use um título de até 200 caracteres, sem quebras de linha.',422);
        return trim($title);
    }
    private function color(mixed $color):string
    {
        if(!is_string($color)||!in_array($color,['yellow','orange','green'],true))throw new HttpException('Escolha um dos três graus de urgência disponíveis.',422);
        return $color;
    }
    public function create(int $user,mixed $body,?int $project,mixed $title='',mixed $color='yellow'):int
    {
        $body=$this->body($body);$title=$this->title($title);$color=$this->color($color);
        $this->pdo->beginTransaction();
        try {
            if($project===null)$tenant=$this->personal($user);
            else{$p=$this->project($user,$project);if($p['archived_at'])throw new HttpException('Escolha um projeto ativo.',409);$tenant=(int)$p['tenant_id'];}
            $q=$this->pdo->prepare('INSERT INTO quick_notes(tenant_id,user_id,project_id,body,title,color) VALUES (?,?,?,?,?,?)');$q->execute([$tenant,$user,$project,$body,$title,$color]);$id=(int)$this->pdo->lastInsertId();$this->pdo->commit();return $id;
        }catch(Throwable $e){$this->pdo->rollBack();throw $e;}
    }
    public function page(int $user,?int $project,int $page=1):array
    {
        $tenant=$project===null?$this->personal($user):(int)$this->project($user,$project)['tenant_id'];
        $where=' FROM quick_notes WHERE tenant_id=? AND user_id=? AND project_id <=> ?';$params=[$tenant,$user,$project];
        $q=$this->pdo->prepare('SELECT COUNT(*)'.$where);$q->execute($params);$total=(int)$q->fetchColumn();$pages=max(1,(int)ceil($total/20));$page=max(1,min($page,$pages));
        $q=$this->pdo->prepare('SELECT id,project_id,body,title,color,created_at'.$where.' ORDER BY id DESC LIMIT 20 OFFSET '.(($page-1)*20));$q->execute($params);return ['items'=>$q->fetchAll(),'total'=>$total,'page'=>$page,'pages'=>$pages];
    }
    private function owned(int $user,int $id,bool $lock=false,?array $known=null):array
    {
        $q=$this->pdo->prepare('SELECT n.* FROM quick_notes n JOIN tenant_users t ON t.tenant_id=n.tenant_id AND t.user_id=n.user_id WHERE n.id=? AND n.user_id=?'.($lock?' FOR UPDATE':''));$q->execute([$id,$user]);$n=$q->fetch();if(!$n)throw new HttpException('Nota não encontrada.',404);
        if($n['project_id']!==null){if($known&&(int)$n['project_id']!==(int)$known['id'])throw new HttpException('Converta no projeto ao qual a nota pertence.',422);$p=$known??$this->project($user,(int)$n['project_id']);if((int)$p['tenant_id']!==(int)$n['tenant_id'])throw new HttpException('Nota não encontrada.',404);}
        elseif((int)$n['tenant_id']!==$this->personal($user))throw new HttpException('Nota não encontrada.',404);
        return $n;
    }
    public function update(int $user,int $id,mixed $body,array $appearance=[]):?int
    {
        $body=$this->body($body);
        $title=array_key_exists('title',$appearance)?$this->title($appearance['title']):null;
        $color=array_key_exists('color',$appearance)?$this->color($appearance['color']):null;
        $hint=$this->owned($user,$id);
        $this->pdo->beginTransaction();
        try {
            $p=$hint['project_id']===null?null:$this->project($user,(int)$hint['project_id']);$n=$this->owned($user,$id,true,$p);
            $this->pdo->prepare('UPDATE quick_notes SET body=?,title=?,color=? WHERE id=? AND tenant_id=? AND user_id=?')->execute([$body,$title??$n['title'],$color??$n['color'],$id,$n['tenant_id'],$user]);
            $this->pdo->commit();return $n['project_id']===null?null:(int)$n['project_id'];
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
    public function delete(int $user,int $id):void
    {
        $hint=$this->owned($user,$id);$this->pdo->beginTransaction();
        try {
            $p=$hint['project_id']===null?null:$this->project($user,(int)$hint['project_id']);$n=$this->owned($user,$id,true,$p);
            $this->pdo->prepare('DELETE FROM quick_notes WHERE id=? AND tenant_id=? AND user_id=?')->execute([$id,$n['tenant_id'],$user]);$this->pdo->commit();
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
    public function convert(int $user,int $id,int $project,int $column):int
    {
        $this->pdo->beginTransaction();
        try {
            // Mesma ordem/serialização do Kanban: projeto antes da nota/coluna.
            $p=$this->project($user,$project,true);$n=$this->owned($user,$id,true,$p);
            if($n['project_id']!==null&&(int)$n['project_id']!==$project)throw new HttpException('Converta no projeto ao qual a nota pertence.',422);
            $q=$this->pdo->prepare('SELECT id,is_done FROM kanban_columns WHERE tenant_id=? AND project_id=? AND id=?');$q->execute([$p['tenant_id'],$project,$column]);$c=$q->fetch();if(!$c)throw new HttpException('Coluna indisponível neste projeto.',422);
            $q=$this->pdo->prepare('SELECT COUNT(*) FROM tasks WHERE tenant_id=? AND project_id=? AND column_id=?');$q->execute([$p['tenant_id'],$project,$column]);$position=(int)$q->fetchColumn();
            $title=$n['title']!==''?$n['title']:mb_substr(preg_replace('/\s+/u',' ',$n['body']),0,200);$done=$c['is_done']?$this->pdo->query('SELECT UTC_TIMESTAMP()')->fetchColumn():null;
            $this->pdo->prepare('INSERT INTO tasks(tenant_id,project_id,column_id,created_by,title,description,position,completed_at) VALUES (?,?,?,?,?,?,?,?)')->execute([$p['tenant_id'],$project,$column,$user,$title,$n['body'],$position,$done]);$task=(int)$this->pdo->lastInsertId();
            $this->pdo->prepare('INSERT INTO task_assignees(tenant_id,project_id,task_id,user_id) VALUES (?,?,?,?)')->execute([$p['tenant_id'],$project,$task,$user]);
            $logger=new ActivityLogger($this->pdo);$logger->record($p,$user,'task.created',null,['title'=>$title,'column_id'=>$column,'position'=>$position,'completed_at'=>$done],$task);
            if($done!==null)$logger->record($p,$user,'task.completed',null,['completed_at'=>$done],$task);
            $logger->record($p,$user,'task.assignees_changed',['user_ids'=>[]],['user_ids'=>[$user]],$task);
            $this->pdo->prepare('UPDATE projects SET board_version=board_version+1 WHERE tenant_id=? AND id=?')->execute([$p['tenant_id'],$project]);
            $this->pdo->prepare('DELETE FROM quick_notes WHERE id=? AND tenant_id=? AND user_id=?')->execute([$id,$n['tenant_id'],$user]);$this->pdo->commit();return $task;
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
}
