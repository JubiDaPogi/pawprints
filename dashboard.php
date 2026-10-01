<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_login();

$user  = current_user();
$staff = is_staff();

$PAGE = 'dashboard';
$PAGE_TITLE = $staff ? 'Clinic Dashboard' : 'My Pets';

/* ---------- Data queries ---------- */
if ($staff) {
    $totalPatients   = (int)$pdo->query("SELECT COUNT(*) FROM patients WHERE deleted_at IS NULL")->fetchColumn();
    $underTreatment  = (int)$pdo->query("SELECT COUNT(*) FROM patients WHERE status='Under Treatment' AND deleted_at IS NULL")->fetchColumn();
    $scheduledCount  = (int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE status='Scheduled'")->fetchColumn();

    // Vaccines due within 90 days (incl. overdue).
    $vaccDue = $pdo->query("
        SELECT v.*, p.name AS pet_name, p.species, p.breed, p.id AS pid,
               o.first_name AS owner_first, o.middle_name AS owner_middle, o.last_name AS owner_last,
               DATEDIFF(v.next_due, CURDATE()) AS days
        FROM vaccinations v
        JOIN patients p ON p.id = v.patient_id
        JOIN owners o ON o.id = p.owner_id
        WHERE v.next_due IS NOT NULL AND DATEDIFF(v.next_due, CURDATE()) <= 90 AND p.deleted_at IS NULL AND v.deleted_at IS NULL
        ORDER BY days ASC
    ")->fetchAll();

    // Upcoming appointments.
    $upcoming = $pdo->query("
        SELECT a.*, p.name AS pet_name, p.species, p.breed, p.id AS pid,
               o.first_name AS owner_first, o.middle_name AS owner_middle, o.last_name AS owner_last
        FROM appointments a
        JOIN patients p ON p.id = a.patient_id
        JOIN owners o ON o.id = p.owner_id
        WHERE a.status = 'Scheduled' AND p.deleted_at IS NULL
        ORDER BY a.appt_date ASC, a.appt_time ASC
        LIMIT 5
    ")->fetchAll();

    // Recent visits / diagnoses.
    $recent = $pdo->query("
        SELECT vi.*, p.name AS pet_name, p.species, p.breed, p.id AS pid,
               o.first_name AS owner_first, o.middle_name AS owner_middle, o.last_name AS owner_last
        FROM visits vi
        JOIN patients p ON p.id = vi.patient_id
        JOIN owners o ON o.id = p.owner_id
        WHERE p.deleted_at IS NULL AND vi.deleted_at IS NULL
        ORDER BY vi.visit_date DESC, vi.id DESC
        LIMIT 5
    ")->fetchAll();
} else {
    // Owner's own pets.
    $oid = (int)$user['owner_id'];
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE owner_id = ? AND deleted_at IS NULL ORDER BY name");
    $stmt->execute([$oid]);
    $myPets = $stmt->fetchAll();

    // Visit records are deliberately NOT loaded for owners — diagnoses,
    // treatments and clinical notes stay with the clinic, so the data
    // never reaches the page in the first place.

    // Owner's upcoming appointments.
    $stmt = $pdo->prepare("
        SELECT a.*, p.name AS pet_name FROM appointments a
        JOIN patients p ON p.id = a.patient_id
        WHERE p.owner_id = ? AND a.status = 'Scheduled' AND p.deleted_at IS NULL
        ORDER BY a.appt_date ASC
    ");
    $stmt->execute([$oid]);
    $myAppts = $stmt->fetchAll();

    $speciesRows = $pdo->query("SELECT name FROM species WHERE deleted_at IS NULL ORDER BY name")->fetchAll();
}

require 'includes/header.php';
?>

<?php if ($staff): /* ============ STAFF DASHBOARD ============ */ ?>

  <div class="vp-stat-row">
    <?php
      render_stat('paw',    'Total patients',  $totalPatients,  'Active records',   'teal');
      render_stat('pulse',  'Under treatment', $underTreatment, 'Needs monitoring', 'amber');
      render_stat('cal',    'Upcoming visits', $scheduledCount, 'Scheduled ahead',  'pine');
      render_stat('vax',    'Vaccines due',    count($vaccDue),  'Within 90 days',   'rose');
    ?>
  </div>

  <div class="vp-grid-2">
    <!-- Upcoming appointments -->
    <div class="vp-card">
      <div class="vp-card-head"><h3><?= nav_icon('cal') ?> Today &amp; upcoming</h3></div>
      <div class="vp-appt-mini">
        <?php if (!$upcoming): ?>
          <?= empty_row('cal', 'Nothing scheduled yet.') ?>
        <?php else: foreach ($upcoming as $a): $apptToday = is_today($a['appt_date']); ?>
          <a class="vp-appt-row link" href="patient.php?id=<?= (int)$a['pid'] ?>">
            <div class="vp-appt-date"><span class="<?= $apptToday ? 'vp-today-tag' : '' ?>"><?= $apptToday ? 'Today' : fmt_date($a['appt_date']) ?></span><small><?= fmt_time($a['appt_time']) ?></small></div>
            <div class="vp-appt-info"><strong><?= e(format_name_formal($a['owner_first'], $a['owner_middle'], $a['owner_last'])) ?></strong><span><?= e($a['pet_name']) ?> · <?= e($a['species']) ?> · <?= e($a['breed']) ?> · <?= e($a['reason']) ?></span></div>
            <svg class="vp-appt-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
          </a>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <!-- Vaccines due -->
    <div class="vp-card">
      <div class="vp-card-head"><h3><?= icon_vax() ?> Vaccinations coming due</h3></div>
      <div class="vp-appt-mini">
        <?php if (!$vaccDue): ?>
          <?= empty_row('check', 'All patients up to date.') ?>
        <?php else: foreach (array_slice($vaccDue, 0, 5) as $v):
            $d = (int)$v['days'];
            $flag = $d < 0 ? 'over' : ($d < 21 ? 'soon' : '');
        ?>
          <a class="vp-appt-row link" href="patient.php?id=<?= (int)$v['pid'] ?>">
            <div class="vp-due-flag <?= $flag ?>"><?= $d < 0 ? abs($d).'d overdue' : ($d === 0 ? 'Today' : 'in '.$d.'d') ?></div>
            <div class="vp-appt-info"><strong><?= e(format_name_formal($v['owner_first'], $v['owner_middle'], $v['owner_last'])) ?></strong><span><?= e($v['pet_name']) ?> · <?= e($v['species']) ?> · <?= e($v['breed']) ?> · <?= e($v['name']) ?></span></div>
            <span class="vp-due-date"><?= fmt_date($v['next_due']) ?></span>
          </a>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>

  <!-- Recent diagnoses -->
  <div class="vp-card">
    <div class="vp-card-head"><h3><?= icon_clip() ?> Recent diagnoses &amp; visits</h3></div>
    <div class="vp-recent-list">
      <?php if (!$recent): ?>
        <?= empty_row('clip', 'No visits recorded yet.') ?>
      <?php else: foreach ($recent as $vi): $visitToday = is_today($vi['visit_date']); ?>
        <a class="vp-recent-item" href="patient.php?id=<?= (int)$vi['pid'] ?>">
          <div class="vp-recent-avatar"><?= species_icon($vi['species'], 18) ?></div>
          <div class="vp-recent-body">
            <div class="vp-recent-top">
              <strong><?= e(format_name_formal($vi['owner_first'], $vi['owner_middle'], $vi['owner_last'])) ?></strong>
              <span class="vp-recent-breed"><?= e($vi['pet_name']) ?> · <?= e($vi['species']) ?> · <?= e($vi['breed']) ?></span>
              <span class="vp-recent-date<?= $visitToday ? ' vp-today-tag' : '' ?>"><?= $visitToday ? 'Today' : fmt_date($vi['visit_date']) ?></span>
            </div>
            <p class="vp-recent-dx"><b>Dx:</b> <?= e($vi['diagnosis']) ?></p>
          </div>
          <svg class="vp-appt-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
        </a>
      <?php endforeach; endif; ?>
    </div>
  </div>

<?php else: /* ============ OWNER DASHBOARD ============ */ ?>

  <div class="vp-owner-hero">
    <div>
      <p class="vp-owner-hi"><?= greeting() ?>,</p>
      <h2><?= e($user['full_name']) ?></h2>
      <p class="vp-owner-note">
        You have <?= count($myPets) ?> pet<?= count($myPets) !== 1 ? 's' : '' ?> on record.
        Tap any pet to see their details and vaccination record.
      </p>
    </div>
    <div class="vp-owner-hero-icon"><svg width="54" height="54" viewBox="0 0 24 24" fill="currentColor"><circle cx="6" cy="9" r="1.6"/><circle cx="10" cy="6.5" r="1.6"/><circle cx="14" cy="6.5" r="1.6"/><circle cx="18" cy="9" r="1.6"/><path d="M8 15c0-2.5 1.8-4 4-4s4 1.5 4 4c0 1.8-1.6 2.6-4 2.6S8 16.8 8 15z"/></svg></div>
  </div>

  <div class="vp-section-row">
    <h3 class="vp-section-h">Your pets</h3>
    <button type="button" class="vp-btn-primary" data-open-modal="modal-add-pet">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
      Add pet
    </button>
  </div>
  <div class="vp-pet-grid">
    <?php foreach ($myPets as $p):
        // No diagnosis blurb for owners — clinical findings stay with
        // the clinic. Vaccination records are still visible on the chart.
        render_pet_card($p, false, null, null);
    endforeach; ?>
  </div>

  <h3 class="vp-section-h">Upcoming appointments</h3>
  <div class="vp-card vp-appt-mini">
    <?php if (!$myAppts): ?>
      <?= empty_row('cal', 'No upcoming appointments scheduled.') ?>
    <?php else: foreach ($myAppts as $a): ?>
      <div class="vp-appt-row">
        <div class="vp-appt-date"><span><?= fmt_date($a['appt_date']) ?></span><small><?= fmt_time($a['appt_time']) ?></small></div>
        <div class="vp-appt-info"><strong><?= e($a['pet_name']) ?></strong><span><?= e($a['reason']) ?></span></div>
        <?= status_pill($a['status']) ?>
      </div>
    <?php endforeach; endif; ?>
  </div>

  <!-- Add pet modal (owner self-service). Vitals (weight/temp/heart) and
       status aren't collected here — those are clinical fields the clinic
       fills in at the first actual visit, so sensible baseline defaults
       are used instead of asking the owner to guess them. -->
  <div class="vp-modal-overlay" id="modal-add-pet">
    <div class="vp-modal wide">
      <div class="vp-modal-head">
        <div><h3>Add a pet</h3><p>Tell us about your pet — the clinic will fill in the rest at your first visit.</p></div>
        <button type="button" class="vp-modal-x" data-close-modal>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
      </div>
      <form method="post" action="actions/create_pet.php">
        <?= csrf_field() ?>
        <div class="vp-modal-body">
          <?= form_alert('pet-new') ?>
          <div class="vp-form-grid">
            <div class="vp-field"><label>Pet name</label><input name="name" placeholder="e.g. Bruno" required></div>
            <div class="vp-field"><label>Species</label>
              <select name="species">
                <?php $spOpts = $speciesRows ? array_column($speciesRows, 'name') : ['Dog','Cat','Bird','Rabbit']; ?>
                <?php foreach ($spOpts as $s): ?>
                  <option><?= e($s) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="vp-field"><label>Breed</label><input name="breed" placeholder="e.g. Aspin" required></div>
            <div class="vp-field"><label>Sex</label>
              <select name="sex">
                <option>Male</option>
                <option>Female</option>
              </select>
            </div>
            <div class="vp-field"><label>Color / markings</label><input name="color" placeholder="e.g. Brown/White"></div>
            <div class="vp-field"><label>Date of birth <small>(if known)</small></label><input type="date" name="birth"></div>
            <div class="vp-field full"><label>Allergies <small>(if any)</small></label><input name="allergies" placeholder="None known"></div>
          </div>
          <div class="vp-form-actions">
            <button type="button" class="vp-btn-ghost" data-close-modal>Cancel</button>
            <button type="submit" class="vp-btn-primary">Add pet</button>
          </div>
        </div>
      </form>
    </div>
  </div>

<?php endif; ?>

<?php require 'includes/footer.php'; ?>
