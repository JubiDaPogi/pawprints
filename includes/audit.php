<?php
/* ============================================================
   Activity logging
   ------------------------------------------------------------
   record_audit() appends one row to the activity_log table for any
   account-related action (logins, profile edits, password
   changes, user management, etc.). Logging must never break the
   action it is recording, so all errors are swallowed.

   The actor defaults to the currently logged-in user, but can be
   passed explicitly (used for login attempts, where the session
   user isn't set yet or the attempt failed).
   ============================================================ */

function record_audit(
    PDO $pdo,
    string $action,
    ?int $target_id = null,
    ?string $target_label = null,
    ?string $details = null,
    ?array $actor = null
): void {
    if ($actor === null && function_exists('current_user')) {
        $actor = current_user();
    }
    $actor_id  = isset($actor['id']) ? (int)$actor['id'] : null;
    $actor_user = $actor['username'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;

    // Trim to the column widths so an over-long detail can't error.
    if ($details !== null)      $details      = mb_substr($details, 0, 255);
    if ($target_label !== null) $target_label = mb_substr($target_label, 0, 120);

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO activity_log
                (actor_id, actor_username, action, target_id, target_label, details, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$actor_id, $actor_user, $action, $target_id, $target_label, $details, $ip]);
    } catch (Throwable $e) {
        // Never let activity logging interrupt the real operation.
    }
}
