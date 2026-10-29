<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Infrastructure\Database;
use App\Security\Session;
use App\Services\AuthService;
use App\Services\ProjectMemberService;
use App\Services\RateLimiter;
use Pecee\SimpleRouter\Exceptions\HttpException;
use Twig\Environment;

final class InvitationController
{
    public function __construct(private Environment $views, private array $config) {}

    public function handle(bool $post): string
    {
        header('Referrer-Policy: no-referrer');
        if (!$post && isset($_GET['token'])) {
            if (is_string($_GET['token']) && preg_match('/^[a-f0-9]{64}$/D', $_GET['token'])) {
                $_SESSION['pending_invitation'] = $_GET['token'];
            } else {
                unset($_SESSION['pending_invitation']);
            }
            header('Location: /invitations/accept', true, 303);
            return '';
        }
        $base = ['projectName' => $this->config['name'], 'csrf' => $_SESSION['csrf']];
        $pdo = null;
        $user = false;
        if (isset($_SESSION['user_id'])) {
            $pdo = Database::connect($this->config['database']);
            $user = (new AuthService($pdo))->currentUser();
        }
        if ($post && !Session::verifyCsrf($_POST['_token'] ?? null)) {
            http_response_code(419);
            return 'Sessão do formulário expirada. Recarregue a página.';
        }
        if (!$user) {
            if ($post) { http_response_code(401); }
            return $this->views->render('invitation.html.twig', $base + ['signedIn' => false, 'invitation' => false, 'errors' => []]);
        }
        if ($user['email_verified_at'] === null) {
            http_response_code(403);
            return $this->views->render('verify-notice.html.twig', $base);
        }
        $service = new ProjectMemberService($pdo);
        $raw = $_SESSION['pending_invitation'] ?? '';
        $errors = [];
        if ($post) {
            $limiter = new RateLimiter($pdo);
            $userAllowed = $limiter->allow('invite-accept-user', (string) $user['id'], 20);
            $ipAllowed = $limiter->allow('invite-accept-ip', $_SERVER['REMOTE_ADDR'] ?? 'unknown', 30);
            if (!$userAllowed || !$ipAllowed) {
                http_response_code(429);
                header('Retry-After: 900');
                $errors[] = 'Muitas tentativas. Aguarde 15 minutos.';
            } else {
                try {
                    $id = $service->accept((int) $user['id'], $raw);
                    unset($_SESSION['pending_invitation']);
                    $_SESSION['project_notice'] = 'Convite aceito. Você já participa do projeto.';
                    header('Location: /projects/' . $id, true, 303);
                    return '';
                } catch (HttpException $exception) {
                    http_response_code((int) $exception->getCode());
                    $errors[] = 'Não foi possível aceitar. Verifique a conta utilizada, a validade do convite ou solicite um novo link.';
                }
            }
        }
        $invitation = $service->preview((int) $user['id'], $raw);
        if (!$invitation && !$errors) { $errors[] = 'Convite indisponível para esta conta. Entre com o e-mail convidado ou solicite um novo link.'; }
        return $this->views->render('invitation.html.twig', $base + ['user' => $user, 'signedIn' => true, 'invitation' => $invitation, 'errors' => $errors]);
    }
}
