<?php
// Employee Add Timesheet & Same-day Edit Panel
$page_title = 'Log Timesheet';
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
$warning = '';

// Load System Settings
$settings_file = __DIR__ . '/../config/settings.json';
$max_daily_hours = 12; // default fallback
$require_approval = true;
if (file_exists($settings_file)) {
    $settings = json_decode(file_get_contents($settings_file), true);
    $max_daily_hours = $settings['max_daily_hours'] ?? 12;
    $require_approval = $settings['enable_approvals'] ?? true;
}

// Handler for forms
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    if (!verify_csrf($token)) {
        $error = 'Invalid request security token (CSRF).';
    } else {
        // 1. ADD NEW TIMESHEET ENTRY
        if ($action === 'add') {
            $date = trim($_POST['date'] ?? '');
            $duration = (float)($_POST['duration'] ?? 0);
            $description = trim($_POST['description'] ?? '');
            $task_id = !empty($_POST['task_id']) && $_POST['task_id'] !== 'other' ? (int)$_POST['task_id'] : null;
            $custom_task_name = trim($_POST['custom_task_name'] ?? '');

            // If "Other" was selected, prepend custom task name to description
            if ($_POST['task_id'] === 'other' && !empty($custom_task_name)) {
                $description = "[Task: {$custom_task_name}] " . $description;
            }

            if (empty($date) || $duration <= 0 || empty($description)) {
                $error = 'All fields are required and duration must be greater than 0.';
            } elseif (strtotime($date) > strtotime(date('Y-m-d'))) {
                $error = 'You cannot log timesheets for future dates.';
            } else {
                try {
                    // Check duplicate for the same task on the same date
                    if ($task_id !== null) {
                        $chk = $pdo->prepare("SELECT COUNT(*) FROM timesheets WHERE user_id = ? AND date = ? AND task_id = ?");
                        $chk->execute([$user_id, $date, $task_id]);
                        if ($chk->fetchColumn() > 0) {
                            $error = 'You have already logged a timesheet for this task on the selected date.';
                        }
                    }

                    if (empty($error)) {
                        // Check daily hours threshold
                        $sum_stmt = $pdo->prepare("SELECT SUM(duration) FROM timesheets WHERE user_id = ? AND date = ?");
                        $sum_stmt->execute([$user_id, $date]);
                        $logged_hours = $sum_stmt->fetchColumn() ?? 0;
                        
                        $total_after_insert = $logged_hours + $duration;
                        if ($total_after_insert > 24) {
                            $error = 'Total logged hours for a single day cannot exceed 24 hours.';
                        } else {
                            if ($total_after_insert > $max_daily_hours) {
                                $warning = "Notice: Your total hours for {$date} will be " . number_format($total_after_insert, 1) . " hrs, which exceeds the daily limit warning threshold (" . number_format($max_daily_hours, 1) . " hrs).";
                            }

                            // Insert Timesheet (Status is pending if approvals are required)
                            $status = $require_approval ? 'pending' : 'approved';
                            $stmt = $pdo->prepare("INSERT INTO timesheets (user_id, task_id, date, duration, description, status) VALUES (?, ?, ?, ?, ?, ?)");
                            $stmt->execute([$user_id, $task_id, $date, $duration, $description, $status]);

                            log_activity($user_id, "Logged {$duration} hours for date {$date}");
                            $success = 'Timesheet logged successfully!';
                            
                            // Clear form values
                            $_POST = [];
                        }
                    }
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }

        // 2. EDIT SAME-DAY TIMESHEET ENTRY
        if ($action === 'edit') {
            $timesheet_id = (int)($_POST['id'] ?? 0);
            $duration = (float)($_POST['duration'] ?? 0);
            $description = trim($_POST['description'] ?? '');

            if ($timesheet_id <= 0 || $duration <= 0 || empty($description)) {
                $error = 'All fields are required and duration must be greater than 0.';
            } else {
                try {
                    // Verify ownership and date (MUST be same-day: date must match CURDATE())
                    $verify_stmt = $pdo->prepare("SELECT date, duration FROM timesheets WHERE id = ? AND user_id = ?");
                    $verify_stmt->execute([$timesheet_id, $user_id]);
                    $record = $verify_stmt->fetch();

                    if (!$record) {
                        $error = 'Timesheet record not found.';
                    } elseif ($record['date'] !== date('Y-m-d')) {
                        $error = 'You can only edit timesheet entries logged on the current day.';
                    } else {
                        // Check daily hours threshold
                        $sum_stmt = $pdo->prepare("SELECT SUM(duration) FROM timesheets WHERE user_id = ? AND date = ? AND id != ?");
                        $sum_stmt->execute([$user_id, date('Y-m-d'), $timesheet_id]);
                        $logged_hours = $sum_stmt->fetchColumn() ?? 0;

                        $total_after_update = $logged_hours + $duration;
                        if ($total_after_update > 24) {
                            $error = 'Total logged hours for a single day cannot exceed 24 hours.';
                        } else {
                            if ($total_after_update > $max_daily_hours) {
                                $warning = "Notice: Your updated daily hours will be " . number_format($total_after_update, 1) . " hrs, which exceeds the daily limit warning threshold (" . number_format($max_daily_hours, 1) . " hrs).";
                            }

                            // Update
                            $update_stmt = $pdo->prepare("UPDATE timesheets SET duration = ?, description = ?, status = 'pending' WHERE id = ?");
                            $update_stmt->execute([$duration, $description, $timesheet_id]);

                            log_activity($user_id, "Updated timesheet entry ID {$timesheet_id}");
                            $success = 'Timesheet entry updated successfully!';
                        }
                    }
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }

        // 3. DELETE SAME-DAY TIMESHEET ENTRY
        if ($action === 'delete') {
            $timesheet_id = (int)($_POST['id'] ?? 0);
            try {
                // Verify ownership and same-day date
                $verify_stmt = $pdo->prepare("SELECT date FROM timesheets WHERE id = ? AND user_id = ?");
                $verify_stmt->execute([$timesheet_id, $user_id]);
                $record = $verify_stmt->fetch();

                if (!$record) {
                    $error = 'Timesheet record not found.';
                } elseif ($record['date'] !== date('Y-m-d')) {
                    $error = 'You can only delete timesheet entries logged on the current day.';
                } else {
                    $del_stmt = $pdo->prepare("DELETE FROM timesheets WHERE id = ?");
                    $del_stmt->execute([$timesheet_id]);

                    log_activity($user_id, "Deleted timesheet entry ID {$timesheet_id}");
                    $success = 'Timesheet entry deleted successfully!';
                }
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Fetch dynamic helper options
try {
    // 1. Pending tasks assigned to this employee (for dropdown)
    $tasks_stmt = $pdo->prepare("SELECT id, title FROM tasks WHERE assigned_to = ? AND status = 'pending' ORDER BY deadline ASC");
    $tasks_stmt->execute([$user_id]);
    $assigned_tasks = $tasks_stmt->fetchAll();

    // 2. Fetch today's logged entries for this employee (for edit/delete)
    $todays_entries_stmt = $pdo->prepare("
        SELECT t.*, tk.title as task_title 
        FROM timesheets t 
        LEFT JOIN tasks tk ON t.task_id = tk.id 
        WHERE t.user_id = ? AND t.date = CURDATE() 
        ORDER BY t.created_at DESC
    ");
    $todays_entries_stmt->execute([$user_id]);
    $todays_entries = $todays_entries_stmt->fetchAll();
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
        <div class="mb-4">
            <h1 class="h3 mb-1 text-gray-800">Log Hours</h1>
            <p class="text-muted small">Record your worked hours and manage today's logs.</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 small"><?php echo e($error); ?></div>
        <?php endif; ?>
        <?php if (!empty($warning)): ?>
            <div class="alert alert-warning py-2 small"><?php echo e($warning); ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success py-2 small"><?php echo e($success); ?></div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Add Form (Left Column) -->
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header bg-transparent py-3">
                        <h6 class="m-0 fw-bold text-primary"><i class="bi bi-calendar-plus me-1"></i> Log Hours Form</h6>
                    </div>
                    <div class="card-body">
                        <form action="" method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="action" value="add">

                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Select Date</label>
                                <input type="date" class="form-control" name="date" max="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Associated Task (Optional)</label>
                                <select class="form-select" name="task_id" id="taskSelector">
                                    <option value="">-- No Specific Task / General Work --</option>
                                    <?php foreach ($assigned_tasks as $task): ?>
                                        <option value="<?php echo $task['id']; ?>" <?php echo (isset($_POST['task_id']) && $_POST['task_id'] == $task['id']) ? 'selected' : ''; ?>>
                                            <?php echo e($task['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <option value="other" <?php echo (isset($_POST['task_id']) && $_POST['task_id'] === 'other') ? 'selected' : ''; ?>>Other (Enter Task Name)</option>
                                </select>
                            </div>

                            <div class="mb-3" id="customTaskNameContainer" style="display: none;">
                                <label class="form-label small fw-semibold">Custom Task Name</label>
                                <input type="text" class="form-control" name="custom_task_name" id="customTaskNameInput" placeholder="Enter your task name..." value="<?php echo e($_POST['custom_task_name'] ?? ''); ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Worked Duration (Hours)</label>
                                <input type="number" class="form-control" name="duration" step="0.25" min="0.25" max="24" placeholder="e.g. 8.5" value="<?php echo e($_POST['duration'] ?? ''); ?>" required>
                            </div>

                            <div class="mb-4">
                                <label class="form-label small fw-semibold">Description of Work</label>
                                <textarea class="form-control" name="description" rows="4" placeholder="Briefly describe what you worked on..." required><?php echo e($_POST['description'] ?? ''); ?></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 fw-semibold">Log Hours</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Today's Logs (Right Column) -->
            <div class="col-lg-7">
                <div class="card h-100">
                    <div class="card-header bg-transparent py-3">
                        <h6 class="m-0 fw-bold text-primary"><i class="bi bi-clock-history me-1"></i> Today's Entries (Editable)</h6>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($todays_entries)): ?>
                            <div class="text-center py-5 text-muted">
                                <i class="bi bi-calendar-x fs-1 mb-2"></i>
                                <p class="mb-0">You have not logged any hours today.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Task</th>
                                            <th>Hours</th>
                                            <th>Description</th>
                                            <th>Status</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($todays_entries as $entry): ?>
                                            <tr>
                                                <td>
                                                    <?php if ($entry['task_title']): ?>
                                                        <span class="fw-medium">
                                                            <i class="bi bi-tag-fill me-1 text-muted"></i><?php echo e($entry['task_title']); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted small">Manual Entry</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><span class="badge bg-primary-subtle text-primary fs-6"><?php echo number_format($entry['duration'], 1); ?> hrs</span></td>
                                                <td>
                                                    <span class="text-truncate d-inline-block" style="max-width: 150px;" title="<?php echo e($entry['description']); ?>">
                                                        <?php echo e($entry['description']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $entry['status'] === 'approved' ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning'; ?>">
                                                        <?php echo ucfirst(e($entry['status'])); ?>
                                                    </span>
                                                </td>
                                                <td class="text-end">
                                                    <div class="btn-group">
                                                        <button class="btn btn-outline-secondary btn-sm" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#editEntryModal"
                                                                data-id="<?php echo $entry['id']; ?>"
                                                                data-duration="<?php echo $entry['duration']; ?>"
                                                                data-desc="<?php echo e($entry['description']); ?>">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <button class="btn btn-outline-danger btn-sm" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#deleteEntryModal"
                                                                data-id="<?php echo $entry['id']; ?>">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
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
            </div>
        </div>
    </div>
</main>

<!-- EDIT SAME-DAY MODAL -->
<div class="modal fade" id="editEntryModal" tabindex="-1" aria-labelledby="editEntryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editEntryId">
                <div class="modal-header">
                    <h5 class="modal-title" id="editEntryModalLabel">Edit Today's Timesheet</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Worked Duration (Hours)</label>
                            <input type="number" class="form-control" name="duration" id="editEntryDuration" step="0.25" min="0.25" max="24" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Description of Work</label>
                            <textarea class="form-control" name="description" id="editEntryDesc" rows="4" required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- DELETE SAME-DAY CONFIRMATION MODAL -->
<div class="modal fade" id="deleteEntryModal" tabindex="-1" aria-labelledby="deleteEntryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form action="" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteEntryId">
                <div class="modal-header">
                    <h5 class="modal-title text-danger" id="deleteEntryModalLabel"><i class="bi bi-exclamation-triangle-fill"></i> Delete Log</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    Are you sure you want to delete this timesheet log?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No</button>
                    <button type="submit" class="btn btn-danger">Yes, Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Task selector: show/hide custom task name input
    const taskSelector = document.getElementById('taskSelector');
    const customContainer = document.getElementById('customTaskNameContainer');
    const customInput = document.getElementById('customTaskNameInput');

    function toggleCustomTask() {
        if (taskSelector.value === 'other') {
            customContainer.style.display = 'block';
            customInput.setAttribute('required', 'required');
        } else {
            customContainer.style.display = 'none';
            customInput.removeAttribute('required');
        }
    }

    if (taskSelector) {
        taskSelector.addEventListener('change', toggleCustomTask);
        toggleCustomTask(); // Run on page load in case of form re-render
    }

    // Populate Edit modal
    const editModal = document.getElementById('editEntryModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            document.getElementById('editEntryId').value = button.getAttribute('data-id');
            document.getElementById('editEntryDuration').value = button.getAttribute('data-duration');
            document.getElementById('editEntryDesc').value = button.getAttribute('data-desc');
        });
    }

    // Populate Delete modal
    const deleteModal = document.getElementById('deleteEntryModal');
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            document.getElementById('deleteEntryId').value = button.getAttribute('data-id');
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
