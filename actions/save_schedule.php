<?php
/* ============================================================
   Appointment schedule management (schedule.php) — staff only.
   One handler, picked by `op`:
     slot_save   add or edit a time slot      (id optional)
     slot_delete remove a time slot
     days_save   set which weekdays are open
     date_save   add or edit a special date   (id optional)
     date_delete remove a special date
     day_toggle  click a calendar day to flip it open/closed
     day_save    calendar day editor: open/close a day, turn slots off or
                 give them their own limit (one day, that weekday in the
                 month, or the whole month)
     day_reset   undo calendar changes for the same choice of days
   Existing appointments store their own date/time, so none of these
   ever move or cancel an appointment that's already booked.
   ============================================================ */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';
require_staff();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('../schedule.php');
verify_csrf('../schedule.php');

$op   = $_POST['op'] ?? '';
$back = '../schedule.php';

/** "HH:MM" or "HH:MM:SS" from an <input type="time"> → "HH:MM:00", or null. */
function clean_time($t) {
    $t = trim((string)$t);
    if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$/', $t)) return null;
    return substr($t, 0, 5) . ':00';
}

switch ($op) {

    // ------------------------------------------------------- time slots
    case 'slot_save':
        $id    = (int)($_POST['id'] ?? 0);
        $start = clean_time($_POST['start_time'] ?? '');
        $end   = clean_time($_POST['end_time'] ?? '');
        if (!$start || !$end) { set_flash('Please enter both a start and an end time.'); redirect($back); }
        if ($end <= $start)   { set_flash('The end time must be after the start time.'); redirect($back); }

        // Places per day: blank = no limit, otherwise a whole number ≥ 1.
        $capRaw = trim((string)($_POST['capacity'] ?? ''));
        if ($capRaw === '') {
            $cap = null;
        } elseif (ctype_digit($capRaw) && (int)$capRaw >= 1 && (int)$capRaw <= 999) {
            $cap = (int)$capRaw;
        } else {
            set_flash('Places per day must be a whole number from 1 to 999, or left blank for no limit.');
            redirect($back);
        }
        $capText = $cap === null ? 'no limit' : $cap . ' per day';

        // No two slots may overlap (touching end-to-start is fine).
        $ov = $pdo->prepare("SELECT start_time, end_time FROM appt_time_slots
                             WHERE id <> ? AND start_time < ? AND end_time > ? LIMIT 1");
        $ov->execute([$id, $end, $start]);
        if ($clash = $ov->fetch()) {
            set_flash('That overlaps the existing ' . slot_label($clash['start_time'], $clash['end_time']) . ' slot.');
            redirect($back);
        }

        $label = slot_label($start, $end);
        if ($id) {
            $old = $pdo->prepare("SELECT start_time, end_time, capacity FROM appt_time_slots WHERE id = ?");
            $old->execute([$id]);
            $o = $old->fetch();
            if (!$o) { set_flash('That time slot no longer exists.'); redirect($back); }
            $pdo->prepare("UPDATE appt_time_slots SET start_time = ?, end_time = ?, capacity = ? WHERE id = ?")
                ->execute([$start, $end, $cap, $id]);
            // Per-day changes made on the calendar follow the slot to its new time.
            if ($o['start_time'] !== $start) {
                try { $pdo->prepare("UPDATE appt_day_slots SET start_time = ? WHERE start_time = ?")->execute([$start, $o['start_time']]); }
                catch (Throwable $e) { /* no calendar changes table yet */ }
            }
            // Changing the places here sets them for every day: drop the
            // per-day limits left over from the calendar (days where the slot
            // is turned off stay off).
            $oldCap = $o['capacity'] === null ? null : (int)$o['capacity'];
            if ($oldCap !== $cap) {
                try { $pdo->prepare("DELETE FROM appt_day_slots WHERE start_time = ? AND is_open = 1")->execute([$start]); }
                catch (Throwable $e) { /* no calendar changes table yet */ }
            }
            record_audit($pdo, 'schedule_update', $id, $label,
                'Changed time slot ' . slot_label($o['start_time'], $o['end_time']) . ' to ' . $label . ' (' . $capText . ')');
            set_flash('Time slot updated to ' . $label . ', ' . $capText . '.');
        } else {
            $pdo->prepare("INSERT INTO appt_time_slots (start_time, end_time, capacity) VALUES (?, ?, ?)")->execute([$start, $end, $cap]);
            record_audit($pdo, 'schedule_update', (int)$pdo->lastInsertId(), $label, 'Added time slot ' . $label . ' (' . $capText . ')');
            set_flash('Time slot ' . $label . ' added, ' . $capText . '.');
        }
        redirect($back);

    case 'slot_delete':
        $id = (int)($_POST['id'] ?? 0);
        $row = $pdo->prepare("SELECT start_time, end_time FROM appt_time_slots WHERE id = ?");
        $row->execute([$id]);
        $s = $row->fetch();
        if (!$s) { set_flash('That time slot no longer exists.'); redirect($back); }
        $pdo->prepare("DELETE FROM appt_time_slots WHERE id = ?")->execute([$id]);
        try { $pdo->prepare("DELETE FROM appt_day_slots WHERE start_time = ?")->execute([$s['start_time']]); }
        catch (Throwable $e) { /* no calendar changes table yet */ }
        $label = slot_label($s['start_time'], $s['end_time']);
        record_audit($pdo, 'schedule_update', $id, $label, 'Removed time slot ' . $label);
        set_flash('Time slot ' . $label . ' removed. Appointments already booked at that time are kept.');
        redirect($back);

    // ---------------------------------------------------------- weekdays
    case 'days_save':
        $open = array_map('intval', (array)($_POST['days'] ?? []));
        $names = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
        $st = $pdo->prepare("INSERT INTO appt_weekdays (dow, is_open) VALUES (?, ?)
                             ON DUPLICATE KEY UPDATE is_open = VALUES(is_open)");
        foreach ($names as $dow => $_) $st->execute([$dow, in_array($dow, $open, true) ? 1 : 0]);
        $list = implode(', ', array_map(fn($d) => $names[$d], array_values(array_intersect(array_keys($names), $open))));
        record_audit($pdo, 'schedule_update', null, 'Open days', 'Set open days to ' . ($list ?: 'none'));
        set_flash($list ? 'Open days saved: ' . $list . '.' : 'Saved — no regular open days. Bookings are only possible on dates you mark as open.');
        redirect($back);

    // ------------------------------------------------------ special dates
    case 'date_save':
        $id   = (int)($_POST['id'] ?? 0);
        $date = trim($_POST['the_date'] ?? '');
        $open = ($_POST['is_open'] ?? '0') === '1' ? 1 : 0;
        $note = mb_substr(trim($_POST['note'] ?? ''), 0, 150);
        $d    = DateTime::createFromFormat('Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date) { set_flash('Please choose a valid date.'); redirect($back); }

        $dup = $pdo->prepare("SELECT COUNT(*) FROM appt_special_dates WHERE the_date = ? AND id <> ?");
        $dup->execute([$date, $id]);
        if ((int)$dup->fetchColumn() > 0) { set_flash(fmt_date($date) . ' is already on the list — edit that entry instead.'); redirect($back); }

        $desc = fmt_date($date) . ' marked ' . ($open ? 'open' : 'closed') . ($note !== '' ? ' (' . $note . ')' : '');
        if ($id) {
            $pdo->prepare("UPDATE appt_special_dates SET the_date = ?, is_open = ?, note = ? WHERE id = ?")
                ->execute([$date, $open, $note !== '' ? $note : null, $id]);
            record_audit($pdo, 'schedule_update', $id, fmt_date($date), 'Updated special date: ' . $desc);
            set_flash('Special date updated: ' . $desc . '.');
        } else {
            $pdo->prepare("INSERT INTO appt_special_dates (the_date, is_open, note) VALUES (?, ?, ?)")
                ->execute([$date, $open, $note !== '' ? $note : null]);
            record_audit($pdo, 'schedule_update', (int)$pdo->lastInsertId(), fmt_date($date), 'Added special date: ' . $desc);
            set_flash('Special date added: ' . $desc . '.');
        }
        redirect($back);

    case 'date_delete':
        $id = (int)($_POST['id'] ?? 0);
        $row = $pdo->prepare("SELECT the_date FROM appt_special_dates WHERE id = ?");
        $row->execute([$id]);
        $date = $row->fetchColumn();
        if (!$date) { set_flash('That date is no longer on the list.'); redirect($back); }
        $pdo->prepare("DELETE FROM appt_special_dates WHERE id = ?")->execute([$id]);
        record_audit($pdo, 'schedule_update', $id, fmt_date($date), 'Removed special date ' . fmt_date($date));
        set_flash(fmt_date($date) . ' removed — it now follows the regular weekly schedule.');
        redirect($back);

    // ------------------------------------------------- open weekdays in a month
    case 'month_days':
        $m = $_POST['month'] ?? '';
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $m)) { set_flash('Please choose a valid month.'); redirect($back); }
        $picked = array_map('intval', (array)($_POST['days'] ?? []));   // 1 = Mon ... 7 = Sun
        $names  = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
        $upOpen = $pdo->prepare("INSERT INTO appt_special_dates (the_date, is_open, note) VALUES (?, 1, NULL)
                                 ON DUPLICATE KEY UPDATE is_open = 1");
        $delD   = $pdo->prepare("DELETE FROM appt_special_dates WHERE the_date = ?");
        $delS   = $pdo->prepare("DELETE FROM appt_day_slots WHERE the_date = ?");
        $nOpen = 0; $nClosed = 0;
        $pdo->beginTransaction();
        for ($ts = strtotime($m . '-01'), $end = strtotime(date('Y-m-t', $ts)); $ts <= $end; $ts = strtotime('+1 day', $ts)) {
            $d = date('Y-m-d', $ts);
            if ($d < date('Y-m-d')) continue;
            if (in_array((int)date('N', $ts), $picked, true)) { $upOpen->execute([$d]); $nOpen++; }
            else { $delD->execute([$d]); $delS->execute([$d]); $nClosed++; }
        }
        $pdo->commit();
        $list = implode(', ', array_map(fn($n) => $names[$n], array_values(array_intersect(array_keys($names), $picked))));
        $label = date('F Y', strtotime($m . '-01'));
        record_audit($pdo, 'schedule_update', null, $label, 'Set open days for ' . $label . ': ' . ($list ?: 'none'));
        set_flash($list ? "$label: $nOpen day" . ($nOpen !== 1 ? 's' : '') . " opened ($list); the rest are closed." : "$label: all remaining days closed.");
        redirect($back . '?m=' . $m);

    // ------------------------------------------------- one-click open/close
    case 'day_toggle':
        $date = trim($_POST['the_date'] ?? '');
        $open = ($_POST['is_open'] ?? '') === '1';
        $dates = calendar_target_dates($date, 'day');
        if ($dates === null) { set_flash('Please choose a valid date.'); redirect($back); }
        if (!$dates)         { set_flash('Past days can\'t be changed.'); redirect($back); }
        $keep = $pdo->prepare("SELECT note FROM appt_special_dates WHERE the_date = ?");
        $keep->execute([$date]);
        $note = (string)$keep->fetchColumn();
        if ($open || $note !== '') {
            $pdo->prepare("INSERT INTO appt_special_dates (the_date, is_open, note) VALUES (?, ?, ?)
                           ON DUPLICATE KEY UPDATE is_open = VALUES(is_open)")
                ->execute([$date, $open ? 1 : 0, $note !== '' ? $note : null]);
        } else {
            $pdo->prepare("DELETE FROM appt_special_dates WHERE the_date = ?")->execute([$date]);
        }
        $n = 0;
        if (!$open) {
            $c = $pdo->prepare("SELECT COUNT(*) FROM appointments a JOIN patients p ON p.id = a.patient_id
                                WHERE a.appt_date = ? AND a.status IN ('Pending','Scheduled') AND p.deleted_at IS NULL");
            $c->execute([$date]);
            $n = (int)$c->fetchColumn();
        }
        record_audit($pdo, 'schedule_update', null, fmt_date($date), ($open ? 'Opened ' : 'Closed ') . fmt_date($date));
        set_flash(fmt_date($date) . ($open ? ' is now open for bookings.' : ' is now closed.' . ($n ? ' The ' . $n . ' appointment' . ($n !== 1 ? 's' : '') . ' already booked that day ' . ($n !== 1 ? 'are' : 'is') . ' kept.' : '')));
        redirect($back);

    // ------------------------------------------------- calendar day editor
    case 'day_save':
    case 'day_reset':
        $date  = trim($_POST['the_date'] ?? '');
        $scope = in_array($_POST['scope'] ?? 'day', ['day', 'weekday', 'month'], true) ? $_POST['scope'] : 'day';
        $dates = calendar_target_dates($date, $scope);
        if ($dates === null) { set_flash('Please choose a valid date.'); redirect($back); }
        if (!$dates)         { set_flash('Past days can\'t be changed.'); redirect($back); }
        $first   = $dates[0];
        $where   = $scope === 'day' ? fmt_date($first)
                 : ($scope === 'weekday' ? count($dates) . ' ' . date('l', strtotime($first)) . (count($dates) !== 1 ? 's' : '') . ' in ' . date('F Y', strtotime($first))
                 : count($dates) . ' day' . (count($dates) !== 1 ? 's' : '') . ' in ' . date('F Y', strtotime($first)) . ' (from ' . fmt_date($first) . ')');

        $delSpecial = $pdo->prepare("DELETE FROM appt_special_dates WHERE the_date = ?");
        $delSlots   = $pdo->prepare("DELETE FROM appt_day_slots WHERE the_date = ?");

        if ($op === 'day_reset') {
            $pdo->beginTransaction();
            foreach ($dates as $d) { $delSpecial->execute([$d]); $delSlots->execute([$d]); }
            $pdo->commit();
            record_audit($pdo, 'schedule_update', null, fmt_date($first), 'Reset ' . $where . ' to the usual schedule');
            set_flash('Reset ' . $where . ' to the usual schedule.');
            redirect($back);
        }

        $open = ($_POST['is_open'] ?? '1') === '1';
        $note = mb_substr(trim($_POST['note'] ?? ''), 0, 150);
        // For a weekday/month edit, only spread open/closed + note to the
        // other days if the admin changed them; otherwise each day keeps its own.
        $dayChanged = $scope === 'day' || ($_POST['day_changed'] ?? '0') === '1';

        // Each slot: ticked = open; places blank = no limit, else 1–999.
        $posted = (array)($_POST['slots'] ?? []);
        $rules  = [];
        foreach (appt_slot_rows() as $s) {
            $t   = $s['start_time'];
            $p   = (array)($posted[$t] ?? []);
            $raw = trim((string)($p['cap'] ?? ''));
            if ($raw === '') {
                $cap = null;
            } elseif (ctype_digit($raw) && (int)$raw >= 1 && (int)$raw <= 999) {
                $cap = (int)$raw;
            } else {
                set_flash('Places for ' . slot_label($t, $s['end_time']) . ' must be a whole number from 1 to 999, or blank for no limit.');
                redirect($back);
            }
            $rules[$t] = ['open' => !empty($p['open']), 'cap' => $cap,
                          'def' => $s['capacity'] === null ? null : (int)$s['capacity']];
        }

        $upSpecial = $pdo->prepare("INSERT INTO appt_special_dates (the_date, is_open, note) VALUES (?, ?, ?)
                                    ON DUPLICATE KEY UPDATE is_open = VALUES(is_open), note = VALUES(note)");
        $upSlot    = $pdo->prepare("INSERT INTO appt_day_slots (the_date, start_time, is_open, capacity) VALUES (?, ?, ?, ?)
                                    ON DUPLICATE KEY UPDATE is_open = VALUES(is_open), capacity = VALUES(capacity)");
        $delSlot   = $pdo->prepare("DELETE FROM appt_day_slots WHERE the_date = ? AND start_time = ?");

        $pdo->beginTransaction();
        foreach ($dates as $d) {
            // Only keep a special-date row when the day differs from its
            // weekday's usual setting (or carries a note), so the Special
            // dates list stays meaningful.
            if ($dayChanged || $d === $first) {
                $usual = false;   // days are closed unless the admin opens them
                if ($open === $usual && $note === '') $delSpecial->execute([$d]);
                else $upSpecial->execute([$d, $open ? 1 : 0, $note !== '' ? $note : null]);
            }

            foreach ($rules as $t => $r) {
                if ($r['open'] && $r['cap'] === $r['def']) $delSlot->execute([$d, $t]);   // same as usual
                else $upSlot->execute([$d, $t, $r['open'] ? 1 : 0, $r['cap']]);
            }
        }
        $pdo->commit();

        $off     = array_keys(array_filter($rules, fn($r) => !$r['open']));
        $changed = array_keys(array_filter($rules, fn($r) => $r['open'] && $r['cap'] !== $r['def']));
        $parts = [];
        if ($dayChanged) $parts[] = ($open ? 'open' : 'closed') . ($note !== '' ? ' (' . $note . ')' : '');
        if ($off)        $parts[] = count($off) . ' slot' . (count($off) !== 1 ? 's' : '') . ' off';
        if ($changed)    $parts[] = count($changed) . ' slot limit' . (count($changed) !== 1 ? 's' : '') . ' changed';
        $desc = $parts ? implode(', ', $parts) : 'the usual time slots';
        record_audit($pdo, 'schedule_update', null, fmt_date($first), 'Changed ' . $where . ': ' . $desc);
        set_flash('Saved for ' . $where . ' — ' . $desc . '.');
        redirect($back);
}

/** The dates a calendar edit applies to, from today on: the one day, every
 *  same weekday in its month, or every day in its month — starting at the
 *  clicked day. null if the date is invalid. */
function calendar_target_dates($date, $scope) {
    $d = DateTime::createFromFormat('!Y-m-d', $date);
    if (!$d || $d->format('Y-m-d') !== $date) return null;
    $today = date('Y-m-d');
    if ($scope === 'day') return $date >= $today ? [$date] : [];
    $out = [];
    $end = $d->format('Y-m-t');
    $dow = $d->format('N');
    for ($c = clone $d; $c->format('Y-m-d') <= $end; $c->modify('+1 day')) {
        $v = $c->format('Y-m-d');
        if ($v < $today) continue;
        if ($scope === 'weekday' && $c->format('N') !== $dow) continue;
        $out[] = $v;
    }
    return $out;
}

redirect($back);
