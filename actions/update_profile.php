<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('../account.php');
verify_csrf('../account.php');

$uid  = (int)current_user()['id'];

// Confirm identity before writing anything: require the current
// password, the same way the password-change form already does.
$stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
$stmt->execute([$uid]);
$stored = $stmt->fetchColumn();
if (!verify_current_password($_POST['current_password'] ?? '', $stored)) {
    record_audit($pdo, 'profile_update', $uid, current_user()['username'], 'Failed: wrong current password');
    set_form_error('profile', 'Your current password is incorrect.');
    redirect('../account.php');
}

$first  = trim($_POST['first_name'] ?? '');
$middle = trim($_POST['middle_name'] ?? '');
$last   = trim($_POST['last_name'] ?? '');
$name   = build_full_name($first, $middle, $last);
$email = trim($_POST['email'] ?? '');
$phone = format_phone($_POST['phone'] ?? '');
$addr  = trim($_POST['address'] ?? '');

if ($first === '') {
    set_form_error('profile', 'Please enter your first name.');
    redirect('../account.php');
}
if ($middle === '') {
    set_form_error('profile', 'Please enter your middle name.');
    redirect('../account.php');
}
if ($last === '') {
    set_form_error('profile', 'Please enter your last name.');
    redirect('../account.php');
}
// The email address is the login identifier: it is required, must be
// valid, and must stay unique. Without these checks a blank value would
// hit the NOT NULL column and a duplicate would hit the UNIQUE index,
// either of which throws an uncaught database error.
if ($email === '') {
    set_form_error('profile', 'An email address is required — it is your login.');
    redirect('../account.php');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    set_form_error('profile', 'Please enter a valid email address.');
    redirect('../account.php');
}
$email = strtolower($email);
$chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE LOWER(email) = ? AND id <> ? AND deleted_at IS NULL");
$chk->execute([$email, $uid]);
if ((int)$chk->fetchColumn() > 0) {
    set_form_error('profile', 'That email address is already registered to another account.');
    redirect('../account.php');
}

// Update the login account.
$pdo->prepare(
    "UPDATE users SET first_name = ?, middle_name = ?, last_name = ?,
            email = ?, phone = ? WHERE id = ?"
)->execute([
    $first, $middle, $last,
    $email !== '' ? $email : null, $phone !== '' ? $phone : null, $uid,
]);

// Owners: keep their client record (owners table) in sync.
$u = $pdo->prepare("SELECT role, owner_id FROM users WHERE id = ?");
$u->execute([$uid]);
$row = $u->fetch();
if ($row && $row['role'] === 'owner' && $row['owner_id']) {
    $pdo->prepare("UPDATE owners SET first_name = ?, middle_name = ?, last_name = ?, email = ?, phone = ?, address = ? WHERE id = ?")
        ->execute([$first, $middle, $last, $email, $phone, $addr, (int)$row['owner_id']]);
}

// Reflect the changes in the current session.
$_SESSION['user']['first_name']  = $first;
$_SESSION['user']['middle_name'] = $middle;
$_SESSION['user']['last_name']   = $last;
$_SESSION['user']['full_name']   = $name;
$_SESSION['user']['email']     = $email !== '' ? $email : null;
$_SESSION['user']['phone']     = $phone !== '' ? $phone : null;

record_audit($pdo, 'profile_update', $uid, current_user()['username'], 'Updated own profile details');
set_flash('Your profile has been updated.', 'profile', 'success');
redirect('../account.php');
