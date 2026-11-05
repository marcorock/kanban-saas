<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Infrastructure\Database;
use App\Repositories\WorkspaceRepository;
use App\Security\Session;
use App\Services\{AuthService, WorkspaceContext, WorkspaceService};
use Pecee\SimpleRouter\Exceptions\HttpException;
use Twig\Environment;

final class WorkspaceController
{
    public function __construct(private Environment $views, private array $config) {}

    public function handle(string $action): string
    {
        $pdo = Database::connect($this->config['database']);
        $user = (new AuthService($pdo))->currentUser();
        if (!$user) { header('Location: /login', true, 303); return ''; }
        $repository = new WorkspaceRepository($pdo);
        $this->views->addGlobal('workspaceChoices', $repository->forUser((int) $user['id']));
        $base = ['projectName' => $this->config['name'], 'user' => $user, 'csrf' => $_SESSION['csrf']];
        if (!$user['email_verified_at']) { http_response_code(403); return $this->views->render('verify-notice.html.twig', $base); }
        $errors = []; $name = is_string($_POST['workspace_name'] ?? null) ? $_POST['workspace_name'] : '';
        if ($action !== 'new') {
            try {
                if (!Session::verifyCsrf($_POST['_token'] ?? null)) throw new HttpException('Sessão do formulário expirada. Tente novamente.', 419);
                $service = new WorkspaceService($pdo);
                if ($action === 'default') {
                    $context = WorkspaceContext::resolve($pdo, (int) $user['id'], $_POST['workspace_id'] ?? null);
                    $service->setDefault((int) $user['id'], (int) $user['session_version'], $context->tenantId);
                    $_SESSION['profile_success'] = 'Área de Trabalho padrão atualizada.';
                    header('Location: /settings#workspace-settings', true, 303);
                } else {
                    $id = $service->create((int) $user['id'], (int) $user['session_version'], $name);
                    header('Location: /workspaces/' . $id . '/dashboard', true, 303);
                }
                return '';
            } catch (\DomainException $error) {
                if ($error->getCode() === 401) { Session::clear(); header('Location: /login', true, 303); return ''; }
                http_response_code($error->getCode() === 403 ? 403 : 422); $errors[] = $error->getMessage();
            } catch (HttpException $error) {
                if ($action === 'default') throw $error;
                http_response_code((int) $error->getCode()); $errors[] = $error->getMessage();
            }
        }
        return $this->views->render('workspaces/new.html.twig', $base + ['errors' => $errors, 'name' => $name]);
    }
}
