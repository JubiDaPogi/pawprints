<?php
/* ============================================================
   Security questions — required at first sign-in.
   Every account must answer three before using the system, so
   there is always a way to recover the password later.
   ============================================================ */
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_login();

$uid = (int)current_user()['id'];
$row = $pdo->prepare("SELECT security_set, security_q1, security_q2, security_q3 FROM users WHERE id = ?");
$row->execute([$uid]);
$me = $row->fetch();

// Already done? Nothing to force.
if ($me && (int)$me['security_set'] === 1) {
    redirect('account.php#security');
}

$questions = security_questions();
// Which three are preselected differs per account, so two people
// setting up on the same day don't get an identical trio.
$suggested = security_questions_for($uid);
$flash       = get_flash();
$flashTarget = get_flash_target();
$flashType   = get_flash_type();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Security questions · Paw Prints Veterinary Clinic</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600;9..144,700&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
<link rel="icon" type="image/png" sizes="32x32" href="assets/favicon-32.png">
<link rel="alternate icon" href="assets/favicon.ico">
<link rel="apple-touch-icon" href="assets/favicon-180.png">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="vp-setup-page">
  <div class="vp-setup-card">
    <div class="vp-setup-icon">
      <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg>
    </div>
    <h1>Set up account recovery</h1>
    <p class="vp-setup-sub">
      Please answer three security questions. If you ever forget your
      password, we'll ask you one of them at random to confirm it's you.
    </p>

    <?php if ($flash): ?>
      <div class="vp-form-alert error" role="alert">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v5M12 16.5v.01"/></svg>
        <span><?= e($flash) ?></span>
      </div>
    <?php endif; ?>

    <form method="post" action="actions/save_security.php" class="vp-setup-form">
      <?= csrf_field() ?>
      <?php for ($i = 1; $i <= 3; $i++): ?>
        <div class="vp-setup-block">
          <span class="vp-setup-num"><?= $i ?></span>
          <div class="vp-setup-fields">
            <div class="vp-field">
              <label for="q<?= $i ?>">Question <?= $i ?></label>
              <select name="q<?= $i ?>" id="q<?= $i ?>" class="vp-secq" required>
                <?php foreach ($questions as $q): ?>
                  <option value="<?= e($q) ?>" <?= ($q === $suggested[$i - 1]) ? 'selected' : '' ?>><?= e($q) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="vp-field">
              <label for="a<?= $i ?>">Your answer</label>
              <input name="a<?= $i ?>" id="a<?= $i ?>" autocomplete="off" required
                     placeholder="Type your answer">
            </div>
          </div>
        </div>
      <?php endfor; ?>

      <p class="vp-hint-text">
        Answers aren't case-sensitive and are stored securely — even clinic
        staff can't read them. Pick answers you'll remember exactly.
      </p>

      <button type="submit" class="vp-btn-primary vp-setup-go">Save and continue</button>
    </form>
  </div>
</div>

<script>
  // Stop the same question being chosen twice.
  (function () {
    var sels = Array.prototype.slice.call(document.querySelectorAll('.vp-secq'));
    function sync() {
      var chosen = sels.map(function (s) { return s.value; });
      sels.forEach(function (s, i) {
        Array.prototype.forEach.call(s.options, function (o) {
          o.disabled = chosen.indexOf(o.value) !== -1 && chosen.indexOf(o.value) !== i;
        });
      });
    }
    sels.forEach(function (s) { s.addEventListener('change', sync); });
    sync();
  })();
</script>
</body>
</html>
