<?php

declare(strict_types=1);
namespace App\Controllers;
use App\Infrastructure\Database;
use App\Security\Session;
use App\Services\{AuthService,PushSubscriptionService,WebPushGateway,NotificationService};

final class PushController
{
    public function __construct(private array $config) {}
    public function handle(string $mode): string
    {
        header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: no-store');
        $pdo=Database::connect($this->config['database']);$user=(new AuthService($pdo))->currentUser();
        if (!$user||!$user['email_verified_at']) return $this->reply(['message'=>'Entre com uma conta verificada.'],$user?403:401);
        if ($mode==='context') return $this->reply(['user_id'=>(int)$user['id'],'enabled'=>(new NotificationService($pdo))->preferences((int)$user['id'])['push_enabled']]);
        if ($mode==='config') return $this->reply(['public_key'=>WebPushGateway::configured($this->config['push'])?$this->config['push']['public_key']:null]);
        $data=$_POST;
        if (strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE']??'')[0]))==='application/json') $data=json_decode(file_get_contents('php://input'),true,32,JSON_THROW_ON_ERROR);
        if (!Session::verifyCsrf($data['_token']??null)) return $this->reply(['message'=>'Sessão do formulário expirada.'],419);
        try {
            $service=new PushSubscriptionService($pdo);
            if ($mode==='save') {
                if (!WebPushGateway::configured($this->config['push'])) return $this->reply(['message'=>'Push ainda não configurado neste servidor.'],503);
                $service->save((int)$user['id'],(int)$user['session_version'],$data);
            } else $service->remove((int)$user['id'],$data['endpoint']??null);
            return $this->reply(['ok'=>true]);
        } catch(\DomainException $error) {return $this->reply(['message'=>$error->getMessage()],(int)$error->getCode());}
    }
    private function reply(array $data,int $status=200): string {http_response_code($status);return json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);}
}
