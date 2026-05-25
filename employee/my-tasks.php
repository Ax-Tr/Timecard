<?php
// Employee Task List & Completion Handling
$page_title = 'My Tasks';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Secure page access
if (is_admin()) {
    header("Location: " . APP_ROOT . "admin/dashboard");
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Load System Settings (for timesheet approval status)
$settings_file = __DIR__ . '/../config/settings.json';
$require_approval = true;
if (file_exists($settings_file)) {
    $settings = json_decode(file_get_contents($settings_file), true);
    $require_approval = $settings['enable_approvals'] ?? true;
}

// Handler for Task Completion POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'complete') {
    $task_id = (int)($_POST['task_id'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    $actual_duration = (float)($_POST['actual_duration'] ?? 0);
    $token = $_POST['csrf_token'] ?? '';

    if (!verify_csrf($token)) {
        $error = 'Security token invalid (CSRF).';
    } elseif ($task_id <= 0 || $actual_duration <= 0) {
        $error = 'Actual duration spent is required and must be greater than 0.';
    } else {
        try {
            // Verify ownership and check task status is pending
            $check_stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ? AND assigned_to = ? AND status = 'pending'");
            $check_stmt->execute([$task_id, $user_id]);
            $task = $check_stmt->fetch();

            if (!$task) {
                $error = 'Task not found or already completed.';
            } else {
                $pdo->beginTransaction();

                // 1. Insert into task_updates
                $update_stmt = $pdo->prepare("INSERT INTO task_updates (task_id, notes, actual_duration) VALUES (?, ?, ?)");
                $update_stmt->execute([$task_id, $notes, $actual_duration]);

                // 2. Set task status as 'completed'
                $task_status_stmt = $pdo->prepare("UPDATE tasks SET status = 'completed' WHERE id = ?");
                $task_status_stmt->execute([$task_id]);

                // 3. Automatically create a timesheet entry
                // Description pattern: Task Completed: [Task Title]. Notes: [Completion Notes]
                $timesheet_desc = "Task Completed: {$task['title']}." . (!empty($notes) ? " Notes: {$notes}" : "");
                $timesheet_status = $require_approval ? 'pending' : 'approved';
                
                $timesheet_stmt = $pdo->prepare("INSERT INTO timesheets (user_id, task_id, date, duration, description, status) VALUES (?, ?, CURDATE(), ?, ?, ?)");
                $timesheet_stmt->execute([$user_id, $task_id, $actual_duration, $timesheet_desc, $timesheet_status]);

                // 4. Notify admin in dashboard
                // Get employee ID
                $emp_stmt = $pdo->prepare("SELECT emp_id, first_name, last_name FROM employees WHERE id = ?");
                $emp_stmt->execute([$user_id]);
                $emp = $emp_stmt->fetch();
                $emp_display = "[{$emp['emp_id']}] {$emp['first_name']} {$emp['last_name']}";

                $admin_msg = "{$emp_display} completed task: '{$task['title']}' spending " . number_format($actual_duration, 1) . " hrs.";
                add_notification(null, $admin_msg); // user_id is NULL for admin

                log_activity($user_id, "Completed task: '{$task['title']}'");

                $pdo->commit();
                $success = "Task completed successfully! A timesheet entry has been created automatically.";
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Fetch Filters
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'pending'; // Default to pending

try {
    // Fetch employee's tasks
    $tasks_stmt = $pdo->prepare("
        SELECT t.*, tu.notes, tu.actual_duration, tu.created_at as completed_at 
        FROM tasks t 
        LEFT JOIN task_updates tu ON t.id = tu.task_id 
        WHERE t.assigned_to = ? 
        ORDER BY t.deadline ASC, t.priority DESC
    ");
    $tasks_stmt->execute([$user_id]);
    $all_tasks = $tasks_stmt->fetchAll();

    // Filter tasks for list view compatibility
    $tasks = array_filter($all_tasks, function($t) use ($status_filter) {
        return $t['status'] === $status_filter;
    });
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

require_once __DIR__ . '/../includes/header.php';
?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<!-- Main Content Area -->
<main class="main-content d-flex flex-column flex-grow-1">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
            <div>
                <h1 class="h3 mb-1 text-gray-800">My Tasks</h1>
                <p class="text-muted small mb-0">Track and complete tasks assigned to you by administrators.</p>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 small"><?php echo e($error); ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success py-2 small"><?php echo e($success); ?></div>
        <?php endif; ?>

        <!-- Filters Tabs -->
        <div class="card mb-4">
            <div class="card-body py-2">
                <ul class="nav nav-pills card-header-pills">
                    <li class="nav-item me-2">
                        <a class="nav-link fw-medium <?php echo $status_filter === 'pending' ? 'active bg-primary' : 'text-secondary'; ?>" href="?status=pending">
                            Pending Tasks
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-medium <?php echo $status_filter === 'completed' ? 'active bg-primary' : 'text-secondary'; ?>" href="?status=completed">
                            Completed Tasks
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- View Toggle Buttons -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div></div>
            <div class="btn-group" role="group" aria-label="Tasks view toggle">
                <button type="button" class="btn btn-outline-primary btn-sm active" id="listViewBtn">
                    <i class="bi bi-grid-3x3-gap-fill me-1"></i> List View
                </button>
                <button type="button" class="btn btn-outline-primary btn-sm" id="kanbanViewBtn">
                    <i class="bi bi-kanban me-1"></i> Kanban Board
                </button>
            </div>
        </div>

        <!-- Task List Rows -->
        <div id="listViewContainer" class="row g-3">
            <?php if (empty($all_tasks)): ?>
                <div class="col-12">
                    <div class="card p-5 text-center text-muted">
                        <i class="bi bi-clipboard-check fs-1 mb-2"></i>
                        <p class="mb-0">No <?php echo e($status_filter); ?> tasks found.</p>
                    </div>
                </div>
                <?php foreach ($all_tasks as $task): ?>
                    <div class="col-12 col-md-6 col-lg-4 task-card-wrapper <?php echo $task['status'] !== $status_filter ? 'd-none' : ''; ?>" data-id="<?php echo $task['id']; ?>" data-status="<?php echo e($task['status']); ?>">
                        <div class="card h-100 d-flex flex-column justify-content-between hover-lift">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="badge <?php echo $task['priority'] === 'high' ? 'bg-danger-subtle text-danger' : ($task['priority'] === 'medium' ? 'bg-warning-subtle text-warning' : 'bg-info-subtle text-info'); ?>">
                                        <?php echo ucfirst(e($task['priority'])); ?> Priority
                                    </span>
                                    <span class="badge <?php echo $task['status'] === 'completed' ? 'bg-success' : 'bg-secondary'; ?>">
                                        <?php echo ucfirst(e($task['status'])); ?>
                                    </span>
                                </div>

                                <h5 class="card-title fw-bold text-truncate mb-2" title="<?php echo e($task['title']); ?>"><?php echo e($task['title']); ?></h5>
                                <p class="card-text text-muted small text-truncate-3 mb-3"><?php echo e($task['description'] ?: 'No description provided.'); ?></p>

                                <div class="border-top pt-3 small text-secondary">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span><strong>Deadline:</strong></span>
                                        <span class="<?php echo (strtotime($task['deadline']) < time() && $task['status'] === 'pending') ? 'text-danger fw-bold' : ''; ?>">
                                            <?php echo e($task['deadline']); ?>
                                        </span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span><strong>Est. Duration:</strong></span>
                                        <span><?php echo number_format($task['estimated_duration'], 1); ?> hrs</span>
                                    </div>
                                </div>

                                <?php if ($task['status'] === 'completed'): ?>
                                    <div class="bg-light p-2 rounded mt-3 small border border-success-subtle text-dark">
                                        <div class="fw-bold text-success mb-1"><i class="bi bi-check-circle-fill"></i> Completed:</div>
                                        <div><strong>Actual Time:</strong> <?php echo number_format($task['actual_duration'], 1); ?> hrs</div>
                                        <div class="text-truncate" title="<?php echo e($task['notes']); ?>"><strong>Notes:</strong> <?php echo e($task['notes'] ?: 'None'); ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($task['status'] === 'pending'): ?>
                                <div class="card-footer bg-transparent py-3 border-top-0 d-flex justify-content-end">
                                    <button class="btn btn-success btn-sm w-100 fw-semibold" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#completeTaskModal"
                                            data-id="<?php echo $task['id']; ?>"
                                            data-title="<?php echo e($task['title']); ?>"
                                            data-est="<?php echo $task['estimated_duration']; ?>">
                                        <i class="bi bi-check-lg me-1"></i> Mark as Completed
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Kanban View Container -->
        <div id="kanbanViewContainer" class="d-none mb-4">
            <div class="kanban-board">
                <!-- Pending Column -->
                <div class="kanban-col" id="col-pending">
                    <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                        <h6 class="fw-bold text-secondary mb-0">PENDING TASKS</h6>
                        <span class="badge bg-secondary rounded-pill" id="count-pending">0</span>
                    </div>
                    <div class="kanban-cards-container" data-status="pending"></div>
                </div>

                <!-- Completed Column -->
                <div class="kanban-col" id="col-completed">
                    <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                        <h6 class="fw-bold text-success mb-0">COMPLETED TASKS</h6>
                        <span class="badge bg-success rounded-pill" id="count-completed">0</span>
                    </div>
                    <div class="kanban-cards-container" data-status="completed"></div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- COMPLETE TASK MODAL -->
<div class="modal fade" id="completeTaskModal" tabindex="-1" aria-labelledby="completeTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="action" value="complete">
                <input type="hidden" name="task_id" id="completeTaskId">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="completeTaskModalLabel">Complete Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">Task: <strong id="completeTaskTitleLabel"></strong></p>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Actual Hours Spent</label>
                        <input type="number" class="form-control" name="actual_duration" id="completeTaskDuration" step="0.25" min="0.25" max="24" required>
                        <small class="text-muted d-block mt-1">This will automatically generate a timesheet entry for today.</small>
                    </div>

                    <div class="mb-1">
                        <label class="form-label small fw-semibold">Completion Notes</label>
                        <textarea class="form-control" name="notes" rows="4" placeholder="Describe the outcome or findings..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success fw-semibold">Submit Completion</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const completeModal = document.getElementById('completeTaskModal');
    if (completeModal) {
        completeModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            document.getElementById('completeTaskId').value = button.getAttribute('data-id');
            document.getElementById('completeTaskTitleLabel').textContent = button.getAttribute('data-title');
            document.getElementById('completeTaskDuration').value = button.getAttribute('data-est');
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
