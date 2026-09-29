<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';
require_manage_users();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('../users.php');
verify_csrf('../users.php');

$id = (int)($_POST['id'] ?? 0);
if (!$id) { set_flash('Missing account.'); redirect('../users.php'); }

if ($id === (int)current_user()['id']) {
    set_flash("You can't change your own account status.");
    redirect('../users.php');
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$target = $stmt->fetch();
if (!$target) { set_flash('That account no longer exists.'); redirect('../users.php'); }

$newState = (int)$target['is_active'] === 1 ? 0 : 1;

// Don't deactivate the last active staff account.
if ($newState === 0 && $target['role'] === 'staff') {
    $activeStaff = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='staff' AND is_active=1 AND deleted_at IS NULL")->fetchColumn();
    if ($activeStaff <= 1) {
        set_flash('This is the last active staff account and cannot be deactivated.');
        redirect('../users.php');
    }
}

// Likewise, never deactivate the last account that can manage users.
if ((int)$target['can_manage_users'] === 1 && (int)$target['is_active'] === 1) {
    $activeAdmins = (int)$pdo->query(
        "SELECT COUNT(*) FROM users WHERE can_manage_users = 1 AND is_active = 1 AND deleted_at IS NULL"
    )->fetchColumn();
    if ($activeAdmins <= 1) {
        set_flash('This is the last account that can manage users and cannot be deactivated.');
        redirect('../users.php');
    }
}

$pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?")->execute([$newState, $id]);

record_audit($pdo, $newState ? 'user_activate' : 'user_deactivate', $id, $target['email'],
    ($newState ? 'Activated' : 'Deactivated') . ' account @' . $target['email']);

set_flash('Account for ' . $target['email'] . ($newState ? ' activated.' : ' deactivated.'));
redirect('../users.php');
