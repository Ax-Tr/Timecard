<?php
// Sidebar Navigation Template
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';

$current_page = basename($_SERVER['SCRIPT_NAME'], '.php');
$role = $_SESSION['role'] ?? 'employee';
?>
<aside id="sidebar" class="bg-dark text-white border-end border-dark" style="min-width: 240px; max-width: 240px; transition: all 0.3s ease;">
    <div class="sidebar-header p-3 border-bottom border-secondary d-flex align-items-center justify-content-center">
        <i class="bi bi-clock-history fs-3 me-2 text-primary"></i>
        <span class="fs-5 fw-bold text-white uppercase letter-spacing-1">Rhine App</span>
    </div>
    
    <div class="sidebar-user p-3 border-bottom border-secondary bg-dark-subtle text-white-50 d-flex align-items-center">
        <div class="avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px; font-weight: bold;">
            <?php echo strtoupper(substr($_SESSION['username'] ?? 'U', 0, 2)); ?>
        </div>
        <div>
            <h6 class="mb-0 text-white fw-bold" style="font-size: 0.9rem;"><?php echo e($_SESSION['username'] ?? 'User'); ?></h6>
            <span class="badge bg-secondary text-uppercase" style="font-size: 0.7rem;"><?php echo $role; ?></span>
        </div>
    </div>

    <div class="p-3">
        <ul class="nav nav-pills flex-column mb-auto">
            <?php if (is_admin()): ?>
                <!-- ADMIN SIDEBAR -->
                <li class="nav-item mb-1">
                    <a href="<?php echo APP_ROOT; ?>admin/dashboard" class="nav-link text-white d-flex align-items-center py-2 px-3 rounded <?php echo $current_page === 'dashboard' ? 'active bg-primary' : 'hover-bg-secondary'; ?>">
                        <i class="bi bi-speedometer2 me-3 fs-5"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item mb-1">
                    <a href="<?php echo APP_ROOT; ?>admin/employees" class="nav-link text-white d-flex align-items-center py-2 px-3 rounded <?php echo $current_page === 'employees' ? 'active bg-primary' : 'hover-bg-secondary'; ?>">
                        <i class="bi bi-people me-3 fs-5"></i>
                        <span>Employees</span>
                    </a>
                </li>
                <li class="nav-item mb-1">
                    <a href="<?php echo APP_ROOT; ?>admin/timesheets" class="nav-link text-white d-flex align-items-center py-2 px-3 rounded <?php echo $current_page === 'timesheets' ? 'active bg-primary' : 'hover-bg-secondary'; ?>">
                        <i class="bi bi-calendar-week me-3 fs-5"></i>
                        <span>Timesheets</span>
                    </a>
                </li>
                <li class="nav-item mb-1">
                    <a href="<?php echo APP_ROOT; ?>admin/tasks" class="nav-link text-white d-flex align-items-center py-2 px-3 rounded <?php echo $current_page === 'tasks' ? 'active bg-primary' : 'hover-bg-secondary'; ?>">
                        <i class="bi bi-list-task me-3 fs-5"></i>
                        <span>Tasks</span>
                    </a>
                </li>
                <li class="nav-item mb-1">
                    <a href="<?php echo APP_ROOT; ?>admin/reports" class="nav-link text-white d-flex align-items-center py-2 px-3 rounded <?php echo $current_page === 'reports' ? 'active bg-primary' : 'hover-bg-secondary'; ?>">
                        <i class="bi bi-graph-up-arrow me-3 fs-5"></i>
                        <span>Reports</span>
                    </a>
                </li>
                <li class="nav-item mb-1">
                    <a href="<?php echo APP_ROOT; ?>admin/attendance" class="nav-link text-white d-flex align-items-center py-2 px-3 rounded <?php echo $current_page === 'attendance' ? 'active bg-primary' : 'hover-bg-secondary'; ?>">
                        <i class="bi bi-stopwatch me-3 fs-5"></i>
                        <span>Attendance</span>
                    </a>
                </li>
                <li class="nav-item mb-1">
                    <a href="<?php echo APP_ROOT; ?>admin/settings" class="nav-link text-white d-flex align-items-center py-2 px-3 rounded <?php echo $current_page === 'settings' ? 'active bg-primary' : 'hover-bg-secondary'; ?>">
                        <i class="bi bi-gear me-3 fs-5"></i>
                        <span>Settings</span>
                    </a>
                </li>
            <?php else: ?>
                <!-- EMPLOYEE SIDEBAR -->
                <li class="nav-item mb-1">
                    <a href="<?php echo APP_ROOT; ?>employee/dashboard" class="nav-link text-white d-flex align-items-center py-2 px-3 rounded <?php echo $current_page === 'dashboard' ? 'active bg-primary' : 'hover-bg-secondary'; ?>">
                        <i class="bi bi-speedometer2 me-3 fs-5"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item mb-1">
                    <a href="<?php echo APP_ROOT; ?>employee/add-timesheet" class="nav-link text-white d-flex align-items-center py-2 px-3 rounded <?php echo $current_page === 'add-timesheet' ? 'active bg-primary' : 'hover-bg-secondary'; ?>">
                        <i class="bi bi-calendar-plus me-3 fs-5"></i>
                        <span>Add Timesheet</span>
                    </a>
                </li>
                <li class="nav-item mb-1">
                    <a href="<?php echo APP_ROOT; ?>employee/my-timesheets" class="nav-link text-white d-flex align-items-center py-2 px-3 rounded <?php echo $current_page === 'my-timesheets' ? 'active bg-primary' : 'hover-bg-secondary'; ?>">
                        <i class="bi bi-calendar-range me-3 fs-5"></i>
                        <span>My Timesheets</span>
                    </a>
                </li>
                <li class="nav-item mb-1">
                    <a href="<?php echo APP_ROOT; ?>employee/my-tasks" class="nav-link text-white d-flex align-items-center py-2 px-3 rounded <?php echo $current_page === 'my-tasks' ? 'active bg-primary' : 'hover-bg-secondary'; ?>">
                        <i class="bi bi-check2-square me-3 fs-5"></i>
                        <span>My Tasks</span>
                    </a>
                </li>
                <li class="nav-item mb-1">
                    <a href="<?php echo APP_ROOT; ?>employee/profile" class="nav-link text-white d-flex align-items-center py-2 px-3 rounded <?php echo $current_page === 'profile' ? 'active bg-primary' : 'hover-bg-secondary'; ?>">
                        <i class="bi bi-person me-3 fs-5"></i>
                        <span>Profile</span>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </div>
</aside>
