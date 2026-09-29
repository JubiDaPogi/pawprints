<?php
/* ============================================================
   Database connection (PDO)
   ------------------------------------------------------------
   These are the DEFAULT XAMPP settings. On a fresh XAMPP install
   the MySQL user is "root" with an empty password, so you usually
   don't need to change anything here.

   If you set a MySQL password, put it in $DB_PASS below.
   ============================================================ */

// Nothing else in the app sets a timezone, so PHP would otherwise fall
// back to the server's default (usually UTC) — throwing off the
// dashboard greeting, the header clock, and every other date()/time()
// call. The clinic operates on Philippine time, so fix it here, once,
// before anything else runs.
date_default_timezone_set('Asia/Manila');

$DB_HOST = 'localhost';
$DB_NAME = 'pawprints_db';
$DB_USER = 'root';
$DB_PASS = '';          // <-- default XAMPP has no password
$DB_CHARSET = 'utf8mb4';

$dsn = "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=$DB_CHARSET";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
} catch (PDOException $e) {
    // Friendly message instead of a raw stack trace
    die(
        '<div style="font-family:sans-serif;max-width:640px;margin:60px auto;padding:28px;'
        . 'border:1px solid #e6e0d4;border-radius:14px;background:#fff;color:#2b1c1c;line-height:1.6">'
        . '<h2 style="margin-top:0;color:#8b1e24">Can\'t connect to the database</h2>'
        . '<p>The app could not reach the <b>pawprints_db</b> database. Check that:</p>'
        . '<ul>'
        . '<li><b>MySQL</b> is running in the XAMPP Control Panel.</li>'
        . '<li>You imported <b>database.sql</b> through phpMyAdmin.</li>'
        . '<li>The settings in <code>config/database.php</code> match your MySQL setup.</li>'
        . '</ul>'
        . '<p style="color:#8b7c7c;font-size:14px">Technical detail: ' . htmlspecialchars($e->getMessage()) . '</p>'
        . '</div>'
    );
}
