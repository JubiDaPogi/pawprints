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
    set_flash("You can't delete your own account.");
    redirect('../users.php');
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$target = $stmt->fetch();
if (!$target) { set_flash('That account no longer exists.'); redirect('../users.php'); }

// Never delete the last remaining staff account.
if ($target['role'] === 'staff') {
    $staffTotal = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='staff' AND deleted_at IS NULL")->fetchColumn();
    if ($staffTotal <= 1) {
        set_flash('This is the only staff account and cannot be deleted.');
        redirect('../users.php');
    }
}

// Never delete the last account that can manage users.
if ((int)$target['can_manage_users'] === 1) {
    $adminTotal = (int)$pdo->query(
        "SELECT COUNT(*) FROM users WHERE can_manage_users = 1 AND deleted_at IS NULL"
    )->fetchColumn();
    if ($adminTotal <= 1) {
        set_flash('This is the only account that can manage users and cannot be deleted.');
        redirect('../users.php');
    }
}

// Delete only the login account — the owner's client + pet records remain.
// Soft delete — restorable from the Archive.
$pdo->prepare("UPDATE users SET deleted_at = NOW() WHERE id = ?")->execute([$id]);

record_audit($pdo, 'user_delete', $id, $target['email'],
    'Deleted ' . role_label($target['role']) . ' account @' . $target['email']);

set_flash('Account for ' . $target['email'] . ' has been deleted.');
redirect('../users.php');
