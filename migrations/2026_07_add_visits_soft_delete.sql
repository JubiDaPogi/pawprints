-- ============================================================
-- Migration: soft-delete support for visit records
-- ------------------------------------------------------------
-- Run this ONCE against an existing PawPrints database. Fresh
-- installs from database.sql already include the column.
--
--   mysql -u <user> -p <database> < migrations/2026_07_add_visits_soft_delete.sql
--
-- Adds the deleted_at marker so a logged visit can be moved to
-- the Archive and later restored or purged, the
-- same way patients, user accounts and species already work.
-- ============================================================

ALTER TABLE visits
    ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER notes;
