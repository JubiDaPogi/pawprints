-- ============================================================
-- Migration: rename audit_log -> activity_log
-- ------------------------------------------------------------
-- Run this ONCE against an existing PawPrints database. Fresh
-- installs from database.sql already use the new name.
--
--   mysql -u <user> -p <database> < migrations/2026_07_rename_audit_log_to_activity_log.sql
--
-- The screen was renamed from "Audit Log" to "Activity Log". This
-- renames the underlying table (and its indexes) to match so the
-- existing security trail is preserved rather than recreated. No
-- rows are touched; only the table and index names change.
-- ============================================================

RENAME TABLE audit_log TO activity_log;

ALTER TABLE activity_log
    RENAME INDEX idx_audit_created TO idx_activity_created,
    RENAME INDEX idx_audit_actor   TO idx_activity_actor;
