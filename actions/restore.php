<?php
/* ============================================================
   Restore a soft-deleted record from the Archive.
   ============================================================ */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';
// Staff may restore patients and species; restoring a USER ACCOUNT
// additionally requires the user-management permission (checked below,
// so hiding the tab isn't the only thing stopping a direct POST).
require_staff_or_manager();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('../archive.php');
verify_csrf('../archive.php');

$type = $_POST['type'] ?? '';
$id   = (int)($_POST['id'] ?? 0);
if (!$id || !in_array($type, ['patient', 'user', 'species', 'visit', 'vaccination'], true)) {
    set_flash('Nothing to restore.');
    redirect('../archive.php');
}

switch ($type) {

    // ---------------------------------------------------------- patient
    case 'patient':
        $row = $pdo->prepare("SELECT * FROM patients WHERE id = ? AND deleted_at IS NOT NULL");
        $row->execute([$id]);
        $p = $row->fetch();
        if (!$p) { set_flash('That patient record is not in the Archive.'); redirect('../archive.php?tab=patients'); }

        // The species may have been removed while this was deleted.
        $sp = $pdo->prepare("SELECT COUNT(*) FROM species WHERE name = ? AND deleted_at IS NULL");
        $sp->execute([$p['species']]);
        if ((int)$sp->fetchColumn() === 0) {
            set_flash('Restore "' . $p['name'] . '" failed: the species "' . $p['species']
                    . '" is no longer on the list. Restore or re-add that species first.');
            redirect('../archive.php?tab=patients');
        }

        $pdo->prepare("UPDATE patients SET deleted_at = NULL WHERE id = ?")->execute([$id]);

        // Put back any reminders that were cancelled by the deletion, so
        // long as their appointment is still ahead of us.
        $pdo->prepare(
            "UPDATE reminders r
             JOIN appointments a ON a.id = r.appointment_id
             SET r.status = 'pending', r.error = NULL, r.sent_at = NULL
             WHERE a.patient_id = ?
               AND r.status = 'cancelled'
               AND r.error = 'Patient record was deleted'
               AND a.status = 'Scheduled'
               AND a.appt_date >= CURDATE()"
        )->execute([$id]);
        record_audit($pdo, 'patient_restore', $id, $p['name'],
            'Restored patient record for ' . $p['name'] . ' (visits, vaccinations and appointments kept)');
        set_flash($p['name'] . "'s record has been restored.");
        redirect('../archive.php?tab=patients');

    // ------------------------------------------------------------ visit
    case 'visit':
        // Join the patient so we can (a) name it in the audit trail and
        // (b) refuse to restore a visit whose patient is itself deleted —
        // it would reappear on a chart that no longer exists.
        $row = $pdo->prepare(
            "SELECT v.*, p.name AS patient_name, p.deleted_at AS patient_deleted
             FROM visits v JOIN patients p ON p.id = v.patient_id
             WHERE v.id = ? AND v.deleted_at IS NOT NULL"
        );
        $row->execute([$id]);
        $v = $row->fetch();
        if (!$v) { set_flash('That visit is not in the Archive.'); redirect('../archive.php?tab=visits'); }

        if ($v['patient_deleted'] !== null) {
            set_flash('Restore this visit failed: its patient "' . $v['patient_name']
                    . '" is in the Archive. Restore the patient first.');
            redirect('../archive.php?tab=visits');
        }

        $pdo->prepare("UPDATE visits SET deleted_at = NULL WHERE id = ?")->execute([$id]);
        record_audit($pdo, 'visit_restore', $id, $v['patient_name'],
            'Restored ' . $v['patient_name'] . '\'s visit (' . fmt_date($v['visit_date'])
            . ($v['reason'] !== '' ? ' — ' . $v['reason'] : '') . ')');
        set_flash($v['patient_name'] . '\'s visit has been restored.');
        redirect('../archive.php?tab=visits');

    // ------------------------------------------------------- vaccination
    case 'vaccination':
        // Same reasoning as visits: join the patient so we can name it in
        // the audit trail and refuse to restore onto a chart that's itself
        // in the Archive.
        $row = $pdo->prepare(
            "SELECT vc.*, p.name AS patient_name, p.deleted_at AS patient_deleted
             FROM vaccinations vc JOIN patients p ON p.id = vc.patient_id
             WHERE vc.id = ? AND vc.deleted_at IS NOT NULL"
        );
        $row->execute([$id]);
        $v = $row->fetch();
        if (!$v) { set_flash('That vaccination is not in the Archive.'); redirect('../archive.php?tab=vaccinations'); }

        if ($v['patient_deleted'] !== null) {
            set_flash('Restore this vaccination failed: its patient "' . $v['patient_name']
                    . '" is in the Archive. Restore the patient first.');
            redirect('../archive.php?tab=vaccinations');
        }

        $pdo->prepare("UPDATE vaccinations SET deleted_at = NULL WHERE id = ?")->execute([$id]);
        record_audit($pdo, 'vaccine_restore', $id, $v['patient_name'],
            'Restored ' . $v['patient_name'] . '\'s ' . $v['name'] . ' vaccine (' . fmt_date($v['date_given']) . ')');
        set_flash($v['patient_name'] . '\'s vaccination has been restored.');
        redirect('../archive.php?tab=vaccinations');

    // ------------------------------------------------------------- user
    case 'user':
        if (!can_manage_users()) {
            set_flash('You do not have permission to restore user accounts.');
            redirect('../archive.php');
        }
        $row = $pdo->prepare("SELECT * FROM users WHERE id = ? AND deleted_at IS NOT NULL");
        $row->execute([$id]);
        $u = $row->fetch();
        if (!$u) { set_flash('That account is not in the Archive.'); redirect('../archive.php?tab=users'); }

        // Someone may have taken this email since the account was deleted.
        $clash = $pdo->prepare("SELECT COUNT(*) FROM users WHERE LOWER(email) = LOWER(?) AND id <> ? AND deleted_at IS NULL");
        $clash->execute([$u['email'], $id]);
        if ((int)$clash->fetchColumn() > 0) {
            set_flash('Cannot restore ' . $u['email'] . ' — another active account now uses that email address.');
            redirect('../archive.php?tab=users');
        }

        $pdo->prepare("UPDATE users SET deleted_at = NULL WHERE id = ?")->execute([$id]);
        record_audit($pdo, 'user_restore', $id, $u['email'],
            'Restored ' . role_label($u['role']) . ' account ' . $u['email']);
        set_flash('The account for ' . $u['email'] . ' has been restored.');
        redirect('../archive.php?tab=users');

    // ---------------------------------------------------------- species
    case 'species':
        $row = $pdo->prepare("SELECT * FROM species WHERE id = ? AND deleted_at IS NOT NULL");
        $row->execute([$id]);
        $s = $row->fetch();
        if (!$s) { set_flash('That species is not in the Archive.'); redirect('../archive.php?tab=species'); }

        // The name may have been re-added in the meantime.
        $clash = $pdo->prepare("SELECT COUNT(*) FROM species WHERE LOWER(name) = LOWER(?) AND id <> ? AND deleted_at IS NULL");
        $clash->execute([$s['name'], $id]);
        if ((int)$clash->fetchColumn() > 0) {
            set_flash('"' . $s['name'] . '" is already back on the species list.');
            redirect('../archive.php?tab=species');
        }

        $pdo->prepare("UPDATE species SET deleted_at = NULL WHERE id = ?")->execute([$id]);
        record_audit($pdo, 'species_restore', $id, $s['name'], 'Restored species "' . $s['name'] . '"');
        set_flash('Species "' . $s['name'] . '" has been restored.');
        redirect('../archive.php?tab=species');
}

redirect('../archive.php');
