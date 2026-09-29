<?php
/* ============================================================
   Record a person's agreement to the Privacy Notice, captured
   at first sign-in (Data Privacy Act, RA 10173).

   Stamps the users row with the version they agreed to, syncs
   the agreement onto their owner/client record when they are a
   pet owner, refreshes the session, and writes an audit entry.
   ============================================================ */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('../privacy_consent.php');
verify_csrf('../privacy_consent.php');

$me  = current_user();
$uid = (int)$me['id'];

// The checkbox is required. If it's missing, send them back with a note.
if (empty($_POST['privacy_consent'])) {
    set_flash('Please tick the box to confirm your agreement.', 'privacy-consent');
    redirect('../privacy_consent.php');
}

$now = date('Y-m-d H:i:s');
$ver = defined('PRIVACY_VERSION') ? PRIVACY_VERSION : '';

// Record against the user account (the person who agreed).
if (has_user_consent_columns($pdo)) {
    $pdo->prepare("UPDATE users SET privacy_consent_at = ?, privacy_consent_ver = ? WHERE id = ?")
        ->execute([$now, $ver, $uid]);
    // Keep the session in step so the gate lets them through immediately.
    $_SESSION['user']['privacy_consent_ver'] = $ver;
} else {
    // Column not present (un-migrated DB): mark the session so this session
    // isn't asked again. Nothing to persist.
    $_SESSION['user']['privacy_consent_ver'] = $ver;
}

// If they are a pet owner, upgrade the client record's consent to reflect
// that the client agreed themselves (not just staff on their behalf).
if (($me['role'] ?? '') === 'owner' && !empty($me['owner_id']) && has_privacy_consent_columns($pdo)) {
    $pdo->prepare("UPDATE owners SET privacy_consent_at = ?, privacy_consent_ver = ? WHERE id = ?")
        ->execute([$now, $ver, (int)$me['owner_id']]);
}

record_audit($pdo, 'privacy_consent', $uid, $me['email'] ?? null,
    'Agreed to the Privacy Notice (v' . ($ver !== '' ? $ver : '—') . ') at sign-in');

redirect('../dashboard.php');
