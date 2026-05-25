<?php
// Employee Timesheet Log History
$page_title = 'My Timesheet Logs';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Secure page access
if (is_admin()) {
    header("Location: " . APP_ROOT . "admin/dashboard");
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$month_filter = isset($_GET['month']) ? trim($_GET['month']) : '';

$where = ["user_id = ?"];
$params = [$user_id];

if ($search !== '') {
    $where[] = "description LIKE ?";
    $params[] = "%$search%";
}

if ($month_filter !== '') {
    if (preg_match('/^(\d{4})-(\d{2})$/', $month_filter, $matches)) {
        $where[] = "YEAR(date) = ? AND MONTH(date) = ?";
        $params[] = (int)$matches[1];
        $params[] = (int)$matches[2];
    }
}

$where_sql = implode(" AND ", $where);

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

try {
    // 1. Calculate Total Worked Hours based on active filter
    $total_hours_stmt = $pdo->prepare("SELECT SUM(duration) FROM timesheets WHERE $where_sql");
    $total_hours_stmt->execute($params);
    $total_hours = $total_hours_stmt->fetchColumn() ?? 0;

    // 2. Fetch counts for pagination
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM timesheets WHERE $where_sql");
    $count_stmt->execute($params);
    $total_rows = $count_stmt->fetchColumn();
    $total_pages = ceil($total_rows / $limit);

    // 3. Fetch logs
    $select_stmt = $pdo->prepare("
        SELECT t.*, tk.title as task_title 
        FROM timesheets t 
        LEFT JOIN tasks tk ON t.task_id = tk.id 
        WHERE $where_sql 
        ORDER BY t.date DESC, t.id DESC 
        LIMIT " . (int)$offset . ", " . (int)$limit
    );
    $select_stmt->execute($params);
    $logs = $select_stmt->fetchAll();

} catch (PDOException $e) {
    error_log("my-timesheets error: " . $e->getMessage());
    $error = "Error loading timesheet logs.";
}

require_once __DIR__ . '/../includes/header.php';
?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<!-- Main Content Area -->
<main class="main-content d-flex flex-column flex-grow-1">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h1 class="h3 mb-1 text-gray-800">My Timesheet History</h1>
                <p class="text-muted small mb-0">Browse and search all your logged hours.</p>
            </div>
            <a href="add-timesheet" class="btn btn-primary"><i class="bi bi-calendar-plus-fill me-1"></i> Log Hours</a>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 small"><?php echo e($error); ?></div>
        <?php endif; ?>

        <!-- Filters & Stats row -->
        <div class="row g-3 mb-4">
            <!-- Filter Form (Left) -->
            <div class="col-xl-8">
                <div class="card h-100">
                    <div class="card-body">
                        <form action="" method="GET" class="row g-3">
                            <div class="col-md-5">
                                <label class="form-label small fw-semibold">Search Details</label>
                                <input type="text" class="form-control" name="search" placeholder="Search in description..." value="<?php echo e($search); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Filter Month</label>
                                <input type="month" class="form-control" name="month" value="<?php echo e($month_filter); ?>">
                            </div>
                            <div class="col-md-3 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-secondary w-100 fw-medium">Filter</button>
                                <a href="my-timesheets" class="btn btn-outline-secondary w-50">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Stats Box (Right) -->
            <div class="col-xl-4">
                <div class="card h-100 bg-success bg-opacity-10 border-0 d-flex flex-column justify-content-center p-3 text-center">
                    <span class="text-uppercase small text-success fw-bold mb-1">Total Worked Hours</span>
                    <h2 class="fw-bold mb-0 text-success"><?php echo number_format($total_hours, 1); ?> <span class="fs-5 fw-normal text-muted">hrs</span></h2>
                    <small class="text-muted mt-1">Based on active filters above</small>
                </div>
            </div>
        </div>

        <!-- Timesheet History Table -->
        <div class="card mb-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.9rem;">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Task Title</th>
                                <th>Worked Hours</th>
                                <th>Description</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">No timesheet records found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><span class="fw-medium"><?php echo e($log['date']); ?></span></td>
                                        <td>
                                            <?php if ($log['task_title']): ?>
                                                <span class="fw-medium">
                                                    <i class="bi bi-tag-fill me-1 text-muted"></i><?php echo e($log['task_title']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted small">None (Manual Entry)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge bg-primary-subtle text-primary fs-6"><?php echo number_format($log['duration'], 1); ?> hrs</span></td>
                                        <td>
                                            <span class="text-truncate d-inline-block" style="max-width: 300px;" title="<?php echo e($log['description']); ?>">
                                                <?php echo e($log['description']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $log['status'] === 'approved' ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning'; ?>">
                                                <?php echo ucfirst(e($log['status'])); ?>
                                            </span>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
