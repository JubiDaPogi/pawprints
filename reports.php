<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_staff();

$PAGE = 'reports';
$PAGE_TITLE = 'Reports & Analytics';

// Totals.
$totPatients = (int)$pdo->query("SELECT COUNT(*) FROM patients WHERE deleted_at IS NULL")->fetchColumn();
$totOwners   = (int)$pdo->query("SELECT COUNT(*) FROM owners")->fetchColumn();
$totVisits   = (int)$pdo->query("SELECT COUNT(*) FROM visits WHERE deleted_at IS NULL")->fetchColumn();
$totVacc     = (int)$pdo->query("SELECT COUNT(*) FROM vaccinations WHERE deleted_at IS NULL")->fetchColumn();

// By species.
$bySpecies = $pdo->query("SELECT species, COUNT(*) AS n FROM patients WHERE deleted_at IS NULL GROUP BY species ORDER BY n DESC")->fetchAll();
$maxSpecies = max(array_map(fn($r) => (int)$r['n'], $bySpecies) ?: [1]);

// By status.
$byStatus = $pdo->query("SELECT status, COUNT(*) AS n FROM patients WHERE deleted_at IS NULL GROUP BY status")->fetchAll();

// Visits by month (last 6 months present in the data).
$monthRows = $pdo->query("
    SELECT DATE_FORMAT(visit_date, '%b') AS mon, DATE_FORMAT(visit_date, '%Y-%m') AS ym, COUNT(*) AS n
    FROM visits WHERE deleted_at IS NULL GROUP BY ym, mon ORDER BY ym ASC
")->fetchAll();
$maxMonth = max(array_map(fn($r) => (int)$r['n'], $monthRows) ?: [1]);

// Appointment summary.
$apScheduled = (int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE status='Scheduled'")->fetchColumn();
$apCompleted = (int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE status='Completed'")->fetchColumn();
$apTotal     = (int)$pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn();

require 'includes/header.php';
?>

<div class="vp-stat-row">
  <?php
    render_stat('paw',   'Patients',           $totPatients, 'On record',         'teal');
    render_stat('users', 'Owners',             $totOwners,   'Registered clients','pine');
    render_stat('clip',  'Total visits logged',$totVisits,   'All-time',          'amber');
    render_stat('vax',   'Vaccines given',     $totVacc,     'All-time',          'rose');
  ?>
</div>

<div class="vp-grid-2">
  <!-- By species -->
  <div class="vp-card">
    <div class="vp-card-head"><h3><?= stat_icon('paw') ?> Patients by species</h3></div>
    <div class="vp-bar-list">
      <?php foreach ($bySpecies as $r): ?>
        <div class="vp-bar-item">
          <div class="vp-bar-label"><?= species_icon($r['species'], 15) ?> <?= e($r['species']) ?></div>
          <div class="vp-bar-track"><div class="vp-bar-fill" style="width:<?= ((int)$r['n'] / $maxSpecies) * 100 ?>%"></div></div>
          <span class="vp-bar-val"><?= (int)$r['n'] ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Visits by month -->
  <div class="vp-card">
    <div class="vp-card-head"><h3><?= stat_icon('chart') ?> Visits by month</h3></div>
    <div class="vp-col-chart">
      <?php foreach ($monthRows as $r): ?>
        <div class="vp-col-item">
          <div class="vp-col-bar-wrap">
            <span class="vp-col-num"><?= (int)$r['n'] ?></span>
            <div class="vp-col-bar" style="height:<?= ((int)$r['n'] / $maxMonth) * 100 ?>%"></div>
          </div>
          <span class="vp-col-label"><?= e($r['mon']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="vp-grid-2">
  <!-- Patient status -->
  <div class="vp-card">
    <div class="vp-card-head"><h3><?= stat_icon('pulse') ?> Patient status</h3></div>
    <div class="vp-status-breakdown">
      <?php foreach ($byStatus as $r): ?>
        <div class="vp-status-line">
          <?= status_pill($r['status']) ?>
          <div class="vp-status-track">
            <div class="vp-status-fill" data-status="<?= e($r['status']) ?>" style="width:<?= $totPatients ? ((int)$r['n'] / $totPatients) * 100 : 0 ?>%"></div>
          </div>
          <span><?= (int)$r['n'] ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Appointment summary -->
  <div class="vp-card">
    <div class="vp-card-head"><h3><?= stat_icon('cal') ?> Appointment summary</h3></div>
    <div class="vp-appt-summary">
      <div class="vp-summary-item"><span class="vp-summary-num"><?= $apScheduled ?></span><span class="vp-summary-lbl">Scheduled</span></div>
      <div class="vp-summary-item"><span class="vp-summary-num"><?= $apCompleted ?></span><span class="vp-summary-lbl">Completed</span></div>
      <div class="vp-summary-item"><span class="vp-summary-num"><?= $apTotal ?></span><span class="vp-summary-lbl">Total</span></div>
    </div>
    <p class="vp-report-note">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
      Data reflects the current clinic records stored in the database.
    </p>
  </div>
</div>

<?php require 'includes/footer.php'; ?>
