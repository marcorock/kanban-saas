<?php

declare(strict_types=1);

namespace App\Services;

use App\Policies\ProjectPolicy;
use App\Repositories\ProjectRepository;
use PDO;
use Pecee\SimpleRouter\Exceptions\HttpException;
use Throwable;

final class BoardService
{
    public function __construct(private PDO $pdo) {}

    public static function initialize(PDO $pdo, array $project, int $actorId): void
    {
        $statement = $pdo->prepare('INSERT INTO kanban_columns (tenant_id, project_id, name, position, is_done) VALUES (?, ?, ?, ?, ?)');
        foreach (['Backlog', 'A Fazer', 'Em Andamento', 'Em Revisão', 'Concluído'] as $position => $name) {
            $statement->execute([$project['tenant_id'], $project['id'], $name, $position, (int) ($position === 4)]);
        }
        (new ActivityLogger($pdo))->record($project, $actorId, 'board.initialized', null, ['column_count' => 5]);
    }

    public function state(int $userId, int $projectId): array
    {
        $this->pdo->beginTransaction();
        try {
            $project = (new ProjectRepository($this->pdo))->find($userId, $projectId, true);
            $state = $this->snapshot($project, $userId);
            $this->pdo->commit();
            return $state;
        } catch (Throwable $exception) { $this->pdo->rollBack(); throw $exception; }
    }

    private function rows(string $table, array $project, string $order): array
    {
        // Identificadores SQL fixos, definidos exclusivamente dentro deste serviço.
        $statement = $this->pdo->prepare("SELECT * FROM {$table} WHERE tenant_id = ? AND project_id = ? ORDER BY {$order}");
        $statement->execute([$project['tenant_id'], $project['id']]);
        return $statement->fetchAll();
    }

    private function snapshot(array $project, int $userId): array
    {
        $columns = $this->rows('kanban_columns', $project, 'position, id');
        $tasks = $this->rows('tasks', $project, 'column_id, position, id');
        $grouped = [];
        foreach ($tasks as $task) {
            $grouped[$task['column_id']][] = ['id' => (int) $task['id'], 'title' => $task['title'], 'position' => (int) $task['position'], 'completed_at' => $task['completed_at']];
        }
        $result = [];
        foreach ($columns as $column) {
            $result[] = ['id' => (int) $column['id'], 'name' => $column['name'], 'position' => (int) $column['position'], 'is_done' => (bool) $column['is_done'], 'tasks' => $grouped[$column['id']] ?? []];
        }
        $active = $project['archived_at'] === null;
        return [
            'project_id' => (int) $project['id'], 'name' => $project['name'], 'version' => (int) $project['board_version'],
            'archived' => !$active, 'can_manage' => $active && ProjectPolicy::canManage($project, $userId),
            'can_write' => $active && ($project['role'] === 'member' || ProjectPolicy::canManage($project, $userId)), 'columns' => $result,
        ];
    }

    private function text(array $data, string $key, int $max): string
    {
        $value = $data[$key] ?? null;
        if (!is_string($value) || str_contains($value, "\0") || !mb_check_encoding($value, 'UTF-8') || trim($value) === '' || mb_strlen(trim($value)) > $max) {
            throw new HttpException('Preencha um texto válido, dentro do limite permitido.', 422);
        }
        return trim($value);
    }

    private function integer(array $data, string $key, int $min = 1): int
    {
        $value = $data[$key] ?? null;
        if (!is_int($value) || $value < $min) { throw new HttpException('Identificador ou posição inválidos.', 422); }
        return $value;
    }

    private function done(array $data): bool
    {
        if (!is_bool($data['is_done'] ?? null)) { throw new HttpException('Informe se a coluna conclui tarefas.', 422); }
        return $data['is_done'];
    }

    private function find(string $table, array $project, int $id): array
    {
        $statement = $this->pdo->prepare("SELECT * FROM {$table} WHERE tenant_id = ? AND project_id = ? AND id = ?");
        $statement->execute([$project['tenant_id'], $project['id'], $id]);
        $row = $statement->fetch();
        if (!$row) { throw new HttpException('Item não encontrado neste projeto.', 404); }
        return $row;
    }

    private function orderTasks(array $project, int $columnId, array $ids): void
    {
        $statement = $this->pdo->prepare('UPDATE tasks SET position = ? WHERE tenant_id = ? AND project_id = ? AND column_id = ? AND id = ?');
        foreach (array_values($ids) as $position => $id) { $statement->execute([$position, $project['tenant_id'], $project['id'], $columnId, $id]); }
    }

    private function taskIds(array $project, int $columnId): array
    {
        $statement = $this->pdo->prepare('SELECT id FROM tasks WHERE tenant_id = ? AND project_id = ? AND column_id = ? ORDER BY position, id');
        $statement->execute([$project['tenant_id'], $project['id'], $columnId]);
        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    private function event(array $project, int $actor, string $action, ?array $before, ?array $after, ?int $taskId = null): void
    {
        (new ActivityLogger($this->pdo))->record($project, $actor, $action, $before, $after, $taskId);
    }

    public function mutate(int $userId, int $projectId, int $version, string $action, array $data): array
    {
        $columnAction = in_array($action, ['column.create', 'column.update', 'column.delete', 'columns.reorder'], true);
        if (!$columnAction && !in_array($action, ['task.create', 'task.rename', 'task.move'], true)) { throw new HttpException('Operação inválida.', 422); }
        $this->pdo->beginTransaction();
        try {
            $project = (new ProjectRepository($this->pdo))->find($userId, $projectId, true);
            if ($project['archived_at'] !== null) { throw new HttpException('Restaure o projeto antes de alterar o quadro.', 409); }
            $canManage = ProjectPolicy::canManage($project, $userId);
            if (($columnAction && !$canManage) || (!$columnAction && !$canManage && $project['role'] !== 'member')) { throw new HttpException('Permissão insuficiente.', 403); }
            if ((int) $project['board_version'] !== $version) { throw new HttpException('O quadro foi alterado por outro participante. Atualize a página e tente novamente.', 409); }
            $now = $this->pdo->query('SELECT UTC_TIMESTAMP()')->fetchColumn();
            if ($action === 'column.create') {
                $name = $this->text($data, 'name', 80); $done = $this->done($data);
                $columns = $this->rows('kanban_columns', $project, 'position, id');
                $this->pdo->prepare('INSERT INTO kanban_columns (tenant_id, project_id, name, position, is_done) VALUES (?, ?, ?, ?, ?)')->execute([$project['tenant_id'], $projectId, $name, count($columns), (int) $done]);
                $id = (int) $this->pdo->lastInsertId();
                $this->event($project, $userId, 'column.created', null, ['column_id' => $id, 'name' => $name, 'is_done' => $done, 'position' => count($columns)]);
            } elseif ($action === 'column.update') {
                $column = $this->find('kanban_columns', $project, $this->integer($data, 'column_id'));
                $name = $this->text($data, 'name', 80); $done = $this->done($data);
                $this->pdo->prepare('UPDATE kanban_columns SET name = ?, is_done = ? WHERE tenant_id = ? AND project_id = ? AND id = ?')->execute([$name, (int) $done, $project['tenant_id'], $projectId, $column['id']]);
                if ((bool) $column['is_done'] !== $done) {
                    foreach ($this->taskIds($project, (int) $column['id']) as $taskId) {
                        $task = $this->find('tasks', $project, $taskId);
                        $completed = $done ? ($task['completed_at'] ?? $now) : null;
                        $this->pdo->prepare('UPDATE tasks SET completed_at = ? WHERE tenant_id = ? AND project_id = ? AND id = ?')->execute([$completed, $project['tenant_id'], $projectId, $taskId]);
                        $this->event($project, $userId, $done ? 'task.completed' : 'task.reopened', ['completed_at' => $task['completed_at']], ['completed_at' => $completed], $taskId);
                    }
                }
                if ($column['name'] !== $name || (bool) $column['is_done'] !== $done) {
                    $this->event($project, $userId, 'column.updated', ['column_id' => (int) $column['id'], 'name' => $column['name'], 'is_done' => (bool) $column['is_done']], ['column_id' => (int) $column['id'], 'name' => $name, 'is_done' => $done]);
                }
            } elseif ($action === 'column.delete') {
                $column = $this->find('kanban_columns', $project, $this->integer($data, 'column_id'));
                $columns = $this->rows('kanban_columns', $project, 'position, id');
                if (count($columns) <= 1 || $this->taskIds($project, (int) $column['id'])) { throw new HttpException('Mantenha pelo menos uma coluna e mova as tarefas antes de excluir.', 409); }
                $this->pdo->prepare('DELETE FROM kanban_columns WHERE tenant_id = ? AND project_id = ? AND id = ?')->execute([$project['tenant_id'], $projectId, $column['id']]);
                $remaining = array_values(array_filter($columns, static fn (array $item): bool => $item['id'] !== $column['id']));
                $statement = $this->pdo->prepare('UPDATE kanban_columns SET position = ? WHERE tenant_id = ? AND project_id = ? AND id = ?');
                foreach ($remaining as $position => $item) { $statement->execute([$position, $project['tenant_id'], $projectId, $item['id']]); }
                $this->event($project, $userId, 'column.deleted', ['column_id' => (int) $column['id'], 'name' => $column['name'], 'position' => (int) $column['position']], null);
            } elseif ($action === 'columns.reorder') {
                $ids = $data['column_ids'] ?? null;
                $columns = $this->rows('kanban_columns', $project, 'position, id');
                $existing = array_map('intval', array_column($columns, 'id'));
                if (!is_array($ids) || !array_is_list($ids) || count($ids) !== count($existing) || array_filter($ids, static fn ($id): bool => !is_int($id)) || count(array_unique($ids)) !== count($ids)) { throw new HttpException('Ordenação de colunas inválida.', 422); }
                $sorted = $ids; $expected = $existing; sort($sorted); sort($expected);
                if ($sorted !== $expected) { throw new HttpException('Colunas não pertencem a este quadro.', 422); }
                $statement = $this->pdo->prepare('UPDATE kanban_columns SET position = ? WHERE tenant_id = ? AND project_id = ? AND id = ?');
                foreach ($ids as $position => $id) { $statement->execute([$position, $project['tenant_id'], $projectId, $id]); }
                if ($existing !== $ids) { $this->event($project, $userId, 'columns.reordered', ['column_ids' => $existing], ['column_ids' => $ids]); }
            } elseif ($action === 'task.create') {
                $column = $this->find('kanban_columns', $project, $this->integer($data, 'column_id'));
                $title = $this->text($data, 'title', 200);
                $position = count($this->taskIds($project, (int) $column['id']));
                $completed = $column['is_done'] ? $now : null;
                $this->pdo->prepare('INSERT INTO tasks (tenant_id, project_id, column_id, created_by, title, position, completed_at) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([$project['tenant_id'], $projectId, $column['id'], $userId, $title, $position, $completed]);
                $taskId = (int) $this->pdo->lastInsertId();
                $this->event($project, $userId, 'task.created', null, ['title' => $title, 'column_id' => (int) $column['id'], 'position' => $position, 'completed_at' => $completed], $taskId);
                if ($completed !== null) { $this->event($project, $userId, 'task.completed', null, ['completed_at' => $completed], $taskId); }
            } elseif ($action === 'task.rename') {
                $task = $this->find('tasks', $project, $this->integer($data, 'task_id'));
                $title = $this->text($data, 'title', 200);
                $this->pdo->prepare('UPDATE tasks SET title = ? WHERE tenant_id = ? AND project_id = ? AND id = ?')->execute([$title, $project['tenant_id'], $projectId, $task['id']]);
                if ($task['title'] !== $title) { $this->event($project, $userId, 'task.updated', ['title' => $task['title']], ['title' => $title], (int) $task['id']); }
            } else {
                $task = $this->find('tasks', $project, $this->integer($data, 'task_id'));
                $column = $this->find('kanban_columns', $project, $this->integer($data, 'column_id'));
                $position = $this->integer($data, 'position', 0);
                $destination = array_values(array_filter($this->taskIds($project, (int) $column['id']), static fn (int $id): bool => $id !== (int) $task['id']));
                if ($position > count($destination)) { throw new HttpException('Posição de destino inválida.', 422); }
                $completed = $column['is_done'] ? ($task['completed_at'] ?? $now) : null;
                $this->pdo->prepare('UPDATE tasks SET column_id = ?, completed_at = ? WHERE tenant_id = ? AND project_id = ? AND id = ?')->execute([$column['id'], $completed, $project['tenant_id'], $projectId, $task['id']]);
                array_splice($destination, $position, 0, [(int) $task['id']]);
                $this->orderTasks($project, (int) $column['id'], $destination);
                if ((int) $task['column_id'] !== (int) $column['id']) { $this->orderTasks($project, (int) $task['column_id'], $this->taskIds($project, (int) $task['column_id'])); }
                if ((int) $task['column_id'] !== (int) $column['id'] || (int) $task['position'] !== $position) {
                    $this->event($project, $userId, 'task.moved', ['column_id' => (int) $task['column_id'], 'position' => (int) $task['position']], ['column_id' => (int) $column['id'], 'position' => $position], (int) $task['id']);
                }
                if ($task['completed_at'] !== $completed) { $this->event($project, $userId, $completed === null ? 'task.reopened' : 'task.completed', ['completed_at' => $task['completed_at']], ['completed_at' => $completed], (int) $task['id']); }
            }
            $this->pdo->prepare('UPDATE projects SET board_version = board_version + 1 WHERE tenant_id = ? AND id = ?')->execute([$project['tenant_id'], $projectId]);
            $project['board_version'] = (int) $project['board_version'] + 1;
            $result = $this->snapshot($project, $userId);
            $this->pdo->commit();
            return $result;
        } catch (Throwable $exception) { $this->pdo->rollBack(); throw $exception; }
    }
}
