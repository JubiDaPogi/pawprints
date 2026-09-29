<?php
/* ============================================================
   Login handler
   ------------------------------------------------------------
   Verifies credentials. Supports two password formats in the
   users table:
     - "plain:xxxx"  -> the seeded default passwords. On the first
                        successful login we transparently replace
                        this with a secure bcrypt hash.
     - bcrypt hash   -> created by password_hash() (all real ones).
   ============================================================ */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('../index.php');

// The sign-in form submits through fetch() so a failed attempt can show
// an inline message instead of reloading the page. That request always
// carries this header; a plain form post (JavaScript unavailable) never
// sets it, so this endpoint still falls back to the old session-flash
// redirect in that case.
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
       && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

/** Report a failed sign-in the right way for how the request arrived. */
function login_fail($message, $isAjax) {
    if ($isAjax) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => $message]);
        exit;
    }
    $_SESSION['login_error'] = $message;
    redirect('../index.php');
}

/** Report a successful sign-in the right way for how the request arrived.
 *  $page is a path relative to the app root, e.g. 'dashboard.php'. */
function login_success($page, $isAjax) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'redirect' => $page]);
        exit;
    }
    redirect('../' . $page);
}

$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// The AJAX flow keeps the typed address in the field itself (the page
// never reloads), so only stash a prefill for the classic redirect path
// — otherwise a later, unrelated visit to the sign-in page could show a
// stale address left over from this attempt.
if (!$isAjax) {
    $_SESSION['login_prefill'] = $email;
}

if ($email === '' || $password === '') {
    login_fail('Please enter your email address and password.', $isAjax);
}

// Look up the account.
// The email address IS the login identifier — there is no separate
// username column. Matched case-insensitively, since nobody types
// capitals consistently on a phone keyboard.
$stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(email) = LOWER(?) AND deleted_at IS NULL LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    record_audit($pdo, 'login_failed', null, $email, 'No account with that email address',
                 ['id' => null, 'username' => $email]);
    // Deliberately identical to the wrong-password message so the form
    // never reveals which email addresses have accounts.
    login_fail('Incorrect email or password. Please try again.', $isAjax);
}

// Verify the password (handles both storage formats).
$stored = $user['password'];
$ok = false;

if (strpos($stored, 'plain:') === 0) {
    // Seeded default password.
    if (hash_equals(substr($stored, 6), $password)) {
        $ok = true;
        // Upgrade to a proper hash so it isn't stored in the clear anymore.
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $up = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $up->execute([$newHash, $user['id']]);
    }
} else {
    // Normal bcrypt hash.
    $ok = password_verify($password, $stored);

    // A brand-new account's temporary password is the digits of their
    // phone number. People naturally type it the way it's written on
    // the form ("0917-123-4567"), so strip the formatting and retry.
    // This only applies while the account is still flagged for a
    // forced password change, so it can't weaken a real password.
    if (!$ok && (int)$user['must_change_password'] === 1) {
        $digitsOnly = preg_replace('/\D/', '', $password);
        if ($digitsOnly !== '' && $digitsOnly !== $password) {
            $ok = password_verify($digitsOnly, $stored);
        }
    }
}

if (!$ok) {
    record_audit($pdo, 'login_failed', (int)$user['id'], $user['email'], 'Incorrect password',
                 ['id' => (int)$user['id'], 'username' => $user['email']]);
    login_fail('Incorrect email or password. Please try again.', $isAjax);
}

// Password is correct — but a deactivated account may not sign in.
if ((int)$user['is_active'] !== 1) {
    record_audit($pdo, 'login_denied', (int)$user['id'], $user['email'], 'Account is deactivated',
                 ['id' => (int)$user['id'], 'username' => $user['email']]);
    login_fail('This account has been deactivated. Please contact the clinic.', $isAjax);
}

// Signed in. The account's own role decides the workspace.
session_regenerate_id(true);
$_SESSION['user'] = [
    'id'                   => (int)$user['id'],
    // Kept as 'username' so audit helpers and existing screens work
    // unchanged; it now always holds the email address.
    'username'             => $user['email'],
    'role'                 => $user['role'],
    // Clinic-staff designation (veterinarian | staff); null for owners and
    // for databases where the staff_title column hasn't been added yet.
    'staff_title'          => $user['staff_title'] ?? null,
    'first_name'           => $user['first_name'],
    'middle_name'          => $user['middle_name'],
    'last_name'            => $user['last_name'],
    'full_name'            => build_full_name($user['first_name'], $user['middle_name'], $user['last_name']),
    'email'                => $user['email'],
    'phone'                => $user['phone'],
    'owner_id'             => $user['owner_id'] !== null ? (int)$user['owner_id'] : null,
    'can_manage_users'     => (int)$user['can_manage_users'],
    'must_change_password' => (int)$user['must_change_password'],
    'security_set'         => (int)$user['security_set'],
];
// Which Privacy Notice version this person has agreed to (per-user, captured
// at first sign-in). Added only when the column exists, so the consent gate
// never triggers on a database where the migration hasn't been run.
if (array_key_exists('privacy_consent_ver', $user)) {
    $_SESSION['user']['privacy_consent_ver'] = $user['privacy_consent_ver'];
}

// Stamp the login time and record it.
$pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([(int)$user['id']]);
record_audit($pdo, 'login', (int)$user['id'], $user['email'], 'Signed in');

// If an administrator flagged this account for a forced password change,
// send them straight to the account page (a banner explains why).
if ((int)$user['must_change_password'] === 1) {
    set_flash('For security, please set a new password before continuing.');
    login_success('account.php#password', $isAjax);
}

login_success('dashboard.php', $isAjax);
