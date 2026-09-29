-- ============================================================
-- Migration: first-login Privacy Notice consent (per user)
-- ------------------------------------------------------------
-- Run this ONCE against an existing PawPrints database. Fresh
-- installs from database.sql already include the columns.
--
--   mysql -u <user> -p <database> < migrations/2026_07_add_user_privacy_consent.sql
--
-- Adds per-USER consent so the Privacy Notice can be shown at a
-- person's first sign-in and they agree themselves (stronger than
-- staff recording it on their behalf in owners.privacy_consent_*).
-- The stored version is compared to PRIVACY_VERSION; if you bump
-- the notice version later, everyone is re-prompted on next login.
--
-- Existing accounts are backfilled as already-consented to the
-- current version so a live clinic's users are not all locked out
-- at once. New accounts created after this migration are prompted
-- at their first sign-in. Remove the UPDATE below if you would
-- rather re-collect consent from everyone.
-- ============================================================

ALTER TABLE users
    ADD COLUMN privacy_consent_at   DATETIME    NULL AFTER security_set,
    ADD COLUMN privacy_consent_ver  VARCHAR(20) NULL AFTER privacy_consent_at;

UPDATE users
   SET privacy_consent_at = NOW(), privacy_consent_ver = '2026-01'
 WHERE privacy_consent_at IS NULL;
