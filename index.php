<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Already signed in? Go straight to the dashboard.
if (is_logged_in()) redirect('dashboard.php');

$error = $_SESSION['login_error'] ?? null;
unset($_SESSION['login_error']);
$isNotice = !empty($_SESSION['login_notice']);
unset($_SESSION['login_notice']);
$prefill = $_SESSION['login_prefill'] ?? '';
unset($_SESSION['login_prefill']);

// A single sign-in form serves both clinic staff and pet owners; the
// account's own role decides which workspace they land in.
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<!-- Apply a saved color theme before first paint, so switching pages
     never flashes back to red for a frame. -->
<script>(function(){try{var t=localStorage.getItem('pp-theme');if(t==='green'||t==='blue')document.documentElement.setAttribute('data-theme',t);}catch(e){}})();</script>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign in · Paw Prints Veterinary Clinic</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600;9..144,700&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
<link rel="icon" type="image/png" sizes="32x32" href="assets/favicon-32.png">
<link rel="alternate icon" href="assets/favicon.ico">
<link rel="apple-touch-icon" href="assets/favicon-180.png">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="vp-login">

  <div class="vp-theme-switch vp-theme-switch-login" role="group" aria-label="Color theme">
    <button type="button" class="vp-theme-dot" data-theme-choice="red" aria-label="Red theme"></button>
    <button type="button" class="vp-theme-dot" data-theme-choice="green" aria-label="Green theme"></button>
    <button type="button" class="vp-theme-dot" data-theme-choice="blue" aria-label="Blue theme"></button>
  </div>

  <!-- Left art panel -->
  <div class="vp-login-art" aria-hidden="true">
    <div class="vp-paws">
      <?php for ($i = 0; $i < 14; $i++): ?>
        <svg class="vp-paw vp-paw-<?= $i ?>" viewBox="0 0 24 24" fill="currentColor"><circle cx="6" cy="9" r="1.6"/><circle cx="10" cy="6.5" r="1.6"/><circle cx="14" cy="6.5" r="1.6"/><circle cx="18" cy="9" r="1.6"/><path d="M8 15c0-2.5 1.8-4 4-4s4 1.5 4 4c0 1.8-1.6 2.6-4 2.6S8 16.8 8 15z"/></svg>
      <?php endfor; ?>
    </div>
    <div class="vp-login-brand">
      <div class="vp-logo-badge"><svg width="30" height="30" viewBox="0 0 24 24" fill="currentColor"><circle cx="6" cy="9" r="1.6"/><circle cx="10" cy="6.5" r="1.6"/><circle cx="14" cy="6.5" r="1.6"/><circle cx="18" cy="9" r="1.6"/><path d="M8 15c0-2.5 1.8-4 4-4s4 1.5 4 4c0 1.8-1.6 2.6-4 2.6S8 16.8 8 15z"/></svg></div>
      <h1>Paw Prints<span>Veterinary Clinic</span></h1>
      <p class="vp-login-tag">Web-Based Patient Tracking System</p>
      <ul class="vp-login-points">
        <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/></svg> Every chart, visit, and vaccine in one place</li>
        <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg> Records kept secure, backed up, and accessible</li>
        <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.5 1-1a5.5 5.5 0 0 0 0-7.9z"/></svg> Better continuity of care for every patient</li>
      </ul>
    </div>
    <p class="vp-login-loc">Bantug, Roxas, Isabela · Est. record system 2026</p>
  </div>

  <!-- Right form panel -->
  <div class="vp-login-panel">
    <div class="vp-login-card">
      <h2>Sign in</h2>
      <p class="vp-login-sub">Access your account here.</p>

      <div class="vp-login-error<?= $isNotice ? ' success' : '' ?>" id="vpLoginError" role="alert"<?= $error ? '' : ' hidden' ?>>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" id="vpLoginErrorIcon"><?= $isNotice ? '<path d="M20 6 9 17l-5-5"/>' : '<circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/>' ?></svg>
        <span id="vpLoginErrorText"><?= e($error ?? '') ?></span>
      </div>

      <form method="post" action="actions/login.php" class="vp-login-form" id="vpLoginForm">
        <label for="vpUser">Email address</label>
        <input id="vpUser" name="email" type="text" inputmode="email" placeholder="you@email.com"
               value="<?= e($prefill) ?>" autocomplete="username" autocapitalize="none"
               autocorrect="off" spellcheck="false" required autofocus>
        <label for="vpPass">Password</label>
        <input id="vpPass" type="password" name="password" placeholder="••••••••"
               autocomplete="current-password" required>
        <a class="vp-forgot-link" href="forgot.php">Forgot your password?</a>
        <button type="submit" class="vp-btn-primary vp-login-go">
          Sign in
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg>
        </button>
      </form>

    </div>
  </div>
</div>



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
    btn.type = 'button';            // never submits the form
    btn.className = 'vp-pw-toggle';
    btn.innerHTML = SHOW + HIDE;
    btn.setAttribute('aria-label', 'Show password');
    btn.setAttribute('title', 'Show password');
    // aria-pressed tells screen readers this is a two-state control.
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

    // Pointer/touch: don't let the press steal focus from the input,
    // which on mobile would dismiss the on-screen keyboard.
    btn.addEventListener('mousedown', function (ev) { ev.preventDefault(); });

    // Calling preventDefault() on touchstart (needed above, to keep the
    // input focused) also suppresses the synthetic "click" event most
    // mobile browsers would otherwise fire afterward — so the toggle has
    // to happen on touchend directly, or a real tap does nothing at all.
    // touchHandled guards against the rare browser that still fires a
    // click on top of this.
    var touchHandled = false;
    btn.addEventListener('touchstart', function (ev) { ev.preventDefault(); }, { passive: false });
    btn.addEventListener('touchend', function (ev) {
      ev.preventDefault();
      touchHandled = true;
      toggle(true);
      setTimeout(function () { touchHandled = false; }, 400);
    }, { passive: false });

    // Click covers mouse and Enter/Space on a real <button>.
    btn.addEventListener('click', function (ev) {
      if (touchHandled) return;
      ev.preventDefault();
      // If activated by keyboard, keep focus on the button so the user
      // can toggle again; otherwise return the caret to the input.
      var viaKeyboard = ev.detail === 0;
      toggle(!viaKeyboard);
      if (viaKeyboard) btn.focus();
    });

    // Explicit Space handling: browsers scroll the page on Space
    // unless we intercept it here.
    btn.addEventListener('keydown', function (ev) {
      if (ev.key === ' ' || ev.key === 'Spacebar' || ev.key === 'Enter') {
        ev.preventDefault();
        toggle(false);
        btn.focus();
      }
    });

    // Re-mask before submit so a visible password is never left on
    // screen after navigation, and never cached as type=text.
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

  function scan(root) {
    (root || document).querySelectorAll('input[type="password"]').forEach(attach);
  }

  scan();

  // Re-scan when a modal opens, since those inputs may have been
  // hidden (or added) after the first pass.
  document.querySelectorAll('[data-open-modal]').forEach(function (b) {
    b.addEventListener('click', function () { setTimeout(scan, 0); });
  });

  // Safety net: if any password field is injected later, catch it.
  if (window.MutationObserver) {
    new MutationObserver(function (muts) {
      for (var i = 0; i < muts.length; i++) {
        if (muts[i].addedNodes.length) { scan(); return; }
      }
    }).observe(document.body, { childList: true, subtree: true });
  }
})();
</script>

<!-- Color theme switcher. -->
<script>
(function () {
  var switches = document.querySelectorAll('.vp-theme-switch');
  if (!switches.length) return;
  var current = document.documentElement.getAttribute('data-theme') || 'red';

  function apply(theme) {
    if (theme === 'red') document.documentElement.removeAttribute('data-theme');
    else document.documentElement.setAttribute('data-theme', theme);
    try { localStorage.setItem('pp-theme', theme); } catch (e) {}
    switches.forEach(function (group) {
      group.querySelectorAll('.vp-theme-dot').forEach(function (dot) {
        dot.setAttribute('aria-pressed', dot.dataset.themeChoice === theme ? 'true' : 'false');
      });
    });
  }

  switches.forEach(function (group) {
    group.querySelectorAll('.vp-theme-dot').forEach(function (dot) {
      dot.setAttribute('aria-pressed', dot.dataset.themeChoice === current ? 'true' : 'false');
      dot.addEventListener('click', function () { apply(dot.dataset.themeChoice); });
    });
  });
})();
</script>

<script>
// Submit the sign-in form in the background so a failed attempt just
// updates the message on this page instead of reloading it. Without
// fetch() support the form is left alone and posts normally — the
// handler still works, it just does a full-page redirect back here.
(function () {
  var form = document.getElementById('vpLoginForm');
  if (!form || !window.fetch) return;

  var banner  = document.getElementById('vpLoginError');
  var text    = document.getElementById('vpLoginErrorText');
  var btn     = form.querySelector('.vp-login-go');
  var btnHTML = btn ? btn.innerHTML : '';
  var hideTimer = null;   // the 5s "time to start hiding" clock
  var fadeTimer = null;   // the 300ms fade-out itself, once it starts

  function clearTimers() {
    clearTimeout(hideTimer);
    clearTimeout(fadeTimer);
  }

  function showError(message) {
    clearTimers();
    banner.style.transition = '';
    banner.style.opacity = '';
    // showError() is only ever used for genuine failures, so it always
    // reverts the banner from the green "success" look a page-load
    // notice (e.g. "password reset") may have left it in.
    banner.classList.remove('success');
    var icon = document.getElementById('vpLoginErrorIcon');
    if (icon) icon.innerHTML = '<circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/>';
    text.textContent = message;
    banner.hidden = false;
    hideTimer = setTimeout(hideError, 5000);
  }

  function hideError() {
    clearTimers();
    banner.style.transition = 'opacity .3s';
    banner.style.opacity = '0';
    fadeTimer = setTimeout(function () {
      banner.hidden = true;
      banner.style.transition = '';
      banner.style.opacity = '';
    }, 300);
  }

  // A message already showing on load (e.g. a no-JS fallback submission
  // that landed back here) disappears on the same 5-second clock.
  if (!banner.hidden) hideTimer = setTimeout(hideError, 5000);

  function setBusy(busy) {
    if (!btn) return;
    btn.disabled = busy;
    btn.innerHTML = busy ? 'Signing in…' : btnHTML;
  }

  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    clearTimers();
    setBusy(true);

    fetch(form.getAttribute('action'), {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: new FormData(form)
    })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (data && data.ok) {
          window.location.href = data.redirect || 'dashboard.php';
          return; // leave the button disabled through the navigation
        }
        setBusy(false);
        showError((data && data.error) || 'Incorrect email or password. Please try again.');
      })
      .catch(function () {
        setBusy(false);
        showError('Something went wrong. Please check your connection and try again.');
      });
  });
})();
</script>

</body>
</html>
