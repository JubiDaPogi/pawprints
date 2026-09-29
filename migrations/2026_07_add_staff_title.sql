-- ============================================================
-- Migration: clinic-staff titles (Veterinarian / Staff)
-- ------------------------------------------------------------
-- Run this ONCE against an existing PawPrints database. Fresh
-- installs from database.sql already include the column.
--
--   mysql -u <user> -p <database> < migrations/2026_07_add_staff_title.sql
--
-- Splits the single "Clinic staff" role into two titles so a
-- staff account can be marked as a Veterinarian or as ordinary
-- Staff. The account's ACCESS level is unchanged — both are
-- still role = 'staff' — this only records their designation.
-- Pet-owner accounts leave the column NULL.
-- ============================================================

ALTER TABLE users
    ADD COLUMN staff_title ENUM('veterinarian','staff') NULL AFTER role;

-- Existing staff accounts have no title yet. Default them to
-- 'staff'; edit any that are veterinarians from the User Accounts
-- screen afterwards. (Owners are left NULL.)
UPDATE users SET staff_title = 'staff' WHERE role = 'staff' AND staff_title IS NULL;
