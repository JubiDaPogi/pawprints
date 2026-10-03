<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_staff();   // staff-only page

$PAGE = 'patients';
$PAGE_TITLE = 'Patient Records';

// Filters from the query string.
$q       = trim($_GET['q'] ?? '');
$species = $_GET['species'] ?? 'All';
// Not a visible chip (species already uses that slot) — this one exists
// so the dashboard's "Under treatment" stat card can deep-link here.
$status  = $_GET['status'] ?? 'All';

// Build the query safely.
$sql = "SELECT p.*, CONCAT_WS(' ', NULLIF(o.first_name,''), NULLIF(o.middle_name,''), NULLIF(o.last_name,'')) AS owner_name, o.first_name AS owner_first, o.middle_name AS owner_middle, o.last_name AS owner_last,
               o.id AS oid, o.phone AS owner_phone, o.email AS owner_email, o.address AS owner_address
        FROM patients p JOIN owners o ON o.id = p.owner_id
        WHERE p.deleted_at IS NULL";
$params = [];

if ($q !== '') {
    $sql .= " AND (p.name LIKE ? OR p.breed LIKE ? OR p.species LIKE ? OR CONCAT_WS(' ', NULLIF(o.first_name,''), NULLIF(o.middle_name,''), NULLIF(o.last_name,'')) LIKE ?)";
    $like = "%$q%";
    array_push($params, $like, $like, $like, $like);
}
if ($species !== 'All') {
    $sql .= " AND p.species = ?";
    $params[] = $species;
}
if ($status !== 'All') {
    $sql .= " AND p.status = ?";
    $params[] = $status;
}
$sql .= " ORDER BY o.last_name, o.first_name, o.id, p.name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$patients = $stmt->fetchAll();

// Group the flat rows by owner so each client appears ONCE, with all
// of their pets underneath. Keyed by owner id, so two clients with the
// same name stay separate records.
$byOwner = [];
foreach ($patients as $row) {
    $oid = (int)$row['oid'];
    if (!isset($byOwner[$oid])) {
        $byOwner[$oid] = [
            'id'      => $oid,
            'name'    => format_name_formal($row['owner_first'], $row['owner_middle'], $row['owner_last']),
            'phone'   => $row['owner_phone'],
            'email'   => $row['owner_email'],
            'address' => $row['owner_address'],
            'pets'    => [],
        ];
    }
    $byOwner[$oid]['pets'][] = $row;
}

// If two clients share a display name, show a client reference on each
// so staff can tell the records apart at a glance.
$nameCounts = [];
foreach ($byOwner as $ow) {
    $nameCounts[$ow['name']] = ($nameCounts[$ow['name']] ?? 0) + 1;
}
foreach ($byOwner as $oid => $ow) {
    $byOwner[$oid]['ambiguous'] = ($nameCounts[$ow['name']] ?? 0) > 1;
}

// Latest visit per listed patient (for the card blurb).
$latestVisit = [];
if ($patients) {
    $ids = implode(',', array_map(fn($p) => (int)$p['id'], $patients));
    $rows = $pdo->query("
        SELECT v1.* FROM visits v1
        JOIN (SELECT patient_id, MAX(visit_date) AS md FROM visits
              WHERE patient_id IN ($ids) AND deleted_at IS NULL GROUP BY patient_id) v2
          ON v1.patient_id = v2.patient_id AND v1.visit_date = v2.md
        WHERE v1.deleted_at IS NULL
    ")->fetchAll();
    foreach ($rows as $r) $latestVisit[$r['patient_id']] = $r;
}

// All owners for the add/edit dropdowns.
$owners = $pdo->query("SELECT * FROM owners ORDER BY last_name, first_name")->fetchAll();

// Species are managed by staff in the database, not hardcoded.
$speciesRows = $pdo->query("SELECT * FROM species WHERE deleted_at IS NULL ORDER BY name")->fetchAll();
$speciesList = array_merge(['All'], array_column($speciesRows, 'name'));

require 'includes/header.php';
?>

<!-- Toolbar -->
<form method="get" class="vp-toolbar" id="filterForm">
  <div class="vp-search">
    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
    <input type="text" name="q" id="patientSearch" value="<?= e($q) ?>" placeholder="Search by pet, breed, or owner…" autocomplete="off">
  </div>
  <!-- Keeps the active species/status filters when the search box submits. -->
  <input type="hidden" name="species" value="<?= e($species) ?>">
  <input type="hidden" name="status" value="<?= e($status) ?>">
  <div class="vp-filter-chips" data-label="Species">
    <?php foreach ($speciesList as $s): ?>
      <button type="submit" name="species" value="<?= $s ?>" class="vp-chip <?= $species === $s ? 'active' : '' ?>"><?= $s ?></button>
    <?php endforeach; ?>
  </div>
  <button type="button" class="vp-btn-ghost" data-open-modal="modal-species">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
    Species
  </button>
  <button type="button" class="vp-btn-primary" data-open-modal="modal-new">
    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
    New patient
  </button>
</form>

<?php if ($status !== 'All'): ?>
  <div class="vp-active-filter">
    Showing <strong><?= e($status) ?></strong> patients only
    <a href="patients.php?species=<?= urlencode($species) ?><?= $q !== '' ? '&q=' . urlencode($q) : '' ?>">Clear filter ✕</a>
  </div>
<?php endif; ?>

<?php if (!$patients): ?>
  <div class="vp-card"><?= empty_row('paw', 'No patients match your search.') ?></div>
<?php else: ?>
  <!-- One block per owner: the client is the record, their pets sit
       inside it. Blocks are keyed by owner id, so two clients with the
       same name never merge. -->
  <?php foreach ($byOwner as $ow): ?>
    <section class="vp-owner-block">
      <header class="vp-owner-head">
        <div class="vp-owner-id">
          <div class="vp-owner-avatar">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-6 8-6s8 2 8 6"/></svg>
          </div>
          <div class="vp-owner-meta">
            <h3>
              <?= e($ow['name']) ?>
              <?php if ($ow['ambiguous']): ?>
                <span class="vp-owner-ref" title="Another client has the same name">Client #<?= (int)$ow['id'] ?></span>
              <?php endif; ?>
            </h3>
            <p>
              <?php
                $bits = array_filter([$ow['phone'], $ow['email'], $ow['address']]);
                echo $bits ? e(implode(' · ', $bits)) : '<span class="vp-dim">No contact details on file</span>';
              ?>
            </p>
          </div>
        </div>
        <span class="vp-owner-count">
          <?= count($ow['pets']) ?> pet<?= count($ow['pets']) !== 1 ? 's' : '' ?>
        </span>
      </header>

      <div class="vp-pet-grid">
        <?php foreach ($ow['pets'] as $p):
            $lv = $latestVisit[$p['id']] ?? null;
            // Owner is already the block header, so the card shows the pet.
            render_pet_card($p, false, null, $lv, true);
        endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
<?php endif; ?>

<!-- New-patient modal -->
<?php render_patient_modal('modal-new', null, $owners, array_column($speciesRows, 'name')); ?>

<!-- Edit modals (one per patient) -->
<?php foreach ($patients as $p): ?>
  <?php render_patient_modal('modal-edit-' . (int)$p['id'], $p, $owners, array_column($speciesRows, 'name')); ?>
<?php endforeach; ?>

<script>
(function () {
  var form  = document.getElementById('filterForm');
  var input = document.getElementById('patientSearch');
  if (!form || !input) return;

  // Put the caret back where it was before the reload, so typing
  // continues naturally instead of the field losing focus.
  try {
    var saved = sessionStorage.getItem('pp_search_caret');
    if (saved !== null) {
      sessionStorage.removeItem('pp_search_caret');
      input.focus();
      var at = Math.min(parseInt(saved, 10) || 0, input.value.length);
      input.setSelectionRange(at, at);
    }
  } catch (e) {}

  // Wait for a pause in typing instead of submitting on every keystroke.
  var timer = null;
  var DELAY = 450;

  function submitSoon() {
    clearTimeout(timer);
    timer = setTimeout(function () {
      try { sessionStorage.setItem('pp_search_caret', input.selectionStart); } catch (e) {}
      form.submit();
    }, DELAY);
  }

  input.addEventListener('input', submitSoon);

  // Enter searches immediately rather than waiting out the delay.
  input.addEventListener('keydown', function (ev) {
    if (ev.key === 'Enter') {
      ev.preventDefault();
      clearTimeout(timer);
      try { sessionStorage.setItem('pp_search_caret', input.selectionStart); } catch (e) {}
      form.submit();
    }
  });

  // A chip click should win over a pending search submit.
  form.querySelectorAll('.vp-chip').forEach(function (chip) {
    chip.addEventListener('click', function () { clearTimeout(timer); });
  });
})();
</script>

<!-- Manage species modal -->
<div class="vp-modal-overlay" id="modal-species">
  <div class="vp-modal">
    <div class="vp-modal-head">
      <div>
        <h3>Species</h3>
        <p>These are the species you can choose when adding a patient.</p>
      </div>
    </div>
    <div class="vp-modal-body">
      <?= form_alert('species') ?>
      <form method="post" action="actions/create_species.php" class="vp-species-add">
        <?= csrf_field() ?>
        <input name="name" placeholder="e.g. Guinea Pig" maxlength="40" required>
        <button type="submit" class="vp-btn-primary">Add</button>
      </form>

      <ul class="vp-species-list">
        <?php foreach ($speciesRows as $sp):
              $used = 0;
              foreach ($patients as $pp) { if ($pp['species'] === $sp['name']) $used++; }
        ?>
          <li>
            <span class="vp-species-name"><?= e($sp['name']) ?></span>
            <form method="post" action="actions/delete_species.php" data-confirm="Remove &quot;<?= e($sp['name']) ?>&quot; from the species list?">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int)$sp['id'] ?>">
              <button type="submit" class="vp-btn-tiny danger" title="Remove">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg>
              </button>
            </form>
          </li>
        <?php endforeach; ?>
      </ul>
      <p class="vp-hint-text">A species that patients are still using can't be removed.</p>

      <div class="vp-form-actions">
        <button type="button" class="vp-btn-ghost" data-close-modal>Done</button>
      </div>
    </div>
  </div>
</div>

<?php require 'includes/footer.php'; ?>
