<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';
require_manage_users();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('../users.php');
verify_csrf('../users.php');

$first    = trim($_POST['first_name'] ?? '');
$middle   = trim($_POST['middle_name'] ?? '');
$last     = trim($_POST['last_name'] ?? '');
// Assembled for activity-log messages and the flash confirmation; the
// database stores only the three parts.
$full     = build_full_name($first, $middle, $last);
// The form's role dropdown submits veterinarian | staff | owner. The first
// two are clinic-staff accounts distinguished by their title; the access
// level (role) is 'staff' for both.
$roleChoice = $_POST['role'] ?? 'owner';
if ($roleChoice === 'veterinarian') {
    $role = 'staff'; $staffTitle = 'veterinarian';
} elseif ($roleChoice === 'staff') {
    $role = 'staff'; $staffTitle = 'staff';
} else {
    $role = 'owner'; $staffTitle = null;
}
$email    = trim($_POST['email'] ?? '');
$phone    = format_phone($_POST['phone'] ?? '');
$ownerId  = ($_POST['owner_id'] ?? '') !== '' ? (int)$_POST['owner_id'] : null;
$active   = isset($_POST['is_active']) ? 1 : 0;
// Admin rights are a per-account permission for BOTH roles, unticked
// by default for every new account. Tick the box to grant a staff or
// owner account access to User Accounts + Activity Log.
$canManage = isset($_POST['can_manage_users']) ? 1 : 0;
$address   = trim($_POST['address'] ?? '');

// ------------------------------------------------------------------
// New accounts are provisioned from the person's contact details:
//   login identifier  = their email address
//   temporary password = the digits of their phone number
// The account is ALWAYS flagged must_change_password, so that phone
// number stops working the moment they set their own password.
// ------------------------------------------------------------------
$email      = strtolower($email);
$password   = temp_password_from_phone($phone);
$mustChange = 1;   // forced for every new account — not user-selectable

// Validation.
if ($first === '') { set_form_error('user-new', 'Please enter the first name.'); redirect('../users.php'); }
if ($middle === '') { set_form_error('user-new', 'Please enter the middle name.'); redirect('../users.php'); }
if ($last === '')  { set_form_error('user-new', 'Please enter the last name.'); redirect('../users.php'); }
if ($email === '') { set_form_error('user-new', 'An email address is required — it is the login.'); redirect('../users.php'); }
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    set_form_error('user-new', 'Please enter a valid email address.'); redirect('../users.php');
}
if ($phone === '') { set_form_error('user-new', 'A phone number is required — it becomes the temporary password.'); redirect('../users.php'); }
// The address is stored on the pet owner's client record, so it is
// required for owner accounts. Staff accounts have no client record,
// so the field is not applicable to them.
if ($role === 'owner' && $address === '') {
    set_form_error('user-new', 'An address is required for pet owners.');
    redirect('../users.php');
}
// Data Privacy Act (RA 10173): personal data may only be collected once the
// data subject has agreed to the Privacy Notice. The HTML checkbox is a
// convenience; this server check is what actually enforces it.
if (empty($_POST['privacy_consent'])) {
    set_form_error('user-new', 'Please confirm agreement to the Privacy Notice before creating the account.');
    redirect('../users.php');
}
$consentAt  = date('Y-m-d H:i:s');
$consentVer = defined('PRIVACY_VERSION') ? PRIVACY_VERSION : '';
if ($err = login_email_problem($email))      { set_form_error('user-new', $err); redirect('../users.php'); }
if ($err = temp_password_problem($password)) { set_form_error('user-new', $err); redirect('../users.php'); }

// The email is the login, so it must be unique.
$chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE LOWER(email) = LOWER(?) AND deleted_at IS NULL");
$chk->execute([$email]);
if ((int)$chk->fetchColumn() > 0) {
    set_form_error('user-new', 'That email address is already registered.');
    redirect('../users.php');
}

// Staff accounts are not tied to an owner record.
if ($role === 'staff') $ownerId = null;
if ($ownerId !== null) {
    $oc = $pdo->prepare("SELECT COUNT(*) FROM owners WHERE id = ?");
    $oc->execute([$ownerId]);
    if ((int)$oc->fetchColumn() === 0) $ownerId = null;
}

// ------------------------------------------------------------------
// An owner account must point at a client record in `owners`, because
// that is what the Patients screen lists when assigning a pet. If staff
// didn't pick an existing client, create one from the account details
// so the new owner is immediately selectable as a pet owner.
// If a client with this email already exists, reuse it instead of
// creating a duplicate.
// ------------------------------------------------------------------
$ownerCreated = false;
$hasConsentCols = has_privacy_consent_columns($pdo);
if ($role === 'owner' && $ownerId === null) {
    $find = $pdo->prepare("SELECT id FROM owners WHERE LOWER(email) = LOWER(?) LIMIT 1");
    $find->execute([$email]);
    $existing = $find->fetchColumn();

    if ($existing) {
        $ownerId = (int)$existing;
        // Record the consent just given against the reused client record.
        if ($hasConsentCols) {
            $pdo->prepare("UPDATE owners SET privacy_consent_at = ?, privacy_consent_ver = ? WHERE id = ?")
                ->execute([$consentAt, $consentVer, $ownerId]);
        }
    } else {
        if ($hasConsentCols) {
            $ins = $pdo->prepare(
                "INSERT INTO owners (first_name, middle_name, last_name, phone, email, address, privacy_consent_at, privacy_consent_ver) VALUES (?,?,?,?,?,?,?,?)"
            );
            $ins->execute([$first, $middle, $last, $phone, $email, $address, $consentAt, $consentVer]);
        } else {
            $ins = $pdo->prepare(
                "INSERT INTO owners (first_name, middle_name, last_name, phone, email, address) VALUES (?,?,?,?,?,?)"
            );
            $ins->execute([$first, $middle, $last, $phone, $email, $address]);
        }
        $ownerId = (int)$pdo->lastInsertId();
        $ownerCreated = true;
    }
}

$hash = password_hash($password, PASSWORD_DEFAULT);

// Include staff_title only when the column exists, so the app keeps working
// on a database where the migration hasn't been run yet.
if (has_staff_title_column($pdo)) {
    $stmt = $pdo->prepare(
        "INSERT INTO users
            (password, role, staff_title, first_name, middle_name, last_name,
             email, phone, owner_id,
             is_active, can_manage_users, must_change_password)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?)"
    );
    $stmt->execute([
        $hash, $role, $staffTitle, $first, $middle, $last,
        $email, $phone,
        $ownerId, $active, $canManage, $mustChange,
    ]);
} else {
    $stmt = $pdo->prepare(
        "INSERT INTO users
            (password, role, first_name, middle_name, last_name,
             email, phone, owner_id,
             is_active, can_manage_users, must_change_password)
         VALUES (?,?,?,?,?,?,?,?,?,?,?)"
    );
    $stmt->execute([
        $hash, $role, $first, $middle, $last,
        $email, $phone,
        $ownerId, $active, $canManage, $mustChange,
    ]);
}
$newId = (int)$pdo->lastInsertId();

if ($ownerCreated) {
    record_audit($pdo, 'owner_create', $ownerId, $full,
        'Created client record for new pet owner ' . $full);
}

record_audit($pdo, 'user_create', $newId, $email,
    'Created ' . role_label($role, $staffTitle) . ' account @' . $email
    . ' (temporary password from phone; password change required)');
if ($canManage) {
    record_audit($pdo, 'permission_grant', $newId, $email, 'Granted user-management permission');
}
// Evidenced record of the Data Privacy Act consent captured on the form.
record_audit($pdo, 'privacy_consent', $newId, $email,
    'Privacy Notice (v' . ($consentVer !== '' ? $consentVer : '—') . ') agreed for ' . $full . ' at account creation');

// Show the credentials once so staff can pass them on.
set_flash('Account created. Username: ' . $email
        . '  ·  Temporary password: ' . $password
        . '  ·  They must set a new password at first sign-in.'
        . ($ownerCreated ? '  ·  Added to the pet-owner list.' : ''));
redirect('../users.php');
