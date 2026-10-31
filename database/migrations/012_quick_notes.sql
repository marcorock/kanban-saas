CREATE TABLE quick_notes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    project_id BIGINT UNSIGNED NULL,
    body TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY ix_quick_notes_personal (user_id,project_id,id),
    CONSTRAINT fk_quick_notes_owner FOREIGN KEY (tenant_id,user_id) REFERENCES tenant_users(tenant_id,user_id),
    CONSTRAINT fk_quick_notes_project FOREIGN KEY (tenant_id,project_id) REFERENCES projects(tenant_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
