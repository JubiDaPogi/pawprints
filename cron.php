<?php
/* ============================================================
   Appointment reminder runner.
   ------------------------------------------------------------
   Sends owners their reminders without anyone signing in:
     • email the day BEFORE their appointment
     • SMS   on the DAY of the appointment

   Run it once a day. On Windows/XAMPP, Task Scheduler:

       Program:   C:\xampp\php\php.exe
       Arguments: C:\xampp\htdocs\pawprints\cron.php
       Trigger:   daily, 7:00 AM

   On Linux, crontab:

       0 7 * * * /usr/bin/php /var/www/pawprints/cron.php

   It is safe to run more than once a day — a UNIQUE key on
   (appointment_id, channel) means nobody is messaged twice.
   ============================================================ */

// Only from the command line, or with the key below. Otherwise anyone
// who found the URL could trigger the run.
define('CRON_KEY', 'pawprints-reminders');

$viaCli = (PHP_SAPI === 'cli');
$viaKey = isset($_GET['key']) && hash_equals(CRON_KEY, (string)$_GET['key']);

if (!$viaCli && !$viaKey) {
    http_response_code(403);
    exit("Forbidden. Run this from the command line, or append ?key=...\n");
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/notify.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/audit.php';
require_once __DIR__ . '/includes/reminders.php';

$start = microtime(true);

try {
    $queued = queue_reminders($pdo);
    $sent   = send_pending($pdo);
} catch (Throwable $e) {
    if (!$viaCli) http_response_code(500);
    echo "Reminder run failed: " . $e->getMessage() . "\n";
    exit(1);
}

$newly = (int)($queued['email'] ?? 0) + (int)($queued['sms'] ?? 0);
$parts = [];
if ($newly)                  $parts[] = $newly . ' queued';
if (!empty($sent['sent']))   $parts[] = $sent['sent'] . ' sent';
if (!empty($sent['logged'])) $parts[] = $sent['logged'] . ' logged (no gateway configured)';
if (!empty($sent['failed'])) $parts[] = $sent['failed'] . ' failed';
if (!empty($sent['cancelled'])) $parts[] = $sent['cancelled'] . ' cancelled (record deleted or appointment changed)';
$summary = $parts ? implode(', ', $parts) : 'nothing due';

// Only write an audit entry when something actually happened, so a
// daily run doesn't bury the log in "nothing due".
if ($parts) {
    record_audit($pdo, 'reminders_run', null, null,
        'Automatic reminder run — ' . $summary,
        ['id' => null, 'username' => 'system']);
}

// Mark the day as done so the in-app fallback doesn't run it again.
@file_put_contents(__DIR__ . '/storage/.reminders-ran', date('Y-m-d'));

printf("[%s] %s (%.2fs)\n", date('Y-m-d H:i:s'), $summary, microtime(true) - $start);
