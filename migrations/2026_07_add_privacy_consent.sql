-- ============================================================
-- Migration: Data Privacy Act (RA 10173) consent tracking
-- ------------------------------------------------------------
-- Run this ONCE against an existing PawPrints database. Fresh
-- installs from database.sql already include the columns.
--
--   mysql -u <user> -p <database> < migrations/2026_07_add_privacy_consent.sql
--
-- Records, per client (owner), that they agreed to the clinic's
-- Privacy Notice and which version of it. The account-creation
-- form captures this going forward; existing clients are marked
-- below as having consented to the first notice version so their
-- records don't show as "consent not recorded". Adjust or clear
-- these if that isn't accurate for your existing clients.
-- ============================================================

ALTER TABLE owners
    ADD COLUMN privacy_consent_at   DATETIME    NULL AFTER address,
    ADD COLUMN privacy_consent_ver  VARCHAR(20) NULL AFTER privacy_consent_at;

-- Backfill existing clients as consenting to the initial notice.
-- Remove or edit this line if you'd rather re-collect consent.
UPDATE owners
   SET privacy_consent_at = NOW(), privacy_consent_ver = '2026-01'
 WHERE privacy_consent_at IS NULL;
