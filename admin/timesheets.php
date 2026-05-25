<?php
// Admin Timesheet Management
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

// Handler for Approval AJAX/Post Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'approve') {
    $timesheet_id = (int)($_POST['id'] ?? 0);
    $token = $_POST['csrf_token'] ?? '';
    
    if (verify_csrf($token)) {
        try {
            $stmt = $pdo->prepare("UPDATE timesheets SET status = 'approved' WHERE id = ?");
            $stmt->execute([$timesheet_id]);
            
            // Get employee log and task title for notification
            $emp_stmt = $pdo->prepare("SELECT e.emp_id, t.user_id, t.date, t.duration, t.description, tk.title as task_title 
                                       FROM timesheets t 
                                       JOIN employees e ON t.user_id = e.id 
                                       LEFT JOIN tasks tk ON t.task_id = tk.id 
                                       WHERE t.id = ?");
            $emp_stmt->execute([$timesheet_id]);
            $t_info = $emp_stmt->fetch();
            
            log_activity($_SESSION['user_id'], "Approved timesheet for employee {$t_info['emp_id']} on date {$t_info['date']} ({$t_info['duration']} hrs)");
            
            // Send notification to the employee
            $task_display = !empty($t_info['task_title']) ? $t_info['task_title'] : (strlen($t_info['description']) > 30 ? substr($t_info['description'], 0, 27) . '...' : $t_info['description']);
            $noti_msg = "Your timesheet for {$task_display} has been approved by Admin.";
            add_notification($t_info['user_id'], $noti_msg);
            
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Timesheet approved successfully.']);
                exit;
            }
            $success = 'Timesheet approved successfully.';
        } catch (PDOException $e) {
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                exit;
            }
            $error = 'Database error: ' . $e->getMessage();
        }
    } else {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Security token invalid.']);
            exit;
        }
        $error = 'Security token invalid (CSRF).';
    }
}

// Filters Setup
$emp_filter = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$week_filter = isset($_GET['week']) ? trim($_GET['week']) : '';
$month_filter = isset($_GET['month']) ? trim($_GET['month']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';

$where = ["1 = 1"];
$params = [];

if ($emp_filter > 0) {
    $where[] = "t.user_id = ?";
    $params[] = $emp_filter;
}

if ($date_from !== '') {
    $where[] = "t.date >= ?";
    $params[] = $date_from;
}

if ($date_to !== '') {
    $where[] = "t.date <= ?";
    $params[] = $date_to;
}

if ($week_filter !== '') {
    if (preg_match('/^(\d{4})-W(\d{2})$/', $week_filter, $matches)) {
        $year = (int)$matches[1];
        $week = (int)$matches[2];
        // MySQL WEEK(date, 1) starts week on Monday
        $where[] = "YEAR(t.date) = ? AND WEEK(t.date, 1) = ?";
        $params[] = $year;
        $params[] = $week;
    }
}

if ($month_filter !== '') {
    if (preg_match('/^(\d{4})-(\d{2})$/', $month_filter, $matches)) {
        $where[] = "YEAR(t.date) = ? AND MONTH(t.date) = ?";
        $params[] = (int)$matches[1];
        $params[] = (int)$matches[2];
    }
}

if ($status_filter !== '') {
    $where[] = "t.status = ?";
    $params[] = $status_filter;
}

$where_sql = implode(" AND ", $where);

// Export CSV Action Handler
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    try {
        $sql = "
            SELECT t.id, e.emp_id, CONCAT(e.first_name, ' ', e.last_name) as employee_name, 
                   t.date, t.duration, tk.title as task_title, t.description, t.status 
            FROM timesheets t 
            JOIN employees e ON t.user_id = e.id 
            LEFT JOIN tasks tk ON t.task_id = tk.id 
            WHERE $where_sql 
            ORDER BY t.date DESC, e.emp_id ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="timesheets_export_' . date('Ymd_His') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Timesheet ID', 'Emp ID', 'Employee Name', 'Date', 'Worked Hours', 'Task Title', 'Work Description', 'Status']);
        
        foreach ($rows as $row) {
            fputcsv($output, [
                $row['id'],
                $row['emp_id'],
                $row['employee_name'],
                $row['date'],
                $row['duration'],
                $row['task_title'] ?: 'N/A',
                $row['description'],
                ucfirst($row['status'])
            ]);
        }
        fclose($output);
        exit;
    } catch (PDOException $e) {
        $error = 'Export failed due to database error.';
    }
}

// Pagination Setup
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

try {
    // 1. Fetch active employees list for filter dropdown
    $employees_list = $pdo->query("SELECT id as user_id, emp_id, first_name, last_name FROM employees WHERE status = 'active' ORDER BY emp_id ASC")->fetchAll();

    // 2. Fetch counts
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM timesheets t WHERE $where_sql");
    $count_stmt->execute($params);
    $total_rows = $count_stmt->fetchColumn();
    $total_pages = ceil($total_rows / $limit);

    // 3. Fetch current page rows
    $select_stmt = $pdo->prepare("
        SELECT t.*, e.emp_id, e.first_name, e.last_name, tk.title as task_title 
        FROM timesheets t 
        JOIN employees e ON t.user_id = e.id 
        LEFT JOIN tasks tk ON t.task_id = tk.id 
        WHERE $where_sql 
        ORDER BY t.date DESC, e.emp_id ASC 
        LIMIT " . (int)$offset . ", " . (int)$limit
    );
    $select_stmt->execute($params);
    $timesheets = $select_stmt->fetchAll();

} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

$page_title = 'Timesheets Management';
require_once __DIR__ . '/../includes/header.php';
?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<!-- Main Content Area -->
<main class="main-content d-flex flex-column flex-grow-1">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h1 class="h3 mb-0 text-gray-800">Timesheets Review</h1>
            <a href="?export=csv&<?php echo http_build_query($_GET); ?>" class="btn btn-success">
                <i class="bi bi-filetype-csv me-1"></i> Export to CSV
            </a>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 small"><?php echo e($error); ?></div>
        <?php endif; ?>
        <?php if (isset($success)): ?>
            <div class="alert alert-success py-2 small"><?php echo e($success); ?></div>
        <?php endif; ?>

        <!-- Filters Form -->
        <div class="card mb-4">
            <div class="card-body">
                <form action="" method="GET" class="d-flex flex-wrap gap-3 align-items-end">
                    <div style="flex: 1 1 200px; max-width: 280px;">
                        <label class="form-label small fw-semibold">Employee</label>
                        <div class="dropdown searchable-dropdown w-100">
                            <button class="btn btn-outline-secondary dropdown-toggle w-100 text-start form-select" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="employeeDropdownBtn">
                                <?php 
                                    $selected_label = 'All Employees';
                                    if ($emp_filter > 0) {
                                        foreach ($employees_list as $emp) {
                                            if ($emp['user_id'] == $emp_filter) {
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
                                    <button type="button" class="dropdown-item text-start<?php echo $emp_filter == 0 ? ' active' : ''; ?>" data-value="0">All Employees</button>
                                    <?php foreach ($employees_list as $emp): ?>
                                        <button type="button" class="dropdown-item text-start<?php echo $emp_filter == $emp['user_id'] ? ' active' : ''; ?>" data-value="<?php echo $emp['user_id']; ?>">
                                            [<?php echo e($emp['emp_id']); ?>] <?php echo e($emp['first_name'] . ' ' . $emp['last_name']); ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <input type="hidden" name="employee_id" class="dropdown-hidden-input" value="<?php echo (int)$emp_filter; ?>">
                        </div>
                    </div>

                    <div style="flex: 1.5 1 280px; max-width: 340px;">
                        <label class="form-label small fw-semibold">Date Range</label>
                        <div class="input-group">
                            <input type="date" class="form-control" name="date_from" value="<?php echo e($date_from); ?>">
                            <span class="input-group-text">to</span>
                            <input type="date" class="form-control" name="date_to" value="<?php echo e($date_to); ?>">
                        </div>
                    </div>

                    <div style="flex: 0.8 1 140px; max-width: 170px;">
                        <label class="form-label small fw-semibold">Specific Week</label>
                        <input type="week" class="form-control" name="week" value="<?php echo e($week_filter); ?>">
                    </div>

                    <div style="flex: 0.8 1 140px; max-width: 170px;">
                        <label class="form-label small fw-semibold">Specific Month</label>
                        <input type="month" class="form-control" name="month" value="<?php echo e($month_filter); ?>">
                    </div>

                    <div style="flex: 1 1 150px; max-width: 190px;">
                        <label class="form-label small fw-semibold">Approval Status</label>
                        <select class="form-select" name="status">
                            <option value="">All Statuses</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending Approval</option>
                            <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        </select>
                    </div>

                    <div class="d-flex gap-2 ms-auto py-1">
                        <button type="submit" class="btn btn-secondary px-3 fw-medium">Apply Filters</button>
                        <a href="timesheets" class="btn btn-outline-secondary px-3">Reset Filters</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Timesheets Table -->
        <div class="card mb-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.9rem;">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Employee</th>
                                <th>Associated Task</th>
                                <th>Task Details</th>
                                <th>Duration</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($timesheets)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">No timesheet records found matching criteria.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($timesheets as $row): ?>
                                    <tr>
                                        <td><span class="fw-medium"><?php echo e($row['date']); ?></span></td>
                                        <td><?php echo e($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                        <td>
                                            <?php if ($row['task_title']): ?>
                                                <span class="fw-medium">
                                                    <i class="bi bi-tag-fill me-1 text-muted"></i><?php echo e($row['task_title']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted small">None (Manual Entry)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 view-timesheet-btn" data-timesheet-id="<?php echo $row['id']; ?>" style="font-size: 0.8rem;">
                                                <i class="bi bi-info-circle-fill me-1"></i>View
                                            </button>
                                        </td>
                                        <td><span class="badge bg-primary-subtle text-primary fs-6"><?php echo number_format($row['duration'], 1); ?> hrs</span></td>
                                        <td>
                                            <span class="badge <?php echo $row['status'] === 'approved' ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning'; ?> timesheet-status-badge">
                                                <?php echo ucfirst(e($row['status'])); ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($row['status'] === 'pending'): ?>
                                                <button class="btn btn-success btn-sm approve-timesheet-btn" data-id="<?php echo $row['id']; ?>">
                                                    <i class="bi bi-check-lg me-1"></i> Approve
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted small"><i class="bi bi-check-circle-fill text-success"></i> Cleared</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation" class="d-flex justify-content-center">
                <ul class="pagination pagination-sm shadow-sm">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Previous</a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</main>

<!-- Task Details Modal -->
<div class="modal fade" id="taskDetailsModal" tabindex="-1" aria-labelledby="taskDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="taskDetailsModalLabel">
                    <i class="bi bi-card-checklist text-primary me-2"></i>Task Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="taskDetailsContent">
                <!-- Dynamically loaded via AJAX in main.js -->
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = "<?php echo csrf_token(); ?>";

    // Dynamic AJAX approval
    const approveBtns = document.querySelectorAll('.approve-timesheet-btn');
    approveBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.getAttribute('data-id');
            const rowCell = this.parentNode;
            const statusBadge = rowCell.parentNode.querySelector('.timesheet-status-badge');
            
            toggleLoader(true);

            const params = new URLSearchParams();
            params.append('action', 'approve');
            params.append('id', id);
            params.append('csrf_token', csrfToken);

            fetch('timesheets', {
                method: 'POST',
                body: params,
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.json())
            .then(data => {
                toggleLoader(false);
                if (data.success) {
                    showToast(data.message, 'success');
                    // Update visual state
                    statusBadge.className = 'badge bg-success-subtle text-success timesheet-status-badge';
                    statusBadge.textContent = 'Approved';
                    rowCell.innerHTML = '<span class="text-muted small"><i class="bi bi-check-circle-fill text-success"></i> Cleared</span>';
                } else {
                    showToast(data.message, 'danger');
                }
            })
            .catch(err => {
                toggleLoader(false);
                showToast('Failed to approve timesheet record', 'danger');
            });
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
