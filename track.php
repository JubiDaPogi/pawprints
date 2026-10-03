<?php
/* ============================================================
   Public "View appointment" — no sign-in needed. An owner enters the
   appointment code they were given plus the email address on their
   account; the appointment is shown only when BOTH match.

   Safeguards (this page is open to anyone):
   - one generic "not found" message — it never says whether the code or
     the email was the wrong half, so neither can be probed on its own;
   - failed tries are logged per IP and the page locks for a while after
     too many;
   - only appointment details are shown — never the owner's phone,
     address or any medical information.
   ============================================================ */
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/audit.php';

const TRACK_MAX_FAILS   = 8;    // failed lookups allowed ...
const TRACK_WINDOW_MIN  = 15;   // ... within this many minutes, per IP

/** "apt20261020001" / "APT 20261020 001" → "APT-20261020-001". */
function normalize_appt_code($raw) {
    $c = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$raw));
    if (preg_match('/^APT(\d{8})(\d{3,})$/', $c, $m)) return 'APT-' . $m[1] . '-' . $m[2];
    return $c;
}

expire_pending_appointments($pdo);   // an unapproved request past its time shows as Expired

$ip     = $_SERVER['REMOTE_ADDR'] ?? '';
$code   = '';
$email  = '';
$error  = null;
$found  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
        $error = 'Please try that again.';
    } else {
        $code  = normalize_appt_code($_POST['code'] ?? '');
        $email = trim((string)($_POST['email'] ?? ''));

        // Too many wrong guesses from this address recently?
        $locked = false;
        try {
            $st = $pdo->prepare("SELECT COUNT(*) FROM activity_log WHERE action = 'appt_lookup_failed' AND ip_address = ?
                                 AND created_at > (NOW() - INTERVAL " . (int)TRACK_WINDOW_MIN . " MINUTE)");
            $st->execute([$ip]);
            $locked = (int)$st->fetchColumn() >= TRACK_MAX_FAILS;
        } catch (Throwable $e) {}

        if ($locked) {
            $error = 'Too many tries. Please wait ' . TRACK_WINDOW_MIN . ' minutes and try again, or call the clinic.';
        } elseif ($code === '' || $email === '') {
            $error = 'Please enter both your appointment code and your email address.';
        } else {
            $st = $pdo->prepare("SELECT a.*, p.name AS pet_name, p.species, p.breed
                                 FROM appointments a
                                 JOIN patients p ON p.id = a.patient_id
                                 JOIN owners o   ON o.id = p.owner_id
                                 WHERE a.appt_code = ? AND LOWER(o.email) = LOWER(?) AND p.deleted_at IS NULL
                                 LIMIT 1");
            $st->execute([$code, $email]);
            $found = $st->fetch() ?: null;
            if (!$found) {
                record_audit($pdo, 'appt_lookup_failed', null, null, 'Appointment lookup — no match',
                             ['id' => null, 'username' => mb_substr($email, 0, 100)]);
                $error = 'We couldn\'t find an appointment with that code and email address. Please check both and try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<script>(function(){try{var t=localStorage.getItem('pp-theme');if(t==='green'||t==='blue')document.documentElement.setAttribute('data-theme',t);}catch(e){}})();</script>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title>View appointment · Paw Prints Veterinary Clinic</title>
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
  <div class="vp-setup-card vp-track-card">
    <div class="vp-setup-icon">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4.5" width="18" height="16" rx="2"/><path d="M3 9h18M8 2.5v4M16 2.5v4"/></svg>
    </div>
    <h1>View appointment</h1>
    <p class="vp-setup-sub">Enter the appointment code you were given and the email address on your account — no sign-in needed.</p>

    <?php if ($error): ?>
      <div class="vp-form-alert error" role="alert">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
        <span><?= e($error) ?></span>
      </div>
    <?php endif; ?>

    <?php if ($found): ?>
      <?php
        $sub = $found['status'];
        $when = fmt_date($found['appt_date']) . ' at ' . appt_time_range($found['appt_time']);
      ?>
      <div class="vp-track-result">
        <div class="vp-track-top">
          <button type="button" class="vp-appt-code" data-copy-code="<?= e($found['appt_code']) ?>" title="Click to copy this code"># <?= e($found['appt_code']) ?></button>
          <?= status_pill($found['status'], true) ?>
        </div>
        <dl class="vp-track-list">
          <dt>Pet</dt><dd><?= e($found['pet_name']) ?> <span class="vp-track-dim">· <?= e($found['species']) ?><?= !empty($found['breed']) ? ' · ' . e($found['breed']) : '' ?></span></dd>
          <dt>Date</dt><dd><?= e(date('l, F j, Y', strtotime($found['appt_date']))) ?></dd>
          <dt>Time</dt><dd><?= e(appt_time_range($found['appt_time'])) ?></dd>
          <dt>Reason</dt><dd><?= e($found['reason'] ?: '—') ?></dd>
          <?php if ($found['status'] === 'Declined' && !empty($found['decline_reason'])): ?>
            <dt>Clinic note</dt><dd><?= e($found['decline_reason']) ?></dd>
          <?php endif; ?>
          <?php if ($found['status'] === 'Cancelled'): ?>
            <dt>Cancelled</dt><dd><?= !empty($found['cancelled_at']) ? e(fmt_date($found['cancelled_at'])) : '' ?><?= !empty($found['cancel_reason']) ? ' — ' . e($found['cancel_reason']) : '' ?></dd>
          <?php endif; ?>
        </dl>
        <p class="vp-track-foot">
          <?php if ($found['status'] === 'Scheduled'): ?>
            Please arrive a few minutes early. To change or cancel, <a href="index.php">sign in</a> to your account.
          <?php elseif ($found['status'] === 'Expired'): ?>
            This request wasn't approved before the appointment time. <a href="index.php">Sign in</a> to send a new request.
          <?php elseif ($found['status'] === 'Completed'): ?>
            This visit has been completed. <a href="index.php">Sign in</a> to see your pet's records.
          <?php else: ?>
            <a href="index.php">Sign in</a> to request a new appointment.
          <?php endif; ?>
        </p>
      </div>
      <a class="vp-btn-ghost vp-track-again" href="track.php">Look up another appointment</a>
    <?php else: ?>
      <form method="post" action="track.php" class="vp-setup-form" id="trackForm" autocomplete="off">
        <?= csrf_field() ?>
        <div class="vp-field vp-field-wide">
          <label for="trackCode">Appointment code</label>
          <input id="trackCode" name="code" value="<?= e($code) ?>" placeholder="APT-YYYYMMDD-000" autocapitalize="characters"
                 autocorrect="off" spellcheck="false" maxlength="20" required autofocus>
        </div>
        <div class="vp-field vp-field-wide">
          <label for="trackEmail">Email address</label>
          <input id="trackEmail" type="email" name="email" value="<?= e($email) ?>" placeholder="you@email.com"
                 autocapitalize="none" autocorrect="off" spellcheck="false" required>
        </div>
        <p class="vp-hint-text">Your code is given when the clinic approves your request, so a request that is still pending doesn't have one yet.</p>
        <button type="submit" class="vp-btn-primary vp-setup-go">View appointment</button>
      </form>
    <?php endif; ?>

    <a class="vp-setup-back" href="index.php">← Back to sign in</a>
  </div>
</div>

<script>
// Capitals + dashes as the code is typed (APT-YYYYMMDD-001).
(function () {
  var input = document.getElementById('trackCode');
  if (!input) return;
  // A code is "apt" + digits, possibly after a "#" or spaces (the list shows
  // codes as "# APT-…"). Anything after the digits is dropped.
  function isCode(v) { return /^[\s#]*apt[\s\-]*[\d\s\-]*$/i.test(v); }
  function format(raw) {
    var m = raw.match(/apt([\s\-]*[\d\s\-]*)/i), rest = m ? m[1] : '';
    var d = rest.replace(/\D/g, '').slice(0, 11), tail = /[\s\-]$/.test(rest);
    if (!d.length) return tail ? 'APT-' : 'APT';
    if (d.length < 8) return 'APT-' + d;
    if (d.length === 8) return 'APT-' + d + (tail ? '-' : '');
    return 'APT-' + d.slice(0, 8) + '-' + d.slice(8);
  }
  function setVal(f) { input.value = f; input.setSelectionRange(f.length, f.length); }
  input.addEventListener('input', function () {
    if (isCode(input.value)) {
      var f = format(input.value);
      if (f !== input.value) setVal(f);
    }
  });
  // Pasting: find the code anywhere in the pasted text and keep just that
  // ("# APT-20260723-001 Scheduled" → "APT-20260723-001").
  input.addEventListener('paste', function (e) {
    var text = (e.clipboardData || window.clipboardData).getData('text') || '';
    if (!/apt[\s\-]*\d/i.test(text)) return;          // not a code — paste normally
    e.preventDefault();
    var m = text.match(/apt[\s\-]*\d[\d\s\-]*/i);
    setVal(format(m[0]));
    input.dispatchEvent(new Event('input', { bubbles: true }));
  });
})();
</script>
<script>
// Click an appointment code to copy it (just the code, e.g. APT-20260723-001).
(function () {
  function legacyCopy(t) {                              // works where the clipboard API isn't allowed
    return new Promise(function (ok, fail) {
      var ta = document.createElement('textarea');
      ta.value = t; ta.setAttribute('readonly', ''); ta.style.cssText = 'position:fixed;left:-9999px;top:0;opacity:0';
      document.body.appendChild(ta); ta.select();
      var done = false;
      try { done = document.execCommand('copy'); } catch (e) {}
      document.body.removeChild(ta);
      done ? ok() : fail();
    });
  }
  function copyText(t) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(t).catch(function () { return legacyCopy(t); });
    }
    return legacyCopy(t);
  }
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-copy-code]');
    if (!b) return;
    e.preventDefault(); e.stopPropagation();           // never follow a surrounding link
    var code = b.getAttribute('data-copy-code'), label = b.getAttribute('data-label') || b.textContent;
    b.setAttribute('data-label', label);
    copyText(code).then(function () { b.classList.add('is-copied'); b.textContent = 'Copied!'; })
                  .catch(function () { b.classList.add('is-copied'); b.textContent = 'Press Ctrl+C'; });
    clearTimeout(b._t);
    b._t = setTimeout(function () { b.classList.remove('is-copied'); b.textContent = label; }, 1400);
  });
})();</script>
</body>
</html>
