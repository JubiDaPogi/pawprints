<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';
require_staff();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('../appointments.php');
verify_csrf('../appointments.php');

$pid    = (int)($_POST['patient_id'] ?? 0);
$reason = trim($_POST['reason'] ?? '');
$date   = trim($_POST['appt_date'] ?? '');
$time   = trim($_POST['appt_time'] ?? '');

if (!$pid || $date === '' || $time === '' || $reason === '') {
    set_flash('Please choose a patient, a day, a time, and a reason.');
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
    VALUES (?,?,?,?, 'Scheduled')
");
$stmt->execute([$pid, $date, $time, $reason]);

$newId = (int)$pdo->lastInsertId();
// Booked by staff = already approved, so it gets its code straight away.
$code = assign_appt_code($pdo, $newId);
$pname = $pdo->prepare("SELECT name FROM patients WHERE id = ?");
$pname->execute([$pid]);
$pname = $pname->fetchColumn();
record_audit($pdo, 'appt_create', $newId, ($pname ?: null),
    'Booked an appointment' . ($code ? ' (' . $code . ')' : '') . ' for ' . ($pname ?: 'patient #' . $pid) . ' on '
    . fmt_date($date) . ' at ' . fmt_time($time) . ' — ' . $reason);
set_flash('Appointment scheduled' . ($code ? ' — code ' . $code : '') . '.');
redirect('../appointments.php');
