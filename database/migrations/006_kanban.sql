ALTER TABLE projects ADD COLUMN board_version BIGINT UNSIGNED NOT NULL DEFAULT 0;

CREATE TABLE kanban_columns (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    project_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(80) NOT NULL,
    position INT UNSIGNED NOT NULL,
    is_done BOOLEAN NOT NULL DEFAULT FALSE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_columns_scope (tenant_id, project_id, id),
    KEY ix_columns_order (tenant_id, project_id, position, id),
    CONSTRAINT fk_columns_project FOREIGN KEY (tenant_id, project_id) REFERENCES projects (tenant_id, id),
    CONSTRAINT ck_columns_done CHECK (is_done IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tasks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    project_id BIGINT UNSIGNED NOT NULL,
    column_id BIGINT UNSIGNED NOT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    position INT UNSIGNED NOT NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tasks_scope (tenant_id, project_id, id),
    KEY ix_tasks_order (tenant_id, project_id, column_id, position, id),
    KEY ix_tasks_creator (created_by),
    CONSTRAINT fk_tasks_column FOREIGN KEY (tenant_id, project_id, column_id) REFERENCES kanban_columns (tenant_id, project_id, id),
    CONSTRAINT fk_tasks_creator FOREIGN KEY (created_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE activity_logs
    ADD COLUMN task_id BIGINT UNSIGNED NULL,
    ADD KEY ix_activity_task (tenant_id, project_id, task_id, id),
    ADD CONSTRAINT fk_activity_task FOREIGN KEY (tenant_id, project_id, task_id) REFERENCES tasks (tenant_id, project_id, id);

INSERT INTO kanban_columns (tenant_id, project_id, name, position, is_done)
SELECT p.tenant_id, p.id, defaults.name, defaults.position, defaults.is_done
FROM projects p
CROSS JOIN (
    SELECT 'Backlog' AS name, 0 AS position, 0 AS is_done
    UNION ALL SELECT 'A Fazer', 1, 0
    UNION ALL SELECT 'Em Andamento', 2, 0
    UNION ALL SELECT 'Em Revisão', 3, 0
    UNION ALL SELECT 'Concluído', 4, 1
) defaults
WHERE p.deleted_at IS NULL;

INSERT INTO activity_logs (tenant_id, project_id, actor_id, action, after_data)
SELECT tenant_id, id, owner_id, 'board.initialized', JSON_OBJECT('source', 'migration', 'column_count', 5)
FROM projects WHERE deleted_at IS NULL;
