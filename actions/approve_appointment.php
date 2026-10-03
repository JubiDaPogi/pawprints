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

// A pending request already holds its place, so approving normally can't
// overfill a slot — but if the slot's limit was lowered after the request
// came in, the others may already take every place.
if (!slot_has_room($a['appt_date'], $a['appt_time'], $id)) {
    set_flash('That slot is already full on ' . fmt_date($a['appt_date']) . ' — its limit was lowered after this request was made. Decline it or raise the limit on the Schedule page.');
    redirect('../appointments.php');
}

$pdo->prepare("UPDATE appointments SET status = 'Scheduled' WHERE id = ?")->execute([$id]);

record_audit($pdo, 'appt_approve', $id, $a['pname'],
    'Approved ' . $a['pname'] . "'s appointment request for " . fmt_date($a['appt_date']) . ' at ' . fmt_time($a['appt_time']));

set_flash('Appointment approved and scheduled.');
redirect('../appointments.php');
