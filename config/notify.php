<?php
/* ============================================================
   Notification settings
   ------------------------------------------------------------
   Out of the box both channels fall back to writing
   storage/reminders.log, so the whole flow can be demonstrated
   on XAMPP without any external account.

   To send for real, fill these in — step-by-step instructions
   are in SETUP-NOTIFICATIONS.md in the project root.
   ============================================================ */

/* ---------- SMS ----------------------------------------------------
   Needs a paid gateway. In the Philippines, Semaphore and Movider are
   common; Twilio works internationally. Sign up, get an API key, then:

       define('SMS_API_KEY', 'your-key-here');
       define('SMS_API_URL', 'https://api.semaphore.co/api/v4/messages');
       define('SMS_SENDER',  'PAWPRINTS');

   Leave SMS_API_KEY empty to keep logging instead of sending.
------------------------------------------------------------------- */
define('SMS_API_KEY', '');
define('SMS_API_URL', 'https://api.semaphore.co/api/v4/messages');
define('SMS_SENDER',  'PAWPRINTS');

/* ---------- EMAIL --------------------------------------------------
   PHP's mail() needs an SMTP server. XAMPP ships without one, so email
   is logged until you configure it.

   The simplest route on XAMPP is to point php.ini at an SMTP relay:

       [mail function]
       SMTP     = smtp.gmail.com
       smtp_port = 587
       sendmail_from = clinic@pawprints.vet

   Gmail also requires an app password rather than your normal one.
   For anything beyond testing, PHPMailer with SMTP auth is the
   sturdier choice.
------------------------------------------------------------------- */
define('CLINIC_FROM_EMAIL', 'no-reply@pawprints.vet');
define('CLINIC_NAME',       'Paw Prints Veterinary Clinic');
