<?php

declare(strict_types=1);
require __DIR__ . '/http.php';

// Conta chamadas SQL de leitura sem armazenar parâmetros ou dados de usuários.
final class MeasuredPDO extends PDO
{
    public int $queries = 0;
    public function prepare(string $query, array $options = []): PDOStatement|false {
        $this->queries++; return parent::prepare($query, $options);
    }
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false {
        $this->queries++; return parent::query($query, $fetchMode, ...$fetchModeArgs);
    }
}
$id = (new App\Services\AuthService($pdo))->register('Carga descartável', 'load-' . bin2hex(random_bytes(6)) . '@example.com', 'Senha-carga-segura-123');
$pdo->prepare('UPDATE users SET email_verified_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$id]);
$projectId = (new App\Services\ProjectService($pdo))->create($id, ['name' => 'Carga de revisão', 'description' => '', 'starts_on' => '', 'due_on' => '']);
$project = (new App\Repositories\ProjectRepository($pdo))->find($id, $projectId);
$statement = $pdo->prepare('SELECT id FROM kanban_columns WHERE project_id = ? ORDER BY position LIMIT 1');
$statement->execute([$projectId]); $column = $statement->fetchColumn();
$db = $config['database'];
$measured = new MeasuredPDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['name']), $db['username'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
$measured->exec("SET time_zone = '+00:00'");
$insert = $pdo->prepare('INSERT INTO tasks (tenant_id, project_id, column_id, created_by, title, position, description, due_on) VALUES (?, ?, ?, ?, ?, ?, ?, UTC_DATE())');
$assign = $pdo->prepare('INSERT INTO task_assignees (tenant_id, project_id, task_id, user_id) VALUES (?, ?, ?, ?)');
$event = $pdo->prepare("INSERT INTO activity_logs (tenant_id, project_id, actor_id, task_id, action, after_data) VALUES (?, ?, ?, ?, 'task.created', ?)");
$previousQueries = null;
foreach ([100, 1000] as $size) {
    $pdo->beginTransaction();
    for ($i = $size === 100 ? 0 : 100; $i < $size; $i++) {
        $insert->execute([$project['tenant_id'], $projectId, $column, $id, 'Carga ' . $i, $i, str_repeat('d', 16000)]);
        $taskId = $pdo->lastInsertId();
        $assign->execute([$project['tenant_id'], $projectId, $taskId, $id]);
        $event->execute([$project['tenant_id'], $projectId, $id, $taskId, json_encode(['title' => 'Carga ' . $i], JSON_THROW_ON_ERROR)]);
    }
    $pdo->commit();
    $measured->queries = 0; memory_reset_peak_usage(); $memory = memory_get_usage(); $start = hrtime(true);
    $board = (new App\Services\BoardService($measured))->state($id, $projectId);
    $milliseconds = (hrtime(true) - $start) / 1e6;
    $bytes = memory_get_peak_usage() - $memory;
    check(count($board['columns'][0]['tasks']) === $size, 'Quadro completo');
    check($bytes < 12 * 1024 * 1024, 'Leitura do quadro não deve carregar 16 MB de descrições');
    if ($previousQueries !== null) check($measured->queries === $previousQueries, 'Consultas constantes, sem N+1');
    $previousQueries = $measured->queries;
    printf("Carga descartável: %d tarefas, %d consultas no quadro, %.2f ms, pico adicional %.2f MiB.\n", $size, $measured->queries, $milliseconds, $bytes / 1048576);
    unset($board);
}
$reports = new App\Services\ReportingService($measured);
$filters = App\Validators\ReportFilters::parse(['project_id' => (string) $projectId, 'status' => 'all'], false);
$tasks = $reports->tasks($id, $filters);
check($tasks['total'] === 1000 && count($tasks['items']) === 25, 'Tarefas paginadas sob carga');
$history = $reports->history($id, App\Validators\ReportFilters::parse(['project_id' => (string) $projectId], true), false);
check($history['total'] >= 1000 && count($history['items']) === 25, 'Histórico paginado sob carga');
$dashboard = $reports->dashboard($id, 1);
check($dashboard['counts']['pending'] === 1000 && count($dashboard['upcoming']) === 5, 'Métricas corretas sob carga');
$plan = $pdo->prepare('EXPLAIN SELECT id FROM tasks WHERE tenant_id = ? AND project_id = ? ORDER BY column_id, position, id');
$plan->execute([$project['tenant_id'], $projectId]);
$keys = $plan->fetch();
check(str_contains((string) $keys['possible_keys'], 'ix_tasks_order'), 'Índice de ordenação disponível no plano');
echo "OK: carga sintética, memória, consultas constantes, paginação, métricas e índice de ordenação. Tempos locais não representam SLA de produção.\n";
