<?php

declare(strict_types=1);
namespace App\Controllers;
use App\Infrastructure\Database;
use App\Security\Session;
use App\Services\{AuthService,QuickNoteService};
use Pecee\SimpleRouter\Exceptions\HttpException;
use Twig\Environment;

final class QuickNoteController
{
    public function __construct(private Environment $views,private array $config) {}
    private function id(mixed $id,bool $optional=false):?int
    {
        if($optional&&($id===null||$id===''))return null;
        if(!is_string($id)||!ctype_digit($id)||strlen($id)>18||(int)$id<1)throw new HttpException('Identificador inválido.',422);return (int)$id;
    }
    public function handle(string $mode,?string $id=null):string
    {
        $json=str_contains($_SERVER['HTTP_ACCEPT']??'','application/json');
        $pdo=Database::connect($this->config['database']);$user=(new AuthService($pdo))->currentUser();
        if(!$user){if($json)return $this->json(['message'=>'Entre novamente.'],401);header('Location: /login',true,303);return '';}
        if(!$user['email_verified_at']){if($json)return $this->json(['message'=>'Confirme seu e-mail.'],403);throw new HttpException('Confirme seu e-mail.',403);}
        $service=new QuickNoteService($pdo);$uid=(int)$user['id'];
        try {
            if($mode==='projects')return $this->json(['projects'=>$service->projects($uid,($_GET['writable']??'')==='1')]);
            if($mode==='columns')return $this->json(['columns'=>$service->columns($uid,$this->id($_GET['project_id']??null))]);
            if($mode==='new')return $this->views->render('notes/new.html.twig',['projectName'=>$this->config['name'],'user'=>$user,'csrf'=>$_SESSION['csrf'],'destinations'=>$service->projects($uid)]);
            if(!Session::verifyCsrf($_POST['_token']??null))throw new HttpException('Sessão do formulário expirada. Recarregue a página.',419);
            $project=$this->id($_POST['project_id']??null,true);
            if($mode==='create'){$service->create($uid,$_POST['body']??null,$project,$_POST['title']??'',$_POST['color']??'yellow');$message='Nota salva.';}
            elseif($mode==='update'){$project=$service->update($uid,$this->id($id),$_POST['body']??null,array_intersect_key($_POST,array_flip(['title','color'])));$message='Nota atualizada.';}
            elseif($mode==='delete'){$service->delete($uid,$this->id($id));$message='Nota excluída.';}
            else{$task=$service->convert($uid,$this->id($id),$this->id($_POST['project_id']??null),$this->id($_POST['column_id']??null));$message='Nota convertida em tarefa.';}
            if($json)return $this->json(['ok'=>true,'message'=>$message,'task_url'=>isset($task)?'/projects/'.$project.'/board?task='.$task:null]);
            header('Location: '.($project?'/projects/'.$project:'/tasks').'#brainstorm',true,303);return '';
        }catch(HttpException $e){if($json)return $this->json(['message'=>$e->getMessage()],(int)$e->getCode());throw $e;}
    }
    private function json(array $data,int $status=200):string{http_response_code($status);header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: no-store');return json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);}
}
