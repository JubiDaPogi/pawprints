<?php
/* ============================================================
   Add a new species (clinic staff only)
   ------------------------------------------------------------
   Species used to be a hardcoded list. Staff can now add their
   own so the clinic isn't limited to Dog/Cat/Bird/Rabbit.
   ============================================================ */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';
require_staff();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('../patients.php');
verify_csrf('../patients.php');

$name = trim($_POST['name'] ?? '');

// Validation.
if ($name === '') {
    set_form_error('species', 'Please enter a species name.');
    redirect('../patients.php');
}
if (mb_strlen($name) > 40) {
    set_form_error('species', 'Species name is too long (40 characters maximum).');
    redirect('../patients.php');
}
if (!preg_match('/^[\p{L} .\'-]+$/u', $name)) {
    set_form_error('species', 'Species name may only contain letters, spaces, and . \' -');
    redirect('../patients.php');
}

// Tidy the capitalisation so the list stays consistent
// ("guinea pig" and "Guinea Pig" shouldn't both exist).
$name = mb_convert_case(mb_strtolower($name), MB_CASE_TITLE, 'UTF-8');

// Already there?
$chk = $pdo->prepare("SELECT COUNT(*) FROM species WHERE LOWER(name) = LOWER(?) AND deleted_at IS NULL");
$chk->execute([$name]);
if ((int)$chk->fetchColumn() > 0) {
    set_form_error('species', '"' . $name . '" is already in the species list.');
    redirect('../patients.php');
}

$pdo->prepare("INSERT INTO species (name) VALUES (?)")->execute([$name]);
$newId = (int)$pdo->lastInsertId();

record_audit($pdo, 'species_create', $newId, $name, 'Added species "' . $name . '"');

set_flash('Species "' . $name . '" added.');
redirect('../patients.php?species=' . urlencode($name));
