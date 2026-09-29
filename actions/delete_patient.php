<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';
require_staff();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('../patients.php');
verify_csrf('../patients.php');

$id = (int)($_POST['id'] ?? 0);
if ($id) {
    $stmt = $pdo->prepare("SELECT name FROM patients WHERE id = ?");
    $stmt->execute([$id]);
    $name = $stmt->fetchColumn();

    // ON DELETE CASCADE removes visits, vaccinations, and appointments too.
    // Soft delete: the row stays so it (and its visits, vaccinations
// and appointments) can be restored from the Archive.
$del = $pdo->prepare("UPDATE patients SET deleted_at = NOW() WHERE id = ?");
    $del->execute([$id]);

// Stop any reminders that haven't gone out yet — the owner shouldn't be
// texted about a pet whose record was just removed. (send_pending()
// also re-checks, so this is belt and braces.)
$pdo->prepare(
    "UPDATE reminders r
     JOIN appointments a ON a.id = r.appointment_id
     SET r.status = 'cancelled', r.error = 'Patient record was deleted', r.sent_at = NOW()
     WHERE a.patient_id = ? AND r.status = 'pending'"
)->execute([$id]);

    set_flash(($name ?: 'Patient') . ' record removed');
    record_audit($pdo, 'patient_delete', $id, ($name ?: null),
        'Moved patient ' . ($name ?: '#' . $id) . ' to the Archive (visits, vaccinations and appointments kept)');
}
redirect('../patients.php');
