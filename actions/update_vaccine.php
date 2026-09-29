<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';
require_staff();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('../patients.php');
verify_csrf('../patients.php');

$id   = (int)($_POST['id'] ?? 0);
$pid  = (int)($_POST['patient_id'] ?? 0);
$name = trim($_POST['name'] ?? '');

if (!$id || !$pid || $name === '') {
    set_flash('A vaccination needs at least a vaccine name.');
    redirect('../patient.php?id=' . $pid . '#vacc');
}

// Make sure the vaccination exists and belongs to the patient we were
// handed. Grab the patient name for the audit trail while we're here.
$check = $pdo->prepare(
    "SELECT p.name FROM vaccinations vc JOIN patients p ON p.id = vc.patient_id
     WHERE vc.id = ? AND vc.patient_id = ? AND vc.deleted_at IS NULL"
);
$check->execute([$id, $pid]);
$pname = $check->fetchColumn();
if ($pname === false) {
    set_flash('That vaccination record could not be found.');
    redirect('../patient.php?id=' . $pid . '#vacc');
}

$stmt = $pdo->prepare("
    UPDATE vaccinations
       SET name = ?, date_given = ?, next_due = ?, vet = ?
     WHERE id = ? AND patient_id = ?
");
$stmt->execute([
    $name,
    $_POST['date_given'] ?: date('Y-m-d'),
    $_POST['next_due'] ?: null,
    // Saved exactly as edited (may be blank if cleared).
    trim($_POST['vet'] ?? ''),
    $id,
    $pid,
]);

set_flash('Vaccination updated');
record_audit($pdo, 'vaccine_update', $id, ($pname ?: null),
    'Edited ' . $name . ' vaccine for ' . ($pname ?: 'patient #' . $pid));
redirect('../patient.php?id=' . $pid . '#vacc');
