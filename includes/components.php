<?php
/* ============================================================
   Reusable render helpers (echo HTML directly).
   Included by header.php so every page can use them.
   ============================================================ */

/** Small stat icons used on the dashboard / reports. */
function stat_icon($name) {
    $set = [
        'paw'   => ['fill', '<circle cx="6" cy="9" r="1.6"/><circle cx="10" cy="6.5" r="1.6"/><circle cx="14" cy="6.5" r="1.6"/><circle cx="18" cy="9" r="1.6"/><path d="M8 15c0-2.5 1.8-4 4-4s4 1.5 4 4c0 1.8-1.6 2.6-4 2.6S8 16.8 8 15z"/>'],
        'pulse' => ['stroke', '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>'],
        'cal'   => ['stroke', '<rect x="3" y="4.5" width="18" height="16" rx="2"/><path d="M3 9h18M8 2.5v4M16 2.5v4"/>'],
        'vax'   => ['stroke', '<path d="m18 2 4 4M17 3l3.5 3.5M13 7l4 4M11 9l-7 7v4h4l7-7"/>'],
        'users' => ['stroke', '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9"/>'],
        'clip'  => ['stroke', '<path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/>'],
        'chart' => ['stroke', '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>'],
        'check' => ['stroke', '<path d="M22 11.1V12a10 10 0 1 1-5.9-9.1"/><path d="M22 4 12 14.01l-3-3"/>'],
    ];
    [$mode, $path] = $set[$name] ?? $set['paw'];
    if ($mode === 'fill') {
        return "<svg width=\"20\" height=\"20\" viewBox=\"0 0 24 24\" fill=\"currentColor\">$path</svg>";
    }
    return "<svg width=\"20\" height=\"20\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\">$path</svg>";
}

function icon_vax()  { return stat_icon('vax'); }
function icon_clip() { return stat_icon('clip'); }

/**
 * A dashboard stat card. Pass $href to make it a link to wherever that
 * count comes from (e.g. the filtered patient or appointment list);
 * leave it null for a plain, non-clickable card (reports.php, users.php).
 */
function render_stat($icon, $label, $value, $sub, $tone, $href = null) {
    $tag   = $href !== null ? 'a' : 'div';
    $attrs = $href !== null ? ' href="' . e($href) . '"' : '';
    echo "<$tag class=\"vp-stat vp-tone-$tone" . ($href !== null ? ' vp-stat-link' : '') . "\"$attrs>"
       . '<div class="vp-stat-icon">' . stat_icon($icon) . '</div>'
       . '<div class="vp-stat-body">'
       . '<span class="vp-stat-value">' . (int)$value . '</span>'
       . '<span class="vp-stat-label">' . e($label) . '</span>'
       . '<span class="vp-stat-sub">' . e($sub) . '</span>'
       . "</div></$tag>";
}

/** An empty-state row. */
function empty_row($icon, $text) {
    return '<div class="vp-empty">' . stat_icon($icon)
         . '<p>' . e($text) . '</p></div>';
}

/** A single vital tile on the patient chart. */
function render_vital($icon, $label, $value, $tone, $small = false) {
    $icons = [
        'weight' => '<path d="M6.5 2h11l3 20H3.5z"/><circle cx="12" cy="9" r="3"/>',
        'temp'   => '<path d="M14 14.8V4a2 2 0 0 0-4 0v10.8a4 4 0 1 0 4 0z"/>',
        'heart'  => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.5 1-1a5.5 5.5 0 0 0 0-7.9z"/>',
        'alert'  => '<circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/>',
    ];
    $path = $icons[$icon] ?? $icons['alert'];
    $svg = "<svg width=\"17\" height=\"17\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\">$path</svg>";
    $valCls = 'vp-vital-value' . ($small ? ' sm' : '');
    echo '<div class="vp-vital vp-tone-' . $tone . '">'
       . '<div class="vp-vital-icon">' . $svg . '</div>'
       . '<div class="vp-vital-body">'
       . '<span class="vp-vital-label">' . e($label) . '</span>'
       . '<span class="' . $valCls . '">' . $value . '</span>'
       . '</div></div>';
}

/** A label/value pair in the details grid. */
function render_detail($label, $value) {
    echo '<div class="vp-detail">'
       . '<span class="vp-detail-label">' . e($label) . '</span>'
       . '<span class="vp-detail-value">' . ($value !== '' && $value !== null ? e($value) : '—') . '</span>'
       . '</div>';
}

/**
 * A pet card.
 *  $p            patient row
 *  $showOwner    whether to show the owner line
 *  $ownerName    owner display name (when $showOwner)
 *  $latestVisit  latest visit row or null (for the "Latest" blurb)
 *  $canManage    staff-only edit/delete hover actions
 */
function render_pet_card($p, $showOwner = false, $ownerName = null, $latestVisit = null, $canManage = false) {
    $pid = (int)$p['id'];
    echo '<div class="vp-pet-card">';
    echo '<a class="vp-pet-main" href="patient.php?id=' . $pid . '">';

    // When an owner is shown, THEY are the headline and the pet becomes
    // the supporting line; otherwise (an owner viewing their own pets)
    // the pet stays the headline.
    $headline = ($showOwner && $ownerName !== null) ? $ownerName : $p['name'];
    // Species, then breed (e.g. "Bird · Cockatiel"); either may be blank.
    $kind    = implode(' · ', array_filter([$p['species'] ?? '', $p['breed'] ?? ''], fn($x) => trim((string)$x) !== ''));
    $subline = ($showOwner && $ownerName !== null)
        ? implode(' · ', array_filter([$p['name'], $kind]))
        : $kind;

    echo '<div class="vp-pet-top">'
       . '<div class="vp-pet-avatar">' . species_icon($p['species'], 22) . '</div>'
       . '<div class="vp-pet-name"><strong>' . e($headline) . '</strong><span>' . e($subline) . '</span></div>'
       . status_pill($p['status'])
       . '</div>';

    echo '<div class="vp-pet-meta">'
       . '<span>' . e($p['sex']) . '</span><i>·</i>'
       . '<span>' . age_from_birth($p['birth']) . '</span><i>·</i>'
       . '<span>' . e($p['weight']) . ' kg</span>'
       . '</div>';

    // (The owner is already the headline above, so no separate owner
    //  line is needed here.)

    // Defence in depth: the diagnosis blurb is clinical information, so
    // the card refuses to print it for a non-staff viewer even if a
    // caller passes a visit row in by mistake.
    if ($latestVisit && is_staff()) {
        echo '<div class="vp-pet-dx"><label>Latest</label><p>' . e($latestVisit['diagnosis']) . '</p></div>';
    }

    echo '<div class="vp-pet-foot"><span class="vp-pet-open">Open chart '
       . '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>'
       . '</span></div>';

    echo '</a>';

    if ($canManage) {
        echo '<div class="vp-pet-hover-actions">';
        echo '<button type="button" title="Edit" data-open-modal="modal-edit-' . $pid . '">'
           . '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg></button>';
        echo '<form method="post" action="actions/delete_patient.php" data-confirm="Remove ' . e($p['name']) . '\'s record? This deletes their chart, visits, and appointments." style="display:inline">'
           . csrf_field()
           . '<input type="hidden" name="id" value="' . $pid . '">'
           . '<button type="submit" class="danger" title="Remove">'
           . '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m2 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg>'
           . '</button></form>';
        echo '</div>';
    }

    echo '</div>';
}

/**
 * A patient add/edit modal form.
 *  $p       existing patient row, or null for a new one
 *  $owners  array of owner rows (for the owner dropdown); null hides it
 */
function render_patient_modal($modalId, $p, $owners, $speciesOptions = null) {
    $isEdit = $p !== null;
    $title  = $isEdit ? 'Edit ' . e($p['name']) : 'New patient record';
    $action = $isEdit ? 'actions/update_patient.php' : 'actions/create_patient.php';
    $val = fn($k, $d = '') => $isEdit ? e($p[$k]) : $d;
    ?>
    <div class="vp-modal-overlay" id="<?= $modalId ?>">
      <div class="vp-modal wide">
        <div class="vp-modal-head">
          <div>
            <h3><?= $title ?></h3>
            <p>Enter the pet's details. Fields are saved to the clinic database.</p>
          </div>
        </div>
        <form method="post" action="<?= $action ?>">
          <?= csrf_field() ?>
          <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><?php endif; ?>
          <div class="vp-modal-body">
            <div class="vp-form-grid">
              <div class="vp-field"><label>Pet name</label><input name="name" value="<?= $val('name') ?>" placeholder="e.g. Bruno" required></div>
              <div class="vp-field"><label>Species</label>
                <select name="species">
                  <?php
                    // Staff manage this list on the Patients screen; fall back
                    // to the original four if none were passed in.
                    $spOpts = $speciesOptions ?: ['Dog','Cat','Bird','Rabbit'];
                    $spCur  = $val('species', $spOpts[0] ?? 'Dog');
                    foreach ($spOpts as $s): ?>
                    <option <?= $spCur === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="vp-field"><label>Breed</label><input name="breed" value="<?= $val('breed') ?>" placeholder="e.g. Aspin" required></div>
              <div class="vp-field"><label>Sex</label>
                <select name="sex">
                  <option <?= $val('sex','Male') === 'Male' ? 'selected' : '' ?>>Male</option>
                  <option <?= $val('sex') === 'Female' ? 'selected' : '' ?>>Female</option>
                </select>
              </div>
              <div class="vp-field"><label>Color / markings</label><input name="color" value="<?= $val('color') ?>" placeholder="e.g. Brown/White"></div>
              <div class="vp-field"><label>Date of birth</label><input type="date" name="birth" value="<?= $val('birth','2024-01-01') ?>"></div>
              <div class="vp-field"><label>Weight (kg)</label><input type="number" step="0.01" name="weight" value="<?= $isEdit ? e($p['weight']) : '0' ?>"></div>
              <div class="vp-field"><label>Temp (°C)</label><input type="number" step="0.1" name="temp" value="<?= $isEdit ? e($p['temp']) : '38.5' ?>"></div>
              <div class="vp-field"><label>Heart rate (bpm)</label><input type="number" name="heart" value="<?= $isEdit ? e($p['heart']) : '100' ?>"></div>
              <div class="vp-field"><label>Status</label>
                <select name="status">
                  <option <?= $val('status','Active') === 'Active' ? 'selected' : '' ?>>Active</option>
                  <option <?= $val('status') === 'Under Treatment' ? 'selected' : '' ?>>Under Treatment</option>
                </select>
              </div>
              <div class="vp-field"><label>Allergies</label><input name="allergies" value="<?= $val('allergies','None known') ?>" placeholder="None known"></div>
              <?php if ($owners !== null): ?>
                <div class="vp-field"><label>Owner</label>
                  <select name="owner_id" data-searchable
                          data-placeholder="Select an owner…"
                          data-search-placeholder="Search owner…">
                    <?php foreach ($owners as $o): ?>
                      <option value="<?= (int)$o['id'] ?>" <?= $isEdit && (int)$p['owner_id'] === (int)$o['id'] ? 'selected' : '' ?>><?= e(build_full_name($o['first_name'], $o['middle_name'], $o['last_name'])) ?><?= !empty($o['email']) ? ' · ' . e($o['email']) : '' ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              <?php endif; ?>
            </div>
            <div class="vp-form-actions">
              <button type="button" class="vp-btn-ghost" data-close-modal>Cancel</button>
              <button type="submit" class="vp-btn-primary"><?= $isEdit ? 'Save changes' : 'Add patient' ?></button>
            </div>
          </div>
        </form>
      </div>
    </div>
    <?php
}

/**
 * A create/edit modal for a USER ACCOUNT (user-management module).
 *  $modalId  DOM id for the overlay
 *  $u        existing user row, or null for a new account
 *  $owners   array of owner rows (to link an owner account to a client)
 *  $selfId   id of the logged-in user (self-editing is restricted)
 */
function render_user_modal($modalId, $u, $owners, $selfId) {
    $isEdit = $u !== null;
    $isSelf = $isEdit && (int)$u['id'] === (int)$selfId;
    $title  = $isEdit
        ? 'Edit ' . e(build_full_name($u['first_name'], $u['middle_name'], $u['last_name']))
        : 'New user account';
    $action = $isEdit ? 'actions/update_user.php' : 'actions/create_user.php';
    $role   = $isEdit ? $u['role'] : 'owner';
    // The role dropdown offers the two clinic-staff titles plus pet owner.
    // Its submitted value is one of: veterinarian | staff | owner, which the
    // create/update actions split back into role + staff_title.
    $roleChoice = 'owner';
    if ($role === 'staff') {
        $curTitle = $isEdit ? ($u['staff_title'] ?? '') : '';
        $roleChoice = $curTitle === 'veterinarian' ? 'veterinarian' : 'staff';
    }
    ?>
    <div class="vp-modal-overlay" id="<?= $modalId ?>">
      <div class="vp-modal wide">
        <div class="vp-modal-head">
          <div>
            <h3><?= $title ?></h3>
            <p><?= $isEdit ? 'Update this account. Leave the password blank to keep it unchanged.' : 'The email address is the login and the phone number becomes the temporary password.' ?></p>
          </div>
        </div>
        <form method="post" action="<?= $action ?>">
          <?= csrf_field() ?>
          <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><?php endif; ?>
          <div class="vp-modal-body">
            <?= form_alert($isEdit ? 'user-edit-' . (int)$u['id'] : 'user-new') ?>
            <div class="vp-form-grid">
              <div class="vp-field"><label>First name</label><input name="first_name" value="<?= $isEdit ? e($u['first_name']) : '' ?>" placeholder="e.g. Juan" required></div>
              <div class="vp-field"><label>Middle name</label><input name="middle_name" value="<?= $isEdit ? e($u['middle_name']) : '' ?>" placeholder="e.g. Reyes" required></div>
              <div class="vp-field"><label>Last name</label><input name="last_name" value="<?= $isEdit ? e($u['last_name']) : '' ?>" placeholder="e.g. Santos" required></div>
              <div class="vp-field">
                <label>Role</label>
                <select name="role" <?= $isSelf ? 'disabled' : '' ?>>
                  <optgroup label="Clinic staff">
                    <option value="veterinarian" <?= $roleChoice === 'veterinarian' ? 'selected' : '' ?>>Veterinarian</option>
                    <option value="staff" <?= $roleChoice === 'staff' ? 'selected' : '' ?>>Staff</option>
                  </optgroup>
                  <option value="owner" <?= $roleChoice === 'owner' ? 'selected' : '' ?>>Pet owner</option>
                </select>
                <?php if ($isSelf): ?><small class="vp-field-note">You can't change your own role.</small><?php endif; ?>
              </div>
              <div class="vp-field">
                <label>Linked owner <small>(owner accounts)</small></label>
                <select name="owner_id">
                  <option value="">— none —</option>
                  <?php foreach ($owners as $o): ?>
                    <option value="<?= (int)$o['id'] ?>" <?= $isEdit && (int)$u['owner_id'] === (int)$o['id'] ? 'selected' : '' ?>><?= e(build_full_name($o['first_name'], $o['middle_name'], $o['last_name'])) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="vp-field">
                <label>Email <small>(this is the sign-in address)</small></label>
                <input type="email" name="email" value="<?= $isEdit ? e($u['email']) : '' ?>" placeholder="you@email.com" required>
              </div>
              <div class="vp-field">
                <label>Phone <?= $isEdit ? '' : '<small>(becomes the temporary password)</small>' ?></label>
                <input name="phone" value="<?= $isEdit ? e($u['phone']) : '' ?>" placeholder="0917-000-0000" inputmode="numeric" maxlength="13" data-phone-mask <?= $isEdit ? '' : 'required' ?>>
              </div>
              <?php if (!$isEdit): ?>
              <div class="vp-field vp-field-wide" id="vp-addr-field">
                <label>Address <small>(required — saved to their client record)</small></label>
                <input name="address" id="vp-addr-input" placeholder="e.g. Bantug, Roxas, Isabela" required>
              </div>
              <?php endif; ?>
              <?php if ($isEdit): ?>
              <div class="vp-field vp-field-wide">
                <label>New password</label>
                <input type="password" name="password" autocomplete="new-password" placeholder="Leave blank to keep current password">
              </div>
              <?php endif; ?>
            </div>

            <div class="vp-check-row">
              <label class="vp-check">
                <input type="checkbox" name="is_active" value="1" <?= (!$isEdit || (int)$u['is_active'] === 1) ? 'checked' : '' ?> <?= $isSelf ? 'disabled' : '' ?>>
                <span>Active account <small>(can sign in)</small></span>
              </label>
              <label class="vp-check">
                <input type="checkbox" name="can_manage_users" value="1" class="vp-canmanage"
                       <?= $isEdit && (int)$u['can_manage_users'] === 1 ? 'checked' : '' ?>
                       <?= $isSelf ? 'disabled' : '' ?>>
                <span>Allow user-account management
                  <small>(User Accounts + Activity Log — untick for a clinic login with no admin features)</small></span>
              </label>
              <label class="vp-check">
                <input type="checkbox" name="must_change_password" value="1" <?= (!$isEdit || (int)$u['must_change_password'] === 1) ? 'checked' : '' ?> <?= $isEdit ? '' : 'disabled' ?>>
                <span>Require a password change at next sign-in<?= $isEdit ? '' : ' <small>(always on for new accounts)</small>' ?></span>
              </label>
            </div>
            <?php if (!$isEdit): ?>
              <p class="vp-hint-text">Sign-in details are generated automatically: the <strong>email address</strong> is the login and the <strong>digits of the phone number</strong> become the temporary password. The new user must choose their own password the first time they sign in.</p>
            <?php endif; ?>
            <?php if ($isSelf): ?>
              <p class="vp-hint-text">Some options are locked because you're editing your own account — use another admin to change your role, status, or permissions.</p>
            <?php endif; ?>

            <div class="vp-form-actions">
              <button type="button" class="vp-btn-ghost" data-close-modal>Cancel</button>
              <button type="submit" class="vp-btn-primary"><?= $isEdit ? 'Save changes' : 'Create account' ?></button>
            </div>
          </div>
        </form>
      </div>
    </div>
    <?php
}

/**
 * A formal, paper-standard veterinary medical record for printing.
 *
 * This is a self-contained document (hidden on screen, shown only when
 * printing) laid out the way a clinic keeps a patient file on paper:
 * letterhead, patient + client identification, a clinical-parameter
 * snapshot, the immunisation record, the chronological medical history,
 * and a certification/signature block.
 *
 *  $p       patient row joined with owner fields
 *  $vaccs   vaccination rows (newest first)
 *  $visits  visit rows (newest first) — clinical history; empty for owners
 *  $opts    ['clinic_name','clinic_address','clinic_contact','printed_by']
 */
function render_patient_print_record($p, $vaccs = [], $visits = [], $opts = []) {
    $clinicName    = $opts['clinic_name']    ?? (defined('CLINIC_NAME') ? CLINIC_NAME : 'Paw Prints Veterinary Clinic');
    $clinicAddress = $opts['clinic_address'] ?? 'Bantug, Roxas, Isabela';
    $clinicContact = $opts['clinic_contact'] ?? '';
    $printedBy     = $opts['printed_by']     ?? '';

    // A stable file/record number so the printout can be matched back to
    // the system record when it lives in a physical folder.
    $recordNo = 'PP-' . str_pad((string)(int)$p['id'], 5, '0', STR_PAD_LEFT);
    $now      = date('F j, Y \a\t g:i A');
    $ownerName = format_name_formal($p['owner_first'], $p['owner_middle'], $p['owner_last']);

    // Vaccination status summary for the header line.
    $vaccCount = count($vaccs);
    $visitCount = count($visits);

    // Allergies are safety-critical, so they are flagged rather than
    // buried in the detail grid.
    $allergies = trim((string)($p['allergies'] ?? ''));
    $hasAllergy = $allergies !== '' && strcasecmp($allergies, 'None known') !== 0 && strcasecmp($allergies, 'None') !== 0;
    ?>
    <div class="vp-med-record" aria-hidden="true">

      <!-- Letterhead -->
      <header class="vp-mr-letterhead">
        <div class="vp-mr-clinic">
          <div class="vp-mr-logo"><?= species_icon('paw', 30) ?></div>
          <div class="vp-mr-clinic-text">
            <h1><?= e($clinicName) ?></h1>
            <p><?= e($clinicAddress) ?><?= $clinicContact ? ' &middot; ' . e($clinicContact) : '' ?></p>
          </div>
        </div>
        <div class="vp-mr-docmeta">
          <span class="vp-mr-doctitle">Patient Medical Record</span>
          <span class="vp-mr-recno">Record No. <?= e($recordNo) ?></span>
          <span class="vp-mr-printed">Issued <?= e($now) ?></span>
        </div>
      </header>

      <?php if ($hasAllergy): ?>
        <div class="vp-mr-allergy">
          <strong>ALLERGY ALERT:</strong> <?= e($allergies) ?>
        </div>
      <?php endif; ?>

      <!-- Patient + Client identification -->
      <section class="vp-mr-idblock">
        <div class="vp-mr-panel">
          <h2 class="vp-mr-sec">Patient Information</h2>
          <table class="vp-mr-fields">
            <tr><th>Name</th><td><?= e($p['name']) ?></td>
                <th>Species</th><td><?= e($p['species']) ?></td></tr>
            <tr><th>Breed</th><td><?= e($p['breed']) ?></td>
                <th>Sex</th><td><?= e($p['sex']) ?></td></tr>
            <tr><th>Color / Markings</th><td><?= e($p['color'] ?: '—') ?></td>
                <th>Date of Birth</th><td><?= fmt_date($p['birth']) ?></td></tr>
            <tr><th>Age</th><td><?= e(age_from_birth($p['birth'])) ?></td>
                <th>Status</th><td><?= e($p['status']) ?></td></tr>
            <tr><th>Allergies</th><td colspan="3"><?= e($allergies ?: 'None known') ?></td></tr>
          </table>
        </div>

        <div class="vp-mr-panel">
          <h2 class="vp-mr-sec">Client / Owner Information</h2>
          <table class="vp-mr-fields">
            <tr><th>Owner</th><td colspan="3"><?= e($ownerName) ?></td></tr>
            <tr><th>Contact No.</th><td colspan="3"><?= e($p['owner_phone'] ?: '—') ?></td></tr>
            <tr><th>Address</th><td colspan="3"><?= e($p['owner_address'] ?? '' ?: '—') ?></td></tr>
          </table>

          <h2 class="vp-mr-sec" style="margin-top:10px">Clinical Parameters</h2>
          <table class="vp-mr-fields">
            <tr><th>Weight</th><td><?= e($p['weight']) ?> kg</td>
                <th>Temp.</th><td><?= e($p['temp']) ?> &deg;C</td></tr>
            <tr><th>Heart Rate</th><td colspan="3"><?= e($p['heart']) ?> bpm</td></tr>
          </table>
          <p class="vp-mr-note">Parameters reflect the most recent recorded examination.</p>
        </div>
      </section>

      <!-- Immunisation record -->
      <section class="vp-mr-section">
        <h2 class="vp-mr-sec">Immunization Record<?= $vaccCount ? ' (' . $vaccCount . ')' : '' ?></h2>
        <?php if (!$vaccs): ?>
          <p class="vp-mr-empty">No vaccinations on record.</p>
        <?php else: ?>
          <table class="vp-mr-table">
            <thead>
              <tr>
                <th style="width:32%">Vaccine / Product</th>
                <th style="width:20%">Date Given</th>
                <th style="width:20%">Next Due</th>
                <th style="width:28%">Administered By</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($vaccs as $v):
                  $d = days_until($v['next_due']);
                  $due = $d !== null && $d < 0 ? ' (overdue)' : '';
              ?>
                <tr>
                  <td><?= e($v['name']) ?></td>
                  <td><?= fmt_date($v['date_given']) ?></td>
                  <td><?= fmt_date($v['next_due']) ?><?= e($due) ?></td>
                  <td><?= e($v['vet']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </section>

      <!-- Medical history (clinical) -->
      <section class="vp-mr-section">
        <h2 class="vp-mr-sec">Medical History<?= $visitCount ? ' (' . $visitCount . ')' : '' ?></h2>
        <?php if (!$visits): ?>
          <p class="vp-mr-empty">No clinical visits recorded.</p>
        <?php else: ?>
          <?php foreach ($visits as $v): ?>
            <div class="vp-mr-visit">
              <div class="vp-mr-visit-head">
                <span class="vp-mr-visit-date"><?= fmt_date($v['visit_date']) ?></span>
                <span class="vp-mr-visit-vet">Attending: <?= e($v['vet'] ?: '—') ?></span>
              </div>
              <table class="vp-mr-visit-body">
                <tr><th>Presenting Complaint</th><td><?= e($v['reason'] ?: '—') ?></td></tr>
                <tr><th>Diagnosis</th><td><?= nl2br(e($v['diagnosis'] ?: '—')) ?></td></tr>
                <tr><th>Treatment / Medication</th><td><?= nl2br(e($v['treatment'] ?: '—')) ?></td></tr>
                <?php if (!empty($v['notes'])): ?>
                  <tr><th>Notes</th><td><?= nl2br(e($v['notes'])) ?></td></tr>
                <?php endif; ?>
              </table>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </section>

      <!-- Certification / signature -->
      <footer class="vp-mr-footer">
        <div class="vp-mr-sign">
          <div class="vp-mr-sign-line">
            <span class="vp-mr-sign-name"><?= $printedBy ? e($printedBy) : '' ?></span>
            <span class="vp-mr-sign-label">Attending Veterinarian (Signature over Printed Name)</span>
          </div>
          <div class="vp-mr-sign-line">
            <span class="vp-mr-sign-name"></span>
            <span class="vp-mr-sign-label">Date</span>
          </div>
        </div>
        <p class="vp-mr-confidential">
          This document is a confidential veterinary medical record of <?= e($clinicName) ?>.
          It is intended solely for the named patient and client and for the continuity of veterinary care.
          Record No. <?= e($recordNo) ?> &middot; Generated <?= e($now) ?>.
        </p>
      </footer>
    </div>
    <?php
}
