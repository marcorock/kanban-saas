ALTER TABLE tenant_users
    ADD COLUMN role ENUM('admin', 'member') NOT NULL DEFAULT 'member' AFTER user_id;

-- A preferência é independente do contexto ativo e só aceita um vínculo existente.
-- Antes de remover o vínculo, a aplicação deverá limpar ou substituir o padrão.
ALTER TABLE users
    ADD COLUMN default_tenant_id BIGINT UNSIGNED NULL AFTER email_verified_at,
    ADD KEY ix_users_default_tenant (default_tenant_id, id),
    ADD CONSTRAINT fk_users_default_membership FOREIGN KEY (default_tenant_id, id)
        REFERENCES tenant_users (tenant_id, user_id);

CREATE TABLE workspace_invitations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    invited_by BIGINT UNSIGNED NOT NULL,
    email VARCHAR(254) NOT NULL,
    role ENUM('admin', 'member') NOT NULL DEFAULT 'member',
    token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    expires_at DATETIME NOT NULL,
    accepted_at DATETIME NULL,
    cancelled_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_workspace_invitation_email (tenant_id, email),
    UNIQUE KEY uq_workspace_invitation_hash (token_hash),
    KEY ix_workspace_invitation_state (tenant_id, accepted_at, cancelled_at, id),
    KEY ix_workspace_invitation_expiry (expires_at),
    KEY ix_workspace_invitation_inviter (invited_by),
    CONSTRAINT fk_workspace_invitation_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id),
    CONSTRAINT fk_workspace_invitation_inviter FOREIGN KEY (invited_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
