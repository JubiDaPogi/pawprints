<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';
require_staff();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('../appointments.php');
verify_csrf('../appointments.php');

$id = (int)($_POST['id'] ?? 0);
if (!$id) redirect('../appointments.php');

$row = $pdo->prepare("SELECT a.*, p.name AS pname FROM appointments a JOIN patients p ON p.id = a.patient_id WHERE a.id = ?");
$row->execute([$id]);
$a = $row->fetch();
if (!$a) { set_flash('That appointment request no longer exists.'); redirect('../appointments.php'); }
if ($a['status'] !== 'Pending') { set_flash('That request has already been handled.'); redirect('../appointments.php'); }

// The slot may have been filled by something else since the request came
// in (another approval, or a direct staff booking) — don't double-book it.
$taken = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE appt_date = ? AND appt_time = ? AND status IN ('Scheduled','Completed') AND id <> ?");
$taken->execute([$a['appt_date'], $a['appt_time'], $id]);
if ((int)$taken->fetchColumn() > 0) {
    set_flash('That time slot is already taken by another appointment — decline this request or ask the owner to pick a different time.');
    redirect('../appointments.php');
}

$pdo->prepare("UPDATE appointments SET status = 'Scheduled' WHERE id = ?")->execute([$id]);

record_audit($pdo, 'appt_approve', $id, $a['pname'],
    'Approved ' . $a['pname'] . "'s appointment request for " . fmt_date($a['appt_date']) . ' at ' . fmt_time($a['appt_time']));

set_flash('Appointment approved and scheduled.');
redirect('../appointments.php');
