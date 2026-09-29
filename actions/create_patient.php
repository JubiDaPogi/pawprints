<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';
require_staff();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('../patients.php');
verify_csrf('../patients.php');

$name = trim($_POST['name'] ?? '');
$breed = trim($_POST['breed'] ?? '');
if ($name === '' || $breed === '') {
    set_flash('Please fill in at least the pet name and breed.');
    redirect('../patients.php');
}

$stmt = $pdo->prepare("
    INSERT INTO patients (name, species, breed, sex, color, birth, owner_id, weight, temp, heart, status, allergies)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
");
$stmt->execute([
    $name,
    $_POST['species'] ?? 'Dog',
    $breed,
    $_POST['sex'] ?? 'Male',
    trim($_POST['color'] ?? ''),
    $_POST['birth'] ?: null,
    (int)($_POST['owner_id'] ?? 0),
    (float)($_POST['weight'] ?? 0),
    (float)($_POST['temp'] ?? 0),
    (int)($_POST['heart'] ?? 0),
    $_POST['status'] ?? 'Active',
    trim($_POST['allergies'] ?? 'None known'),
]);

$newId = (int)$pdo->lastInsertId();
record_audit($pdo, 'patient_create', $newId, $name,
    'Added patient ' . $name . ' (' . ($_POST['species'] ?? 'Dog') . ' · ' . $breed . ')');
set_flash($name . ' added to patient records');
redirect('../patients.php');
