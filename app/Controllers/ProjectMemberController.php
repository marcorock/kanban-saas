<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Policies\ProjectPolicy;
use App\Security\Session;
use App\Services\Mailer;
use App\Services\ProjectMemberService;
use App\Services\RateLimiter;
use PDO;
use Pecee\SimpleRouter\Exceptions\HttpException;
use Twig\Environment;

final class ProjectMemberController
{
    public function __construct(private Environment $views, private array $config, private PDO $pdo) {}

    public function dispatch(string $action, array $project, array $user, array $base): string
    {
        $userId = (int) $user['id'];
        $projectId = (int) $project['id'];
        $service = new ProjectMemberService($this->pdo);
        $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
        $oldInvite = [
            'email' => $action === 'invite' && is_string($_POST['email'] ?? null) ? mb_substr($_POST['email'], 0, 254) : '',
            'role' => $action === 'invite' && is_string($_POST['role'] ?? null) ? $_POST['role'] : 'member',
        ];
        $render = fn (array $errors = []): string => $this->views->render('projects/members.html.twig', $base + $service->overview($userId, $projectId, $page) + [
            'isOwner' => ProjectPolicy::isOwner($project, $userId), 'errors' => $errors, 'oldInvite' => $oldInvite,
        ]);
        if ($action === 'members') { return $render(); }
        ProjectPolicy::authorizeMembers($project, $userId);
        if (!Session::verifyCsrf($_POST['_token'] ?? null)) {
            http_response_code(419);
            return $render(['Sua sessão do formulário expirou. Confira os dados e tente novamente.']);
        }
        try {
            if ($action === 'invite') {
                $email = is_string($_POST['email'] ?? null) ? strtolower(trim($_POST['email'])) : '';
                $role = is_string($_POST['role'] ?? null) ? $_POST['role'] : '';
                $limiter = new RateLimiter($this->pdo);
                $allowed = $limiter->allow('invite-user', (string) $userId, 30);
                $ipAllowed = $limiter->allow('invite-ip', $_SERVER['REMOTE_ADDR'] ?? 'unknown', 30);
                $emailAllowed = $limiter->allow('invite-email', $projectId . ':' . $email, 3);
                if (!$allowed || !$ipAllowed || !$emailAllowed) {
                    header('Retry-After: 900');
                    throw new HttpException('Muitas solicitações. Aguarde 15 minutos.', 429);
                }
                if (!$service->invite($userId, $projectId, $email, $role, new Mailer($this->config['mail']), $this->config['url'])) {
                    http_response_code(502);
                    return $render(['Convite salvo, mas o e-mail não foi enviado. Tente reenviar o convite mais tarde.']);
                }
                $notice = 'Convite enviado. O acesso será liberado somente após o aceite.';
            } else {
                $field = $action === 'cancel-invitation' ? 'invitation_id' : 'user_id';
                $target = is_string($_POST[$field] ?? null) && ctype_digit($_POST[$field]) && strlen($_POST[$field]) <= 18 ? (int) $_POST[$field] : 0;
                if ($target < 1) { throw new HttpException('Identificador inválido.', 422); }
                if ($action === 'cancel-invitation') {
                    $service->cancel($userId, $projectId, $target);
                    $notice = 'Convite cancelado.';
                } else {
                    $memberAction = match ($action) { 'member-role' => 'role', 'member-remove' => 'remove', 'transfer' => 'transfer' };
                    if (in_array($memberAction, ['remove', 'transfer'], true) && ($_POST['confirm'] ?? '') !== $memberAction) {
                        throw new HttpException('Confirme explicitamente a operação.', 422);
                    }
                    $role = is_string($_POST['role'] ?? null) ? $_POST['role'] : '';
                    $service->member($userId, $projectId, $target, $memberAction, $role);
                    $notice = match ($memberAction) { 'role' => 'Papel atualizado.', 'remove' => 'Participante removido.', 'transfer' => 'Propriedade transferida. Você permanece como administrador.' };
                }
            }
        } catch (HttpException $exception) {
            http_response_code((int) $exception->getCode());
            return $render([$exception->getMessage()]);
        }
        $_SESSION['project_notice'] = $notice;
        header('Location: /projects/' . $projectId . '/members', true, 303);
        return '';
    }
}
