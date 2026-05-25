<?php
// Top Navigation Bar
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';

// Fetch unread notifications
$noti_limit = 5;
$unread_count = 0;
$unread_notifications = [];

if (is_logged_in()) {
    $user_id = $_SESSION['user_id'];
    try {
        if (is_admin()) {
            $noti_stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id IS NULL AND is_read = 0 ORDER BY created_at DESC LIMIT ?");
        } else {
            $noti_stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT ?");
        }
        $noti_stmt->bindValue(1, is_admin() ? $noti_limit : $user_id, PDO::PARAM_INT);
        if (!is_admin()) {
            $noti_stmt->bindValue(2, $noti_limit, PDO::PARAM_INT);
        }
        $noti_stmt->execute();
        $unread_notifications = $noti_stmt->fetchAll();
        
        // Total count
        if (is_admin()) {
            $count_stmt = $pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id IS NULL AND is_read = 0");
        } else {
            $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
            $count_stmt->execute([$user_id]);
        }
        $unread_count = $count_stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Notification fetch error: " . $e->getMessage());
    }
}
?>
<nav class="navbar navbar-expand navbar-light bg-body sticky-top border-bottom">
    <div class="container-fluid px-3">
        <!-- Sidebar Toggle -->
        <button class="btn btn-link link-secondary me-2 p-1" id="sidebarToggle" type="button" aria-label="Toggle Sidebar">
            <i class="bi bi-list fs-4"></i>
        </button>

        <!-- Brand / Logo (Hidden on mobile to fit elements) -->
        <span class="navbar-brand mb-0 h1 d-none d-md-block fs-6 fw-bold text-uppercase tracking-wide text-primary">Rhine Manager</span>

        <!-- Right Utilities -->
        <ul class="navbar-nav ms-auto align-items-center">
            <!-- Theme Toggle -->
            <li class="nav-item me-2">
                <button class="btn btn-link link-secondary p-1" id="themeToggle" type="button" title="Toggle theme">
                    <i class="bi bi-sun-fill fs-5" id="themeIcon"></i>
                </button>
            </li>

            <!-- Notifications Dropdown -->
            <li class="nav-item dropdown me-2">
                <button class="btn btn-link link-secondary p-1 position-relative" id="notificationsDropdown" data-bs-toggle="dropdown" aria-expanded="false" type="button">
                    <i class="bi bi-bell-fill fs-5" id="notificationBellIcon"></i>
                    <span id="notificationBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-light <?php echo $unread_count > 0 ? '' : 'd-none'; ?>" style="font-size: 0.65rem;">
                        <?php echo $unread_count > 99 ? '99+' : $unread_count; ?>
                    </span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 py-0 overflow-hidden" aria-labelledby="notificationsDropdown" style="width: 280px; font-size: 0.85rem;">
                    <li class="dropdown-header bg-light py-2 text-dark font-weight-bold border-bottom" id="notificationDropdownHeader">
                        Notifications (<?php echo $unread_count; ?> Unread)
                    </li>
                    <div id="notificationDropdownList" style="max-height: 250px; overflow-y: auto;">
                        <?php if (empty($unread_notifications)): ?>
                            <li class="text-muted text-center py-3" id="noNotificationsLabel">No new notifications</li>
                        <?php else: ?>
                            <?php foreach ($unread_notifications as $noti): ?>
                                <li>
                                    <a class="dropdown-item py-2 px-3 border-bottom d-flex flex-column text-wrap" href="#">
                                        <span><?php echo e($noti['message']); ?></span>
                                        <small class="text-muted mt-1" style="font-size: 0.75rem;"><?php echo date('M d, g:i a', strtotime($noti['created_at'])); ?></small>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <li id="clearNotificationsWrapper" class="<?php echo $unread_count > 0 ? '' : 'd-none'; ?>">
                        <button class="dropdown-item text-center text-primary py-2 border-top" id="clearNotificationsBtn">
                            Mark all as read
                        </button>
                    </li>
                </ul>
            </li>

            <!-- Divider -->
            <div class="vr mx-2 text-muted" style="height: 20px;"></div>

            <!-- Profile Dropdown -->
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle py-1 px-2 d-flex align-items-center link-secondary" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px; font-weight: 500;">
                        <?php echo strtoupper(substr($_SESSION['username'] ?? 'U', 0, 2)); ?>
                    </div>
                    <span class="d-none d-md-inline text-body-secondary" style="font-size: 0.9rem; font-weight: 500;">
                        <?php echo e($_SESSION['username'] ?? 'User'); ?>
                    </span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" aria-labelledby="profileDropdown">
                    <li class="dropdown-header">
                        <small class="text-muted d-block">Role</small>
                        <span class="badge bg-secondary-subtle text-secondary text-capitalize"><?php echo e($_SESSION['role']); ?></span>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <?php if (!is_admin()): ?>
                        <li>
                            <a class="dropdown-item" href="<?php echo APP_ROOT; ?>employee/profile">
                                <i class="bi bi-person me-2"></i> My Profile
                            </a>
                        </li>
                    <?php endif; ?>
                    <li>
                        <a class="dropdown-item text-danger" href="<?php echo APP_ROOT; ?>auth/logout">
                            <i class="bi bi-box-arrow-right me-2"></i> Logout
                        </a>
                    </li>
                </ul>
            </li>
        </ul>
    </div>
</nav>
