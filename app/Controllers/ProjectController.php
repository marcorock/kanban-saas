<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Infrastructure\Database;
use App\Policies\ProjectPolicy;
use App\Repositories\ProjectRepository;
use App\Security\Session;
use App\Services\AuthService;
use App\Services\ProjectService;
use App\Validators\ProjectValidator;
use Pecee\SimpleRouter\Exceptions\HttpException;
use Twig\Environment;

final class ProjectController
{
    public function __construct(private Environment $views, private array $config) {}

    private function redirect(string $path): string
    {
        header('Location: ' . $path, true, 303);
        return '';
    }

    public function dispatch(string $action, ?string $id = null): string
    {
        if (!isset($_SESSION['user_id'])) { return $this->redirect('/login'); }
        $pdo = Database::connect($this->config['database']);
        $user = (new AuthService($pdo))->currentUser();
        if (!$user) { return $this->redirect('/login'); }
        $notice = $_SESSION['project_notice'] ?? '';
        unset($_SESSION['project_notice']);
        $base = ['wide' => true, 'notice' => $notice, 'projectName' => $this->config['name'], 'user' => $user, 'csrf' => $_SESSION['csrf']];
        if ($user['email_verified_at'] === null) {
            http_response_code(403);
            return $this->views->render('verify-notice.html.twig', $base);
        }
        if ($id !== null && (!ctype_digit($id) || (int) $id < 1 || strlen($id) > 18)) {
            throw new HttpException('Projeto não encontrado.', 404);
        }
        $repository = new ProjectRepository($pdo);
        $service = new ProjectService($pdo);
        $userId = (int) $user['id'];
        if ($action === 'index') {
            $archived = ($_GET['status'] ?? '') === 'archived';
            $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
            return $this->views->render('projects/index.html.twig', $base + ['result' => $repository->page($userId, $archived, $page), 'archived' => $archived]);
        }
        $project = $id === null ? null : $repository->find($userId, (int) $id);
        if (in_array($action, ['edit', 'update'], true)) { ProjectPolicy::authorize($project, $userId, 'update'); }
        if ($action === 'show') {
            return $this->views->render('projects/show.html.twig', $base + ['project' => $project, 'canManage' => ProjectPolicy::canManage($project, $userId), 'isOwner' => ProjectPolicy::isOwner($project, $userId)]);
        }
        $blank = ['name' => '', 'description' => '', 'starts_on' => '', 'due_on' => ''];
        if (in_array($action, ['new', 'edit'], true)) {
            return $this->views->render('projects/form.html.twig', $base + ['project' => $project, 'old' => $project ?? $blank, 'errors' => []]);
        }
        if (!Session::verifyCsrf($_POST['_token'] ?? null)) {
            http_response_code(419);
            return 'Sessão do formulário expirada. Recarregue a página.';
        }
        if (in_array($action, ['create', 'update'], true)) {
            $data = [];
            $formatError = false;
            foreach (array_keys($blank) as $key) {
                if (isset($_POST[$key]) && (!is_string($_POST[$key]) || str_contains($_POST[$key], "\0"))) { $formatError = true; }
                $data[$key] = is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
            }
            $errors = $formatError ? ['Formato de formulário inválido.'] : ProjectValidator::errors($data);
            if ($errors) {
                http_response_code(422);
                return $this->views->render('projects/form.html.twig', $base + ['project' => $project, 'old' => $data, 'errors' => $errors]);
            }
            if ($action === 'create') {
                $id = (string) $service->create($userId, $data);
            } else {
                $service->change($userId, (int) $id, 'update', $data);
            }
            $_SESSION['project_notice'] = $action === 'create' ? 'Projeto criado.' : 'Projeto atualizado.';
            return $this->redirect('/projects/' . $id);
        }
        if ($action === 'delete' && ($_POST['confirm'] ?? '') !== 'delete') {
            throw new HttpException('Confirmação de exclusão obrigatória.', 422);
        }
        $service->change($userId, (int) $id, $action);
        $_SESSION['project_notice'] = match ($action) { 'archive' => 'Projeto arquivado.', 'restore' => 'Projeto restaurado.', 'delete' => 'Projeto excluído.' };
        return $this->redirect($action === 'delete' ? '/projects' : '/projects/' . $id);
    }
}
