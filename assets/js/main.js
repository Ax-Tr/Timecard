// Custom Application Scripts

document.addEventListener('DOMContentLoaded', function () {
    // Initialize Bootstrap tooltips
    const tooltipTriggerList = Array.from(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(tooltipTriggerEl => {
        new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // 1. Sidebar Toggle Logic
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');

    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function (e) {
            e.preventDefault();
            if (window.innerWidth > 768) {
                sidebar.classList.toggle('collapsed');
            } else {
                sidebar.classList.toggle('show-mobile');
            }
        });
    }

    // Close mobile sidebar when clicking outside
    document.addEventListener('click', function (e) {
        if (window.innerWidth <= 768 && sidebar && sidebarToggle) {
            if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target) && sidebar.classList.contains('show-mobile')) {
                sidebar.classList.remove('show-mobile');
            }
        }
    });

    // 2. Theme Toggle Logic
    const themeToggle = document.getElementById('themeToggle');
    const themeIcon = document.getElementById('themeIcon');

    if (themeToggle && themeIcon) {
        themeToggle.addEventListener('click', function () {
            const currentTheme = document.documentElement.getAttribute('data-bs-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            document.documentElement.setAttribute('data-bs-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme);
        });

        // Sync icon on page load
        const activeTheme = document.documentElement.getAttribute('data-bs-theme') || 'light';
        updateThemeIcon(activeTheme);
    }

    function updateThemeIcon(theme) {
        if (theme === 'dark') {
            themeIcon.className = 'bi bi-moon-stars-fill fs-5';
        } else {
            themeIcon.className = 'bi bi-sun-fill fs-5';
        }
    }

    // 3. Clear Notifications Handler
    const clearNotiBtn = document.getElementById('clearNotificationsBtn');
    if (clearNotiBtn) {
        clearNotiBtn.addEventListener('click', function () {
            // Find base path dynamically (e.g. /timecard/ or /)
            const appRoot = getAppRootPath();
            fetch(appRoot + 'includes/clear_notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Notifications marked as read', 'success');
                    // Reload page after a delay or dynamically empty items
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showToast(data.message || 'Error clearing notifications', 'danger');
                }
            })
            .catch(err => {
                console.error(err);
                showToast('Failed to clear notifications', 'danger');
            });
        });
    }

    // Helper to extract app root path from standard links
    function getAppRootPath() {
        const sidebarLink = document.querySelector('#sidebar .nav-link');
        if (sidebarLink) {
            const href = sidebarLink.getAttribute('href');
            // e.g. /timecard/admin/dashboard
            const parts = href.split('/');
            // Remove 'admin/dashboard' or 'employee/dashboard'
            parts.pop();
            parts.pop();
            return parts.join('/') + '/';
        }
        return '/';
    }

    // 4. General Searchable Dropdown Initializer
    const searchableDropdowns = document.querySelectorAll('.searchable-dropdown');
    searchableDropdowns.forEach(dropdownEl => {
        const searchInput = dropdownEl.querySelector('.dropdown-search-input');
        const dropdownBtn = dropdownEl.querySelector('.dropdown-toggle');
        const hiddenInput = dropdownEl.querySelector('.dropdown-hidden-input');
        const optionsList = dropdownEl.querySelector('.dropdown-options-list');
        const options = optionsList.querySelectorAll('.dropdown-item');

        // Filter options as user types
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const query = this.value.toLowerCase().trim();
                options.forEach(opt => {
                    const text = opt.textContent.toLowerCase();
                    if (text.includes(query)) {
                        opt.style.display = 'block';
                    } else {
                        opt.style.display = 'none';
                    }
                });
            });
        }

        // Handle option click
        options.forEach(opt => {
            opt.addEventListener('click', function(e) {
                e.preventDefault();
                const val = this.getAttribute('data-value');
                const text = this.textContent.trim();

                // Set hidden value and button text
                if (hiddenInput) hiddenInput.value = val;
                if (dropdownBtn) {
                    // Update button content, maintaining form-select layout
                    dropdownBtn.innerHTML = text;
                }

                // Highlight active option
                options.forEach(o => o.classList.remove('active'));
                this.classList.add('active');

                // Close dropdown
                const dropdownInstance = bootstrap.Dropdown.getInstance(dropdownBtn) || new bootstrap.Dropdown(dropdownBtn);
                dropdownInstance.hide();
            });
        });

        // Reset search query and focus when dropdown is shown
        dropdownEl.addEventListener('shown.bs.dropdown', function() {
            if (searchInput) {
                searchInput.value = '';
                searchInput.focus();
            }
            options.forEach(opt => opt.style.display = 'block');
        });
    });

    // 5. Timesheet & Task Details Modal Fetch & Render
    const taskDetailsModalEl = document.getElementById('taskDetailsModal');
    if (taskDetailsModalEl) {
        const taskModalContent = document.getElementById('taskDetailsContent');

        document.addEventListener('click', function(e) {
            const btn = e.target.closest('.view-timesheet-btn');
            if (btn) {
                e.preventDefault();
                const timesheetId = btn.getAttribute('data-timesheet-id');
                if (!timesheetId) return;

                // Open Modal
                const modal = bootstrap.Modal.getOrCreateInstance(taskDetailsModalEl);
                modal.show();

                // Loading State
                taskModalContent.innerHTML = `
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                `;

                // Fetch data
                const appRoot = getAppRootPath();
                fetch(appRoot + 'admin/get_timesheet_details.php?id=' + timesheetId)
                    .then(response => {
                        if (!response.ok) throw new Error('Timesheet record not found');
                        return response.json();
                    })
                    .then(data => {
                        let taskHtml = '';
                        if (data.task_id) {
                            let priorityBadge = '';
                            if (data.task_priority === 'high') {
                                priorityBadge = '<span class="badge bg-danger-subtle text-danger">High</span>';
                            } else if (data.task_priority === 'medium') {
                                priorityBadge = '<span class="badge bg-warning-subtle text-warning">Medium</span>';
                            } else {
                                priorityBadge = '<span class="badge bg-info-subtle text-info">Low</span>';
                            }

                            let updatesHtml = '';
                            if (data.update_details) {
                                updatesHtml = `
                                    <div class="mt-3 pt-2 border-top">
                                        <div class="small text-muted mb-1 fw-semibold">COMPLETION FEEDBACK</div>
                                        <div class="bg-body-secondary p-2 rounded small">
                                            <div class="mb-1"><strong>Actual Duration:</strong> ${parseFloat(data.update_details.actual_duration).toFixed(1)} hrs</div>
                                            <div><strong>Notes:</strong> ${data.update_details.notes ? escapeHtml(data.update_details.notes) : 'No notes provided.'}</div>
                                        </div>
                                    </div>
                                `;
                            }

                            taskHtml = `
                                <div class="card bg-light border-0 mt-3">
                                    <div class="card-body p-3">
                                        <h6 class="fw-bold mb-2 text-primary d-flex align-items-center"><i class="bi bi-tag-fill me-1"></i>Associated Task Details</h6>
                                        <div class="fw-semibold small mb-1">${escapeHtml(data.task_title)}</div>
                                        <div class="d-flex gap-2 align-items-center mb-2 flex-wrap">
                                            ${priorityBadge}
                                            <span class="text-muted small"><i class="bi bi-calendar-event me-1"></i>Deadline: ${escapeHtml(data.task_deadline)}</span>
                                            <span class="text-muted small"><i class="bi bi-clock me-1"></i>Est. Hours: ${parseFloat(data.task_estimated_duration).toFixed(1)} hrs</span>
                                        </div>
                                        <div class="text-body-secondary small mb-2" style="white-space: pre-line;">${data.task_description ? escapeHtml(data.task_description) : 'No task description.'}</div>
                                        ${updatesHtml}
                                    </div>
                                </div>
                            `;
                        } else {
                            taskHtml = `
                                <div class="card bg-light border-0 mt-3">
                                    <div class="card-body p-3 text-muted small d-flex align-items-center">
                                        <i class="bi bi-info-circle me-2 fs-5"></i>Associated Task: None (Manual Entry)
                                    </div>
                                </div>
                            `;
                        }

                        taskModalContent.innerHTML = `
                            <div class="d-flex flex-column gap-3">
                                <!-- Employee & Work Info -->
                                <div>
                                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                        <div>
                                            <h5 class="fw-bold mb-0 text-dark">${escapeHtml(data.employee_name)}</h5>
                                            <div class="text-muted small mt-1">
                                                <span class="me-3"><i class="bi bi-person-badge me-1"></i>ID: <code>${escapeHtml(data.emp_id)}</code></span>
                                                <span><i class="bi bi-calendar-check me-1"></i>Date: ${escapeHtml(data.work_date)}</span>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-primary text-white fs-6 py-2 px-3">${parseFloat(data.work_duration).toFixed(1)} hrs</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Work Details -->
                                <div class="border-top pt-3">
                                    <h6 class="fw-semibold text-secondary mb-2"><i class="bi bi-pencil-square me-1"></i>Work Details</h6>
                                    <p class="text-body-secondary mb-0 small" style="white-space: pre-line; line-height: 1.5;">${escapeHtml(data.work_details)}</p>
                                </div>

                                <!-- Task Info -->
                                ${taskHtml}
                            </div>
                        `;
                    })
                    .catch(err => {
                        console.error(err);
                        taskModalContent.innerHTML = `
                            <div class="alert alert-danger mb-0 py-2 small text-center">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i> Failed to load timesheet details.
                            </div>
                        `;
                    });
            }
        });
    }

    // Helper to escape HTML characters
    function escapeHtml(str) {
        if (!str) return '';
        return str
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
});

// Toast notification helper
function showToast(message, type = 'success') {
    const toastEl = document.getElementById('liveToast');
    const toastMsg = document.getElementById('toastMessage');
    
    if (toastEl && toastMsg) {
        toastMsg.innerText = message;
        
        // Remove existing type classes
        toastEl.className = 'toast align-items-center border-0';
        
        // Add color class
        if (type === 'success') {
            toastEl.classList.add('text-bg-success');
        } else if (type === 'danger') {
            toastEl.classList.add('text-bg-danger');
        } else if (type === 'warning') {
            toastEl.classList.add('text-bg-warning');
        } else {
            toastEl.classList.add('text-bg-info');
        }
        
        const toast = new bootstrap.Toast(toastEl);
        toast.show();
    }
}

// Show/Hide loader overlay helper
function toggleLoader(show = true) {
    let overlay = document.getElementById('ajaxLoaderOverlay');
    if (show) {
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'ajaxLoaderOverlay';
            overlay.className = 'spinner-overlay';
            overlay.innerHTML = '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>';
            document.body.appendChild(overlay);
        }
    } else {
        if (overlay) {
            overlay.remove();
        }
    }
}
