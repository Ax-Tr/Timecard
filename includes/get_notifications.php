<?php
// Endpoint to fetch unread notifications
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$limit = 5;

try {
    // 1. Fetch unread count
    if (is_admin()) {
        $count_stmt = $pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id IS NULL AND is_read = 0");
    } else {
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $count_stmt->execute([$user_id]);
    }
    $unread_count = (int)$count_stmt->fetchColumn();

    // 2. Fetch latest 5 unread notifications
    if (is_admin()) {
        $noti_stmt = $pdo->prepare("SELECT id, message, created_at FROM notifications WHERE user_id IS NULL AND is_read = 0 ORDER BY created_at DESC LIMIT ?");
        $noti_stmt->bindValue(1, $limit, PDO::PARAM_INT);
    } else {
        $noti_stmt = $pdo->prepare("SELECT id, message, created_at FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT ?");
        $noti_stmt->bindValue(1, $user_id, PDO::PARAM_INT);
        $noti_stmt->bindValue(2, $limit, PDO::PARAM_INT);
    }
    $noti_stmt->execute();
    $unread_notifications = $noti_stmt->fetchAll();

    // Format timestamps nicely
    foreach ($unread_notifications as &$noti) {
        $noti['formatted_time'] = date('M d, g:i a', strtotime($noti['created_at']));
    }

    echo json_encode([
        'success' => true,
        'unread_count' => $unread_count,
        'notifications' => $unread_notifications
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
exit;
