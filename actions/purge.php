<?php
/* ============================================================
   Permanently delete a record from the Archive.
   ------------------------------------------------------------
   This is the ONE irreversible action in the system, so it is
   restricted to accounts with full user-management rights and
   is refused for anything not already in the Archive.
   ============================================================ */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';
require_manage_users();          // full admin only — not limited staff

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('../archive.php');
verify_csrf('../archive.php');

$type = $_POST['type'] ?? '';
$id   = (int)($_POST['id'] ?? 0);
if (!$id || !in_array($type, ['patient', 'user', 'species', 'visit', 'vaccination'], true)) {
    set_flash('Nothing to delete.');
    redirect('../archive.php');
}

switch ($type) {

    // ---------------------------------------------------------- patient
    case 'patient':
        $row = $pdo->prepare("SELECT * FROM patients WHERE id = ? AND deleted_at IS NOT NULL");
        $row->execute([$id]);
        $p = $row->fetch();
        if (!$p) {
            set_flash('That patient is not in the Archive.');
            redirect('../archive.php?tab=patients');
        }

        // Count the history first — once the row goes, the foreign keys
        // cascade and these are gone with it.
        $n = [];
        foreach (['visits', 'vaccinations', 'appointments'] as $t) {
            $c = $pdo->prepare("SELECT COUNT(*) FROM {$t} WHERE patient_id = ?");
            $c->execute([$id]);
            $n[$t] = (int)$c->fetchColumn();
        }

        $pdo->prepare("DELETE FROM patients WHERE id = ?")->execute([$id]);
        record_audit($pdo, 'purge', $id, $p['name'],
            'Permanently deleted patient ' . $p['name'] . ' — ' . $n['visits'] . ' visit(s), '
            . $n['vaccinations'] . ' vaccination(s), ' . $n['appointments'] . ' appointment(s) removed');
        set_flash($p['name'] . "'s record and its history have been permanently deleted.");
        redirect('../archive.php?tab=patients');

    // ------------------------------------------------------------ visit
    case 'visit':
        $row = $pdo->prepare(
            "SELECT v.*, p.name AS patient_name
             FROM visits v JOIN patients p ON p.id = v.patient_id
             WHERE v.id = ? AND v.deleted_at IS NOT NULL"
        );
        $row->execute([$id]);
        $v = $row->fetch();
        if (!$v) {
            set_flash('That visit is not in the Archive.');
            redirect('../archive.php?tab=visits');
        }

        $pdo->prepare("DELETE FROM visits WHERE id = ?")->execute([$id]);
        record_audit($pdo, 'purge', $id, $v['patient_name'],
            'Permanently deleted ' . $v['patient_name'] . '\'s visit ('
            . fmt_date($v['visit_date']) . ($v['reason'] !== '' ? ' — ' . $v['reason'] : '') . ')');
        set_flash('The visit has been permanently deleted.');
        redirect('../archive.php?tab=visits');

    // ------------------------------------------------------- vaccination
    case 'vaccination':
        $row = $pdo->prepare(
            "SELECT vc.*, p.name AS patient_name
             FROM vaccinations vc JOIN patients p ON p.id = vc.patient_id
             WHERE vc.id = ? AND vc.deleted_at IS NOT NULL"
        );
        $row->execute([$id]);
        $v = $row->fetch();
        if (!$v) {
            set_flash('That vaccination is not in the Archive.');
            redirect('../archive.php?tab=vaccinations');
        }

        $pdo->prepare("DELETE FROM vaccinations WHERE id = ?")->execute([$id]);
        record_audit($pdo, 'purge', $id, $v['patient_name'],
            'Permanently deleted ' . $v['patient_name'] . '\'s ' . $v['name'] . ' vaccine ('
            . fmt_date($v['date_given']) . ')');
        set_flash('The vaccination has been permanently deleted.');
        redirect('../archive.php?tab=vaccinations');

    // ------------------------------------------------------------- user
    case 'user':
        $row = $pdo->prepare("SELECT * FROM users WHERE id = ? AND deleted_at IS NOT NULL");
        $row->execute([$id]);
        $u = $row->fetch();
        if (!$u) {
            set_flash('That account is not in the Archive.');
            redirect('../archive.php?tab=users');
        }
        if ((int)$u['id'] === (int)current_user()['id']) {
            set_flash('You cannot permanently delete your own account.');
            redirect('../archive.php?tab=users');
        }

        // The activity log keeps a username snapshot rather than a foreign
        // key, so removing the account doesn't erase its history.
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
        record_audit($pdo, 'purge', $id, $u['email'],
            'Permanently deleted ' . role_label($u['role']) . ' account ' . $u['email']
            . ' (their client record and pets were kept)');
        set_flash('The account for ' . $u['email'] . ' has been permanently deleted.');
        redirect('../archive.php?tab=users');

    // ---------------------------------------------------------- species
    case 'species':
        $row = $pdo->prepare("SELECT * FROM species WHERE id = ? AND deleted_at IS NOT NULL");
        $row->execute([$id]);
        $s = $row->fetch();
        if (!$s) {
            set_flash('That species is not in the Archive.');
            redirect('../archive.php?tab=species');
        }

        // Patients store the species as text, so a name still in use by
        // any patient — including a soft-deleted one that may yet be
        // restored — must not be purged.
        $inUse = $pdo->prepare("SELECT COUNT(*) FROM patients WHERE species = ?");
        $inUse->execute([$s['name']]);
        $count = (int)$inUse->fetchColumn();
        if ($count > 0) {
            set_flash('Cannot permanently delete "' . $s['name'] . '" — ' . $count
                    . ' patient record' . ($count === 1 ? '' : 's') . ' still use'
                    . ($count === 1 ? 's' : '') . ' this species.');
            redirect('../archive.php?tab=species');
        }

        $pdo->prepare("DELETE FROM species WHERE id = ?")->execute([$id]);
        record_audit($pdo, 'purge', $id, $s['name'], 'Permanently deleted species "' . $s['name'] . '"');
        set_flash('Species "' . $s['name'] . '" has been permanently deleted.');
        redirect('../archive.php?tab=species');
}

redirect('../archive.php');
