<?php
/* ============================================================
   Forgot password — three steps:
     1. enter your email address
     2. answer ONE security question, picked at random each time
     3. choose a new password
   ============================================================ */
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/audit.php';

if (is_logged_in()) redirect('dashboard.php');

$step  = $_SESSION['recover']['step'] ?? 1;
$error = $_SESSION['recover_error'] ?? null;
unset($_SESSION['recover_error']);
$askIndex = $_SESSION['recover']['ask'] ?? null;
$question = $_SESSION['recover']['question'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<script>(function(){try{var t=localStorage.getItem('pp-theme');if(t==='green'||t==='blue')document.documentElement.setAttribute('data-theme',t);}catch(e){}})();</script>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Account recovery · Paw Prints Veterinary Clinic</title>
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
  <div class="vp-setup-card narrow">
    <div class="vp-setup-icon">
      <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0"/></svg>
    </div>

    <div class="vp-steps" aria-hidden="true">
      <span class="<?= $step >= 1 ? 'on' : '' ?>">1</span>
      <i></i>
      <span class="<?= $step >= 2 ? 'on' : '' ?>">2</span>
      <i></i>
      <span class="<?= $step >= 3 ? 'on' : '' ?>">3</span>
    </div>

    <?php if ($error): ?>
      <div class="vp-form-alert error" role="alert">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v5M12 16.5v.01"/></svg>
        <span><?= e($error) ?></span>
      </div>
    <?php endif; ?>

    <?php if ($step == 1): ?>
      <h1>Forgot your password?</h1>
      <p class="vp-setup-sub">Enter the email address you sign in with and we'll ask you a security question.</p>
      <form method="post" action="actions/recover.php" class="vp-setup-form">
        <?= csrf_field() ?>
        <input type="hidden" name="step" value="1">
        <div class="vp-field">
          <label for="rEmail">Email address</label>
          <input id="rEmail" name="email" type="text" inputmode="email" placeholder="you@email.com"
                 autocomplete="username" autocapitalize="none" autocorrect="off" spellcheck="false"
                 required autofocus>
        </div>
        <button type="submit" class="vp-btn-primary vp-setup-go">Continue</button>
      </form>

    <?php elseif ($step == 2): ?>
      <h1>Security question</h1>
      <p class="vp-setup-sub">Answer to confirm this account is yours.</p>
      <form method="post" action="actions/recover.php" class="vp-setup-form">
        <?= csrf_field() ?>
        <input type="hidden" name="step" value="2">
        <div class="vp-field">
          <label for="rAns"><?= e($question) ?></label>
          <input id="rAns" name="answer" autocomplete="off" required autofocus
                 placeholder="Your answer">
        </div>
        <p class="vp-hint-text">Answers aren't case-sensitive.</p>
        <button type="submit" class="vp-btn-primary vp-setup-go">Continue</button>
      </form>

    <?php else: ?>
      <h1>Choose a new password</h1>
      <p class="vp-setup-sub">Almost done — pick a password you'll remember.</p>
      <form method="post" action="actions/recover.php" class="vp-setup-form">
        <?= csrf_field() ?>
        <input type="hidden" name="step" value="3">
        <div class="vp-field">
          <label for="rPw">New password</label>
          <input id="rPw" type="password" name="password" autocomplete="new-password" required autofocus>
        </div>
        <div class="vp-field">
          <label for="rPw2">Confirm new password</label>
          <input id="rPw2" type="password" name="confirm" autocomplete="new-password" required>
        </div>
        <ul class="vp-pw-rules" id="vpPwRules" aria-live="polite">
          <?php foreach (password_checks('') as $label => $_): ?>
            <li data-rule="<?= e($label) ?>">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
              <span><?= e($label) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
        <button type="submit" class="vp-btn-primary vp-setup-go">Reset password</button>
      </form>
    <?php endif; ?>

    <a class="vp-setup-back" href="index.php">← Back to sign in</a>
  </div>
</div>

<script>
(function () {
  // Live password checklist (same rules as the server).
  var pw = document.getElementById('rPw');
  var rules = document.getElementById('vpPwRules');
  if (!pw || !rules) return;
  var tests = {
    'At least 8 characters':            function (v) { return v.length >= 8; },
    'An uppercase letter (A\u2013Z)':    function (v) { return /[A-Z]/.test(v); },
    'A lowercase letter (a\u2013z)':     function (v) { return /[a-z]/.test(v); },
    'A number (0\u20139)':               function (v) { return /[0-9]/.test(v); },
    'A special character (! @ # $ \u2026)': function (v) { return /[^A-Za-z0-9]/.test(v); }
  };
  function check() {
    rules.querySelectorAll('li').forEach(function (li) {
      var fn = tests[li.getAttribute('data-rule')];
      li.classList.toggle('ok', fn ? fn(pw.value) : false);
    });
  }
  pw.addEventListener('input', check);
  check();
})();

// Show/hide toggle for password fields (same behavior as the sign-in page).
(function () {
  var SHOW = '<svg class="vp-pw-show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>';
  var HIDE = '<svg class="vp-pw-hide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-10-8-10-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 10 8 10 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="2" y1="2" x2="22" y2="22"/></svg>';

  function attach(input) {
    if (!input || input.dataset.pwReady === '1') return;
    input.dataset.pwReady = '1';

    var wrap = document.createElement('span');
    wrap.className = 'vp-pw-wrap';
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);

    var btn = document.createElement('button');
    btn.type = 'button';            // never submits the form
    btn.className = 'vp-pw-toggle';
    btn.innerHTML = SHOW + HIDE;
    btn.setAttribute('aria-label', 'Show password');
    btn.setAttribute('title', 'Show password');
    btn.setAttribute('aria-pressed', 'false');

    function setState(visible) {
      input.type = visible ? 'text' : 'password';
      btn.classList.toggle('is-on', visible);
      var label = visible ? 'Hide password' : 'Show password';
      btn.setAttribute('aria-label', label);
      btn.setAttribute('title', label);
      btn.setAttribute('aria-pressed', visible ? 'true' : 'false');
    }

    function toggle(refocusInput) {
      var visible = input.type !== 'text';
      setState(visible);
      if (refocusInput) {
        input.focus();
        var end = input.value.length;
        // Changing `type` resets the caret; some browsers only apply a
        // new selection on the next tick, so the restore is deferred.
        setTimeout(function () {
          try { input.setSelectionRange(end, end); } catch (e) {}
        }, 0);
      }
    }

    btn.addEventListener('mousedown', function (ev) { ev.preventDefault(); });

    // Calling preventDefault() on touchstart (needed above, to keep the
    // input focused) also suppresses the synthetic "click" event most
    // mobile browsers would otherwise fire afterward — so the toggle has
    // to happen on touchend directly, or a real tap does nothing at all.
    var touchHandled = false;
    btn.addEventListener('touchstart', function (ev) { ev.preventDefault(); }, { passive: false });
    btn.addEventListener('touchend', function (ev) {
      ev.preventDefault();
      touchHandled = true;
      toggle(true);
      setTimeout(function () { touchHandled = false; }, 400);
    }, { passive: false });

    btn.addEventListener('click', function (ev) {
      if (touchHandled) return;
      ev.preventDefault();
      var viaKeyboard = ev.detail === 0;
      toggle(!viaKeyboard);
      if (viaKeyboard) btn.focus();
    });

    btn.addEventListener('keydown', function (ev) {
      if (ev.key === ' ' || ev.key === 'Spacebar' || ev.key === 'Enter') {
        ev.preventDefault();
        toggle(false);
        btn.focus();
      }
    });

    var form = input.form;
    if (form && !form.dataset.pwSubmitBound) {
      form.dataset.pwSubmitBound = '1';
      form.addEventListener('submit', function () {
        form.querySelectorAll('input[data-pw-ready="1"]').forEach(function (i) {
          if (i.type === 'text') i.type = 'password';
        });
        form.querySelectorAll('.vp-pw-toggle').forEach(function (b) {
          b.classList.remove('is-on');
          b.setAttribute('aria-pressed', 'false');
          b.setAttribute('aria-label', 'Show password');
          b.setAttribute('title', 'Show password');
        });
      });
    }

    wrap.appendChild(btn);
  }

  document.querySelectorAll('input[type="password"]').forEach(attach);
})();
</script>
</body>
</html>
