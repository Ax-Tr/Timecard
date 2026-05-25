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

    // Get list of active employees for the filter dropdown
    $filter_employees = $pdo->query("
        SELECT id, first_name, last_name, emp_id 
        FROM employees 
        WHERE status = 'active' AND role = 'employee' 
        ORDER BY first_name ASC, last_name ASC
    ")->fetchAll();

    // Check if employee filter is set
    $selected_employee_id = isset($_GET['employee_id']) && is_numeric($_GET['employee_id']) ? (int)$_GET['employee_id'] : null;

    // 5. Fetch monthly statistics (Last 6 months)
    if ($selected_employee_id) {
        $monthly_stats_stmt = $pdo->prepare("
            SELECT DATE_FORMAT(date, '%Y-%m') as month_key, DATE_FORMAT(date, '%M %Y') as month_name, SUM(duration) as hours 
            FROM timesheets 
            WHERE user_id = :employee_id
            GROUP BY DATE_FORMAT(date, '%Y-%m'), DATE_FORMAT(date, '%M %Y') 
            ORDER BY month_key DESC 
            LIMIT 6
        ");
        $monthly_stats_stmt->execute(['employee_id' => $selected_employee_id]);
    } else {
        $monthly_stats_stmt = $pdo->query("
            SELECT DATE_FORMAT(date, '%Y-%m') as month_key, DATE_FORMAT(date, '%M %Y') as month_name, SUM(duration) as hours 
            FROM timesheets 
            GROUP BY DATE_FORMAT(date, '%Y-%m'), DATE_FORMAT(date, '%M %Y') 
            ORDER BY month_key DESC 
            LIMIT 6
        ");
    }
    $monthly_stats = $monthly_stats_stmt->fetchAll();

    // 6a. Toppers of the month (Top 3 active employees by hours)
    $toppers_stmt = $pdo->query("
        SELECT e.id, e.emp_id, e.first_name, e.last_name, e.email, COALESCE(SUM(t.duration), 0) as hours 
        FROM employees e 
        LEFT JOIN timesheets t ON e.id = t.user_id AND t.date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
        WHERE e.status = 'active' AND e.role = 'employee'
        GROUP BY e.id 
        ORDER BY hours DESC 
        LIMIT 3
    ");
    $toppers = $toppers_stmt->fetchAll();

    // 6b. Weakers of the month (Bottom 3 active employees by hours)
    $weakers_stmt = $pdo->query("
        SELECT e.id, e.emp_id, e.first_name, e.last_name, e.email, COALESCE(SUM(t.duration), 0) as hours 
        FROM employees e 
        LEFT JOIN timesheets t ON e.id = t.user_id AND t.date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
        WHERE e.status = 'active' AND e.role = 'employee'
        GROUP BY e.id 
        ORDER BY hours ASC 
        LIMIT 3
    ");
    $weakers = $weakers_stmt->fetchAll();

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
            <div>
                <h1 class="h3 mb-0 text-gray-800 fw-bold">Admin Dashboard</h1>
                <p class="text-muted small mb-0">Overview of organization performance, tracking, and metrics</p>
            </div>
            <span class="text-muted small"><i class="bi bi-calendar3 me-1"></i> Today is <?php echo date('l, M d, Y'); ?></span>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger shadow-sm"><?php echo e($error); ?></div>
        <?php endif; ?>

        <!-- KPI Cards Row -->
        <div class="row g-3 mb-4">
            <!-- Total Employees -->
            <div class="col-md-6 col-lg-3">
                <div class="card h-100 border-start border-primary border-4 hover-lift shadow-sm">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted text-uppercase small fw-bold" style="font-size: 0.75rem; letter-spacing: 0.5px;">Total Employees</span>
                            <h3 class="mb-0 fw-bold mt-1 text-dark"><?php echo $total_emp; ?></h3>
                        </div>
                        <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle">
                            <i class="bi bi-people-fill fs-3"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total Logged Hours -->
            <div class="col-md-6 col-lg-3">
                <div class="card h-100 border-start border-success border-4 hover-lift shadow-sm">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted text-uppercase small fw-bold" style="font-size: 0.75rem; letter-spacing: 0.5px;">Total Logged Hours</span>
                            <h3 class="mb-0 fw-bold mt-1 text-dark"><?php echo number_format($total_hours, 1); ?> <span class="fs-6 fw-normal text-muted">hrs</span></h3>
                        </div>
                        <div class="bg-success bg-opacity-10 text-success p-3 rounded-circle">
                            <i class="bi bi-clock-fill fs-3"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Timesheets -->
            <div class="col-md-6 col-lg-3">
                <div class="card h-100 border-start border-warning border-4 hover-lift shadow-sm">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted text-uppercase small fw-bold" style="font-size: 0.75rem; letter-spacing: 0.5px;">Pending Timesheets</span>
                            <h3 class="mb-0 fw-bold mt-1 text-dark"><?php echo $pending_timesheets; ?></h3>
                        </div>
                        <div class="bg-warning bg-opacity-10 text-warning p-3 rounded-circle">
                            <i class="bi bi-file-earmark-diff-fill fs-3"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Completed Tasks -->
            <div class="col-md-6 col-lg-3">
                <div class="card h-100 border-start border-info border-4 hover-lift shadow-sm">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted text-uppercase small fw-bold" style="font-size: 0.75rem; letter-spacing: 0.5px;">Completed Tasks</span>
                            <h3 class="mb-0 fw-bold mt-1 text-dark"><?php echo $completed_tasks; ?></h3>
                        </div>
                        <div class="bg-info bg-opacity-10 text-info p-3 rounded-circle">
                            <i class="bi bi-check2-circle fs-3"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Toppers & Weakers side-by-side (Dashboards at the beginning) -->
        <div class="row g-4 mb-4">
            <!-- Toppers Card -->
            <div class="col-xl-6">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center py-3">
                        <div class="d-flex align-items-center">
                            <div class="bg-success bg-opacity-10 text-success p-2 rounded me-3">
                                <i class="bi bi-trophy-fill fs-4"></i>
                            </div>
                            <div>
                                <h5 class="card-title mb-0 fw-bold text-success">Monthly Toppers</h5>
                                <small class="text-muted">Top performers this month</small>
                            </div>
                        </div>
                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-2 fw-semibold">🏆 High Achievers</span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($toppers)): ?>
                            <p class="text-muted text-center py-4">No logged hours recorded yet.</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php 
                                $max_topper_hours = max(array_column($toppers, 'hours') ?: [1]);
                                $rank = 1;
                                foreach ($toppers as $row): 
                                    $percentage = min(100, ($row['hours'] / $max_topper_hours) * 100);
                                    
                                    // Assign rank badge or emoji
                                    $rank_badge = '';
                                    if ($rank === 1) $rank_badge = '🥇';
                                    elseif ($rank === 2) $rank_badge = '🥈';
                                    elseif ($rank === 3) $rank_badge = '🥉';
                                    else $rank_badge = '<span class="badge bg-secondary-subtle text-secondary rounded-circle px-2">' . $rank . '</span>';
                                    
                                    // Initials and Color
                                    $initials = strtoupper(substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1));
                                    $colors = ['#4f46e5', '#0ea5e9', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6'];
                                    $avatar_bg = $colors[$row['id'] % count($colors)];
                                ?>
                                    <div class="list-group-item list-group-item-action border-0 rounded-3 mb-2 p-3 d-flex align-items-center justify-content-between hover-lift employee-report-trigger cursor-pointer" 
                                         data-id="<?php echo $row['id']; ?>"
                                         data-name="<?php echo e($row['first_name'] . ' ' . $row['last_name']); ?>"
                                         data-emp-id="<?php echo e($row['emp_id']); ?>"
                                         data-email="<?php echo e($row['email']); ?>"
                                         data-year="<?php echo date('Y'); ?>"
                                         data-month="<?php echo date('m'); ?>">
                                         
                                        <div class="d-flex align-items-center flex-grow-1 me-3">
                                            <div class="me-3 fs-4 text-center" style="width: 32px;"><?php echo $rank_badge; ?></div>
                                            
                                            <div class="rounded-circle text-white d-flex align-items-center justify-content-center me-3 fw-bold shadow-sm" 
                                                 style="width: 42px; height: 42px; background: <?php echo $avatar_bg; ?>; font-size: 0.95rem;">
                                                <?php echo $initials; ?>
                                            </div>
                                            
                                            <div class="flex-grow-1">
                                                <h6 class="mb-0 fw-bold text-dark"><?php echo e($row['first_name'] . ' ' . $row['last_name']); ?></h6>
                                                <small class="text-muted">ID: <code><?php echo e($row['emp_id']); ?></code></small>
                                                
                                                <div class="progress mt-2" style="height: 6px; border-radius: 3px; background-color: var(--bs-secondary-bg);">
                                                    <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" role="progressbar" style="width: <?php echo $percentage; ?>%; border-radius: 3px;" aria-valuenow="<?php echo $row['hours']; ?>" aria-valuemin="0" aria-valuemax="<?php echo $max_topper_hours; ?>"></div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="text-end">
                                            <span class="badge bg-success-subtle text-success fs-6 rounded-pill px-3 py-2 fw-bold"><?php echo number_format($row['hours'], 1); ?> hrs</span>
                                        </div>
                                    </div>
                                <?php 
                                    $rank++;
                                endforeach; 
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Weakers Card -->
            <div class="col-xl-6">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center py-3">
                        <div class="d-flex align-items-center">
                            <div class="bg-danger bg-opacity-10 text-danger p-2 rounded me-3">
                                <i class="bi bi-exclamation-triangle-fill fs-4"></i>
                            </div>
                            <div>
                                <h5 class="card-title mb-0 fw-bold text-danger">Needs Attention</h5>
                                <small class="text-muted">Least hours logged this month</small>
                            </div>
                        </div>
                        <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-3 py-2 fw-semibold">⚠️ Action Required</span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($weakers)): ?>
                            <p class="text-muted text-center py-4">No active employees found.</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php 
                                $max_weaker_hours = max(array_column($weakers, 'hours') ?: [1]);
                                $rank = 1;
                                foreach ($weakers as $row): 
                                    // For progress bar: relative to 40 hours target or max_weaker_hours
                                    $target_hours = max($max_weaker_hours, 40);
                                    $percentage = min(100, ($row['hours'] / $target_hours) * 100);
                                    
                                    // Initials and Color
                                    $initials = strtoupper(substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1));
                                    $colors = ['#ef4444', '#f97316', '#b91c1c', '#ea580c', '#dc2626'];
                                    $avatar_bg = $colors[$row['id'] % count($colors)];
                                ?>
                                    <div class="list-group-item list-group-item-action border-0 rounded-3 mb-2 p-3 d-flex align-items-center justify-content-between hover-lift employee-report-trigger cursor-pointer" 
                                         data-id="<?php echo $row['id']; ?>"
                                         data-name="<?php echo e($row['first_name'] . ' ' . $row['last_name']); ?>"
                                         data-emp-id="<?php echo e($row['emp_id']); ?>"
                                         data-email="<?php echo e($row['email']); ?>"
                                         data-year="<?php echo date('Y'); ?>"
                                         data-month="<?php echo date('m'); ?>">
                                         
                                        <div class="d-flex align-items-center flex-grow-1 me-3">
                                            <div class="me-3 fs-5 text-center" style="width: 32px;">
                                                <i class="bi bi-arrow-down-circle-fill text-danger"></i>
                                            </div>
                                            
                                            <div class="rounded-circle text-white d-flex align-items-center justify-content-center me-3 fw-bold shadow-sm" 
                                                 style="width: 42px; height: 42px; background: <?php echo $avatar_bg; ?>; font-size: 0.95rem;">
                                                <?php echo $initials; ?>
                                            </div>
                                            
                                            <div class="flex-grow-1">
                                                <h6 class="mb-0 fw-bold text-dark"><?php echo e($row['first_name'] . ' ' . $row['last_name']); ?></h6>
                                                <small class="text-muted">ID: <code><?php echo e($row['emp_id']); ?></code></small>
                                                
                                                <div class="progress mt-2" style="height: 6px; border-radius: 3px; background-color: var(--bs-secondary-bg);">
                                                    <div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo $percentage; ?>%; border-radius: 3px;" aria-valuenow="<?php echo $row['hours']; ?>" aria-valuemin="0" aria-valuemax="<?php echo $target_hours; ?>"></div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="text-end">
                                            <span class="badge bg-danger-subtle text-danger fs-6 rounded-pill px-3 py-2 fw-bold"><?php echo number_format($row['hours'], 1); ?> hrs</span>
                                        </div>
                                    </div>
                                <?php 
                                    $rank++;
                                endforeach; 
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Monthly Statistics Trend (Middle Section) -->
        <div class="row g-4 mb-4">
            <div class="col-xl-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-transparent border-0 d-flex flex-column flex-sm-row justify-content-between align-items-sm-center py-3 gap-2">
                        <div class="d-flex align-items-center">
                            <div class="bg-primary bg-opacity-10 text-primary p-2 rounded me-3">
                                <i class="bi bi-graph-up-arrow fs-4"></i>
                            </div>
                            <div>
                                <h5 class="card-title mb-0 fw-bold text-primary">Monthly Trend</h5>
                                <small class="text-muted">Logged hours comparison over last 6 months</small>
                            </div>
                        </div>
                        
                        <!-- Searchable Employee Filter Dropdown -->
                        <div style="width: 280px; position: relative;" id="trendEmployeeDropdown">
                            <div class="dropdown searchable-dropdown w-100">
                                <button class="btn btn-outline-secondary dropdown-toggle w-100 text-start form-select text-truncate" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="trendEmployeeBtn" style="font-size: 0.85rem; border-color: rgba(0,0,0,0.15); background-color: var(--bs-body-bg);">
                                    <?php 
                                        $selected_label = 'All Employees';
                                        if ($selected_employee_id) {
                                            foreach ($filter_employees as $emp) {
                                                if ($emp['id'] == $selected_employee_id) {
                                                    $selected_label = '[' . $emp['emp_id'] . '] ' . $emp['first_name'] . ' ' . $emp['last_name'];
                                                    break;
                                                }
                                            }
                                        }
                                        echo e($selected_label);
                                    ?>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end w-100 p-2 shadow-sm" style="max-height: 250px; overflow-y: auto; z-index: 1050;">
                                    <input type="text" class="form-control mb-2 dropdown-search-input form-control-sm" placeholder="Search employee..." autocomplete="off">
                                    <div class="dropdown-options-list">
                                        <button type="button" class="dropdown-item text-start<?php echo !$selected_employee_id ? ' active' : ''; ?>" data-value="0" style="font-size: 0.85rem;">All Employees</button>
                                        <?php foreach ($filter_employees as $emp): ?>
                                            <button type="button" class="dropdown-item text-start<?php echo $selected_employee_id == $emp['id'] ? ' active' : ''; ?>" data-value="<?php echo $emp['id']; ?>" style="font-size: 0.85rem;">
                                                [<?php echo e($emp['emp_id']); ?>] <?php echo e($emp['first_name'] . ' ' . $emp['last_name']); ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <input type="hidden" name="trend_employee_id" class="dropdown-hidden-input" value="<?php echo $selected_employee_id ?? '0'; ?>">
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($monthly_stats)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-bar-chart text-muted display-4"></i>
                                <p class="text-muted mt-2 mb-0">No logged hours found for this query.</p>
                            </div>
                        <?php else: ?>
                            <!-- Chart.js container -->
                            <div class="mb-4" style="height: 300px; position: relative;">
                                <canvas id="monthlyTrendChart"></canvas>
                            </div>
                            
                            <div class="row g-2 justify-content-center">
                                <?php foreach (array_reverse($monthly_stats) as $stat): ?>
                                    <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                                        <div class="p-3 border rounded text-center bg-body-tertiary shadow-sm hover-lift">
                                            <span class="text-muted small d-block mb-1 text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.5px;"><?php echo e($stat['month_name']); ?></span>
                                            <span class="fs-4 fw-bold text-primary"><?php echo number_format($stat['hours'], 1); ?></span> <small class="text-muted">hrs</small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity Log (Log details to be at bottom) -->
        <div class="row g-4">
            <div class="col-xl-12">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center py-3">
                        <div class="d-flex align-items-center">
                            <div class="bg-secondary bg-opacity-10 text-secondary p-2 rounded me-3">
                                <i class="bi bi-clock-history fs-4"></i>
                            </div>
                            <div>
                                <h5 class="card-title mb-0 fw-bold text-dark">Recent Activity Log</h5>
                                <small class="text-muted">Latest system and employee operations</small>
                            </div>
                        </div>
                        <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-3 py-2 fw-semibold">📅 Live Log Feed</span>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($activities)): ?>
                            <p class="text-muted text-center py-4">No activity logged.</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush" style="font-size: 0.9rem;">
                                <?php foreach ($activities as $log): ?>
                                    <div class="list-group-item py-3 px-4 border-bottom d-flex justify-content-between align-items-center hover-bg-secondary">
                                        <div class="d-flex align-items-center">
                                            <div class="me-3">
                                                <?php if ($log['username'] === 'Admin'): ?>
                                                    <span class="badge bg-danger rounded-pill px-2 py-1" style="font-size: 0.75rem;">Admin</span>
                                                <?php else: ?>
                                                    <span class="badge bg-primary rounded-pill px-2 py-1" style="font-size: 0.75rem;"><?php echo e($log['username'] ?? 'System'); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <p class="mb-0 text-dark fw-medium"><?php echo e($log['action']); ?></p>
                                            </div>
                                        </div>
                                        <div>
                                            <small class="text-muted"><i class="bi bi-calendar3 me-1"></i> <?php echo date('M d, Y - H:i', strtotime($log['created_at'])); ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

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

    // 2. Trend Employee Filter Redirect Handler
    const trendDropdownEl = document.getElementById('trendEmployeeDropdown');
    if (trendDropdownEl) {
        const options = trendDropdownEl.querySelectorAll('.dropdown-options-list .dropdown-item');
        options.forEach(opt => {
            opt.addEventListener('click', function(e) {
                e.preventDefault();
                const val = this.getAttribute('data-value');
                const url = new URL(window.location.href);
                if (val && val !== '0') {
                    url.searchParams.set('employee_id', val);
                } else {
                    url.searchParams.delete('employee_id');
                }
                window.location.href = url.toString();
            });
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
