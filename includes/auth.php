<?php
/* ============================================================
   Authentication guard
   ------------------------------------------------------------
   Include this at the very top of every protected page. It starts
   the session and redirects to the login page if nobody is signed
   in. Use require_staff() on pages only staff may open.
   ============================================================ */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// The idle check below writes to the activity log, and this file is
// included before audit.php on most pages — so pull it in here.
require_once __DIR__ . '/audit.php';

/* ------------------------------------------------------------------
   Automatic sign-out after a period of inactivity.
   Clinic computers are often shared and left unattended, so a session
   that sits idle is closed rather than left open on patient records.
   The countdown resets on every page load or action.
------------------------------------------------------------------ */
const IDLE_TIMEOUT   = 900;   // 15 minutes of no activity
const IDLE_WARN_AFTER = 780;  // warn the user 2 minutes before

/**
 * Ends an idle session. Called automatically below on every request.
 * Returns true when the session was expired by this call.
 */
function enforce_idle_timeout() {
    if (!isset($_SESSION['user'])) return false;

    $now  = time();
    $last = $_SESSION['last_activity'] ?? $now;

    if ($now - $last > IDLE_TIMEOUT) {
        // Record it before the session is destroyed, so the activity log
        // shows why the person was signed out.
        if (function_exists('record_audit') && isset($GLOBALS['pdo'])) {
            record_audit($GLOBALS['pdo'], 'logout', (int)$_SESSION['user']['id'],
                $_SESSION['user']['username'], 'Signed out automatically after inactivity');
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                      $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        session_start();
        $_SESSION['login_error'] = 'You were signed out automatically after a period of inactivity.';
        return true;
    }

    $_SESSION['last_activity'] = $now;
    return false;
}

/** Seconds of idle time left before the session expires. */
function idle_seconds_left() {
    if (!isset($_SESSION['user'])) return 0;
    $last = $_SESSION['last_activity'] ?? time();
    return max(0, IDLE_TIMEOUT - (time() - $last));
}

// Run the check on every request that includes this file.
if (enforce_idle_timeout()) {
    $depth = (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/actions/') !== false) ? '../' : '';
    header('Location: ' . $depth . 'index.php');
    exit;
}

/** True if someone is logged in. */
function is_logged_in() {
    return isset($_SESSION['user']);
}

/** The current user array, or null. */
function current_user() {
    return $_SESSION['user'] ?? null;
}

/** True if the current user is clinic staff (full access). */
function is_staff() {
    return isset($_SESSION['user']) && $_SESSION['user']['role'] === 'staff';
}

/** Force login — redirect visitors who aren't signed in. */
function require_login() {
    if (!is_logged_in()) {
        redirect('index.php');
    }
}

/** True if the current user is a pet owner. */
function is_owner() {
    return isset($_SESSION['user']) && $_SESSION['user']['role'] === 'owner';
}

/**
 * True if the current user may use the user-management module.
 * Staff always may. An owner may ONLY if they've been explicitly
 * granted the permission (users.can_manage_users = 1) — this is the
 * "unless additional permissions are explicitly granted" rule.
 */
/**
 * True if the current user may use the user-management module
 * (User Accounts + Activity Log).
 *
 * This is a per-account permission, not a role. Clinic staff get it by
 * default when their account is created, but it can be switched off to
 * give a staff member the clinic workspace WITHOUT admin features —
 * useful for assistants and receptionists who handle patients but
 * shouldn't create or delete logins. An owner never has it unless it is
 * explicitly granted.
 *
 * The last active administrator can't have it revoked (see
 * actions/update_user.php), so the clinic can never lock itself out.
 */
function can_manage_users() {
    $u = current_user();
    if (!$u) return false;
    return !empty($u['can_manage_users']);
}

/**
 * Every account must have its three recovery questions answered before
 * using the system. Called from header.php, so it covers every page
 * that renders the shell.
 */
function require_security_setup() {
    $u = current_user();
    if (!$u) return;
    if (!empty($u['security_set'])) return;

    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    // Don't bounce the setup page itself, or logout.
    if (in_array($script, ['security_setup.php', 'logout.php'], true)) return;

    redirect('security_setup.php');
}

/**
 * Data Privacy Act: the Privacy Notice is shown at a person's first sign-in
 * and they must agree before using the system. Placed in header.php ahead of
 * the security-questions gate, so a brand-new user sees the notice first.
 * Re-prompts automatically if PRIVACY_VERSION is bumped. Never traps anyone
 * on a database where the consent columns don't exist.
 */
function require_privacy_consent() {
    $u = current_user();
    if (!$u) return;
    if (!needs_privacy_consent($u)) return;

    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    // Don't bounce the consent page itself, the notice, or logout.
    if (in_array($script, ['privacy_consent.php', 'privacy.php', 'logout.php'], true)) return;

    redirect('privacy_consent.php');
}

/**
 * Guard for pages that clinic staff may use, plus any owner who has been
 * granted the user-management permission. Used by the Archive, where
 * restoring a patient is clinical work rather than administration.
 */
function require_staff_or_manager() {
    require_login();
    if (!is_staff() && !can_manage_users()) {
        set_flash('You do not have access to that page.');
        redirect('dashboard.php');
    }
}

/** True if this user is staff but without admin (user-management) rights. */
function is_limited_staff() {
    $u = current_user();
    return $u && $u['role'] === 'staff' && empty($u['can_manage_users']);
}

/** Staff-only pages call this. Owners get bounced to their dashboard. */
function require_staff() {
    require_login();
    if (!is_staff()) {
        redirect('dashboard.php');
    }
}

/**
 * Guard for the user-management module. Allows staff and any owner
 * who has been granted can_manage_users; everyone else is bounced.
 */
function require_manage_users() {
    require_login();
    if (!can_manage_users()) {
        set_flash('You do not have permission to manage user accounts.');
        redirect('dashboard.php');
    }
}

/**
 * Authorisation check for acting on a specific account. A user may
 * always act on their OWN account; acting on someone else's account
 * additionally requires the manage-users permission.
 */
function can_act_on_user($target_user_id) {
    $u = current_user();
    if (!$u) return false;
    if ((int)$u['id'] === (int)$target_user_id) return true;
    return can_manage_users();
}
