ALTER TABLE notification_outbox MODIFY kind ENUM('due_today','overdue','comment','assigned','role_changed','invited') NOT NULL;

-- Recupera convites pendentes de contas existentes, sem conceder acesso ao projeto.
INSERT INTO notification_outbox(user_id,tenant_id,project_id,actor_id,subject_id,kind,event_day,dedup_key)
SELECT u.id,i.tenant_id,i.project_id,i.invited_by,i.id,'invited',UTC_DATE(),
       SHA2(CONCAT('invitation:',i.id,':',i.token_hash,':',u.id),256)
FROM project_invitations i
JOIN users u ON u.email=i.email
JOIN projects p ON p.id=i.project_id AND p.tenant_id=i.tenant_id
WHERE u.id<>i.invited_by AND i.accepted_at IS NULL AND i.cancelled_at IS NULL
  AND i.expires_at>UTC_TIMESTAMP() AND p.deleted_at IS NULL AND p.archived_at IS NULL;
