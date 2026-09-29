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
    // Pull the visit (and its patient's name) for the audit trail before
    // we touch it. Scope to the patient so a stray id can't move another
    // pet's visit to the Archive.
    $stmt = $pdo->prepare(
        "SELECT v.visit_date, v.reason, p.name AS patient_name
         FROM visits v JOIN patients p ON p.id = v.patient_id
         WHERE v.id = ? AND v.patient_id = ? AND v.deleted_at IS NULL"
    );
    $stmt->execute([$id, $pid]);
    $v = $stmt->fetch();

    if ($v) {
        // Soft delete: the row stays so the visit can be restored from
        // the Archive or permanently removed later.
        $pdo->prepare("UPDATE visits SET deleted_at = NOW() WHERE id = ? AND patient_id = ?")
            ->execute([$id, $pid]);

        record_audit($pdo, 'visit_delete', $id, $v['patient_name'],
            'Moved ' . $v['patient_name'] . '\'s visit (' . fmt_date($v['visit_date'])
            . ($v['reason'] !== '' ? ' — ' . $v['reason'] : '') . ') to the Archive');
        set_flash('Visit / diagnosis moved to Archive');
    } else {
        set_flash('That visit could not be found.');
    }
}
redirect('../patient.php?id=' . $pid);
