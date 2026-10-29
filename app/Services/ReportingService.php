<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ProjectRepository;
use PDO;
use Throwable;

final class ReportingService
{
    private const ACCESS = ' JOIN project_members pm ON pm.tenant_id = p.tenant_id AND pm.project_id = p.id JOIN tenant_users tu ON tu.tenant_id = p.tenant_id AND tu.user_id = pm.user_id';
    private const SCOPE = ' WHERE pm.user_id = ? AND p.deleted_at IS NULL';
    private const ASSIGNED = ' FROM task_assignees mine JOIN tasks t ON t.tenant_id = mine.tenant_id AND t.project_id = mine.project_id AND t.id = mine.task_id JOIN projects p ON p.tenant_id = t.tenant_id AND p.id = t.project_id';
    private const HISTORY = ' FROM activity_logs a JOIN projects p ON p.tenant_id = a.tenant_id AND p.id = a.project_id JOIN users u ON u.id = a.actor_id LEFT JOIN tasks t ON t.tenant_id = a.tenant_id AND t.project_id = a.project_id AND t.id = a.task_id';

    public function __construct(private PDO $pdo) {}

    private function query(string $sql, array $params): array
    {
        $statement = $this->pdo->prepare($sql); $statement->execute($params); return $statement->fetchAll();
    }

    private function page(string $select, string $from, array $params, string $order, int $page, int $size = 25): array
    {
        $total = (int) $this->query('SELECT COUNT(*) AS total' . $from, $params)[0]['total'];
        $pages = max(1, (int) ceil($total / $size)); $page = max(1, min($page, $pages)); $offset = ($page - 1) * $size;
        $items = $this->query($select . $from . ' ORDER BY ' . $order . " LIMIT {$size} OFFSET {$offset}", $params);
        return compact('items', 'total', 'page', 'pages');
    }

    public function projects(int $userId): array
    {
        return $this->query('SELECT p.id, p.name, p.archived_at FROM projects p' . self::ACCESS . self::SCOPE . ' ORDER BY p.name, p.id', [$userId]);
    }

    public function tasks(int $userId, array $filters): array
    {
        if ($filters['project_id'] !== null) (new ProjectRepository($this->pdo))->find($userId, $filters['project_id']);
        $from = self::ASSIGNED . ' JOIN kanban_columns c ON c.tenant_id = t.tenant_id AND c.project_id = t.project_id AND c.id = t.column_id' . self::ACCESS . self::SCOPE . ' AND mine.user_id = ?';
        $params = [$userId, $userId];
        if ($filters['project_id'] !== null) { $from .= ' AND p.id = ?'; $params[] = $filters['project_id']; }
        if ($filters['archived'] !== 'all') $from .= $filters['archived'] === 'active' ? ' AND p.archived_at IS NULL' : ' AND p.archived_at IS NOT NULL';
        if ($filters['status'] === 'completed') $from .= ' AND t.completed_at IS NOT NULL';
        elseif ($filters['status'] !== 'all') { $from .= ' AND t.completed_at IS NULL'; if ($filters['status'] === 'in_progress') $from .= ' AND t.started_at IS NOT NULL'; }
        if ($filters['priority'] !== '') { $from .= ' AND t.priority = ?'; $params[] = $filters['priority']; }
        if ($filters['due'] === 'overdue') $from .= ' AND t.completed_at IS NULL AND t.due_on < UTC_DATE()';
        elseif ($filters['due'] === 'upcoming') $from .= ' AND t.completed_at IS NULL AND t.due_on BETWEEN UTC_DATE() AND DATE_ADD(UTC_DATE(), INTERVAL 7 DAY)';
        elseif ($filters['due'] === 'none') $from .= ' AND t.due_on IS NULL';
        foreach (['from' => '>=', 'to' => '<='] as $key => $operator) if ($filters[$key] !== '') { $from .= " AND t.due_on {$operator} ?"; $params[] = $filters[$key]; }
        return $this->page('SELECT t.id, t.title, t.priority, t.due_on, t.completed_at, t.started_at, p.id AS project_id, p.name AS project_name, p.archived_at, c.name AS column_name, (t.completed_at IS NULL AND t.due_on < UTC_DATE()) AS overdue', $from, $params, "t.due_on IS NULL, t.due_on, FIELD(t.priority, 'urgent', 'high', 'normal', 'low'), t.id DESC", $filters['page']);
    }

    private function names(string $table, array $ids): array
    {
        if (!$ids) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        return array_column($this->query("SELECT id, name FROM {$table} WHERE id IN ({$placeholders})", $ids), 'name', 'id');
    }

    private function present(array $events, int $userId): array
    {
        $ids = ActivityPresenter::ids($events);
        $names = ['users' => $this->names('users', $ids['users']), 'columns' => $this->names('kanban_columns', $ids['columns'])];
        return array_map(static fn (array $event): array => ActivityPresenter::present($event + ['viewer_id' => $userId], $names), $events);
    }

    public function history(int $userId, array $filters, bool $personal): array
    {
        if ($filters['project_id'] !== null) (new ProjectRepository($this->pdo))->find($userId, $filters['project_id']);
        $from = self::HISTORY . self::ACCESS . self::SCOPE; $params = [$userId];
        if ($personal) { $from .= ' AND a.actor_id = ?'; $params[] = $userId; }
        if ($filters['project_id'] !== null) { $from .= ' AND p.id = ?'; $params[] = $filters['project_id']; }
        if ($filters['action'] !== '') { $from .= ' AND a.action = ?'; $params[] = $filters['action']; }
        if ($filters['from'] !== '') { $from .= ' AND a.created_at >= ?'; $params[] = $filters['from'] . ' 00:00:00'; }
        if ($filters['to'] !== '') { $from .= ' AND a.created_at <= ?'; $params[] = $filters['to'] . ' 23:59:59'; }
        $result = $this->page('SELECT a.id, a.action, a.before_data, a.after_data, a.created_at, a.task_id, a.actor_id, p.id AS project_id, p.name AS project_name, p.owner_id, pm.role, u.name AS author, t.title AS task_title', $from, $params, 'a.created_at DESC, a.id DESC', $filters['page']);
        $result['items'] = $this->present($result['items'], $userId); return $result;
    }

    public function dashboard(int $userId, int $page): array
    {
        $this->pdo->beginTransaction();
        try {
            $active = ' FROM projects p' . self::ACCESS . self::SCOPE . ' AND p.archived_at IS NULL';
            $activeProjects = (int) $this->query('SELECT COUNT(*) AS total' . $active, [$userId])[0]['total'];
            $assigned = self::ASSIGNED . self::ACCESS . self::SCOPE . ' AND mine.user_id = ? AND p.archived_at IS NULL';
            $counts = $this->query('SELECT COALESCE(SUM(t.completed_at IS NULL), 0) AS pending, COALESCE(SUM(t.completed_at IS NULL AND t.started_at IS NOT NULL), 0) AS in_progress, COALESCE(SUM(t.completed_at IS NOT NULL), 0) AS completed, COALESCE(SUM(t.completed_at IS NULL AND t.due_on < UTC_DATE()), 0) AS overdue' . $assigned, [$userId, $userId])[0];
            $counts = array_map('intval', $counts); $counts['active_projects'] = $activeProjects;
            $pages = max(1, (int) ceil($activeProjects / 10)); $page = max(1, min($page, $pages)); $offset = ($page - 1) * 10;
            $progress = $this->query('SELECT p.id, p.name, COUNT(t.id) AS total, COALESCE(SUM(t.completed_at IS NOT NULL), 0) AS completed FROM projects p' . self::ACCESS . ' LEFT JOIN tasks t ON t.tenant_id = p.tenant_id AND t.project_id = p.id' . self::SCOPE . " AND p.archived_at IS NULL GROUP BY p.id, p.name ORDER BY p.id DESC LIMIT 10 OFFSET {$offset}", [$userId]);
            foreach ($progress as &$project) $project['percentage'] = $project['total'] ? (int) round(100 * $project['completed'] / $project['total']) : 0; unset($project);
            $upcoming = $this->query('SELECT t.id, t.title, t.due_on, p.id AS project_id, p.name AS project_name' . $assigned . ' AND t.completed_at IS NULL AND t.due_on BETWEEN UTC_DATE() AND DATE_ADD(UTC_DATE(), INTERVAL 7 DAY) ORDER BY t.due_on, t.id LIMIT 5', [$userId, $userId]);
            $recent = $this->query('SELECT a.id, a.action, a.created_at, a.task_id, p.id AS project_id, p.name AS project_name, u.name AS author, t.title AS task_title' . self::HISTORY . self::ACCESS . self::SCOPE . ' ORDER BY a.created_at DESC, a.id DESC LIMIT 10', [$userId]);
            foreach ($recent as &$event) $event['label'] = ActivityPresenter::LABELS[$event['action']] ?? 'Atividade registrada'; unset($event);
            $this->pdo->commit();
            return ['counts' => $counts, 'projects' => ['items' => $progress, 'page' => $page, 'pages' => $pages], 'upcoming' => $upcoming, 'recent' => $recent];
        } catch (Throwable $exception) { $this->pdo->rollBack(); throw $exception; }
    }
}
