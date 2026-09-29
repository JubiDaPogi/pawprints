-- ============================================================
-- Migration: soft-delete support for vaccination records
-- ------------------------------------------------------------
-- Run this ONCE against an existing PawPrints database. Fresh
-- installs from database.sql already include the column.
--
--   mysql -u <user> -p <database> < migrations/2026_07_add_vaccinations_soft_delete.sql
--
-- Adds the deleted_at marker so a logged vaccination can be moved
-- to the Archive and later restored or purged, the
-- same way visits already work.
-- ============================================================

ALTER TABLE vaccinations
    ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER vet;
