<?php
// get_employee_monthly_report.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_logged_in() || !is_admin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$employee_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$year = isset($_GET['year']) ? (int)$_GET['year'] : 0;
$month = isset($_GET['month']) ? (int)$_GET['month'] : 0;

if ($employee_id <= 0 || $year <= 0 || $month <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
    exit;
}

try {
    // Verify employee exists
    $emp_stmt = $pdo->prepare("SELECT id, emp_id, first_name, last_name, email, role FROM employees WHERE id = ?");
    $emp_stmt->execute([$employee_id]);
    $employee = $emp_stmt->fetch();

    if (!$employee) {
        echo json_encode(['success' => false, 'message' => 'Employee not found.']);
        exit;
    }

    // Query timesheets with task titles
    $ts_stmt = $pdo->prepare("
        SELECT t.date, t.duration, t.description, t.status, tk.title as task_title 
        FROM timesheets t 
        LEFT JOIN tasks tk ON t.task_id = tk.id 
        WHERE t.user_id = ? AND YEAR(t.date) = ? AND MONTH(t.date) = ? 
        ORDER BY t.date ASC
    ");
    $ts_stmt->execute([$employee_id, $year, $month]);
    $timesheets = $ts_stmt->fetchAll();

    // Format timestamps and values
    foreach ($timesheets as &$ts) {
        $ts['formatted_date'] = date('M d, Y', strtotime($ts['date']));
        $ts['duration'] = (float)$ts['duration'];
    }

    echo json_encode([
        'success' => true,
        'employee' => $employee,
        'timesheets' => $timesheets
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
exit;
