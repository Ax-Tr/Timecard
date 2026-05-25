<?php
// Admin Dashboard
$page_title = 'Admin Dashboard';
require_once __DIR__ . '/../includes/header.php';
require_admin();

// Fetch metrics
try {
    // 1. Total Employees
    $total_emp = $pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn();

    // 2. Total Logged Hours
    $total_hours = $pdo->query("SELECT SUM(duration) FROM timesheets")->fetchColumn() ?? 0;

    // 3. Pending Timesheets
    $pending_timesheets = $pdo->query("SELECT COUNT(*) FROM timesheets WHERE status = 'pending'")->fetchColumn();

    // 4. Completed Tasks
    $completed_tasks = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'completed'")->fetchColumn();

    // 5. Monthly statistics (Last 6 months)
    $monthly_stats_stmt = $pdo->query("
        SELECT DATE_FORMAT(date, '%M %Y') as month_name, SUM(duration) as hours 
        FROM timesheets 
        GROUP BY DATE_FORMAT(date, '%Y-%m') 
        ORDER BY date DESC 
        LIMIT 6
    ");
    $monthly_stats = $monthly_stats_stmt->fetchAll();

    // 6. Productivity summary (Top 5 employees by hours)
    $productivity_stmt = $pdo->query("
        SELECT e.first_name, e.last_name, e.emp_id, SUM(t.duration) as hours 
        FROM timesheets t 
        JOIN employees e ON t.user_id = e.id 
        WHERE t.date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
        GROUP BY e.id 
        ORDER BY hours DESC 
        LIMIT 5
    ");
    $productivity = $productivity_stmt->fetchAll();

    // 7. Recent activity logs
    $activity_stmt = $pdo->query("
        SELECT a.*, e.emp_id as username 
        FROM activity_logs a 
        LEFT JOIN employees e ON a.user_id = e.id 
        ORDER BY a.created_at DESC 
        LIMIT 5
    ");
    $activities = $activity_stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Dashboard query error: " . $e->getMessage());
    $error = "Error fetching dashboard statistics.";
}
?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<!-- Main Content Area -->
<main class="main-content d-flex flex-column flex-grow-1">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0 text-gray-800">Admin Dashboard</h1>
            <span class="text-muted small"><i class="bi bi-calendar3 me-1"></i> Today is <?php echo date('l, M d, Y'); ?></span>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo e($error); ?></div>
        <?php endif; ?>

        <!-- KPI Cards Row -->
        <div class="row g-3 mb-4">
            <!-- Total Employees -->
            <div class="col-md-6 col-lg-3">
                <div class="card h-100 border-start border-primary border-4 hover-lift">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted text-uppercase small fw-bold">Total Employees</span>
                            <h3 class="mb-0 fw-bold mt-1"><?php echo $total_emp; ?></h3>
                        </div>
                        <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle">
                            <i class="bi bi-people-fill fs-3"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total Logged Hours -->
            <div class="col-md-6 col-lg-3">
                <div class="card h-100 border-start border-success border-4 hover-lift">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted text-uppercase small fw-bold">Total Logged Hours</span>
                            <h3 class="mb-0 fw-bold mt-1"><?php echo number_format($total_hours, 1); ?> <span class="fs-6 fw-normal">hrs</span></h3>
                        </div>
                        <div class="bg-success bg-opacity-10 text-success p-3 rounded-circle">
                            <i class="bi bi-clock-fill fs-3"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Timesheets -->
            <div class="col-md-6 col-lg-3">
                <div class="card h-100 border-start border-warning border-4 hover-lift">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted text-uppercase small fw-bold">Pending Timesheets</span>
                            <h3 class="mb-0 fw-bold mt-1"><?php echo $pending_timesheets; ?></h3>
                        </div>
                        <div class="bg-warning bg-opacity-10 text-warning p-3 rounded-circle">
                            <i class="bi bi-file-earmark-diff-fill fs-3"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Completed Tasks -->
            <div class="col-md-6 col-lg-3">
                <div class="card h-100 border-start border-info border-4 hover-lift">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted text-uppercase small fw-bold">Completed Tasks</span>
                            <h3 class="mb-0 fw-bold mt-1"><?php echo $completed_tasks; ?></h3>
                        </div>
                        <div class="bg-info bg-opacity-10 text-info p-3 rounded-circle">
                            <i class="bi bi-check2-circle fs-3"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Productivity & Monthly stats -->
            <div class="col-xl-8">
                <!-- Employee Productivity Summary -->
                <div class="card mb-4">
                    <div class="card-header bg-transparent py-3">
                        <h6 class="m-0 fw-bold text-primary">Employee Productivity (This Month)</h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($productivity)): ?>
                            <p class="text-muted text-center py-4">No hours logged yet this month.</p>
                        <?php else: ?>
                            <!-- Productivity Bar Chart -->
                            <div class="mb-4" style="height: 240px; position: relative;">
                                <canvas id="productivityChart"></canvas>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Employee</th>
                                            <th>Emp ID</th>
                                            <th>Hours Logged</th>
                                            <th>Visual Representation</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $max_hours = max(array_column($productivity, 'hours') ?: [1]);
                                        foreach ($productivity as $row): 
                                            $percentage = min(100, ($row['hours'] / $max_hours) * 100);
                                        ?>
                                            <tr>
                                                <td><span class="fw-semibold"><?php echo e($row['first_name'] . ' ' . $row['last_name']); ?></span></td>
                                                <td><code><?php echo e($row['emp_id']); ?></code></td>
                                                <td><span class="badge bg-success-subtle text-success fs-6"><?php echo number_format($row['hours'], 1); ?> hrs</span></td>
                                                <td style="width: 40%;">
                                                    <div class="progress" style="height: 8px;">
                                                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $percentage; ?>%;" aria-valuenow="<?php echo $row['hours']; ?>" aria-valuemin="0" aria-valuemax="<?php echo $max_hours; ?>"></div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
 
                <!-- Monthly Statistics -->
                <div class="card">
                    <div class="card-header bg-transparent py-3">
                        <h6 class="m-0 fw-bold text-primary">Monthly Statistics (Logged Hours Trend)</h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($monthly_stats)): ?>
                            <p class="text-muted text-center py-4">No data available.</p>
                        <?php else: ?>
                            <!-- Monthly Trend Chart -->
                            <div class="mb-4" style="height: 240px; position: relative;">
                                <canvas id="monthlyTrendChart"></canvas>
                            </div>
                            <div class="row">
                                <?php foreach (array_reverse($monthly_stats) as $stat): ?>
                                    <div class="col-sm-6 col-md-4 mb-3">
                                        <div class="p-3 border rounded text-center bg-body-tertiary">
                                            <span class="text-muted small d-block mb-1 text-uppercase fw-bold"><?php echo e($stat['month_name']); ?></span>
                                            <span class="fs-4 fw-bold text-primary"><?php echo number_format($stat['hours'], 1); ?></span> <small class="text-muted">hrs</small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar / Logs / Feed -->
            <div class="col-xl-4">
                <!-- Recent Activities -->
                <div class="card h-100">
                    <div class="card-header bg-transparent py-3">
                        <h6 class="m-0 fw-bold text-primary">Recent Activity Log</h6>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($activities)): ?>
                            <p class="text-muted text-center py-4">No activity logged.</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush" style="font-size: 0.85rem;">
                                <?php foreach ($activities as $log): ?>
                                    <div class="list-group-item py-3 px-4">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="fw-semibold <?php echo $log['username'] === 'Admin' ? 'text-danger' : 'text-primary'; ?>">
                                                <?php echo e($log['username'] ?? 'System'); ?>
                                            </span>
                                            <small class="text-muted"><?php echo date('M d, H:i', strtotime($log['created_at'])); ?></small>
                                        </div>
                                        <p class="mb-0 text-secondary"><?php echo e($log['action']); ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Monthly statistics (Logged Hours Trend)
    const monthlyCtx = document.getElementById('monthlyTrendChart');
    if (monthlyCtx) {
        const months = <?php echo json_encode(array_column(array_reverse($monthly_stats), 'month_name')); ?>;
        const hours = <?php echo json_encode(array_column(array_reverse($monthly_stats), 'hours')); ?>;

        new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: months,
                datasets: [{
                    label: 'Logged Hours',
                    data: hours,
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.05)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.35,
                    pointBackgroundColor: '#0d6efd',
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0,0,0,0.05)' },
                        ticks: { callback: value => value + ' hrs' }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    }

    // 2. Employee Productivity Chart
    const productivityCtx = document.getElementById('productivityChart');
    if (productivityCtx) {
        const names = <?php echo json_encode(array_map(function($p) {
            return $p['first_name'] . ' ' . $p['last_name'];
        }, $productivity)); ?>;
        const hours = <?php echo json_encode(array_column($productivity, 'hours')); ?>;

        new Chart(productivityCtx, {
            type: 'bar',
            data: {
                labels: names,
                datasets: [{
                    label: 'Hours Logged',
                    data: hours,
                    backgroundColor: 'rgba(25, 135, 84, 0.85)',
                    hoverBackgroundColor: '#198754',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0,0,0,0.05)' },
                        ticks: { callback: value => value + ' hrs' }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
