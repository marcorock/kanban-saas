<?php

declare(strict_types=1);

namespace App\Services;

final class ActivityPresenter
{
    public const LABELS = [
        'project.created' => 'Projeto criado', 'project.updated' => 'Projeto alterado', 'project.archived' => 'Projeto arquivado', 'project.restored' => 'Projeto restaurado', 'project.deleted' => 'Projeto excluído', 'project.ownership_transferred' => 'Propriedade transferida',
        'board.initialized' => 'Quadro inicializado', 'column.created' => 'Coluna criada', 'column.updated' => 'Coluna alterada', 'column.deleted' => 'Coluna excluída', 'columns.reordered' => 'Colunas reordenadas',
        'task.created' => 'Tarefa criada', 'task.deleted' => 'Tarefa excluída', 'task.updated' => 'Tarefa alterada', 'task.moved' => 'Tarefa movimentada', 'task.completed' => 'Tarefa concluída', 'task.reopened' => 'Tarefa reaberta', 'task.assignees_changed' => 'Responsáveis alterados',
        'checklist.created' => 'Lista criada', 'checklist.updated' => 'Lista alterada', 'checklist.deleted' => 'Lista excluída', 'checklist.item_created' => 'Item criado', 'checklist.item_updated' => 'Item alterado', 'checklist.item_deleted' => 'Item excluído',
        'comment.created' => 'Comentário adicionado', 'comment.updated' => 'Comentário alterado', 'comment.deleted' => 'Comentário excluído',
        'invitation.created' => 'Convite enviado', 'invitation.reissued' => 'Convite reenviado', 'invitation.cancelled' => 'Convite cancelado', 'invitation.accepted' => 'Convite aceito',
        'member.added' => 'Participante adicionado', 'member.removed' => 'Participante removido', 'member.role_changed' => 'Papel alterado',
    ];
    private const FIELDS = ['name' => 'Nome', 'title' => 'Título', 'description' => 'Descrição', 'priority' => 'Prioridade', 'starts_on' => 'Início previsto', 'started_at' => 'Início real', 'due_on' => 'Prazo', 'completed_at' => 'Conclusão real', 'archived_at' => 'Arquivamento', 'deleted_at' => 'Exclusão', 'owner_id' => 'Proprietário', 'user_id' => 'Participante', 'user_ids' => 'Responsáveis', 'role' => 'Papel', 'previous_owner_role' => 'Papel do proprietário anterior', 'email' => 'E-mail', 'column_id' => 'Coluna', 'column_ids' => 'Ordem das colunas', 'position' => 'Posição', 'is_done' => 'Concluído', 'column_count' => 'Colunas iniciais', 'body' => 'Comentário', 'items' => 'Itens'];

    public static function ids(array $events): array
    {
        $users = []; $columns = [];
        foreach ($events as $event) {
            foreach (['before_data', 'after_data'] as $side) {
                $data = json_decode($event[$side] ?? 'null', true) ?? [];
                foreach (['owner_id', 'user_id'] as $key) if (isset($data[$key])) $users[] = (int) $data[$key];
                foreach ($data['user_ids'] ?? [] as $id) $users[] = (int) $id;
                if (isset($data['column_id'])) $columns[] = (int) $data['column_id'];
                foreach ($data['column_ids'] ?? [] as $id) $columns[] = (int) $id;
            }
        }
        return ['users' => array_values(array_unique($users)), 'columns' => array_values(array_unique($columns))];
    }

    private static function value(string $key, mixed $value, array $names): string
    {
        if ($value === null) return 'Não definido';
        if (in_array($key, ['owner_id', 'user_id'], true)) return $names['users'][(int) $value] ?? 'Participante indisponível';
        if ($key === 'column_id') return $names['columns'][(int) $value] ?? 'Coluna removida';
        if ($key === 'user_ids') return $value ? implode(', ', array_map(static fn ($id): string => $names['users'][(int) $id] ?? 'Participante indisponível', $value)) : 'Sem responsáveis';
        if ($key === 'column_ids') return implode(' → ', array_map(static fn ($id): string => $names['columns'][(int) $id] ?? 'Coluna removida', $value));
        if ($key === 'items') return implode('; ', array_map(static fn (array $item): string => ($item['is_done'] ? '☑ ' : '☐ ') . $item['title'], $value));
        if ($key === 'is_done') return $value ? 'Sim' : 'Não';
        if ($key === 'position') return (string) ((int) $value + 1);
        if ($key === 'priority') return ['low' => 'Baixa', 'normal' => 'Normal', 'high' => 'Alta', 'urgent' => 'Urgente'][$value] ?? 'Não definida';
        if (in_array($key, ['role', 'previous_owner_role'], true)) return ['owner' => 'Proprietário', 'admin' => 'Administrador', 'member' => 'Membro', 'viewer' => 'Visualizador'][$value] ?? 'Não definido';
        if (is_string($value) && preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})(.*)$/D', $value, $date) && in_array($key, ['starts_on', 'started_at', 'due_on', 'completed_at', 'archived_at', 'deleted_at'], true)) return $date[3] . '/' . $date[2] . '/' . $date[1] . $date[4] . ($date[4] ? ' UTC' : '');
        return is_scalar($value) ? (string) $value : 'Alterado';
    }

    public static function present(array $event, array $names): array
    {
        $event['label'] = self::LABELS[$event['action']] ?? 'Atividade registrada';
        $event['changes'] = [];
        $restricted = str_starts_with($event['action'], 'invitation.') && !($event['role'] === 'admin' || ($event['role'] === 'owner' && (int) $event['owner_id'] === (int) $event['viewer_id']));
        $event['restricted'] = $restricted;
        if (!$restricted) {
            $before = json_decode($event['before_data'] ?? 'null', true) ?? [];
            $after = json_decode($event['after_data'] ?? 'null', true) ?? [];
            foreach (self::FIELDS as $key => $label) {
                if (!array_key_exists($key, $before) && !array_key_exists($key, $after)) continue;
                if (($before[$key] ?? null) === ($after[$key] ?? null)) continue;
                $event['changes'][] = ['label' => $label, 'before' => self::value($key, $before[$key] ?? null, $names), 'after' => self::value($key, $after[$key] ?? null, $names)];
            }
        }
        unset($event['before_data'], $event['after_data'], $event['role'], $event['owner_id'], $event['viewer_id']);
        return $event;
    }
}
