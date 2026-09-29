<?php
/* ============================================================
   Remove a species from the list (clinic staff only)
   ------------------------------------------------------------
   Refuses to delete a species that patients are still using, so
   existing records can never be orphaned.
   ============================================================ */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';
require_staff();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('../patients.php');
verify_csrf('../patients.php');

$id = (int)($_POST['id'] ?? 0);

$row = $pdo->prepare("SELECT * FROM species WHERE id = ?");
$row->execute([$id]);
$sp = $row->fetch();
if (!$sp) {
    set_flash('That species no longer exists.');
    redirect('../patients.php');
}

// Block removal while patients still use it.
$inUse = $pdo->prepare("SELECT COUNT(*) FROM patients WHERE species = ? AND deleted_at IS NULL");
$inUse->execute([$sp['name']]);
$count = (int)$inUse->fetchColumn();
if ($count > 0) {
    set_form_error('species', 'Cannot remove "' . $sp['name'] . '" — ' . $count . ' patient'
            . ($count === 1 ? ' is' : 's are') . ' still recorded as this species.');
    redirect('../patients.php');
}

// Soft delete — restorable from the Archive.
$pdo->prepare("UPDATE species SET deleted_at = NOW() WHERE id = ?")->execute([$id]);
record_audit($pdo, 'species_delete', $id, $sp['name'], 'Removed species "' . $sp['name'] . '"');

set_flash('Species "' . $sp['name'] . '" removed.');
redirect('../patients.php');
