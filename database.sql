-- Employee Time Sheet & Task Management System Database Schema
-- Optimized for MySQL 5.7+ / MariaDB
-- Simplified for unified Employees table

CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    emp_id VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'employee') NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    assigned_to INT NOT NULL,
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    deadline DATE NOT NULL,
    estimated_duration DECIMAL(5,2) NOT NULL,
    status ENUM('pending', 'completed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_to) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS timesheets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    task_id INT DEFAULT NULL,
    date DATE NOT NULL,
    duration DECIMAL(5,2) NOT NULL,
    description TEXT NOT NULL,
    status ENUM('pending', 'approved') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS task_updates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT UNIQUE NOT NULL,
    notes TEXT DEFAULT NULL,
    actual_duration DECIMAL(5,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    action VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES employees(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    date DATE NOT NULL,
    clock_in DATETIME NOT NULL,
    clock_out DATETIME DEFAULT NULL,
    duration DECIMAL(5,2) DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Seed Admin User (Password: Ags@2026)
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES
('Admin', '$2y$10$0ut6oOrn4JYqnl4HXU81/.Nqzv3KIG2p6LLiEPb84gJYPy74iE6oG', 'admin', 'Admin', 'Admin', 'admin@rhine.com', NULL, 'active');

-- Seed 60 Employees from employees.xlsx
-- Passwords stored as plaintext, auto-upgraded to bcrypt on first login
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26011', 'Tc@AGS26011', 'employee', 'Siddula', 'Hemasai', 'ags26011@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26018', 'Tc@AGS26018', 'employee', 'Settybathini', 'Sathish', 'ags26018@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26010', 'Tc@AGS26010', 'employee', 'Vemula', 'Varshith', 'ags26010@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26019', 'Tc@AGS26019', 'employee', 'Nelapatla', 'Srinivas', 'ags26019@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26014', 'Tc@AGS26014', 'employee', 'Vulli', 'Pavan', 'ags26014@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26017', 'Tc@AGS26017', 'employee', 'Vavilla', 'Saikumar', 'ags26017@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26016', 'Tc@AGS26016', 'employee', 'Manisha', 'Ashroba Virkar', 'ags26016@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26015', 'Tc@AGS26015', 'employee', 'Karne', 'Narsimha Reddy', 'ags26015@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26013', 'Tc@AGS26013', 'employee', 'Kalepu', 'Vijay Kumar', 'ags26013@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26021', 'Tc@AGS26021', 'employee', 'Kontham', 'Sushma', 'ags26021@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26023', 'Tc@AGS26023', 'employee', 'Bobbarala', 'Varshini', 'ags26023@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26026', 'Tc@AGS26026', 'employee', 'Surampudi', 'Roop Sai', 'ags26026@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26028', 'Tc@AGS26028', 'employee', 'Mohammed', 'Abdul Moid', 'ags26028@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26029', 'Tc@AGS26029', 'employee', 'Mula', 'Sai Preetham Goud', 'ags26029@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26030', 'Tc@AGS26030', 'employee', 'Sruthin', 'Kumar Basa', 'ags26030@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26032', 'Tc@AGS26032', 'employee', 'Vempadapu', 'Uday Sai Tarun', 'ags26032@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26034', 'Tc@AGS26034', 'employee', 'Sripada', 'Shivakumar', 'ags26034@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26035', 'Tc@AGS26035', 'employee', 'Varakantham', 'Surender Reddy', 'ags26035@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26041', 'Tc@AGS26041', 'employee', 'Sudhagani', 'Sai Charan', 'ags26041@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26043', 'Tc@AGS26043', 'employee', 'Chebrolu', 'Yashaswini', 'ags26043@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26045', 'Tc@AGS26045', 'employee', 'Chebrolu', 'Monika', 'ags26045@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26047', 'Tc@AGS26047', 'employee', 'Upadhyayula', 'Naga Lakshmi Deepthi', 'ags26047@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26048', 'Tc@AGS26048', 'employee', 'Rapolu', 'Rekha', 'ags26048@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26049', 'Tc@AGS26049', 'employee', 'Ragiru', 'Nikhitha', 'ags26049@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26051', 'Tc@AGS26051', 'employee', 'Mundlamuri', 'Mrudula', 'ags26051@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26055', 'Tc@AGS26055', 'employee', 'Cholleti', 'Sai Kumar', 'ags26055@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26053', 'Tc@AGS26053', 'employee', 'Palavelli', 'Praveen Reddy', 'ags26053@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26054', 'Tc@AGS26054', 'employee', 'Yaramothu', 'Himaja', 'ags26054@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26031', 'Tc@AGS26031', 'employee', 'Y', 'Raghavendar', 'ags26031@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26056', 'Tc@AGS26056', 'employee', 'Bommala', 'Naveen Kumar', 'ags26056@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26057', 'Tc@AGS26057', 'employee', 'Odeti', 'Srinivas', 'ags26057@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26058', 'Tc@AGS26058', 'employee', 'Matha', 'Sai Prasad', 'ags26058@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26059', 'Tc@AGS26059', 'employee', 'Chamakuri', 'Raghuram', 'ags26059@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26025', 'Tc@AGS26025', 'employee', 'Tumula', 'Dinesh Kumar', 'ags26025@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26060', 'Tc@AGS26060', 'employee', 'Thallada', 'Venkatasagar', 'ags26060@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26061', 'Tc@AGS26061', 'employee', 'Indla', 'Kiran Kumar', 'ags26061@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26062', 'Tc@AGS26062', 'employee', 'K', 'Tharun Kumar Reddy', 'ags26062@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26063', 'Tc@AGS26063', 'employee', 'Bondada', 'Jayanth', 'ags26063@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26065', 'Tc@AGS26065', 'employee', 'Boddupalli', 'Krishna Prasad', 'ags26065@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26066', 'Tc@AGS26066', 'employee', 'Mohammad', 'Sulthan', 'ags26066@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26067', 'Tc@AGS26067', 'employee', 'Md', 'Imran Khan', 'ags26067@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26068', 'Tc@AGS26068', 'employee', 'Narmeta', 'Srujanreddy', 'ags26068@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26069', 'Tc@AGS26069', 'employee', 'Neetapalli', 'Sreekanth Reddy', 'ags26069@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26070', 'Tc@AGS26070', 'employee', 'Melath', 'Ashwin', 'ags26070@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26072', 'Tc@AGS26072', 'employee', 'Mohd', 'Sameer', 'ags26072@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26073', 'Tc@AGS26073', 'employee', 'Boya', 'Indrasena Naidu', 'ags26073@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26074', 'Tc@AGS26074', 'employee', 'Manikonda', 'Nagasurya Koundinya', 'ags26074@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26075', 'Tc@AGS26075', 'employee', 'KONDAPALLY', 'AKHIL', 'ags26075@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26076', 'Tc@AGS26076', 'employee', 'Shaik', 'Arif', 'ags26076@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26005', 'Tc@AGS26005', 'employee', 'Tanakala', 'Janardhan', 'ags26005@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26006', 'Tc@AGS26006', 'employee', 'Abhijeet', 'Vijaysingh Pardeshi', 'ags26006@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26020', 'Tc@AGS26020', 'employee', 'Chaitanya', '.', 'ags26020@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26024', 'Tc@AGS26024', 'employee', 'Priya', 'Darshini Pola', 'ags26024@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26038', 'Tc@AGS26038', 'employee', 'Ainala', 'Saiesha', 'ags26038@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26039', 'Tc@AGS26039', 'employee', 'Esther', 'Deva Priya', 'ags26039@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26044', 'Tc@AGS26044', 'employee', 'Pulikonda', 'Naveen', 'ags26044@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26004', 'Tc@AGS26004', 'employee', 'Sharath', 'Chandra', 'ags26004@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26078', 'Tc@AGS26078', 'employee', 'Aishwarya', '.', 'ags26078@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26079', 'Tc@AGS26079', 'employee', 'new', 'employee', 'ags26079@axiora.com', NULL, 'active');
INSERT INTO employees (emp_id, password, role, first_name, last_name, email, phone, status) VALUES ('AGS26077', 'Tc@AGS26077', 'employee', 'employee', '.', 'ags26077@axiora.com', NULL, 'active');
