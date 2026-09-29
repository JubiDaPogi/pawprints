<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('../account.php');
verify_csrf('../account.php#password');

$uid     = (int)current_user()['id'];
$current = $_POST['current_password'] ?? '';
$new     = $_POST['new_password'] ?? '';
$confirm = $_POST['confirm_password'] ?? '';

// Load the stored password.
$stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
$stmt->execute([$uid]);
$stored = $stmt->fetchColumn();

// Verify the current password (supports the seeded "plain:" format too).
if (!verify_current_password($current, $stored)) {
    record_audit($pdo, 'password_change', $uid, current_user()['username'], 'Failed: wrong current password');
    set_form_error('password', 'Your current password is incorrect.');
    redirect('../account.php#password');
}

// Validate the new password.
if ($err = password_problem($new)) {
    set_form_error('password', $err);
    redirect('../account.php#password');
}
if ($new !== $confirm) {
    set_form_error('password', 'The new passwords do not match.');
    redirect('../account.php#password');
}
if (password_verify($new, (string)$stored)) {
    set_form_error('password', 'Your new password must be different from the current one.');
    redirect('../account.php#password');
}

// Save the new hash and clear any forced-change flag.
$hash = password_hash($new, PASSWORD_DEFAULT);
$pdo->prepare("UPDATE users SET password = ?, must_change_password = 0 WHERE id = ?")
    ->execute([$hash, $uid]);

$_SESSION['user']['must_change_password'] = 0;
session_regenerate_id(true);   // refresh the session after a credential change

record_audit($pdo, 'password_change', $uid, current_user()['username'], 'Changed own password');
set_flash('Your password has been changed.', 'password', 'success');
redirect('../account.php');
