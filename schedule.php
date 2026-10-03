<?php
/* ============================================================
   Appointment schedule — staff manage when appointments can be booked:
   the hour slots, which weekdays are open, and date-specific
   exceptions (holidays / one-off open days). Changes apply to both the
   staff "Schedule appointment" and owner "Request appointment" forms.
   ============================================================ */
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_staff();

$PAGE = 'schedule';
$PAGE_TITLE = 'Appointment Schedule';

$slotRows = $pdo->query("SELECT id, start_time, end_time, capacity FROM appt_time_slots ORDER BY start_time")->fetchAll();
$weekdays = appt_open_weekdays();
$specials = $pdo->query("SELECT id, the_date, is_open, note FROM appt_special_dates ORDER BY the_date")->fetchAll();
$today    = date('Y-m-d');

// Upcoming bookings per slot time / per date, so staff can see what an
// edit or deletion would touch before making it (bookings are never
// moved or cancelled by these changes — this is just for awareness).
$liveWhere = "a.appt_date >= CURDATE() AND a.status IN ('Pending','Scheduled') AND p.deleted_at IS NULL";
$slotCounts = [];
foreach ($pdo->query("SELECT a.appt_time, COUNT(*) n FROM appointments a JOIN patients p ON p.id = a.patient_id
                      WHERE $liveWhere GROUP BY a.appt_time") as $r) {
    $slotCounts[$r['appt_time']] = (int)$r['n'];
}
$dateCounts = [];
foreach ($pdo->query("SELECT a.appt_date, COUNT(*) n FROM appointments a JOIN patients p ON p.id = a.patient_id
                      WHERE $liveWhere GROUP BY a.appt_date") as $r) {
    $dateCounts[$r['appt_date']] = (int)$r['n'];
}

$dayNames = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

/* ---- Month calendar: every day of the month being viewed (?m=YYYY-MM),
        with how it's set up and how full it is. Clicking a day opens the
        day editor, which is filled from $calDays. ---- */
$calMonth = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $_GET['m'] ?? '') ? $_GET['m'] : date('Y-m');
$calFirst = $calMonth . '-01';
$calLast  = date('Y-m-t', strtotime($calFirst));
$calPrev  = date('Y-m', strtotime($calFirst . ' -1 month'));
$calNext  = date('Y-m', strtotime($calFirst . ' +1 month'));

$calSpecial = [];
$st = $pdo->prepare("SELECT the_date, is_open, note FROM appt_special_dates WHERE the_date BETWEEN ? AND ?");
$st->execute([$calFirst, $calLast]);
foreach ($st as $r) $calSpecial[$r['the_date']] = $r;

$calOver = appt_day_slot_overrides($calFirst, $calLast);

// Places taken per date and slot — same rule as booking: everything but
// Declined holds a place.
$calUsed = [];
$st = $pdo->prepare("SELECT a.appt_date, a.appt_time, COUNT(*) n FROM appointments a JOIN patients p ON p.id = a.patient_id
                     WHERE a.appt_date BETWEEN ? AND ? AND a.status <> 'Declined' AND p.deleted_at IS NULL
                     GROUP BY a.appt_date, a.appt_time");
$st->execute([$calFirst, $calLast]);
foreach ($st as $r) $calUsed[$r['appt_date']][$r['appt_time']] = (int)$r['n'];

$calDays = [];
for ($ts = strtotime($calFirst); $ts <= strtotime($calLast); $ts = strtotime('+1 day', $ts)) {
    $d    = date('Y-m-d', $ts);
    $wOpen = false;   // nothing is open until the admin opens it
    $sp   = $calSpecial[$d] ?? null;
    $open = $sp ? (bool)$sp['is_open'] : $wOpen;
    $slots = []; $booked = 0; $left = 0; $unlimited = false; $anyOpen = false; $custom = false;
    foreach ($slotRows as $s) {
        $t    = $s['start_time'];
        $def  = $s['capacity'] === null ? null : (int)$s['capacity'];
        $ov   = $calOver[$d][$t] ?? null;
        $sOpen = $ov ? $ov['open'] : true;
        $cap  = $ov ? $ov['cap'] : $def;
        $used = $calUsed[$d][$t] ?? 0;
        $booked += $used;
        if ($ov && (!$ov['open'] || $ov['cap'] !== $def)) $custom = true;   // only real differences get the dot
        if ($sOpen) {
            $anyOpen = true;
            if ($cap === null) $unlimited = true; else $left += max(0, $cap - $used);
        }
        $slots[] = ['id' => (int)$s['id'], 't' => $t, 'label' => slot_label($t, $s['end_time']), 'open' => $sOpen,
                    'cap' => $cap, 'def' => $def, 'used' => $used];
    }
    // Bookings at times that are no longer a slot still count as booked.
    foreach ($calUsed[$d] ?? [] as $t => $n) {
        if (!array_filter($slotRows, fn($s) => $s['start_time'] === $t)) $booked += $n;
    }
    $state = $d < $today ? 'past'
           : (!$open || !$anyOpen ? 'closed'
           : ($unlimited || $left > 0 ? 'open' : 'full'));
    $calDays[$d] = [
        'date' => $d, 'label' => date('l, F j, Y', $ts), 'dow' => date('l', $ts),
        'past' => $d < $today, 'weekdayOpen' => $wOpen, 'open' => $open,
        'note' => $sp['note'] ?? '', 'custom' => $custom, 'booked' => $booked,
        'holiday' => ($h = ph_holiday_on($d)) ? $h + ['typeLabel' => ph_holiday_type_label($h['type'])] : null,
        'state' => $state, 'left' => $unlimited ? null : $left, 'slots' => $slots,
    ];
}

/** Small inline icons used on this page. */
function sched_icon($name) {
    $p = [
        'plus'  => '<path d="M12 5v14M5 12h14"/>',
        'edit'  => '<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
        'trash' => '<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'cal'   => '<rect x="3" y="4.5" width="18" height="16" rx="2"/><path d="M3 9h18M8 2.5v4M16 2.5v4"/>',
        'week'  => '<rect x="3" y="4.5" width="18" height="16" rx="2"/><path d="M3 9h18M7 13h2M11 13h2M15 13h2M7 17h2M11 17h2"/>',
    ][$name];
    return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}

require 'includes/header.php';
?>

<p class="vp-sched-intro">
  These settings decide when appointments can be booked — for both the staff
  <strong>Schedule appointment</strong> form and pet owners' <strong>Request appointment</strong> form.
  Changing them never moves or cancels an appointment that's already booked.
</p>

<!-- Month calendar — click a day to change it -->
<div class="vp-card vp-scal">
  <div class="vp-card-head vp-scal-top">
    <h3><?= sched_icon('cal') ?> Calendar</h3>
    <div class="vp-scal-nav">
      <a class="vp-cal-nav" href="schedule.php?m=<?= e($calPrev) ?>" aria-label="Previous month">&#8249;</a>
      <strong><?= e(date('F Y', strtotime($calFirst))) ?></strong>
      <a class="vp-cal-nav" href="schedule.php?m=<?= e($calNext) ?>" aria-label="Next month">&#8250;</a>
      <?php if ($calMonth !== date('Y-m')): ?><a class="vp-btn-tiny ghost" href="schedule.php">This month</a><?php endif; ?>
    </div>
  </div>
  <p class="vp-hint-text vp-scal-hint">Click a day to switch it <strong>open (green)</strong> or <strong>closed (red)</strong>. Use the pencil on a day to edit it — add a note, turn single time slots off, change how many places each slot has, or apply a change to that weekday or the whole month.</p>
  <div class="vp-scal-grid">
    <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $dn): ?><span class="vp-cal-dow"><?= $dn ?></span><?php endforeach; ?>
    <?php for ($i = 0, $lead = (int)date('w', strtotime($calFirst)); $i < $lead; $i++): ?><span></span><?php endfor; ?>
    <?php foreach ($calDays as $d => $c):
        $sub = $c['state'] === 'open' ? ($c['left'] === null ? 'Open' : $c['left'] . ' left')
             : ($c['state'] === 'full' ? 'Full' : ($c['state'] === 'closed' ? 'Closed' : ''));
        $cls = 'vp-scal-day is-' . $c['state'] . ($c['custom'] ? ' is-custom' : '') . ($d === $today ? ' is-today' : '') . ($c['holiday'] ? ' is-holiday' : ''); ?>
      <div class="vp-scal-cell">
        <button type="button" class="<?= $cls ?>" data-toggle="<?= e($d) ?>" data-open="<?= $c['open'] ? 1 : 0 ?>" aria-pressed="<?= $c['open'] ? 'true' : 'false' ?>"
                title="<?= e($c['label']) ?> — click to <?= $c['open'] ? 'close' : 'open' ?><?= $c['holiday'] ? ' · ' . e($c['holiday']['name']) . ' (' . ph_holiday_type_label($c['holiday']['type']) . ')' : '' ?><?= $c['note'] !== '' ? ' · ' . e($c['note']) : '' ?>"<?= $c['past'] ? ' disabled' : '' ?>>
          <span class="vp-scal-num"><?= (int)substr($d, 8) ?><?php if ($c['holiday']): ?><svg class="vp-scal-flag" viewBox="0 0 24 24" fill="currentColor" aria-label="Holiday"><path d="M5 3v18M5 4h12l-2.5 4L17 12H5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg><?php endif; ?><?php if ($c['note'] !== ''): ?><svg class="vp-note-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-label="Note"><path d="M5 4h14v11l-5 5H5z"/><path d="M14 20v-5h5M8.5 9h7M8.5 12.5h4"/></svg><?php endif; ?></span>
          <?php if ($sub): ?><span class="vp-scal-sub"><?= e($sub) ?></span><?php endif; ?>
          <?php if ($c['booked']): ?><span class="vp-scal-booked"><?= $c['booked'] ?> booked</span><?php endif; ?>
        </button>
        <?php if (!$c['past']): ?>
          <button type="button" class="vp-scal-edit" data-day="<?= e($d) ?>" title="Edit <?= e($c['label']) ?>" aria-label="Edit <?= e($c['label']) ?>"><?= sched_icon('edit') ?></button>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <?php $monthHolidays = array_filter($calDays, fn($c) => $c['holiday'] || $c['note'] !== ''); ?>
  <?php if ($monthHolidays): ?>
    <ul class="vp-cal-hols vp-scal-hols">
      <?php foreach ($monthHolidays as $d => $c): ?>
        <li><b><?= e(date('M j', strtotime($d))) ?></b>
          <?php if ($c['holiday']): ?><span class="hol"><?= e($c['holiday']['name']) ?></span><?php endif; ?>
          <?php if ($c['note'] !== ''): ?><span class="note"><?= e($c['note']) ?></span><?php endif; ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
  <div class="vp-cal-legend vp-scal-legend">
    <span><i class="is-open"></i>Open</span>
    <span><i class="is-full"></i>Open but full</span>
    <span><i class="is-closed"></i>Closed</span>
    <span><svg class="vp-scal-flag" viewBox="0 0 24 24" fill="currentColor" aria-label="Holiday"><path d="M5 3v18M5 4h12l-2.5 4L17 12H5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Philippine holiday</span>
    <span><svg class="vp-note-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-label="Note"><path d="M5 4h14v11l-5 5H5z"/><path d="M14 20v-5h5M8.5 9h7M8.5 12.5h4"/></svg> Note</span>
    <span><b class="vp-scal-dot"></b>Slot changes</span>
  </div>
</div>

<?php
/* ---- Modals (kept outside the cards: the cards' entrance animation would
        otherwise trap these full-screen overlays inside them) ---- */

function slot_modal($id, $s) {
    $edit = $s !== null; ?>
  <div class="vp-modal-overlay" id="<?= $id ?>">
    <div class="vp-modal">
      <div class="vp-modal-head">
        <div><h3><?= $edit ? 'Edit time slot' : 'Add time slot' ?></h3><p>Set the time and how many pets can be seen in it. Slots can't overlap.</p></div>
      </div>
      <form method="post" action="actions/save_schedule.php">
        <?= csrf_field() ?>
        <input type="hidden" name="op" value="slot_save">
        <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><?php endif; ?>
        <div class="vp-modal-body">
          <div class="vp-form-grid">
            <div class="vp-field"><label>Start time</label><input type="time" name="start_time" required value="<?= $edit ? e(substr($s['start_time'], 0, 5)) : '' ?>"></div>
            <div class="vp-field"><label>End time</label><input type="time" name="end_time" required value="<?= $edit ? e(substr($s['end_time'], 0, 5)) : '' ?>"></div>
            <div class="vp-field full"><label>Places per day, every day <small>(leave blank for no limit)</small></label>
              <input type="number" name="capacity" min="1" max="999" step="1" placeholder="No limit" value="<?= $edit && $s['capacity'] !== null ? (int)$s['capacity'] : '' ?>">
              <small class="vp-field-note">How many appointments this slot takes on any one day. Pending requests hold a place until approved or declined.</small>
            </div>
          </div>
          <?php if ($edit): ?><p class="vp-hint-text">Appointments already booked at the old time keep their time.</p><?php endif; ?>
          <div class="vp-form-actions">
            <button type="button" class="vp-btn-ghost" data-close-modal>Cancel</button>
            <button type="submit" class="vp-btn-primary"><?= $edit ? 'Save changes' : 'Add slot' ?></button>
          </div>
        </div>
      </form>
    </div>
  </div>
<?php }

function date_modal($id, $d) {
    $edit = $d !== null; ?>
  <div class="vp-modal-overlay" id="<?= $id ?>">
    <div class="vp-modal">
      <div class="vp-modal-head">
        <div><h3><?= $edit ? 'Edit special date' : 'Add special date' ?></h3><p>Overrides the regular open days for this one date.</p></div>
      </div>
      <form method="post" action="actions/save_schedule.php">
        <?= csrf_field() ?>
        <input type="hidden" name="op" value="date_save">
        <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$d['id'] ?>"><?php endif; ?>
        <div class="vp-modal-body">
          <div class="vp-form-grid">
            <div class="vp-field"><label>Date</label>
              <input type="date" name="the_date" required value="<?= $edit ? e($d['the_date']) : '' ?>" <?= $edit ? '' : 'min="' . date('Y-m-d') . '"' ?>>
            </div>
            <div class="vp-field"><label>On this date the clinic is</label>
              <select name="is_open">
                <option value="0" <?= $edit && !$d['is_open'] ? 'selected' : '' ?>>Closed — no bookings</option>
                <option value="1" <?= $edit && $d['is_open'] ? 'selected' : '' ?>>Open — takes bookings</option>
              </select>
            </div>
            <div class="vp-field full"><label>Note <small>(optional — shown to owners on a closed date)</small></label>
              <input name="note" maxlength="150" placeholder="e.g. Christmas Day, Staff training" value="<?= $edit ? e($d['note'] ?? '') : '' ?>">
            </div>
          </div>
          <div class="vp-form-actions">
            <button type="button" class="vp-btn-ghost" data-close-modal>Cancel</button>
            <button type="submit" class="vp-btn-primary"><?= $edit ? 'Save changes' : 'Add date' ?></button>
          </div>
        </div>
      </form>
    </div>
  </div>
<?php }

slot_modal('modal-slot-new', null);
foreach ($slotRows as $s) slot_modal('modal-slot-' . (int)$s['id'], $s);
?>

<!-- Day editor — one modal, filled in by the script below from the day clicked -->
<div class="vp-modal-overlay" id="modal-day">
  <div class="vp-modal wide">
    <div class="vp-modal-head">
      <div><h3 id="dayTitle">Day</h3><p id="dayIntro">Changes apply to this date only unless you choose otherwise below.</p></div>
    </div>
    <form method="post" action="actions/save_schedule.php" id="dayForm">
      <?= csrf_field() ?>
      <input type="hidden" name="op" value="day_save">
      <input type="hidden" name="the_date" id="dayDate">
      <input type="hidden" name="day_changed" id="dayChanged" value="0">
      <div class="vp-modal-body">
        <div class="vp-form-grid">
          <div class="vp-field full"><label>On this day the clinic is</label>
            <div class="vp-scal-toggle">
              <label><input type="radio" name="is_open" value="1" id="dayOpen1"><span>Open — takes bookings</span></label>
              <label><input type="radio" name="is_open" value="0" id="dayOpen0"><span>Closed — no bookings</span></label>
            </div>
            <small class="vp-field-note" id="dayUsual"></small>
            <small class="vp-scal-holnote" id="dayHoliday" hidden></small>
          </div>
          <div class="vp-field full"><label>Note <small>(optional — e.g. holiday name)</small></label>
            <input name="note" id="dayNote" maxlength="150" placeholder="e.g. Christmas Day, Half day">
          </div>
          <div class="vp-field full" id="daySlotsField"><div class="vp-scal-slots-head"><label>Time slots</label>
              <button type="button" class="vp-btn-tiny ghost" data-slot-modal="modal-slot-new"><?= sched_icon('plus') ?> Add slot</button></div>
            <div class="vp-scal-slots" id="daySlots"></div>
            <small class="vp-field-note">Untick a slot to stop bookings at that time on this day; leave places blank for no limit. Editing or deleting a time slot changes it on every day.</small>
          </div>
          <div class="vp-field full"><label>Apply these settings to</label>
            <select name="scope" id="dayScope"></select>
          </div>
        </div>
        <p class="vp-sched-warn vp-scal-warn" id="dayWarn" hidden></p>
        <div class="vp-form-actions">
          <button type="button" class="vp-btn-ghost" data-close-modal>Cancel</button>
          <button type="submit" class="vp-btn-primary">Save day</button>
        </div>
      </div>
    </form>
  </div>
</div>
<?php foreach ($slotRows as $s): $sl = slot_label($s['start_time'], $s['end_time']); ?>
<form method="post" action="actions/save_schedule.php" id="slotDel-<?= (int)$s['id'] ?>" hidden
      data-confirm="Remove the <?= e($sl) ?> time slot from every day? It won't be bookable anymore. Appointments already booked at this time are kept."
      data-confirm-title="Remove time slot" data-confirm-yes="Remove">
  <?= csrf_field() ?>
  <input type="hidden" name="op" value="slot_delete">
  <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
</form>
<?php endforeach; ?>
<form method="post" action="actions/save_schedule.php" id="dayToggleForm" hidden>
  <?= csrf_field() ?>
  <input type="hidden" name="op" value="day_toggle">
  <input type="hidden" name="the_date" id="toggleDate">
  <input type="hidden" name="is_open" id="toggleOpen">
</form>
<script>
(function () {
  var DAYS = <?= json_encode($calDays, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  var monthName = <?= json_encode(date('F', strtotime($calFirst))) ?>;
  var modal = document.getElementById('modal-day');
  var ICON_EDIT = <?= json_encode(sched_icon('edit')) ?>;
  var ICON_TRASH = <?= json_encode(sched_icon('trash')) ?>;

  // Edit / Add slot open the slot dialogs (which sit above this one).
  modal.addEventListener('click', function (e) {
    var b = e.target.closest('[data-slot-modal]');
    if (!b) return;
    var m = document.getElementById(b.dataset.slotModal);
    if (m) m.classList.add('open');
  });
  var $ = function (id) { return document.getElementById(id); };
  function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

  function syncOpen() {
    var open = $('dayOpen1').checked;
    $('daySlotsField').classList.toggle('is-off', !open);
    var c = DAYS[$('dayDate').value];
    var warn = !open && c && c.booked
      ? c.booked + ' appointment' + (c.booked !== 1 ? 's are' : ' is') + ' already booked this day — closing it keeps them, but takes no new bookings.'
      : '';
    $('dayWarn').textContent = warn;
    $('dayWarn').hidden = !warn;
  }

  // Clicking a day flips it open <-> closed straight away.
  document.querySelectorAll('.vp-scal-day[data-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      $('toggleDate').value = btn.dataset.toggle;
      $('toggleOpen').value = btn.dataset.open === '1' ? '0' : '1';
      btn.disabled = true;
      $('dayToggleForm').requestSubmit();
    });
  });

  document.querySelectorAll('.vp-scal-edit[data-day]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var c = DAYS[btn.dataset.day];
      if (!c || c.past) return;
      $('dayTitle').textContent = c.label;
      $('dayDate').value = c.date;
      $('dayOpen1').checked = c.open;
      $('dayOpen0').checked = !c.open;
      $('dayNote').value = c.note || '';
      $('dayChanged').value = '0';
      $('dayUsual').textContent = 'Days stay closed until you open them.';
      $('dayHoliday').hidden = !c.holiday;
      if (c.holiday) $('dayHoliday').textContent = 'Philippine holiday: ' + c.holiday.name + ' (' + c.holiday.typeLabel + ')';
      $('daySlots').innerHTML = c.slots.length ? c.slots.map(function (s) {
        var base = 'slots[' + s.t + ']';
        return '<div class="vp-scal-slot">'
          + '<label class="vp-scal-slot-on"><input type="checkbox" name="' + base + '[open]" value="1"' + (s.open ? ' checked' : '') + '>'
          + '<i class="vp-scal-check" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></i>'
          + '<span>' + esc(s.label) + '</span></label>'
          + '<span class="vp-scal-slot-used">' + (s.used ? s.used + ' booked' : '') + '</span>'
          + '<input type="number" name="' + base + '[cap]" min="1" max="999" step="1" placeholder="No limit" aria-label="Places for ' + esc(s.label) + '"'
          + ' value="' + (s.cap === null ? '' : s.cap) + '">'
          + '<span class="vp-scal-slot-act">'
          + '<button type="button" class="vp-scal-slot-btn" data-slot-modal="modal-slot-' + s.id + '" title="Edit time slot" aria-label="Edit ' + esc(s.label) + '">' + ICON_EDIT + '</button>'
          + '<button type="submit" form="slotDel-' + s.id + '" class="vp-scal-slot-btn del" title="Delete time slot" aria-label="Delete ' + esc(s.label) + '">' + ICON_TRASH + '</button>'
          + '</span>'
          + '</div>';
      }).join('') : '<p class="vp-hint-text">No time slots set up yet — use Add slot above.</p>';
      $('dayScope').innerHTML =
          '<option value="day">This day only</option>'
        + '<option value="weekday">Every ' + c.dow + ' in ' + monthName + ' (from this day on)</option>'
        + '<option value="month">Every day in ' + monthName + ' (from this day on)</option>';
      syncOpen();
      modal.classList.add('open');
    });
  });
  // Open/closed and the note only spread to other days (weekday / month
  // choice) when they were actually changed here — so changing slot limits
  // for a month doesn't also open its Sundays.
  function markChanged() { $('dayChanged').value = '1'; }
  $('dayOpen1').addEventListener('change', function () { markChanged(); syncOpen(); });
  $('dayOpen0').addEventListener('change', function () { markChanged(); syncOpen(); });
  $('dayNote').addEventListener('input', markChanged);
})();
</script>

<?php require 'includes/footer.php'; ?>
