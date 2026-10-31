<?php

declare(strict_types=1);

namespace App\Services;

use App\Policies\ProjectPolicy;
use DateTimeImmutable;
use PDO;
use Pecee\SimpleRouter\Exceptions\HttpException;

final class TaskService
{
    public const ACTIONS = ['task.update', 'task.delete', 'checklist.create', 'checklist.update', 'checklist.delete', 'item.create', 'item.update', 'item.delete', 'comment.create', 'comment.update', 'comment.delete'];

    public function __construct(private PDO $pdo) {}

    private function id(array $data, string $field): int
    {
        if (!is_int($data[$field] ?? null) || $data[$field] < 1) { throw new HttpException('Identificador inválido.', 422); }
        return $data[$field];
    }

    private function text(array $data, string $field, int $max, bool $empty = false): string
    {
        $value = $data[$field] ?? null;
        if (!is_string($value) || str_contains($value, "\0") || !mb_check_encoding($value, 'UTF-8') || mb_strlen(trim($value)) > $max || (!$empty && trim($value) === '')) {
            throw new HttpException('Preencha os textos dentro dos limites indicados.', 422);
        }
        return trim($value);
    }

    private function date(array $data, string $field, bool $time = false): ?string
    {
        $value = $data[$field] ?? null;
        if ($value === '' || $value === null) { return null; }
        $pattern = $time ? '/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/D' : '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D';
        $format = $time ? 'Y-m-d H:i:s' : 'Y-m-d';
        $date = is_string($value) && preg_match($pattern, $value) ? DateTimeImmutable::createFromFormat('!' . $format, $value) : false;
        if (!$date || $date->format($format) !== $value || $value < '1000-01-01') { throw new HttpException('Informe datas válidas.', 422); }
        return $value;
    }

    private function row(string $table, array $project, int $taskId, int $id): array
    {
        $taskCondition = $table === 'tasks' ? '' : ' AND task_id = ?';
        $params = [$project['tenant_id'], $project['id'], $id];
        if ($taskCondition) $params[] = $taskId;
        $statement = $this->pdo->prepare("SELECT * FROM {$table} WHERE tenant_id = ? AND project_id = ? AND id = ?{$taskCondition}");
        $statement->execute($params);
        $row = $statement->fetch();
        if (!$row) { throw new HttpException('Item não encontrado nesta tarefa.', 404); }
        return $row;
    }

    public function assignees(array $project, int $taskId): array
    {
        $statement = $this->pdo->prepare('SELECT user_id FROM task_assignees WHERE tenant_id = ? AND project_id = ? AND task_id = ? ORDER BY user_id');
        $statement->execute([$project['tenant_id'], $project['id'], $taskId]);
        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    private function event(array $project, int $actor, int $taskId, string $action, ?array $before, ?array $after): void
    {
        (new ActivityLogger($this->pdo))->record($project, $actor, $action, $before, $after, $taskId);
    }

    public function apply(array $project, int $actor, string $action, array $data, string $now): void
    {
        $taskId = $this->id($data, 'task_id');
        $task = $this->row('tasks', $project, $taskId, $taskId);
        $scope = [$project['tenant_id'], $project['id'], $taskId];
        if ($action === 'task.delete') {
            if ((int)$task['created_by'] !== $actor) throw new HttpException('Somente quem criou a tarefa pode excluí-la.',403);
            if (($data['confirm'] ?? null) !== true) throw new HttpException('Confirme a exclusão da tarefa e de seu conteúdo.',422);
            // Mantém o histórico do projeto e os recibos; remove links para a tarefa excluída.
            $this->pdo->prepare('UPDATE activity_logs SET task_id=NULL WHERE tenant_id=? AND project_id=? AND task_id=?')->execute($scope);
            $this->pdo->prepare("UPDATE notification_outbox SET task_id=NULL,status=CASE WHEN status IN ('pending','processing','retry') THEN 'cancelled' ELSE status END,lock_token=NULL,locked_until=NULL WHERE tenant_id=? AND project_id=? AND task_id=?")->execute($scope);
            foreach (['task_comments','task_checklists','task_assignees'] as $table) $this->pdo->prepare('DELETE FROM '.$table.' WHERE tenant_id=? AND project_id=? AND task_id=?')->execute($scope);
            $this->pdo->prepare('DELETE FROM tasks WHERE tenant_id=? AND project_id=? AND id=?')->execute($scope);
            $this->pdo->prepare('UPDATE tasks SET position=position-1 WHERE tenant_id=? AND project_id=? AND column_id=? AND position>?')->execute([$project['tenant_id'],$project['id'],$task['column_id'],$task['position']]);
            (new ActivityLogger($this->pdo))->record($project,$actor,'task.deleted',['title'=>$task['title'],'column_id'=>(int)$task['column_id'],'position'=>(int)$task['position']],null);
            return;
        }
        if ($action === 'task.update') {
            $next = [
                'title' => $this->text($data, 'title', 200), 'description' => $this->text($data, 'description', 16000, true),
                'priority' => $data['priority'] ?? null, 'starts_on' => $this->date($data, 'starts_on'),
                'started_at' => $this->date($data, 'started_at', true), 'due_on' => $this->date($data, 'due_on'),
            ];
            if (!in_array($next['priority'], ['low', 'normal', 'high', 'urgent'], true)) { throw new HttpException('Prioridade inválida.', 422); }
            if (($next['starts_on'] !== null && $next['due_on'] !== null && $next['due_on'] < $next['starts_on'])
                || ($next['started_at'] !== null && ($next['started_at'] > $now || ($task['completed_at'] !== null && $next['started_at'] > $task['completed_at'])))) {
                throw new HttpException('Revise a ordem das datas. O início real não pode estar no futuro ou após a conclusão.', 422);
            }
            $assigned = $data['assignee_ids'] ?? null;
            if (!is_array($assigned) || !array_is_list($assigned) || count($assigned) > 100 || array_filter($assigned, static fn ($id): bool => !is_int($id) || $id < 1) || count(array_unique($assigned)) !== count($assigned)) {
                throw new HttpException('Responsáveis inválidos (máximo de 100 por tarefa).', 422);
            }
            sort($assigned);
            if ($assigned) {
                $placeholders = implode(',', array_fill(0, count($assigned), '?'));
                $statement = $this->pdo->prepare("SELECT pm.user_id FROM project_members pm JOIN tenant_users tu ON tu.tenant_id = pm.tenant_id AND tu.user_id = pm.user_id JOIN users u ON u.id = pm.user_id WHERE pm.tenant_id = ? AND pm.project_id = ? AND u.email_verified_at IS NOT NULL AND pm.user_id IN ({$placeholders}) ORDER BY pm.user_id");
                $statement->execute([$project['tenant_id'], $project['id'], ...$assigned]);
                if (array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN)) !== $assigned) { throw new HttpException('Todos os responsáveis devem ser participantes verificados deste projeto.', 422); }
            }
            $before = array_intersect_key($task, $next);
            $this->pdo->prepare('UPDATE tasks SET title = ?, description = ?, priority = ?, starts_on = ?, started_at = ?, due_on = ? WHERE tenant_id = ? AND project_id = ? AND id = ?')->execute([...array_values($next), ...$scope]);
            if ($before !== $next) { $this->event($project, $actor, $taskId, 'task.updated', $before, $next); }
            $previous = $this->assignees($project, $taskId);
            if ($previous !== $assigned) {
                $this->pdo->prepare('DELETE FROM task_assignees WHERE tenant_id = ? AND project_id = ? AND task_id = ?')->execute($scope);
                $statement = $this->pdo->prepare('INSERT INTO task_assignees (tenant_id, project_id, task_id, user_id) VALUES (?, ?, ?, ?)');
                foreach ($assigned as $id) $statement->execute([...$scope, $id]);
                $this->event($project, $actor, $taskId, 'task.assignees_changed', ['user_ids' => $previous], ['user_ids' => $assigned]);
            }
            return;
        }
        if (str_starts_with($action, 'checklist.')) {
            if ($action === 'checklist.create') {
                $title = $this->text($data, 'title', 120);
                $statement = $this->pdo->prepare('SELECT COALESCE(MAX(position) + 1, 0) FROM task_checklists WHERE tenant_id = ? AND project_id = ? AND task_id = ?');
                $statement->execute($scope); $position = (int) $statement->fetchColumn();
                $this->pdo->prepare('INSERT INTO task_checklists (tenant_id, project_id, task_id, title, position) VALUES (?, ?, ?, ?, ?)')->execute([...$scope, $title, $position]);
                $this->event($project, $actor, $taskId, 'checklist.created', null, ['checklist_id' => (int) $this->pdo->lastInsertId(), 'title' => $title]);
            } else {
                $checklist = $this->row('task_checklists', $project, $taskId, $this->id($data, 'checklist_id'));
                if ($action === 'checklist.update') {
                    $title = $this->text($data, 'title', 120);
                    $this->pdo->prepare('UPDATE task_checklists SET title = ? WHERE tenant_id = ? AND project_id = ? AND task_id = ? AND id = ?')->execute([$title, ...$scope, $checklist['id']]);
                    if ($title !== $checklist['title']) $this->event($project, $actor, $taskId, 'checklist.updated', ['checklist_id' => (int) $checklist['id'], 'title' => $checklist['title']], ['checklist_id' => (int) $checklist['id'], 'title' => $title]);
                } else {
                    if (($data['confirm'] ?? null) !== true) { throw new HttpException('Confirme a exclusão da lista e de seus itens.', 422); }
                    $statement = $this->pdo->prepare('SELECT title, is_done FROM task_checklist_items WHERE tenant_id = ? AND project_id = ? AND task_id = ? AND checklist_id = ? ORDER BY position, id');
                    $statement->execute([...$scope, $checklist['id']]); $items = $statement->fetchAll();
                    $this->pdo->prepare('DELETE FROM task_checklists WHERE tenant_id = ? AND project_id = ? AND task_id = ? AND id = ?')->execute([...$scope, $checklist['id']]);
                    $this->event($project, $actor, $taskId, 'checklist.deleted', ['checklist_id' => (int) $checklist['id'], 'title' => $checklist['title'], 'items' => $items], null);
                }
            }
            return;
        }
        if (str_starts_with($action, 'item.')) {
            if ($action === 'item.create') {
                $checklist = $this->row('task_checklists', $project, $taskId, $this->id($data, 'checklist_id'));
                $title = $this->text($data, 'title', 300);
                $statement = $this->pdo->prepare('SELECT COALESCE(MAX(position) + 1, 0) FROM task_checklist_items WHERE tenant_id = ? AND project_id = ? AND task_id = ? AND checklist_id = ?');
                $statement->execute([...$scope, $checklist['id']]); $position = (int) $statement->fetchColumn();
                $this->pdo->prepare('INSERT INTO task_checklist_items (tenant_id, project_id, task_id, checklist_id, title, position) VALUES (?, ?, ?, ?, ?, ?)')->execute([...$scope, $checklist['id'], $title, $position]);
                $this->event($project, $actor, $taskId, 'checklist.item_created', null, ['item_id' => (int) $this->pdo->lastInsertId(), 'checklist_id' => (int) $checklist['id'], 'title' => $title]);
            } else {
                $item = $this->row('task_checklist_items', $project, $taskId, $this->id($data, 'item_id'));
                $before = ['item_id' => (int) $item['id'], 'title' => $item['title'], 'is_done' => (bool) $item['is_done']];
                if ($action === 'item.update') {
                    $title = $this->text($data, 'title', 300);
                    if (!is_bool($data['is_done'] ?? null)) { throw new HttpException('Estado do item inválido.', 422); }
                    $after = ['item_id' => (int) $item['id'], 'title' => $title, 'is_done' => $data['is_done']];
                    $this->pdo->prepare('UPDATE task_checklist_items SET title = ?, is_done = ? WHERE tenant_id = ? AND project_id = ? AND task_id = ? AND id = ?')->execute([$title, (int) $data['is_done'], ...$scope, $item['id']]);
                    if ($before !== $after) $this->event($project, $actor, $taskId, 'checklist.item_updated', $before, $after);
                } else {
                    if (($data['confirm'] ?? null) !== true) { throw new HttpException('Confirme a exclusão do item.', 422); }
                    $this->pdo->prepare('DELETE FROM task_checklist_items WHERE tenant_id = ? AND project_id = ? AND task_id = ? AND id = ?')->execute([...$scope, $item['id']]);
                    $this->event($project, $actor, $taskId, 'checklist.item_deleted', $before, null);
                }
            }
            return;
        }
        if ($action === 'comment.create') {
            $body = $this->text($data, 'body', 5000);
            $this->pdo->prepare('INSERT INTO task_comments (tenant_id, project_id, task_id, author_id, body) VALUES (?, ?, ?, ?, ?)')->execute([...$scope, $actor, $body]);
            $this->event($project, $actor, $taskId, 'comment.created', null, ['comment_id' => (int) $this->pdo->lastInsertId(), 'body' => $body]);
        } else {
            $comment = $this->row('task_comments', $project, $taskId, $this->id($data, 'comment_id'));
            if ($comment['deleted_at'] !== null) { throw new HttpException('Comentário indisponível.', 404); }
            $author = (int) $comment['author_id'] === $actor;
            if (!$author && ($action === 'comment.update' || !ProjectPolicy::canManage($project, $actor))) { throw new HttpException('Permissão insuficiente sobre este comentário.', 403); }
            if ($action === 'comment.update') {
                $body = $this->text($data, 'body', 5000);
                $this->pdo->prepare('UPDATE task_comments SET body = ? WHERE tenant_id = ? AND project_id = ? AND task_id = ? AND id = ?')->execute([$body, ...$scope, $comment['id']]);
                if ($body !== $comment['body']) $this->event($project, $actor, $taskId, 'comment.updated', ['comment_id' => (int) $comment['id'], 'body' => $comment['body']], ['comment_id' => (int) $comment['id'], 'body' => $body]);
            } else {
                if (($data['confirm'] ?? null) !== true) { throw new HttpException('Confirme a exclusão do comentário.', 422); }
                $this->pdo->prepare('UPDATE task_comments SET deleted_at = UTC_TIMESTAMP() WHERE tenant_id = ? AND project_id = ? AND task_id = ? AND id = ?')->execute([...$scope, $comment['id']]);
                $this->event($project, $actor, $taskId, 'comment.deleted', ['comment_id' => (int) $comment['id'], 'body' => $comment['body']], null);
            }
        }
    }

    public function members(array $project): array
    {
        $statement = $this->pdo->prepare('SELECT pm.user_id, u.name FROM project_members pm JOIN users u ON u.id = pm.user_id JOIN tenant_users tu ON tu.tenant_id = pm.tenant_id AND tu.user_id = pm.user_id WHERE pm.tenant_id = ? AND pm.project_id = ? AND u.email_verified_at IS NOT NULL ORDER BY u.name, pm.user_id');
        $statement->execute([$project['tenant_id'], $project['id']]);
        return array_map(static fn (array $member): array => ['id' => (int) $member['user_id'], 'name' => $member['name']], $statement->fetchAll());
    }

    public function read(array $project, int $actor, int $taskId, int $commentsPage, int $activityPage): array
    {
        $task = $this->row('tasks', $project, $taskId, $taskId);
        foreach (['id', 'column_id', 'position', 'created_by'] as $key) $task[$key] = (int) $task[$key];
        unset($task['tenant_id'], $task['project_id']);
        $task['assignee_ids'] = $this->assignees($project, $taskId);
        $scope = [$project['tenant_id'], $project['id'], $taskId];
        $members = $this->members($project);
        $statement = $this->pdo->prepare('SELECT id, title, position FROM task_checklists WHERE tenant_id = ? AND project_id = ? AND task_id = ? ORDER BY position, id');
        $statement->execute($scope); $checklists = $statement->fetchAll();
        $statement = $this->pdo->prepare('SELECT id, checklist_id, title, is_done FROM task_checklist_items WHERE tenant_id = ? AND project_id = ? AND task_id = ? ORDER BY checklist_id, position, id');
        $statement->execute($scope); $items = [];
        foreach ($statement->fetchAll() as $item) $items[$item['checklist_id']][] = ['id' => (int) $item['id'], 'title' => $item['title'], 'is_done' => (bool) $item['is_done']];
        foreach ($checklists as &$checklist) { $checklist['id'] = (int) $checklist['id']; $checklist['items'] = $items[$checklist['id']] ?? []; } unset($checklist);
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM task_comments WHERE tenant_id = ? AND project_id = ? AND task_id = ? AND deleted_at IS NULL');
        $statement->execute($scope); $pages = max(1, (int) ceil((int) $statement->fetchColumn() / 20));
        $page = max(1, min($commentsPage, $pages)); $offset = ($page - 1) * 20;
        $statement = $this->pdo->prepare("SELECT c.id, c.author_id, u.name AS author, c.body, c.created_at, c.updated_at FROM task_comments c JOIN users u ON u.id = c.author_id WHERE c.tenant_id = ? AND c.project_id = ? AND c.task_id = ? AND c.deleted_at IS NULL ORDER BY c.id DESC LIMIT 20 OFFSET {$offset}");
        $statement->execute($scope); $comments = ['items' => $statement->fetchAll(), 'page' => $page, 'pages' => $pages];
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM activity_logs WHERE tenant_id = ? AND project_id = ? AND task_id = ?');
        $statement->execute($scope); $pages = max(1, (int) ceil((int) $statement->fetchColumn() / 20));
        $page = max(1, min($activityPage, $pages)); $offset = ($page - 1) * 20;
        $statement = $this->pdo->prepare("SELECT a.id, a.action, u.name AS author, a.created_at FROM activity_logs a JOIN users u ON u.id = a.actor_id WHERE a.tenant_id = ? AND a.project_id = ? AND a.task_id = ? ORDER BY a.id DESC LIMIT 20 OFFSET {$offset}");
        $statement->execute($scope); $activity = ['items' => $statement->fetchAll(), 'page' => $page, 'pages' => $pages];
        return ['task' => $task, 'members' => $members, 'checklists' => $checklists, 'comments' => $comments, 'activity' => $activity, 'actor_id' => $actor, 'can_delete' => $project['archived_at'] === null && $task['created_by'] === $actor];
    }
}
