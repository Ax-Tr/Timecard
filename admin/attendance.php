<?php
// Admin Attendance Logs & Reports Page
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$error = '';
$employees = [];
$attendance_logs = [];

// Default date range: current month to today
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$selected_user_id = isset($_GET['employee_id']) && (int)$_GET['employee_id'] > 0 ? (int)$_GET['employee_id'] : 0;

try {
    // 1. Fetch active employees list for filter dropdown
    $emp_stmt = $pdo->query("SELECT id, emp_id, first_name, last_name FROM employees WHERE status = 'active' ORDER BY emp_id ASC");
    $employees = $emp_stmt->fetchAll();

    // 2. Build Query based on filters
    $query_str = "
        SELECT a.*, e.emp_id, e.first_name, e.last_name, e.email 
        FROM attendance a 
        JOIN employees e ON a.user_id = e.id 
        WHERE a.date >= :start_date AND a.date <= :end_date
    ";
    
    if ($selected_user_id > 0) {
        $query_str .= " AND e.id = :user_id";
    }
    
    $query_str .= " ORDER BY a.clock_in DESC, a.id DESC";
    
    $log_stmt = $pdo->prepare($query_str);
    $log_stmt->bindValue(':start_date', $start_date);
    $log_stmt->bindValue(':end_date', $end_date);
    
    if ($selected_user_id > 0) {
        $log_stmt->bindValue(':user_id', $selected_user_id, PDO::PARAM_INT);
    }
    
    $log_stmt->execute();
    $attendance_logs = $log_stmt->fetchAll();

} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// 3. Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="attendance_report_' . $start_date . '_to_' . $end_date . '.csv"');
    
    $output = fopen('php://output', 'w');
    // Header columns
    fputcsv($output, ['Employee ID', 'Name', 'Email Address', 'Date', 'Clock In', 'Clock Out', 'Duration (Hours)', 'Status']);
    
    foreach ($attendance_logs as $log) {
        $name = $log['first_name'] . ' ' . $log['last_name'];
        $clock_in = date('Y-m-d h:i A', strtotime($log['clock_in']));
        $clock_out = $log['clock_out'] ? date('Y-m-d h:i A', strtotime($log['clock_out'])) : 'N/A';
        $duration = $log['duration'] !== null ? number_format($log['duration'], 2) : '0.00';
        $status = $log['clock_out'] ? 'Completed' : 'Currently Clocked In';
        
        fputcsv($output, [
            $log['emp_id'],
            $name,
            $log['email'],
            $log['date'],
            $clock_in,
            $clock_out,
            $duration,
            $status
        ]);
    }
    fclose($output);
    exit;
}

$page_title = 'Attendance Reports';
require_once __DIR__ . '/../includes/header.php';
?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<!-- Main Content Area -->
<main class="main-content d-flex flex-column flex-grow-1">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h1 class="h3 mb-0 text-gray-800">Attendance Reports & Logs</h1>
            <a href="?export=csv&<?php echo http_build_query($_GET); ?>" class="btn btn-success">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export to CSV
            </a>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 small"><?php echo e($error); ?></div>
        <?php endif; ?>

        <!-- Filters Form -->
        <div class="card mb-4 shadow-sm border-0">
            <div class="card-body">
                <form action="" method="GET" class="d-flex flex-wrap gap-3 align-items-end">
                    <div style="flex: 1 1 200px; max-width: 280px;">
                        <label class="form-label small fw-semibold">Employee</label>
                        <div class="dropdown searchable-dropdown w-100">
                            <button class="btn btn-outline-secondary dropdown-toggle w-100 text-start form-select text-truncate" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="employeeDropdownBtn">
                                <?php 
                                    $selected_label = 'All Employees';
                                    if ($selected_user_id > 0) {
                                        foreach ($employees as $emp) {
                                            if ($emp['id'] == $selected_user_id) {
                                                $selected_label = '[' . $emp['emp_id'] . '] ' . $emp['first_name'] . ' ' . $emp['last_name'];
                                                break;
                                            }
                                        }
                                    }
                                    echo e($selected_label);
                                ?>
                            </button>
                            <div class="dropdown-menu w-100 p-2" style="max-height: 300px; overflow-y: auto;">
                                <input type="text" class="form-control mb-2 dropdown-search-input" placeholder="Type to search..." autocomplete="off">
                                <div class="dropdown-options-list">
                                    <button type="button" class="dropdown-item text-start<?php echo $selected_user_id == 0 ? ' active' : ''; ?>" data-value="0">All Employees</button>
                                    <?php foreach ($employees as $emp): ?>
                                        <button type="button" class="dropdown-item text-start<?php echo $selected_user_id == $emp['id'] ? ' active' : ''; ?>" data-value="<?php echo $emp['id']; ?>">
                                            [<?php echo e($emp['emp_id']); ?>] <?php echo e($emp['first_name'] . ' ' . $emp['last_name']); ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <input type="hidden" name="employee_id" class="dropdown-hidden-input" value="<?php echo (int)$selected_user_id; ?>">
                        </div>
                    </div>

                    <div style="flex: 1.5 1 280px; max-width: 340px;">
                        <label class="form-label small fw-semibold">Date Range</label>
                        <div class="input-group">
                            <input type="date" class="form-control" name="start_date" value="<?php echo e($start_date); ?>" required>
                            <span class="input-group-text">to</span>
                            <input type="date" class="form-control" name="end_date" value="<?php echo e($end_date); ?>" required>
                        </div>
                    </div>

                    <div>
                        <button type="submit" class="btn btn-primary fw-semibold px-4">
                            <i class="bi bi-funnel-fill me-1"></i> Apply Filters
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Attendance Logs Table -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-transparent py-3">
                <h6 class="m-0 fw-bold text-primary">Attendance Logs</h6>
            </div>
            <div class="card-body">
                <?php if (empty($attendance_logs)): ?>
                    <p class="text-muted text-center py-5">No attendance records found matching the filters.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size: 0.875rem;">
                            <thead class="table-light">
                                <tr>
                                    <th>Employee ID</th>
                                    <th>Employee Name</th>
                                    <th>Date</th>
                                    <th>Clock-In Time</th>
                                    <th>Clock-Out Time</th>
                                    <th>Hours Worked</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attendance_logs as $log): ?>
                                    <tr>
                                        <td><span class="fw-semibold text-primary"><?php echo e($log['emp_id']); ?></span></td>
                                        <td><?php echo e($log['first_name'] . ' ' . $log['last_name']); ?></td>
                                        <td><?php echo e($log['date']); ?></td>
                                        <td><span class="text-dark"><i class="bi bi-box-arrow-in-right text-success me-1"></i><?php echo date('h:i A', strtotime($log['clock_in'])); ?></span></td>
                                        <td>
                                            <?php if ($log['clock_out']): ?>
                                                <span class="text-dark"><i class="bi bi-box-arrow-right text-danger me-1"></i><?php echo date('h:i A', strtotime($log['clock_out'])); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($log['duration'] !== null): ?>
                                                <span class="badge bg-primary-subtle text-primary fw-semibold"><?php echo number_format($log['duration'], 2); ?> hrs</span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-dark fw-semibold">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($log['clock_out']): ?>
                                                <span class="badge bg-success-subtle text-success">Completed</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning-subtle text-warning"><i class="bi bi-arrow-repeat spin me-1"></i>Clocked In</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
