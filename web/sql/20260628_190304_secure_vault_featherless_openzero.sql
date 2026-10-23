-- ZeroThink secure vault + Featherless/OpenZero provider columns
-- Date: 2026-06-28
--
-- Run manually after a database backup.
-- This migration is additive only: nullable columns, no drops, no deletes.
-- Existing plaintext key columns are left in place for backward compatibility.
-- When ZEROTHINK_VAULT_MASTER_KEY_B64 is configured, new saves write *_cipher
-- and set the matching plaintext key column to NULL.

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS api_key_featherless TEXT NULL,
  ADD COLUMN IF NOT EXISTS api_key_zerothink TEXT NULL,
  ADD COLUMN IF NOT EXISTS api_key_groq_cipher TEXT NULL,
  ADD COLUMN IF NOT EXISTS api_key_openai_cipher TEXT NULL,
  ADD COLUMN IF NOT EXISTS api_key_gemini_cipher TEXT NULL,
  ADD COLUMN IF NOT EXISTS api_key_xai_cipher TEXT NULL,
  ADD COLUMN IF NOT EXISTS api_key_nvidia_cipher TEXT NULL,
  ADD COLUMN IF NOT EXISTS api_key_quantum_cipher TEXT NULL,
  ADD COLUMN IF NOT EXISTS api_key_ibm_quantum_cipher TEXT NULL,
  ADD COLUMN IF NOT EXISTS api_key_featherless_cipher TEXT NULL,
  ADD COLUMN IF NOT EXISTS api_key_zerothink_cipher TEXT NULL,
  ADD COLUMN IF NOT EXISTS serper_api_key_cipher TEXT NULL;

-- Optional post-migration check:
-- SHOW COLUMNS FROM users LIKE 'api_key_featherless%';
-- SHOW COLUMNS FROM users LIKE 'api_key_zerothink%';
--
-- Rollback caution:
-- Do not drop these columns on live without a verified backup. They can remain
-- harmlessly even if the feature is disabled.
