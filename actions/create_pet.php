<?php
/* ============================================================
   A pet owner adding their own pet from the "My Pets" dashboard.
   Unlike actions/create_patient.php (staff, full clinical form with
   an owner picker), this is self-service: the owner can only ever
   attach the new pet to their own account, and clinical vitals
   (weight/temp/heart/status) aren't collected here — the clinic
   fills those in at the first real visit.
   ============================================================ */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';
require_login();

$user = current_user();
if ($user['role'] !== 'owner') {
    // Staff add patients from the Patients screen instead.
    redirect('../dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('../dashboard.php');
verify_csrf('../dashboard.php');

$name  = trim($_POST['name'] ?? '');
$breed = trim($_POST['breed'] ?? '');
if ($name === '' || $breed === '') {
    set_form_error('pet-new', "Please fill in at least your pet's name and breed.");
    redirect('../dashboard.php');
}

$stmt = $pdo->prepare("
    INSERT INTO patients (name, species, breed, sex, color, birth, owner_id, weight, temp, heart, status, allergies)
    VALUES (?,?,?,?,?,?,?,0,38.5,100,'Active',?)
");
$stmt->execute([
    $name,
    $_POST['species'] ?? 'Dog',
    $breed,
    $_POST['sex'] ?? 'Male',
    trim($_POST['color'] ?? ''),
    $_POST['birth'] ?: null,
    (int)$user['owner_id'],
    trim($_POST['allergies'] ?? '') ?: 'None known',
]);
$newId = (int)$pdo->lastInsertId();

record_audit($pdo, 'patient_create', $newId, $name,
    'Added pet ' . $name . ' (' . ($_POST['species'] ?? 'Dog') . ' · ' . $breed . ') — self-registered by owner');

set_flash($name . ' has been added to your pets.');
redirect('../dashboard.php');
