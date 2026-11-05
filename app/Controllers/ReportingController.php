<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Infrastructure\Database;
use App\Repositories\ProjectRepository;
use App\Services\ActivityPresenter;
use App\Services\AuthService;
use App\Services\ReportingService;
use App\Validators\ReportFilters;
use Pecee\SimpleRouter\Exceptions\HttpException;
use Twig\Environment;

final class ReportingController
{
    public function __construct(private Environment $views, private array $config) {}

    public function handle(string $mode, ?string $projectId = null, ?string $workspaceId = null): string
    {
        $sync = $mode === 'dashboard' && in_array($_GET['sync'] ?? null, ['version', 'state'], true);
        if ($sync) header('Cache-Control: private, no-store');
        if ($sync && !isset($_SESSION['user_id'])) return $this->json(['error' => 'Sessão encerrada.'], 401);
        if (!isset($_SESSION['user_id'])) { header('Location: /login', true, 303); return ''; }
        $pdo = Database::connect($this->config['database']);
        $user = (new AuthService($pdo))->currentUser();
        if ($sync && !$user) return $this->json(['error' => 'Sessão encerrada.'], 401);
        if (!$user) { header('Location: /login', true, 303); return ''; }
        $this->views->addGlobal('workspaceChoices', (new \App\Repositories\WorkspaceRepository($pdo))->forUser((int) $user['id']));
        $base = ['projectName' => $this->config['name'], 'user' => $user, 'wide' => true, 'csrf' => $_SESSION['csrf'], 'sessionInvitation' => isset($_SESSION['pending_invitation'])];
        if ($sync && $user['email_verified_at'] === null) return $this->json(['error' => 'Confirme seu e-mail.'], 403);
        if ($user['email_verified_at'] === null) { http_response_code(403); return $this->views->render('verify-notice.html.twig', $base); }
        $context = $workspaceId === null ? ($projectId === null ? \App\Services\WorkspaceContext::initial($pdo, (int) $user['id']) : new \App\Services\WorkspaceContext((int) $user['id'], (int) (new ProjectRepository($pdo))->find((int) $user['id'], (int) $projectId)['tenant_id'])) : \App\Services\WorkspaceContext::resolve($pdo, (int) $user['id'], $workspaceId);
        $base['workspacePath'] = $context ? '/workspaces/' . $context->tenantId : '';
        if ($context && $projectId !== null) (new ProjectRepository($pdo, $context))->find((int) $user['id'], (int) $projectId);
        $service = new ReportingService($pdo, $context); $userId = (int) $user['id'];
        try {
            $input = $_GET;
            if ($projectId !== null) $input['project_id'] = $projectId;
            $filters = ReportFilters::parse($input, $mode === 'history');
            if ($mode === 'dashboard') {
                header('Cache-Control: private, no-store');
                $version = $service->dashboardVersion($userId, $filters['page']);
                if ($sync) {
                    $result = ['version' => $version];
                    if ($_GET['sync'] === 'state') {
                        $result['html'] = $this->views->render('dashboard/content.html.twig', $base + $service->dashboard($userId, $filters['page']));
                    }
                    return $this->json($result);
                }
                return $this->views->render('dashboard.html.twig', $base + ['dashboardVersion' => $version] + $service->dashboard($userId, $filters['page']));
            }
            if ($mode === 'tasks') return $this->views->render('reports/tasks.html.twig', $base + ['filters' => $filters, 'projects' => $service->projects($userId), 'result' => $service->tasks($userId, $filters), 'brainstorm'=>(new \App\Services\QuickNoteService($pdo, $context))->page($userId,null,filter_var($_GET['notes_page']??1,FILTER_VALIDATE_INT)?:1), 'noteProjectId'=>null, 'noteCanCreate'=>true, 'noteCanConvert'=>true, 'notesBase'=>$base['workspacePath'].'/tasks']);
            $project = $projectId === null ? null : (new ProjectRepository($pdo, $context))->find($userId, (int) $projectId);
            return $this->views->render('reports/history.html.twig', $base + ['filters' => $filters, 'project' => $project, 'projects' => $service->projects($userId), 'actions' => ActivityPresenter::LABELS, 'result' => $service->history($userId, $filters, $projectId === null)]);
        } catch (HttpException $exception) {
            if ($exception->getCode() !== 422) throw $exception;
            if ($sync) return $this->json(['error' => $exception->getMessage()], 422);
            http_response_code(422);
            return $this->views->render($mode === 'tasks' ? 'reports/tasks-error.html.twig' : ($mode === 'history' ? 'reports/history-error.html.twig' : 'reports/error.html.twig'), $base + ['message' => $exception->getMessage(), 'back' => $base['workspacePath'] . ($mode === 'tasks' ? '/tasks' : ($mode === 'dashboard' ? '/dashboard' : ($projectId === null ? '/history' : '/projects/' . (int) $projectId . '/history')))]);
        }
    }
    private function json(array $data, int $status = 200): string
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        return json_encode($data, JSON_THROW_ON_ERROR);
    }
}
