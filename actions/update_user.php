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

$cur = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$cur->execute([$id]);
$target = $cur->fetch();
if (!$target) { set_flash('That account no longer exists.'); redirect('../users.php'); }

$isSelf = $id === (int)current_user()['id'];

$first    = trim($_POST['first_name'] ?? '');
$middle   = trim($_POST['middle_name'] ?? '');
$last     = trim($_POST['last_name'] ?? '');
$full     = build_full_name($first, $middle, $last);
$email    = trim($_POST['email'] ?? '');
// The email address IS the login identifier.
$email    = strtolower($email);
$phone    = format_phone($_POST['phone'] ?? '');
$password = $_POST['password'] ?? '';
$ownerId  = ($_POST['owner_id'] ?? '') !== '' ? (int)$_POST['owner_id'] : null;

// Role / status / permission cannot be changed on your OWN account.
// The role dropdown submits veterinarian | staff | owner; the first two are
// clinic-staff titles. When editing yourself, the current values are kept.
if ($isSelf) {
    $role       = $target['role'];
    $staffTitle = $target['staff_title'] ?? null;
} else {
    $roleChoice = $_POST['role'] ?? 'owner';
    if ($roleChoice === 'veterinarian') {
        $role = 'staff'; $staffTitle = 'veterinarian';
    } elseif ($roleChoice === 'staff') {
        $role = 'staff'; $staffTitle = 'staff';
    } else {
        $role = 'owner'; $staffTitle = null;
    }
}
$active     = $isSelf ? 1 : (isset($_POST['is_active']) ? 1 : 0);
$canManage  = $isSelf ? (int)$target['can_manage_users']
                      : (isset($_POST['can_manage_users']) ? 1 : 0);
$mustChange = isset($_POST['must_change_password']) ? 1 : 0;

// Validation.
if ($first === '') { set_form_error('user-edit-' . $id, 'Please enter the first name.'); redirect('../users.php'); }
if ($middle === '') { set_form_error('user-edit-' . $id, 'Please enter the middle name.'); redirect('../users.php'); }
if ($last === '')  { set_form_error('user-edit-' . $id, 'Please enter the last name.'); redirect('../users.php'); }
if ($email === '') { set_form_error('user-edit-' . $id, 'An email address is required — it is the login.'); redirect('../users.php'); }
if ($err = login_email_problem($email)) { set_form_error('user-edit-' . $id, $err); redirect('../users.php'); }

// The email is the login, so it must be unique (excluding this account).
$chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE LOWER(email) = ? AND id <> ? AND deleted_at IS NULL");
$chk->execute([$email, $id]);
if ((int)$chk->fetchColumn() > 0) {
    set_form_error('user-edit-' . $id, 'That email address is already registered to another account.');
    redirect('../users.php');
}

// Don't strand the clinic without an admin: block demoting/deactivating the
// last active staff account.
$activeStaff = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='staff' AND is_active=1 AND deleted_at IS NULL")->fetchColumn();
$wasActiveStaff = ($target['role'] === 'staff' && (int)$target['is_active'] === 1);
if ($wasActiveStaff && $activeStaff <= 1 && ($role !== 'staff' || $active !== 1)) {
    set_form_error('user-edit-' . $id, 'This is the last active staff account — keep it active and staff.');
    redirect('../users.php');
}

// Admin rights are now revocable, so guard the same way: there must
// always be at least one active account that can manage users, or
// nobody could ever reach the User Accounts screen again.
$activeAdmins = (int)$pdo->query(
    "SELECT COUNT(*) FROM users WHERE can_manage_users = 1 AND is_active = 1 AND deleted_at IS NULL"
)->fetchColumn();
$wasActiveAdmin = ((int)$target['can_manage_users'] === 1 && (int)$target['is_active'] === 1);
if ($wasActiveAdmin && $activeAdmins <= 1 && ($canManage !== 1 || $active !== 1)) {
    set_form_error('user-edit-' . $id,
        'This is the last account that can manage users — it must keep admin access and stay active.');
    redirect('../users.php');
}

if ($role === 'staff') $ownerId = null;
if ($ownerId !== null) {
    $oc = $pdo->prepare("SELECT COUNT(*) FROM owners WHERE id = ?");
    $oc->execute([$ownerId]);
    if ((int)$oc->fetchColumn() === 0) $ownerId = null;
}

// An owner account always needs a client record so the person can be
// picked as a pet owner on the Patients screen. Create one if it's
// missing, and keep the contact details in step with the account.
$ownerCreated = false;
if ($role === 'owner') {
    if ($ownerId === null) {
        $find = $pdo->prepare("SELECT id FROM owners WHERE LOWER(email) = LOWER(?) LIMIT 1");
        $find->execute([$email]);
        $existing = $find->fetchColumn();
        if ($existing) {
            $ownerId = (int)$existing;
        } else {
            $ins = $pdo->prepare("INSERT INTO owners (first_name, middle_name, last_name, phone, email, address) VALUES (?,?,?,?,?,'')");
            $ins->execute([$first, $middle, $last, $phone, $email]);
            $ownerId = (int)$pdo->lastInsertId();
            $ownerCreated = true;
        }
    } else {
        // Keep the client record's contact details aligned with the account.
        $pdo->prepare("UPDATE owners SET first_name = ?, middle_name = ?, last_name = ?, phone = ?, email = ? WHERE id = ?")
            ->execute([$first, $middle, $last, $phone, $email, $ownerId]);
    }
}

// Optional password reset.
if ($password !== '') {
    if ($err = password_problem($password)) { set_form_error('user-edit-' . $id, $err); redirect('../users.php'); }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $id]);
}

if (has_staff_title_column($pdo)) {
    $pdo->prepare(
        "UPDATE users SET first_name=?, middle_name=?, last_name=?, email=?, phone=?, role=?, staff_title=?, owner_id=?,
                is_active=?, can_manage_users=?, must_change_password=? WHERE id=?"
    )->execute([
        $first, $middle, $last,
        $email !== '' ? $email : null,
        $phone !== '' ? $phone : null,
        $role, $staffTitle, $ownerId, $active, $canManage, $mustChange, $id,
    ]);
} else {
    $pdo->prepare(
        "UPDATE users SET first_name=?, middle_name=?, last_name=?, email=?, phone=?, role=?, owner_id=?,
                is_active=?, can_manage_users=?, must_change_password=? WHERE id=?"
    )->execute([
        $first, $middle, $last,
        $email !== '' ? $email : null,
        $phone !== '' ? $phone : null,
        $role, $ownerId, $active, $canManage, $mustChange, $id,
    ]);
}

// Audit — main edit plus notable permission/status transitions.
record_audit($pdo, 'user_update', $id, $email, 'Edited account @' . $email);
if ((int)$target['can_manage_users'] !== $canManage) {
    record_audit($pdo, $canManage ? 'permission_grant' : 'permission_revoke', $id, $email,
        ($canManage ? 'Granted' : 'Revoked') . ' user-management permission');
}
if ((int)$target['is_active'] !== $active) {
    record_audit($pdo, $active ? 'user_activate' : 'user_deactivate', $id, $email,
        ($active ? 'Activated' : 'Deactivated') . ' account @' . $email);
}
if ($password !== '') {
    record_audit($pdo, 'password_change', $id, $email, 'Reset password for @' . $email);
}

set_flash('Account for ' . $email . ' updated.');
redirect('../users.php');
