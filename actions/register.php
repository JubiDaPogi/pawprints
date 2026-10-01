<?php
/* ============================================================
   Public self-registration handler — pet owners only.
   Deliberately simpler than actions/create_user.php: no role choice
   (always 'owner'), no admin permissions, and the person sets their
   own real password on the spot instead of getting a temporary one.
   ============================================================ */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';

if (is_logged_in()) redirect('../dashboard.php');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('../signup.php');
verify_csrf('../signup.php');

$first   = trim($_POST['first_name'] ?? '');
$middle  = trim($_POST['middle_name'] ?? '');
$last    = trim($_POST['last_name'] ?? '');
$full    = build_full_name($first, $middle, $last);
$email   = strtolower(trim($_POST['email'] ?? ''));
$phone   = format_phone($_POST['phone'] ?? '');
$address = trim($_POST['address'] ?? '');
$pw      = $_POST['password'] ?? '';
$pw2     = $_POST['confirm_password'] ?? '';

/** Bail out with an error, keeping what they already typed (never the
 *  password) so they don't have to fill the whole form in again. */
function signup_fail($msg, $post) {
    $_SESSION['signup_error'] = $msg;
    $_SESSION['signup_old'] = [
        'first_name'  => $post['first_name']  ?? '',
        'middle_name' => $post['middle_name'] ?? '',
        'last_name'   => $post['last_name']   ?? '',
        'email'       => $post['email']       ?? '',
        'phone'       => $post['phone']       ?? '',
        'address'     => $post['address']     ?? '',
    ];
    redirect('../signup.php');
}

if ($first === '')  signup_fail('Please enter your first name.', $_POST);
if ($middle === '') signup_fail('Please enter your middle name.', $_POST);
if ($last === '')   signup_fail('Please enter your last name.', $_POST);
if ($address === '') signup_fail('Please enter your home address.', $_POST);
if ($err = login_email_problem($email)) signup_fail($err, $_POST);
if ($phone === '') signup_fail('A phone number is required.', $_POST);
if ($pw !== $pw2)  signup_fail('The passwords do not match.', $_POST);
if ($err = password_problem($pw)) signup_fail($err, $_POST);
if (empty($_POST['privacy_consent'])) {
    signup_fail('Please agree to the Privacy Notice to create an account.', $_POST);
}

// The email is the login, so it must be unique.
$chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE LOWER(email) = ? AND deleted_at IS NULL");
$chk->execute([$email]);
if ((int)$chk->fetchColumn() > 0) {
    signup_fail('That email address is already registered. Try signing in, or use "Forgot your password?".', $_POST);
}

$consentAt  = date('Y-m-d H:i:s');
$consentVer = defined('PRIVACY_VERSION') ? PRIVACY_VERSION : '';
$hasConsentCols = has_privacy_consent_columns($pdo);

// Every owner account needs a client record — reuse one already on file
// for this email (e.g. added by staff before the person signed up
// themselves) rather than creating a duplicate.
$find = $pdo->prepare("SELECT id FROM owners WHERE LOWER(email) = LOWER(?) LIMIT 1");
$find->execute([$email]);
$existingOwner = $find->fetchColumn();

if ($existingOwner) {
    $ownerId = (int)$existingOwner;
    if ($hasConsentCols) {
        $pdo->prepare("UPDATE owners SET privacy_consent_at = ?, privacy_consent_ver = ? WHERE id = ?")
            ->execute([$consentAt, $consentVer, $ownerId]);
    }
    $ownerCreated = false;
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

$hash = password_hash($pw, PASSWORD_DEFAULT);

// Unlike an admin-created account, the person themselves just ticked the
// consent box on this very form — so their own users-row consent is
// stamped immediately, and they won't be asked again at first sign-in
// (see includes/auth.php: require_privacy_consent).
$hasUserConsentCols = has_user_consent_columns($pdo);
if ($hasUserConsentCols) {
    $stmt = $pdo->prepare(
        "INSERT INTO users
            (password, role, first_name, middle_name, last_name, email, phone, owner_id,
             is_active, can_manage_users, must_change_password, privacy_consent_at, privacy_consent_ver)
         VALUES (?, 'owner', ?,?,?,?,?,?, 1, 0, 0, ?, ?)"
    );
    $stmt->execute([$hash, $first, $middle, $last, $email, $phone, $ownerId, $consentAt, $consentVer]);
} else {
    $stmt = $pdo->prepare(
        "INSERT INTO users
            (password, role, first_name, middle_name, last_name, email, phone, owner_id,
             is_active, can_manage_users, must_change_password)
         VALUES (?, 'owner', ?,?,?,?,?,?, 1, 0, 0)"
    );
    $stmt->execute([$hash, $first, $middle, $last, $email, $phone, $ownerId]);
}
$newId = (int)$pdo->lastInsertId();

if ($ownerCreated) {
    record_audit($pdo, 'owner_create', $ownerId, $full,
        'Created client record for new pet owner ' . $full, ['id' => $newId, 'username' => $email]);
}
record_audit($pdo, 'user_create', $newId, $email,
    'Self-registered a pet-owner account @' . $email, ['id' => $newId, 'username' => $email]);
record_audit($pdo, 'privacy_consent', $newId, $email,
    'Privacy Notice (v' . ($consentVer !== '' ? $consentVer : '—') . ') agreed for ' . $full . ' at sign-up',
    ['id' => $newId, 'username' => $email]);

$_SESSION['login_prefill'] = $email;
$_SESSION['login_notice']  = true;
$_SESSION['login_error']   = 'Your account has been created — please sign in.';
redirect('../index.php');
