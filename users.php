<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_manage_users();   // staff, or an owner granted the permission

$PAGE = 'users';
$PAGE_TITLE = 'User Accounts';

$selfId = (int)current_user()['id'];

// Filters.
$q    = trim($_GET['q'] ?? '');
$role = $_GET['role'] ?? 'All';

$sql = "SELECT u.*, CONCAT_WS(' ', NULLIF(u.first_name,''), NULLIF(u.middle_name,''), NULLIF(u.last_name,'')) AS full_name, CONCAT_WS(' ', NULLIF(o.first_name,''), NULLIF(o.middle_name,''), NULLIF(o.last_name,'')) AS owner_name
        FROM users u LEFT JOIN owners o ON o.id = u.owner_id
        WHERE u.deleted_at IS NULL";
$params = [];
if ($q !== '') {
    $sql .= " AND (u.email LIKE ? OR CONCAT_WS(' ', NULLIF(u.first_name,''), NULLIF(u.middle_name,''), NULLIF(u.last_name,'')) LIKE ? OR u.email LIKE ?)";
    $like = "%$q%";
    array_push($params, $like, $like, $like);
}
if ($role === 'Staff')  $sql .= " AND u.role = 'staff'";
if ($role === 'Owner')  $sql .= " AND u.role = 'owner'";
// Your own account sits at the top of the list, then staff before
// owners, then alphabetically by first name. The self test is a bound
// parameter, not string-interpolated, so it stays a prepared statement.
$sql .= " ORDER BY (u.id = ?) DESC, u.role, u.first_name, u.last_name";
$params[] = $selfId;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Counts for the summary strip.
$counts = $pdo->query("
    SELECT
      COUNT(*) AS total,
      SUM(is_active = 1) AS active,
      SUM(role = 'staff') AS staff,
      SUM(role = 'owner') AS owner
    FROM users
    WHERE deleted_at IS NULL
")->fetch();

$owners = $pdo->query("SELECT * FROM owners ORDER BY last_name, first_name")->fetchAll();
$roleTabs = ['All', 'Staff', 'Owner'];

require 'includes/header.php';
?>

<div class="vp-stat-row">
  <?php
    render_stat('users', 'Total accounts', (int)$counts['total'],  'All roles',        'teal');
    render_stat('check', 'Active',         (int)$counts['active'], 'Able to sign in',  'pine');
    render_stat('user',  'Staff',          (int)$counts['staff'],  'Administrator accounts',      'amber');
    render_stat('paw',   'Owners',         (int)$counts['owner'],  'Portal accounts',  'rose');
  ?>
</div>

<!-- Toolbar -->
<form method="get" class="vp-toolbar" id="userFilter">
  <div class="vp-search">
    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search by name or email…" oninput="document.getElementById('userFilter').submit()">
  </div>
  <div class="vp-filter-chips">
    <?php foreach ($roleTabs as $t): ?>
      <button type="submit" name="role" value="<?= $t ?>" class="vp-chip <?= $role === $t ? 'active' : '' ?>"><?= $t ?></button>
    <?php endforeach; ?>
  </div>
  <button type="button" class="vp-btn-primary" data-open-modal="modal-user-new">
    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
    New account
  </button>
</form>

<?php if (!$users): ?>
  <div class="vp-card"><?= empty_row('users', 'No accounts match your search.') ?></div>
<?php else: ?>
  <div class="vp-card vp-table-card">
    <table class="vp-table">
      <thead>
        <tr>
          <th>Account</th>
          <th>Role</th>
          <th>Status</th>
          <th>Last sign-in</th>
          <th class="vp-th-actions">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u):
          $isSelf = (int)$u['id'] === $selfId;
          $isAdmin       = (int)$u['can_manage_users'] === 1;
          $isLimitedStaff = $u['role'] === 'staff' && !$isAdmin;
        ?>
        <tr>
          <td>
            <div class="vp-u-cell">
              <div class="vp-u-avatar <?= $u['role'] === 'staff' ? 'staff' : 'owner' ?>">
                <?= strtoupper(substr($u['full_name'], 0, 1)) ?>
              </div>
              <div class="vp-u-meta">
                <strong><?= e($u['full_name']) ?> <?php if ($isSelf): ?><span class="vp-you">you</span><?php endif; ?></strong>
                <span><?= e($u['email']) ?></span>
              </div>
            </div>
          </td>
          <td>
            <span class="vp-role-tag <?= $u['role'] ?>"><?= e(role_label($u['role'], $u['staff_title'] ?? null)) ?></span>
            <?php if ($isAdmin): ?><span class="vp-role-tag admin">Full access</span><?php endif; ?>
            <?php if ($isLimitedStaff): ?><span class="vp-role-tag limited">Limited access</span><?php endif; ?>
          </td>
          <td><?= status_pill($u['is_active'] ? 'Active' : 'Inactive') ?><?php if (!$u['is_active']): ?><span class="vp-inactive-lbl">Deactivated</span><?php endif; ?></td>
          <td class="vp-u-last"><?= $u['last_login'] ? fmt_date($u['last_login']) : '<span class="vp-dim">Never</span>' ?></td>
          <td>
            <div class="vp-row-actions">
              <button type="button" class="vp-btn-tiny" data-open-modal="modal-user-edit-<?= (int)$u['id'] ?>" title="Edit account">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
              </button>
              <?php if (!$isSelf): ?>
                <form method="post" action="actions/toggle_user_active.php" style="display:inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                  <?php if ($u['is_active']): ?>
                    <button type="submit" class="vp-btn-tiny" title="Deactivate account">
                      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18.36 6.64A9 9 0 1 1 5.64 6.64M12 2v10"/></svg>
                    </button>
                  <?php else: ?>
                    <button type="submit" class="vp-btn-tiny on" title="Activate account">
                      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                    </button>
                  <?php endif; ?>
                </form>
                <form method="post" action="actions/delete_user.php" style="display:inline"
                      data-confirm="Delete the account for <?= e(build_full_name($u['first_name'], $u['middle_name'], $u['last_name'])) ?> (<?= e($u['email']) ?>)? (Their pet records are kept.)">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                  <button type="submit" class="vp-btn-tiny danger" title="Delete account">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m2 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg>
                  </button>
                </form>
              <?php else: ?>
                <span class="vp-self-lock" title="You can't deactivate or delete your own account">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                </span>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<!-- Create + edit modals -->
<?php render_user_modal('modal-user-new', null, $owners, $selfId); ?>
<?php foreach ($users as $u): ?>
  <?php render_user_modal('modal-user-edit-' . (int)$u['id'], $u, $owners, $selfId); ?>
<?php endforeach; ?>

<?php require 'includes/footer.php'; ?>
