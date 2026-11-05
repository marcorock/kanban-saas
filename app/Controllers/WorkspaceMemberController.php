<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Infrastructure\Database;
use App\Repositories\WorkspaceRepository;
use App\Security\Session;
use App\Services\{AuthService, Mailer, RateLimiter, WorkspaceContext, WorkspaceMemberService};
use Pecee\SimpleRouter\Exceptions\HttpException;
use Twig\Environment;

final class WorkspaceMemberController
{
    public function __construct(private Environment $views, private array $config) {}

    private function redirect(string $path): string { header('Location: '.$path,true,303); return ''; }

    private function id(mixed $value): int
    {
        if (!is_string($value) || !ctype_digit($value) || strlen($value)>18 || (int)$value<1) throw new HttpException('Identificador inválido.',404);
        return (int)$value;
    }

    public function handle(string $action, ?string $workspaceId = null): string
    {
        header('Referrer-Policy: no-referrer');
        $post=$_SERVER['REQUEST_METHOD']==='POST';
        if ($action==='accept' && !$post && (isset($_GET['token']) || isset($_GET['id']))) {
            unset($_SESSION['pending_workspace_invitation'], $_SESSION['pending_invitation']);
            if (is_string($_GET['token'] ?? null) && preg_match('/^[a-f0-9]{64}$/D',$_GET['token'])) $_SESSION['pending_workspace_invitation']=$_GET['token'];
            elseif (isset($_GET['id'])) $_SESSION['pending_workspace_invitation']=$this->id($_GET['id']);
            return $this->redirect('/workspace-invitations/accept');
        }
        if ($post && !Session::verifyCsrf($_POST['_token'] ?? null)) throw new HttpException('Sessão do formulário expirada.',419);
        $pdo=Database::connect($this->config['database']);
        $user=(new AuthService($pdo))->currentUser();
        $base=['projectName'=>$this->config['name'],'csrf'=>$_SESSION['csrf'],'errors'=>[],'signedIn'=>(bool)$user,'workspaceInvitation'=>true];
        if (!$user) {
            if ($action!=='accept') return $this->redirect('/login');
            return $this->views->render('invitation.html.twig',$base+['invitation'=>false]);
        }
        $base['user']=$user;
        if (!$user['email_verified_at']) { http_response_code(403); return $this->views->render('verify-notice.html.twig',$base); }
        $this->views->addGlobal('workspaceChoices',(new WorkspaceRepository($pdo))->forUser((int)$user['id']));
        $service=new WorkspaceMemberService($pdo); $uid=(int)$user['id'];
        if ($action==='invitations') return $this->views->render('workspaces/invitations.html.twig',$base+['invitations'=>$service->forEmail($user['email'])]);
        $tenant=null;
        if ($workspaceId!==null) {
            $tenant=WorkspaceContext::resolve($pdo,$uid,$workspaceId)->tenantId;
            $this->views->addGlobal('workspacePath','/workspaces/'.$tenant);
        }
        try {
            if ($post) {
                $limiter=new RateLimiter($pdo);
                $allowed=$limiter->allow('workspace-'.$action.'-user',(string)$uid,20);
                $ipAllowed=$limiter->allow('workspace-'.$action.'-ip',$_SERVER['REMOTE_ADDR'] ?? 'unknown',30);
                if (!$allowed || !$ipAllowed) { header('Retry-After: 900'); throw new HttpException('Muitas tentativas. Aguarde 15 minutos.',429); }
                $value=static fn(string $key): string => is_string($_POST[$key] ?? null) ? $_POST[$key] : '';
                if ($action==='accept') {
                    $id=$service->accept($uid,$_SESSION['pending_workspace_invitation'] ?? '');
                    unset($_SESSION['pending_workspace_invitation']);
                    return $this->redirect('/workspaces/'.$id.'/dashboard');
                }
                if ($action==='invite') {
                    $sent=$service->invite($uid,$tenant,$value('email'),$value('role'),new Mailer($this->config['mail']),$this->config['url']);
                    $_SESSION['workspace_notice']=$sent ? 'Convite enviado.' : 'Convite criado, mas o envio falhou. Reenvie o convite para gerar outro link.';
                } elseif ($action==='cancel') $service->cancel($uid,$tenant,$this->id($_POST['invitation_id'] ?? null));
                else $service->manage($uid,$tenant,$action,$action==='rename' ? 0 : $this->id($_POST['user_id'] ?? null),$value($action==='rename' ? 'name' : 'role'));
                return $this->redirect('/workspaces/'.$tenant.'/members');
            }
        } catch (HttpException|\DomainException $error) {
            http_response_code(in_array($error->getCode(),[403,404,409,422,429],true) ? $error->getCode() : 422);
            $base['errors'][]=$error->getMessage();
        }
        if ($action==='accept') {
            $invitation=$service->pending($_SESSION['pending_workspace_invitation'] ?? '');
            $preview=false;
            if ($invitation && strcasecmp($invitation['email'],$user['email'])===0) {
                $workspace=(new WorkspaceRepository($pdo))->find((int)$invitation['invited_by'],(int)$invitation['tenant_id']);
                if ($workspace && \App\Policies\WorkspacePolicy::can($workspace,(int)$invitation['invited_by'],'invite',null,$invitation['role'])) $preview=['name'=>$workspace['name'],'role'=>$invitation['role']];
            }
            if (!$preview && !$base['errors']) $base['errors'][]='Convite indisponível para esta conta. Confira o e-mail e a validade ou solicite um novo convite.';
            return $this->views->render('invitation.html.twig',$base+['invitation'=>$preview]);
        }
        $notice=$_SESSION['workspace_notice'] ?? ''; unset($_SESSION['workspace_notice']);
        $page=is_string($_GET['page'] ?? null) && ctype_digit($_GET['page']) ? min(1000000,(int)$_GET['page']) : 1;
        return $this->views->render('workspaces/members.html.twig',$base+$service->overview($uid,$tenant,$page)+['notice'=>$notice]);
    }
}
