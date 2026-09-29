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
$sql = "SELECT a.*, p.name AS pet_name, p.species, p.breed, p.id AS pid, CONCAT_WS(' ', NULLIF(o.first_name,''), NULLIF(o.middle_name,''), NULLIF(o.last_name,'')) AS owner_name, o.first_name AS owner_first, o.middle_name AS owner_middle, o.last_name AS owner_last
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
$sql .= " ORDER BY a.appt_date ASC, a.appt_time ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$appts = $stmt->fetchAll();

// Patient list for the "schedule" dropdown (staff only).
$allPatients = [];
if ($staff) {
    $allPatients = $pdo->query("
        SELECT p.id, p.name, p.species, o.id AS owner_id, CONCAT_WS(' ', NULLIF(o.first_name,''), NULLIF(o.middle_name,''), NULLIF(o.last_name,'')) AS owner_name, o.first_name AS owner_first, o.middle_name AS owner_middle, o.last_name AS owner_last
        FROM patients p JOIN owners o ON o.id = p.owner_id WHERE p.deleted_at IS NULL
        ORDER BY o.last_name, o.first_name, o.id, p.name
    ")->fetchAll();

    // Group pets under their owner so the owner name is listed once
    // (removes the repeated-owner clutter in the flat dropdown).
    $patientsByOwner = [];
    foreach ($allPatients as $pt) {
        $oid = (int)$pt['owner_id'];
        if (!isset($patientsByOwner[$oid])) {
            $patientsByOwner[$oid] = [
                'label' => format_name_formal($pt['owner_first'], $pt['owner_middle'], $pt['owner_last']),
                'pets'  => [],
            ];
        }
        $patientsByOwner[$oid]['pets'][] = $pt;
    }
}

require 'includes/header.php';
?>

<form method="get" class="vp-toolbar" id="apptFilterForm" data-search-form data-search-key="appts">
  <div class="vp-search">
    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
    <input type="text" name="q" id="apptSearch" data-search-input value="<?= e($q) ?>" placeholder="<?= $staff ? 'Search by owner, pet, reason…' : 'Search by pet, reason…' ?>" autocomplete="off">
  </div>
  <!-- Keeps the active status filter when the search box submits. -->
  <input type="hidden" name="status" value="<?= e($filter) ?>">
  <div class="vp-filter-chips">
    <?php foreach (['All','Today','Scheduled','Completed'] as $f): ?>
      <!-- Carry the search term along so switching filters doesn't clear it. -->
      <a href="appointments.php?status=<?= $f ?><?= $q !== '' ? '&q=' . urlencode($q) : '' ?>" class="vp-chip <?= $filter === $f ? 'active' : '' ?>"><?= $f ?></a>
    <?php endforeach; ?>
  </div>
  <?php if ($staff): ?>
    <button type="button" class="vp-btn-primary" data-open-modal="modal-schedule">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
      Schedule appointment
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
              <strong><?= $staff ? e(format_name_formal($a['owner_first'], $a['owner_middle'], $a['owner_last'])) : e($a['pet_name']) ?></strong>
              <span class="vp-appt-breed"><?php if ($staff): ?><?= e($a['pet_name']) ?> · <?php endif; ?><?= e($a['species']) ?> · <?= e($a['breed']) ?></span>
            </div>
            <span class="vp-appt-reason"><?= e($a['reason']) ?></span>
            <span class="vp-appt-time">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
              <?= fmt_time($a['appt_time']) ?>
            </span>
          </div>
          <div class="vp-appt-full-actions">
            <?= status_pill($a['status']) ?>
            <?php if ($staff && $a['status'] === 'Scheduled'): ?>
              <form method="post" action="actions/complete_appointment.php" style="display:inline">
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

<?php if ($staff): ?>
  <!-- Schedule appointment modal -->
  <div class="vp-modal-overlay" id="modal-schedule">
    <div class="vp-modal">
      <div class="vp-modal-head">
        <div><h3>Schedule appointment</h3><p>Book a visit for a patient.</p></div>
        <button type="button" class="vp-modal-x" data-close-modal><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
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
                      <option value="<?= (int)$pt['id'] ?>"><?= e($pt['name']) ?> · <?= e($pt['species']) ?></option>
                    <?php endforeach; ?>
                  </optgroup>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="vp-field"><label>Date</label><input type="date" name="appt_date" value="<?= date('Y-m-d', strtotime('+1 day')) ?>"></div>
            <div class="vp-field"><label>Time</label><input type="time" name="appt_time" value="10:00"></div>
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
<?php endif; ?>

<?php require 'includes/footer.php'; ?>
