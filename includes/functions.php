<?php
/* ============================================================
   Shared helper functions
   ============================================================ */

/* ------------------------------------------------------------
   Data Privacy Act (RA 10173) — consent configuration.
   PRIVACY_VERSION identifies the edition of the Privacy Notice a
   person agreed to. Bump it (e.g. '2026-02') whenever the notice
   text in privacy.php changes so you can tell who agreed to which
   version and re-prompt if you ever need fresh consent.
   The contact details below appear on the notice and are the
   channel for data-subject requests (access, correction, deletion,
   withdrawal of consent).
   ------------------------------------------------------------ */
if (!defined('PRIVACY_VERSION'))       define('PRIVACY_VERSION', '2026-01');
if (!defined('PRIVACY_CONTACT_EMAIL')) define('PRIVACY_CONTACT_EMAIL', 'privacy@pawprints.vet');
if (!defined('PRIVACY_CONTACT_PHONE')) define('PRIVACY_CONTACT_PHONE', '(078) 000-0000');
if (!defined('CLINIC_ADDRESS'))        define('CLINIC_ADDRESS', 'Bantug, Roxas, Isabela');

/** The app build shown in the sidebar and on the sign-in page. Bump this
 *  when shipping a meaningful round of changes. */
if (!defined('APP_VERSION')) define('APP_VERSION', '1.0.0');

/** Escape output for safe HTML rendering. */
function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/** Format a Y-m-d date like "Jul 2, 2026". */
function fmt_date($date) {
    if (!$date) return '—';
    $ts = strtotime($date);
    return $ts ? date('M j, Y', $ts) : e($date);
}

/** Format a HH:MM:SS time like "10:00 AM". */
function fmt_time($time) {
    if (!$time) return '';
    $ts = strtotime($time);
    return $ts ? date('g:i A', $ts) : e($time);
}

/** True if a Y-m-d date string falls on today's date. */
function is_today($date) {
    if (!$date) return false;
    $ts = strtotime($date);
    return $ts && date('Y-m-d', $ts) === date('Y-m-d');
}

/** Time-of-day greeting: "Good morning" before noon, "Good afternoon"
 *  until 6pm, "Good evening" after that. */
function greeting() {
    $h = (int)date('G');
    if ($h < 12) return 'Good morning';
    if ($h < 18) return 'Good afternoon';
    return 'Good evening';
}

/** Human age from a birth date, e.g. "5y 4m" or "8 mo". */
function age_from_birth($birth) {
    if (!$birth) return '—';
    $b = new DateTime($birth);
    $n = new DateTime('today');
    $diff = $b->diff($n);
    if ($diff->y <= 0) return $diff->m . ' mo';
    return $diff->m > 0 ? "{$diff->y}y {$diff->m}m" : "{$diff->y}y";
}

/** Days from today until $date (negative = overdue). */
function days_until($date) {
    if (!$date) return null;
    $target = new DateTime($date);
    $today  = new DateTime('today');
    return (int)$today->diff($target)->format('%r%a');
}

/** Colour pair for a status pill. Returns [background, foreground]. */
function status_colors($status) {
    switch ($status) {
        case 'Active':
        case 'Scheduled':        return ['var(--ok-bg)',    'var(--ok-fg)'];
        case 'Under Treatment':
        case 'Pending':          return ['var(--warn-bg)',  'var(--warn-fg)'];
        case 'Completed':        return ['var(--muted-bg)', 'var(--muted-fg)'];
        case 'Declined':         return ['var(--rose-soft)','var(--rose)'];
        case 'Cancelled':        return ['var(--muted-bg)','var(--muted-fg)'];
        case 'Expired':          return ['var(--muted-bg)','var(--muted-fg)'];
        default:                 return ['var(--muted-bg)', 'var(--muted-fg)'];
    }
}

/* ------------------------------------------------------------
   Appointment schedule — managed by staff on schedule.php.
   Three pieces, all in the database:
     appt_time_slots    the bookable hour slots
     appt_weekdays      which days of the week are open
     appt_special_dates date-specific overrides (holiday / one-off open day)
   Both booking forms (staff "Schedule", owner "Request") and both
   booking actions read from these, so one edit applies everywhere.
   A slot can hold any number of appointments — there is deliberately no
   capacity limit or double-booking check.
   Every reader falls back to the original Mon–Sat, 9–4 schedule if the
   tables aren't there yet, so booking never breaks on an old database.
   ------------------------------------------------------------ */

/** Human label for a slot, e.g. "9:00 – 10:00 AM" or "11:00 AM – 12:00 PM". */
function slot_label($start, $end) {
    $s = strtotime($start); $e = strtotime($end);
    return date('A', $s) === date('A', $e)
        ? date('g:i', $s) . ' – ' . date('g:i A', $e)
        : date('g:i A', $s) . ' – ' . date('g:i A', $e);
}

/** An appointment's time as a range, e.g. "10:00 – 11:00 AM". Only the start
 *  time is stored, so the end comes from the time slot that starts then; if
 *  that slot no longer exists, just the start time is shown. */
function appt_time_range($time) {
    if ($time === null || $time === '') return '';
    foreach (appt_slot_rows() as $r) {
        if ($r['start_time'] === $time) return slot_label($r['start_time'], $r['end_time']);
    }
    return fmt_time($time);
}

/** All time-slot rows (id, start_time, end_time, capacity), earliest first.
 *  capacity is null for "no limit". */
function appt_slot_rows() {
    static $rows = null;
    if ($rows !== null) return $rows;
    $pdo = $GLOBALS['pdo'];
    try {
        $rows = $pdo->query("SELECT id, start_time, end_time, capacity FROM appt_time_slots ORDER BY start_time")->fetchAll();
    } catch (Throwable $e) {
        try {   // slots table exists but predates the capacity column
            $rows = $pdo->query("SELECT id, start_time, end_time, NULL AS capacity FROM appt_time_slots ORDER BY start_time")->fetchAll();
        } catch (Throwable $e2) {
            $rows = [];
            foreach ([['09:00:00','10:00:00'],['10:00:00','11:00:00'],['11:00:00','12:00:00'],
                      ['13:00:00','14:00:00'],['14:00:00','15:00:00'],['15:00:00','16:00:00']] as $i => [$s, $en]) {
                $rows[] = ['id' => $i + 1, 'start_time' => $s, 'end_time' => $en, 'capacity' => null];
            }
        }
    }
    return $rows;
}

/** A slot's usual per-day capacity: an int, or null for no limit / unknown time. */
function slot_capacity($time) {
    foreach (appt_slot_rows() as $r) {
        if ($r['start_time'] === $time) return $r['capacity'] === null ? null : (int)$r['capacity'];
    }
    return null;
}

/** Per-date slot changes made on the Schedule calendar, for dates in
 *  [$from, $to] (inclusive, Y-m-d):
 *  ['Y-m-d' => ['HH:MM:SS' => ['open' => bool, 'cap' => int|null]]]. */
function appt_day_slot_overrides($from, $to) {
    $out = [];
    try {
        $st = $GLOBALS['pdo']->prepare("SELECT the_date, start_time, is_open, capacity FROM appt_day_slots WHERE the_date BETWEEN ? AND ?");
        $st->execute([$from, $to]);
        foreach ($st as $r) {
            $out[$r['the_date']][$r['start_time']] = [
                'open' => (bool)$r['is_open'],
                'cap'  => $r['capacity'] === null ? null : (int)$r['capacity'],
            ];
        }
    } catch (Throwable $e) { /* table not there yet — no per-date changes */ }
    return $out;
}

/** How one slot works on one date, with any calendar change applied:
 *  ['open' => bool, 'cap' => int|null]. */
function slot_rule_on($date, $time) {
    $o = appt_day_slot_overrides($date, $date);
    if (isset($o[$date][$time])) return $o[$date][$time];
    return ['open' => true, 'cap' => slot_capacity($time)];
}

/** Appointments holding a place in a slot on a date. Pending requests
 *  count — they hold the place until staff decide — and so do Completed
 *  ones; only Declined, Cancelled and Expired free a place. $excludeId leaves one out (used
 *  when approving a request, which is already counted in this total). */
function slot_booked_count($date, $time, $excludeId = 0) {
    $st = $GLOBALS['pdo']->prepare(
        "SELECT COUNT(*) FROM appointments a JOIN patients p ON p.id = a.patient_id
         WHERE a.appt_date = ? AND a.appt_time = ? AND a.status NOT IN ('Declined','Cancelled','Expired')
           AND p.deleted_at IS NULL AND a.id <> ?");
    $st->execute([$date, $time, (int)$excludeId]);
    return (int)$st->fetchColumn();
}

/** Give an appointment its reference code — APT-<appointment date as
 *  YYYYMMDD>-<3-digit number for that day>, e.g. APT-20261020-002 — if it
 *  doesn't have one yet, and return the code. Numbers are never reused: a
 *  code stays with its appointment for good, so the next one that day
 *  always takes the highest number so far + 1.
 *  Called when staff approve a request or book an appointment directly.
 *  The UNIQUE key on appt_code guards against two approvals racing for
 *  the same number — the loser just takes the next one. */
function assign_appt_code($pdo, $id) {
    $st = $pdo->prepare("SELECT appt_code, appt_date FROM appointments WHERE id = ?");
    $st->execute([(int)$id]);
    $row = $st->fetch();
    if (!$row) return null;
    if ($row['appt_code']) return $row['appt_code'];
    $prefix = 'APT-' . date('Ymd', strtotime($row['appt_date'])) . '-';
    for ($try = 0; $try < 5; $try++) {
        $max = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(appt_code, ?) AS UNSIGNED)) FROM appointments WHERE appt_code LIKE ?");
        $max->execute([strlen($prefix) + 1, $prefix . '%']);
        $code = $prefix . str_pad((string)((int)$max->fetchColumn() + 1), 3, '0', STR_PAD_LEFT);
        try {
            $pdo->prepare("UPDATE appointments SET appt_code = ? WHERE id = ? AND appt_code IS NULL")->execute([$code, (int)$id]);
            return $code;
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') throw $e;   // duplicate — someone took it, try the next number
        }
    }
    return null;
}

/** Save the schedule of every day that is over, as it stands right now, so
 *  that viewing a past day later shows what was in force then — not whatever
 *  the time slots happen to be set to by then. A day is over once it is
 *  before today, or today after its last slot has started. Safe to call on
 *  every request (it only writes days not yet saved); call it BEFORE any
 *  change to the slots. */
function freeze_past_days($pdo) {
    try {
        $today = date('Y-m-d');
        $rows  = appt_slot_rows();
        $over  = $rows && max(array_column($rows, 'start_time')) <= date('H:i:s');
        $end   = $over ? $today : date('Y-m-d', strtotime('-1 day'));
        // A saved day that isn't over by the clock (e.g. the computer's date was
        // set back) isn't finished — drop it so it behaves as a normal day again.
        $pdo->prepare("DELETE FROM appt_day_frozen WHERE the_date > ?")->execute([$end]);
        $last  = $pdo->query("SELECT MAX(the_date) FROM appt_day_frozen")->fetchColumn();
        if ($last) {
            $start = date('Y-m-d', strtotime($last . ' +1 day'));
        } else {
            // First run: begin at the earliest day anything was ever set or booked.
            $first = $pdo->query("SELECT MIN(d) FROM (
                         SELECT MIN(the_date) d FROM appt_special_dates
                         UNION ALL SELECT MIN(the_date) FROM appt_day_slots
                         UNION ALL SELECT MIN(appt_date) FROM appointments) x")->fetchColumn();
            if (!$first) return;
            $start = max($first, date('Y-m-d', strtotime('-400 days')));
        }
        if ($start > $end) return;

        $ov = appt_day_slot_overrides($start, $end);
        $sp = [];
        $st = $pdo->prepare("SELECT the_date, is_open, note FROM appt_special_dates WHERE the_date BETWEEN ? AND ?");
        $st->execute([$start, $end]);
        foreach ($st as $r) $sp[$r['the_date']] = $r;

        $ins = $pdo->prepare("INSERT IGNORE INTO appt_day_frozen (the_date, day_open, note, slots) VALUES (?, ?, ?, ?)");
        $pdo->beginTransaction();
        for ($ts = strtotime($start); $ts <= strtotime($end); $ts = strtotime('+1 day', $ts)) {
            $d = date('Y-m-d', $ts);
            $slots = [];
            foreach ($rows as $r) {
                $t = $r['start_time'];
                $o = $ov[$d][$t] ?? null;
                $slots[] = ['t' => $t, 'e' => $r['end_time'], 'open' => $o ? $o['open'] : true,
                            'cap' => $o ? $o['cap'] : ($r['capacity'] === null ? null : (int)$r['capacity'])];
            }
            $row = $sp[$d] ?? null;
            $ins->execute([$d, $row ? (int)$row['is_open'] : 0, $row && $row['note'] !== null ? $row['note'] : null, json_encode($slots)]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();   // table not there yet — nothing is frozen
    }
}

/** Any request still Pending when its appointment time arrives is marked
 *  Expired — nobody approved it in time, so it can no longer be approved.
 *  Runs on every signed-in page load and on the public lookup (cheap: one
 *  UPDATE that usually matches nothing), and each expiry is logged so staff
 *  and the owner are told. */
function expire_pending_appointments($pdo) {
    try {
        $due = $pdo->query("SELECT a.id, a.appt_date, a.appt_time, p.name AS pname
                            FROM appointments a JOIN patients p ON p.id = a.patient_id
                            WHERE a.status = 'Pending'
                              AND TIMESTAMP(a.appt_date, IFNULL(a.appt_time, '23:59:59')) <= NOW()")->fetchAll();
        if (!$due) return;
        $up = $pdo->prepare("UPDATE appointments SET status = 'Expired' WHERE id = ? AND status = 'Pending'");
        foreach ($due as $r) {
            $up->execute([(int)$r['id']]);
            if ($up->rowCount() && function_exists('record_audit')) {
                record_audit($pdo, 'appt_expire', (int)$r['id'], $r['pname'],
                    'Request for ' . $r['pname'] . ' on ' . fmt_date($r['appt_date']) . ' at ' . fmt_time($r['appt_time'])
                    . ' expired — it was not approved before the appointment time',
                    ['id' => null, 'username' => 'system']);
            }
        }
    } catch (Throwable $e) { /* never break a page over housekeeping */ }
}
/** True once a slot on today's date has started — it can't be booked any more. */
function slot_has_started($date, $time) {
    return $date === date('Y-m-d') && $time <= date('H:i:s');
}

/** True if the slot still has a place on that date (always true when
 *  no limit). Uses that date's own limit if one was set on the calendar. */
function slot_has_room($date, $time, $excludeId = 0) {
    $cap = slot_rule_on($date, $time)['cap'];
    return $cap === null || slot_booked_count($date, $time, $excludeId) < $cap;
}

/** Places already taken per upcoming date and slot, for the booking forms:
 *  ['Y-m-d' => ['HH:MM:SS' => n]]. */
function upcoming_slot_usage() {
    $out = [];
    try {
        $st = $GLOBALS['pdo']->prepare(
            "SELECT a.appt_date, a.appt_time, COUNT(*) n FROM appointments a JOIN patients p ON p.id = a.patient_id
             WHERE a.appt_date >= ? AND a.status NOT IN ('Declined','Cancelled','Expired') AND p.deleted_at IS NULL
             GROUP BY a.appt_date, a.appt_time");
        $st->execute([date('Y-m-d')]);
        foreach ($st as $r) $out[$r['appt_date']][$r['appt_time']] = (int)$r['n'];
    } catch (Throwable $e) { /* no usage info — forms just won't pre-flag full slots */ }
    return $out;
}

/** Bookable slots as [start "HH:MM:SS" => label], for the booking dropdowns. */
function appointment_slots() {
    $out = [];
    foreach (appt_slot_rows() as $r) {
        $out[$r['start_time']] = slot_label($r['start_time'], $r['end_time']);
    }
    return $out;
}

/** Weekly pattern as [1..7 => bool open], 1 = Monday ... 7 = Sunday. */
function appt_open_weekdays() {
    static $days = null;
    if ($days !== null) return $days;
    $days = [1 => true, 2 => true, 3 => true, 4 => true, 5 => true, 6 => true, 7 => false];
    try {
        foreach ($GLOBALS['pdo']->query("SELECT dow, is_open FROM appt_weekdays") as $r) {
            $days[(int)$r['dow']] = (bool)$r['is_open'];
        }
    } catch (Throwable $e) { /* keep the default pattern */ }
    return $days;
}

/** Special dates from today on, as ['Y-m-d' => ['open' => bool, 'note' => str]]. */
function appt_upcoming_special_dates() {
    static $map = null;
    if ($map !== null) return $map;
    $map = [];
    try {
        $st = $GLOBALS['pdo']->prepare("SELECT the_date, is_open, note FROM appt_special_dates WHERE the_date >= ? ORDER BY the_date");
        $st->execute([date('Y-m-d')]);
        foreach ($st as $r) $map[$r['the_date']] = ['open' => (bool)$r['is_open'], 'note' => (string)$r['note']];
    } catch (Throwable $e) { /* no overrides */ }
    return $map;
}

/** True if bookings are taken on $dateStr (Y-m-d): only days the admin
 *  opened on the Schedule calendar. Doesn't check past/future — callers do. */
function is_bookable_date($dateStr) {
    $ts = strtotime((string)$dateStr);
    if ($ts === false) return false;
    $ymd = date('Y-m-d', $ts);
    try {
        $st = $GLOBALS['pdo']->prepare("SELECT is_open FROM appt_special_dates WHERE the_date = ?");
        $st->execute([$ymd]);
        $o = $st->fetchColumn();
        if ($o !== false) return (bool)$o;
    } catch (Throwable $e) { /* no schedule table — nothing is open */ }
    // No automatic weekly pattern: a day is bookable only when the admin
    // has opened it on the Schedule calendar.
    return false;
}

/** First bookable date strictly after today (looks ahead up to a year). */
function next_bookable_date() {
    for ($i = 1; $i <= 366; $i++) {
        $d = date('Y-m-d', strtotime("+$i day"));
        if (is_bookable_date($d)) return $d;
    }
    return date('Y-m-d', strtotime('+1 day'));
}

/** Short description of the open days, e.g. "Mon–Sat" or "Mon, Wed, Fri". */
function open_days_label() {
    $names = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
    $open = array_keys(array_filter(appt_open_weekdays()));
    if (!$open) return 'no regular days';
    // A single unbroken run of 3+ days reads better as a range.
    $isRun = ($open[count($open) - 1] - $open[0]) === count($open) - 1;
    if ($isRun && count($open) >= 3) return $names[$open[0]] . '–' . $names[$open[count($open) - 1]];
    return implode(', ', array_map(fn($d) => $names[$d], $open));
}

/** Philippine national holidays for a year, as
 *  ['Y-m-d' => ['name' => str, 'type' => 'regular'|'special']].
 *  Fixed dates plus the ones that move (Holy Week from Easter, National
 *  Heroes Day = last Monday of August). Holidays declared year by year
 *  (Eid'l Fitr, Eid'l Adha, Chinese New Year, extra special days) can't be
 *  worked out from the calendar, so they aren't listed — add them as a
 *  note on the day. */
function ph_holidays($year) {
    static $cache = [];
    $year = (int)$year;
    if (isset($cache[$year])) return $cache[$year];

    // Easter Sunday (Anonymous Gregorian algorithm).
    $a = $year % 19; $b = intdiv($year, 100); $c = $year % 100;
    $d = intdiv($b, 4); $e = $b % 4; $g = intdiv(8 * $b + 13, 25);
    $h = (19 * $a + $b - $d - $g + 15) % 30;
    $i = intdiv($c, 4); $k = $c % 4;
    $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
    $m = intdiv($a + 11 * $h + 19 * $l, 433);
    $month = intdiv($h + $l - 7 * $m + 90, 25);
    $day   = ($h + $l - 7 * $m + 33 * $month + 19) % 32;
    $easter = mktime(0, 0, 0, $month, $day, $year);
    $off = fn($n) => date('Y-m-d', strtotime(($n >= 0 ? '+' : '') . $n . ' day', $easter));

    $r = 'regular'; $sp = 'special';
    $list = [
        "$year-01-01" => ["New Year's Day", $r],
        "$year-02-25" => ['EDSA People Power Anniversary', $sp],
        $off(-3)      => ['Maundy Thursday', $r],
        $off(-2)      => ['Good Friday', $r],
        $off(-1)      => ['Black Saturday', $sp],
        "$year-04-09" => ['Araw ng Kagitingan', $r],
        "$year-05-01" => ['Labor Day', $r],
        "$year-06-12" => ['Independence Day', $r],
        "$year-08-21" => ['Ninoy Aquino Day', $sp],
        date('Y-m-d', strtotime("last monday of august $year")) => ['National Heroes Day', $r],
        "$year-11-01" => ["All Saints' Day", $sp],
        "$year-11-02" => ["All Souls' Day", $sp],
        "$year-11-30" => ['Bonifacio Day', $r],
        "$year-12-08" => ['Feast of the Immaculate Conception', $sp],
        "$year-12-24" => ['Christmas Eve', $sp],
        "$year-12-25" => ['Christmas Day', $r],
        "$year-12-30" => ['Rizal Day', $r],
        "$year-12-31" => ["New Year's Eve", $sp],
    ];
    $out = [];
    foreach ($list as $date => [$name, $type]) $out[$date] = ['name' => $name, 'type' => $type];
    ksort($out);
    return $cache[$year] = $out;
}

/** Wording for a holiday's type, e.g. "regular holiday". */
function ph_holiday_type_label($type) {
    return $type === 'regular' ? 'regular holiday' : 'special non-working day';
}

/** The Philippine holiday on a date (Y-m-d), or null. */
function ph_holiday_on($date) {
    return ph_holidays((int)substr($date, 0, 4))[$date] ?? null;
}
/** Render a status pill. */
function status_pill($status, $large = false) {
    [$bg, $fg] = status_colors($status);
    $cls = 'vp-pill' . ($large ? ' lg' : '');
    return "<span class=\"$cls\" style=\"background:$bg;color:$fg\">"
         . "<span class=\"vp-pill-dot\" style=\"background:$fg\"></span>"
         . e($status) . "</span>";
}

/** Inline SVG icon for a species (falls back to a paw). */
function species_icon($species, $size = 18) {
    // Simple, self-contained line icons (no external icon library needed).
    $paw = '<circle cx="6" cy="9" r="1.6"/><circle cx="10" cy="6.5" r="1.6"/><circle cx="14" cy="6.5" r="1.6"/><circle cx="18" cy="9" r="1.6"/><path d="M8 15c0-2.5 1.8-4 4-4s4 1.5 4 4c0 1.8-1.6 2.6-4 2.6S8 16.8 8 15z"/>';
    return "<svg width=\"$size\" height=\"$size\" viewBox=\"0 0 24 24\" fill=\"currentColor\" "
         . "xmlns=\"http://www.w3.org/2000/svg\">$paw</svg>";
}

/** Flash-message helpers (a one-time toast shown after redirects). */
/**
 * Store a one-shot message for the next page load.
 *
 * $target lets a handler say which form the message belongs to. When a
 * target is given, the message is rendered as an inline alert INSIDE
 * that form (next to the fields the person needs to fix) instead of a
 * floating toast in the corner. Leave it null for page-level news
 * ("Patient added") that isn't tied to any one form.
 *
 * $type is 'error' or 'success' and only affects the inline styling.
 */
function set_flash($message, $target = null, $type = 'error') {
    $_SESSION['flash'] = $message;
    if ($target === null) {
        unset($_SESSION['flash_target'], $_SESSION['flash_type']);
    } else {
        $_SESSION['flash_target'] = $target;
        $_SESSION['flash_type']   = $type;
    }
}

/** Convenience wrapper: a message that belongs to a specific form. */
function set_form_error($target, $message) {
    set_flash($message, $target, 'error');
}

function get_flash() {
    if (!empty($_SESSION['flash'])) {
        $m = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $m;
    }
    return null;
}

/** Which form the pending flash belongs to (null = show as a toast). */
function get_flash_target() {
    if (!empty($_SESSION['flash_target'])) {
        $t = $_SESSION['flash_target'];
        unset($_SESSION['flash_target']);
        return $t;
    }
    return null;
}

function get_flash_type() {
    $t = $_SESSION['flash_type'] ?? 'error';
    unset($_SESSION['flash_type']);
    return $t;
}

/**
 * Render the inline alert for $formKey, if the pending flash belongs to
 * it. Call this inside the form, above the fields. Returns '' when the
 * message is for a different form (or is a page-level toast).
 */
function form_alert($formKey) {
    global $flash, $flashTarget, $flashType;
    if ($flash === null || $flashTarget !== $formKey) return '';
    $icon = $flashType === 'success'
        ? '<path d="M20 6 9 17l-5-5"/>'
        : '<circle cx="12" cy="12" r="10"/><path d="M12 8v5M12 16.5v.01"/>';
    return '<div class="vp-form-alert ' . e($flashType) . '" role="alert">'
         . '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
         . 'stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
         . $icon . '</svg><span>' . e($flash) . '</span></div>';
}

/** Redirect helper. */
function redirect($path) {
    header('Location: ' . return_to_target($path));
    exit;
}

/**
 * Every POST form on an app page carries a hidden `return_to` (added by
 * footer.php) holding the page it was submitted from, including its query
 * string and #tab — e.g. "appointments.php?status=Pending". When an action
 * redirects back to that SAME page, use the submitted copy instead so the
 * person lands on the filter/tab they were on, not the page's default.
 *
 * Only same-page redirects are rewritten: an action that deliberately goes
 * somewhere else (deleting a patient from its chart → the patient list)
 * keeps its own destination. If the action's own target names a #fragment
 * (e.g. "#vacc" after editing a vaccine), that fragment wins.
 */
function return_to_target($path) {
    $rt = $_POST['return_to'] ?? '';
    // Local page names only — no slashes, schemes or hosts, so this can
    // never be turned into an open redirect.
    if (!is_string($rt) || !preg_match('~^([A-Za-z0-9_-]+\.php)(\?[^#\s]*)?(#[A-Za-z0-9_-]*)?$~', $rt, $rm)) {
        return $path;
    }
    $prefix = strncmp($path, '../', 3) === 0 ? '../' : '';
    if (!preg_match('~^([A-Za-z0-9_-]+\.php)(\?[^#]*)?(#.*)?$~', substr($path, strlen($prefix)), $pm)) {
        return $path;
    }
    if ($pm[1] !== $rm[1]) return $path;

    $query    = $rm[2] ?? '';
    $fragment = (isset($pm[3]) && $pm[3] !== '') ? $pm[3] : ($rm[3] ?? '');
    return $prefix . $rm[1] . $query . $fragment;
}

/* ------------------------------------------------------------
   CSRF protection
   Every state-changing account form embeds csrf_field(); its
   handler calls verify_csrf() before doing anything. Relies on the
   session already being started (auth.php does this on every page
   and handler).
   ------------------------------------------------------------ */
function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}
function csrf_field() {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}
/** Validate the posted token; on failure flash a message and redirect. */
function verify_csrf($redirect = '../dashboard.php') {
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
        set_flash('Security check failed — please try that again.');
        redirect($redirect);
    }
}

/** Friendly label for a role. */
/**
 * Join first / middle / last into one display name.
 * The middle name is optional, so "Ramon" + "" + "Aquino" gives
 * "Ramon Aquino" rather than a double space.
 */
/**
 * SQL that assembles a display name from the first/middle/last columns.
 * There is no stored full_name column, so queries build it on the fly:
 *   SELECT ... , " . name_sql('u') . " AS full_name
 * CONCAT_WS skips NULLs, so a missing middle name doesn't leave a
 * double space.
 */
function name_sql($alias) {
    return "CONCAT_WS(' ', NULLIF({$alias}.first_name,''), NULLIF({$alias}.middle_name,''), NULLIF({$alias}.last_name,''))";
}

/**
 * Formal name for listings: "Santos, Maria C."
 * Last name first, then first name, then the middle initial. Falls back
 * gracefully when a part is missing:
 *   Santos, Maria C.   (all three)
 *   Santos, Maria      (no middle name)
 *   Santos             (surname only)
 */
/**
 * Short display name: "Maria C. Santos".
 * First name, middle initial, surname — the everyday way a name is
 * written, as opposed to format_name_formal() which is the
 * "Santos, Maria C." form used for scanning lists.
 */
function format_name_short($first, $middle, $last) {
    $first  = trim((string)$first);
    $middle = trim((string)$middle);
    $last   = trim((string)$last);

    $initials = '';
    if ($middle !== '') {
        // One initial per word, so compound middle names like
        // "Dela Cruz" read as "D. C." rather than being cut to "D.".
        $bits = [];
        foreach (preg_split('/\s+/u', $middle) as $part) {
            if ($part !== '') $bits[] = mb_strtoupper(mb_substr($part, 0, 1)) . '.';
        }
        $initials = implode(' ', $bits);
    }
    return implode(' ', array_filter([$first, $initials, $last], fn($v) => $v !== ''));
}

function format_name_formal($first, $middle, $last) {
    $first  = trim((string)$first);
    $middle = trim((string)$middle);
    $last   = trim((string)$last);

    if ($last === '') return build_full_name($first, $middle, $last);

    $tail = $first;
    if ($middle !== '') {
        // Multi-word middle names give one initial per word (e.g. "Dela
        // Cruz" -> "D. C."), which keeps compound Filipino names readable.
        $initials = [];
        foreach (preg_split('/\s+/u', $middle) as $part) {
            if ($part !== '') $initials[] = mb_strtoupper(mb_substr($part, 0, 1)) . '.';
        }
        if ($initials) $tail = trim($tail . ' ' . implode(' ', $initials));
    }
    return $tail === '' ? $last : $last . ', ' . $tail;
}

/** Same, but reading straight from a row with first/middle/last columns. */
function row_name_formal($row, $prefix = '') {
    return format_name_formal(
        $row[$prefix . 'first_name']  ?? '',
        $row[$prefix . 'middle_name'] ?? '',
        $row[$prefix . 'last_name']   ?? ''
    );
}

function build_full_name($first, $middle, $last) {
    $parts = array_filter([trim((string)$first), trim((string)$middle), trim((string)$last)],
                          fn($v) => $v !== '');
    return implode(' ', $parts);
}

/**
 * "Santos, Maria C." — used where a sorted, formal listing reads better.
 */
function name_last_first($first, $middle, $last) {
    $first  = trim((string)$first);
    $middle = trim((string)$middle);
    $last   = trim((string)$last);
    if ($last === '') return $first;
    $out = $last . ', ' . $first;
    if ($middle !== '') {
        $out .= ' ' . mb_strtoupper(mb_substr($middle, 0, 1)) . '.';
    }
    return $out;
}

/** Middle initial only, e.g. "C." — empty string when there is none. */
function middle_initial($middle) {
    $middle = trim((string)$middle);
    return $middle === '' ? '' : mb_strtoupper(mb_substr($middle, 0, 1)) . '.';
}

/** Validate one part of a person's name. Returns an error or null. */
function name_part_problem($value, $label, $required = true) {
    $value = trim((string)$value);
    if ($value === '') {
        return $required ? 'Please enter the ' . $label . '.' : null;
    }
    if (mb_strlen($value) > 60) {
        return ucfirst($label) . ' is too long (60 characters maximum).';
    }
    // Letters, spaces, hyphens, apostrophes and periods cover names like
    // "Dela Cruz", "O'Brien", "Jose Ma." and accented spellings.
    if (!preg_match("/^[\p{L}][\p{L} .'-]*$/u", $value)) {
        return ucfirst($label) . ' may only contain letters, spaces, and . \' -';
    }
    return null;
}

/**
 * Human label for an account's role. Clinic staff are further split into
 * Veterinarian and (non-vet) Staff via the optional $staffTitle; owners are
 * always "Pet owner". Passing only the role keeps older call sites working.
 */
function role_label($role, $staffTitle = null) {
    if ($role !== 'staff') return 'Pet owner';
    if ($staffTitle === 'veterinarian') return 'Veterinarian';
    if ($staffTitle === 'staff')        return 'Staff';
    return 'Clinic staff';   // staff account with no title set yet
}

/**
 * The clinic-staff titles offered in the account form. The array key is what
 * the form submits and what is stored in users.staff_title; the value is the
 * label shown to the user.
 */
function staff_titles() {
    return ['veterinarian' => 'Veterinarian', 'staff' => 'Staff'];
}

/**
 * Default value for an "Attending vet" / "Administered by" field, based on
 * who is filling the form. A veterinarian's own account pre-fills as
 * "Dr. <Last name>"; anyone else (non-vet staff) gets an empty string so
 * they type in whichever veterinarian actually attended. $user is the
 * session user array (or any row with role/staff_title/last_name).
 */
function default_attending_vet($user) {
    if (!$user || ($user['role'] ?? '') !== 'staff') return '';
    if (($user['staff_title'] ?? '') !== 'veterinarian') return '';
    $last = trim((string)($user['last_name'] ?? ''));
    return $last !== '' ? 'Dr. ' . $last : '';
}

/**
 * True if the users table has the staff_title column. Fresh installs and
 * migrated databases have it; this lets the account code stay working on a
 * database where the migration hasn't been run yet. The result is cached for
 * the request so the lookup only happens once.
 */
function has_staff_title_column($pdo) {
    static $has = null;
    if ($has !== null) return $has;
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'staff_title'");
        $has = $stmt && $stmt->fetch() !== false;
    } catch (Throwable $e) {
        $has = false;
    }
    return $has;
}

/**
 * True if the owners table has the privacy-consent columns
 * (privacy_consent_at / privacy_consent_ver). Lets the account code keep
 * working on a database where the consent migration hasn't been run yet.
 * Cached for the request.
 */
function has_privacy_consent_columns($pdo) {
    static $has = null;
    if ($has !== null) return $has;
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM owners LIKE 'privacy_consent_at'");
        $has = $stmt && $stmt->fetch() !== false;
    } catch (Throwable $e) {
        $has = false;
    }
    return $has;
}

/**
 * True if the users table has its own privacy-consent columns, used for the
 * first-login consent gate. Separate from the owners-table check above.
 * Cached for the request.
 */
function has_user_consent_columns($pdo) {
    static $has = null;
    if ($has !== null) return $has;
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'privacy_consent_at'");
        $has = $stmt && $stmt->fetch() !== false;
    } catch (Throwable $e) {
        $has = false;
    }
    return $has;
}

/**
 * Whether the given user still needs to agree to the CURRENT Privacy Notice.
 * True when they've never agreed, or agreed to an older notice version (so a
 * bump of PRIVACY_VERSION re-prompts everyone). $user is a row/session array
 * with a privacy_consent_ver key. If the app can't tell the version columns
 * exist, it returns false so the gate never traps anyone on an un-migrated DB.
 */
function needs_privacy_consent($user) {
    if (!$user) return false;
    // A key must be present to evaluate; on an un-migrated DB it's absent.
    if (!array_key_exists('privacy_consent_ver', $user)) return false;
    $agreed  = (string)($user['privacy_consent_ver'] ?? '');
    $current = defined('PRIVACY_VERSION') ? PRIVACY_VERSION : '';
    return $agreed !== $current;
}

/**
 * The security questions people choose from. Kept in one place so the
 * setup screen and account recovery always agree.
 */
function security_questions() {
    return [
        'What was the name of your first pet?',
        'What city were you born in?',
        "What is your mother's maiden name?",
        'What was the name of your elementary school?',
        'What is your favourite food?',
        'What was the make of your first vehicle?',
        'What is the name of your closest childhood friend?',
        'What street did you live on in third grade?',
    ];
}

/**
 * Pick three questions for a particular account.
 *
 * The choice is random per user, so two people setting up on the same
 * day don't get the same trio — but it's seeded from the user id, so
 * reloading the page or coming back later shows the SAME three rather
 * than reshuffling under them mid-form.
 *
 * They can still change any of the three from the dropdowns; this only
 * decides what is preselected.
 */
function security_questions_for($userId) {
    $bank = security_questions();
    $keys = array_keys($bank);

    // Deterministic Fisher-Yates shuffle seeded from the user id. This
    // spreads the picks evenly across the bank, which a plain hash-sort
    // does not — that skews towards whichever questions happen to hash
    // low, so some are suggested twice as often as others.
    $seed = crc32('pawprints-secq-' . $userId);
    mt_srand($seed);
    for ($i = count($keys) - 1; $i > 0; $i--) {
        $j = mt_rand(0, $i);
        [$keys[$i], $keys[$j]] = [$keys[$j], $keys[$i]];
    }
    mt_srand();   // don't leave the global generator seeded

    return [$bank[$keys[0]], $bank[$keys[1]], $bank[$keys[2]]];
}

/**
 * Normalise an answer before hashing or comparing: trimmed, lower-cased
 * and with runs of whitespace collapsed. Without this, "Manila " and
 * "manila" would count as different answers.
 */
function normalise_answer($answer) {
    return preg_replace('/\s+/u', ' ', trim(mb_strtolower((string)$answer)));
}

/** Hash a security answer for storage. */
function hash_answer($answer) {
    return password_hash(normalise_answer($answer), PASSWORD_DEFAULT);
}

/** Check a typed answer against a stored hash. */
function answer_matches($typed, $hash) {
    return $hash && password_verify(normalise_answer($typed), $hash);
}

/** Validate a login email address. Returns null if OK, or an error string. */
function login_email_problem($username) {
    // The email address IS the login identifier — there is no separate
    // username anywhere in the app.
    if ($username === '') {
        return 'An email address is required.';
    }
    if (strlen($username) > 60) {
        return 'That email address is too long (60 characters maximum).';
    }
    if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
        return 'Please enter a valid email address (e.g. name@email.com).';
    }
    return null;
}

/** Validate a proposed password. Returns null if OK, or an error string. */
/**
 * Strength rule for a password the user chooses themselves.
 * A safe password must resist guessing and brute-force attacks, so it
 * needs length plus all four character classes.
 */
function password_problem($pw) {
    if (strlen($pw) < 8) {
        return 'Password must be at least 8 characters.';
    }
    if (!preg_match('/[A-Z]/', $pw)) {
        return 'Password must contain at least one uppercase letter (A–Z).';
    }
    if (!preg_match('/[a-z]/', $pw)) {
        return 'Password must contain at least one lowercase letter (a–z).';
    }
    if (!preg_match('/[0-9]/', $pw)) {
        return 'Password must contain at least one number (0–9).';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $pw)) {
        return 'Password must contain at least one special character (e.g. ! @ # $ % ^ & * ( ) _ + - = ?).';
    }
    return null;
}

/**
 * The individual strength checks, for the live meter on the password
 * form. Returns [label => bool passed].
 */
function password_checks($pw) {
    return [
        'At least 8 characters'        => strlen($pw) >= 8,
        'An uppercase letter (A–Z)'    => (bool)preg_match('/[A-Z]/', $pw),
        'A lowercase letter (a–z)'     => (bool)preg_match('/[a-z]/', $pw),
        'A number (0–9)'               => (bool)preg_match('/[0-9]/', $pw),
        'A special character (! @ # $ …)' => (bool)preg_match('/[^A-Za-z0-9]/', $pw),
    ];
}

/**
 * Verify a typed password against the account's stored hash. Supports
 * the seeded "plain:" format used by demo accounts before their first
 * successful login, as well as normal password_hash() bcrypt hashes.
 *
 * Shared by every form that must reconfirm identity before saving a
 * change to the account — the password change form, and (per account
 * policy) the profile and security-question forms on My Account.
 */
function verify_current_password($typed, $stored) {
    $typed = (string)$typed;
    if ($typed === '' || !$stored) return false;
    if (strpos((string)$stored, 'plain:') === 0) {
        return hash_equals(substr((string)$stored, 6), $typed);
    }
    return password_verify($typed, (string)$stored);
}

/**
 * Rule for a TEMPORARY password issued by staff (the new user's phone
 * digits only). It is deliberately looser than password_problem() because
 * the account is always flagged must_change_password, so this value
 * only survives until the user's first sign-in. The full strength
 * rules still apply the moment they choose their own password.
 */
function temp_password_problem($pw) {
    $digits = preg_replace('/\D/', '', $pw);
    if (strlen($digits) < 7) {
        return 'The phone number needs at least 7 digits to be used as a temporary password.';
    }
    return null;
}

/** Strip a phone number down to the digits used as the temp password. */
/**
 * Store phone numbers in one consistent shape: 0917-555-0142.
 * The browser mask does this as you type, but a pasted value or a
 * request with JavaScript disabled still has to be tidied here.
 * Anything that isn't 11 digits is kept as typed rather than mangled.
 */
function format_phone($phone) {
    $d = preg_replace('/\D/', '', (string)$phone);
    if (strlen($d) !== 11) return trim((string)$phone);
    return substr($d, 0, 4) . '-' . substr($d, 4, 3) . '-' . substr($d, 7);
}

function temp_password_from_phone($phone) {
    return preg_replace('/\D/', '', (string)$phone);
}

/** Human-readable label + tone for an audit action code. */
function audit_action_meta($action) {
    $map = [
        'login'            => ['Signed in',            'teal'],
        'logout'           => ['Signed out',           'muted'],
        'login_failed'     => ['Failed sign-in',       'rose'],
        'login_denied'     => ['Sign-in blocked',      'rose'],
        'appt_lookup_failed' => ['Appointment lookup failed', 'amber'],
        'profile_update'   => ['Profile updated',      'teal'],
        'password_change'  => ['Password changed',     'amber'],
        'security_update'  => ['Security settings',    'amber'],
        'user_create'      => ['Account created',      'teal'],
        'owner_create'     => ['Pet owner added',     'teal'],
        'species_create'   => ['Species added',       'teal'],
        'species_delete'   => ['Species removed',     'rose'],
        'patient_create'   => ['Patient added',       'teal'],
        'patient_update'   => ['Patient edited',      'teal'],
        'patient_delete'   => ['Patient archived',    'rose'],
        'patient_restore'  => ['Patient restored',    'teal'],
        'visit_create'     => ['Visit logged',        'teal'],
        'visit_update'     => ['Visit edited',        'teal'],
        'visit_delete'     => ['Visit archived',      'rose'],
        'visit_restore'    => ['Visit restored',      'teal'],
        'vaccine_add'      => ['Vaccine recorded',    'teal'],
        'vaccine_update'   => ['Vaccine updated',     'teal'],
        'vaccine_delete'   => ['Vaccine archived',    'rose'],
        'vaccine_restore'  => ['Vaccine restored',    'teal'],
        'appt_create'      => ['Appointment booked',  'teal'],
        'appt_request'     => ['Appointment requested', 'amber'],
        'appt_approve'     => ['Appointment approved', 'teal'],
        'appt_decline'     => ['Appointment declined', 'rose'],
        'appt_expire'      => ['Request expired',      'amber'],
        'appt_cancel'     => ['Appointment cancelled', 'rose'],
        'appt_complete'    => ['Appointment done',    'teal'],
        'schedule_update'  => ['Schedule changed',    'amber'],
        'user_restore'     => ['Account restored',    'teal'],
        'species_restore'  => ['Species restored',    'teal'],
        'purge'            => ['Permanently deleted', 'rose'],
        'recovery_start'   => ['Recovery started',   'amber'],
        'recovery_failed'  => ['Recovery failed',    'rose'],
        'reminders_run'    => ['Reminders sent',    'teal'],
        'user_update'      => ['Account edited',       'teal'],
        'user_activate'    => ['Account activated',    'teal'],
        'user_deactivate'  => ['Account deactivated',  'rose'],
        'user_delete'      => ['Account deleted',      'rose'],
        'permission_grant' => ['Permission granted',   'amber'],
        'permission_revoke'=> ['Permission revoked',   'amber'],
        'privacy_consent'  => ['Privacy consent',      'teal'],
        'privacy_withdraw' => ['Consent withdrawn',    'amber'],
        'account_seeded'   => ['Account created',      'muted'],
    ];
    return $map[$action] ?? [ucfirst(str_replace('_', ' ', $action)), 'muted'];
}

/** Badge for an audit action (coloured pill). */
function audit_pill($action) {
    [$label, $tone] = audit_action_meta($action);
    $tones = [
        'teal'  => ['var(--ok-bg)',    'var(--ok-fg)'],
        'amber' => ['var(--warn-bg)',  'var(--warn-fg)'],
        'rose'  => ['var(--rose-soft)','var(--rose)'],
        'muted' => ['var(--muted-bg)', 'var(--muted-fg)'],
    ];
    [$bg, $fg] = $tones[$tone] ?? $tones['muted'];
    return '<span class="vp-pill" style="background:' . $bg . ';color:' . $fg . '">'
         . '<span class="vp-pill-dot" style="background:' . $fg . '"></span>'
         . e($label) . '</span>';
}

/** "time ago" helper for audit rows, e.g. "3h ago". */
function time_ago($datetime) {
    if (!$datetime) return '';
    $ts = strtotime($datetime);
    if (!$ts) return e($datetime);
    $diff = time() - $ts;
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return floor($diff / 60) . 'm ago';
    if ($diff < 86400)  return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j, Y', $ts);
}
