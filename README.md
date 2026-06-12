# Employee Attendance & Time Sheet & Task Management System

A lightweight, secure, and production-ready corporate web application optimized specifically for shared hosting environments like **ProFreeHost**, **InfinityFree**, or **000webhost**.

---

## Key Features

1. **Role-Based Workspaces**: Complete interfaces for both administrators and employees.
2. **Interactive Dashboard**:
   - **Admin**: Total employees, cumulative worked hours, pending timesheet clearances, task completion tracking, monthly graphs, and live activity logs.
   - **Employee**: Today's schedule, pending tasks count, monthly hour stats, and recent logs.
3. **Timesheet Entry System**:
   - Manual hour logging with date validations (prevents logging future dates).
   - Duplicate prevention check (avoids logging duplicate hours for the same task).
   - Warning notifications if total logged hours exceed daily limits.
   - Same-day self-editing and deleting of logged entries.
4. **TODO / Task Assignment System**:
   - Admins can assign tasks to employees with priorities, deadlines, and estimated times.
   - **Automatic Timesheet Generation**: When an employee marks an assigned task as completed, they enter the actual duration and notes. The system automatically inserts a corresponding approved/pending timesheet entry and moves the task to the completed section.
5. **Advanced Filters & Reporting**:
   - Filter timesheet records by employee, date range, specific week (HTML5), specific month, or approval state.
   - One-click Excel/CSV report generator.
6. **Security & Optimizations**:
   - SQL Injection protection via PDO Prepared Statements.
   - Cross-Site Scripting (XSS) prevention on all output strings.
   - Session Hijacking prevention (HttpOnly, Secure cookie flags, SameSite strict).
   - Automated inactivity logout (15 minutes idle timeout).
   - Dynamic dynamic path resolver to support project installations inside subdirectories out-of-the-box.
   - High performance: no Node, React, or heavy JS. Only native PHP 8.x and Bootstrap 5.

---

## Credentials

### Employee Login
- **Username**: `EMP101` through `EMP160` (60 seeded employee accounts)
- **Password**: `Tca@(EmployeeID)` (e.g. `Tca@EMP101` for employee EMP101)
  *(Note: Password upgrade protocol will automatically convert these plaintext seeded values to secure `password_hash()` bcrypt hashes on their first successful login).*

---

## File Structure

```text
/
├── .htaccess                 # Apache routing and config security
├── index.php                 # Core router (directs based on session role)
├── README.md                 # Deployment & user manual
├── database.sql              # Database schema & 60 seeded employee rows
├── config/
│   ├── db.php                # Database PDO link & secure session startup
│   └── settings.json         # Editable system-wide thresholds (Hours limit)
├── includes/
│   ├── header.php            # HTML layout head & theme config
│   ├── footer.php            # Script declarations & Toast container
│   ├── navbar.php            # Header navigation & notifications dropdown
│   ├── sidebar.php           # Role-based vertical side navigation links
│   ├── functions.php         # Global utilities: CSRF, e(), log_activity()
│   └── clear_notifications.php # REST endpoint to clear user notifications (AJAX)
├── assets/
│   ├── css/
│   │   └── style.css         # Custom layout overrides & styling
│   └── js/
│       └── main.js           # AJAX triggers, theme toggling & loaders
├── auth/
│   ├── login.php             # Secure login verification
│   └── logout.php            # Secure session termination
├── admin/
│   ├── dashboard.php         # Admin metrics
│   ├── employees.php         # CRUD operations on employees & status switches
│   ├── timesheets.php        # Timesheet reviews, CSV export, and approvals
│   ├── tasks.php             # Assign and track tasks
│   ├── reports.php           # Monthly analytics table & CSV downloads
│   └── settings.php          # Manage admin password and limit warnings
└── employee/
    ├── dashboard.php         # Employee metrics
    ├── add-timesheet.php     # Daily work logs & same-day updates
    ├── my-timesheets.php     # Full search history
    ├── my-tasks.php          # Mark tasks complete, auto-logs timesheets
    └── profile.php           # Update contact phone & passwords
```

---

## Installation & Deployment

### Step 1: Upload Files
Using an FTP Client (like FileZilla) or your hosting control panel's File Manager:
1. Connect to your host.
2. Upload the entire project directory contents to the target directory (e.g., `public_html/` or a subfolder like `public_html/timecard/`).

### Step 2: Import Database
1. Log in to your hosting control panel (cPanel or VistaPanel).
2. Create a new MySQL Database (e.g. `epiz_xxxx_timecard`).
3. Open **phpMyAdmin** from the panel and select the newly created database.
4. Go to the **Import** tab, choose the `database.sql` file from the project, and click **Go**.

### Step 3: Configure Connection
1. Open the file `config/db.php` in a text editor.
2. Update the Database Credentials constants:
   ```php
   define('DB_HOST', 'your_mysql_hostname'); // usually sqlXXX.epizy.com on ProFreeHost
   define('DB_USER', 'your_mysql_username');
   define('DB_PASS', 'your_mysql_password');
   define('DB_NAME', 'your_mysql_database');
   ```
3. Save the file.

### Step 4: Access System
Open your web browser and navigate to your domain (e.g., `http://yourdomain.live/` or `http://yourdomain.live/timecard/`).
The system will automatically recognize the folder depth and redirect you to the login screen.

---

## Hosting Optimization & Limits
- **Low Memory Footprint**: Keeps active database handles to a minimum. Connections close automatically when scripts end.
- **Resource Limits**: Avoids high CPU usage warnings on ProFreeHost because it does not use long polling or WebSockets. AJAX is strictly triggered by click events.
- **Cache Friendly**: Custom CSS and JS assets are small, reducing HTTP request headers and payload size.
- **Htaccess Security**: Prevents access to config, JSON files, logs, and database backups.
