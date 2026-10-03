<?php
/* ============================================================
   Archive — restore soft-deleted records.
   Deleting a patient, user account or species only marks the row
   (deleted_at), so nothing is actually destroyed and everything
   here can be put back exactly as it was.
   ============================================================ */
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
// Clinic staff can restore patients and species; deleted USER ACCOUNTS
// are only listed for those with the user-management permission.
require_staff_or_manager();
$canSeeUsers = can_manage_users();
// Permanent deletion is irreversible, so only full admins get it.
$canPurge    = can_manage_users();

$PAGE       = 'archive';
$PAGE_TITLE = 'Archive';

$allowedTabs = $canSeeUsers ? ['patients', 'visits', 'vaccinations', 'users', 'species'] : ['patients', 'visits', 'vaccinations', 'species'];
$tab = $_GET['tab'] ?? 'patients';
if (!in_array($tab, $allowedTabs, true)) $tab = 'patients';

$q    = trim($_GET['q'] ?? '');
$like = "%$q%";

// Deleted patients, with their owner and how much history rides along.
$patSql = "
    SELECT p.*,
           CONCAT_WS(' ', NULLIF(o.first_name,''), NULLIF(o.middle_name,''), NULLIF(o.last_name,'')) AS owner_name,
           o.first_name AS owner_first, o.middle_name AS owner_middle, o.last_name AS owner_last,
           (SELECT COUNT(*) FROM visits v        WHERE v.patient_id = p.id AND v.deleted_at IS NULL) AS n_visits,
           (SELECT COUNT(*) FROM vaccinations vc WHERE vc.patient_id = p.id AND vc.deleted_at IS NULL) AS n_vaccs,
           (SELECT COUNT(*) FROM appointments a  WHERE a.patient_id = p.id) AS n_appts
    FROM patients p JOIN owners o ON o.id = p.owner_id
    WHERE p.deleted_at IS NOT NULL";
$patParams = [];
if ($q !== '') {
    $patSql .= " AND (p.name LIKE ? OR p.breed LIKE ? OR p.species LIKE ? OR CONCAT_WS(' ', NULLIF(o.first_name,''), NULLIF(o.middle_name,''), NULLIF(o.last_name,'')) LIKE ?)";
    array_push($patParams, $like, $like, $like, $like);
}
$patSql .= " ORDER BY p.deleted_at DESC";
$stmt = $pdo->prepare($patSql);
$stmt->execute($patParams);
$delPatients = $stmt->fetchAll();

// Deleted visits — only those individually sent to the Archive from a
// patient chart. Visits that vanished because their whole patient was
// deleted are restored with the patient, so they're not listed here.
$visSql = "
    SELECT v.*, p.name AS patient_name, p.species AS patient_species,
           p.deleted_at AS patient_deleted
    FROM visits v JOIN patients p ON p.id = v.patient_id
    WHERE v.deleted_at IS NOT NULL AND p.deleted_at IS NULL";
$visParams = [];
if ($q !== '') {
    $visSql .= " AND (p.name LIKE ? OR p.species LIKE ? OR v.vet LIKE ? OR v.reason LIKE ? OR v.diagnosis LIKE ?)";
    array_push($visParams, $like, $like, $like, $like, $like);
}
$visSql .= " ORDER BY v.deleted_at DESC";
$stmt = $pdo->prepare($visSql);
$stmt->execute($visParams);
$delVisits = $stmt->fetchAll();

// Deleted vaccinations — only those individually sent to the Archive from
// a patient chart. Vaccinations that vanished because their whole patient
// was deleted are restored with the patient, so they're not listed here.
$vacSql = "
    SELECT vc.*, p.name AS patient_name, p.species AS patient_species,
           p.deleted_at AS patient_deleted
    FROM vaccinations vc JOIN patients p ON p.id = vc.patient_id
    WHERE vc.deleted_at IS NOT NULL AND p.deleted_at IS NULL";
$vacParams = [];
if ($q !== '') {
    $vacSql .= " AND (p.name LIKE ? OR p.species LIKE ? OR vc.name LIKE ? OR vc.vet LIKE ?)";
    array_push($vacParams, $like, $like, $like, $like);
}
$vacSql .= " ORDER BY vc.deleted_at DESC";
$stmt = $pdo->prepare($vacSql);
$stmt->execute($vacParams);
$delVaccinations = $stmt->fetchAll();

if ($canSeeUsers) {
    $usrSql = "SELECT * FROM users WHERE deleted_at IS NOT NULL";
    $usrParams = [];
    if ($q !== '') {
        $usrSql .= " AND (CONCAT_WS(' ', NULLIF(first_name,''), NULLIF(middle_name,''), NULLIF(last_name,'')) LIKE ? OR email LIKE ? OR role LIKE ?)";
        array_push($usrParams, $like, $like, $like);
    }
    $usrSql .= " ORDER BY deleted_at DESC";
    $stmt = $pdo->prepare($usrSql);
    $stmt->execute($usrParams);
    $delUsers = $stmt->fetchAll();
} else {
    $delUsers = [];
}

$spcSql = "
    SELECT s.*,
           (SELECT COUNT(*) FROM patients p WHERE p.species = s.name AND p.deleted_at IS NULL) AS in_use
    FROM species s WHERE s.deleted_at IS NOT NULL";
$spcParams = [];
if ($q !== '') {
    $spcSql .= " AND s.name LIKE ?";
    $spcParams[] = $like;
}
$spcSql .= " ORDER BY s.deleted_at DESC";
$stmt = $pdo->prepare($spcSql);
$stmt->execute($spcParams);
$delSpecies = $stmt->fetchAll();

$counts = [
    'patients'     => count($delPatients),
    'visits'       => count($delVisits),
    'vaccinations' => count($delVaccinations),
    'users'        => count($delUsers),
    'species'      => count($delSpecies),
];

require 'includes/header.php';
?>

<div class="vp-card">
  <div class="vp-card-head">
    <h3>
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/><path d="M12 11v6M9 11v6M15 11v6"/></svg>
      Deleted items
    </h3>
    <span class="vp-hint-text" style="margin:0">Nothing is permanently removed — restore anything below.</span>
  </div>

  <form method="get" class="vp-archive-search" id="archiveFilter" data-search-form data-search-key="archive">
    <div class="vp-search">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
      <input type="text" name="q" id="archiveSearch" data-search-input value="<?= e($q) ?>" placeholder="Search this tab…" autocomplete="off">
    </div>
    <div class="vp-filter-chips" data-label="Show">
      <?php
        $tabLabels = ['patients' => 'Patients', 'visits' => 'Visits', 'vaccinations' => 'Vaccinations', 'species' => 'Species'];
        if ($canSeeUsers) $tabLabels['users'] = 'User accounts';
        foreach ($tabLabels as $k => $label): ?>
        <!-- Carry the search term across tab switches. -->
        <a class="vp-chip <?= $tab === $k ? 'active' : '' ?>" href="archive.php?tab=<?= $k ?><?= $q !== '' ? '&q=' . urlencode($q) : '' ?>">
          <?= $label ?> (<?= $counts[$k] ?>)
        </a>
      <?php endforeach; ?>
    </div>
    <!-- Keeps the current tab when the search box submits. -->
    <input type="hidden" name="tab" value="<?= e($tab) ?>">
  </form>

  <?php /* ---------------- PATIENTS ---------------- */ ?>
  <?php if ($tab === 'patients'): ?>
    <?php if (!$delPatients): ?>
      <?= empty_row('paw', $q !== '' ? 'No deleted patients match your search.' : 'No deleted patient records.') ?>
    <?php else: ?>
      <div class="vp-table-card">
        <table class="vp-table">
          <thead>
            <tr><th>Patient</th><th>Owner</th><th>History kept</th><th>Deleted</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($delPatients as $p): ?>
              <tr>
                <td>
                  <div class="vp-u-cell">
                    <div class="vp-u-avatar"><?= species_icon($p['species'], 16) ?></div>
                    <div class="vp-u-meta">
                      <strong><?= e($p['name']) ?></strong>
                      <span><?= e($p['species']) ?> · <?= e($p['breed']) ?></span>
                    </div>
                  </div>
                </td>
                <td><?= e(format_name_formal($p['owner_first'], $p['owner_middle'], $p['owner_last'])) ?></td>
                <td class="vp-dim">
                  <?= (int)$p['n_visits'] ?> visit<?= (int)$p['n_visits'] === 1 ? '' : 's' ?>,
                  <?= (int)$p['n_vaccs'] ?> vaccine<?= (int)$p['n_vaccs'] === 1 ? '' : 's' ?>,
                  <?= (int)$p['n_appts'] ?> appointment<?= (int)$p['n_appts'] === 1 ? '' : 's' ?>
                </td>
                <td class="vp-dim"><?= time_ago($p['deleted_at']) ?></td>
                <td>
                  <div class="vp-bin-actions">
                    <form method="post" action="actions/restore.php">
                      <?= csrf_field() ?>
                      <input type="hidden" name="type" value="patient">
                      <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                      <button type="submit" class="vp-btn-small">Restore</button>
                    </form>
                    <?php if ($canPurge): ?>
                      <form method="post" action="actions/purge.php"
                            data-confirm="Permanently delete <?= e($p['name']) ?>? This also removes <?= (int)$p['n_visits'] ?> visit(s), <?= (int)$p['n_vaccs'] ?> vaccination(s) and <?= (int)$p['n_appts'] ?> appointment(s). This CANNOT be undone.">
                        <?= csrf_field() ?>
                        <input type="hidden" name="type" value="patient">
                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                        <button type="submit" class="vp-btn-small danger">Delete forever</button>
                      </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <?php /* ---------------- VISITS ---------------- */ ?>
  <?php if ($tab === 'visits'): ?>
    <?php if (!$delVisits): ?>
      <?= empty_row('clip', $q !== '' ? 'No deleted visits match your search.' : 'No deleted visit records.') ?>
    <?php else: ?>
      <div class="vp-table-card">
        <table class="vp-table">
          <thead>
            <tr><th>Patient</th><th>Visit</th><th>Reason &amp; diagnosis</th><th>Deleted</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($delVisits as $v): ?>
              <tr>
                <td>
                  <div class="vp-u-cell">
                    <div class="vp-u-avatar"><?= species_icon($v['patient_species'], 16) ?></div>
                    <div class="vp-u-meta">
                      <strong><?= e($v['patient_name']) ?></strong>
                      <span><?= e($v['vet']) ?></span>
                    </div>
                  </div>
                </td>
                <td class="vp-dim"><?= fmt_date($v['visit_date']) ?></td>
                <td class="vp-dim">
                  <strong style="color:var(--ink)"><?= e($v['reason'] ?: '—') ?></strong>
                  <?php if (!empty($v['diagnosis'])): ?>
                    <br><span><?= e(mb_strimwidth($v['diagnosis'], 0, 80, '…')) ?></span>
                  <?php endif; ?>
                </td>
                <td class="vp-dim"><?= time_ago($v['deleted_at']) ?></td>
                <td>
                  <div class="vp-bin-actions">
                    <form method="post" action="actions/restore.php">
                      <?= csrf_field() ?>
                      <input type="hidden" name="type" value="visit">
                      <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
                      <button type="submit" class="vp-btn-small">Restore</button>
                    </form>
                    <?php if ($canPurge): ?>
                      <form method="post" action="actions/purge.php"
                            data-confirm="Permanently delete <?= e($v['patient_name']) ?>'s visit from <?= e(fmt_date($v['visit_date'])) ?>? This CANNOT be undone.">
                        <?= csrf_field() ?>
                        <input type="hidden" name="type" value="visit">
                        <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
                        <button type="submit" class="vp-btn-small danger">Delete forever</button>
                      </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <?php /* ---------------- VACCINATIONS ---------------- */ ?>
  <?php if ($tab === 'vaccinations'): ?>
    <?php if (!$delVaccinations): ?>
      <?= empty_row('vax', $q !== '' ? 'No deleted vaccinations match your search.' : 'No deleted vaccination records.') ?>
    <?php else: ?>
      <div class="vp-table-card">
        <table class="vp-table">
          <thead>
            <tr><th>Patient</th><th>Vaccine</th><th>Given</th><th>Next due</th><th>Deleted</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($delVaccinations as $v): ?>
              <tr>
                <td>
                  <div class="vp-u-cell">
                    <div class="vp-u-avatar"><?= species_icon($v['patient_species'], 16) ?></div>
                    <div class="vp-u-meta">
                      <strong><?= e($v['patient_name']) ?></strong>
                      <span><?= e($v['vet']) ?></span>
                    </div>
                  </div>
                </td>
                <td class="vp-dim"><?= e($v['name']) ?></td>
                <td class="vp-dim"><?= fmt_date($v['date_given']) ?></td>
                <td class="vp-dim"><?= fmt_date($v['next_due']) ?></td>
                <td class="vp-dim"><?= time_ago($v['deleted_at']) ?></td>
                <td>
                  <div class="vp-bin-actions">
                    <form method="post" action="actions/restore.php">
                      <?= csrf_field() ?>
                      <input type="hidden" name="type" value="vaccination">
                      <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
                      <button type="submit" class="vp-btn-small">Restore</button>
                    </form>
                    <?php if ($canPurge): ?>
                      <form method="post" action="actions/purge.php"
                            data-confirm="Permanently delete <?= e($v['patient_name']) ?>'s <?= e($v['name']) ?> vaccination from <?= e(fmt_date($v['date_given'])) ?>? This CANNOT be undone.">
                        <?= csrf_field() ?>
                        <input type="hidden" name="type" value="vaccination">
                        <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
                        <button type="submit" class="vp-btn-small danger">Delete forever</button>
                      </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <?php /* ---------------- USER ACCOUNTS ---------------- */ ?>
  <?php if ($tab === 'users' && $canSeeUsers): ?>
    <?php if (!$delUsers): ?>
      <?= empty_row('users', $q !== '' ? 'No deleted accounts match your search.' : 'No deleted user accounts.') ?>
    <?php else: ?>
      <div class="vp-table-card">
        <table class="vp-table">
          <thead>
            <tr><th>Account</th><th>Role</th><th>Deleted</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($delUsers as $u): ?>
              <tr>
                <td>
                  <div class="vp-u-cell">
                    <div class="vp-u-avatar"><?= strtoupper(substr($u['first_name'], 0, 1)) ?></div>
                    <div class="vp-u-meta">
                      <strong><?= e(build_full_name($u['first_name'], $u['middle_name'], $u['last_name'])) ?></strong>
                      <span><?= e($u['email']) ?></span>
                    </div>
                  </div>
                </td>
                <td><span class="vp-role-tag <?= e($u['role']) ?>"><?= e(role_label($u['role'], $u['staff_title'] ?? null)) ?></span></td>
                <td class="vp-dim"><?= time_ago($u['deleted_at']) ?></td>
                <td>
                  <div class="vp-bin-actions">
                    <form method="post" action="actions/restore.php">
                      <?= csrf_field() ?>
                      <input type="hidden" name="type" value="user">
                      <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                      <button type="submit" class="vp-btn-small">Restore</button>
                    </form>
                    <?php if ($canPurge): ?>
                      <form method="post" action="actions/purge.php"
                            data-confirm="Permanently delete the account for <?= e($u['email']) ?>? This CANNOT be undone. (Their client record and pets are kept.)">
                        <?= csrf_field() ?>
                        <input type="hidden" name="type" value="user">
                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                        <button type="submit" class="vp-btn-small danger">Delete forever</button>
                      </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <?php /* ---------------- SPECIES ---------------- */ ?>
  <?php if ($tab === 'species'): ?>
    <?php if (!$delSpecies): ?>
      <?= empty_row('paw', $q !== '' ? 'No deleted species match your search.' : 'No deleted species.') ?>
    <?php else: ?>
      <div class="vp-table-card">
        <table class="vp-table">
          <thead><tr><th>Species</th><th>Deleted</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($delSpecies as $sp): ?>
              <tr>
                <td><strong><?= e($sp['name']) ?></strong></td>
                <td class="vp-dim"><?= time_ago($sp['deleted_at']) ?></td>
                <td>
                  <div class="vp-bin-actions">
                    <form method="post" action="actions/restore.php">
                      <?= csrf_field() ?>
                      <input type="hidden" name="type" value="species">
                      <input type="hidden" name="id" value="<?= (int)$sp['id'] ?>">
                      <button type="submit" class="vp-btn-small">Restore</button>
                    </form>
                    <?php if ($canPurge): ?>
                      <form method="post" action="actions/purge.php"
                            data-confirm="Permanently delete the species &quot;<?= e($sp['name']) ?>&quot;? This CANNOT be undone.">
                        <?= csrf_field() ?>
                        <input type="hidden" name="type" value="species">
                        <input type="hidden" name="id" value="<?= (int)$sp['id'] ?>">
                        <button type="submit" class="vp-btn-small danger">Delete forever</button>
                      </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php require 'includes/footer.php'; ?>
