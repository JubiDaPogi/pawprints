-- ============================================================
-- Paw Prints Veterinary Clinic — Web-Based Patient Tracking System
-- Database schema + seed data (MySQL / MariaDB, for XAMPP)
-- ------------------------------------------------------------
-- How to load:
--   1. Start Apache + MySQL in the XAMPP Control Panel.
--   2. Open http://localhost/phpmyadmin
--   3. Click the "Import" tab, choose this file, and run it.
--      (Or on the SQL tab, paste the whole file and press Go.)
-- This will DROP and recreate the database each time you import,
-- so you always get a clean copy with the sample data.
-- ============================================================

DROP DATABASE IF EXISTS pawprints_db;
CREATE DATABASE pawprints_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pawprints_db;

-- ------------------------------------------------------------
-- USERS  (login accounts)
--   role = 'staff'  -> clinic staff: full access, incl. user management
--   role = 'owner'  -> pet owner: own pets + own account only
--   staff_title     -> for staff only, distinguishes 'veterinarian'
--                      from ordinary 'staff'. NULL for pet owners.
-- Account-management columns:
--   is_active            -> deactivated accounts cannot log in
--   can_manage_users     -> extra permission that lets an OWNER into the
--                           user-management module (staff always may).
--                           This is the "explicitly granted permission".
--   must_change_password -> force a password change at next login
--   security_question/_answer -> account-recovery security setting
--                                (the answer is stored hashed)
-- ------------------------------------------------------------
CREATE TABLE users (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    password             VARCHAR(255) NOT NULL,        -- PHP password_hash()
    role                 ENUM('staff','owner') NOT NULL,
    staff_title          ENUM('veterinarian','staff') NULL,  -- staff only; NULL for owners
    first_name           VARCHAR(60)  NOT NULL,
    middle_name          VARCHAR(60)  NOT NULL,
    last_name            VARCHAR(60)  NOT NULL,
    email                VARCHAR(120) NOT NULL UNIQUE,   -- this is the login identifier
    phone                VARCHAR(40)  NULL,
    owner_id             INT NULL,                       -- set only for owner accounts
    is_active            TINYINT(1) NOT NULL DEFAULT 1,
    can_manage_users     TINYINT(1) NOT NULL DEFAULT 0,
    deleted_at           DATETIME NULL,      -- soft delete: recoverable
    must_change_password TINYINT(1) NOT NULL DEFAULT 0,
    -- Three security questions, answered at first sign-in and used for
    -- account recovery. Answers are hashed, never stored in plain text.
    security_q1          VARCHAR(200) NULL,
    security_a1          VARCHAR(255) NULL,
    security_q2          VARCHAR(200) NULL,
    security_a2          VARCHAR(255) NULL,
    security_q3          VARCHAR(200) NULL,
    security_a3          VARCHAR(255) NULL,
    security_set         TINYINT(1) NOT NULL DEFAULT 0, -- 0 = must set them up
    -- Data Privacy Act consent captured by the PERSON at first sign-in
    -- (distinct from owners.privacy_consent_*, which staff record on the
    -- client's behalf). Stores which notice version they agreed to; if it
    -- differs from the current PRIVACY_VERSION, they are re-prompted.
    privacy_consent_at   DATETIME     NULL,
    privacy_consent_ver  VARCHAR(20)  NULL,
    last_login           DATETIME NULL,
    -- When this person last opened the notification bell; activity newer
    -- than this counts as unread on the badge.
    notifications_seen_at DATETIME NULL,
    created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- ACTIVITY_LOG  (security trail for account-related actions)
-- actor_id is intentionally NOT a foreign key, and the username is
-- snapshotted, so the trail survives even if the user is deleted.
-- ------------------------------------------------------------
-- ------------------------------------------------------------
-- REMINDERS
-- One row per reminder the system decides to send:
--   'email' — the day BEFORE the appointment
--   'sms'   — on the morning OF the appointment
-- Rows are queued by the reminder runner and marked 'sent' once the
-- channel accepts them, so a reminder can never go out twice.
-- ------------------------------------------------------------
CREATE TABLE reminders (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    channel        ENUM('email','sms') NOT NULL,
    send_on        DATE NOT NULL,
    recipient      VARCHAR(160) NOT NULL,
    body           TEXT NOT NULL,
    status         ENUM('pending','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
    error          VARCHAR(255) NULL,
    sent_at        DATETIME NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_reminder (appointment_id, channel),
    INDEX idx_due (status, send_on)
) ENGINE=InnoDB;

CREATE TABLE activity_log (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    actor_id       INT NULL,
    actor_username VARCHAR(60) NULL,
    action         VARCHAR(40) NOT NULL,
    target_id      INT NULL,
    target_label   VARCHAR(120) NULL,
    details        VARCHAR(255) NULL,
    ip_address     VARCHAR(45) NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_activity_created (created_at),
    INDEX idx_activity_actor (actor_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- OWNERS  (pet owners / clients)
-- ------------------------------------------------------------
CREATE TABLE owners (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    first_name   VARCHAR(60)  NOT NULL DEFAULT '',
    middle_name  VARCHAR(60)  NOT NULL,
    last_name    VARCHAR(60)  NOT NULL,
    phone    VARCHAR(40),
    email    VARCHAR(120),
    address  VARCHAR(200),
    -- Data Privacy Act (RA 10173) consent: when the client agreed to the
    -- Privacy Notice and which version of it. NULL = not yet recorded.
    privacy_consent_at   DATETIME     NULL,
    privacy_consent_ver  VARCHAR(20)  NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- SPECIES  (managed by clinic staff from the Patients screen)
-- ------------------------------------------------------------
CREATE TABLE species (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(40) NOT NULL UNIQUE,
    deleted_at DATETIME NULL,                  -- soft delete: recoverable
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- PATIENTS  (the pets)
-- ------------------------------------------------------------
CREATE TABLE patients (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(80)  NOT NULL,
    species    VARCHAR(30)  NOT NULL,
    breed      VARCHAR(60),
    sex        ENUM('Male','Female') DEFAULT 'Male',
    color      VARCHAR(60),
    birth      DATE,
    owner_id   INT NOT NULL,
    weight     DECIMAL(6,2) DEFAULT 0,
    temp       DECIMAL(4,1) DEFAULT 0,
    heart      INT DEFAULT 0,
    status     ENUM('Active','Under Treatment') DEFAULT 'Active',
    allergies  VARCHAR(160) DEFAULT 'None known',
    deleted_at DATETIME NULL,                  -- soft delete: recoverable
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES owners(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- VISITS  (each check-up: reason, diagnosis, treatment, notes)
-- ------------------------------------------------------------
CREATE TABLE visits (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    visit_date DATE NOT NULL,
    reason     VARCHAR(200),
    diagnosis  TEXT,
    treatment  TEXT,
    vet        VARCHAR(80),
    notes      TEXT,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- VACCINATIONS
-- ------------------------------------------------------------
CREATE TABLE vaccinations (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    name       VARCHAR(80) NOT NULL,
    date_given DATE NOT NULL,
    next_due   DATE,
    vet        VARCHAR(80),
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- APPOINTMENTS
-- ------------------------------------------------------------
CREATE TABLE appointments (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    patient_id  INT NOT NULL,
    appt_date   DATE NOT NULL,
    appt_time   TIME,
    reason      VARCHAR(200),
    -- 'Pending' = requested by a pet owner, awaiting staff approval.
    -- 'Declined' = staff turned down a pending request.
    status          ENUM('Pending','Scheduled','Completed','Declined') DEFAULT 'Scheduled',
    -- Staff's reason/remarks when declining a request — shown back to the
    -- owner so they know why, and kept for the clinic's own record.
    decline_reason  VARCHAR(255) NULL,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- APPOINTMENT SCHEDULE  (managed by staff on the Schedule screen)
-- ------------------------------------------------------------
-- Bookable hour slots. Appointments store their own time, so editing
-- or deleting a slot never changes an appointment already booked.
CREATE TABLE appt_time_slots (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    start_time  TIME NOT NULL UNIQUE,
    end_time    TIME NOT NULL,
    -- How many appointments this slot takes per day. NULL = no limit.
    -- Pending requests count toward it (they hold a place until decided).
    capacity    INT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Which days of the week take bookings. dow: 1 = Monday ... 7 = Sunday.
CREATE TABLE appt_weekdays (
    dow      TINYINT PRIMARY KEY,
    is_open  TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- Date-specific exceptions that override the weekly pattern: a holiday
-- (is_open = 0) or a one-off open day (is_open = 1).
CREATE TABLE appt_special_dates (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    the_date   DATE NOT NULL UNIQUE,
    is_open    TINYINT(1) NOT NULL DEFAULT 0,
    note       VARCHAR(150) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Per-date changes to individual slots, set from the Schedule calendar:
-- a row turns one slot off for that date, or gives it a different number
-- of places (capacity NULL = no limit). No row = the slot's usual setting.
CREATE TABLE appt_day_slots (
    the_date   DATE NOT NULL,
    start_time TIME NOT NULL,
    is_open    TINYINT(1) NOT NULL DEFAULT 1,
    capacity   INT NULL,
    PRIMARY KEY (the_date, start_time)
) ENGINE=InnoDB;

-- ============================================================
-- SEED DATA
-- ============================================================

-- Owners ------------------------------------------------------
INSERT INTO owners (id, first_name, middle_name, last_name, phone, email, address, privacy_consent_at, privacy_consent_ver) VALUES
(1, 'Maria', 'Cruz',    'Santos',    '0917-555-0142', 'maria.santos@email.com', 'Bantug, Roxas, Isabela',    NOW(), '2026-01'),
(2, 'Jose',  'Ramos',   'Dela Cruz', '0918-555-0198', 'jose.dc@email.com',      'Rang-ayan, Roxas, Isabela', NOW(), '2026-01'),
(3, 'Ana',   'Bautista','Reyes',     '0920-555-0176', 'ana.reyes@email.com',    'Poblacion, Roxas, Isabela', NOW(), '2026-01'),
(4, 'Ramon', 'Bautista', 'Aquino',    '0915-555-0121', 'ramon.a@email.com',      'Bantug, Roxas, Isabela',    NOW(), '2026-01');

-- Species -----------------------------------------------------
INSERT INTO species (name) VALUES
('Dog'), ('Cat'), ('Bird'), ('Rabbit');

-- Default schedule: Mon–Sat, 9 AM–4 PM in hour slots, closed 12–1 PM.
INSERT INTO appt_time_slots (start_time, end_time) VALUES
('09:00:00','10:00:00'), ('10:00:00','11:00:00'), ('11:00:00','12:00:00'),
('13:00:00','14:00:00'), ('14:00:00','15:00:00'), ('15:00:00','16:00:00');
INSERT INTO appt_weekdays (dow, is_open) VALUES
(1,1), (2,1), (3,1), (4,1), (5,1), (6,1), (7,0);

-- Patients ----------------------------------------------------
INSERT INTO patients (id, name, species, breed, sex, color, birth, owner_id, weight, temp, heart, status, allergies) VALUES
(1, 'Bruno', 'Dog',  'Aspin',     'Male',   'Brown/White', '2021-03-14', 1, 18.4, 38.6,  92, 'Active',          'None known'),
(2, 'Milo',  'Cat',  'Puspin',    'Male',   'Orange Tabby','2022-08-01', 2,  4.2, 38.9, 160, 'Active',          'None known'),
(3, 'Luna',  'Dog',  'Shih Tzu',  'Female', 'White/Gold',  '2020-11-22', 3,  6.1, 39.1, 110, 'Under Treatment', 'Chicken protein'),
(4, 'Kiko',  'Bird', 'Cockatiel', 'Male',   'Grey/Yellow', '2023-01-30', 4, 0.09, 41.5, 340, 'Active',          'None known'),
(5, 'Bella', 'Cat',  'Persian',   'Female', 'Cream',       '2019-06-18', 1,  3.8, 38.5, 155, 'Active',          'None known');

-- Vaccinations ------------------------------------------------
INSERT INTO vaccinations (patient_id, name, date_given, next_due, vet) VALUES
(1, 'Anti-Rabies',        '2026-05-10', '2027-05-10', 'Dr. Lomibao'),
(1, '5-in-1 (DHPPiL)',    '2026-02-18', '2027-02-18', 'Dr. Lomibao'),
(2, 'Anti-Rabies',        '2026-06-01', '2027-06-01', 'Dr. Lomibao'),
(2, '4-in-1 (FVRCP+C)',   '2026-06-01', '2027-06-01', 'Dr. Lomibao'),
(3, 'Anti-Rabies',        '2026-04-12', '2027-04-12', 'Dr. Lomibao'),
(5, 'Anti-Rabies',        '2026-03-05', '2027-03-05', 'Dr. Lomibao'),
(5, '4-in-1 (FVRCP+C)',   '2026-03-05', '2027-03-05', 'Dr. Lomibao');

-- Visits ------------------------------------------------------
INSERT INTO visits (patient_id, visit_date, reason, diagnosis, treatment, vet, notes) VALUES
(1, '2026-07-02', 'Annual check-up + vaccination', 'Healthy; mild tartar buildup', 'Anti-rabies booster; dental cleaning recommended', 'Dr. Lomibao', 'Weight up 0.6kg from last visit. Advise diet control.'),
(1, '2026-02-18', 'Skin irritation, left flank',   'Flea allergy dermatitis',      'Topical anti-parasitic; medicated shampoo weekly x4', 'Dr. Lomibao', 'Owner to keep bedding clean. Recheck in 1 month.'),
(2, '2026-06-01', 'First visit + vaccination',     'Healthy kitten',               'Core vaccines administered; deworming', 'Dr. Lomibao', 'Scheduled for neuter next month.'),
(3, '2026-07-15', 'Vomiting, loss of appetite (2 days)', 'Acute gastroenteritis',  'IV fluids; anti-emetic; bland diet 5 days', 'Dr. Lomibao', 'Recheck in 3 days. Withhold chicken-based food.'),
(3, '2026-04-12', 'Routine vaccination',           'Healthy',                      'Anti-rabies booster', 'Dr. Lomibao', ''),
(4, '2026-05-20', 'Feather plucking',              'Behavioral stress / possible boredom', 'Environmental enrichment plan; recheck', 'Dr. Lomibao', 'Advise more social interaction & toys.'),
(5, '2026-03-05', 'Annual check-up',               'Healthy; recommend regular grooming', 'Vaccines updated; nail trim', 'Dr. Lomibao', 'Long-coat maintenance discussed.');

-- Appointments ------------------------------------------------
INSERT INTO appointments (patient_id, appt_date, appt_time, reason, status) VALUES
(3, '2026-07-23', '10:00:00', 'Gastroenteritis recheck', 'Scheduled'),
(2, '2026-07-25', '14:00:00', 'Neuter procedure',        'Scheduled'),
(1, '2026-08-02', '09:30:00', 'Dental cleaning',         'Scheduled'),
(4, '2026-07-18', '11:00:00', 'Feather-plucking recheck','Completed'),
(5, '2026-07-28', '15:30:00', 'Grooming & wellness',     'Scheduled');

-- ------------------------------------------------------------
-- USERS
-- Default login credentials:
--   Staff  ->  username: admin      password: admin123   (full access)
--   Owners ->  username: maria / jose / ana / ramon
--              password (all four): owner123             (own account + pets)
--
-- NOTE ON PASSWORDS: they are seeded here with a "plain:" marker
-- so the accounts work the moment you import this file. The first
-- time each account logs in, the app automatically re-saves the
-- password as a secure bcrypt hash (PHP password_hash). Any new
-- account you create through the app is hashed from the start.
--
-- All seed accounts are active.
--
-- Seed accounts have security_set = 0, so each demo login is asked to
-- choose its three recovery questions on first sign-in — which is
-- exactly the flow a new clinic account goes through.
--
-- can_manage_users = 1 means the account may open User Accounts and
-- the Activity Log. The seeded staff account has it. A staff member can
-- switch it OFF for another staff account to create a limited
-- 'assistant' login: full clinic workspace, no admin features. Owners
-- start at 0 and only get it if explicitly granted.
-- ------------------------------------------------------------
INSERT INTO users (password, role, staff_title, first_name, middle_name, last_name, email, phone, owner_id, is_active, can_manage_users) VALUES
('plain:admin123', 'staff', 'veterinarian', 'Elena', 'Marquez',  'Lomibao',   'clinic@pawprints.vet',   '0917-555-0100', NULL, 1, 1),
('plain:owner123', 'owner', NULL,           'Maria', 'Cruz',     'Santos',    'maria.santos@email.com', '0917-555-0142', 1,    1, 0),
('plain:owner123', 'owner', NULL,           'Jose',  'Ramos',    'Dela Cruz', 'jose.dc@email.com',      '0918-555-0198', 2,    1, 0),
('plain:owner123', 'owner', NULL,           'Ana',   'Bautista', 'Reyes',     'ana.reyes@email.com',    '0920-555-0176', 3,    1, 0),
('plain:owner123', 'owner', NULL,           'Ramon', 'Bautista',  'Aquino',    'ramon.a@email.com',      '0915-555-0121', 4,    1, 0);

-- Activity log ------------------------------------------------
-- A few starter entries so the Activity Log screen isn't empty on
-- first run. Real entries are appended automatically as accounts
-- are used and managed.
INSERT INTO activity_log (actor_id, actor_username, action, target_id, target_label, details, ip_address, created_at) VALUES
(1, 'admin', 'account_seeded', 1, 'admin', 'Initial staff account created during setup', '127.0.0.1', NOW() - INTERVAL 2 DAY),
(1, 'admin', 'account_seeded', 2, 'maria', 'Owner account created during setup',         '127.0.0.1', NOW() - INTERVAL 2 DAY),
(1, 'admin', 'account_seeded', 3, 'jose',  'Owner account created during setup',         '127.0.0.1', NOW() - INTERVAL 2 DAY);
