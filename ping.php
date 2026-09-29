<?php
/* ============================================================
   Keep-alive ping
   ------------------------------------------------------------
   Called by the "Stay signed in" button on the idle warning.
   Including auth.php refreshes $_SESSION['last_activity'], which
   is all that's needed to reset the countdown. Returns 401 if the
   session has already expired so the page can redirect to login.
   ============================================================ */

require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit;
}

echo json_encode(['ok' => true, 'seconds_left' => idle_seconds_left()]);
