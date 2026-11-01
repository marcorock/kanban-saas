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
        try{$p=(new ProjectRepository($this->pdo))->find($user,$id,$this->pdo->inTransaction());}catch(\PDOException $e){if((int)($e->errorInfo[1]??0)===1020)throw new HttpException('O projeto foi alterado. Recarregue a lista.',409);throw $e;}
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
    private function visibility(mixed $value,?int $project):string
    {
        if(!is_string($value)||!in_array($value,['private','project'],true))throw new HttpException('Escolha Privado ou Projeto. Público ainda não está disponível.',422);
        if($value==='project'&&$project===null)throw new HttpException('Vincule a nota a um projeto para compartilhá-la.',422);
        return $value;
    }
    private function revision(mixed $value):int
    {
        if((!is_int($value)&&!is_string($value))||!ctype_digit((string)$value)||strlen((string)$value)>18||(int)$value<1)throw new HttpException('Versão da nota inválida.',422);
        return (int)$value;
    }
    private function snapshot(array $note):array
    {
        return array_intersect_key($note,array_flip(['tenant_id','project_id','user_id','created_by','visibility','color','revision','task_id']));
    }
    private function audit(int $user,int $id,int $tenant,string $action,?array $before,?array $after,array $changed=[]):void
    {
        $old=$before===null?null:$this->snapshot($before);$new=$after===null?null:$this->snapshot($after);
        if($new!==null)$new['changed_fields']=$changed;
        $this->pdo->prepare('INSERT INTO quick_note_events(note_id,tenant_id,actor_id,action,before_state,after_state) VALUES (?,?,?,?,?,?)')->execute([$id,$tenant,$user,$action,$old===null?null:json_encode($old,JSON_THROW_ON_ERROR),$new===null?null:json_encode($new,JSON_THROW_ON_ERROR)]);
    }
    // Projetos em ordem estável antes da nota, como no Kanban. Uma mudança de destino
    // ocorrida após a leitura inicial exige recarregar em vez de bloquear outro projeto.
    private function lockedOwner(int $user,int $id,?int $target=null):array
    {
        $q=$this->pdo->prepare('SELECT * FROM quick_notes WHERE id=? AND user_id=?');$q->execute([$id,$user]);$hint=$q->fetch();if(!$hint)throw new HttpException('Nota não encontrada.',404);$ids=[];
        if($hint['project_id']!==null)$ids[]=(int)$hint['project_id'];
        if($target!==null)$ids[]=$target;
        $ids=array_unique($ids);sort($ids,SORT_NUMERIC);$projects=[];
        foreach($ids as $project)$projects[$project]=$this->project($user,$project);
        $q=$this->pdo->prepare('SELECT * FROM quick_notes WHERE id=? AND user_id=? FOR UPDATE');
        try{$q->execute([$id,$user]);}catch(\PDOException $e){
            // MariaDB pode invalidar a leitura quando outra transação moveu a linha.
            if((int)($e->errorInfo[1]??0)===1020)throw new HttpException('A nota foi alterada. Recarregue a lista.',409);
            throw $e;
        }
        $note=$q->fetch();
        if(!$note)throw new HttpException('Nota não encontrada.',404);
        if($note['project_id']!==$hint['project_id']||$note['tenant_id']!==$hint['tenant_id'])throw new HttpException('A nota foi movida. Recarregue a lista.',409);
        $tenant=$note['project_id']===null?$this->personal($user):(int)$projects[(int)$note['project_id']]['tenant_id'];
        if($tenant!==(int)$note['tenant_id'])throw new HttpException('Nota não encontrada.',404);
        return [$note,$projects];
    }
    public function create(int $user,mixed $body,?int $project,mixed $title='',mixed $color='yellow',mixed $visibility='private'):int
    {
        $body=$this->body($body);$title=$this->title($title);$color=$this->color($color);$visibility=$this->visibility($visibility,$project);
        $this->pdo->beginTransaction();
        try {
            if($project===null)$tenant=$this->personal($user);
            else{$p=$this->project($user,$project);if($p['archived_at'])throw new HttpException('Escolha um projeto ativo.',409);$tenant=(int)$p['tenant_id'];}
            $q=$this->pdo->prepare('INSERT INTO quick_notes(tenant_id,user_id,created_by,project_id,body,title,color,visibility) VALUES (?,?,?,?,?,?,?,?)');$q->execute([$tenant,$user,$user,$project,$body,$title,$color,$visibility]);$id=(int)$this->pdo->lastInsertId();
            $this->audit($user,$id,$tenant,'created',null,['tenant_id'=>$tenant,'user_id'=>$user,'created_by'=>$user,'project_id'=>$project,'color'=>$color,'visibility'=>$visibility,'revision'=>1]);
            $this->pdo->commit();return $id;
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
    public function page(int $user,?int $project,int $page=1):array
    {
        $tenant=$project===null?$this->personal($user):(int)$this->project($user,$project)['tenant_id'];
        $where=' FROM quick_notes n JOIN users u ON u.id=n.user_id WHERE n.tenant_id=? AND n.project_id <=> ? AND (n.user_id=? OR n.visibility=\'project\')';$params=[$tenant,$project,$user];
        if($project!==null){$where.=' AND EXISTS (SELECT 1 FROM project_members pm JOIN tenant_users tu ON tu.tenant_id=pm.tenant_id AND tu.user_id=pm.user_id WHERE pm.tenant_id=n.tenant_id AND pm.project_id=n.project_id AND pm.user_id=?)';$params[]=$user;}
        $q=$this->pdo->prepare('SELECT COUNT(*)'.$where);$q->execute($params);$total=(int)$q->fetchColumn();$pages=max(1,(int)ceil($total/20));$page=max(1,min($page,$pages));
        $q=$this->pdo->prepare('SELECT n.id,n.user_id,n.created_by,n.project_id,n.body,n.title,n.color,n.visibility,n.revision,n.created_at,u.name AS owner_name'.$where.' ORDER BY n.id DESC LIMIT 20 OFFSET '.(($page-1)*20));$q->execute($params);return ['items'=>$this->withInvolved($q->fetchAll()),'total'=>$total,'page'=>$page,'pages'=>$pages];
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
        $expected=array_key_exists('revision',$appearance)?$this->revision($appearance['revision']):null;
        $moving=array_key_exists('project_id',$appearance);$target=$appearance['project_id']??null;
        if($moving&&$target!==null&&(!is_int($target)||$target<1))throw new HttpException('Destino inválido.',422);
        $this->pdo->beginTransaction();
        try {
            [$n,$projects]=$this->lockedOwner($user,$id,$target);
            if($expected!==null&&(int)$n['revision']!==$expected)throw new HttpException('A nota foi alterada. Recarregue antes de editar.',409);
            $current=$n['project_id']===null?null:(int)$n['project_id'];$target=$moving?$target:$current;
            $visibility=$this->visibility(array_key_exists('visibility',$appearance)?$appearance['visibility']:$n['visibility'],$target);
            $changedLocation=$target!==$current;
            if($changedLocation&&$current!==null&&$projects[$current]['archived_at'])throw new HttpException('Não mova notas de um projeto arquivado.',409);
            if($target!==null&&($changedLocation||$visibility!==$n['visibility'])&&$projects[$target]['archived_at'])throw new HttpException('Escolha um projeto ativo.',409);
            $tenant=$target===null?$this->personal($user):(int)$projects[$target]['tenant_id'];
            $new=array_replace($n,['tenant_id'=>$tenant,'project_id'=>$target,'body'=>$body,'title'=>$title??$n['title'],'color'=>$color??$n['color'],'visibility'=>$visibility,'revision'=>(int)$n['revision']+1]);
            $this->pdo->prepare('UPDATE quick_notes SET tenant_id=?,project_id=?,body=?,title=?,color=?,visibility=?,revision=? WHERE id=? AND user_id=?')->execute([$tenant,$target,$body,$new['title'],$new['color'],$visibility,$new['revision'],$id,$user]);
            $changed=[];foreach(['body','title','color','project_id','visibility'] as $field)if($new[$field]!=$n[$field])$changed[]=$field;
            $this->audit($user,$id,$tenant,$changedLocation?'moved':'updated',$n,$new,$changed);
            $this->pdo->commit();return $target;
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
    public function delete(int $user,int $id,mixed $revision=null):void
    {
        $expected=$revision===null?null:$this->revision($revision);$this->pdo->beginTransaction();
        try {
            [$n]=$this->lockedOwner($user,$id);
            if($expected!==null&&(int)$n['revision']!==$expected)throw new HttpException('A nota foi alterada. Recarregue antes de excluir.',409);
            $this->audit($user,$id,(int)$n['tenant_id'],'deleted',$n,null);
            $this->pdo->prepare('DELETE FROM quick_notes WHERE id=? AND user_id=?')->execute([$id,$user]);$this->pdo->commit();
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
    public function recipients(int $user,int $id):array
    {
        $note=$this->owned($user,$id);
        if($note['project_id']===null)throw new HttpException('Vincule a nota a um projeto antes de transferir.',422);
        $project=$this->project($user,(int)$note['project_id']);
        if($project['archived_at'])throw new HttpException('O projeto está arquivado.',409);
        $q=$this->pdo->prepare('SELECT u.id,u.name FROM project_members m JOIN tenant_users t ON t.tenant_id=m.tenant_id AND t.user_id=m.user_id JOIN users u ON u.id=m.user_id WHERE m.tenant_id=? AND m.project_id=? AND m.user_id<>? AND u.email_verified_at IS NOT NULL ORDER BY u.name,u.id');$q->execute([$note['tenant_id'],$note['project_id'],$user]);return $q->fetchAll();
    }
    public function transfer(int $user,int $id,int $recipient,bool $confirmed,mixed $revision):int
    {
        if(!$confirmed)throw new HttpException('Confirme a transferência da propriedade.',422);
        if($recipient<1||$recipient===$user)throw new HttpException('Escolha outro membro do projeto.',422);
        $expected=$this->revision($revision);
        for($attempt=0;$attempt<3;$attempt++){
            $this->pdo->beginTransaction();
            try{
                [$note,$projects]=$this->lockedOwner($user,$id);
                if($note['project_id']===null)throw new HttpException('Vincule a nota a um projeto antes de transferir.',422);
                $project=$projects[(int)$note['project_id']];
                if($project['archived_at'])throw new HttpException('O projeto está arquivado.',409);
                if((int)$note['revision']!==$expected)throw new HttpException('A nota foi alterada. Recarregue antes de transferir.',409);
                $q=$this->pdo->prepare('SELECT m.user_id FROM project_members m JOIN tenant_users t ON t.tenant_id=m.tenant_id AND t.user_id=m.user_id JOIN users u ON u.id=m.user_id WHERE m.tenant_id=? AND m.project_id=? AND m.user_id=? AND u.email_verified_at IS NOT NULL FOR UPDATE');$q->execute([$note['tenant_id'],$note['project_id'],$recipient]);
                if(!$q->fetchColumn())throw new HttpException('Destinatário indisponível neste projeto.',422);
                $this->pdo->prepare('UPDATE quick_notes SET user_id=?,revision=revision+1 WHERE id=? AND user_id=?')->execute([$recipient,$id,$user]);
                $after=array_replace($note,['user_id'=>$recipient,'revision'=>$expected+1]);
                $this->audit($user,$id,(int)$note['tenant_id'],'transferred',$note,$after,['user_id']);
                $this->pdo->commit();return (int)$note['project_id'];
            }catch(Throwable $e){
                if($this->pdo->inTransaction())$this->pdo->rollBack();
                // Operação apenas no banco: repetir uma transação vítima de deadlock é seguro.
                if($e instanceof \PDOException&&(int)($e->errorInfo[1]??0)===1213&&$attempt<2)continue;
                throw $e;
            }
        }
        throw new HttpException('Recarregue a nota e tente transferir novamente.',409);
    }
    private function withInvolved(array $items):array
    {
        if(!$items)return [];
        $ids=array_column($items,'id');$marks=implode(',',array_fill(0,count($ids),'?'));
        $sql="SELECT n.id AS note_id,u.id,u.name,(u.id=n.user_id) AS is_owner,(u.id=n.created_by) AS is_creator FROM (SELECT id AS note_id,user_id AS person_id FROM quick_notes WHERE id IN ($marks) UNION SELECT id,created_by FROM quick_notes WHERE id IN ($marks) UNION SELECT note_id,CAST(JSON_UNQUOTE(JSON_EXTRACT(after_state,'$.user_id')) AS UNSIGNED) FROM quick_note_events WHERE note_id IN ($marks) AND action='transferred') involved JOIN quick_notes n ON n.id=involved.note_id JOIN users u ON u.id=involved.person_id WHERE 1=1 AND u.email_verified_at IS NOT NULL AND (u.id=n.user_id OR n.visibility='project') AND ((n.project_id IS NULL AND u.id=n.user_id) OR EXISTS (SELECT 1 FROM project_members m JOIN tenant_users t ON t.tenant_id=m.tenant_id AND t.user_id=m.user_id WHERE m.tenant_id=n.tenant_id AND m.project_id=n.project_id AND m.user_id=u.id)) ORDER BY n.id,is_owner DESC,u.id";
        $q=$this->pdo->prepare($sql);$q->execute(array_merge($ids,$ids,$ids));$people=[];
        foreach($q->fetchAll() as $person)$people[(int)$person['note_id']][]=$person;
        foreach($items as &$note)$note['involved']=$people[(int)$note['id']]??[];
        return $items;
    }
    public function convert(int $user,int $id,int $project,int $column,mixed $title,mixed $revision):int

    {
        $title=$this->title($title);if($title==='')throw new HttpException('Informe o título da tarefa.',422);$expected=$this->revision($revision);
        $this->pdo->beginTransaction();
        try {
            [$n,$projects]=$this->lockedOwner($user,$id,$project);
            if($n['project_id']===null||(int)$n['project_id']!==$project)throw new HttpException('Converta somente no projeto ao qual a nota pertence.',422);
            $p=$projects[$project];
            if($p['archived_at'])throw new HttpException('O projeto está arquivado.',409);
            if(!in_array($p['role'],['owner','admin','member'],true))throw new HttpException('Seu papel não permite criar tarefas.',403);
            if((int)$n['revision']!==$expected)throw new HttpException('A nota foi alterada. Recarregue antes de converter.',409);
            $q=$this->pdo->prepare('SELECT id,is_done FROM kanban_columns WHERE tenant_id=? AND project_id=? AND id=?');$q->execute([$p['tenant_id'],$project,$column]);$c=$q->fetch();if(!$c)throw new HttpException('Coluna indisponível neste projeto.',422);
            $q=$this->pdo->prepare('SELECT COUNT(*) FROM tasks WHERE tenant_id=? AND project_id=? AND column_id=?');$q->execute([$p['tenant_id'],$project,$column]);$position=(int)$q->fetchColumn();
            $done=$c['is_done']?$this->pdo->query('SELECT UTC_TIMESTAMP()')->fetchColumn():null;
            $this->pdo->prepare('INSERT INTO tasks(tenant_id,project_id,column_id,created_by,title,description,position,completed_at) VALUES (?,?,?,?,?,?,?,?)')->execute([$p['tenant_id'],$project,$column,$user,$title,$n['body'],$position,$done]);$task=(int)$this->pdo->lastInsertId();
            $this->pdo->prepare('INSERT INTO task_assignees(tenant_id,project_id,task_id,user_id) VALUES (?,?,?,?)')->execute([$p['tenant_id'],$project,$task,$user]);
            $logger=new ActivityLogger($this->pdo);$logger->record($p,$user,'task.created',null,['title'=>$title,'column_id'=>$column,'position'=>$position,'completed_at'=>$done],$task);
            if($done!==null)$logger->record($p,$user,'task.completed',null,['completed_at'=>$done],$task);
            $logger->record($p,$user,'task.assignees_changed',['user_ids'=>[]],['user_ids'=>[$user]],$task);
            $this->pdo->prepare('UPDATE projects SET board_version=board_version+1 WHERE tenant_id=? AND id=?')->execute([$p['tenant_id'],$project]);
            $this->audit($user,$id,(int)$n['tenant_id'],'converted',$n,array_replace($n,['revision'=>(int)$n['revision']+1,'task_id'=>$task]),['converted_to_task']);
            $this->pdo->prepare('DELETE FROM quick_notes WHERE id=? AND tenant_id=? AND user_id=?')->execute([$id,$n['tenant_id'],$user]);$this->pdo->commit();return $task;
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
}
