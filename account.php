<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_login();   // any signed-in user may manage their OWN account

$PAGE = 'account';
$PAGE_TITLE = 'My Account';

$uid   = (int)current_user()['id'];
$staff = is_staff();

// Load the live account record (session data can be stale).
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$uid]);
$me = $stmt->fetch();
if (!$me) { redirect('logout.php'); }

// Owners keep their client details in the owners table.
$ownerRow = null;
if ($me['role'] === 'owner' && $me['owner_id']) {
    $o = $pdo->prepare("SELECT * FROM owners WHERE id = ?");
    $o->execute([(int)$me['owner_id']]);
    $ownerRow = $o->fetch();
}

// This user's own recent activity.
$act = $pdo->prepare("SELECT * FROM activity_log WHERE actor_id = ? ORDER BY created_at DESC, id DESC LIMIT 8");
$act->execute([$uid]);
$myActivity = $act->fetchAll();

// Prefer the owner record for contact details when present.
$email = $ownerRow['email'] ?? $me['email'] ?? '';
$phone = $ownerRow['phone'] ?? $me['phone'] ?? '';
$addr  = $ownerRow['address'] ?? '';
$hasSecurityQ = (int)($me['security_set'] ?? 0) === 1;

require 'includes/header.php';
?>

<!-- Account overview -->
<div class="vp-card vp-acct-overview">
  <div class="vp-acct-id">
    <div class="vp-acct-avatar <?= $staff ? 'staff' : 'owner' ?>">
      <?php if ($staff): ?>
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.8 2.3v5.5a5 5 0 0 0 10 0V2.3M9.8 12.8v3a4 4 0 0 0 8 0v-1.5"/><circle cx="19" cy="12.5" r="2.2"/></svg>
      <?php else: ?>
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-6 8-6s8 2 8 6"/></svg>
      <?php endif; ?>
    </div>
    <div>
      <h2 class="vp-acct-name"><?= e(build_full_name($me['first_name'], $me['middle_name'], $me['last_name'])) ?></h2>
      <div class="vp-acct-tags">
        <span class="vp-pill" style="background:var(--muted-bg);color:var(--muted-fg)"><span class="vp-pill-dot" style="background:var(--muted-fg)"></span><?= e($me['email']) ?></span>
        <?= status_pill($me['is_active'] ? 'Active' : 'Inactive') ?>
        <span class="vp-pill" style="background:var(--teal-soft);color:var(--pine)"><span class="vp-pill-dot" style="background:var(--pine)"></span><?= e(role_label($me['role'], $me['staff_title'] ?? null)) ?></span>
        <?php if ($me['role'] === 'owner' && $me['can_manage_users']): ?>
          <span class="vp-pill" style="background:var(--warn-bg);color:var(--warn-fg)"><span class="vp-pill-dot" style="background:var(--warn-fg)"></span>User management</span>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="vp-acct-facts">
    <div><label>Member since</label><span><?= fmt_date($me['created_at']) ?></span></div>
    <div><label>Last sign-in</label><span><?= $me['last_login'] ? fmt_date($me['last_login']) . ' · ' . fmt_time($me['last_login']) : '—' ?></span></div>
  </div>
</div>

<div class="vp-grid-2">
  <!-- Profile -->
  <div class="vp-card">
    <div class="vp-card-head"><h3>Profile information</h3></div>
    <form method="post" action="actions/update_profile.php" class="vp-acct-form">
      <?= form_alert('profile') ?>
      <?= csrf_field() ?>
      <div class="vp-form-grid">
        <div class="vp-field"><label>First name</label><input name="first_name" value="<?= e($me['first_name']) ?>" required></div>
        <div class="vp-field"><label>Middle name</label><input name="middle_name" value="<?= e($me['middle_name']) ?>" required></div>
        <div class="vp-field"><label>Last name</label><input name="last_name" value="<?= e($me['last_name']) ?>" required></div>
        <div class="vp-field"><label>Email address</label><input type="email" name="email" value="<?= e($email) ?>" placeholder="you@email.com" required></div>
        <div class="vp-field"><label>Phone</label><input name="phone" value="<?= e($phone) ?>" placeholder="0917-000-0000" inputmode="numeric" maxlength="13" data-phone-mask></div>
        <?php if ($me['role'] === 'owner'): ?>
          <div class="vp-field vp-field-wide"><label>Home address</label><input name="address" value="<?= e($addr) ?>" placeholder="Barangay, Town, Province"></div>
        <?php endif; ?>
      </div>
      <div class="vp-form-grid one">
        <div class="vp-field">
          <label>Current password</label>
          <input type="password" name="current_password" required autocomplete="current-password">
          <small class="vp-field-note">Enter your password to confirm these changes.</small>
        </div>
      </div>
      <div class="vp-form-actions">
        <button type="submit" class="vp-btn-primary">Save profile</button>
      </div>
    </form>
  </div>

  <!-- Password -->
  <div class="vp-card" id="password">
    <div class="vp-card-head"><h3>Change password</h3></div>
    <form method="post" action="actions/change_password.php" class="vp-acct-form">
      <?= form_alert('password') ?>
      <?= csrf_field() ?>
      <div class="vp-form-grid one">
        <div class="vp-field"><label>Current password</label><input type="password" name="current_password" required autocomplete="current-password"></div>
        <div class="vp-field"><label>New password</label><input type="password" name="new_password" id="vpNewPw" required autocomplete="new-password"></div>
        <div class="vp-field"><label>Confirm new password</label><input type="password" name="confirm_password" id="vpConfirmPw" required autocomplete="new-password"></div>
      </div>

      <ul class="vp-pw-rules" id="vpPwRules" aria-live="polite">
        <?php foreach (password_checks('') as $label => $_): ?>
          <li data-rule="<?= e($label) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
            <span><?= e($label) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
      <div class="vp-form-actions">
        <button type="submit" class="vp-btn-primary">Update password</button>
      </div>
    </form>
  </div>
</div>

<div class="vp-grid-2">
  <!-- Security settings -->
  <div class="vp-card" id="security">
    <div class="vp-card-head">
      <h3>Security settings</h3>
      <span class="vp-chip-static <?= $hasSecurityQ ? 'on' : '' ?>"><?= $hasSecurityQ ? 'Recovery questions set' : 'Not set up' ?></span>
    </div>
    <form method="post" action="actions/save_security.php" class="vp-acct-form">
      <?= form_alert('security') ?>
      <?= csrf_field() ?>
      <input type="hidden" name="from" value="account">
      <?php
        $qbank = security_questions();
        $suggested = security_questions_for((int)$me['id']);
        for ($i = 1; $i <= 3; $i++):
          $cur = $me['security_q' . $i] ?? '';
      ?>
        <div class="vp-form-grid one">
          <div class="vp-field">
            <label>Question <?= $i ?></label>
            <select name="q<?= $i ?>" class="vp-secq" required>
              <?php foreach ($qbank as $qi => $q): ?>
                <option value="<?= e($q) ?>" <?= ($cur !== '' ? $cur === $q : $q === $suggested[$i - 1]) ? 'selected' : '' ?>><?= e($q) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="vp-field">
            <label>Your answer</label>
            <input name="a<?= $i ?>" autocomplete="off" required
                   placeholder="<?= $hasSecurityQ ? 'Re-enter to keep or change it' : 'Type your answer' ?>">
          </div>
        </div>
      <?php endfor; ?>
      <p class="vp-hint-text">
        One of these is picked at random if you ever need to recover your
        account. Answers aren't case-sensitive and are stored securely.
      </p>
      <div class="vp-form-grid one">
        <div class="vp-field">
          <label>Current password</label>
          <input type="password" name="current_password" required autocomplete="current-password">
          <small class="vp-field-note">Enter your password to confirm these changes.</small>
        </div>
      </div>
      <div class="vp-form-actions">
        <button type="submit" class="vp-btn-primary">Save security questions</button>
      </div>
    </form>
  </div>

  <!-- Recent activity -->
  <div class="vp-card">
    <div class="vp-card-head"><h3>Recent account activity</h3></div>
    <?php if (!$myActivity): ?>
      <?= empty_row('clip', 'No account activity yet.') ?>
    <?php else: ?>
      <div class="vp-activity">
        <?php foreach ($myActivity as $a): ?>
          <div class="vp-activity-row">
            <?= audit_pill($a['action']) ?>
            <span class="vp-activity-detail"><?= e($a['details'] ?: '—') ?></span>
            <span class="vp-activity-time"><?= time_ago($a['created_at']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Data privacy -->
  <div class="vp-card">
    <div class="vp-card-head"><h3>Data privacy</h3></div>
    <div class="vp-acct-privacy">
      <?php if ($ownerRow !== null): ?>
        <?php $consentAt = $ownerRow['privacy_consent_at'] ?? null; ?>
        <?php if ($consentAt): ?>
          <p class="vp-privacy-status ok">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
            Privacy Notice agreed on <?= fmt_date($consentAt) ?><?php if (!empty($ownerRow['privacy_consent_ver'])): ?> (version <?= e($ownerRow['privacy_consent_ver']) ?>)<?php endif; ?>.
          </p>
        <?php else: ?>
          <p class="vp-privacy-status pending">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
            No privacy consent is recorded on your client record yet.
          </p>
        <?php endif; ?>
      <?php endif; ?>
      <p class="vp-hint-text" style="margin-top:2px">
        We handle your personal data under the Data Privacy Act of 2012.
        Read the <a href="privacy.php" target="_blank" rel="noopener">Privacy Notice</a>
        to see what we collect and why. You may request access, correction, or
        deletion of your data, or withdraw consent, by contacting us<?php if (defined('PRIVACY_CONTACT_EMAIL') && PRIVACY_CONTACT_EMAIL): ?>
        at <?= e(PRIVACY_CONTACT_EMAIL) ?><?php endif; ?>.
      </p>
    </div>
  </div>
</div>

<?php require 'includes/footer.php'; ?>
