<?php
// Secure AJAX Timesheet details retrieval
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$timesheet_id = (int)($_GET['id'] ?? 0);

if (!$timesheet_id) {
    header('HTTP/1.1 400 Bad Request');
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid Timesheet ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT t.id as timesheet_id, t.date as work_date, t.duration as work_duration, 
               t.description as work_details, t.status as timesheet_status, t.task_id,
               e.emp_id, CONCAT(e.first_name, ' ', e.last_name) as employee_name,
               tk.title as task_title, tk.description as task_description, 
               tk.priority as task_priority, tk.deadline as task_deadline, 
               tk.estimated_duration as task_estimated_duration
        FROM timesheets t
        JOIN employees e ON t.user_id = e.id
        LEFT JOIN tasks tk ON t.task_id = tk.id
        WHERE t.id = ?
    ");
    $stmt->execute([$timesheet_id]);
    $details = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$details) {
        header('HTTP/1.1 404 Not Found');
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Timesheet record not found']);
        exit;
    }

    // Fetch completion updates if there is an associated task
    $details['update_details'] = null;
    if ($details['task_id']) {
        $update_stmt = $pdo->prepare("SELECT * FROM task_updates WHERE task_id = ?");
        $update_stmt->execute([$details['task_id']]);
        $update = $update_stmt->fetch(PDO::FETCH_ASSOC);
        $details['update_details'] = $update ?: null;
    }

    header('Content-Type: application/json');
    echo json_encode($details);
} catch (PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
