<?php
// Admin Reports Page
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');

$error = '';

try {
    // 1. Fetch Quick Summaries for the selected Month/Year
    // A. Total Hours Logged
    $hours_stmt = $pdo->prepare("SELECT SUM(duration) FROM timesheets WHERE YEAR(date) = ? AND MONTH(date) = ?");
    $hours_stmt->execute([$year, $month]);
    $monthly_total_hours = $hours_stmt->fetchColumn() ?? 0;

    // B. Total Tasks Completed
    $tasks_stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE status = 'completed' AND YEAR(deadline) = ? AND MONTH(deadline) = ?");
    $tasks_stmt->execute([$year, $month]);
    $monthly_completed_tasks = $tasks_stmt->fetchColumn() ?? 0;

    // C. Active Employees Who Logged Hours
    $active_eng_stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM timesheets WHERE YEAR(date) = ? AND MONTH(date) = ?");
    $active_eng_stmt->execute([$year, $month]);
    $active_logging_count = $active_eng_stmt->fetchColumn() ?? 0;

    // 2. Fetch Employee-wise summary table for the selected Month/Year
    $summary_stmt = $pdo->prepare("
        SELECT e.id, e.emp_id, e.first_name, e.last_name, e.email, 
               COALESCE(SUM(t.duration), 0) as total_hours,
               COUNT(DISTINCT t.id) as days_logged
        FROM employees e
        LEFT JOIN timesheets t ON e.id = t.user_id AND YEAR(t.date) = ? AND MONTH(t.date) = ?
        WHERE e.status = 'active'
        GROUP BY e.id
        ORDER BY total_hours DESC, e.emp_id ASC
    ");
    $summary_stmt->execute([$year, $month]);
    $employee_summary = $summary_stmt->fetchAll();

} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// Handle Report Export to CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="monthly_report_' . $year . '_' . str_pad($month, 2, '0', STR_PAD_LEFT) . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Employee ID', 'Name', 'Email Address', 'Total Logged Hours', 'Days Logged']);
    
    foreach ($employee_summary as $row) {
        fputcsv($output, [
            $row['emp_id'],
            $row['first_name'] . ' ' . $row['last_name'],
            $row['email'],
            number_format($row['total_hours'], 1),
            $row['days_logged']
        ]);
    }
    fclose($output);
    exit;
}

$page_title = 'Monthly Reports';
require_once __DIR__ . '/../includes/header.php';
?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<!-- Main Content Area -->
<main class="main-content d-flex flex-column flex-grow-1">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h1 class="h3 mb-0 text-gray-800">Monthly Reports & Analytics</h1>
            <a href="?export=csv&year=<?php echo $year; ?>&month=<?php echo $month; ?>" class="btn btn-success">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export Month to CSV
            </a>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 small"><?php echo e($error); ?></div>
        <?php endif; ?>

        <!-- Month Selection Filter -->
        <div class="card mb-4">
            <div class="card-body">
                <form action="" method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Select Year</label>
                        <select class="form-select" name="year">
                            <?php 
                            $current_year = (int)date('Y');
                            for ($y = $current_year; $y >= $current_year - 5; $y--): 
                            ?>
                                <option value="<?php echo $y; ?>" <?php echo $year === $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Select Month</label>
                        <select class="form-select" name="month">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php echo $month === $m ? 'selected' : ''; ?>>
                                    <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-secondary w-100 fw-medium">Generate Report</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Quick Stats Cards Row -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card h-100 bg-primary bg-opacity-10 border-0 p-3 text-center">
                    <span class="text-uppercase small text-primary fw-bold mb-1">Monthly Total Hours</span>
                    <h2 class="fw-bold mb-0 text-primary"><?php echo number_format($monthly_total_hours, 1); ?></h2>
                    <small class="text-muted mt-1">Logged by active employees</small>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card h-100 bg-success bg-opacity-10 border-0 p-3 text-center">
                    <span class="text-uppercase small text-success fw-bold mb-1">Monthly Completed Tasks</span>
                    <h2 class="fw-bold mb-0 text-success"><?php echo $monthly_completed_tasks; ?></h2>
                    <small class="text-muted mt-1">Tasks completed by deadline</small>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card h-100 bg-warning bg-opacity-10 border-0 p-3 text-center">
                    <span class="text-uppercase small text-warning fw-bold mb-1">Active Logging Employees</span>
                    <h2 class="fw-bold mb-0 text-warning"><?php echo $active_logging_count; ?></h2>
                    <small class="text-muted mt-1">Employees with active hours</small>
                </div>
            </div>
        </div>

        <!-- Productivity Summary Table -->
        <div class="card">
            <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold text-primary">Monthly Employee Performance (<?php echo date('F Y', mktime(0, 0, 0, $month, 1, $year)); ?>)</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.9rem;">
                        <thead class="table-light">
                            <tr>
                                <th>Emp ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Days Logged</th>
                                <th>Total Logged Hours</th>
                                <th>Avg Hours/Day</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($employee_summary)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">No active employees to display.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($employee_summary as $row): ?>
                                    <tr>
                                        <td><code><?php echo e($row['emp_id']); ?></code></td>
                                        <td><span class="fw-semibold"><?php echo e($row['first_name'] . ' ' . $row['last_name']); ?></span></td>
                                        <td><?php echo e($row['email']); ?></td>
                                        <td><?php echo $row['days_logged']; ?> days</td>
                                        <td>
                                            <span class="badge bg-primary-subtle text-primary fs-6">
                                                <?php echo number_format($row['total_hours'], 1); ?> hrs
                                            </span>
                                        </td>
                                        <td>
                                            <span class="fw-medium">
                                                <?php 
                                                echo $row['days_logged'] > 0 
                                                    ? number_format($row['total_hours'] / $row['days_logged'], 1) . ' hrs'
                                                    : '0.0 hrs';
                                                ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <a href="print_employee_report.php?id=<?php echo $row['id']; ?>&year=<?php echo $year; ?>&month=<?php echo $month; ?>" 
                                               target="_blank" 
                                               class="btn btn-outline-danger btn-sm fw-medium">
                                                <i class="bi bi-file-earmark-pdf me-1"></i> PDF Report
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
