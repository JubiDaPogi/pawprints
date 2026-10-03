<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_login();

$user  = current_user();
$staff = is_staff();

$PAGE = 'appointments';
$PAGE_TITLE = 'Appointments';

$filter = $_GET['status'] ?? 'All';
$q      = trim($_GET['q'] ?? '');

// Build query — staff see all, owners see only their pets'.
$sql = "SELECT a.*, p.name AS pet_name, p.species, p.breed, p.id AS pid, CONCAT_WS(' ', NULLIF(o.first_name,''), NULLIF(o.middle_name,''), NULLIF(o.last_name,'')) AS owner_name, o.first_name AS owner_first, o.middle_name AS owner_middle, o.last_name AS owner_last, o.email AS owner_email
        FROM appointments a
        JOIN patients p ON p.id = a.patient_id
        JOIN owners o ON o.id = p.owner_id
        WHERE p.deleted_at IS NULL";
$params = [];

if (!$staff) {
    $sql .= " AND p.owner_id = ?";
    $params[] = (int)$user['owner_id'];
}
if ($filter === 'Today') {
    $sql .= " AND a.appt_date = CURDATE()";
} elseif ($filter !== 'All') {
    $sql .= " AND a.status = ?";
    $params[] = $filter;
}
if ($q !== '') {
    // Match the pet, breed, species, reason, or the owner's full name.
    $sql .= " AND (p.name LIKE ? OR p.breed LIKE ? OR p.species LIKE ? OR a.reason LIKE ? OR CONCAT_WS(' ', NULLIF(o.first_name,''), NULLIF(o.middle_name,''), NULLIF(o.last_name,'')) LIKE ?)";
    $like = "%$q%";
    array_push($params, $like, $like, $like, $like, $like);
}
// Completed and Declined are both settled — nothing left to act on — so
// push them to the bottom regardless of date, keeping everything else
// (what still needs attention) sorted by when it's happening, soonest
// first.
$sql .= " ORDER BY (a.status IN ('Completed','Declined')) ASC, a.appt_date ASC, a.appt_time ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$appts = $stmt->fetchAll();

// Patient list for the "schedule" dropdown (staff only).
$allPatients = [];
if ($staff) {
    $allPatients = $pdo->query("
        SELECT p.id, p.name, p.species, p.breed, o.id AS owner_id, CONCAT_WS(' ', NULLIF(o.first_name,''), NULLIF(o.middle_name,''), NULLIF(o.last_name,'')) AS owner_name, o.first_name AS owner_first, o.middle_name AS owner_middle, o.last_name AS owner_last, o.email AS owner_email
        FROM patients p JOIN owners o ON o.id = p.owner_id WHERE p.deleted_at IS NULL
        ORDER BY o.last_name, o.first_name, o.id, p.name
    ")->fetchAll();

    // Group pets under their owner so the owner name is listed once
    // (removes the repeated-owner clutter in the flat dropdown).
    $patientsByOwner = [];
    foreach ($allPatients as $pt) {
        $oid = (int)$pt['owner_id'];
        if (!isset($patientsByOwner[$oid])) {
            $label = format_name_formal($pt['owner_first'], $pt['owner_middle'], $pt['owner_last']);
            if (!empty($pt['owner_email'])) $label .= ' · ' . $pt['owner_email'];
            $patientsByOwner[$oid] = [
                'label' => $label,
                'pets'  => [],
            ];
        }
        $patientsByOwner[$oid]['pets'][] = $pt;
    }
}

// This owner's own pets, for the "Request appointment" form.
$myPets = [];
if (!$staff) {
    $myPets = $pdo->prepare("SELECT id, name, species, breed FROM patients WHERE owner_id = ? AND deleted_at IS NULL ORDER BY name");
    $myPets->execute([(int)$user['owner_id']]);
    $myPets = $myPets->fetchAll();
}
$slots = appointment_slots();

// Default to the next open day so both booking forms' date fields never
// open on a day the clinic is closed.
$defaultApptDate = next_bookable_date();
// The browser-side date check mirrors is_bookable_date(): weekly pattern
// (JS getDay(): 0 = Sunday ... 6 = Saturday) plus date-specific overrides.
$schedJs = [
    'open'    => array_fill(0, 7, false),   // no automatic weekdays — only days the clinic opened
    'special' => appt_upcoming_special_dates(),
    // Per-slot daily limits (null = no limit) and places already taken
    // per upcoming date, so the Time dropdown can show what's left and
    // grey out full slots. The server re-checks with slot_has_room().
    'cap'     => (function () { $c = []; foreach (appt_slot_rows() as $r) $c[$r['start_time']] = $r['capacity'] === null ? null : (int)$r['capacity']; return $c; })(),
    'used'    => upcoming_slot_usage(),
    'today'   => date('Y-m-d'),
    'now'     => date('H:i:s'),   // slots that have started today can't be booked
    // Philippine holidays, so the booking calendar can mark them.
    'holidays' => (function () { $h = []; for ($y = (int)date('Y'); $y <= (int)date('Y') + 3; $y++) foreach (ph_holidays($y) as $d => $x) $h[$d] = $x['name']; return $h; })(),
    // Per-date slot changes from the Schedule calendar (slot off / own limit).
    'days'    => appt_day_slot_overrides(date('Y-m-d'), date('Y-m-d', strtotime('+1 year'))),
];

require 'includes/header.php';
?>

<form method="get" class="vp-toolbar" id="apptFilterForm" data-search-form data-search-key="appts">
  <div class="vp-search">
    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
    <input type="text" name="q" id="apptSearch" data-search-input value="<?= e($q) ?>" placeholder="<?= $staff ? 'Search by owner, pet, reason…' : 'Search by pet, reason…' ?>" autocomplete="off">
  </div>
  <!-- Keeps the active status filter when the search box submits. -->
  <input type="hidden" name="status" value="<?= e($filter) ?>">
  <div class="vp-filter-chips" data-label="Status">
    <?php foreach (['All','Today','Pending','Scheduled','Completed','Declined'] as $f): ?>
      <!-- Carry the search term along so switching filters doesn't clear it. -->
      <a href="appointments.php?status=<?= $f ?><?= $q !== '' ? '&q=' . urlencode($q) : '' ?>" class="vp-chip <?= $filter === $f ? 'active' : '' ?>"><?= $f ?></a>
    <?php endforeach; ?>
  </div>
  <?php if ($staff): ?>
    <button type="button" class="vp-btn-primary" data-open-modal="modal-schedule">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
      Schedule appointment
    </button>
  <?php else: ?>
    <button type="button" class="vp-btn-primary" data-open-modal="modal-request" <?= !$myPets ? 'disabled title="Add a pet to your account first"' : '' ?>>
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
      Request appointment
    </button>
  <?php endif; ?>
</form>

<div class="vp-card">
  <?php if (!$appts): ?>
    <?= empty_row('cal', $q !== '' ? 'No appointments match your search.' : 'No appointments to show.') ?>
  <?php else: ?>
    <div class="vp-appt-full">
      <?php foreach ($appts as $a): ?>
        <div class="vp-appt-full-row">
          <div class="vp-appt-cal">
            <span class="vp-appt-mon"><?= date('M', strtotime($a['appt_date'])) ?></span>
            <span class="vp-appt-day"><?= date('j', strtotime($a['appt_date'])) ?></span>
          </div>
          <div class="vp-appt-full-info">
            <div class="vp-appt-full-top">
              <strong>
                <?php if ($staff): ?>
                  <?= e(format_name_formal($a['owner_first'], $a['owner_middle'], $a['owner_last'])) ?>
                  <?php if (!empty($a['owner_email'])): ?><span class="vp-appt-email"><?= e($a['owner_email']) ?></span><?php endif; ?>
                <?php else: ?>
                  <?= e($a['pet_name']) ?>
                <?php endif; ?>
              </strong>
              <span class="vp-appt-breed"><?php if ($staff): ?><?= e($a['pet_name']) ?> · <?php endif; ?><?= e($a['species']) ?><?= !empty($a['breed']) ? ' · ' . e($a['breed']) : '' ?></span>
            </div>
            <span class="vp-appt-reason"><?= e($a['reason']) ?></span>
            <span class="vp-appt-time">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
              <?= fmt_time($a['appt_time']) ?>
            </span>
            <?php if ($a['status'] === 'Declined' && !empty($a['decline_reason'])): ?>
              <span class="vp-appt-declined-note">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
                <?= e($a['decline_reason']) ?>
              </span>
            <?php endif; ?>
          </div>
          <div class="vp-appt-full-actions">
            <?= status_pill($a['status']) ?>
            <?php if ($staff && $a['status'] === 'Pending'): ?>
              <form method="post" action="actions/approve_appointment.php" style="display:inline"
                    data-confirm="Approve this request and schedule the appointment for <?= e($a['pet_name']) ?> on <?= e(fmt_date($a['appt_date'])) ?> at <?= e(fmt_time($a['appt_time'])) ?>?"
                    data-confirm-title="Approve appointment" data-confirm-yes="Approve" data-confirm-tone="ok">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                <button type="submit" class="vp-btn-tiny">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> Approve
                </button>
              </form>
              <button type="button" class="vp-btn-tiny ghost" data-open-modal="modal-decline-<?= (int)$a['id'] ?>">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg> Decline
              </button>
            <?php endif; ?>
            <?php if ($staff && $a['status'] === 'Scheduled'): ?>
              <form method="post" action="actions/complete_appointment.php" style="display:inline"
                    data-confirm="Mark <?= e($a['pet_name']) ?>'s appointment on <?= e(fmt_date($a['appt_date'])) ?> as completed?"
                    data-confirm-title="Mark as done" data-confirm-yes="Mark done" data-confirm-tone="ok">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                <button type="submit" class="vp-btn-tiny">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg> Mark done
                </button>
              </form>
            <?php endif; ?>
            <a class="vp-btn-tiny ghost" href="patient.php?id=<?= (int)$a['pid'] ?>">Chart</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php if ($staff):
  // Decline modals live OUTSIDE the list card on purpose: the card has an
  // entrance animation (a transform), which would trap a position:fixed
  // overlay inside the card instead of covering the whole screen.
  foreach ($appts as $a):
    if ($a['status'] !== 'Pending') continue; ?>
  <div class="vp-modal-overlay" id="modal-decline-<?= (int)$a['id'] ?>">
    <div class="vp-modal">
      <div class="vp-modal-head vp-modal-head-center">
        <div>
          <div class="vp-confirm-icon decline">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
          </div>
          <h3>Decline appointment request</h3>
          <p>Let the owner know why — this is shown on their appointment.</p>
        </div>
      </div>
      <form method="post" action="actions/decline_appointment.php">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
        <div class="vp-modal-body">
          <div class="vp-field full">
            <label>Reason / remarks <small>(optional)</small></label>
            <textarea name="decline_reason" rows="4" placeholder="e.g. That slot is no longer available — please request a different time."></textarea>
          </div>
          <div class="vp-form-actions">
            <button type="button" class="vp-btn-ghost" data-close-modal>Cancel</button>
            <button type="submit" class="vp-btn-primary">Decline request</button>
          </div>
        </div>
      </form>
    </div>
  </div>
<?php endforeach; endif; ?>

<?php if ($staff): ?>
  <!-- Schedule appointment modal -->
  <div class="vp-modal-overlay" id="modal-schedule">
    <div class="vp-modal">
      <div class="vp-modal-head">
        <div><h3>Schedule appointment</h3><p>Book a visit for a patient.</p></div>
      </div>
      <form method="post" action="actions/create_appointment.php">
        <?= csrf_field() ?>
        <div class="vp-modal-body">
          <div class="vp-form-grid">
            <div class="vp-field full"><label>Patient</label>
              <select name="patient_id" required data-searchable
                      data-placeholder="Select a patient…"
                      data-search-placeholder="Search owner or pet…">
                <?php foreach ($patientsByOwner as $grp): ?>
                  <optgroup label="<?= e($grp['label']) ?>">
                    <?php foreach ($grp['pets'] as $pt): ?>
                      <option value="<?= (int)$pt['id'] ?>"><?= e($pt['name']) ?> · <?= e($pt['species']) ?><?= !empty($pt['breed']) ? ' · ' . e($pt['breed']) : '' ?></option>
                    <?php endforeach; ?>
                  </optgroup>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="vp-field"><label>Day <small>(green = open)</small></label>
              <input type="hidden" name="appt_date" id="scheduleDate" value="<?= e($defaultApptDate) ?>">
              <button type="button" class="vp-cal-trigger" data-cal-for="scheduleDate" aria-expanded="false" aria-controls="scheduleDateCal">
                <span class="vp-cal-trigger-text">Choose a day</span>
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4.5" width="18" height="17" rx="2.5"/><path d="M3 9.5h18M8 2.5v4M16 2.5v4"/></svg>
              </button>
              <small class="vp-field-note" id="scheduleDateNote"></small>
            </div>
            <div class="vp-field"><label>Time</label>
              <select name="appt_time" required>
                <?php if (!$slots): ?><option value="">No time slots set up yet</option><?php endif; ?>
                <?php foreach ($slots as $val => $label): ?>
                  <option value="<?= e($val) ?>"><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="vp-field full vp-cal" id="scheduleDateCal" hidden></div>
            <div class="vp-field full"><label>Reason</label><input name="reason" placeholder="e.g. Vaccination, check-up" required></div>
          </div>
          <div class="vp-form-actions">
            <button type="button" class="vp-btn-ghost" data-close-modal>Cancel</button>
            <button type="submit" class="vp-btn-primary">Schedule</button>
          </div>
        </div>
      </form>
    </div>
  </div>
<?php else: ?>
  <!-- Request appointment modal (pet owner) -->
  <div class="vp-modal-overlay" id="modal-request">
    <div class="vp-modal">
      <div class="vp-modal-head">
        <div><h3>Request appointment</h3><p>Pick a day and time — the clinic will confirm it.</p></div>
      </div>
      <form method="post" action="actions/request_appointment.php" id="apptRequestForm">
        <?= csrf_field() ?>
        <div class="vp-modal-body">
          <div class="vp-form-grid">
            <div class="vp-field full"><label>Pet</label>
              <select name="patient_id" required>
                <?php foreach ($myPets as $pt): ?>
                  <option value="<?= (int)$pt['id'] ?>"><?= e($pt['name']) ?> · <?= e($pt['species']) ?><?= !empty($pt['breed']) ? ' · ' . e($pt['breed']) : '' ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="vp-field"><label>Day <small>(green = open)</small></label>
              <input type="hidden" name="appt_date" id="apptReqDate" value="<?= e($defaultApptDate) ?>">
              <button type="button" class="vp-cal-trigger" data-cal-for="apptReqDate" aria-expanded="false" aria-controls="apptReqDateCal">
                <span class="vp-cal-trigger-text">Choose a day</span>
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4.5" width="18" height="17" rx="2.5"/><path d="M3 9.5h18M8 2.5v4M16 2.5v4"/></svg>
              </button>
              <small class="vp-field-note" id="apptReqDateNote"></small>
            </div>
            <div class="vp-field"><label>Time</label>
              <select name="appt_time" required>
                <?php if (!$slots): ?><option value="">No time slots set up yet</option><?php endif; ?>
                <?php foreach ($slots as $val => $label): ?>
                  <option value="<?= e($val) ?>"><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="vp-field full vp-cal" id="apptReqDateCal" hidden></div>
            <div class="vp-field full"><label>Reason</label><input name="reason" placeholder="e.g. Vaccination, check-up" required></div>
          </div>
          <p class="vp-hint-text">Pick a green day — those are the days the clinic is open — and a time slot. Your pet's visit is confirmed once the clinic approves the request.</p>
          <div class="vp-form-actions">
            <button type="button" class="vp-btn-ghost" data-close-modal>Cancel</button>
            <button type="submit" class="vp-btn-primary">Send request</button>
          </div>
        </div>
      </form>
    </div>
  </div>
<?php endif; ?>

<!-- Booking calendar for both forms (staff "Schedule" and owner
     "Request"), driven by the schedule managed on schedule.php. Each day
     is coloured by the places it has left: green = available, red = full,
     grey = closed or past. The server re-checks everything with
     is_bookable_date() / slot_has_room(); this just guides the choice. -->
<script>
(function () {
  var SCHED  = <?= json_encode($schedJs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  var DAYS   = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
  var MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
  var slotKeys = Object.keys(SCHED.cap);

  // A slot's setting on one date: the calendar's change for that day if
  // there is one, otherwise its usual limit.
  function rule(v, k) {
    if (v === SCHED.today && k <= SCHED.now) return { open: false, cap: SCHED.cap[k], started: true };
    var d = SCHED.days[v];
    if (d && d[k]) return d[k];
    return { open: true, cap: SCHED.cap[k] };
  }

  var NOTE_ICO = "<svg class=\"vp-note-ico\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\" aria-label=\"Note\"><path d=\"M5 4h14v11l-5 5H5z\"\/><path d=\"M14 20v-5h5M8.5 9h7M8.5 12.5h4\"\/><\/svg>";
  function esc(t) { return String(t).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function fmt(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
  function parse(v) { var p = v.split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); }
  function pretty(v) {
    var d = parse(v);
    return DAYS[d.getDay()].slice(0, 3) + ', ' + MONTHS[d.getMonth()].slice(0, 3) + ' ' + d.getDate() + ', ' + d.getFullYear();
  }

  // How a day looks for booking.
  //   state: past | closed | full | open    left: places left (null = no limit)
  function dayInfo(v) {
    if (v < SCHED.today) return { state: 'past' };
    var sp = SCHED.special[v];
    var open = sp ? !!sp.open : !!SCHED.open[parse(v).getDay()];
    var keys = slotKeys.filter(function (k) { return rule(v, k).open; });
    if (!open || !keys.length) return { state: 'closed', note: sp && sp.note };
    var used = SCHED.used[v] || {}, left = 0, unlimited = false;
    keys.forEach(function (k) {
      var cap = rule(v, k).cap;
      if (cap === null || cap === undefined) unlimited = true;
      else left += Math.max(0, cap - (used[k] || 0));
    });
    if (unlimited) return { state: 'open', left: null };
    return left > 0 ? { state: 'open', left: left } : { state: 'full', left: 0 };
  }

  function makeTimePicker(sel) {
    var wrap = document.createElement('div');
    wrap.className = 'vp-ss vp-tp';
    sel.parentNode.insertBefore(wrap, sel);
    wrap.appendChild(sel);
    sel.classList.add('vp-ss-native');
    sel.tabIndex = -1;

    var trig = document.createElement('button');
    trig.type = 'button';
    trig.className = 'vp-ss-trigger';
    trig.setAttribute('aria-haspopup', 'listbox');
    trig.setAttribute('aria-expanded', 'false');
    trig.innerHTML = '<span class="vp-ss-label"></span><svg class="vp-ss-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>';
    var panel = document.createElement('div');
    panel.className = 'vp-ss-panel';
    panel.hidden = true;
    var list = document.createElement('div');
    list.className = 'vp-ss-list';
    list.setAttribute('role', 'listbox');
    panel.appendChild(list);
    wrap.appendChild(trig);
    wrap.appendChild(panel);

    function row(o) {
      return '<span class="vp-tp-time">' + esc(o.dataset.label || o.textContent) + '</span>'
        + (o.dataset.st ? '<span class="vp-tp-st ' + o.dataset.tone + '">' + esc(o.dataset.st) + '</span>' : '');
    }
    function render() {
      var cur = sel.options[sel.selectedIndex];
      trig.querySelector('.vp-ss-label').innerHTML = cur ? row(cur) : '';
      list.innerHTML = '';
      Array.prototype.forEach.call(sel.options, function (o) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'vp-ss-opt vp-tp-opt' + (o.selected ? ' selected' : '');
        b.setAttribute('role', 'option');
        b.disabled = o.disabled || !o.value;
        b.dataset.value = o.value;
        b.innerHTML = row(o);
        list.appendChild(b);
      });
    }
    function setOpen(open) {
      panel.hidden = !open;
      wrap.classList.toggle('open', open);
      trig.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open) place();
    }
    // Pin the list right under the field (above it if there's no room below),
    // exactly as wide as the field. Fixed positioning keeps the modal's
    // scroll area from clipping it.
    function place() {
      var box = trig.getBoundingClientRect(), h = Math.min(list.scrollHeight + 4, 272);
      var below = window.innerHeight - box.bottom - 12, up = below < h && box.top > below;
      // At least 280px wide so full times and statuses fit; the list grows to the
      // left of the field (its right edge stays aligned with the field's).
      var w = Math.min(Math.max(box.width, 280), window.innerWidth - 24);
      panel.style.left  = Math.max(12, box.right - w) + 'px';
      panel.style.width = w + 'px';
      panel.style.top    = up ? '' : (box.bottom + 6) + 'px';
      panel.style.bottom = up ? (window.innerHeight - box.top + 6) + 'px' : '';
      list.style.maxHeight = Math.max(140, (up ? box.top : below) - 18) + 'px';
    }
    // Scrolling the form or resizing the window closes it, so it never drifts.
    window.addEventListener('resize', function () { setOpen(false); });
    document.addEventListener('scroll', function (e) { if (!panel.hidden && !panel.contains(e.target)) setOpen(false); }, true);
    trig.addEventListener('click', function () { setOpen(panel.hidden); });
    list.addEventListener('click', function (e) {
      var b = e.target.closest('.vp-tp-opt');
      if (!b || b.disabled) return;
      sel.value = b.dataset.value;
      sel.dispatchEvent(new Event('change', { bubbles: true }));
      render();
      setOpen(false);
      trig.focus();
    });
    document.addEventListener('click', function (e) { if (!wrap.contains(e.target)) setOpen(false); });
    wrap.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) { e.stopPropagation(); setOpen(false); trig.focus(); } });
    render();
    return { render: render };
  }

  ['scheduleDate', 'apptReqDate'].forEach(function (id) {
    var input   = document.getElementById(id);
    var note    = document.getElementById(id + 'Note');
    var cal     = document.getElementById(id + 'Cal');
    var trigger = document.querySelector('[data-cal-for="' + id + '"]');
    if (!input || !note || !cal || !trigger) return;
    var timeSel = input.form.querySelector('select[name="appt_time"]');
    if (timeSel) {
      Array.prototype.forEach.call(timeSel.options, function (o) { o.dataset.label = o.textContent; });
    }

    // Time picker: a native <select> can't colour part of an option, so the
    // select is kept (for the form value) but shown as a custom list where
    // the status after each time is coloured — green available, red full,
    // grey started / not available.
    var picker = timeSel ? makeTimePicker(timeSel) : null;

    // If the suggested day is already full, start on the next one with room.
    if (!input.value || dayInfo(input.value).state !== 'open') {
      var t = parse(SCHED.today);
      for (var i = 0; i < 366; i++) {
        var v = fmt(new Date(t.getFullYear(), t.getMonth(), t.getDate() + i));
        if (dayInfo(v).state === 'open') { input.value = v; break; }
      }
    }
    var view;

    // Show places left in each slot for the chosen date; full slots are
    // greyed out and skipped if one was selected.
    function syncTimes() {
      if (!timeSel) return;
      var used = (input.value && SCHED.used[input.value]) || {};
      var firstOpen = null;
      Array.prototype.forEach.call(timeSel.options, function (o) {
        if (!o.value) return;
        var r = rule(input.value, o.value), cap = r.cap, label = o.dataset.label;
        var st, tone;
        if (!r.open) {
          st = r.started ? 'Started' : 'Unavailable'; tone = 'off';
        } else if (cap === null || cap === undefined) {
          st = 'Available'; tone = 'ok';
        } else {
          var left = cap - (used[o.value] || 0);
          st = left <= 0 ? 'Full' : left + ' left'; tone = left <= 0 ? 'full' : 'ok';
        }
        o.disabled = tone !== 'ok';
        o.dataset.st = st; o.dataset.tone = tone;
        o.textContent = label + ' — ' + st;
        if (!o.disabled && firstOpen === null) firstOpen = o;
      });
      var cur = timeSel.options[timeSel.selectedIndex];
      if ((!cur || cur.disabled) && firstOpen) timeSel.value = firstOpen.value;
      timeSel.setCustomValidity(firstOpen ? '' : 'Every slot is full on this date');
      if (picker) picker.render();
    }

    function check() {
      syncTimes();
      var v = input.value, msg = '';
      if (!v) {
        msg = 'Please choose a day.';
      } else {
        var info = dayInfo(v);
        if (info.state === 'past') msg = 'That day has already passed — please pick another day.';
        if (info.state === 'full') msg = 'Every slot is full on this day — please pick another day.';
        if (info.state === 'closed') msg = SCHED.special[v]
          ? 'The clinic is closed on this date' + (info.note ? ' (' + info.note + ')' : '') + ' — please pick another day.'
          : 'The clinic is closed on ' + DAYS[parse(v).getDay()] + 's — please pick another day.';
      }
      trigger.querySelector('.vp-cal-trigger-text').textContent = v ? pretty(v) : 'Choose a day';
      trigger.classList.toggle('is-invalid', !!msg);
      note.textContent = msg;
      return !msg;
    }

    function render() {
      var y = view.getFullYear(), m = view.getMonth(), t = parse(SCHED.today);
      var atStart = y < t.getFullYear() || (y === t.getFullYear() && m <= t.getMonth());
      var h = '<div class="vp-cal-head">'
        + '<button type="button" class="vp-cal-nav" data-nav="-1" aria-label="Previous month"' + (atStart ? ' disabled' : '') + '>&#8249;</button>'
        + '<strong>' + MONTHS[m] + ' ' + y + '</strong>'
        + '<button type="button" class="vp-cal-nav" data-nav="1" aria-label="Next month">&#8250;</button></div>'
        + '<div class="vp-cal-grid">';
      DAYS.forEach(function (d) { h += '<span class="vp-cal-dow">' + d.slice(0, 2) + '</span>'; });
      var lead = new Date(y, m, 1).getDay(), count = new Date(y, m + 1, 0).getDate();
      for (var i = 0; i < lead; i++) h += '<span></span>';
      for (var d = 1; d <= count; d++) {
        var v = fmt(new Date(y, m, d)), info = dayInfo(v), sub = '', title;
        if (info.state === 'open') {
          sub   = info.left === null ? 'Open' : info.left + ' left';
          title = info.left === null ? 'Available' : info.left + (info.left === 1 ? ' place' : ' places') + ' left';
        } else if (info.state === 'full') {
          sub = 'Full'; title = 'Fully booked';
        } else if (info.state === 'closed') {
          title = 'Closed' + (info.note ? ' — ' + info.note : '');
        } else {
          title = 'Past';
        }
        var hol = SCHED.holidays[v];
        if (hol) title += ' · ' + hol;
        var dnote = SCHED.special[v] && SCHED.special[v].note;
        if (dnote && info.state !== 'closed') title += ' · ' + dnote;
        var cls = 'vp-cal-day is-' + info.state + (v === input.value ? ' is-selected' : '') + (v === SCHED.today ? ' is-today' : '') + (hol ? ' is-holiday' : '') + (dnote ? ' has-note' : '');
        h += '<button type="button" class="' + cls + '" data-date="' + v + '" title="' + pretty(v) + ' · ' + title + '"'
           + (info.state === 'open' ? '' : ' disabled') + '>'
           + '<span class="vp-cal-num">' + d + '</span>'
           + (sub ? '<span class="vp-cal-left">' + sub + '</span>' : '') + ((hol || dnote) ? '<span class="vp-cal-marks">' + (hol ? '<i class="vp-cal-hol"></i>' : '') + (dnote ? '<span class="vp-cal-noteico">' + NOTE_ICO + '</span>' : '') + '</span>' : '') + '</button>';
      }
      // Holidays this month, spelled out (cells are too small for long names on phones).
      var hl = [];
      for (var q = 1; q <= count; q++) { var hv = fmt(new Date(y, m, q)); var sp = SCHED.special[hv], dn = MONTHS[m].slice(0, 3) + ' ' + q;
        var ph = SCHED.holidays[hv], pn = sp && sp.note;
        if (ph || pn) hl.push('<li><b>' + dn + '</b> ' + (ph ? '<span class="hol">' + esc(ph) + '</span> ' : '') + (pn ? '<span class="note">' + esc(pn) + '</span>' : '') + '</li>'); }
      h += '</div>' + (hl.length ? '<ul class="vp-cal-hols">' + hl.join('') + '</ul>' : '') + '<div class="vp-cal-legend">'
        + '<span><i class="is-open"></i>Available</span>'
        + '<span><i class="is-full"></i>Full</span>'
        + '<span><i class="is-closed"></i>Closed</span>'
        + '<span><i class="is-hol"></i>Holiday</span>'
        + '<span>' + NOTE_ICO + 'Note</span></div>';
      cal.innerHTML = h;
    }

    function setOpen(open) {
      cal.hidden = !open;
      trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open) {
        view = parse(input.value || SCHED.today);
        view.setDate(1);
        render();
        cal.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
      }
    }

    trigger.addEventListener('click', function () { setOpen(cal.hidden); });
    cal.addEventListener('click', function (e) {
      var nav = e.target.closest('[data-nav]');
      if (nav) { view.setMonth(view.getMonth() + (+nav.dataset.nav)); render(); return; }
      var day = e.target.closest('[data-date]');
      if (day && !day.disabled) {
        input.value = day.dataset.date;
        check();
        setOpen(false);
        trigger.focus();
      }
    });
    // Hidden inputs skip the browser's own validation, so check on submit.
    input.form.addEventListener('submit', function (e) {
      if (!check()) { e.preventDefault(); e.stopImmediatePropagation(); setOpen(true); }
    });
    // The calendar starts folded away each time the modal is opened.
    var overlay = input.closest('.vp-modal-overlay');
    if (overlay) new MutationObserver(function () { if (!overlay.classList.contains('open')) setOpen(false); })
      .observe(overlay, { attributes: true, attributeFilter: ['class'] });
    check();
  });
})();
</script>

<?php require 'includes/footer.php'; ?>
