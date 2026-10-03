<?php
/* ============================================================
   Marks the signed-in user's notifications as read — called in the
   background when they open the bell. Anything logged after this
   moment counts as unread again on their next page load.
   ============================================================ */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
verify_csrf('../dashboard.php');

try {
    $pdo->prepare("UPDATE users SET notifications_seen_at = NOW() WHERE id = ?")
        ->execute([(int)current_user()['id']]);
} catch (Throwable $e) {
    // Column not migrated yet — nothing to record.
}
http_response_code(204);
