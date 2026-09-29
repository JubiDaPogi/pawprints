<?php
/* ============================================================
   Page shell — <head>, sidebar, and top bar.
   Set $PAGE (nav key) and $PAGE_TITLE before including this.
   Pages that use it must later include footer.php.
   ============================================================ */
require_login();
require_once __DIR__ . '/components.php';

// Data Privacy Act: show the Privacy Notice and require agreement at first
// sign-in, before anything else (including the recovery-questions setup).
require_privacy_consent();

// Force the recovery questions before anything else can be used.
require_security_setup();

// Appointment reminders go straight to the owner — nobody has to press
// anything. XAMPP has no cron, so the daily cycle piggybacks on the
// first page view of the day by ANY signed-in user (staff or owner).
// Run cron.php from Task Scheduler for a deployment that shouldn't
// depend on somebody opening the site.
$stampFile = __DIR__ . '/../storage/.reminders-ran';
$todayKey  = date('Y-m-d');
if (trim((string)@file_get_contents($stampFile)) !== $todayKey) {
    @file_put_contents($stampFile, $todayKey);
    require_once __DIR__ . '/../config/notify.php';
    require_once __DIR__ . '/reminders.php';
    try { run_reminders($pdo); } catch (Throwable $e) { /* never break the page */ }
}

$user      = current_user();
$staff     = is_staff();
$PAGE      = $PAGE      ?? 'dashboard';
$PAGE_TITLE = $PAGE_TITLE ?? 'Paw Prints';

// ------------------------------------------------------------------
// NOTIFICATIONS for the bell dropdown (role-aware).
// Staff see clinic-wide items; owners see only their own pets.
// Each entry: icon, title, meta line, href, and an urgency tone.
// These variables are header-specific so they never collide with a
// page variable (e.g. the dashboard's own $upcoming list).
// ------------------------------------------------------------------
$navNotifs = [];
$today     = date('Y-m-d');

// --- 1. Appointments today -----------------------------------------
if ($staff) {
    $nStmt = $pdo->prepare(
        "SELECT a.*, p.name AS pet, p.species, p.breed,
                o.first_name AS owner_first, o.middle_name AS owner_middle, o.last_name AS owner_last
         FROM appointments a
         JOIN patients p ON p.id = a.patient_id
         JOIN owners o ON o.id = p.owner_id
         WHERE a.status='Scheduled' AND a.appt_date = ? AND p.deleted_at IS NULL
         ORDER BY a.appt_time"
    );
    $nStmt->execute([$today]);
} else {
    $nStmt = $pdo->prepare(
        "SELECT a.*, p.name AS pet, p.species, p.breed,
                o.first_name AS owner_first, o.middle_name AS owner_middle, o.last_name AS owner_last
         FROM appointments a
         JOIN patients p ON p.id = a.patient_id
         JOIN owners o ON o.id = p.owner_id
         WHERE a.status='Scheduled' AND a.appt_date = ? AND p.owner_id = ? AND p.deleted_at IS NULL
         ORDER BY a.appt_time"
    );
    $nStmt->execute([$today, (int)$user['owner_id']]);
}
foreach ($nStmt->fetchAll() as $r) {
    $navNotifs[] = [
        'icon'   => 'cal',
        'tone'   => 'today',
        'owner'  => format_name_formal($r['owner_first'], $r['owner_middle'], $r['owner_last']),
        'pet'    => $r['pet'] . ' · ' . $r['species'] . ' · ' . $r['breed'],
        'detail' => $r['reason'] ?: 'Appointment',
        'meta'   => 'Today at ' . fmt_time($r['appt_time']),
        'href'   => 'appointments.php',
    ];
}

// --- 2. Upcoming appointments --------------------------------------
// Staff work clinic-wide, so a tight 7-day window keeps the list
// actionable. An owner only has a handful of appointments, and hiding
// one would look like the system lost it — so owners see ALL upcoming.
if ($staff) {
    $soon = date('Y-m-d', strtotime('+7 days'));
    $nStmt = $pdo->prepare(
        "SELECT a.*, p.name AS pet, p.species, p.breed,
                o.first_name AS owner_first, o.middle_name AS owner_middle, o.last_name AS owner_last
         FROM appointments a
         JOIN patients p ON p.id = a.patient_id
         JOIN owners o ON o.id = p.owner_id
         WHERE a.status='Scheduled' AND a.appt_date > ? AND a.appt_date <= ? AND p.deleted_at IS NULL
         ORDER BY a.appt_date, a.appt_time"
    );
    $nStmt->execute([$today, $soon]);
} else {
    $nStmt = $pdo->prepare(
        "SELECT a.*, p.name AS pet, p.species, p.breed,
                o.first_name AS owner_first, o.middle_name AS owner_middle, o.last_name AS owner_last
         FROM appointments a
         JOIN patients p ON p.id = a.patient_id
         JOIN owners o ON o.id = p.owner_id
         WHERE a.status='Scheduled' AND a.appt_date > ? AND p.owner_id = ? AND p.deleted_at IS NULL
         ORDER BY a.appt_date, a.appt_time"
    );
    $nStmt->execute([$today, (int)$user['owner_id']]);
}
foreach ($nStmt->fetchAll() as $r) {
    $navNotifs[] = [
        'icon'   => 'cal',
        'tone'   => 'soon',
        'owner'  => format_name_formal($r['owner_first'], $r['owner_middle'], $r['owner_last']),
        'pet'    => $r['pet'] . ' · ' . $r['species'] . ' · ' . $r['breed'],
        'detail' => $r['reason'] ?: 'Appointment',
        'meta'   => fmt_date($r['appt_date']) . ' at ' . fmt_time($r['appt_time']),
        'href'   => 'appointments.php',
    ];
}

// --- 3. Vaccinations due / overdue ---------------------------------
// Same reasoning as above: staff get a 30-day working window, owners
// see everything due for their own pets (plus anything overdue).
if ($staff) {
    $vacWindow = date('Y-m-d', strtotime('+30 days'));
    $nStmt = $pdo->prepare(
        "SELECT v.*, p.name AS pet, p.id AS pid, p.species, p.breed,
                o.first_name AS owner_first, o.middle_name AS owner_middle, o.last_name AS owner_last
         FROM vaccinations v
         JOIN patients p ON p.id = v.patient_id
         JOIN owners o ON o.id = p.owner_id
         WHERE v.next_due IS NOT NULL AND v.next_due <= ? AND p.deleted_at IS NULL AND v.deleted_at IS NULL
         ORDER BY v.next_due"
    );
    $nStmt->execute([$vacWindow]);
} else {
    $vacWindow = date('Y-m-d', strtotime('+90 days'));
    $nStmt = $pdo->prepare(
        "SELECT v.*, p.name AS pet, p.id AS pid, p.species, p.breed,
                o.first_name AS owner_first, o.middle_name AS owner_middle, o.last_name AS owner_last
         FROM vaccinations v
         JOIN patients p ON p.id = v.patient_id
         JOIN owners o ON o.id = p.owner_id
         WHERE v.next_due IS NOT NULL AND v.next_due <= ? AND p.owner_id = ? AND p.deleted_at IS NULL AND v.deleted_at IS NULL
         ORDER BY v.next_due"
    );
    $nStmt->execute([$vacWindow, (int)$user['owner_id']]);
}
foreach ($nStmt->fetchAll() as $r) {
    $overdue = $r['next_due'] < $today;
    $navNotifs[] = [
        'icon'   => 'syringe',
        'tone'   => $overdue ? 'overdue' : 'soon',
        'owner'  => format_name_formal($r['owner_first'], $r['owner_middle'], $r['owner_last']),
        'pet'    => $r['pet'] . ' · ' . $r['species'] . ' · ' . $r['breed'],
        'detail' => $r['name'],
        'meta'   => ($overdue ? 'Overdue since ' : 'Due ') . fmt_date($r['next_due']),
        'href'   => 'patient.php?id=' . (int)$r['pid'],
    ];
}

// --- 4. Patients under treatment (staff only) ----------------------
if ($staff) {
    foreach ($pdo->query(
        "SELECT p.id, p.name, p.species, p.breed,
                o.first_name AS owner_first, o.middle_name AS owner_middle, o.last_name AS owner_last
         FROM patients p
         JOIN owners o ON o.id = p.owner_id
         WHERE p.status='Under Treatment' AND p.deleted_at IS NULL ORDER BY p.name"
    )->fetchAll() as $r) {
        $navNotifs[] = [
            'icon'   => 'paw',
            'tone'   => 'care',
            'owner'  => format_name_formal($r['owner_first'], $r['owner_middle'], $r['owner_last']),
            'pet'    => $r['name'] . ' · ' . $r['species'] . ' · ' . $r['breed'],
            'detail' => 'Under treatment',
            'meta'   => 'Needs monitoring',
            'href'   => 'patient.php?id=' . (int)$r['id'],
        ];
    }
}

$navBellCount = count($navNotifs);

// Navigation items depend on role.
$nav = $staff ? [
    ['key' => 'dashboard',    'label' => 'Dashboard',    'href' => 'dashboard.php',    'icon' => 'grid'],
    ['key' => 'patients',     'label' => 'Patients',     'href' => 'patients.php',     'icon' => 'paw'],
    ['key' => 'appointments', 'label' => 'Appointments', 'href' => 'appointments.php', 'icon' => 'cal'],
    ['key' => 'reports',      'label' => 'Reports',      'href' => 'reports.php',      'icon' => 'chart'],
] : [
    ['key' => 'dashboard',    'label' => 'My Pets',      'href' => 'dashboard.php',    'icon' => 'paw'],
    ['key' => 'appointments', 'label' => 'Appointments', 'href' => 'appointments.php', 'icon' => 'cal'],
];

// Account-management links. The user-management screens appear only for
// staff or an owner who's been granted the permission; "My Account"
// (self-service) is available to everyone.
if (can_manage_users()) {
    $nav[] = ['key' => 'users', 'label' => 'User Accounts', 'href' => 'users.php', 'icon' => 'users'];
    $nav[] = ['key' => 'activity', 'label' => 'Activity Log',  'href' => 'activity.php', 'icon' => 'log'];
}
// The Archive is open to all clinic staff, since restoring a patient
// record is clinical work rather than account administration. Limited
// staff see patients and species only; deleted user accounts stay behind
// the admin permission.
if ($staff || can_manage_users()) {
    $nav[] = ['key' => 'archive', 'label' => 'Archive', 'href' => 'archive.php', 'icon' => 'trash'];
}
$nav[] = ['key' => 'account', 'label' => 'My Account', 'href' => 'account.php', 'icon' => 'user'];

function nav_icon($name) {
    $icons = [
        'grid'  => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'paw'   => '<circle cx="6" cy="9" r="1.6"/><circle cx="10" cy="6.5" r="1.6"/><circle cx="14" cy="6.5" r="1.6"/><circle cx="18" cy="9" r="1.6"/><path d="M8 15c0-2.5 1.8-4 4-4s4 1.5 4 4c0 1.8-1.6 2.6-4 2.6S8 16.8 8 15z"/>',
        'cal'   => '<rect x="3" y="4.5" width="18" height="16" rx="2"/><path d="M3 9h18M8 2.5v4M16 2.5v4"/>',
        'chart' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'syringe' => '<path d="m18 2 4 4M17 7l3-3M19 9 8.7 19.3a2.4 2.4 0 0 1-3.4 0l-.6-.6a2.4 2.4 0 0 1 0-3.4L15 5zM9 11l4 4M6 14l4 4M2 22l3-3"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9"/>',
        'user'  => '<circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-6 8-6s8 2 8 6"/>',
        'log'   => '<path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="M9 12h6M9 16h4"/>',
        'trash' => '<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M12 11v6M9 11v6M15 11v6"/>',
        'bell'  => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0"/>',
    ];
    $p = $icons[$name] ?? $icons['paw'];
    $strokeIcons = ['cal', 'chart', 'users', 'user', 'log', 'syringe', 'trash', 'bell'];
    $fill = in_array($name, $strokeIcons) ? 'none' : 'currentColor';
    $stroke = in_array($name, $strokeIcons) ? 'currentColor' : 'none';
    return "<svg width=\"19\" height=\"19\" viewBox=\"0 0 24 24\" fill=\"$fill\" stroke=\"$stroke\" "
         . "stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\">$p</svg>";
}

$flash       = get_flash();
$flashTarget = get_flash_target();   // null => show as a floating toast
$flashType   = get_flash_type();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($PAGE_TITLE) ?> · Paw Prints Veterinary Clinic</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600;9..144,700&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
<link rel="icon" type="image/png" sizes="32x32" href="assets/favicon-32.png">
<link rel="alternate icon" href="assets/favicon.ico">
<link rel="apple-touch-icon" href="assets/favicon-180.png">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body data-page="<?= e($PAGE ?? '') ?>">
<div class="vp-root">

  <!-- Sidebar -->
  <aside class="vp-side">
    <div class="vp-side-brand">
      <div class="vp-logo-badge sm"><?= species_icon('paw', 20) ?></div>
      <div class="vp-side-brand-text">
        <strong>Paw Prints</strong>
        <span>Patient Tracking</span>
      </div>
    </div>

    <nav class="vp-nav">
      <?php foreach ($nav as $item):
        $active = ($PAGE === $item['key']) || ($item['key'] === 'patients' && $PAGE === 'record');
      ?>
        <a href="<?= $item['href'] ?>" class="vp-nav-item <?= $active ? 'active' : '' ?>">
          <?= nav_icon($item['icon']) ?>
          <span><?= e($item['label']) ?></span>
          <?php if ($active): ?><span class="vp-nav-mark"></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="vp-side-foot">
      <a href="account.php" class="vp-user-chip <?= $staff ? 'staff' : 'owner' ?>" title="Manage your account">
        <div class="vp-user-avatar">
          <?php if ($staff): ?>
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.8 2.3v5.5a5 5 0 0 0 10 0V2.3M9.8 12.8v3a4 4 0 0 0 8 0v-1.5"/><circle cx="19" cy="12.5" r="2.2"/></svg>
          <?php else: ?>
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-6 8-6s8 2 8 6"/></svg>
          <?php endif; ?>
        </div>
        <div class="vp-user-meta">
          <?php
            // Falls back to the stored full name for any session created
            // before the split-name fields existed.
            $chipName = isset($user['first_name'])
                ? format_name_short($user['first_name'], $user['middle_name'] ?? '', $user['last_name'] ?? '')
                : ($user['full_name'] ?? '');
          ?>
          <strong><?= e($chipName) ?></strong>
          <span><?= e(role_label($user['role'] ?? '', $user['staff_title'] ?? null)) ?></span>
        </div>
      </a>
      <a href="logout.php" class="vp-logout">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
        Sign out
      </a>
    </div>
  </aside>

  <!-- Main column -->
  <main class="vp-main">
    <header class="vp-top">
      <div>
        <div class="vp-top-eyebrow"><?= $staff ? 'Staff workspace' : 'Owner portal' ?></div>
        <h1 class="vp-top-title"><?= e($PAGE_TITLE) ?></h1>
      </div>
      <div class="vp-top-right">
        <?php /* Printing lives on the patient chart page only (the "Print chart" button there). */ ?>
        <div class="vp-bell-wrap">
          <button type="button" class="vp-top-bell" id="vpBellBtn"
                  aria-label="Notifications<?= $navBellCount ? ' (' . $navBellCount . ' new)' : '' ?>"
                  aria-haspopup="true" aria-expanded="false" aria-controls="vpBellPanel">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0"/></svg>
            <?php if ($navBellCount > 0): ?><span class="vp-bell-dot"><?= $navBellCount > 9 ? '9+' : $navBellCount ?></span><?php endif; ?>
          </button>

          <div class="vp-bell-panel" id="vpBellPanel" role="dialog" aria-label="Notifications" hidden>
            <div class="vp-bell-head">
              <span>Notifications</span>
              <?php if ($navBellCount > 0): ?><span class="vp-bell-count"><?= $navBellCount ?></span><?php endif; ?>
            </div>

            <?php if (!$navNotifs): ?>
              <div class="vp-bell-empty">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                <p>You're all caught up.</p>
              </div>
            <?php else: ?>
              <div class="vp-bell-list">
                <?php foreach ($navNotifs as $n): ?>
                  <a class="vp-bell-item tone-<?= e($n['tone']) ?>" href="<?= e($n['href']) ?>">
                    <span class="vp-bell-ico"><?= nav_icon($n['icon']) ?></span>
                    <span class="vp-bell-txt">
                      <span class="vp-bell-title"><?= e($n['owner']) ?><span class="vp-bell-pet"><?= e($n['pet']) ?></span></span>
                      <span class="vp-bell-detail"><?= e($n['detail']) ?></span>
                      <span class="vp-bell-meta"><?= e($n['meta']) ?></span>
                    </span>
                  </a>
                <?php endforeach; ?>
              </div>
              <a class="vp-bell-foot" href="appointments.php">View all appointments</a>
            <?php endif; ?>
          </div>
        </div>

        <div class="vp-top-date">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
          <span id="vpClock"><?= date('M j, Y') ?> &middot; <?= date('g:i A') ?></span>
        </div>
      </div>
    </header>

    <div class="vp-content">
      <!-- Print-only letterhead (hidden on screen, shown on paper). -->
      <div class="vp-print-head" aria-hidden="true">
        <div class="vp-print-brand">
          <span class="vp-print-logo"><?= species_icon('paw', 24) ?></span>
          <div class="vp-print-brand-text">
            <strong><?= e(defined('CLINIC_NAME') ? CLINIC_NAME : 'Paw Prints Veterinary Clinic') ?></strong>
            <span>Bantug, Roxas, Isabela</span>
          </div>
        </div>
        <div class="vp-print-meta">
          <strong><?= e($PAGE_TITLE ?? 'Document') ?></strong>
          <span>Printed <span id="vpPrintDate"><?= date('M j, Y') . ' at ' . date('g:i A') ?></span></span>
        </div>
      </div>

      <div class="vp-stack">
<?php if (!empty($user['must_change_password']) && ($PAGE ?? '') !== 'account'): ?>
        <a href="account.php#password" class="vp-banner warn">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
          <span><strong>Action needed:</strong> please set a new password to secure your account.</span>
          <span class="vp-banner-go">Change password →</span>
        </a>
<?php endif; ?>
