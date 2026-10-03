<?php
/* ============================================================
   A pet owner's self-service appointment request.
   Unlike actions/create_appointment.php (staff booking straight to
   'Scheduled'), this always lands as 'Pending' — staff approve or
   decline it from the Appointments screen.
   ============================================================ */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';
require_login();

$user = current_user();
if ($user['role'] !== 'owner') {
    // Staff have their own direct-scheduling form on the same page.
    redirect('../appointments.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('../appointments.php');
verify_csrf('../appointments.php');

$pid    = (int)($_POST['patient_id'] ?? 0);
$date   = trim($_POST['appt_date'] ?? '');
$time   = trim($_POST['appt_time'] ?? '');
$reason = trim($_POST['reason'] ?? '');

if (!$pid || $date === '' || $time === '' || $reason === '') {
    set_flash('Please choose a pet, a day, a time, and a reason.');
    redirect('../appointments.php');
}

// The pet must actually be this owner's — never trust the posted id.
$own = $pdo->prepare("SELECT id FROM patients WHERE id = ? AND owner_id = ? AND deleted_at IS NULL");
$own->execute([$pid, (int)$user['owner_id']]);
if (!$own->fetchColumn()) {
    set_flash('That pet is not on your account.');
    redirect('../appointments.php');
}

if (!is_bookable_date($date) || strtotime($date) < strtotime('today')) {
    set_flash('Please choose an upcoming day the clinic is open.');
    redirect('../appointments.php');
}

$slots = appointment_slots();
if (!isset($slots[$time])) {
    set_flash('Please choose one of the available time slots.');
    redirect('../appointments.php');
}

// A slot that has already started today can't be booked.
if (slot_has_started($date, $time)) {
    set_flash('The ' . $slots[$time] . ' slot has already started today — please pick a later time or another day.');
    redirect('../appointments.php');
}

// The slot can be turned off for a single date on the Schedule calendar.
if (!slot_rule_on($date, $time)['open']) {
    set_flash('The ' . $slots[$time] . ' slot isn\'t available on ' . fmt_date($date) . ' — please pick another time.');
    redirect('../appointments.php');
}

// The slot may have a per-day limit set on the Schedule page.
if (!slot_has_room($date, $time)) {
    set_flash('The ' . $slots[$time] . ' slot is already full on ' . fmt_date($date) . ' — please pick another time.');
    redirect('../appointments.php');
}

$stmt = $pdo->prepare("
    INSERT INTO appointments (patient_id, appt_date, appt_time, reason, status)
    VALUES (?,?,?,?, 'Pending')
");
$stmt->execute([$pid, $date, $time, $reason]);
$newId = (int)$pdo->lastInsertId();

$pname = $pdo->prepare("SELECT name FROM patients WHERE id = ?");
$pname->execute([$pid]);
$pname = $pname->fetchColumn();

record_audit($pdo, 'appt_request', $newId, ($pname ?: null),
    'Requested an appointment for ' . ($pname ?: 'a patient') . ' on '
    . fmt_date($date) . ' at ' . fmt_time($time) . ' — ' . $reason);

set_flash('Your appointment request has been sent — the clinic will confirm it soon.');
redirect('../appointments.php');
