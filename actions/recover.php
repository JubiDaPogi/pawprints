<?php
/* ============================================================
   Account recovery handler.
   ------------------------------------------------------------
   Step 1  find the account (never reveals whether it exists)
   Step 2  answer ONE security question, chosen at random
   Step 3  set a new password
   ============================================================ */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('../forgot.php');
verify_csrf('../forgot.php');

$step = (int)($_POST['step'] ?? 1);

function bail($msg, $reset = false) {
    $_SESSION['recover_error'] = $msg;
    if ($reset) unset($_SESSION['recover']);
    redirect('../forgot.php');
}

/* ---------------------------------------------------------- step 1 */
if ($step === 1) {
    $email = trim($_POST['email'] ?? '');
    if ($email === '') bail('Please enter your email address.');

    $stmt = $pdo->prepare(
        "SELECT * FROM users
         WHERE LOWER(email) = LOWER(?) AND deleted_at IS NULL AND is_active = 1
         LIMIT 1"
    );
    $stmt->execute([$email]);
    $u = $stmt->fetch();

    // An account with no questions set can't be recovered this way, and
    // an unknown address must look identical — otherwise this form
    // becomes a way to discover which emails are registered.
    if (!$u || (int)$u['security_set'] !== 1) {
        record_audit($pdo, 'recovery_failed', $u ? (int)$u['id'] : null, $email,
            $u ? 'Recovery attempted but no security questions are set' : 'Recovery attempted for an unknown email',
            ['id' => null, 'username' => $email]);
        bail('If that email address has an account with recovery set up, a security question will appear. Please check the address and try again.', true);
    }

    // Pick one of the three questions at random — a different one each
    // time, so an attacker can't keep retrying against a known answer.
    $slot = random_int(1, 3);
    $_SESSION['recover'] = [
        'step'     => 2,
        'uid'      => (int)$u['id'],
        'ask'      => $slot,
        'question' => $u['security_q' . $slot],
        'tries'    => 0,
        'started'  => time(),
    ];
    record_audit($pdo, 'recovery_start', (int)$u['id'], $u['email'],
        'Account recovery started — asked security question ' . $slot,
        ['id' => (int)$u['id'], 'username' => $u['email']]);
    redirect('../forgot.php');
}

/* ---------------------------------------------------------- step 2 */
if ($step === 2) {
    $r = $_SESSION['recover'] ?? null;
    if (!$r || ($r['step'] ?? 0) < 2) bail('Please start again.', true);

    // A recovery session shouldn't stay open indefinitely.
    if (time() - ($r['started'] ?? 0) > 900) {
        bail('That took too long — please start again.', true);
    }

    $answer = trim($_POST['answer'] ?? '');
    if ($answer === '') bail('Please answer the question.');

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([(int)$r['uid']]);
    $u = $stmt->fetch();
    if (!$u) bail('Please start again.', true);

    if (!answer_matches($answer, $u['security_a' . $r['ask']])) {
        $_SESSION['recover']['tries'] = ($r['tries'] ?? 0) + 1;
        record_audit($pdo, 'recovery_failed', (int)$u['id'], $u['email'],
            'Wrong answer to security question ' . $r['ask'],
            ['id' => (int)$u['id'], 'username' => $u['email']]);

        // Three wrong answers ends the attempt, so the questions can't
        // be brute-forced one guess at a time.
        if ($_SESSION['recover']['tries'] >= 3) {
            bail('Too many incorrect answers. Please start again or contact the clinic.', true);
        }
        bail('That answer doesn\'t match our records. Please try again.');
    }

    $_SESSION['recover']['step'] = 3;
    $_SESSION['recover']['verified'] = true;
    redirect('../forgot.php');
}

/* ---------------------------------------------------------- step 3 */
if ($step === 3) {
    $r = $_SESSION['recover'] ?? null;
    if (!$r || empty($r['verified'])) bail('Please start again.', true);
    if (time() - ($r['started'] ?? 0) > 900) bail('That took too long — please start again.', true);

    $pw      = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if ($err = password_problem($pw)) bail($err);
    if ($pw !== $confirm)             bail('The passwords do not match.');

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([(int)$r['uid']]);
    $u = $stmt->fetch();
    if (!$u) bail('Please start again.', true);

    // Don't let them set the password they already had.
    if (password_verify($pw, $u['password'])) {
        bail('Please choose a password you haven\'t used before.');
    }

    $pdo->prepare("UPDATE users SET password = ?, must_change_password = 0 WHERE id = ?")
        ->execute([password_hash($pw, PASSWORD_DEFAULT), (int)$u['id']]);

    record_audit($pdo, 'password_change', (int)$u['id'], $u['email'],
        'Password reset through account recovery',
        ['id' => (int)$u['id'], 'username' => $u['email']]);

    unset($_SESSION['recover']);
    session_regenerate_id(true);
    $_SESSION['login_error'] = 'Your password has been reset — please sign in.';
    $_SESSION['login_notice'] = true;
    redirect('../index.php');
}

redirect('../forgot.php');
