<?php

declare(strict_types=1);
require __DIR__.'/board-http.php';
use App\Services\{AuthService,ProjectService,ProjectMemberService,NotificationService,NotificationWorker,Mailer};
use Pecee\SimpleRouter\Exceptions\HttpException;
$auth=new AuthService($pdo);$members=new ProjectMemberService($pdo);$notices=new NotificationService($pdo);$worker=new NotificationWorker($pdo);$mailer=new Mailer($config['mail']);
$password='Senha-convite-interno-123';$suffix=bin2hex(random_bytes(6));$users=[];
foreach (['owner','guest','other'] as $name) {
    $email=$name.'-'.$suffix.'@example.com';$id=$auth->register($name,$email,$password);
    $pdo->prepare('UPDATE users SET email_verified_at=UTC_TIMESTAMP() WHERE id=?')->execute([$id]);$users[$name]=compact('id','email');
}
$owner=$users['owner']['id'];$guest=$users['guest']['id'];$other=$users['other']['id'];
$project=(new ProjectService($pdo))->create($owner,['name'=>'Convite interno','description'=>'DESCRICAO-PRIVADA','starts_on'=>'','due_on'=>'']);
$members->invite($owner,$project,$users['guest']['email'],'member',$mailer,$config['url']);
$q=$pdo->prepare('SELECT id FROM project_invitations WHERE project_id=? AND email=?');$q->execute([$project,$users['guest']['email']]);$invitation=(int)$q->fetchColumn();
check($notices->unread($guest)===0,'Evento não entrega na requisição');
$now=new DateTimeImmutable('now',new DateTimeZone('UTC'));
$worker->run(new DateTimeImmutable('now',new DateTimeZone('UTC')),500);check($notices->unread($guest)===1,'Convite entregue sem exigir participação prévia no tenant');
check($notices->unread($owner)===0&&$notices->unread($other)===0,'Somente destinatário recebe convite');
$csrf=boardLogin($users['guest']['email']);$html=request('/notifications')[1];
check(str_contains($html,'Convite para projeto')&&str_contains($html,'/invitations/accept?id='.$invitation),'Convite com link interno');
$content=json_encode($notices->page($guest,1)['items'][0]['links']);check(!str_contains($content,$users['guest']['email'])&&!str_contains($content,'DESCRICAO-PRIVADA'),'Sem e-mail nem descrição');
check(request('/projects/'.$project)[0]===404,'Convite não concede acesso antes do aceite');
boardLogin($users['other']['email']);request('/invitations/accept?id='.$invitation);
check(!str_contains(request('/invitations/accept')[1],'Convite interno'),'IDOR não revela projeto');
try{$members->accept($other,$invitation);check(false,'Aceite de outra conta recusado');}catch(HttpException $e){check($e->getCode()===403,'Conta convidada exigida');}
$csrf=boardLogin($users['guest']['email']);check(request('/invitations/accept?id='.$invitation)[0]===303,'ID armazenado na sessão');
check(str_contains(request('/invitations/accept')[1],'Convite interno'),'Preview para convidado');
check(request('/invitations/accept',[])[0]===419,'CSRF no aceite interno');
check(request('/invitations/accept',['_token'=>$csrf])[0]===303,'Aceite interno sem token exposto');
check(request('/projects/'.$project)[0]===200&&$notices->unread($guest)===0,'Aceite concede acesso e retira convite pendente');
$members->invite($owner,$project,$users['other']['email'],'viewer',$mailer,$config['url']);$worker->run(new DateTimeImmutable('now',new DateTimeZone('UTC')),500);check($notices->unread($other)===1,'Segundo convite');
$q->execute([$project,$users['other']['email']]);$second=(int)$q->fetchColumn();$members->cancel($owner,$project,$second);check($notices->unread($other)===0,'Cancelamento revoga visibilidade');
try{$members->accept($other,$second);check(false,'Cancelado recusado');}catch(HttpException $e){check($e->getCode()===422,'Cancelado indisponível');}
$members->invite($owner,$project,$users['other']['email'],'viewer',$mailer,$config['url']);$worker->run(new DateTimeImmutable('now',new DateTimeZone('UTC')),500);check($notices->unread($other)===1,'Reemissão não duplica convite anterior cancelado');
$pdo->prepare('UPDATE project_invitations SET expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=?')->execute([$second]);check($notices->unread($other)===0,'Expiração oculta convite');
$notices->savePreferences($other,1,['bell_enabled'=>false,'push_enabled'=>false,'email_enabled'=>false]);
$members->invite($owner,$project,$users['other']['email'],'viewer',$mailer,$config['url']);$worker->run(new DateTimeImmutable('now',new DateTimeZone('UTC')),500);check($notices->unread($other)===0,'Canal desligado respeitado');
echo "OK: convite interno, conta existente sem tenant, IDOR, privacidade, aceite, CSRF, cancelamento, expiração, reemissão e preferências.\n";
