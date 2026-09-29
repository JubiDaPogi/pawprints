<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';
require_staff();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('../appointments.php');
verify_csrf('../appointments.php');

$id = (int)($_POST['id'] ?? 0);
if ($id) {
    $stmt = $pdo->prepare("UPDATE appointments SET status = 'Completed' WHERE id = ?");
    $stmt->execute([$id]);

    $pname = $pdo->prepare("SELECT p.name FROM appointments a JOIN patients p ON p.id = a.patient_id WHERE a.id = ?");
    $pname->execute([$id]);
    $pname = $pname->fetchColumn();
    record_audit($pdo, 'appt_complete', $id, ($pname ?: null),
        'Marked ' . ($pname ? $pname . '\'s' : 'an') . ' appointment as completed');
    set_flash('Appointment marked as completed');
}
redirect('../appointments.php');
