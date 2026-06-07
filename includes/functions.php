<?php
// Core helper and security functions

// Clean outputs to prevent XSS
function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// Check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Check if user is admin
function is_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Enforce authentication
function require_login() {
    if (!is_logged_in()) {
        header("Location: " . APP_ROOT . "auth/login");
        exit;
    }
}

// Enforce admin privileges
function require_admin() {
    require_login();
    if (!is_admin()) {
        header("HTTP/1.1 403 Forbidden");
        echo "403 Forbidden - Admin Access Only";
        exit;
    }
}

// Generate CSRF Token
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF Token
function verify_csrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Sanitize inputs
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Log activities to the database
function log_activity($user_id, $action) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?, ?)");
        $stmt->execute([$user_id, $action]);
    } catch (PDOException $e) {
        // Fail silently or log error locally to avoid stopping user flow
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

// Add notifications for admin or employee
function add_notification($user_id, $message) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $stmt->execute([$user_id, $message]);
    } catch (PDOException $e) {
        error_log("Failed to add notification: " . $e->getMessage());
    }
}

// Format worked duration into "X hr Y mins Z secs" format
function format_worked_duration($clock_in, $clock_out) {
    if (!$clock_in || !$clock_out) return '-';
    $seconds = strtotime($clock_out) - strtotime($clock_in);
    if ($seconds <= 0) return '0 secs';
    
    $h = floor($seconds / 3600);
    $m = floor(($seconds % 3600) / 60);
    $s = $seconds % 60;
    
    $parts = [];
    $parts[] = "{$h} hr" . ($h != 1 ? 's' : '');
    $parts[] = "{$m} min" . ($m != 1 ? 's' : '');
    $parts[] = "{$s} sec" . ($s != 1 ? 's' : '');
    
    return implode(' ', $parts);
}
