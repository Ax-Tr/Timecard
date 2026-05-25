<?php
// export_employee_monthly_csv.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$employee_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$year = isset($_GET['year']) ? (int)$_GET['year'] : 0;
$month = isset($_GET['month']) ? (int)$_GET['month'] : 0;

if ($employee_id <= 0 || $year <= 0 || $month <= 0) {
    die('Invalid parameters.');
}

try {
    // Fetch employee details
    $emp_stmt = $pdo->prepare("SELECT emp_id, first_name, last_name FROM employees WHERE id = ?");
    $emp_stmt->execute([$employee_id]);
    $employee = $emp_stmt->fetch();

    if (!$employee) {
        die('Employee not found.');
    }

    // Fetch detailed timesheets for this employee, month, and year
    $ts_stmt = $pdo->prepare("
        SELECT t.date, t.duration, t.description, t.status, tk.title as task_title 
        FROM timesheets t 
        LEFT JOIN tasks tk ON t.task_id = tk.id 
        WHERE t.user_id = ? AND YEAR(t.date) = ? AND MONTH(t.date) = ? 
        ORDER BY t.date ASC
    ");
    $ts_stmt->execute([$employee_id, $year, $month]);
    $timesheets = $ts_stmt->fetchAll();

    // Setup headers
    $filename = "timesheet_report_" . $employee['emp_id'] . "_" . $year . "_" . str_pad($month, 2, '0', STR_PAD_LEFT) . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    
    // Title/Metadata Block
    fputcsv($output, ['Rhine System - Monthly Timesheet Report']);
    fputcsv($output, ['Employee Name', $employee['first_name'] . ' ' . $employee['last_name']]);
    fputcsv($output, ['Employee ID', $employee['emp_id']]);
    fputcsv($output, ['Report Month', date('F Y', mktime(0, 0, 0, $month, 1, $year))]);
    fputcsv($output, ['Generated On', date('Y-m-d H:i:s')]);
    fputcsv($output, []); // Empty spacing row

    // Table Headers
    fputcsv($output, ['Work Date', 'Associated Task', 'Work Details / Description', 'Duration (Hours)', 'Status']);

    // Table Content
    $total_hours = 0;
    foreach ($timesheets as $row) {
        $total_hours += (float)$row['duration'];
        fputcsv($output, [
            $row['date'],
            !empty($row['task_title']) ? $row['task_title'] : 'Manual Entry',
            $row['description'],
            number_format($row['duration'], 1),
            ucfirst($row['status'])
        ]);
    }
    
    fputcsv($output, []);
    fputcsv($output, ['Total Worked Hours', number_format($total_hours, 1) . ' hrs']);
    
    fclose($output);
    exit;

} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}
