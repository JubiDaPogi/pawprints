<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_staff();

$PAGE = 'reports';
$PAGE_TITLE = 'Reports & Analytics';

/* ------------------------------------------------------------------
   Period: all time, or one day / week / month / year. ?period=…&d=Y-m-d
   picks it; d is any date inside the period (default today), and the
   arrows step to the previous / next one.
------------------------------------------------------------------ */
$periods = ['all' => 'All time', 'daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'yearly' => 'Yearly'];
$period  = $_GET['period'] ?? 'all';
if (!isset($periods[$period])) $period = 'all';

// The "Go to" box sends a month (m=YYYY-MM) or a year (y=YYYY) for those periods.
if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $_GET['m'] ?? '')) $_GET['d'] = $_GET['m'] . '-01';
elseif (preg_match('/^\d{4}$/', $_GET['y'] ?? '') && (int)$_GET['y'] >= 1970) $_GET['d'] = $_GET['y'] . '-01-01';
$anchor = DateTime::createFromFormat('!Y-m-d', $_GET['d'] ?? '');
if (!$anchor || $anchor->format('Y-m-d') !== ($_GET['d'] ?? '')) $anchor = new DateTime('today');
$todayStr = date('Y-m-d');

$from = $to = $prevD = $nextD = null;
$rangeLabel = 'All time';
switch ($period) {
    case 'daily':
        $from = $to = $anchor->format('Y-m-d');
        $prevD = (clone $anchor)->modify('-1 day')->format('Y-m-d');
        $nextD = (clone $anchor)->modify('+1 day')->format('Y-m-d');
        $rangeLabel = $anchor->format('D, M j, Y');
        break;
    case 'weekly':
        $mon = (clone $anchor)->modify('monday this week');
        $sun = (clone $mon)->modify('+6 days');
        $from = $mon->format('Y-m-d'); $to = $sun->format('Y-m-d');
        $prevD = (clone $mon)->modify('-7 days')->format('Y-m-d');
        $nextD = (clone $mon)->modify('+7 days')->format('Y-m-d');
        $rangeLabel = $mon->format('M j') . ' – ' . $sun->format('M j, Y');
        break;
    case 'monthly':
        $first = new DateTime($anchor->format('Y-m-01'));
        $from = $first->format('Y-m-d'); $to = $first->format('Y-m-t');
        $prevD = (clone $first)->modify('-1 month')->format('Y-m-d');
        $nextD = (clone $first)->modify('+1 month')->format('Y-m-d');
        $rangeLabel = $first->format('F Y');
        break;
    case 'yearly':
        $first = new DateTime($anchor->format('Y') . '-01-01');
        $from = $first->format('Y-m-d'); $to = $first->format('Y-12-31');
        $prevD = (clone $first)->modify('-1 year')->format('Y-m-d');
        $nextD = (clone $first)->modify('+1 year')->format('Y-m-d');
        $rangeLabel = $first->format('Y');
        break;
}
$scoped = $from !== null;
$canNext = $scoped && $to < $todayStr;           // nothing to report beyond today
$isCurrent = $scoped && $from <= $todayStr && $to >= $todayStr;

/** Link to a period / anchor. */
function report_url($period, $d = null) {
    return 'reports.php?period=' . $period . ($d ? '&d=' . $d : '');
}

/* ------------------------------------------------------------------
   Numbers
------------------------------------------------------------------ */
if (!$scoped) {
    $totPatients = (int)$pdo->query("SELECT COUNT(*) FROM patients WHERE deleted_at IS NULL")->fetchColumn();
    $totOwners   = (int)$pdo->query("SELECT COUNT(*) FROM owners")->fetchColumn();
    $totVisits   = (int)$pdo->query("SELECT COUNT(*) FROM visits WHERE deleted_at IS NULL")->fetchColumn();
    $totVacc     = (int)$pdo->query("SELECT COUNT(*) FROM vaccinations WHERE deleted_at IS NULL")->fetchColumn();

    $bySpecies = $pdo->query("SELECT species, COUNT(*) AS n FROM patients WHERE deleted_at IS NULL GROUP BY species ORDER BY n DESC")->fetchAll();
    $byStatus  = $pdo->query("SELECT status, COUNT(*) AS n FROM patients WHERE deleted_at IS NULL GROUP BY status")->fetchAll();
    $statusBase = $totPatients;
    $apRows = $pdo->query("SELECT status, COUNT(*) n FROM appointments GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);

    // Visits by month (every month present in the data).
    $series = [];
    foreach ($pdo->query("SELECT DATE_FORMAT(visit_date, '%b') AS mon, DATE_FORMAT(visit_date, '%Y-%m') AS ym, COUNT(*) AS n
                          FROM visits WHERE deleted_at IS NULL GROUP BY ym, mon ORDER BY ym ASC") as $r) {
        $series[] = ['label' => $r['mon'], 'n' => (int)$r['n']];
    }
    $chartTitle = 'Visits by month';
} else {
    $one = function ($sql, $params = []) use ($pdo) { $st = $pdo->prepare($sql); $st->execute($params); return (int)$st->fetchColumn(); };
    $rng = [$from, $to];
    $seenWhere = "v.deleted_at IS NULL AND p.deleted_at IS NULL AND v.visit_date BETWEEN ? AND ?";

    $totPatients = $one("SELECT COUNT(DISTINCT v.patient_id) FROM visits v JOIN patients p ON p.id = v.patient_id WHERE $seenWhere", $rng);
    $totVisits   = $one("SELECT COUNT(*) FROM visits v JOIN patients p ON p.id = v.patient_id WHERE $seenWhere", $rng);
    $totVacc     = $one("SELECT COUNT(*) FROM vaccinations x JOIN patients p ON p.id = x.patient_id
                         WHERE x.deleted_at IS NULL AND p.deleted_at IS NULL AND x.date_given BETWEEN ? AND ?", $rng);
    $totAppts    = $one("SELECT COUNT(*) FROM appointments a JOIN patients p ON p.id = a.patient_id
                         WHERE p.deleted_at IS NULL AND a.appt_date BETWEEN ? AND ?", $rng);

    $st = $pdo->prepare("SELECT p.species, COUNT(DISTINCT p.id) AS n FROM visits v JOIN patients p ON p.id = v.patient_id
                         WHERE $seenWhere GROUP BY p.species ORDER BY n DESC");
    $st->execute($rng); $bySpecies = $st->fetchAll();
    $st = $pdo->prepare("SELECT p.status, COUNT(DISTINCT p.id) AS n FROM visits v JOIN patients p ON p.id = v.patient_id
                         WHERE $seenWhere GROUP BY p.status");
    $st->execute($rng); $byStatus = $st->fetchAll();
    $statusBase = $totPatients;

    $st = $pdo->prepare("SELECT a.status, COUNT(*) n FROM appointments a JOIN patients p ON p.id = a.patient_id
                         WHERE p.deleted_at IS NULL AND a.appt_date BETWEEN ? AND ? GROUP BY a.status");
    $st->execute($rng); $apRows = $st->fetchAll(PDO::FETCH_KEY_PAIR);

    // Chart buckets: daily = the 7 days up to that day, weekly = each day,
    // monthly = each week of the month, yearly = each month.
    $buckets = [];   // [label, from, to]
    if ($period === 'daily') {
        for ($i = 6; $i >= 0; $i--) {
            $d = (clone $anchor)->modify("-$i day")->format('Y-m-d');
            $buckets[] = [date('D j', strtotime($d)), $d, $d];
        }
        $chartTitle = 'Visits — last 7 days';
    } elseif ($period === 'weekly') {
        for ($i = 0; $i < 7; $i++) {
            $d = date('Y-m-d', strtotime("$from +$i day"));
            $buckets[] = [date('D', strtotime($d)), $d, $d];
        }
        $chartTitle = 'Visits by day';
    } elseif ($period === 'monthly') {
        $cur = $from;
        while ($cur <= $to) {
            $wEnd = min($to, date('Y-m-d', strtotime('sunday this week', strtotime($cur))));
            $a = (int)substr($cur, 8); $b = (int)substr($wEnd, 8);
            $buckets[] = [$a === $b ? (string)$a : $a . "–" . $b, $cur, $wEnd];
            $cur = date('Y-m-d', strtotime("$wEnd +1 day"));
        }
        $chartTitle = 'Visits by week';
    } else {
        for ($m = 0; $m < 12; $m++) {
            $ms = date('Y-m-01', strtotime("$from +$m month"));
            $buckets[] = [date('M', strtotime($ms)), $ms, date('Y-m-t', strtotime($ms))];
        }
        $chartTitle = 'Visits by month';
    }
    $dayCounts = [];
    $minD = $buckets[0][1]; $maxD = $buckets[count($buckets) - 1][2];
    $st = $pdo->prepare("SELECT v.visit_date d, COUNT(*) n FROM visits v JOIN patients p ON p.id = v.patient_id
                         WHERE $seenWhere GROUP BY v.visit_date");
    $st->execute([$minD, $maxD]);
    foreach ($st as $r) $dayCounts[$r['d']] = (int)$r['n'];
    $series = [];
    foreach ($buckets as [$label, $a, $b]) {
        $n = 0;
        foreach ($dayCounts as $d => $c) if ($d >= $a && $d <= $b) $n += $c;
        $series[] = ['label' => $label, 'n' => $n];
    }
}

$maxSpecies = max([1, ...array_map(fn($r) => (int)$r['n'], $bySpecies)]);
$maxSeries  = max([1, ...array_map(fn($r) => $r['n'], $series)]);
$ap = fn($k) => (int)($apRows[$k] ?? 0);
$apTotal = array_sum(array_map('intval', $apRows));

/* ---- Printed (formal) report: a visit register for the period ---- */
$regLimit = 200;
$regSql = "SELECT v.visit_date, v.reason, v.diagnosis, v.vet, p.name AS pet, p.species,
                  CONCAT_WS(', ', NULLIF(o.last_name,''), NULLIF(o.first_name,'')) AS owner
           FROM visits v JOIN patients p ON p.id = v.patient_id JOIN owners o ON o.id = p.owner_id
           WHERE v.deleted_at IS NULL AND p.deleted_at IS NULL"
        . ($scoped ? " AND v.visit_date BETWEEN ? AND ?" : "")
        . " ORDER BY v.visit_date ASC, v.id ASC LIMIT " . ($regLimit + 1);
$st = $pdo->prepare($regSql);
$st->execute($scoped ? [$from, $to] : []);
$register = $st->fetchAll();
$regMore  = count($register) > $regLimit;
if ($regMore) array_pop($register);

$reportNo   = 'RPT-' . date('Ymd-His');
$issuedAt   = date('F j, Y \a\t g:i A');
$me = current_user();
$preparedBy = trim(($me['first_name'] ?? '') . ' ' . ($me['last_name'] ?? ''));
$periodText = $scoped
    ? ($period === 'weekly' ? 'Week of ' . $rangeLabel : $rangeLabel) . ' (' . fmt_date($from) . ($from !== $to ? ' – ' . fmt_date($to) : '') . ')'
    : 'All records to date';

require 'includes/header.php';
?>

<!-- Period: dropdown (All time / Daily / Weekly / Monthly / Quarterly) and, for a
     period, arrows to step back and forward. -->
<div class="vp-toolbar vp-report-bar no-print">
  <div class="vp-filter-chips" data-label="Report">
    <?php foreach ($periods as $k => $label): ?>
      <a class="vp-chip <?= $period === $k ? 'active' : '' ?>" href="<?= e(report_url($k)) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </div>
  <?php if ($scoped): ?>
    <!-- The period is one field that shows exactly what the report covers
         ("Sep 28 – Oct 4, 2026", "October 2026"…). Clicking it opens a calendar;
         pick any day / month and the report jumps to the period containing it. -->
    <form method="get" class="vp-report-jump">
      <input type="hidden" name="period" value="<?= e($period) ?>">
      <?php if ($period === 'yearly'): ?>
        <span class="vp-report-pick-ico"><?= stat_icon('cal') ?></span>
        <input type="number" name="y" aria-label="Year" min="1970" max="2100" step="1" value="<?= e($anchor->format('Y')) ?>" onchange="if(this.value.length===4)this.form.submit()">
      <?php else: ?>
        <?php $isMonth = $period === 'monthly'; ?>
        <button type="button" class="vp-report-pick" onclick="var i=this.nextElementSibling;try{i.showPicker()}catch(e){i.focus();i.click()}">
          <?= stat_icon('cal') ?>
          <span><?= e($period === 'weekly' ? 'Week of ' . $rangeLabel : $rangeLabel) ?></span>
          <svg class="vp-ss-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
        </button>
        <input class="vp-report-native" tabindex="-1" aria-hidden="true"
               type="<?= $isMonth ? 'month' : 'date' ?>" name="<?= $isMonth ? 'm' : 'd' ?>"
               value="<?= e($anchor->format($isMonth ? 'Y-m' : 'Y-m-d')) ?>" onchange="if(this.value)this.form.submit()">
      <?php endif; ?>
    </form>
  <?php endif; ?>
  <button type="button" class="vp-btn-ghost vp-report-print" onclick="window.print()">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8" rx="1"/></svg>
    Print report
  </button>
</div>

<p class="vp-print-period" hidden>
  <strong><?= e($periods[$period]) ?> report</strong>
  <?= $scoped ? ' — ' . e($period === 'weekly' ? 'Week of ' . $rangeLabel : $rangeLabel) . ' (' . e(fmt_date($from)) . ($from !== $to ? ' to ' . e(fmt_date($to)) : '') . ')' : ' — all records to date' ?>
</p>

<div class="vp-stat-row">
  <?php if (!$scoped): ?>
    <?php
      render_stat('paw',   'Patients',           $totPatients, 'On record',         'teal');
      render_stat('users', 'Owners',             $totOwners,   'Registered clients','pine');
      render_stat('clip',  'Total visits logged',$totVisits,   'All-time',          'amber');
      render_stat('vax',   'Vaccines given',     $totVacc,     'All-time',          'rose');
    ?>
  <?php else: ?>
    <?php
      render_stat('paw',   'Patients seen',      $totPatients, $rangeLabel, 'teal');
      render_stat('clip',  'Visits logged',      $totVisits,   $rangeLabel, 'amber');
      render_stat('vax',   'Vaccines given',     $totVacc,     $rangeLabel, 'rose');
      render_stat('cal',   'Appointments',       $totAppts,    $rangeLabel, 'pine');
    ?>
  <?php endif; ?>
</div>

<div class="vp-grid-2">
  <!-- By species -->
  <div class="vp-card">
    <div class="vp-card-head"><h3><?= stat_icon('paw') ?> <?= $scoped ? 'Patients seen by species' : 'Patients by species' ?></h3></div>
    <?php if (!$bySpecies): ?>
      <?= empty_row('paw', 'No patients were seen in this period.') ?>
    <?php else: ?>
    <div class="vp-bar-list">
      <?php foreach ($bySpecies as $r): ?>
        <div class="vp-bar-item">
          <div class="vp-bar-label"><?= species_icon($r['species'], 15) ?> <?= e($r['species']) ?></div>
          <div class="vp-bar-track"><div class="vp-bar-fill" style="width:<?= ((int)$r['n'] / $maxSpecies) * 100 ?>%"></div></div>
          <span class="vp-bar-val"><?= (int)$r['n'] ?></span>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Visits over time -->
  <div class="vp-card">
    <div class="vp-card-head"><h3><?= stat_icon('chart') ?> <?= e($chartTitle) ?></h3></div>
    <?php if (!$series): ?>
      <?= empty_row('chart', 'No visits logged yet.') ?>
    <?php else: ?>
    <div class="vp-col-chart">
      <?php foreach ($series as $r): ?>
        <div class="vp-col-item">
          <div class="vp-col-bar-wrap">
            <span class="vp-col-num"><?= (int)$r['n'] ?></span>
            <div class="vp-col-bar" style="height:<?= ($r['n'] / $maxSeries) * 100 ?>%"></div>
          </div>
          <span class="vp-col-label"><?= e($r['label']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<div class="vp-grid-2">
  <!-- Patient status -->
  <div class="vp-card">
    <div class="vp-card-head"><h3><?= stat_icon('pulse') ?> <?= $scoped ? 'Status of patients seen' : 'Patient status' ?></h3></div>
    <?php if (!$byStatus): ?>
      <?= empty_row('pulse', 'No patients were seen in this period.') ?>
    <?php else: ?>
    <div class="vp-status-breakdown">
      <?php foreach ($byStatus as $r): ?>
        <div class="vp-status-line">
          <?= status_pill($r['status']) ?>
          <div class="vp-status-track">
            <div class="vp-status-fill" data-status="<?= e($r['status']) ?>" style="width:<?= $statusBase ? ((int)$r['n'] / $statusBase) * 100 : 0 ?>%"></div>
          </div>
          <span><?= (int)$r['n'] ?></span>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Appointment summary -->
  <div class="vp-card">
    <div class="vp-card-head"><h3><?= stat_icon('cal') ?> Appointment summary<?= $scoped ? ' — ' . e($rangeLabel) : '' ?></h3></div>
    <div class="vp-appt-summary vp-appt-summary-5">
      <div class="vp-summary-item"><span class="vp-summary-num"><?= $ap('Pending') ?></span><span class="vp-summary-lbl">Pending</span></div>
      <div class="vp-summary-item"><span class="vp-summary-num"><?= $ap('Scheduled') ?></span><span class="vp-summary-lbl">Scheduled</span></div>
      <div class="vp-summary-item"><span class="vp-summary-num"><?= $ap('Completed') ?></span><span class="vp-summary-lbl">Completed</span></div>
      <div class="vp-summary-item"><span class="vp-summary-num"><?= $ap('Declined') ?></span><span class="vp-summary-lbl">Declined</span></div>
      <div class="vp-summary-item"><span class="vp-summary-num"><?= $ap('Cancelled') ?></span><span class="vp-summary-lbl">Cancelled</span></div>
      <div class="vp-summary-item"><span class="vp-summary-num"><?= $ap('Expired') ?></span><span class="vp-summary-lbl">Expired</span></div>
      <div class="vp-summary-item"><span class="vp-summary-num"><?= $apTotal ?></span><span class="vp-summary-lbl">Total</span></div>
    </div>
    <p class="vp-report-note">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
      <?= $scoped ? 'Figures count visits, vaccines and appointments dated in this period.' : 'Data reflects the current clinic records stored in the database.' ?>
    </p>
  </div>
</div>

<!-- ============================================================
     Formal printed report (hidden on screen; this is what prints).
     Same paper style as the patient medical record.
     ============================================================ -->
<div class="vp-med-record vp-report-doc" aria-hidden="true">
  <header class="vp-mr-letterhead">
    <div class="vp-mr-clinic">
      <div class="vp-mr-logo"><?= species_icon('paw', 30) ?></div>
      <div class="vp-mr-clinic-text">
        <h1><?= e(defined('CLINIC_NAME') ? CLINIC_NAME : 'Paw Prints Veterinary Clinic') ?></h1>
        <p>Bantug, Roxas, Isabela</p>
      </div>
    </div>
    <div class="vp-mr-docmeta">
      <span class="vp-mr-doctitle"><?= e($periods[$period]) ?> Clinic Report</span>
      <span class="vp-mr-recno">Report No. <?= e($reportNo) ?></span>
      <span class="vp-mr-printed">Issued <?= e($issuedAt) ?></span>
    </div>
  </header>

  <section class="vp-mr-section">
    <table class="vp-mr-fields">
      <tr><th>Reporting period</th><td><?= e($periodText) ?></td></tr>
      <tr><th>Prepared by</th><td><?= e($preparedBy ?: '—') ?></td></tr>
    </table>
  </section>

  <section class="vp-mr-section">
    <h2 class="vp-mr-sec">I. Summary</h2>
    <table class="vp-mr-table vp-rd-summary">
      <thead><tr><th>Indicator</th><th class="num">Count</th></tr></thead>
      <tbody>
        <?php if ($scoped): ?>
          <tr><td>Patients seen</td><td class="num"><?= $totPatients ?></td></tr>
          <tr><td>Visits logged</td><td class="num"><?= $totVisits ?></td></tr>
          <tr><td>Vaccines given</td><td class="num"><?= $totVacc ?></td></tr>
          <tr><td>Appointments</td><td class="num"><?= $totAppts ?></td></tr>
        <?php else: ?>
          <tr><td>Patients on record</td><td class="num"><?= $totPatients ?></td></tr>
          <tr><td>Registered owners</td><td class="num"><?= $totOwners ?></td></tr>
          <tr><td>Visits logged</td><td class="num"><?= $totVisits ?></td></tr>
          <tr><td>Vaccines given</td><td class="num"><?= $totVacc ?></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </section>

  <section class="vp-mr-idblock">
    <div class="vp-mr-panel">
      <h2 class="vp-mr-sec">II. <?= $scoped ? 'Patients Seen by Species' : 'Patients by Species' ?></h2>
      <?php if (!$bySpecies): ?><p class="vp-mr-empty">None in this period.</p><?php else: ?>
      <table class="vp-mr-table">
        <thead><tr><th>Species</th><th class="num">Patients</th><th class="num">Share</th></tr></thead>
        <tbody>
          <?php $spTot = array_sum(array_map(fn($r) => (int)$r['n'], $bySpecies)); foreach ($bySpecies as $r): ?>
            <tr><td><?= e($r['species']) ?></td><td class="num"><?= (int)$r['n'] ?></td><td class="num"><?= $spTot ? round((int)$r['n'] / $spTot * 100) : 0 ?>%</td></tr>
          <?php endforeach; ?>
          <tr class="tot"><td>Total</td><td class="num"><?= $spTot ?></td><td class="num">100%</td></tr>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
    <div class="vp-mr-panel">
      <h2 class="vp-mr-sec">III. <?= $scoped ? 'Status of Patients Seen' : 'Patient Status' ?></h2>
      <?php if (!$byStatus): ?><p class="vp-mr-empty">None in this period.</p><?php else: ?>
      <table class="vp-mr-table">
        <thead><tr><th>Status</th><th class="num">Patients</th></tr></thead>
        <tbody>
          <?php foreach ($byStatus as $r): ?>
            <tr><td><?= e($r['status']) ?></td><td class="num"><?= (int)$r['n'] ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </section>

  <section class="vp-mr-idblock">
    <div class="vp-mr-panel">
      <h2 class="vp-mr-sec">IV. <?= e($chartTitle) ?></h2>
      <?php if (!$series): ?><p class="vp-mr-empty">No visits logged.</p><?php else: ?>
      <table class="vp-mr-table">
        <thead><tr><th><?= $period === 'monthly' ? 'Days' : ($period === 'yearly' || !$scoped ? 'Month' : 'Day') ?></th><th class="num">Visits</th></tr></thead>
        <tbody>
          <?php foreach ($series as $r): ?>
            <tr><td><?= e($r['label']) ?></td><td class="num"><?= (int)$r['n'] ?></td></tr>
          <?php endforeach; ?>
          <tr class="tot"><td>Total</td><td class="num"><?= array_sum(array_column($series, 'n')) ?></td></tr>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
    <div class="vp-mr-panel">
      <h2 class="vp-mr-sec">V. Appointments</h2>
      <table class="vp-mr-table">
        <thead><tr><th>Status</th><th class="num">Count</th></tr></thead>
        <tbody>
          <?php foreach (['Pending', 'Scheduled', 'Completed', 'Declined', 'Cancelled', 'Expired'] as $k): ?>
            <tr><td><?= $k ?></td><td class="num"><?= $ap($k) ?></td></tr>
          <?php endforeach; ?>
          <tr class="tot"><td>Total</td><td class="num"><?= $apTotal ?></td></tr>
        </tbody>
      </table>
    </div>
  </section>

  <section class="vp-mr-section">
    <h2 class="vp-mr-sec">VI. Visit Register</h2>
    <?php if (!$register): ?>
      <p class="vp-mr-empty">No visits were logged in this period.</p>
    <?php else: ?>
      <table class="vp-mr-table">
        <thead><tr><th>#</th><th>Date</th><th>Patient</th><th>Owner</th><th>Reason / Diagnosis</th><th>Attending</th></tr></thead>
        <tbody>
          <?php foreach ($register as $i => $v): ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td class="nowrap"><?= e(fmt_date($v['visit_date'])) ?></td>
              <td><?= e($v['pet']) ?> <span class="vp-rd-muted">(<?= e($v['species']) ?>)</span></td>
              <td><?= e($v['owner']) ?></td>
              <td><?= e($v['reason']) ?><?php if (trim((string)$v['diagnosis']) !== ''): ?><br><span class="vp-rd-muted"><?= e($v['diagnosis']) ?></span><?php endif; ?></td>
              <td><?= e($v['vet'] ?: '—') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php if ($regMore): ?><p class="vp-mr-note">Showing the first <?= $regLimit ?> visits — choose a shorter period for the full list.</p><?php endif; ?>
    <?php endif; ?>
  </section>

  <footer class="vp-mr-footer">
    <div class="vp-mr-sign vp-rd-sign">
      <div class="vp-mr-sign-line">
        <span class="vp-mr-sign-name"><?= e($preparedBy) ?></span>
        <span class="vp-mr-sign-label">Prepared by (Signature over Printed Name)</span>
      </div>
      <div class="vp-mr-sign-line">
        <span class="vp-mr-sign-name"></span>
        <span class="vp-mr-sign-label">Noted by — Clinic Head / Veterinarian</span>
      </div>
      <div class="vp-mr-sign-line">
        <span class="vp-mr-sign-name"></span>
        <span class="vp-mr-sign-label">Date</span>
      </div>
    </div>
    <p class="vp-mr-confidential">
      Confidential — for internal use of <?= e(defined('CLINIC_NAME') ? CLINIC_NAME : 'Paw Prints Veterinary Clinic') ?>.
      Figures are drawn from the clinic records at the time of issue. Report No. <?= e($reportNo) ?> &middot; Generated <?= e($issuedAt) ?>.
    </p>
  </footer>
</div>

<?php require 'includes/footer.php'; ?>
