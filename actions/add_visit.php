<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';
require_staff();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('../patients.php');
verify_csrf('../patients.php');

$pid = (int)($_POST['patient_id'] ?? 0);
$reason = trim($_POST['reason'] ?? '');
$diagnosis = trim($_POST['diagnosis'] ?? '');

if (!$pid || $reason === '' || $diagnosis === '') {
    set_flash('A visit needs at least a reason and a diagnosis.');
    redirect('../patient.php?id=' . $pid);
}

$stmt = $pdo->prepare("
    INSERT INTO visits (patient_id, visit_date, reason, diagnosis, treatment, vet, notes)
    VALUES (?,?,?,?,?,?,?)
");
$stmt->execute([
    $pid,
    $_POST['visit_date'] ?: date('Y-m-d'),
    $reason,
    $diagnosis,
    trim($_POST['treatment'] ?? ''),
    // Kept exactly as entered: a veterinarian's form pre-fills their name,
    // while a staff member may leave it blank or type the attending vet.
    trim($_POST['vet'] ?? ''),
    trim($_POST['notes'] ?? ''),
]);

$newId = (int)$pdo->lastInsertId();
$pname = $pdo->prepare("SELECT name FROM patients WHERE id = ?");
$pname->execute([$pid]);
$pname = $pname->fetchColumn();
record_audit($pdo, 'visit_create', $newId, ($pname ?: null),
    'Logged a visit for ' . ($pname ?: 'patient #' . $pid) . ' — ' . $reason);
set_flash('Visit / diagnosis logged');
redirect('../patient.php?id=' . $pid);
