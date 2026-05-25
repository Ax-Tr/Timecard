<?php
// Admin Task Assignments
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$error = '';
$success = '';

// Handling POST CRUD requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $token = $_POST['csrf_token'] ?? '';

    if (!verify_csrf($token)) {
        $error = 'Invalid security token (CSRF).';
    } else {
        if ($action === 'add') {
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $assigned_to = (int)($_POST['assigned_to'] ?? 0);
            $priority = trim($_POST['priority'] ?? 'medium');
            $deadline = trim($_POST['deadline'] ?? '');
            $est_duration = (float)($_POST['estimated_duration'] ?? 0);

            if (empty($title) || $assigned_to <= 0 || empty($deadline) || $est_duration <= 0) {
                $error = 'All fields except description are required, and duration must be greater than 0.';
            } elseif (strtotime($deadline) < strtotime(date('Y-m-d'))) {
                $error = 'Deadline cannot be in the past.';
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO tasks (title, description, assigned_to, priority, deadline, estimated_duration) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$title, $description, $assigned_to, $priority, $deadline, $est_duration]);

                    // Add Notification for Employee
                    // Get employee name
                    $emp_stmt = $pdo->prepare("SELECT emp_id FROM employees WHERE id = ?");
                    $emp_stmt->execute([$assigned_to]);
                    $emp_id = $emp_stmt->fetchColumn();
                    
                    add_notification($assigned_to, "New task assigned: {$title}. Deadline: {$deadline}");
                    log_activity($_SESSION['user_id'], "Assigned new task '{$title}' to Employee {$emp_id}");

                    $success = "Task assigned successfully.";
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }

        if ($action === 'edit') {
            $task_id = (int)($_POST['id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $assigned_to = (int)($_POST['assigned_to'] ?? 0);
            $priority = trim($_POST['priority'] ?? 'medium');
            $deadline = trim($_POST['deadline'] ?? '');
            $est_duration = (float)($_POST['estimated_duration'] ?? 0);

            if (empty($title) || $assigned_to <= 0 || empty($deadline) || $est_duration <= 0) {
                $error = 'All fields except description are required, and duration must be greater than 0.';
            } else {
                try {
                    // Update Task
                    $stmt = $pdo->prepare("UPDATE tasks SET title = ?, description = ?, assigned_to = ?, priority = ?, deadline = ?, estimated_duration = ? WHERE id = ?");
                    $stmt->execute([$title, $description, $assigned_to, $priority, $deadline, $est_duration, $task_id]);

                    log_activity($_SESSION['user_id'], "Updated details for Task ID {$task_id}");
                    $success = "Task updated successfully.";
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }

        if ($action === 'delete') {
            $task_id = (int)($_POST['id'] ?? 0);
            try {
                $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
                $stmt->execute([$task_id]);

                log_activity($_SESSION['user_id'], "Deleted Task ID {$task_id}");
                $success = "Task deleted successfully.";
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Fetch Filters
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$emp_filter = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
$priority_filter = isset($_GET['priority']) ? trim($_GET['priority']) : '';

$where = ["1 = 1"];
$params = [];

if ($status_filter !== '') {
    $where[] = "t.status = ?";
    $params[] = $status_filter;
}

if ($emp_filter > 0) {
    $where[] = "t.assigned_to = ?";
    $params[] = $emp_filter;
}

if ($priority_filter !== '') {
    $where[] = "t.priority = ?";
    $params[] = $priority_filter;
}

$where_sql = implode(" AND ", $where);

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

try {
    // 1. Fetch active employees for dropdown
    $employees_list = $pdo->query("SELECT id as user_id, emp_id, first_name, last_name FROM employees WHERE status = 'active' ORDER BY emp_id ASC")->fetchAll();

    // 2. Fetch counts
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks t WHERE $where_sql");
    $count_stmt->execute($params);
    $total_rows = $count_stmt->fetchColumn();
    $total_pages = ceil($total_rows / $limit);

    // 3. Fetch tasks page data
    $select_stmt = $pdo->prepare("
        SELECT t.*, e.emp_id, e.first_name, e.last_name, tu.notes, tu.actual_duration, tu.created_at as completed_at 
        FROM tasks t 
        JOIN employees e ON t.assigned_to = e.id 
        LEFT JOIN task_updates tu ON t.id = tu.task_id 
        WHERE $where_sql 
        ORDER BY t.created_at DESC 
        LIMIT " . (int)$offset . ", " . (int)$limit
    );
    $select_stmt->execute($params);
    $tasks = $select_stmt->fetchAll();

} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

$page_title = 'Task Assignments';
require_once __DIR__ . '/../includes/header.php';
?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<!-- Main Content Area -->
<main class="main-content d-flex flex-column flex-grow-1">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container-fluid p-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h1 class="h3 mb-0 text-gray-800">Task Assignments</h1>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#assignTaskModal">
                <i class="bi bi-plus-circle-fill me-1"></i> Assign New Task
            </button>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 small"><?php echo e($error); ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success py-2 small"><?php echo e($success); ?></div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form action="" method="GET" class="row g-3">
                    <div class="col-md-4">
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
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Status</label>
                        <select class="form-select" name="status">
                            <option value="">All Statuses</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Priority</label>
                        <select class="form-select" name="priority">
                            <option value="">All Priorities</option>
                            <option value="low" <?php echo $priority_filter === 'low' ? 'selected' : ''; ?>>Low</option>
                            <option value="medium" <?php echo $priority_filter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="high" <?php echo $priority_filter === 'high' ? 'selected' : ''; ?>>High</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-secondary w-100 fw-medium">Apply</button>
                        <a href="tasks" class="btn btn-outline-secondary w-50">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tasks list -->
        <div class="row g-3">
            <?php if (empty($tasks)): ?>
                <div class="col-12">
                    <div class="card p-5 text-center text-muted">
                        <i class="bi bi-clipboard2-x fs-1 mb-2"></i>
                        <p class="mb-0">No assigned tasks found matching the criteria.</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($tasks as $task): ?>
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="card h-100 d-flex flex-column justify-content-between hover-lift">
                            <div class="card-body">
                                <!-- Top status + priority header -->
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="badge <?php echo $task['priority'] === 'high' ? 'bg-danger-subtle text-danger' : ($task['priority'] === 'medium' ? 'bg-warning-subtle text-warning' : 'bg-info-subtle text-info'); ?>">
                                        <?php echo ucfirst(e($task['priority'])); ?> Priority
                                    </span>
                                    <span class="badge <?php echo $task['status'] === 'completed' ? 'bg-success text-white' : 'bg-secondary text-white'; ?>">
                                        <?php echo ucfirst(e($task['status'])); ?>
                                    </span>
                                </div>

                                <h5 class="card-title fw-bold text-truncate mb-2" title="<?php echo e($task['title']); ?>"><?php echo e($task['title']); ?></h5>
                                <p class="card-text text-muted small text-truncate-3 mb-3"><?php echo e($task['description'] ?: 'No description provided.'); ?></p>

                                <div class="border-top pt-3 small text-secondary">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span><strong>Assigned To:</strong></span>
                                        <span><?php echo e($task['first_name'] . ' ' . $task['last_name']); ?> (<?php echo e($task['emp_id']); ?>)</span>
                                    </div>
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

                                <!-- If Completed show details -->
                                <?php if ($task['status'] === 'completed'): ?>
                                    <div class="bg-light p-2 rounded mt-3 small border border-success-subtle text-dark">
                                        <div class="fw-bold text-success mb-1"><i class="bi bi-check-circle-fill"></i> Completion Details:</div>
                                        <div><strong>Actual Time:</strong> <?php echo number_format($task['actual_duration'], 1); ?> hrs</div>
                                        <div class="text-truncate" title="<?php echo e($task['notes']); ?>"><strong>Notes:</strong> <?php echo e($task['notes'] ?: 'None'); ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="card-footer bg-transparent py-3 border-top-0 d-flex justify-content-end gap-2">
                                <?php if ($task['status'] === 'pending'): ?>
                                    <button class="btn btn-outline-secondary btn-sm" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editTaskModal"
                                            data-id="<?php echo $task['id']; ?>"
                                            data-title="<?php echo e($task['title']); ?>"
                                            data-desc="<?php echo e($task['description']); ?>"
                                            data-assigned="<?php echo $task['assigned_to']; ?>"
                                            data-priority="<?php echo e($task['priority']); ?>"
                                            data-deadline="<?php echo e($task['deadline']); ?>"
                                            data-duration="<?php echo $task['estimated_duration']; ?>">
                                        <i class="bi bi-pencil me-1"></i> Edit
                                    </button>
                                <?php endif; ?>
                                <button class="btn btn-outline-danger btn-sm" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#deleteTaskModal"
                                        data-id="<?php echo $task['id']; ?>"
                                        data-title="<?php echo e($task['title']); ?>">
                                    <i class="bi bi-trash me-1"></i> Delete
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation" class="d-flex justify-content-center mt-4">
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

<!-- ASSIGN TASK MODAL -->
<div class="modal fade" id="assignTaskModal" tabindex="-1" aria-labelledby="assignTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title" id="assignTaskModalLabel">Assign New Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Task Title</label>
                            <input type="text" class="form-control" name="title" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Task Description</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Assign To</label>
                            <select class="form-select" name="assigned_to" required>
                                <option value="">Select Employee</option>
                                <?php foreach ($employees_list as $emp): ?>
                                    <option value="<?php echo $emp['user_id']; ?>">
                                        [<?php echo e($emp['emp_id']); ?>] <?php echo e($emp['first_name'] . ' ' . $emp['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Priority</label>
                            <select class="form-select" name="priority">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Deadline</label>
                            <input type="date" class="form-control" name="deadline" min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Est. Duration (Hours)</label>
                            <input type="number" class="form-control" name="estimated_duration" step="0.25" min="0.25" placeholder="e.g. 8.5" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Assign Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- EDIT TASK MODAL -->
<div class="modal fade" id="editTaskModal" tabindex="-1" aria-labelledby="editTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editTaskId">
                <div class="modal-header">
                    <h5 class="modal-title" id="editTaskModalLabel">Edit Task Assignment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Task Title</label>
                            <input type="text" class="form-control" name="title" id="editTaskTitle" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Task Description</label>
                            <textarea class="form-control" name="description" id="editTaskDesc" rows="3"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Assign To</label>
                            <select class="form-select" name="assigned_to" id="editTaskAssigned" required>
                                <?php foreach ($employees_list as $emp): ?>
                                    <option value="<?php echo $emp['user_id']; ?>">
                                        [<?php echo e($emp['emp_id']); ?>] <?php echo e($emp['first_name'] . ' ' . $emp['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Priority</label>
                            <select class="form-select" name="priority" id="editTaskPriority">
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Deadline</label>
                            <input type="date" class="form-control" name="deadline" id="editTaskDeadline" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Est. Duration (Hours)</label>
                            <input type="number" class="form-control" name="estimated_duration" id="editTaskDuration" step="0.25" min="0.25" required>
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

<!-- DELETE TASK MODAL -->
<div class="modal fade" id="deleteTaskModal" tabindex="-1" aria-labelledby="deleteTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteTaskId">
                <div class="modal-header">
                    <h5 class="modal-title text-danger" id="deleteTaskModalLabel"><i class="bi bi-exclamation-triangle-fill me-1"></i> Delete Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <p class="mb-0">Are you sure you want to permanently delete task <strong id="deleteTaskTitleLabel"></strong>?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Confirm Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Populate Edit Task Modal
    const editModal = document.getElementById('editTaskModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            document.getElementById('editTaskId').value = button.getAttribute('data-id');
            document.getElementById('editTaskTitle').value = button.getAttribute('data-title');
            document.getElementById('editTaskDesc').value = button.getAttribute('data-desc');
            document.getElementById('editTaskAssigned').value = button.getAttribute('data-assigned');
            document.getElementById('editTaskPriority').value = button.getAttribute('data-priority');
            document.getElementById('editTaskDeadline').value = button.getAttribute('data-deadline');
            document.getElementById('editTaskDuration').value = button.getAttribute('data-duration');
        });
    }

    // Populate Delete Task Modal
    const deleteModal = document.getElementById('deleteTaskModal');
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            document.getElementById('deleteTaskId').value = button.getAttribute('data-id');
            document.getElementById('deleteTaskTitleLabel').textContent = button.getAttribute('data-title');
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
