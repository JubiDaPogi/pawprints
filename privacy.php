<?php
/* ============================================================
   Privacy Notice (Data Privacy Act of 2012, RA 10173)
   ------------------------------------------------------------
   A standalone, publicly viewable page — it must be readable
   BEFORE anyone agrees, so it does not require a login. The
   consent checkbox on the account form links here.

   IMPORTANT: the text below is a practical starting point, not
   legal advice. Have it reviewed by a lawyer or your Data
   Protection Officer and adjust to your clinic's actual
   practices before relying on it.
   ============================================================ */
require_once 'config/database.php';
require_once 'includes/functions.php';

$clinic  = defined('CLINIC_NAME') ? CLINIC_NAME : 'Paw Prints Veterinary Clinic';
$address = defined('CLINIC_ADDRESS') ? CLINIC_ADDRESS : 'Bantug, Roxas, Isabela';
$email   = defined('PRIVACY_CONTACT_EMAIL') ? PRIVACY_CONTACT_EMAIL : '';
$phone   = defined('PRIVACY_CONTACT_PHONE') ? PRIVACY_CONTACT_PHONE : '';
$version = defined('PRIVACY_VERSION') ? PRIVACY_VERSION : '';
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
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="vp-doc-page">
  <article class="vp-doc">
    <header class="vp-doc-head">
      <div class="vp-doc-brand">
        <span class="vp-doc-logo"><?= species_icon('paw', 26) ?></span>
        <div>
          <strong><?= e($clinic) ?></strong>
          <span><?= e($address) ?></span>
        </div>
      </div>
      <h1>Privacy Notice</h1>
      <p class="vp-doc-meta">
        In accordance with the Data Privacy Act of 2012 (Republic Act No. 10173).
        <?php if ($version): ?><br>Notice version <?= e($version) ?>.<?php endif; ?>
      </p>
    </header>

    <section>
      <h2>1. Who we are</h2>
      <p><?= e($clinic) ?> (&ldquo;the Clinic,&rdquo; &ldquo;we,&rdquo; &ldquo;us&rdquo;),
      located at <?= e($address) ?>, is the personal information controller
      responsible for the personal data described in this notice.</p>
    </section>

    <section>
      <h2>2. What personal data we collect</h2>
      <p>To register you as a client and care for your animals, we collect:</p>
      <ul>
        <li>Your name and contact details (address, phone number, email address);</li>
        <li>Records about your pets (species, breed, age, and medical history); and</li>
        <li>Account and activity information needed to operate this system securely.</li>
      </ul>
    </section>

    <section>
      <h2>3. Why we process it</h2>
      <p>We use your personal data to:</p>
      <ul>
        <li>Identify you as a client and maintain your pets' medical records;</li>
        <li>Provide veterinary services you request and follow up on care;</li>
        <li>Contact you about appointments, results, and vaccination reminders; and</li>
        <li>Keep records the Clinic is required to keep and secure our systems.</li>
      </ul>
    </section>

    <section>
      <h2>4. Legal basis</h2>
      <p>We process your data based on your consent, on the performance of the
      veterinary services you ask us to provide, and on our compliance with
      legal obligations. You may withdraw consent for optional processing
      (such as marketing messages) at any time; this does not affect
      processing necessary to provide care or to meet our legal duties.</p>
    </section>

    <section>
      <h2>5. Who can see your data</h2>
      <p>Your data is accessed only by authorised Clinic staff who need it to
      do their work. We do not sell your personal data. We share it with
      third parties only when necessary to deliver a service you requested,
      or when required by law.</p>
    </section>

    <section>
      <h2>6. How long we keep it</h2>
      <p>We keep your data for as long as you remain a client and for a
      reasonable period afterwards to meet record-keeping and legal
      requirements, after which it is securely deleted or anonymised.</p>
    </section>

    <section>
      <h2>7. How we protect it</h2>
      <p>We apply reasonable organisational, physical, and technical measures
      to keep your data safe, including access controls, secure passwords,
      and an activity log of who accesses records.</p>
    </section>

    <section>
      <h2>8. Your rights</h2>
      <p>Under the Data Privacy Act you have the right to be informed, to
      access your data, to correct inaccurate data, to object to or restrict
      processing, to have your data deleted or blocked where allowed, to data
      portability, and to be indemnified for damages. You may also lodge a
      complaint with the National Privacy Commission (privacy.gov.ph).</p>
    </section>

    <section>
      <h2>9. How to reach us</h2>
      <p>To exercise your rights, withdraw consent, or ask questions about this
      notice, contact our Data Protection Officer:</p>
      <ul>
        <?php if ($email): ?><li>Email: <?= e($email) ?></li><?php endif; ?>
        <?php if ($phone): ?><li>Phone: <?= e($phone) ?></li><?php endif; ?>
        <li>Address: <?= e($clinic) ?>, <?= e($address) ?></li>
      </ul>
    </section>

    <footer class="vp-doc-foot">
      <a href="javascript:void(0)" class="vp-doc-back" onclick="window.history.length > 1 ? window.history.back() : window.close()">&larr; Back</a>
      <button type="button" class="vp-doc-print" onclick="window.print()">Print this notice</button>
    </footer>
  </article>
</div>
</body>
</html>
