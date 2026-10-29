ALTER TABLE tasks
    ADD COLUMN description TEXT NOT NULL DEFAULT '',
    ADD COLUMN priority ENUM('low', 'normal', 'high', 'urgent') NOT NULL DEFAULT 'normal',
    ADD COLUMN starts_on DATE NULL,
    ADD COLUMN started_at DATETIME NULL,
    ADD COLUMN due_on DATE NULL,
    ADD KEY ix_tasks_due (tenant_id, project_id, completed_at, due_on),
    ADD CONSTRAINT ck_task_planned_dates CHECK (starts_on IS NULL OR due_on IS NULL OR due_on >= starts_on);

ALTER TABLE project_members ADD UNIQUE KEY uq_project_members_scope (tenant_id, project_id, user_id);

CREATE TABLE task_assignees (
    tenant_id BIGINT UNSIGNED NOT NULL,
    project_id BIGINT UNSIGNED NOT NULL,
    task_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (task_id, user_id),
    KEY ix_assignees_task (tenant_id, project_id, task_id),
    KEY ix_assignees_member (tenant_id, project_id, user_id),
    KEY ix_assignees_user (user_id, tenant_id, project_id, task_id),
    CONSTRAINT fk_assignees_task FOREIGN KEY (tenant_id, project_id, task_id) REFERENCES tasks (tenant_id, project_id, id),
    CONSTRAINT fk_assignees_member FOREIGN KEY (tenant_id, project_id, user_id) REFERENCES project_members (tenant_id, project_id, user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE task_checklists (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    project_id BIGINT UNSIGNED NOT NULL,
    task_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(120) NOT NULL,
    position INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_checklists_scope (tenant_id, project_id, task_id, id),
    KEY ix_checklists_order (tenant_id, project_id, task_id, position, id),
    CONSTRAINT fk_checklists_task FOREIGN KEY (tenant_id, project_id, task_id) REFERENCES tasks (tenant_id, project_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE task_checklist_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    project_id BIGINT UNSIGNED NOT NULL,
    task_id BIGINT UNSIGNED NOT NULL,
    checklist_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(300) NOT NULL,
    position INT UNSIGNED NOT NULL,
    is_done BOOLEAN NOT NULL DEFAULT FALSE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY ix_checklist_items_order (tenant_id, project_id, task_id, checklist_id, position, id),
    CONSTRAINT fk_items_checklist FOREIGN KEY (tenant_id, project_id, task_id, checklist_id) REFERENCES task_checklists (tenant_id, project_id, task_id, id) ON DELETE CASCADE,
    CONSTRAINT ck_checklist_item_done CHECK (is_done IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE task_comments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    project_id BIGINT UNSIGNED NOT NULL,
    task_id BIGINT UNSIGNED NOT NULL,
    author_id BIGINT UNSIGNED NOT NULL,
    body TEXT NOT NULL,
    deleted_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY ix_comments_task (tenant_id, project_id, task_id, deleted_at, id),
    KEY ix_comments_author (author_id),
    CONSTRAINT fk_comments_task FOREIGN KEY (tenant_id, project_id, task_id) REFERENCES tasks (tenant_id, project_id, id),
    CONSTRAINT fk_comments_author FOREIGN KEY (author_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
