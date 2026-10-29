CREATE TABLE auth_rate_limits (
    bucket CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
    attempts INT UNSIGNED NOT NULL,
    expires_at DATETIME NOT NULL,
    KEY ix_auth_rate_limits_expiry (expires_at)
) ENGINE=InnoDB;
