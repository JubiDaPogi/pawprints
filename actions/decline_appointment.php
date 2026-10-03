<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';
require_staff();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('../appointments.php');
verify_csrf('../appointments.php');

$id     = (int)($_POST['id'] ?? 0);
$reason = trim($_POST['decline_reason'] ?? '');
if (!$id) redirect('../appointments.php');

$row = $pdo->prepare("SELECT a.*, p.name AS pname FROM appointments a JOIN patients p ON p.id = a.patient_id WHERE a.id = ?");
$row->execute([$id]);
$a = $row->fetch();
if (!$a) { set_flash('That appointment request no longer exists.'); redirect('../appointments.php'); }
if ($a['status'] !== 'Pending') { set_flash('That request has already been handled.'); redirect('../appointments.php'); }

$pdo->prepare("UPDATE appointments SET status = 'Declined', decline_reason = ? WHERE id = ?")
    ->execute([$reason !== '' ? $reason : null, $id]);

record_audit($pdo, 'appt_decline', $id, $a['pname'],
    'Declined ' . $a['pname'] . "'s appointment request for " . fmt_date($a['appt_date']) . ' at ' . fmt_time($a['appt_time'])
    . ($reason !== '' ? ' — ' . $reason : ''));

set_flash('Appointment request declined.');
redirect('../appointments.php');
