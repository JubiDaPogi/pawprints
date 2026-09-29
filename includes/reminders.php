<?php
/* ============================================================
   Appointment reminders
   ------------------------------------------------------------
   Two reminders per scheduled appointment:
     • EMAIL  the day BEFORE  ("your appointment is tomorrow")
     • SMS    on the DAY of   ("your appointment is today")

   queue_reminders()  works out what is due and writes rows
   send_pending()     hands due rows to the channel and marks them

   Sending is deliberately pluggable: on a plain XAMPP install there
   is no SMTP server and no SMS gateway, so both channels fall back
   to logging. Point them at real services in config/notify.php when
   the clinic has accounts.
   ============================================================ */

require_once __DIR__ . '/audit.php';

/** Where the fallback log is written. */
function reminder_log_path() {
    return __DIR__ . '/../storage/reminders.log';
}

/**
 * Build the message text for a reminder.
 * Kept short for SMS — most gateways bill per 160 characters.
 */
function reminder_body($channel, $row) {
    $when = fmt_date($row['appt_date']);
    $time = $row['appt_time'] ? fmt_time($row['appt_time']) : '';
    $pet  = $row['pet_name'];
    $why  = $row['reason'] ?: 'a check-up';

    if ($channel === 'sms') {
        return "Paw Prints Vet: Reminder - {$pet} has an appointment TODAY"
             . ($time ? " at {$time}" : '') . " for {$why}. See you soon!";
    }

    $owner = $row['owner_name'];
    return "Hello {$owner},\n\n"
         . "This is a friendly reminder from Paw Prints Veterinary Clinic.\n\n"
         . "{$pet} has an appointment TOMORROW, {$when}"
         . ($time ? " at {$time}" : '') . ".\n"
         . "Reason: {$why}\n\n"
         . "Please arrive about 10 minutes early. If you need to reschedule,\n"
         . "just reply to this email or call the clinic.\n\n"
         . "Thank you,\n"
         . "Paw Prints Veterinary Clinic\n"
         . "Bantug, Roxas, Isabela";
}

/**
 * Queue any reminders that are now due.
 * Safe to run repeatedly — the UNIQUE key on (appointment_id, channel)
 * means an appointment can only ever be queued once per channel.
 */
function queue_reminders(PDO $pdo) {
    $today    = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $queued   = ['email' => 0, 'sms' => 0];

    // EMAIL — appointments happening tomorrow.
    // SMS   — appointments happening today.
    $plan = [
        ['email', $tomorrow],
        ['sms',   $today],
    ];

    foreach ($plan as [$channel, $date]) {
        $stmt = $pdo->prepare("
            SELECT a.id, a.appt_date, a.appt_time, a.reason,
                   p.name AS pet_name,
                   CONCAT_WS(' ', NULLIF(o.first_name,''), NULLIF(o.last_name,'')) AS owner_name,
                   o.email AS owner_email, o.phone AS owner_phone
            FROM appointments a
            JOIN patients p ON p.id = a.patient_id
            JOIN owners   o ON o.id = p.owner_id
            WHERE a.status = 'Scheduled'
              AND a.appt_date = ?
              AND p.deleted_at IS NULL
              AND NOT EXISTS (
                    SELECT 1 FROM reminders r
                    WHERE r.appointment_id = a.id AND r.channel = ?
              )
        ");
        $stmt->execute([$date, $channel]);

        foreach ($stmt->fetchAll() as $row) {
            $to = $channel === 'sms' ? trim((string)$row['owner_phone'])
                                     : trim((string)$row['owner_email']);
            // No contact details on file — nothing to send to.
            if ($to === '') continue;

            $ins = $pdo->prepare("
                INSERT IGNORE INTO reminders
                    (appointment_id, channel, send_on, recipient, body)
                VALUES (?,?,?,?,?)
            ");
            $ins->execute([
                (int)$row['id'], $channel, $date, $to,
                reminder_body($channel, $row),
            ]);
            if ($ins->rowCount() > 0) $queued[$channel]++;
        }
    }
    return $queued;
}

/** Deliver one email. Returns [ok, error]. */
function deliver_email($to, $body) {
    $subject = 'Appointment reminder - Paw Prints Veterinary Clinic';
    $headers = "From: Paw Prints Veterinary Clinic <no-reply@pawprints.vet>\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n";

    // A default XAMPP install has no mail server, so mail() will fail.
    // Log it instead of pretending it went out.
    if (!function_exists('mail') || !ini_get('SMTP')) {
        return [false, 'No SMTP configured — logged instead of sent'];
    }
    $ok = @mail($to, $subject, $body, $headers);
    return [$ok, $ok ? null : 'mail() returned false'];
}

/**
 * Deliver one SMS. Returns [ok, error].
 * Real delivery needs a gateway account (Semaphore, Twilio, etc).
 * Set SMS_API_KEY in config/notify.php to switch this on.
 */
function deliver_sms($to, $body) {
    $key = defined('SMS_API_KEY') ? SMS_API_KEY : '';
    if ($key === '') {
        return [false, 'No SMS gateway configured — logged instead of sent'];
    }

    // Example shape for a Philippine gateway; adjust to your provider.
    $ch = curl_init(defined('SMS_API_URL') ? SMS_API_URL : '');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_POSTFIELDS     => http_build_query([
            'apikey'  => $key,
            'number'  => preg_replace('/\D/', '', $to),
            'message' => $body,
            'sendername' => defined('SMS_SENDER') ? SMS_SENDER : 'PAWPRINTS',
        ]),
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($res === false)      return [false, 'Gateway unreachable: ' . $err];
    if ($code < 200 || $code >= 300) return [false, 'Gateway HTTP ' . $code];
    return [true, null];
}

/** Append to the fallback log so nothing is silently lost. */
function log_reminder($channel, $to, $body, $note) {
    $dir = dirname(reminder_log_path());
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $line = sprintf("[%s] %s -> %s (%s)\n%s\n%s\n",
        date('Y-m-d H:i:s'), strtoupper($channel), $to, $note, $body, str_repeat('-', 60));
    @file_put_contents(reminder_log_path(), $line, FILE_APPEND);
}

/**
 * Send everything that is pending and due today or earlier.
 * Each row is marked before the next is attempted, so a crash mid-run
 * can't cause duplicates.
 */
function send_pending(PDO $pdo, $limit = 50) {
    // Re-check the appointment at SEND time, not just when it was
    // queued. A reminder sits in the queue for up to a day, and in that
    // window the appointment may be cancelled, completed, or the patient
    // (or their owner's account) deleted. Sending anyway would text
    // somebody about a pet whose record no longer exists.
    $due = $pdo->prepare("
        SELECT r.*,
               a.id         AS appt_still_there,
               a.status     AS appt_status,
               a.appt_date  AS appt_date,
               p.deleted_at AS pet_deleted,
               o.id         AS owner_still_there
        FROM reminders r
        LEFT JOIN appointments a ON a.id = r.appointment_id
        LEFT JOIN patients     p ON p.id = a.patient_id
        LEFT JOIN owners       o ON o.id = p.owner_id
        WHERE r.status = 'pending' AND r.send_on <= CURDATE()
        ORDER BY r.send_on, r.id
        LIMIT {$limit}
    ");
    $due->execute();
    $rows = $due->fetchAll();

    $result = ['sent' => 0, 'logged' => 0, 'failed' => 0, 'cancelled' => 0];

    foreach ($rows as $r) {

        // Work out whether this reminder is still worth sending.
        $skip = null;
        if ($r['appt_still_there'] === null)          $skip = 'Appointment was deleted';
        elseif ($r['pet_deleted'] !== null)           $skip = 'Patient record was deleted';
        elseif ($r['owner_still_there'] === null)     $skip = 'Owner record was deleted';
        elseif ($r['appt_status'] === 'Completed')    $skip = 'Appointment already completed';
        elseif ($r['appt_date'] !== null && $r['appt_date'] < date('Y-m-d'))
                                                     $skip = 'Appointment date has passed';

        if ($skip !== null) {
            $pdo->prepare("UPDATE reminders SET status='cancelled', error=?, sent_at=NOW() WHERE id=?")
                ->execute([$skip, (int)$r['id']]);
            $result['cancelled']++;
            continue;
        }

        // A reminder can be sent late — most often because the patient was
        // deleted and then restored. The wording was written for its
        // original day, so "TOMORROW" would be wrong if the appointment
        // is now today. Rebuild the text against the real date.
        $body = $r['body'];
        if ($r['appt_date'] === date('Y-m-d') && $r['channel'] === 'email'
            && strpos($body, 'TOMORROW') !== false) {
            $body = str_replace(
                ['has an appointment TOMORROW,', 'appointment TOMORROW'],
                ['has an appointment TODAY,', 'appointment TODAY'],
                $body
            );
            $pdo->prepare("UPDATE reminders SET body = ? WHERE id = ?")
                ->execute([$body, (int)$r['id']]);
            $r['body'] = $body;
        }

        [$ok, $err] = $r['channel'] === 'sms'
            ? deliver_sms($r['recipient'], $r['body'])
            : deliver_email($r['recipient'], $r['body']);

        if ($ok) {
            $pdo->prepare("UPDATE reminders SET status='sent', sent_at=NOW(), error=NULL WHERE id=?")
                ->execute([(int)$r['id']]);
            $result['sent']++;
            continue;
        }

        // Not configured yet: record it as sent-to-log rather than a
        // failure, so the queue doesn't fill with red rows on XAMPP.
        if (strpos((string)$err, 'logged instead of sent') !== false) {
            log_reminder($r['channel'], $r['recipient'], $r['body'], $err);
            $pdo->prepare("UPDATE reminders SET status='sent', sent_at=NOW(), error=? WHERE id=?")
                ->execute([$err, (int)$r['id']]);
            $result['logged']++;
            continue;
        }

        $pdo->prepare("UPDATE reminders SET status='failed', error=? WHERE id=?")
            ->execute([mb_substr((string)$err, 0, 255), (int)$r['id']]);
        $result['failed']++;
    }
    return $result;
}

/**
 * Queue then send. Called by cron.php (the proper way) and, as a
 * fallback, once a day from header.php so a demo install still sends
 * without a scheduled task configured.
 */
function run_reminders(PDO $pdo) {
    $q = queue_reminders($pdo);
    $s = send_pending($pdo);
    return $q + $s;
}
