<?php
/* ============================================================
   Privacy Notice consent — shown at a person's first sign-in.
   Under the Data Privacy Act (RA 10173), the person themselves
   reads the notice and agrees before using the system. This is
   stronger evidence of consent than staff recording it on the
   client's behalf when the account was created.

   A standalone page (no app shell), so the header gate never
   loops back onto it.
   ============================================================ */
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_login();

$me = current_user();

// Already agreed to the current version? Nothing to force — go on in.
if (!needs_privacy_consent($me)) {
    redirect('dashboard.php');
}

$clinic  = defined('CLINIC_NAME') ? CLINIC_NAME : 'Paw Prints Veterinary Clinic';
$version = defined('PRIVACY_VERSION') ? PRIVACY_VERSION : '';
$flash       = get_flash();
$flashTarget = get_flash_target();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<script>(function(){try{var t=localStorage.getItem('pp-theme');if(t==='green'||t==='blue')document.documentElement.setAttribute('data-theme',t);}catch(e){}})();</script>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Privacy Notice · <?= e($clinic) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600;9..144,700&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
<link rel="stylesheet" href="assets/css/style.css?v=<?= @filemtime(__DIR__ . "/assets/css/style.css") ?>">
</head>
<body>
<div class="vp-setup-page">
  <div class="vp-setup-card">
    <div class="vp-setup-icon">
      <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg>
    </div>
    <h1>Before you continue</h1>
    <p class="vp-setup-sub">
      <?= e($clinic) ?> handles your personal data under the Data Privacy Act
      of 2012 (RA 10173). Please read the Privacy Notice and confirm your
      agreement to continue.
    </p>

    <?php if ($flash && $flashTarget === 'privacy-consent'): ?>
      <div class="vp-form-alert error"><?= e($flash) ?></div>
    <?php endif; ?>

    <!-- The notice summary, with a link to the full page. -->
    <div class="vp-consent-summary">
      <h2>Privacy Notice<?php if ($version): ?> <span>v<?= e($version) ?></span><?php endif; ?></h2>
      <ul>
        <li>We collect your name and contact details, and records about your pets, to provide veterinary care.</li>
        <li>We use them to identify you, keep medical records, follow up on care, and send appointment and vaccination reminders.</li>
        <li>Only authorised clinic staff can access your data. We do not sell it, and share it only to deliver a service you asked for or when required by law.</li>
        <li>You may access, correct, or delete your data, or withdraw consent, at any time.</li>
      </ul>
      <a href="privacy.php" target="_blank" rel="noopener" class="vp-consent-readfull">
        Read the full Privacy Notice
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M7 7h10v10"/></svg>
      </a>
    </div>

    <form method="post" action="actions/agree_privacy.php">
      <?= csrf_field() ?>
      <label class="vp-consent">
        <input type="checkbox" name="privacy_consent" value="1" required>
        <span>I have read and understood the Privacy Notice, and I consent to
          <?= e($clinic) ?> collecting and processing my personal data as
          described.</span>
      </label>
      <div class="vp-consent-actions">
        <a href="logout.php" class="vp-btn-ghost">Not now — sign out</a>
        <button type="submit" class="vp-btn-primary">I Agree &amp; Continue</button>
      </div>
    </form>
  </div>
</div>
</body>
</html>
