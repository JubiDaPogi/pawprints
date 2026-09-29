<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_login();

$user  = current_user();
$staff = is_staff();

$id = (int)($_GET['id'] ?? 0);

// Fetch the patient + owner.
$stmt = $pdo->prepare("SELECT p.*, CONCAT_WS(' ', NULLIF(o.first_name,''), NULLIF(o.middle_name,''), NULLIF(o.last_name,'')) AS owner_name, o.first_name AS owner_first, o.middle_name AS owner_middle, o.last_name AS owner_last,
                              o.phone AS owner_phone, o.address AS owner_address
                       FROM patients p JOIN owners o ON o.id = p.owner_id
                       WHERE p.id = ? AND p.deleted_at IS NULL");
$stmt->execute([$id]);
$p = $stmt->fetch();

if (!$p) {
    http_response_code(404);
    $PAGE = 'patients'; $PAGE_TITLE = 'Not found';
    require 'includes/header.php';
    echo '<div class="vp-card">' . empty_row('paw', 'That patient record could not be found.') . '</div>';
    require 'includes/footer.php';
    exit;
}

// Access control: owners may only open their own pets.
if (!$staff && (int)$p['owner_id'] !== (int)$user['owner_id']) {
    redirect('dashboard.php');
}

// Visits are clinical records (diagnosis, treatment, notes) and are
// loaded for clinic staff only — an owner's page never receives them.
$visits = [];
if ($staff) {
    $vStmt = $pdo->prepare("SELECT * FROM visits WHERE patient_id = ? AND deleted_at IS NULL ORDER BY visit_date DESC, id DESC");
    $vStmt->execute([$id]);
    $visits = $vStmt->fetchAll();
}

// Vaccination history is shared with owners.
$vaccs = $pdo->prepare("SELECT * FROM vaccinations WHERE patient_id = ? AND deleted_at IS NULL ORDER BY date_given DESC, id DESC");
$vaccs->execute([$id]);
$vaccs = $vaccs->fetchAll();

$PAGE = 'record';
$PAGE_TITLE = 'Patient Chart';
require 'includes/header.php';
?>

<?php
  // Formal, paper-standard medical record — hidden on screen, this is what
  // actually prints when "Print chart" is used.
  render_patient_print_record($p, $vaccs, $visits, [
      'clinic_address' => 'Bantug, Roxas, Isabela',
  ]);
?>

<a class="vp-back" href="<?= $staff ? 'patients.php' : 'dashboard.php' ?>">
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
  Back
</a>

<!-- Chart header -->
<div class="vp-chart-head">
  <div class="vp-chart-id">
    <div class="vp-chart-avatar"><?= species_icon($p['species'], 34) ?></div>
    <div>
      <div class="vp-chart-name-row">
        <h2><?= e($p['name']) ?></h2>
        <?= status_pill($p['status'], true) ?>
      </div>
      <p class="vp-chart-sub">
        <?= e($p['species']) ?> · <?= e($p['breed']) ?> · <?= e($p['sex']) ?> · <?= age_from_birth($p['birth']) ?> · <?= e($p['color']) ?>
      </p>
      <div class="vp-chart-owner">
        <span class="vp-chart-owner-item">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-6 8-6s8 2 8 6"/></svg>
          <?= e(format_name_formal($p['owner_first'], $p['owner_middle'], $p['owner_last'])) ?>
        </span>
        <span class="vp-dot-sep">•</span>
        <span class="vp-chart-owner-item">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3-8.6A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.4-1.2a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/></svg>
          <?= e($p['owner_phone']) ?>
        </span>
      </div>
    </div>
  </div>
  <?php if ($staff): ?>
    <div class="vp-chart-actions">
      <button type="button" class="vp-btn-ghost" onclick="window.print()">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8" rx="1"/></svg>
        Print chart
      </button>
      <button type="button" class="vp-btn-ghost" data-open-modal="modal-edit-<?= $id ?>">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
        Edit
      </button>
    </div>
  <?php endif; ?>
</div>

<!-- Vitals -->
<div class="vp-vitals">
  <?php
    render_vital('weight', 'Weight',    e($p['weight']) . ' kg', 'teal');
    render_vital('temp',   'Temp',      e($p['temp']) . ' °C',   'amber');
    render_vital('heart',  'Heart rate',e($p['heart']) . ' bpm', 'rose');
    render_vital('alert',  'Allergies', e($p['allergies']),      'pine', true);
  ?>
</div>

<!-- Tabs (CSS-only via radio inputs) -->
<div class="vp-rec-tabs" id="recTabs">
  <?php if ($staff): /* Clinical notes are for clinic staff only. */ ?>
  <button class="active" data-tab="visits" onclick="showTab(this,'visits')">
    <?= icon_clip() ?> Visits &amp; Diagnoses <span class="vp-tab-count"><?= count($visits) ?></span>
  </button>
  <?php endif; ?>
  <button <?= $staff ? '' : 'class="active"' ?> data-tab="vacc" onclick="showTab(this,'vacc')">
    <?= icon_vax() ?> Vaccinations <span class="vp-tab-count"><?= count($vaccs) ?></span>
  </button>
  <button data-tab="info" onclick="showTab(this,'info')">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/></svg>
    Details
  </button>
</div>

<!-- Visits panel — staff only: diagnoses, treatments and clinical notes
     are not shown to pet owners. -->
<?php if ($staff): ?>
<div class="vp-tabpanel" data-panel="visits">
  <div class="vp-card">
    <div class="vp-card-head">
      <h3><?= icon_clip() ?> Visit history</h3>
      <?php if ($staff): ?>
        <button type="button" class="vp-btn-small" data-open-modal="modal-visit">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg> Log visit
        </button>
      <?php endif; ?>
    </div>
    <?php if (!$visits): ?>
      <?= empty_row('clip', 'No visits recorded yet.') ?>
    <?php else: ?>
      <div class="vp-timeline">
        <?php foreach ($visits as $v): ?>
          <div class="vp-tl-item">
            <div class="vp-tl-marker"><span></span></div>
            <div class="vp-tl-body">
              <div class="vp-tl-top">
                <span class="vp-tl-date"><?= fmt_date($v['visit_date']) ?></span>
                <span class="vp-tl-vet"><?= e($v['vet']) ?></span>
                <span class="vp-tl-tools">
                  <button type="button" class="vp-tl-tool" title="Edit visit" data-open-modal="modal-visit-edit-<?= $v['id'] ?>">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                  </button>
                  <form method="post" action="actions/delete_visit.php" style="display:inline"
                        data-confirm="Remove <?= e(fmt_date($v['visit_date'])) ?> visit and its diagnosis?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= $v['id'] ?>">
                    <input type="hidden" name="patient_id" value="<?= $id ?>">
                    <button type="submit" class="vp-tl-tool danger" title="Delete visit">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg>
                    </button>
                  </form>
                </span>
              </div>
              <p class="vp-tl-reason"><?= e($v['reason']) ?></p>
              <div class="vp-dx-grid">
                <div class="vp-dx-cell"><label>Diagnosis</label><p><?= e($v['diagnosis']) ?></p></div>
                <div class="vp-dx-cell"><label>Treatment</label><p><?= e($v['treatment']) ?></p></div>
              </div>
              <?php if (!empty($v['notes'])): ?>
                <p class="vp-tl-notes"><b>Notes:</b> <?= e($v['notes']) ?></p>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php endif; /* end staff-only visits panel */ ?>

<!-- Vaccinations panel — visible to owners too. -->
<div class="vp-tabpanel" data-panel="vacc"<?= $staff ? ' style="display:none"' : '' ?>>
  <div class="vp-card">
    <div class="vp-card-head">
      <h3><?= icon_vax() ?> Vaccination record</h3>
      <?php if ($staff): ?>
        <button type="button" class="vp-btn-small" data-open-modal="modal-vaccine">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg> Add vaccine
        </button>
      <?php endif; ?>
    </div>
    <?php if (!$vaccs): ?>
      <?= empty_row('vax', 'No vaccinations on record.') ?>
    <?php else: ?>
      <div class="vp-vac-table">
        <div class="vp-vac-row head"><span>Vaccine</span><span>Given</span><span>Next due</span><span>Administered by</span></div>
        <?php foreach ($vaccs as $v):
          $d = days_until($v['next_due']);
          $cls = $d === null ? '' : ($d < 0 ? 'over' : ($d < 30 ? 'soon' : ''));
        ?>
          <div class="vp-vac-row">
            <span class="vp-vac-name">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 2 4 4M17 3l3.5 3.5M13 7l4 4M11 9l-7 7v4h4l7-7"/></svg>
              <?= e($v['name']) ?>
            </span>
            <span><?= fmt_date($v['date_given']) ?></span>
            <span class="vp-vac-next <?= $cls ?>"><?= fmt_date($v['next_due']) ?></span>
            <span class="vp-vac-vet">
              <span class="vp-vac-vet-name"><?= e($v['vet']) ?></span>
              <?php if ($staff): ?>
              <span class="vp-vac-tools">
                <button type="button" class="vp-tl-tool" title="Edit vaccination" data-open-modal="modal-vaccine-edit-<?= $v['id'] ?>">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                </button>
                <form method="post" action="actions/delete_vaccine.php" style="display:inline"
                      data-confirm="Remove the <?= e($v['name']) ?> vaccination from <?= e(fmt_date($v['date_given'])) ?>?">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= $v['id'] ?>">
                  <input type="hidden" name="patient_id" value="<?= $id ?>">
                  <button type="submit" class="vp-tl-tool danger" title="Delete vaccination">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg>
                  </button>
                </form>
              </span>
              <?php endif; ?>
            </span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Details panel -->
<div class="vp-tabpanel" data-panel="info" style="display:none">
  <div class="vp-card">
    <div class="vp-card-head"><h3>
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
      Patient details</h3></div>
    <div class="vp-detail-grid">
      <?php
        render_detail('Full name', $p['name']);
        render_detail('Species', $p['species']);
        render_detail('Breed', $p['breed']);
        render_detail('Sex', $p['sex']);
        render_detail('Color / markings', $p['color']);
        render_detail('Date of birth', fmt_date($p['birth']));
        render_detail('Age', age_from_birth($p['birth']));
        render_detail('Status', $p['status']);
        render_detail('Allergies', $p['allergies']);
        render_detail('Owner', format_name_formal($p['owner_first'], $p['owner_middle'], $p['owner_last']));
        render_detail('Contact', $p['owner_phone']);
        render_detail('Address', $p['owner_address']);
      ?>
    </div>
  </div>
</div>

<?php if ($staff): ?>
  <!-- Edit patient modal -->
  <?php
    $owners = $pdo->query("SELECT * FROM owners ORDER BY last_name, first_name")->fetchAll();
    $speciesNames = $pdo->query("SELECT name FROM species WHERE deleted_at IS NULL ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    render_patient_modal('modal-edit-' . $id, $p, $owners, $speciesNames);
  ?>

  <!-- Log visit modal -->
  <div class="vp-modal-overlay" id="modal-visit">
    <div class="vp-modal wide">
      <div class="vp-modal-head">
        <div><h3>Log visit &amp; diagnosis</h3><p>Record the reason for visit, diagnosis, and treatment given.</p></div>
        <button type="button" class="vp-modal-x" data-close-modal><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
      </div>
      <form method="post" action="actions/add_visit.php">
        <?= csrf_field() ?>
        <input type="hidden" name="patient_id" value="<?= $id ?>">
        <div class="vp-modal-body">
          <div class="vp-form-grid">
            <div class="vp-field"><label>Visit date</label><input type="date" name="visit_date" value="<?= date('Y-m-d') ?>"></div>
            <div class="vp-field"><label>Attending vet</label><input name="vet" value="<?= e(default_attending_vet($user)) ?>" placeholder="e.g. Dr. Santos"></div>
            <div class="vp-field full"><label>Reason for visit</label><input name="reason" placeholder="e.g. Vomiting, loss of appetite" required></div>
            <div class="vp-field full"><label>Diagnosis</label><textarea name="diagnosis" placeholder="Clinical findings / diagnosis" required></textarea></div>
            <div class="vp-field full"><label>Treatment / medication</label><textarea name="treatment" placeholder="Prescribed treatment and procedures"></textarea></div>
            <div class="vp-field full"><label>Notes</label><textarea name="notes" placeholder="Follow-up instructions, observations…"></textarea></div>
          </div>
          <div class="vp-form-actions">
            <button type="button" class="vp-btn-ghost" data-close-modal>Cancel</button>
            <button type="submit" class="vp-btn-primary">Save visit</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Per-visit edit modal — kept at page root (outside the animated
       .vp-tabpanel) so the fixed overlay covers the viewport and
       centres, matching the Log visit modal above. Deleting a visit
       is confirmed inline via the standard "Please confirm" dialog
       instead of a dedicated modal. -->
  <?php foreach ($visits as $v): ?>
    <!-- Edit visit modal -->
    <div class="vp-modal-overlay" id="modal-visit-edit-<?= $v['id'] ?>">
      <div class="vp-modal wide">
        <div class="vp-modal-head">
          <div><h3>Edit visit &amp; diagnosis</h3><p>Update the reason for visit, diagnosis, and treatment given.</p></div>
          <button type="button" class="vp-modal-x" data-close-modal><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
        </div>
        <form method="post" action="actions/update_visit.php">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= $v['id'] ?>">
          <input type="hidden" name="patient_id" value="<?= $id ?>">
          <div class="vp-modal-body">
            <div class="vp-form-grid">
              <div class="vp-field"><label>Visit date</label><input type="date" name="visit_date" value="<?= e($v['visit_date']) ?>"></div>
              <div class="vp-field"><label>Attending vet</label><input name="vet" value="<?= e($v['vet']) ?>"></div>
              <div class="vp-field full"><label>Reason for visit</label><input name="reason" value="<?= e($v['reason']) ?>" required></div>
              <div class="vp-field full"><label>Diagnosis</label><textarea name="diagnosis" required><?= e($v['diagnosis']) ?></textarea></div>
              <div class="vp-field full"><label>Treatment / medication</label><textarea name="treatment"><?= e($v['treatment']) ?></textarea></div>
              <div class="vp-field full"><label>Notes</label><textarea name="notes"><?= e($v['notes']) ?></textarea></div>
            </div>
            <div class="vp-form-actions">
              <button type="button" class="vp-btn-ghost" data-close-modal>Cancel</button>
              <button type="submit" class="vp-btn-primary">Save changes</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  <?php endforeach; ?>

  <!-- Add vaccine modal -->
  <div class="vp-modal-overlay" id="modal-vaccine">
    <div class="vp-modal">
      <div class="vp-modal-head">
        <div><h3>Add vaccination</h3><p>Record a vaccine and its next-due date.</p></div>
        <button type="button" class="vp-modal-x" data-close-modal><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
      </div>
      <form method="post" action="actions/add_vaccine.php">
        <?= csrf_field() ?>
        <input type="hidden" name="patient_id" value="<?= $id ?>">
        <div class="vp-modal-body">
          <div class="vp-form-grid">
            <div class="vp-field full"><label>Vaccine</label>
              <input name="name" placeholder="e.g. Anti-Rabies, 5-in-1 (DHPPiL), Bordetella" required>
            </div>
            <div class="vp-field"><label>Date given</label><input type="date" name="date_given" value="<?= date('Y-m-d') ?>"></div>
            <div class="vp-field"><label>Next due</label><input type="date" name="next_due" value="<?= date('Y-m-d', strtotime('+1 year')) ?>"></div>
            <div class="vp-field full"><label>Administered by</label><input name="vet" value="<?= e(default_attending_vet($user)) ?>" placeholder="e.g. Dr. Santos"></div>
          </div>
          <div class="vp-form-actions">
            <button type="button" class="vp-btn-ghost" data-close-modal>Cancel</button>
            <button type="submit" class="vp-btn-primary">Save vaccine</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Per-vaccine edit modal — kept at page root, same reasoning as the
       per-visit edit modals above. Deleting a vaccine is confirmed inline
       via the standard "Please confirm" dialog instead of a dedicated
       modal. -->
  <?php foreach ($vaccs as $v): ?>
    <!-- Edit vaccine modal -->
    <div class="vp-modal-overlay" id="modal-vaccine-edit-<?= $v['id'] ?>">
      <div class="vp-modal">
        <div class="vp-modal-head">
          <div><h3>Edit vaccination</h3><p>Update this vaccine record and its next-due date.</p></div>
          <button type="button" class="vp-modal-x" data-close-modal><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
        </div>
        <form method="post" action="actions/update_vaccine.php">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= $v['id'] ?>">
          <input type="hidden" name="patient_id" value="<?= $id ?>">
          <div class="vp-modal-body">
            <div class="vp-form-grid">
              <div class="vp-field full"><label>Vaccine</label>
                <input name="name" value="<?= e($v['name']) ?>" placeholder="e.g. Anti-Rabies, 5-in-1 (DHPPiL), Bordetella" required>
              </div>
              <div class="vp-field"><label>Date given</label><input type="date" name="date_given" value="<?= e($v['date_given']) ?>"></div>
              <div class="vp-field"><label>Next due</label><input type="date" name="next_due" value="<?= e($v['next_due']) ?>"></div>
              <div class="vp-field full"><label>Administered by</label><input name="vet" value="<?= e($v['vet']) ?>" placeholder="e.g. Dr. Santos"></div>
            </div>
            <div class="vp-form-actions">
              <button type="button" class="vp-btn-ghost" data-close-modal>Cancel</button>
              <button type="submit" class="vp-btn-primary">Save changes</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<script>
  function showTab(btn, name) {
    document.querySelectorAll('#recTabs button').forEach(function (b) { b.classList.remove('active'); });
    btn.classList.add('active');
    document.querySelectorAll('.vp-tabpanel').forEach(function (p) {
      p.style.display = p.getAttribute('data-panel') === name ? '' : 'none';
    });
    if (history.replaceState) {
      history.replaceState(null, '', '#' + name);
    }
  }

  // Open the tab named in the URL hash (e.g. after adding/updating/deleting a
  // vaccination, which sends us back to #vacc) so the change is shown under
  // Vaccinations instead of the default Visits tab.
  (function () {
    var name = (location.hash || '').replace('#', '');
    if (!name) return;
    var btn = document.querySelector('#recTabs button[data-tab="' + name + '"]');
    if (btn) showTab(btn, name);
  })();

  // On phones #recTabs scrolls horizontally, so whichever tab is active
  // (the default first tab, or one opened via the hash above) needs to
  // actually be in view — otherwise the row can land scrolled past it,
  // with no highlighted tab visible at all.
  (function () {
    var active = document.querySelector('#recTabs button.active');
    if (active) active.scrollIntoView({ inline: 'nearest', block: 'nearest' });
  })();
</script>

<?php require 'includes/footer.php'; ?>
