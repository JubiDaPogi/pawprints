<?php
/* ============================================================
   Public self-registration — pet owners only. Clinic staff accounts
   are never self-service; those are still provisioned by an admin
   from User Accounts (actions/create_user.php).
   ============================================================ */
require_once 'config/database.php';
require_once 'config/notify.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

if (is_logged_in()) redirect('dashboard.php');

$error = $_SESSION['signup_error'] ?? null;
unset($_SESSION['signup_error']);
$old = $_SESSION['signup_old'] ?? [];
unset($_SESSION['signup_old']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<!-- Apply a saved color theme before first paint, so switching pages
     never flashes back to red for a frame. -->
<script>(function(){try{var t=localStorage.getItem('pp-theme');if(t==='green'||t==='blue')document.documentElement.setAttribute('data-theme',t);}catch(e){}})();</script>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create account · Paw Prints Veterinary Clinic</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600;9..144,700&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
<link rel="icon" type="image/png" sizes="32x32" href="assets/favicon-32.png">
<link rel="alternate icon" href="assets/favicon.ico">
<link rel="apple-touch-icon" href="assets/favicon-180.png">
<link rel="stylesheet" href="assets/css/style.css?v=<?= @filemtime(__DIR__ . "/assets/css/style.css") ?>">
</head>
<body>
<div class="vp-setup-page">
  <div class="vp-setup-card">
    <div class="vp-setup-icon">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="8" r="4"/><path d="M2 21c0-4 3.1-6 7-6s7 2 7 6"/><path d="M19 8v6M22 11h-6"/></svg>
    </div>
    <h1>Create your account</h1>
    <p class="vp-setup-sub">For pet owners — register to book appointments and keep track of your pet's records.</p>

    <?php if ($error): ?>
      <div class="vp-form-alert error" role="alert">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v5M12 16.5v.01"/></svg>
        <span><?= e($error) ?></span>
      </div>
    <?php endif; ?>

    <form method="post" action="actions/register.php" class="vp-setup-form" id="signupForm">
      <?= csrf_field() ?>
      <div class="vp-form-grid">
        <div class="vp-field"><label>First name</label><input name="first_name" value="<?= e($old['first_name'] ?? '') ?>" placeholder="e.g. Juan" required autofocus></div>
        <div class="vp-field"><label>Middle name</label><input name="middle_name" value="<?= e($old['middle_name'] ?? '') ?>" placeholder="e.g. Reyes" required></div>
        <div class="vp-field"><label>Last name</label><input name="last_name" value="<?= e($old['last_name'] ?? '') ?>" placeholder="e.g. Santos" required></div>
        <div class="vp-field">
          <label>Email address</label>
          <input type="email" name="email" value="<?= e($old['email'] ?? '') ?>" placeholder="you@email.com"
                 autocomplete="username" autocapitalize="none" autocorrect="off" spellcheck="false" required>
        </div>
        <div class="vp-field"><label>Phone</label><input name="phone" value="<?= e($old['phone'] ?? '') ?>" placeholder="0917-000-0000" inputmode="numeric" maxlength="13" data-phone-mask required></div>
        <div class="vp-field vp-field-wide"><label>Home address</label><input name="address" value="<?= e($old['address'] ?? '') ?>" placeholder="e.g. Bantug, Roxas, Isabela" required></div>
        <div class="vp-field">
          <label>Password</label>
          <input type="password" name="password" id="suPw" autocomplete="new-password" required>
        </div>
        <div class="vp-field">
          <label>Confirm password</label>
          <input type="password" name="confirm_password" id="suPw2" autocomplete="new-password" required>
        </div>
      </div>

      <ul class="vp-pw-rules" id="suPwRules" aria-live="polite">
        <?php foreach (password_checks('') as $label => $_): ?>
          <li data-rule="<?= e($label) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
            <span><?= e($label) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>

      <!-- Data Privacy Act (RA 10173) consent. Required by the server
           before an account can be created. -->
      <label class="vp-consent">
        <input type="checkbox" name="privacy_consent" value="1" required>
        <span>I have read and agree to the
          <a href="privacy.php" target="_blank" rel="noopener">Privacy Notice</a>,
          and consent to <?= e(defined('CLINIC_NAME') ? CLINIC_NAME : 'the clinic') ?>
          collecting and processing my personal data to provide veterinary care.
          <small>Required by the Data Privacy Act of 2012.</small></span>
      </label>

      <button type="submit" class="vp-btn-primary vp-setup-go">Create account</button>
    </form>

    <a class="vp-setup-back" href="index.php">← Back to sign in</a>
  </div>
</div>

<script>
(function () {
  // Live password checklist (same rules as the server).
  var pw = document.getElementById('suPw');
  var pw2 = document.getElementById('suPw2');
  var rules = document.getElementById('suPwRules');
  if (!pw || !rules) return;
  var tests = {
    'At least 8 characters':            function (v) { return v.length >= 8; },
    'An uppercase letter (A–Z)':    function (v) { return /[A-Z]/.test(v); },
    'A lowercase letter (a–z)':     function (v) { return /[a-z]/.test(v); },
    'A number (0–9)':               function (v) { return /[0-9]/.test(v); },
    'A special character (! @ # $ …)': function (v) { return /[^A-Za-z0-9]/.test(v); }
  };
  function check() {
    rules.querySelectorAll('li').forEach(function (li) {
      var fn = tests[li.getAttribute('data-rule')];
      li.classList.toggle('ok', fn ? fn(pw.value) : false);
    });
  }
  pw.addEventListener('input', check);
  check();

  // Confirm-password match, checked live so it's caught before the
  // round trip to the server.
  function checkMatch() {
    pw2.setCustomValidity(pw2.value && pw2.value !== pw.value ? "Passwords don't match" : '');
  }
  pw.addEventListener('input', checkMatch);
  pw2.addEventListener('input', checkMatch);
})();
</script>

<!-- Show/hide toggle for password fields (same behavior as the sign-in page). -->
<script>
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
    btn.type = 'button';
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
        setTimeout(function () {
          try { input.setSelectionRange(end, end); } catch (e) {}
        }, 0);
      }
    }

    btn.addEventListener('mousedown', function (ev) { ev.preventDefault(); });

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

<!-- Phone number mask: inserts the dashes as you type, e.g.
     0917-555-0142. Digits are the only thing stored; the dashes are
     added for readability. -->
<script>
(function () {
  function digits(v) { return (v || '').replace(/\D/g, '').slice(0, 11); }

  function format(v) {
    var d = digits(v);
    if (d.length <= 4)  return d;
    if (d.length <= 7)  return d.slice(0, 4) + '-' + d.slice(4);
    return d.slice(0, 4) + '-' + d.slice(4, 7) + '-' + d.slice(7);
  }

  document.querySelectorAll('input[data-phone-mask]').forEach(function (input) {
    if (input.value) input.value = format(input.value);

    input.addEventListener('input', function () {
      var caret = input.selectionStart;
      var before = digits(input.value.slice(0, caret)).length;

      input.value = format(input.value);

      var pos = 0, seen = 0;
      while (pos < input.value.length && seen < before) {
        if (/\d/.test(input.value[pos])) seen++;
        pos++;
      }
      if (input.value[pos] === '-') pos++;
      try { input.setSelectionRange(pos, pos); } catch (e) {}
    });

    input.addEventListener('keydown', function (ev) {
      if (ev.key !== 'Backspace') return;
      var pos = input.selectionStart;
      if (pos === input.selectionEnd && pos > 0 && input.value[pos - 1] === '-') {
        ev.preventDefault();
        var v = input.value;
        input.value = format(v.slice(0, pos - 2) + v.slice(pos));
        var np = Math.max(0, pos - 2);
        try { input.setSelectionRange(np, np); } catch (e) {}
      }
    });
  });
})();
</script>
</body>
</html>
