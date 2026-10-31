ALTER TABLE quick_notes
    ADD COLUMN created_by BIGINT UNSIGNED NULL AFTER user_id,
    ADD COLUMN visibility ENUM('private','project') NOT NULL DEFAULT 'private',
    ADD COLUMN revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
    ADD KEY ix_quick_notes_visibility (tenant_id,project_id,visibility,id),
    ADD CONSTRAINT ck_quick_notes_visibility CHECK (visibility='private' OR project_id IS NOT NULL);
UPDATE quick_notes SET created_by=user_id WHERE created_by IS NULL;
ALTER TABLE quick_notes MODIFY COLUMN created_by BIGINT UNSIGNED NOT NULL,
    ADD CONSTRAINT fk_quick_notes_creator FOREIGN KEY (created_by) REFERENCES users(id);

-- Histórico privado preservado também depois da exclusão/conversão da nota.
-- note_id é uma referência histórica, intencionalmente sem FK para quick_notes.
CREATE TABLE quick_note_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    note_id BIGINT UNSIGNED NOT NULL,
    tenant_id BIGINT UNSIGNED NOT NULL,
    actor_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(32) NOT NULL,
    before_state JSON NULL,
    after_state JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY ix_quick_note_events (note_id,id),
    CONSTRAINT fk_quick_note_events_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_quick_note_events_actor FOREIGN KEY (actor_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO quick_note_events(note_id,tenant_id,actor_id,action,after_state)
SELECT id,tenant_id,user_id,'imported',JSON_OBJECT('tenant_id',tenant_id,'project_id',project_id,'user_id',user_id,'created_by',created_by,'visibility',visibility,'color',color,'revision',revision) FROM quick_notes;
