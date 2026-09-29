<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';
require_staff();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('../patients.php');
verify_csrf('../patients.php');

$id  = (int)($_POST['id'] ?? 0);
$pid = (int)($_POST['patient_id'] ?? 0);
$reason = trim($_POST['reason'] ?? '');
$diagnosis = trim($_POST['diagnosis'] ?? '');

if (!$id || !$pid || $reason === '' || $diagnosis === '') {
    set_flash('A visit needs at least a reason and a diagnosis.');
    redirect('../patient.php?id=' . $pid);
}

// Make sure the visit exists, isn't archived, and belongs to the patient
// we were handed. Grab the patient name for the audit trail while we're here.
$check = $pdo->prepare(
    "SELECT p.name FROM visits v JOIN patients p ON p.id = v.patient_id
     WHERE v.id = ? AND v.patient_id = ? AND v.deleted_at IS NULL"
);
$check->execute([$id, $pid]);
$pname = $check->fetchColumn();
if ($pname === false) {
    set_flash('That visit could not be found.');
    redirect('../patient.php?id=' . $pid);
}

$stmt = $pdo->prepare("
    UPDATE visits
       SET visit_date = ?, reason = ?, diagnosis = ?, treatment = ?, vet = ?, notes = ?
     WHERE id = ? AND patient_id = ?
");
$stmt->execute([
    $_POST['visit_date'] ?: date('Y-m-d'),
    $reason,
    $diagnosis,
    trim($_POST['treatment'] ?? ''),
    // Saved exactly as edited (may be blank if cleared).
    trim($_POST['vet'] ?? ''),
    trim($_POST['notes'] ?? ''),
    $id,
    $pid,
]);

set_flash('Visit / diagnosis updated');
record_audit($pdo, 'visit_update', $id, ($pname ?: null),
    'Edited a visit for ' . ($pname ?: 'patient #' . $pid) . ' (' . fmt_date($_POST['visit_date'] ?: date('Y-m-d')) . ')');
redirect('../patient.php?id=' . $pid);
