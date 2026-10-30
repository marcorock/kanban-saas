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

    public function handle(string $mode, ?string $projectId = null): string
    {
        if (!isset($_SESSION['user_id'])) { header('Location: /login', true, 303); return ''; }
        $pdo = Database::connect($this->config['database']);
        $user = (new AuthService($pdo))->currentUser();
        if (!$user) { header('Location: /login', true, 303); return ''; }
        $base = ['projectName' => $this->config['name'], 'user' => $user, 'wide' => true, 'csrf' => $_SESSION['csrf'], 'sessionInvitation' => isset($_SESSION['pending_invitation'])];
        if ($user['email_verified_at'] === null) { http_response_code(403); return $this->views->render('verify-notice.html.twig', $base); }
        $service = new ReportingService($pdo); $userId = (int) $user['id'];
        try {
            $input = $_GET;
            if ($projectId !== null) $input['project_id'] = $projectId;
            $filters = ReportFilters::parse($input, $mode === 'history');
            if ($mode === 'dashboard') return $this->views->render('dashboard.html.twig', $base + $service->dashboard($userId, $filters['page']));
            if ($mode === 'tasks') return $this->views->render('reports/tasks.html.twig', $base + ['filters' => $filters, 'projects' => $service->projects($userId), 'result' => $service->tasks($userId, $filters)]);
            $project = $projectId === null ? null : (new ProjectRepository($pdo))->find($userId, (int) $projectId);
            return $this->views->render('reports/history.html.twig', $base + ['filters' => $filters, 'project' => $project, 'projects' => $service->projects($userId), 'actions' => ActivityPresenter::LABELS, 'result' => $service->history($userId, $filters, $projectId === null)]);
        } catch (HttpException $exception) {
            if ($exception->getCode() !== 422) throw $exception;
            http_response_code(422);
            return $this->views->render($mode === 'tasks' ? 'reports/tasks-error.html.twig' : ($mode === 'history' ? 'reports/history-error.html.twig' : 'reports/error.html.twig'), $base + ['message' => $exception->getMessage(), 'back' => $mode === 'tasks' ? '/tasks' : ($mode === 'dashboard' ? '/dashboard' : ($projectId === null ? '/history' : '/projects/' . (int) $projectId . '/history'))]);
        }
    }
}
