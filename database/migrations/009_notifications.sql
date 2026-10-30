CREATE TABLE notification_preferences (
    user_id BIGINT UNSIGNED PRIMARY KEY,
    bell_enabled BOOLEAN NOT NULL DEFAULT TRUE,
    push_enabled BOOLEAN NOT NULL DEFAULT FALSE,
    email_enabled BOOLEAN NOT NULL DEFAULT FALSE,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_notification_preferences_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT ck_notification_preferences_flags CHECK (bell_enabled IN (0,1) AND push_enabled IN (0,1) AND email_enabled IN (0,1))
) ENGINE=InnoDB;

CREATE TABLE notification_outbox (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    tenant_id BIGINT UNSIGNED NULL,
    project_id BIGINT UNSIGNED NULL,
    task_id BIGINT UNSIGNED NULL,
    actor_id BIGINT UNSIGNED NULL,
    event_id BIGINT UNSIGNED NULL,
    subject_id BIGINT UNSIGNED NULL,
    kind ENUM('due_today','overdue','comment','assigned','role_changed') NOT NULL,
    channel ENUM('bell','push','email') NOT NULL DEFAULT 'bell',
    due_on DATE NULL,
    event_day DATE NOT NULL,
    target_role VARCHAR(16) NULL,
    dedup_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    status ENUM('pending','processing','delivered','cancelled','retry','failed') NOT NULL DEFAULT 'pending',
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    locked_until DATETIME NULL,
    lock_token CHAR(32) CHARACTER SET ascii NULL,
    last_error VARCHAR(64) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    delivered_at DATETIME NULL,
    UNIQUE KEY uq_notification_dedup (dedup_key),
    UNIQUE KEY uq_outbox_event (user_id,channel,event_id,kind),
    UNIQUE KEY uq_outbox_recipient (id,user_id),
    KEY ix_outbox_pending (channel,status,available_at,id),
    KEY ix_outbox_deadline (kind,user_id,task_id,due_on),
    CONSTRAINT fk_outbox_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_outbox_project FOREIGN KEY (tenant_id,project_id) REFERENCES projects(tenant_id,id),
    CONSTRAINT fk_outbox_task FOREIGN KEY (tenant_id,project_id,task_id) REFERENCES tasks(tenant_id,project_id,id),
    CONSTRAINT fk_outbox_event FOREIGN KEY (event_id) REFERENCES activity_logs(id),
    CONSTRAINT ck_outbox_scope CHECK ((kind = 'due_today' AND tenant_id IS NULL AND project_id IS NULL AND task_id IS NULL AND due_on IS NOT NULL) OR (kind <> 'due_today' AND tenant_id IS NOT NULL AND project_id IS NOT NULL)),
    CONSTRAINT ck_outbox_actor CHECK (actor_id IS NULL OR actor_id <> user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    outbox_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    read_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_notification_delivery (outbox_id),
    KEY ix_notification_user_read (user_id,read_at,id),
    CONSTRAINT fk_notification_outbox FOREIGN KEY (outbox_id,user_id) REFERENCES notification_outbox(id,user_id)
) ENGINE=InnoDB;

CREATE TABLE push_subscriptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    endpoint TEXT NOT NULL,
    endpoint_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    public_key VARCHAR(255) NOT NULL,
    auth_token VARCHAR(255) NOT NULL,
    content_encoding VARCHAR(32) NOT NULL DEFAULT 'aes128gcm',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_push_endpoint (endpoint_hash),
    KEY ix_push_user (user_id,id),
    CONSTRAINT fk_push_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
