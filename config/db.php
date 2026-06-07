<?php
// Prevent direct access to config
if (basename($_SERVER['SCRIPT_FILENAME']) === 'db.php') {
    header("HTTP/1.1 403 Forbidden");
    exit;
}

// Set default timezone
date_default_timezone_set('Asia/Kolkata');

// Minimum shift duration in seconds before employees are allowed to clock out.
// - Local Testing: 120 seconds (2 minutes)
// - Production: 32400 seconds (9 hours)
define('MIN_SHIFT_SECONDS', 120);

// Secure session configuration
ini_set('session.use_only_cookies', 1);
ini_set('session.use_strict_mode', 1);

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'httponly' => true,
    'samesite' => 'Strict'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Calculate dynamic App Root URL path (e.g. /timecard/ or /)
$doc_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
$proj_dir = str_replace('\\', '/', dirname(__DIR__));
$app_root = str_replace($doc_root, '', $proj_dir);

// Cleanup slashes
if (substr($app_root, 0, 1) !== '/') {
    $app_root = '/' . $app_root;
}
if (substr($app_root, -1) !== '/') {
    $app_root .= '/';
}
define('APP_ROOT', $app_root);

// Session Timeout Handler (Disabled as requested)
/*
$timeout_duration = 900;
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout_duration)) {
        // Log activity timeout
        session_unset();
        session_destroy();
        header("Location: " . APP_ROOT . "auth/login?timeout=1");
        exit;
    }
    $_SESSION['last_activity'] = time();
}
*/

// Database Credentials (Update with hosting details if needed)
define('DB_HOST', getenv('DB_HOST') ?: 'sql200.ezyro.com');
define('DB_USER', getenv('DB_USER') ?: 'ezyro_41961501');
define('DB_PASS', getenv('DB_PASS') ?: 'Ags@2026');
define('DB_NAME', getenv('DB_NAME') ?: 'ezyro_41961501_timecard');

// Establish Database Connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}
