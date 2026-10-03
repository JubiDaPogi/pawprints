<?php
/* Pet owner cancels one of their own appointments (Pending or Scheduled,
   today or later). The appointment keeps its code — codes are never
   reused — and its place in the time slot is freed for someone else. */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || is_staff()) redirect('../appointments.php');
verify_csrf('../appointments.php');

$user   = current_user();
$id     = (int)($_POST['id'] ?? 0);
$reason = mb_substr(trim($_POST['cancel_reason'] ?? ''), 0, 255);
if (!$id) redirect('../appointments.php');

// Only the owner's own pet's appointment — never trust the posted id.
$row = $pdo->prepare("SELECT a.*, p.name AS pname FROM appointments a JOIN patients p ON p.id = a.patient_id
                      WHERE a.id = ? AND p.owner_id = ? AND p.deleted_at IS NULL");
$row->execute([$id, (int)$user['owner_id']]);
$a = $row->fetch();
if (!$a) { set_flash('That appointment is not on your account.'); redirect('../appointments.php'); }
if (!in_array($a['status'], ['Pending', 'Scheduled'], true)) { set_flash('That appointment can no longer be cancelled.'); redirect('../appointments.php'); }
if ($a['appt_date'] < date('Y-m-d')) { set_flash('Past appointments can\'t be cancelled.'); redirect('../appointments.php'); }

$pdo->prepare("UPDATE appointments SET status = 'Cancelled', cancel_reason = ?, cancelled_at = NOW() WHERE id = ?")
    ->execute([$reason !== '' ? $reason : null, $id]);

$code = $a['appt_code'] ? ' (' . $a['appt_code'] . ')' : '';
record_audit($pdo, 'appt_cancel', $id, $a['pname'],
    'Cancelled ' . $a['pname'] . "'s appointment" . $code . ' for ' . fmt_date($a['appt_date']) . ' at ' . fmt_time($a['appt_time'])
    . ($reason !== '' ? ' — ' . $reason : ''));

set_flash('Your appointment' . $code . ' on ' . fmt_date($a['appt_date']) . ' has been cancelled.');
redirect('../appointments.php');
