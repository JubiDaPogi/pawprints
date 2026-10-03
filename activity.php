<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_manage_users();

$PAGE = 'activity';
$PAGE_TITLE = 'Activity Log';

// Group filter.
$groups = [
    'All'          => [],
    'Sign-ins'     => ['login', 'logout', 'login_failed', 'login_denied', 'appt_lookup_failed'],
    'Appointments' => ['appt_request', 'appt_approve', 'appt_decline', 'appt_cancel', 'appt_expire', 'appt_create', 'appt_complete', 'reminders_run'],
    'Records'      => ['patient_create', 'patient_update', 'patient_delete', 'patient_restore',
                       'visit_create', 'visit_update', 'visit_delete', 'visit_restore',
                       'vaccine_add', 'vaccine_update', 'vaccine_delete', 'vaccine_restore',
                       'species_create', 'species_delete', 'species_restore', 'purge'],
    'Schedule'     => ['schedule_update'],
    'Accounts'     => ['user_create', 'user_update', 'user_activate', 'user_deactivate', 'user_delete', 'user_restore',
                       'permission_grant', 'permission_revoke', 'owner_create', 'account_seeded'],
    'Security'     => ['profile_update', 'password_change', 'security_update', 'recovery_start', 'recovery_failed',
                       'privacy_consent', 'privacy_withdraw'],
];
$group = $_GET['group'] ?? 'All';
if (!isset($groups[$group])) $group = 'All';

$q = trim($_GET['q'] ?? '');

$sql = "SELECT * FROM activity_log";
$where = [];
$params = [];
if ($group !== 'All' && $groups[$group]) {
    $in = implode(',', array_fill(0, count($groups[$group]), '?'));
    $where[] = "action IN ($in)";
    $params = array_merge($params, $groups[$group]);
}
if ($q !== '') {
    // Search the person, action, details, target, or IP address.
    $where[] = "(actor_username LIKE ? OR action LIKE ? OR details LIKE ? OR target_label LIKE ? OR ip_address LIKE ?)";
    $like = "%$q%";
    array_push($params, $like, $like, $like, $like, $like);
}
if ($where) {
    $sql .= " WHERE " . implode(' AND ', $where);
}
$sql .= " ORDER BY created_at DESC, id DESC LIMIT 200";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$entries = $stmt->fetchAll();

require 'includes/header.php';
?>

<form method="get" class="vp-toolbar" id="auditFilter" data-search-form data-search-key="audit">
  <div class="vp-search">
    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
    <input type="text" name="q" id="auditSearch" data-search-input data-appt-code value="<?= e($q) ?>" placeholder="Search by user, action, details, or IP…" autocomplete="off">
  </div>
  <!-- Keeps the active category when the search box submits. -->
  <input type="hidden" name="group" value="<?= e($group) ?>">
  <div class="vp-filter-chips" data-label="Category">
    <?php foreach (array_keys($groups) as $g): ?>
      <button type="submit" name="group" value="<?= $g ?>" class="vp-chip <?= $group === $g ? 'active' : '' ?>"><?= $g ?></button>
    <?php endforeach; ?>
  </div>
</form>
<div class="vp-audit-intro vp-audit-caption">
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="M9 12h6M9 16h4"/></svg>
  <span>Security trail of account activity.
    <?php if ($q !== ''): ?>Showing <?= count($entries) ?> match<?= count($entries) === 1 ? '' : 'es' ?> for &ldquo;<?= e($q) ?>&rdquo;.<?php else: ?>Showing the latest <?= count($entries) ?> events.<?php endif; ?>
  </span>
</div>

<?php if (!$entries): ?>
  <div class="vp-card"><?= empty_row('clip', $q !== '' ? 'No activity matches your search.' : 'No audit entries in this category yet.') ?></div>
<?php else: ?>
  <div class="vp-card vp-table-card">
    <table class="vp-table vp-audit-table">
      <thead>
        <tr>
          <th>When</th>
          <th>Who</th>
          <th>Action</th>
          <th>Details</th>
          <th>IP address</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($entries as $a): ?>
          <tr>
            <td class="vp-audit-when">
              <strong><?= time_ago($a['created_at']) ?></strong>
              <span><?= fmt_date($a['created_at']) ?> · <?= fmt_time($a['created_at']) ?></span>
            </td>
            <td>
              <?php if ($a['actor_username']): ?>
                <span class="vp-audit-who">@<?= e($a['actor_username']) ?></span>
              <?php else: ?>
                <span class="vp-dim">unknown</span>
              <?php endif; ?>
            </td>
            <td><?= audit_pill($a['action']) ?></td>
            <td class="vp-audit-detail">
              <?= e($a['details'] ?: '—') ?>
              <?php if ($a['target_label'] && stripos((string)$a['details'], (string)$a['target_label']) === false): ?>
                <span class="vp-audit-target">@<?= e($a['target_label']) ?></span>
              <?php endif; ?>
            </td>
            <td class="vp-audit-ip"><?= e($a['ip_address'] ?: '—') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php require 'includes/footer.php'; ?>
