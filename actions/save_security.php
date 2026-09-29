<?php
/* ============================================================
   Save the three security questions and answers.
   Used both by the first-sign-in setup and by My Account.
   ============================================================ */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('../security_setup.php');
verify_csrf('../security_setup.php');

$uid  = (int)current_user()['id'];
$back = ($_POST['from'] ?? '') === 'account' ? '../account.php#security' : '../security_setup.php';
$key  = ($_POST['from'] ?? '') === 'account' ? 'security' : null;

function fail($msg, $back, $key) {
    if ($key) set_form_error($key, $msg);
    else      set_flash($msg);
    redirect($back);
}

// Editing existing security questions from My Account is an account
// change, so it requires the current password like the other account
// forms. The forced first-sign-in setup ($key is null here) is skipped
// — there's no prior account state to protect yet.
if ($key) {
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$uid]);
    $stored = $stmt->fetchColumn();
    if (!verify_current_password($_POST['current_password'] ?? '', $stored)) {
        record_audit($pdo, 'security_update', $uid, current_user()['username'], 'Failed: wrong current password');
        fail('Your current password is incorrect.', $back, $key);
    }
}

$valid = security_questions();
$qs = $as = [];
for ($i = 1; $i <= 3; $i++) {
    $q = trim($_POST['q' . $i] ?? '');
    $a = trim($_POST['a' . $i] ?? '');

    // Only questions from our own list — nobody gets to invent one.
    if (!in_array($q, $valid, true)) fail('Please choose your questions from the list.', $back, $key);
    if ($a === '')                   fail('Please answer all three questions.', $back, $key);
    if (mb_strlen($a) < 2)           fail('Answers need to be at least 2 characters.', $back, $key);

    $qs[] = $q;
    $as[] = $a;
}

// Three different questions, or recovery is weaker than it looks.
if (count(array_unique($qs)) !== 3) {
    fail('Please choose three different questions.', $back, $key);
}
// Three different answers, so one guess can't unlock all of them.
$normalised = array_map('normalise_answer', $as);
if (count(array_unique($normalised)) !== 3) {
    fail('Please give a different answer to each question.', $back, $key);
}

$pdo->prepare(
    "UPDATE users SET security_q1=?, security_a1=?, security_q2=?, security_a2=?,
            security_q3=?, security_a3=?, security_set=1 WHERE id=?"
)->execute([
    $qs[0], hash_answer($as[0]),
    $qs[1], hash_answer($as[1]),
    $qs[2], hash_answer($as[2]),
    $uid,
]);

$_SESSION['user']['security_set'] = 1;
record_audit($pdo, 'security_update', $uid, current_user()['username'],
    'Set up the three account-recovery questions');

if ($key) {
    set_flash('Your security questions have been updated.', 'security', 'success');
    redirect('../account.php#security');
}
set_flash('Thanks — your account recovery is set up.');
redirect('../dashboard.php');
