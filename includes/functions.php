<?php
/* ============================================================
   Shared helper functions
   ============================================================ */

/* ------------------------------------------------------------
   Data Privacy Act (RA 10173) — consent configuration.
   PRIVACY_VERSION identifies the edition of the Privacy Notice a
   person agreed to. Bump it (e.g. '2026-02') whenever the notice
   text in privacy.php changes so you can tell who agreed to which
   version and re-prompt if you ever need fresh consent.
   The contact details below appear on the notice and are the
   channel for data-subject requests (access, correction, deletion,
   withdrawal of consent).
   ------------------------------------------------------------ */
if (!defined('PRIVACY_VERSION'))       define('PRIVACY_VERSION', '2026-01');
if (!defined('PRIVACY_CONTACT_EMAIL')) define('PRIVACY_CONTACT_EMAIL', 'privacy@pawprints.vet');
if (!defined('PRIVACY_CONTACT_PHONE')) define('PRIVACY_CONTACT_PHONE', '(078) 000-0000');
if (!defined('CLINIC_ADDRESS'))        define('CLINIC_ADDRESS', 'Bantug, Roxas, Isabela');

/** The app build shown in the sidebar and on the sign-in page. Bump this
 *  when shipping a meaningful round of changes. */
if (!defined('APP_VERSION')) define('APP_VERSION', '1.0.0');

/** Escape output for safe HTML rendering. */
function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/** Format a Y-m-d date like "Jul 2, 2026". */
function fmt_date($date) {
    if (!$date) return '—';
    $ts = strtotime($date);
    return $ts ? date('M j, Y', $ts) : e($date);
}

/** Format a HH:MM:SS time like "10:00 AM". */
function fmt_time($time) {
    if (!$time) return '';
    $ts = strtotime($time);
    return $ts ? date('g:i A', $ts) : e($time);
}

/** True if a Y-m-d date string falls on today's date. */
function is_today($date) {
    if (!$date) return false;
    $ts = strtotime($date);
    return $ts && date('Y-m-d', $ts) === date('Y-m-d');
}

/** Time-of-day greeting: "Good morning" before noon, "Good afternoon"
 *  until 6pm, "Good evening" after that. */
function greeting() {
    $h = (int)date('G');
    if ($h < 12) return 'Good morning';
    if ($h < 18) return 'Good afternoon';
    return 'Good evening';
}

/** Human age from a birth date, e.g. "5y 4m" or "8 mo". */
function age_from_birth($birth) {
    if (!$birth) return '—';
    $b = new DateTime($birth);
    $n = new DateTime('today');
    $diff = $b->diff($n);
    if ($diff->y <= 0) return $diff->m . ' mo';
    return $diff->m > 0 ? "{$diff->y}y {$diff->m}m" : "{$diff->y}y";
}

/** Days from today until $date (negative = overdue). */
function days_until($date) {
    if (!$date) return null;
    $target = new DateTime($date);
    $today  = new DateTime('today');
    return (int)$today->diff($target)->format('%r%a');
}

/** Colour pair for a status pill. Returns [background, foreground]. */
function status_colors($status) {
    switch ($status) {
        case 'Active':
        case 'Scheduled':        return ['var(--ok-bg)',    'var(--ok-fg)'];
        case 'Under Treatment':
        case 'Pending':          return ['var(--warn-bg)',  'var(--warn-fg)'];
        case 'Completed':        return ['var(--muted-bg)', 'var(--muted-fg)'];
        case 'Declined':         return ['var(--rose-soft)','var(--rose)'];
        default:                 return ['var(--muted-bg)', 'var(--muted-fg)'];
    }
}

/**
 * The clinic's fixed appointment grid: one hour-long slot per start time,
 * Monday–Saturday, 9am–4pm with a noon break. Both the staff "Schedule
 * appointment" form and the owner "Request appointment" form book from
 * this same list, so a slot can only ever be held by one appointment.
 */
function appointment_slots() {
    return [
        '09:00:00' => '9:00 – 10:00 AM',
        '10:00:00' => '10:00 – 11:00 AM',
        '11:00:00' => '11:00 AM – 12:00 PM',
        '13:00:00' => '1:00 – 2:00 PM',
        '14:00:00' => '2:00 – 3:00 PM',
        '15:00:00' => '3:00 – 4:00 PM',
    ];
}

/** True if $dateStr (Y-m-d) falls on a Monday through Saturday. */
function is_valid_appt_weekday($dateStr) {
    $ts = strtotime((string)$dateStr);
    if ($ts === false) return false;
    $dow = (int)date('N', $ts); // 1 = Monday ... 7 = Sunday
    return $dow >= 1 && $dow <= 6;
}

/** Render a status pill. */
function status_pill($status, $large = false) {
    [$bg, $fg] = status_colors($status);
    $cls = 'vp-pill' . ($large ? ' lg' : '');
    return "<span class=\"$cls\" style=\"background:$bg;color:$fg\">"
         . "<span class=\"vp-pill-dot\" style=\"background:$fg\"></span>"
         . e($status) . "</span>";
}

/** Inline SVG icon for a species (falls back to a paw). */
function species_icon($species, $size = 18) {
    // Simple, self-contained line icons (no external icon library needed).
    $paw = '<circle cx="6" cy="9" r="1.6"/><circle cx="10" cy="6.5" r="1.6"/><circle cx="14" cy="6.5" r="1.6"/><circle cx="18" cy="9" r="1.6"/><path d="M8 15c0-2.5 1.8-4 4-4s4 1.5 4 4c0 1.8-1.6 2.6-4 2.6S8 16.8 8 15z"/>';
    return "<svg width=\"$size\" height=\"$size\" viewBox=\"0 0 24 24\" fill=\"currentColor\" "
         . "xmlns=\"http://www.w3.org/2000/svg\">$paw</svg>";
}

/** Flash-message helpers (a one-time toast shown after redirects). */
/**
 * Store a one-shot message for the next page load.
 *
 * $target lets a handler say which form the message belongs to. When a
 * target is given, the message is rendered as an inline alert INSIDE
 * that form (next to the fields the person needs to fix) instead of a
 * floating toast in the corner. Leave it null for page-level news
 * ("Patient added") that isn't tied to any one form.
 *
 * $type is 'error' or 'success' and only affects the inline styling.
 */
function set_flash($message, $target = null, $type = 'error') {
    $_SESSION['flash'] = $message;
    if ($target === null) {
        unset($_SESSION['flash_target'], $_SESSION['flash_type']);
    } else {
        $_SESSION['flash_target'] = $target;
        $_SESSION['flash_type']   = $type;
    }
}

/** Convenience wrapper: a message that belongs to a specific form. */
function set_form_error($target, $message) {
    set_flash($message, $target, 'error');
}

function get_flash() {
    if (!empty($_SESSION['flash'])) {
        $m = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $m;
    }
    return null;
}

/** Which form the pending flash belongs to (null = show as a toast). */
function get_flash_target() {
    if (!empty($_SESSION['flash_target'])) {
        $t = $_SESSION['flash_target'];
        unset($_SESSION['flash_target']);
        return $t;
    }
    return null;
}

function get_flash_type() {
    $t = $_SESSION['flash_type'] ?? 'error';
    unset($_SESSION['flash_type']);
    return $t;
}

/**
 * Render the inline alert for $formKey, if the pending flash belongs to
 * it. Call this inside the form, above the fields. Returns '' when the
 * message is for a different form (or is a page-level toast).
 */
function form_alert($formKey) {
    global $flash, $flashTarget, $flashType;
    if ($flash === null || $flashTarget !== $formKey) return '';
    $icon = $flashType === 'success'
        ? '<path d="M20 6 9 17l-5-5"/>'
        : '<circle cx="12" cy="12" r="10"/><path d="M12 8v5M12 16.5v.01"/>';
    return '<div class="vp-form-alert ' . e($flashType) . '" role="alert">'
         . '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
         . 'stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
         . $icon . '</svg><span>' . e($flash) . '</span></div>';
}

/** Redirect helper. */
function redirect($path) {
    header("Location: $path");
    exit;
}

/* ------------------------------------------------------------
   CSRF protection
   Every state-changing account form embeds csrf_field(); its
   handler calls verify_csrf() before doing anything. Relies on the
   session already being started (auth.php does this on every page
   and handler).
   ------------------------------------------------------------ */
function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}
function csrf_field() {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}
/** Validate the posted token; on failure flash a message and redirect. */
function verify_csrf($redirect = '../dashboard.php') {
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
        set_flash('Security check failed — please try that again.');
        redirect($redirect);
    }
}

/** Friendly label for a role. */
/**
 * Join first / middle / last into one display name.
 * The middle name is optional, so "Ramon" + "" + "Aquino" gives
 * "Ramon Aquino" rather than a double space.
 */
/**
 * SQL that assembles a display name from the first/middle/last columns.
 * There is no stored full_name column, so queries build it on the fly:
 *   SELECT ... , " . name_sql('u') . " AS full_name
 * CONCAT_WS skips NULLs, so a missing middle name doesn't leave a
 * double space.
 */
function name_sql($alias) {
    return "CONCAT_WS(' ', NULLIF({$alias}.first_name,''), NULLIF({$alias}.middle_name,''), NULLIF({$alias}.last_name,''))";
}

/**
 * Formal name for listings: "Santos, Maria C."
 * Last name first, then first name, then the middle initial. Falls back
 * gracefully when a part is missing:
 *   Santos, Maria C.   (all three)
 *   Santos, Maria      (no middle name)
 *   Santos             (surname only)
 */
/**
 * Short display name: "Maria C. Santos".
 * First name, middle initial, surname — the everyday way a name is
 * written, as opposed to format_name_formal() which is the
 * "Santos, Maria C." form used for scanning lists.
 */
function format_name_short($first, $middle, $last) {
    $first  = trim((string)$first);
    $middle = trim((string)$middle);
    $last   = trim((string)$last);

    $initials = '';
    if ($middle !== '') {
        // One initial per word, so compound middle names like
        // "Dela Cruz" read as "D. C." rather than being cut to "D.".
        $bits = [];
        foreach (preg_split('/\s+/u', $middle) as $part) {
            if ($part !== '') $bits[] = mb_strtoupper(mb_substr($part, 0, 1)) . '.';
        }
        $initials = implode(' ', $bits);
    }
    return implode(' ', array_filter([$first, $initials, $last], fn($v) => $v !== ''));
}

function format_name_formal($first, $middle, $last) {
    $first  = trim((string)$first);
    $middle = trim((string)$middle);
    $last   = trim((string)$last);

    if ($last === '') return build_full_name($first, $middle, $last);

    $tail = $first;
    if ($middle !== '') {
        // Multi-word middle names give one initial per word (e.g. "Dela
        // Cruz" -> "D. C."), which keeps compound Filipino names readable.
        $initials = [];
        foreach (preg_split('/\s+/u', $middle) as $part) {
            if ($part !== '') $initials[] = mb_strtoupper(mb_substr($part, 0, 1)) . '.';
        }
        if ($initials) $tail = trim($tail . ' ' . implode(' ', $initials));
    }
    return $tail === '' ? $last : $last . ', ' . $tail;
}

/** Same, but reading straight from a row with first/middle/last columns. */
function row_name_formal($row, $prefix = '') {
    return format_name_formal(
        $row[$prefix . 'first_name']  ?? '',
        $row[$prefix . 'middle_name'] ?? '',
        $row[$prefix . 'last_name']   ?? ''
    );
}

function build_full_name($first, $middle, $last) {
    $parts = array_filter([trim((string)$first), trim((string)$middle), trim((string)$last)],
                          fn($v) => $v !== '');
    return implode(' ', $parts);
}

/**
 * "Santos, Maria C." — used where a sorted, formal listing reads better.
 */
function name_last_first($first, $middle, $last) {
    $first  = trim((string)$first);
    $middle = trim((string)$middle);
    $last   = trim((string)$last);
    if ($last === '') return $first;
    $out = $last . ', ' . $first;
    if ($middle !== '') {
        $out .= ' ' . mb_strtoupper(mb_substr($middle, 0, 1)) . '.';
    }
    return $out;
}

/** Middle initial only, e.g. "C." — empty string when there is none. */
function middle_initial($middle) {
    $middle = trim((string)$middle);
    return $middle === '' ? '' : mb_strtoupper(mb_substr($middle, 0, 1)) . '.';
}

/** Validate one part of a person's name. Returns an error or null. */
function name_part_problem($value, $label, $required = true) {
    $value = trim((string)$value);
    if ($value === '') {
        return $required ? 'Please enter the ' . $label . '.' : null;
    }
    if (mb_strlen($value) > 60) {
        return ucfirst($label) . ' is too long (60 characters maximum).';
    }
    // Letters, spaces, hyphens, apostrophes and periods cover names like
    // "Dela Cruz", "O'Brien", "Jose Ma." and accented spellings.
    if (!preg_match("/^[\p{L}][\p{L} .'-]*$/u", $value)) {
        return ucfirst($label) . ' may only contain letters, spaces, and . \' -';
    }
    return null;
}

/**
 * Human label for an account's role. Clinic staff are further split into
 * Veterinarian and (non-vet) Staff via the optional $staffTitle; owners are
 * always "Pet owner". Passing only the role keeps older call sites working.
 */
function role_label($role, $staffTitle = null) {
    if ($role !== 'staff') return 'Pet owner';
    if ($staffTitle === 'veterinarian') return 'Veterinarian';
    if ($staffTitle === 'staff')        return 'Staff';
    return 'Clinic staff';   // staff account with no title set yet
}

/**
 * The clinic-staff titles offered in the account form. The array key is what
 * the form submits and what is stored in users.staff_title; the value is the
 * label shown to the user.
 */
function staff_titles() {
    return ['veterinarian' => 'Veterinarian', 'staff' => 'Staff'];
}

/**
 * Default value for an "Attending vet" / "Administered by" field, based on
 * who is filling the form. A veterinarian's own account pre-fills as
 * "Dr. <Last name>"; anyone else (non-vet staff) gets an empty string so
 * they type in whichever veterinarian actually attended. $user is the
 * session user array (or any row with role/staff_title/last_name).
 */
function default_attending_vet($user) {
    if (!$user || ($user['role'] ?? '') !== 'staff') return '';
    if (($user['staff_title'] ?? '') !== 'veterinarian') return '';
    $last = trim((string)($user['last_name'] ?? ''));
    return $last !== '' ? 'Dr. ' . $last : '';
}

/**
 * True if the users table has the staff_title column. Fresh installs and
 * migrated databases have it; this lets the account code stay working on a
 * database where the migration hasn't been run yet. The result is cached for
 * the request so the lookup only happens once.
 */
function has_staff_title_column($pdo) {
    static $has = null;
    if ($has !== null) return $has;
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'staff_title'");
        $has = $stmt && $stmt->fetch() !== false;
    } catch (Throwable $e) {
        $has = false;
    }
    return $has;
}

/**
 * True if the owners table has the privacy-consent columns
 * (privacy_consent_at / privacy_consent_ver). Lets the account code keep
 * working on a database where the consent migration hasn't been run yet.
 * Cached for the request.
 */
function has_privacy_consent_columns($pdo) {
    static $has = null;
    if ($has !== null) return $has;
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM owners LIKE 'privacy_consent_at'");
        $has = $stmt && $stmt->fetch() !== false;
    } catch (Throwable $e) {
        $has = false;
    }
    return $has;
}

/**
 * True if the users table has its own privacy-consent columns, used for the
 * first-login consent gate. Separate from the owners-table check above.
 * Cached for the request.
 */
function has_user_consent_columns($pdo) {
    static $has = null;
    if ($has !== null) return $has;
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'privacy_consent_at'");
        $has = $stmt && $stmt->fetch() !== false;
    } catch (Throwable $e) {
        $has = false;
    }
    return $has;
}

/**
 * Whether the given user still needs to agree to the CURRENT Privacy Notice.
 * True when they've never agreed, or agreed to an older notice version (so a
 * bump of PRIVACY_VERSION re-prompts everyone). $user is a row/session array
 * with a privacy_consent_ver key. If the app can't tell the version columns
 * exist, it returns false so the gate never traps anyone on an un-migrated DB.
 */
function needs_privacy_consent($user) {
    if (!$user) return false;
    // A key must be present to evaluate; on an un-migrated DB it's absent.
    if (!array_key_exists('privacy_consent_ver', $user)) return false;
    $agreed  = (string)($user['privacy_consent_ver'] ?? '');
    $current = defined('PRIVACY_VERSION') ? PRIVACY_VERSION : '';
    return $agreed !== $current;
}

/**
 * The security questions people choose from. Kept in one place so the
 * setup screen and account recovery always agree.
 */
function security_questions() {
    return [
        'What was the name of your first pet?',
        'What city were you born in?',
        "What is your mother's maiden name?",
        'What was the name of your elementary school?',
        'What is your favourite food?',
        'What was the make of your first vehicle?',
        'What is the name of your closest childhood friend?',
        'What street did you live on in third grade?',
    ];
}

/**
 * Pick three questions for a particular account.
 *
 * The choice is random per user, so two people setting up on the same
 * day don't get the same trio — but it's seeded from the user id, so
 * reloading the page or coming back later shows the SAME three rather
 * than reshuffling under them mid-form.
 *
 * They can still change any of the three from the dropdowns; this only
 * decides what is preselected.
 */
function security_questions_for($userId) {
    $bank = security_questions();
    $keys = array_keys($bank);

    // Deterministic Fisher-Yates shuffle seeded from the user id. This
    // spreads the picks evenly across the bank, which a plain hash-sort
    // does not — that skews towards whichever questions happen to hash
    // low, so some are suggested twice as often as others.
    $seed = crc32('pawprints-secq-' . $userId);
    mt_srand($seed);
    for ($i = count($keys) - 1; $i > 0; $i--) {
        $j = mt_rand(0, $i);
        [$keys[$i], $keys[$j]] = [$keys[$j], $keys[$i]];
    }
    mt_srand();   // don't leave the global generator seeded

    return [$bank[$keys[0]], $bank[$keys[1]], $bank[$keys[2]]];
}

/**
 * Normalise an answer before hashing or comparing: trimmed, lower-cased
 * and with runs of whitespace collapsed. Without this, "Manila " and
 * "manila" would count as different answers.
 */
function normalise_answer($answer) {
    return preg_replace('/\s+/u', ' ', trim(mb_strtolower((string)$answer)));
}

/** Hash a security answer for storage. */
function hash_answer($answer) {
    return password_hash(normalise_answer($answer), PASSWORD_DEFAULT);
}

/** Check a typed answer against a stored hash. */
function answer_matches($typed, $hash) {
    return $hash && password_verify(normalise_answer($typed), $hash);
}

/** Validate a login email address. Returns null if OK, or an error string. */
function login_email_problem($username) {
    // The email address IS the login identifier — there is no separate
    // username anywhere in the app.
    if ($username === '') {
        return 'An email address is required.';
    }
    if (strlen($username) > 60) {
        return 'That email address is too long (60 characters maximum).';
    }
    if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
        return 'Please enter a valid email address (e.g. name@email.com).';
    }
    return null;
}

/** Validate a proposed password. Returns null if OK, or an error string. */
/**
 * Strength rule for a password the user chooses themselves.
 * A safe password must resist guessing and brute-force attacks, so it
 * needs length plus all four character classes.
 */
function password_problem($pw) {
    if (strlen($pw) < 8) {
        return 'Password must be at least 8 characters.';
    }
    if (!preg_match('/[A-Z]/', $pw)) {
        return 'Password must contain at least one uppercase letter (A–Z).';
    }
    if (!preg_match('/[a-z]/', $pw)) {
        return 'Password must contain at least one lowercase letter (a–z).';
    }
    if (!preg_match('/[0-9]/', $pw)) {
        return 'Password must contain at least one number (0–9).';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $pw)) {
        return 'Password must contain at least one special character (e.g. ! @ # $ % ^ & * ( ) _ + - = ?).';
    }
    return null;
}

/**
 * The individual strength checks, for the live meter on the password
 * form. Returns [label => bool passed].
 */
function password_checks($pw) {
    return [
        'At least 8 characters'        => strlen($pw) >= 8,
        'An uppercase letter (A–Z)'    => (bool)preg_match('/[A-Z]/', $pw),
        'A lowercase letter (a–z)'     => (bool)preg_match('/[a-z]/', $pw),
        'A number (0–9)'               => (bool)preg_match('/[0-9]/', $pw),
        'A special character (! @ # $ …)' => (bool)preg_match('/[^A-Za-z0-9]/', $pw),
    ];
}

/**
 * Verify a typed password against the account's stored hash. Supports
 * the seeded "plain:" format used by demo accounts before their first
 * successful login, as well as normal password_hash() bcrypt hashes.
 *
 * Shared by every form that must reconfirm identity before saving a
 * change to the account — the password change form, and (per account
 * policy) the profile and security-question forms on My Account.
 */
function verify_current_password($typed, $stored) {
    $typed = (string)$typed;
    if ($typed === '' || !$stored) return false;
    if (strpos((string)$stored, 'plain:') === 0) {
        return hash_equals(substr((string)$stored, 6), $typed);
    }
    return password_verify($typed, (string)$stored);
}

/**
 * Rule for a TEMPORARY password issued by staff (the new user's phone
 * digits only). It is deliberately looser than password_problem() because
 * the account is always flagged must_change_password, so this value
 * only survives until the user's first sign-in. The full strength
 * rules still apply the moment they choose their own password.
 */
function temp_password_problem($pw) {
    $digits = preg_replace('/\D/', '', $pw);
    if (strlen($digits) < 7) {
        return 'The phone number needs at least 7 digits to be used as a temporary password.';
    }
    return null;
}

/** Strip a phone number down to the digits used as the temp password. */
/**
 * Store phone numbers in one consistent shape: 0917-555-0142.
 * The browser mask does this as you type, but a pasted value or a
 * request with JavaScript disabled still has to be tidied here.
 * Anything that isn't 11 digits is kept as typed rather than mangled.
 */
function format_phone($phone) {
    $d = preg_replace('/\D/', '', (string)$phone);
    if (strlen($d) !== 11) return trim((string)$phone);
    return substr($d, 0, 4) . '-' . substr($d, 4, 3) . '-' . substr($d, 7);
}

function temp_password_from_phone($phone) {
    return preg_replace('/\D/', '', (string)$phone);
}

/** Human-readable label + tone for an audit action code. */
function audit_action_meta($action) {
    $map = [
        'login'            => ['Signed in',            'teal'],
        'logout'           => ['Signed out',           'muted'],
        'login_failed'     => ['Failed sign-in',       'rose'],
        'login_denied'     => ['Sign-in blocked',      'rose'],
        'profile_update'   => ['Profile updated',      'teal'],
        'password_change'  => ['Password changed',     'amber'],
        'security_update'  => ['Security settings',    'amber'],
        'user_create'      => ['Account created',      'teal'],
        'owner_create'     => ['Pet owner added',     'teal'],
        'species_create'   => ['Species added',       'teal'],
        'species_delete'   => ['Species removed',     'rose'],
        'patient_create'   => ['Patient added',       'teal'],
        'patient_update'   => ['Patient edited',      'teal'],
        'patient_delete'   => ['Patient archived',    'rose'],
        'patient_restore'  => ['Patient restored',    'teal'],
        'visit_create'     => ['Visit logged',        'teal'],
        'visit_update'     => ['Visit edited',        'teal'],
        'visit_delete'     => ['Visit archived',      'rose'],
        'visit_restore'    => ['Visit restored',      'teal'],
        'vaccine_add'      => ['Vaccine recorded',    'teal'],
        'vaccine_update'   => ['Vaccine updated',     'teal'],
        'vaccine_delete'   => ['Vaccine archived',    'rose'],
        'vaccine_restore'  => ['Vaccine restored',    'teal'],
        'appt_create'      => ['Appointment booked',  'teal'],
        'appt_request'     => ['Appointment requested', 'amber'],
        'appt_approve'     => ['Appointment approved', 'teal'],
        'appt_decline'     => ['Appointment declined', 'rose'],
        'appt_complete'    => ['Appointment done',    'teal'],
        'user_restore'     => ['Account restored',    'teal'],
        'species_restore'  => ['Species restored',    'teal'],
        'purge'            => ['Permanently deleted', 'rose'],
        'recovery_start'   => ['Recovery started',   'amber'],
        'recovery_failed'  => ['Recovery failed',    'rose'],
        'reminders_run'    => ['Reminders sent',    'teal'],
        'user_update'      => ['Account edited',       'teal'],
        'user_activate'    => ['Account activated',    'teal'],
        'user_deactivate'  => ['Account deactivated',  'rose'],
        'user_delete'      => ['Account deleted',      'rose'],
        'permission_grant' => ['Permission granted',   'amber'],
        'permission_revoke'=> ['Permission revoked',   'amber'],
        'privacy_consent'  => ['Privacy consent',      'teal'],
        'privacy_withdraw' => ['Consent withdrawn',    'amber'],
        'account_seeded'   => ['Account created',      'muted'],
    ];
    return $map[$action] ?? [ucfirst(str_replace('_', ' ', $action)), 'muted'];
}

/** Badge for an audit action (coloured pill). */
function audit_pill($action) {
    [$label, $tone] = audit_action_meta($action);
    $tones = [
        'teal'  => ['var(--ok-bg)',    'var(--ok-fg)'],
        'amber' => ['var(--warn-bg)',  'var(--warn-fg)'],
        'rose'  => ['var(--rose-soft)','var(--rose)'],
        'muted' => ['var(--muted-bg)', 'var(--muted-fg)'],
    ];
    [$bg, $fg] = $tones[$tone] ?? $tones['muted'];
    return '<span class="vp-pill" style="background:' . $bg . ';color:' . $fg . '">'
         . '<span class="vp-pill-dot" style="background:' . $fg . '"></span>'
         . e($label) . '</span>';
}

/** "time ago" helper for audit rows, e.g. "3h ago". */
function time_ago($datetime) {
    if (!$datetime) return '';
    $ts = strtotime($datetime);
    if (!$ts) return e($datetime);
    $diff = time() - $ts;
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return floor($diff / 60) . 'm ago';
    if ($diff < 86400)  return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j, Y', $ts);
}
