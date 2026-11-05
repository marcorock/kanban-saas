ALTER TABLE notification_outbox
    DROP CONSTRAINT ck_outbox_scope,
    ADD CONSTRAINT ck_outbox_scope CHECK (
        tenant_id IS NOT NULL AND (
            (kind = 'due_today' AND project_id IS NULL AND task_id IS NULL AND due_on IS NOT NULL)
            OR (kind <> 'due_today' AND project_id IS NOT NULL)
        )
    ),
    ADD CONSTRAINT fk_outbox_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id);
