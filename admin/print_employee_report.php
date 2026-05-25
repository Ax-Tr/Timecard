<?php
// print_employee_report.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$employee_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');

$error = '';
$employee = null;
$timesheets = [];

if ($employee_id <= 0) {
    $error = 'Invalid employee identifier.';
} else {
    try {
        // Fetch employee details
        $emp_stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
        $emp_stmt->execute([$employee_id]);
        $employee = $emp_stmt->fetch();

        if (!$employee) {
            $error = 'Employee not found.';
        } else {
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
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timesheet Report - <?php echo $employee ? e($employee['first_name'] . ' ' . $employee['last_name']) : 'Error'; ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
            color: #333;
        }
        .report-card {
            background-color: #fff;
            border: 0;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            padding: 3rem;
            margin-top: 2rem;
            margin-bottom: 2rem;
        }
        .report-header {
            border-bottom: 2px solid #eaedf1;
            padding-bottom: 2rem;
            margin-bottom: 2rem;
        }
        .logo-placeholder {
            font-weight: 800;
            font-size: 1.5rem;
            letter-spacing: -0.5px;
            color: #0d6efd;
        }
        .info-label {
            font-size: 0.8rem;
            text-transform: uppercase;
            font-weight: 700;
            color: #8898aa;
            margin-bottom: 0.25rem;
        }
        .info-value {
            font-size: 0.95rem;
            font-weight: 600;
            color: #32325d;
        }
        .stats-box {
            background-color: #f6f9fc;
            border-radius: 8px;
            padding: 1.25rem;
            text-align: center;
            border: 1px solid #e9ecef;
        }
        .stats-box h3 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0;
            color: #0d6efd;
        }
        .stats-box .label {
            font-size: 0.75rem;
            text-transform: uppercase;
            font-weight: 600;
            color: #8898aa;
            margin-top: 0.25rem;
        }
        @media print {
            body {
                background-color: #fff;
                color: #000;
            }
            .report-card {
                box-shadow: none;
                padding: 0;
                margin: 0;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger mt-5 text-center">
                    <i class="bi bi-exclamation-triangle-fill fs-2 d-block mb-2"></i>
                    <h5>Error Generating Report</h5>
                    <p class="mb-0"><?php echo e($error); ?></p>
                    <a href="reports.php" class="btn btn-primary btn-sm mt-3 no-print">Back to Reports</a>
                </div>
            <?php else: ?>
                <div class="no-print d-flex justify-content-between align-items-center mt-4 mb-2">
                    <a href="reports.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Back to Reports
                    </a>
                    <button onclick="window.print()" class="btn btn-primary">
                        <i class="bi bi-printer me-1"></i> Print/Save PDF
                    </button>
                </div>

                <div class="report-card">
                    <!-- Report Header -->
                    <div class="report-header d-flex justify-content-between align-items-start">
                        <div>
                            <div class="logo-placeholder mb-2">
                                <i class="bi bi-clock-history me-1 text-primary"></i>TIMECARD
                            </div>
                            <h4 class="fw-bold text-dark">Monthly Timesheet Report</h4>
                            <p class="text-muted mb-0">For the period of <?php echo date('F Y', mktime(0, 0, 0, $month, 1, $year)); ?></p>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill fw-semibold">
                                CONFIRMED LOG
                            </span>
                            <div class="text-muted small mt-2">Generated on: <?php echo date('M d, Y'); ?></div>
                        </div>
                    </div>

                    <!-- Employee Details -->
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="info-label">Employee Name</div>
                            <div class="info-value"><?php echo e($employee['first_name'] . ' ' . $employee['last_name']); ?></div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="info-label">Employee ID</div>
                            <div class="info-value"><code><?php echo e($employee['emp_id']); ?></code></div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="info-label">Email Address</div>
                            <div class="info-value"><?php echo e($employee['email']); ?></div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="info-label">Designation/Role</div>
                            <div class="info-value text-capitalize"><?php echo e($employee['role']); ?></div>
                        </div>
                    </div>

                    <!-- Summary Metrics -->
                    <?php
                    $total_hours = 0;
                    $dates = array_unique(array_column($timesheets, 'date'));
                    $days_logged = count($dates);
                    foreach ($timesheets as $ts) {
                        $total_hours += (float)$ts['duration'];
                    }
                    $avg_hours = $days_logged > 0 ? $total_hours / $days_logged : 0;
                    ?>
                    <div class="row g-3 mb-5">
                        <div class="col-md-4">
                            <div class="stats-box">
                                <h3><?php echo number_format($total_hours, 1); ?> hrs</h3>
                                <div class="label">Total Logged Hours</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stats-box">
                                <h3><?php echo $days_logged; ?> days</h3>
                                <div class="label">Total Days Logged</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stats-box">
                                <h3><?php echo number_format($avg_hours, 1); ?> hrs</h3>
                                <div class="label">Average Hours / Day</div>
                            </div>
                        </div>
                    </div>

                    <!-- Detailed Table -->
                    <h5 class="fw-bold mb-3 text-dark">Timesheet Entries Breakdown</h5>
                    <div class="table-responsive">
                        <table class="table table-striped align-middle" style="font-size: 0.85rem;">
                            <thead class="table-dark">
                                <tr>
                                    <th>Date</th>
                                    <th>Associated Task</th>
                                    <th>Work Details / Description</th>
                                    <th>Hours</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($timesheets)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">No timesheet entries found for this period.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($timesheets as $ts): ?>
                                        <tr>
                                            <td class="fw-semibold" style="white-space: nowrap;"><?php echo date('M d, Y', strtotime($ts['date'])); ?></td>
                                            <td>
                                                <?php if (!empty($ts['task_title'])): ?>
                                                    <span class="text-primary fw-medium"><?php echo e($ts['task_title']); ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted italic">Manual Entry</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-secondary"><?php echo e($ts['description']); ?></td>
                                            <td class="fw-bold"><?php echo number_format($ts['duration'], 1); ?> hrs</td>
                                            <td>
                                                <span class="badge <?php echo $ts['status'] === 'approved' ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning'; ?> text-capitalize">
                                                    <?php echo e($ts['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Approval Section -->
                    <div class="row mt-5 pt-5">
                        <div class="col-6 text-center">
                            <div style="border-bottom: 1px solid #ccc; width: 200px; margin: 0 auto 10px auto;"></div>
                            <div class="small text-muted fw-bold">Employee Signature</div>
                            <div class="small text-muted">Date: ____/____/_______</div>
                        </div>
                        <div class="col-6 text-center">
                            <div style="border-bottom: 1px solid #ccc; width: 200px; margin: 0 auto 10px auto;"></div>
                            <div class="small text-muted fw-bold">Authorized Approver</div>
                            <div class="small text-muted">Date: ____/____/_______</div>
                        </div>
                    </div>

                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
window.onload = function() {
    <?php if (empty($error)): ?>
    window.print();
    <?php endif; ?>
}
</script>
</body>
</html>
