<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';
require_staff();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('../patients.php');
verify_csrf('../patients.php');

$id = (int)($_POST['id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$breed = trim($_POST['breed'] ?? '');
if (!$id || $name === '' || $breed === '') {
    set_flash('Please fill in at least the pet name and breed.');
    redirect('../patients.php');
}

// Keep owner unchanged if the field wasn't submitted (edit-from-chart hides it).
if (isset($_POST['owner_id'])) {
    $stmt = $pdo->prepare("
        UPDATE patients SET name=?, species=?, breed=?, sex=?, color=?, birth=?, owner_id=?,
               weight=?, temp=?, heart=?, status=?, allergies=? WHERE id=?
    ");
    $stmt->execute([
        $name, $_POST['species'] ?? 'Dog', $breed, $_POST['sex'] ?? 'Male',
        trim($_POST['color'] ?? ''), $_POST['birth'] ?: null, (int)$_POST['owner_id'],
        (float)($_POST['weight'] ?? 0), (float)($_POST['temp'] ?? 0), (int)($_POST['heart'] ?? 0),
        $_POST['status'] ?? 'Active', trim($_POST['allergies'] ?? 'None known'), $id,
    ]);
} else {
    $stmt = $pdo->prepare("
        UPDATE patients SET name=?, species=?, breed=?, sex=?, color=?, birth=?,
               weight=?, temp=?, heart=?, status=?, allergies=? WHERE id=?
    ");
    $stmt->execute([
        $name, $_POST['species'] ?? 'Dog', $breed, $_POST['sex'] ?? 'Male',
        trim($_POST['color'] ?? ''), $_POST['birth'] ?: null,
        (float)($_POST['weight'] ?? 0), (float)($_POST['temp'] ?? 0), (int)($_POST['heart'] ?? 0),
        $_POST['status'] ?? 'Active', trim($_POST['allergies'] ?? 'None known'), $id,
    ]);
}

set_flash($name . "'s record updated");
record_audit($pdo, 'patient_update', $id, $name, 'Updated patient record for ' . $name);
redirect('../patient.php?id=' . $id);
