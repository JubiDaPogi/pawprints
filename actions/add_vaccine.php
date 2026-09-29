<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';
require_staff();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('../patients.php');
verify_csrf('../patients.php');

$pid = (int)($_POST['patient_id'] ?? 0);
if (!$pid) redirect('../patients.php');

$stmt = $pdo->prepare("
    INSERT INTO vaccinations (patient_id, name, date_given, next_due, vet)
    VALUES (?,?,?,?,?)
");
$stmt->execute([
    $pid,
    trim($_POST['name'] ?? 'Anti-Rabies'),
    $_POST['date_given'] ?: date('Y-m-d'),
    $_POST['next_due'] ?: null,
    // Kept as entered — a veterinarian pre-fills their name; a staff member
    // may leave it blank or type whoever administered the vaccine.
    trim($_POST['vet'] ?? ''),
]);

$vname = trim($_POST['name'] ?? 'Anti-Rabies');
$pname = $pdo->prepare("SELECT name FROM patients WHERE id = ?");
$pname->execute([$pid]);
$pname = $pname->fetchColumn();
record_audit($pdo, 'vaccine_add', (int)$pdo->lastInsertId(), ($pname ?: null),
    'Recorded ' . $vname . ' vaccine for ' . ($pname ?: 'patient #' . $pid));
set_flash('Vaccination recorded');
redirect('../patient.php?id=' . $pid . '#vacc');
