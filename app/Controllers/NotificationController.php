<?php

declare(strict_types=1);
namespace App\Controllers;
use App\Infrastructure\Database;
use App\Security\Session;
use App\Services\{AuthService,NotificationService};
use Twig\Environment;

final class NotificationController
{
    public function __construct(private Environment $views,private array $config) {}

    public function handle(string $mode,?string $id=null): string
    {
        $pdo=Database::connect($this->config['database']);$user=(new AuthService($pdo))->currentUser();
        if (!$user) {if (($_GET['format']??null)==='count') {http_response_code(401);header('Content-Type: application/json');return '{"error":"unauthenticated"}';}header('Location: /login',true,303);return '';}
        $base=['projectName'=>$this->config['name'],'user'=>$user,'csrf'=>$_SESSION['csrf'],'wide'=>false];
        if (!$user['email_verified_at']) {http_response_code(403);return $this->views->render('verify-notice.html.twig',$base);}
        $service=new NotificationService($pdo);
        if ($mode==='index' && ($_GET['format']??null)==='count') {header('Content-Type: application/json');return json_encode(['unread'=>$service->unread((int)$user['id'])],JSON_THROW_ON_ERROR);}
        $back=$mode==='preferences'?'/settings#notification-settings':'/notifications';
        if ($mode!=='index') {
            if (!Session::verifyCsrf($_POST['_token']??null)) return $this->error($base,'Sessão do formulário expirada. Tente novamente.',419,$back);
            if ($mode==='preferences') {
                $flags=[];foreach (['bell_enabled','push_enabled','email_enabled'] as $key) {
                    if (isset($_POST[$key]) && $_POST[$key]!=='1') return $this->error($base,'Revise as preferências selecionadas.',422,$back);
                    $flags[$key]=isset($_POST[$key]);
                }
                try {$service->savePreferences((int)$user['id'],(int)$user['session_version'],$flags);}
                catch (\DomainException $error) {Session::clear();header('Location: /login',true,303);return '';}
                $_SESSION['profile_success']='Preferências de notificações atualizadas.';
            } else {
                if (!is_string($id)||!ctype_digit($id)||strlen($id)>18||(int)$id<1||!$service->markRead((int)$user['id'],(int)$id)) return $this->error($base,'Notificação indisponível.',404,$back);
            }
            header('Location: '.$back,true,303);return '';
        }
        $page=$_GET['page']??'1';if (!is_string($page)||!ctype_digit($page)||strlen($page)>7||(int)$page<1) return $this->error($base,'Página inválida.',422,$back);
        return $this->views->render('notifications/index.html.twig',$base+['result'=>$service->page((int)$user['id'],min(1000000,(int)$page))]);
    }

    private function error(array $base,string $message,int $status,string $back): string
    {
        http_response_code($status);return $this->views->render('notifications/error.html.twig',$base+compact('message','back'));
    }
}
