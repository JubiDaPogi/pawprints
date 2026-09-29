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

if ($id && $pid) {
    // Pull the vaccine (and its patient's name) for the audit trail before
    // we touch it. Scope to the patient so a stray id can't remove another
    // pet's vaccination.
    $stmt = $pdo->prepare(
        "SELECT vc.name, vc.date_given, p.name AS patient_name
         FROM vaccinations vc JOIN patients p ON p.id = vc.patient_id
         WHERE vc.id = ? AND vc.patient_id = ? AND vc.deleted_at IS NULL"
    );
    $stmt->execute([$id, $pid]);
    $v = $stmt->fetch();

    if ($v) {
        // Soft delete: the row stays so the vaccination can be restored
        // from the Archive or permanently removed later,
        // the same way visits already work.
        $pdo->prepare("UPDATE vaccinations SET deleted_at = NOW() WHERE id = ? AND patient_id = ?")
            ->execute([$id, $pid]);

        record_audit($pdo, 'vaccine_delete', $id, $v['patient_name'],
            'Moved ' . $v['patient_name'] . '\'s ' . $v['name'] . ' vaccine (' . fmt_date($v['date_given']) . ') to the Archive');
        set_flash('Vaccination moved to Archive');
    } else {
        set_flash('That vaccination record could not be found.');
    }
}
redirect('../patient.php?id=' . $pid . '#vacc');
