-- ZeroThink account API tokens
-- Date: 2026-06-28
--
-- Run manually after a database backup.
-- This migration is additive only: one new table, no drops, no deletes.
-- API keys are stored as SHA-256 hashes of high-entropy ztapi_* tokens.
-- Plaintext keys are shown once at creation time and are not stored.

CREATE TABLE IF NOT EXISTS zerothink_api_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token_hash CHAR(64) NOT NULL,
  token_hint VARCHAR(32) NOT NULL,
  label VARCHAR(120) NULL,
  scopes VARCHAR(255) NULL,
  client_meta TEXT NULL,
  created_by_user_id INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_used_at DATETIME NULL,
  expires_at DATETIME NULL,
  revoked_at DATETIME NULL,
  revoked_by_user_id INT NULL,
  revoke_reason VARCHAR(255) NULL,
  UNIQUE KEY uniq_zerothink_api_token_hash (token_hash),
  KEY idx_zerothink_api_tokens_user (user_id),
  KEY idx_zerothink_api_tokens_active (revoked_at, expires_at),
  KEY idx_zerothink_api_tokens_created_by (created_by_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Optional post-migration check:
-- SHOW TABLES LIKE 'zerothink_api_tokens';
--
-- Rollback caution:
-- Do not drop this table on live without a verified backup, because active
-- API access keys will be lost.
