ALTER TABLE activity_logs
    ADD KEY ix_activity_project_period (tenant_id, project_id, created_at, id),
    ADD KEY ix_activity_actor_period (actor_id, created_at, id);
