<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';
require_staff();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('../appointments.php');
verify_csrf('../appointments.php');

$pid = (int)($_POST['patient_id'] ?? 0);
$reason = trim($_POST['reason'] ?? '');
if (!$pid || $reason === '') {
    set_flash('Please choose a patient and enter a reason.');
    redirect('../appointments.php');
}

$stmt = $pdo->prepare("
    INSERT INTO appointments (patient_id, appt_date, appt_time, reason, status)
    VALUES (?,?,?,?, 'Scheduled')
");
$stmt->execute([
    $pid,
    $_POST['appt_date'] ?: date('Y-m-d'),
    $_POST['appt_time'] ?: '10:00:00',
    $reason,
]);

$newId = (int)$pdo->lastInsertId();
$pname = $pdo->prepare("SELECT name FROM patients WHERE id = ?");
$pname->execute([$pid]);
$pname = $pname->fetchColumn();
record_audit($pdo, 'appt_create', $newId, ($pname ?: null),
    'Booked an appointment for ' . ($pname ?: 'patient #' . $pid) . ' on '
    . fmt_date($_POST['appt_date'] ?: date('Y-m-d')) . ' — ' . $reason);
set_flash('Appointment scheduled');
redirect('../appointments.php');
