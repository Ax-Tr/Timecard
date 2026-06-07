<?php
// Employee Dashboard
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Secure page access
require_login();
if (is_admin()) {
    header("Location: " . APP_ROOT . "admin/dashboard");
    exit;
}

$user_id = $_SESSION['user_id'];
$error = $_SESSION['attendance_error'] ?? '';
$success = $_SESSION['attendance_success'] ?? '';
unset($_SESSION['attendance_error'], $_SESSION['attendance_success']);

// Handle Clock-in / Clock-out POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attendance_action'])) {
    $attendance_act = $_POST['attendance_action'];
    $token = $_POST['csrf_token'] ?? '';

    if (!verify_csrf($token)) {
        $_SESSION['attendance_error'] = 'Invalid CSRF security token.';
    } else {
        try {
            // Check active session with elapsed seconds computed natively by the database timezone
            $chk_stmt = $pdo->prepare("SELECT *, TIMESTAMPDIFF(SECOND, clock_in, NOW()) as seconds_elapsed FROM attendance WHERE user_id = ? AND clock_out IS NULL LIMIT 1");
            $chk_stmt->execute([$user_id]);
            $current_session = $chk_stmt->fetch();

            if ($attendance_act === 'clock_in') {
                if ($current_session) {
                    $_SESSION['attendance_error'] = 'You are already clocked in.';
                } else {
                    // Rule 2: Check if employee has already clocked out today
                    $today_done_stmt = $pdo->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = CURDATE() AND clock_out IS NOT NULL LIMIT 1");
                    $today_done_stmt->execute([$user_id]);
                    $today_done = $today_done_stmt->fetch();

                    if ($today_done) {
                        $_SESSION['attendance_error'] = 'Warning: You have already completed your attendance logging for today. You cannot clock in again today.';
                    } else {
                        $ins_stmt = $pdo->prepare("INSERT INTO attendance (user_id, date, clock_in) VALUES (?, CURDATE(), NOW())");
                        $ins_stmt->execute([$user_id]);
                        log_activity($user_id, "Clocked in");
                        $_SESSION['attendance_success'] = 'Successfully clocked in!';
                    }
                }
            } elseif ($attendance_act === 'clock_out') {
                if (!$current_session) {
                    $_SESSION['attendance_error'] = 'You are not clocked in.';
                } else {
                    // Rule 1: Prevent clocking out early (require minimum shift duration)
                    $diff_seconds = (int)$current_session['seconds_elapsed'];
                    
                    if ($diff_seconds < MIN_SHIFT_SECONDS) {
                        $remaining = MIN_SHIFT_SECONDS - $diff_seconds;
                        $remaining_text = "";
                        if ($remaining >= 3600) {
                            $hours = floor($remaining / 3600);
                            $minutes = floor(($remaining % 3600) / 60);
                            $remaining_text = "{$hours}h {$minutes}m";
                        } elseif ($remaining >= 60) {
                            $minutes = floor($remaining / 60);
                            $remaining_text = "{$minutes}m";
                        } else {
                            $remaining_text = "{$remaining}s";
                        }
                        $_SESSION['attendance_error'] = "Warning: You cannot clock out early. Required shift duration is not met (remaining: {$remaining_text}).";
                    } else {
                        $upd_stmt = $pdo->prepare("
                            UPDATE attendance 
                            SET clock_out = NOW(), 
                                duration = ROUND(TIMESTAMPDIFF(SECOND, clock_in, NOW()) / 3600.0, 2) 
                            WHERE id = ?
                        ");
                        $upd_stmt->execute([$current_session['id']]);
                        
                        // Fetch the updated record to get duration
                        $dur_stmt = $pdo->prepare("SELECT duration FROM attendance WHERE id = ?");
                        $dur_stmt->execute([$current_session['id']]);
                        $duration = $dur_stmt->fetchColumn();

                        log_activity($user_id, "Clocked out. Duration: " . number_format($duration, 2) . " hrs");
                        $_SESSION['attendance_success'] = 'Successfully clocked out! Worked ' . number_format($duration, 2) . ' hours.';
                    }
                }
            }
        } catch (PDOException $e) {
            $_SESSION['attendance_error'] = 'Attendance logging error: ' . $e->getMessage();
        }
    }

    // Redirect to prevent duplicate form submissions on page refresh
    header("Location: " . APP_ROOT . "employee/dashboard");
    exit;
}

// Fetch active clock-in session
$active_session = null;
try {
    $session_stmt = $pdo->prepare("SELECT *, TIMESTAMPDIFF(SECOND, clock_in, NOW()) as seconds_elapsed FROM attendance WHERE user_id = ? AND clock_out IS NULL LIMIT 1");
    $session_stmt->execute([$user_id]);
    $active_session = $session_stmt->fetch();
} catch (PDOException $e) {
    error_log("Failed to fetch active attendance session: " . $e->getMessage());
}

// Check if already completed attendance session today
$completed_today = null;
try {
    $comp_stmt = $pdo->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = CURDATE() AND clock_out IS NOT NULL LIMIT 1");
    $comp_stmt->execute([$user_id]);
    $completed_today = $comp_stmt->fetch();
} catch (PDOException $e) {
    error_log("Failed to fetch completed attendance session: " . $e->getMessage());
}

// Now include header after POST and redirect logic has executed successfully
$page_title = 'Employee Dashboard';
require_once __DIR__ . '/../includes/header.php';


$display_name = $_SESSION['username'];
if (isset($_SESSION['full_name'])) {
    $display_name = $_SESSION['full_name'];
} else {
    try {
        $name_stmt = $pdo->prepare("SELECT first_name, last_name FROM employees WHERE id = ?");
        $name_stmt->execute([$user_id]);
        $emp_profile = $name_stmt->fetch();
        if ($emp_profile) {
            $display_name = $emp_profile['first_name'] . ' ' . $emp_profile['last_name'];
            $_SESSION['full_name'] = $display_name;
        }
    } catch (PDOException $e) {
        // fallback to username
    }
}

try {
    // 1. Fetch Today's Tasks Count
    $today_tasks_stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status = 'pending' AND deadline = CURDATE()");
    $today_tasks_stmt->execute([$user_id]);
    $today_tasks_count = $today_tasks_stmt->fetchColumn();

    // 2. Fetch Pending Tasks Count
    $pending_tasks_stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status = 'pending'");
    $pending_tasks_stmt->execute([$user_id]);
    $pending_tasks_count = $pending_tasks_stmt->fetchColumn();

    // 3. Fetch Monthly Hours Summary
    $monthly_hours_stmt = $pdo->prepare("
        SELECT SUM(duration) 
        FROM timesheets 
        WHERE user_id = ? AND date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
    ");
    $monthly_hours_stmt->execute([$user_id]);
    $monthly_hours = $monthly_hours_stmt->fetchColumn() ?? 0;

    // 4. Fetch Recent Timesheet Entries (Last 5)
    $recent_timesheets_stmt = $pdo->prepare("
        SELECT t.*, tk.title as task_title 
        FROM timesheets t 
        LEFT JOIN tasks tk ON t.task_id = tk.id 
        WHERE t.user_id = ? 
        ORDER BY t.date DESC, t.id DESC 
        LIMIT 5
    ");
    $recent_timesheets_stmt->execute([$user_id]);
    $recent_timesheets = $recent_timesheets_stmt->fetchAll();

    // 5. Fetch Top 3 Pending Tasks
    $tasks_preview_stmt = $pdo->prepare("
        SELECT * FROM tasks 
        WHERE assigned_to = ? AND status = 'pending' 
        ORDER BY deadline ASC, priority DESC 
        LIMIT 3
    ");
    $tasks_preview_stmt->execute([$user_id]);
    $tasks_preview = $tasks_preview_stmt->fetchAll();

    // 6. Fetch last 7 days of worked hours for chart
    $weekly_stats_stmt = $pdo->prepare("
        SELECT date, SUM(duration) as hours 
        FROM timesheets 
        WHERE user_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY date 
        ORDER BY date ASC
    ");
    $weekly_stats_stmt->execute([$user_id]);
    $weekly_stats = $weekly_stats_stmt->fetchAll();

    $chart_labels = [];
    $chart_data = [];
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $chart_labels[] = date('D, M d', strtotime($d));
        
        $hours = 0;
        foreach ($weekly_stats as $stat) {
            if ($stat['date'] === $d) {
                $hours = (float)$stat['hours'];
                break;
            }
        }
        $chart_data[] = $hours;
    }

    // 7. Fetch task vs manual hours allocation
    $allocation_stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN task_id IS NOT NULL THEN duration ELSE 0 END) as task_hours,
            SUM(CASE WHEN task_id IS NULL THEN duration ELSE 0 END) as manual_hours
        FROM timesheets
        WHERE user_id = ? AND date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
    ");
    $allocation_stmt->execute([$user_id]);
    $allocation = $allocation_stmt->fetch();
    $task_hours = (float)($allocation['task_hours'] ?? 0);
    $manual_hours = (float)($allocation['manual_hours'] ?? 0);

    // 8. Fetch 1 year of daily logged hours for contribution heatmap
    $heatmap_stmt = $pdo->prepare("
        SELECT date, SUM(duration) as daily_hours 
        FROM timesheets 
        WHERE user_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 364 DAY)
        GROUP BY date
    ");
    $heatmap_stmt->execute([$user_id]);
    $heatmap_data = $heatmap_stmt->fetchAll();

    // Index by date for fast lookup in PHP
    $daily_logs = [];
    foreach ($heatmap_data as $row) {
        $daily_logs[$row['date']] = (float)$row['daily_hours'];
    }

    // Generate contribution calendar weeks and days
    $start_date = new DateTime();
    $start_date->modify('-364 days');
    $day_of_week = (int)$start_date->format('w');
    if ($day_of_week > 0) {
        $start_date->modify("-$day_of_week days");
    }
    
    $end_date = new DateTime();
    $interval = new DateInterval('P1D');
    $date_period = new DatePeriod($start_date, $interval, $end_date->modify('+1 day'));

    $weeks = [];
    $current_week = [];
    
    foreach ($date_period as $date) {
        $date_str = $date->format('Y-m-d');
        $hours = $daily_logs[$date_str] ?? 0;
        
        $level = 0;
        if ($hours > 0 && $hours <= 2) {
            $level = 1;
        } elseif ($hours > 2 && $hours <= 5) {
            $level = 2;
        } elseif ($hours > 5 && $hours <= 8) {
            $level = 3;
        } elseif ($hours > 8) {
            $level = 4;
        }
        
        $current_week[] = [
            'date' => $date_str,
            'formatted_date' => $date->format('M d, Y'),
            'hours' => $hours,
            'level' => $level
        ];
        
        if (count($current_week) === 7) {
            $weeks[] = $current_week;
            $current_week = [];
        }
    }
    if (!empty($current_week)) {
        $weeks[] = $current_week;
    }

    // 9. Fetch Recent Attendance Logs (Last 5)
    $recent_attendance_stmt = $pdo->prepare("
        SELECT * 
        FROM attendance 
        WHERE user_id = ? 
        ORDER BY date DESC, clock_in DESC 
        LIMIT 5
    ");
    $recent_attendance_stmt->execute([$user_id]);
    $recent_attendance = $recent_attendance_stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Employee dashboard error: " . $e->getMessage());
    $error = "Error loading dashboard metrics.";
}
?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<!-- Main Content Area -->
<main class="main-content d-flex flex-column flex-grow-1">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
            <div>
                <h1 class="h3 mb-1 text-gray-800">Welcome Back, <?php echo e($display_name); ?></h1>
                <p class="text-muted small mb-0">Here is your schedule and timesheet overview.</p>
            </div>
            <span class="text-muted small"><i class="bi bi-clock me-1"></i> Today is <?php echo date('D, M d, Y'); ?></span>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 small"><?php echo e($error); ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success py-2 small"><?php echo e($success); ?></div>
        <?php endif; ?>

        <!-- Clock In/Out Widget -->
        <div class="card shadow-sm border-0 mb-4 bg-body-tertiary">
            <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-3 rounded-circle <?php 
                        if ($active_session) {
                            echo 'bg-success bg-opacity-10 text-success';
                        } elseif ($completed_today) {
                            echo 'bg-info bg-opacity-10 text-info';
                        } else {
                            echo 'bg-secondary bg-opacity-10 text-secondary';
                        }
                    ?>">
                        <i class="bi <?php 
                            if ($active_session) {
                                echo 'bi-clock-fill';
                            } elseif ($completed_today) {
                                echo 'bi-calendar-check-fill';
                            } else {
                                echo 'bi-clock-history';
                            }
                        ?> fs-4"></i>
                    </div>
                    <div>
                        <h5 class="mb-1 fw-bold">Daily Attendance Tracker</h5>
                        <?php if ($active_session): ?>
                            <p class="text-success mb-1 small fw-semibold">
                                <i class="bi bi-check-circle-fill me-1"></i> Clocked-in at <strong><?php echo date('h:i A', strtotime($active_session['clock_in'])); ?></strong>
                            </p>
                            <!-- Live Shift duration and progress bar -->
                            <div class="mt-2" style="min-width: 280px; max-width: 350px;">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="text-muted small" style="font-size: 0.75rem;">Shift Progress:</span>
                                    <span id="liveWorkedTime" class="badge bg-success-subtle text-success fw-bold font-monospace">00:00:00</span>
                                </div>
                                <div class="progress shadow-sm" style="height: 8px; border-radius: 4px; background-color: var(--bs-border-color);">
                                    <div id="shiftProgressBar" class="progress-bar bg-success progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                        <?php elseif ($completed_today): ?>
                            <p class="text-info mb-0 small">
                                <i class="bi bi-award-fill me-1"></i> Shift completed today! Clocked in at <strong><?php echo date('h:i A', strtotime($completed_today['clock_in'])); ?></strong> and clocked out at <strong><?php echo date('h:i A', strtotime($completed_today['clock_out'])); ?></strong> (worked <strong><?php echo number_format($completed_today['duration'], 2); ?></strong> hrs).
                            </p>
                        <?php else: ?>
                            <p class="text-muted mb-0 small">You are currently clocked out. Don't forget to clock in!</p>
                        <?php endif; ?>
                    </div>
                </div>
                <div>
                    <?php if ($completed_today): ?>
                        <button class="btn btn-secondary fw-semibold px-4 py-2" disabled>
                            <i class="bi bi-check-lg me-1"></i> Shift Completed
                        </button>
                    <?php else: ?>
                        <form method="POST" action="" class="m-0">
                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                            <?php if ($active_session): ?>
                                <?php 
                                    $remaining_seconds = MIN_SHIFT_SECONDS - (int)$active_session['seconds_elapsed'];
                                    if ($remaining_seconds < 0) {
                                        $remaining_seconds = 0;
                                    }
                                ?>
                                <input type="hidden" name="attendance_action" value="clock_out">
                                <button type="submit" 
                                        id="clockOutBtn" 
                                        class="btn btn-danger fw-semibold px-4 py-2 hover-lift"
                                        data-elapsed="<?php echo (int)$active_session['seconds_elapsed']; ?>"
                                        data-required="<?php echo MIN_SHIFT_SECONDS; ?>"
                                        <?php if ($remaining_seconds > 0): ?>
                                            disabled 
                                        <?php endif; ?>>
                                    <i class="bi bi-box-arrow-right me-1"></i> 
                                    <span id="clockOutText">Clock Out</span>
                                </button>
                            <?php else: ?>
                                <input type="hidden" name="attendance_action" value="clock_in">
                                <button type="submit" class="btn btn-success fw-semibold px-4 py-2 hover-lift">
                                    <i class="bi bi-box-arrow-in-right me-1"></i> Clock In
                                </button>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- KPI Cards Row -->
        <div class="row g-3 mb-4">
            <!-- Today's Tasks -->
            <div class="col-md-4">
                <div class="card h-100 border-start border-danger border-4 hover-lift">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted text-uppercase small fw-bold">Due Today</span>
                            <h3 class="mb-0 fw-bold mt-1"><?php echo $today_tasks_count; ?> <span class="fs-6 fw-normal text-muted">tasks</span></h3>
                        </div>
                        <div class="bg-danger bg-opacity-10 text-danger p-3 rounded-circle">
                            <i class="bi bi-calendar-event-fill fs-3"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending To-Dos -->
            <div class="col-md-4">
                <div class="card h-100 border-start border-warning border-4 hover-lift">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted text-uppercase small fw-bold">Pending To-Dos</span>
                            <h3 class="mb-0 fw-bold mt-1"><?php echo $pending_tasks_count; ?> <span class="fs-6 fw-normal text-muted">tasks</span></h3>
                        </div>
                        <div class="bg-warning bg-opacity-10 text-warning p-3 rounded-circle">
                            <i class="bi bi-clipboard2-check-fill fs-3"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Monthly Worked Hours -->
            <div class="col-md-4">
                <div class="card h-100 border-start border-success border-4 hover-lift">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted text-uppercase small fw-bold">Hours Logged This Month</span>
                            <h3 class="mb-0 fw-bold mt-1"><?php echo number_format($monthly_hours, 1); ?> <span class="fs-6 fw-normal text-muted">hrs</span></h3>
                        </div>
                        <div class="bg-success bg-opacity-10 text-success p-3 rounded-circle">
                            <i class="bi bi-hourglass-split fs-3"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Left Column: Pending Tasks preview -->
            <div class="col-xl-6">
                <div class="card h-100">
                    <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 fw-bold text-primary">Your Tasks Preview</h6>
                        <a href="my-tasks" class="btn btn-sm btn-outline-primary fw-semibold">View All Tasks</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($tasks_preview)): ?>
                            <div class="text-center py-5 text-muted">
                                <i class="bi bi-emoji-smile fs-2 mb-2"></i>
                                <p class="mb-0">Awesome! No pending tasks on your list.</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($tasks_preview as $task): ?>
                                    <div class="list-group-item px-0 py-3 d-flex justify-content-between align-items-start gap-2 flex-wrap flex-sm-nowrap">
                                        <div>
                                            <h6 class="fw-bold mb-1"><?php echo e($task['title']); ?></h6>
                                            <p class="text-muted small mb-1 text-truncate-2"><?php echo e($task['description'] ?: 'No details provided'); ?></p>
                                            <span class="badge bg-secondary-subtle text-secondary small">Deadline: <?php echo e($task['deadline']); ?></span>
                                            <span class="badge <?php echo $task['priority'] === 'high' ? 'bg-danger-subtle text-danger' : ($task['priority'] === 'medium' ? 'bg-warning-subtle text-warning' : 'bg-info-subtle text-info'); ?> small">
                                                <?php echo ucfirst(e($task['priority'])); ?>
                                            </span>
                                        </div>
                                        <a href="my-tasks" class="btn btn-sm btn-primary">Complete</a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column: Recent Timesheet entries -->
            <div class="col-xl-6">
                <div class="card h-100">
                    <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 fw-bold text-primary">Recent Timesheet Entries</h6>
                        <a href="add-timesheet" class="btn btn-sm btn-success fw-semibold"><i class="bi bi-plus-circle me-1"></i> Log Hours</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_timesheets)): ?>
                            <p class="text-muted text-center py-5">No timesheet records logged recently.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Date</th>
                                            <th>Hours</th>
                                            <th>Description</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_timesheets as $ts): ?>
                                            <tr>
                                                <td><span class="fw-semibold"><?php echo e($ts['date']); ?></span></td>
                                                <td><span class="badge bg-primary-subtle text-primary"><?php echo number_format($ts['duration'], 1); ?> hrs</span></td>
                                                <td>
                                                    <span class="text-truncate d-inline-block" style="max-width: 150px;" title="<?php echo e($ts['description']); ?>">
                                                        <?php echo e($ts['description']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $ts['status'] === 'approved' ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning'; ?>">
                                                        <?php echo ucfirst(e($ts['status'])); ?>
                                                    </span>
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
        </div>

        <!-- Dashboard Charts Row -->
        <div class="row g-4 mt-1">
            <!-- Weekly Hours Line Chart -->
            <div class="col-xl-8">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-header bg-transparent py-3">
                        <h6 class="m-0 fw-bold text-primary">Your Daily Worked Hours (Last 7 Days)</h6>
                    </div>
                    <div class="card-body">
                        <div style="height: 240px; position: relative;">
                            <canvas id="weeklyHoursChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Task vs Manual hours allocation chart -->
            <div class="col-xl-4">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-header bg-transparent py-3">
                        <h6 class="m-0 fw-bold text-primary">Hour Allocation (This Month)</h6>
                    </div>
                    <div class="card-body d-flex flex-column justify-content-center align-items-center">
                        <?php if ($task_hours == 0 && $manual_hours == 0): ?>
                            <p class="text-muted text-center py-4 my-auto">No hours logged yet this month.</p>
                        <?php else: ?>
                            <div style="height: 200px; width: 200px; position: relative;" class="mx-auto mb-3">
                                <canvas id="allocationChart"></canvas>
                            </div>
                            <div class="w-100 mt-2 text-center small">
                                <span class="badge bg-primary text-white me-2">Task-Assigned: <?php echo number_format($task_hours, 1); ?> hrs</span>
                                <span class="badge bg-secondary text-white">Manual Entry: <?php echo number_format($manual_hours, 1); ?> hrs</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Attendance Logs -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 fw-bold text-primary"><i class="bi bi-calendar-check-fill me-1"></i> Your Recent Attendance History</h6>
                        <span class="badge bg-success-subtle text-success small fw-semibold">Last 5 Shifts</span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_attendance)): ?>
                            <p class="text-muted text-center py-4 mb-0">No attendance logs found.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Date</th>
                                            <th>Clock In</th>
                                            <th>Clock Out</th>
                                            <th>Shift Duration</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_attendance as $log): ?>
                                            <tr>
                                                <td><span class="fw-semibold text-dark"><?php echo date('D, M d, Y', strtotime($log['date'])); ?></span></td>
                                                <td><span class="text-success fw-semibold"><i class="bi bi-box-arrow-in-right me-1"></i><?php echo date('h:i A', strtotime($log['clock_in'])); ?></span></td>
                                                <td>
                                                    <?php if ($log['clock_out']): ?>
                                                        <span class="text-danger fw-semibold"><i class="bi bi-box-arrow-right me-1"></i><?php echo date('h:i A', strtotime($log['clock_out'])); ?></span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning-subtle text-warning">Active Shift</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($log['duration'] !== null): ?>
                                                        <span class="badge bg-primary-subtle text-primary font-monospace fs-7"><?php echo number_format($log['duration'], 2); ?> hrs</span>
                                                    <?php else: ?>
                                                        <span class="text-muted small">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($log['clock_out']): ?>
                                                        <span class="badge bg-success-subtle text-success"><i class="bi bi-check-circle-fill me-1"></i>Completed</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-primary-subtle text-primary progress-bar-striped progress-bar-animated"><i class="bi bi-play-fill me-1"></i>Active</span>
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
        </div>

        <!-- Heatmap Section -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-transparent py-3">
                        <h6 class="m-0 fw-bold text-primary"><i class="bi bi-calendar3 me-1"></i> Your Activity Heatmap (Last 12 Months)</h6>
                    </div>
                    <div class="card-body">
                        <div class="heatmap-container">
                            <div class="heatmap-grid mb-3">
                                <!-- Y-Axis labels (Sun, Tue, Thu, Sat) -->
                                <div class="d-flex flex-column justify-content-between text-muted me-2 small" style="height: 95px; font-size: 0.7rem; margin-top: 15px;">
                                    <span>Sun</span>
                                    <span>Tue</span>
                                    <span>Thu</span>
                                    <span>Sat</span>
                                </div>
                                
                                <div class="d-flex gap-1 overflow-auto py-1">
                                    <?php foreach ($weeks as $week): ?>
                                        <div class="heatmap-week">
                                            <?php foreach ($week as $day): ?>
                                                <div class="heatmap-day level-<?php echo $day['level']; ?>" 
                                                     data-bs-toggle="tooltip" 
                                                     data-bs-placement="top" 
                                                     title="<?php echo e($day['formatted_date']) . ': ' . number_format($day['hours'], 1) . ' hrs'; ?>">
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <!-- Legend -->
                            <div class="d-flex justify-content-between align-items-center text-muted small border-top pt-3 flex-wrap gap-2">
                                <span>Learn how we count logged hours</span>
                                <div class="d-flex align-items-center gap-1">
                                    <span class="me-1">Less</span>
                                    <div class="heatmap-day level-0" style="cursor: default;"></div>
                                    <div class="heatmap-day level-1" style="cursor: default;"></div>
                                    <div class="heatmap-day level-2" style="cursor: default;"></div>
                                    <div class="heatmap-day level-3" style="cursor: default;"></div>
                                    <div class="heatmap-day level-4" style="cursor: default;"></div>
                                    <span class="ms-1">More</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<!-- Canvas Confetti CDN -->
<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
<script>
<?php if (!empty($success)): ?>
    <?php if (strpos(strtolower($success), 'clocked out') !== false): ?>
        window.triggerConfetti = true;
    <?php elseif (strpos(strtolower($success), 'clocked in') !== false): ?>
        window.triggerClockInConfetti = true;
    <?php endif; ?>
<?php endif; ?>

document.addEventListener('DOMContentLoaded', function () {
    // Blast ribbons if clock-out succeeded!
    if (window.triggerConfetti) {
        // Center burst
        confetti({
            particleCount: 150,
            spread: 80,
            origin: { y: 0.6 }
        });
        // Dual side-cannons 250ms later
        setTimeout(() => {
            confetti({
                particleCount: 80,
                angle: 60,
                spread: 55,
                origin: { x: 0, y: 0.8 }
            });
            confetti({
                particleCount: 80,
                angle: 120,
                spread: 55,
                origin: { x: 1, y: 0.8 }
            });
        }, 250);
    } else if (window.triggerClockInConfetti) {
        // Energetic clock-in burst
        confetti({
            particleCount: 70,
            spread: 50,
            origin: { y: 0.7 }
        });
    }
    // 1. Weekly hours line chart
    const weeklyCtx = document.getElementById('weeklyHoursChart');
    if (weeklyCtx) {
        const labels = <?php echo json_encode($chart_labels); ?>;
        const data = <?php echo json_encode($chart_data); ?>;

        new Chart(weeklyCtx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Hours Worked',
                    data: data,
                    borderColor: '#198754',
                    backgroundColor: 'rgba(25, 135, 84, 0.05)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.35,
                    pointBackgroundColor: '#198754',
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

    // 2. Task vs Manual hours allocation chart
    const allocationCtx = document.getElementById('allocationChart');
    if (allocationCtx) {
        const taskHours = <?php echo $task_hours; ?>;
        const manualHours = <?php echo $manual_hours; ?>;

        new Chart(allocationCtx, {
            type: 'doughnut',
            data: {
                labels: ['Task-Assigned', 'Manual Entry'],
                datasets: [{
                    data: [taskHours, manualHours],
                    backgroundColor: ['#0d6efd', '#6c757d'],
                    hoverBackgroundColor: ['#0b5ed7', '#5c636a'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                cutout: '70%'
            }
        });
    }

    // 3. Dynamic Clock Out countdown timer & live work progress
    const clockOutBtn = document.getElementById('clockOutBtn');
    const clockOutText = document.getElementById('clockOutText');
    const liveWorkedTime = document.getElementById('liveWorkedTime');
    const shiftProgressBar = document.getElementById('shiftProgressBar');

    if (clockOutBtn) {
        let elapsed = parseInt(clockOutBtn.getAttribute('data-elapsed'), 10) || 0;
        const required = parseInt(clockOutBtn.getAttribute('data-required'), 10) || 120;

        function formatDuration(totalSeconds) {
            const hrs = Math.floor(totalSeconds / 3600);
            const mins = Math.floor((totalSeconds % 3600) / 60);
            const secs = totalSeconds % 60;
            return [
                hrs.toString().padStart(2, '0'),
                mins.toString().padStart(2, '0'),
                secs.toString().padStart(2, '0')
            ].join(':');
        }

        function formatRemaining(seconds) {
            if (seconds <= 0) return '';
            const hrs = Math.floor(seconds / 3600);
            const mins = Math.floor((seconds % 3600) / 60);
            const secs = seconds % 60;
            if (hrs > 0) {
                return `${hrs}h ${mins}m remaining`;
            } else if (mins > 0) {
                return `${mins}m ${secs}s remaining`;
            } else {
                return `${secs}s remaining`;
            }
        }

        function updateUI() {
            // Update live elapsed timer
            if (liveWorkedTime) {
                liveWorkedTime.textContent = formatDuration(elapsed);
            }

            // Calculate progress percentage
            const pct = Math.min((elapsed / required) * 100, 100);
            if (shiftProgressBar) {
                shiftProgressBar.style.width = pct.toFixed(1) + '%';
                shiftProgressBar.setAttribute('aria-valuenow', Math.round(pct));
            }

            // Update remaining label
            const remaining = required - elapsed;
            if (remaining > 0) {
                clockOutBtn.setAttribute('disabled', 'true');
                
                const timeStr = formatRemaining(remaining);
                if (clockOutText) {
                    clockOutText.textContent = `Clock Out (Lock: ${timeStr})`;
                }
            } else {
                clockOutBtn.removeAttribute('disabled');
                if (clockOutText) {
                    clockOutText.textContent = 'Clock Out';
                }
                if (shiftProgressBar) {
                    shiftProgressBar.classList.remove('progress-bar-striped', 'progress-bar-animated');
                }
            }
        }

        // Run immediately
        updateUI();

        // Tick every second
        const timerInterval = setInterval(() => {
            elapsed++;
            updateUI();
        }, 1000);
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
