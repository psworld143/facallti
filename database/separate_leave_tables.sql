-- Create separate leave tables for faculty and employees
-- This provides better data organization and avoids complex joins

-- Faculty Leave Requests Table
CREATE TABLE IF NOT EXISTS faculty_leave_requests (
    id INT(11) NOT NULL AUTO_INCREMENT,
    faculty_id INT(11) NOT NULL,
    leave_type_id INT(11) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_days DECIMAL(5,2) NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('pending','approved_by_head','approved_by_hr','rejected','cancelled') DEFAULT 'pending',
    department_head_approval ENUM('pending','approved','rejected') DEFAULT 'pending',
    hr_approval ENUM('pending','approved','rejected') DEFAULT 'pending',
    department_head_id INT(11) NULL,
    hr_approver_id INT(11) NULL,
    department_head_comment TEXT NULL,
    hr_comment TEXT NULL,
    department_head_approved_at TIMESTAMP NULL,
    hr_approved_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_faculty_id (faculty_id),
    INDEX idx_leave_type_id (leave_type_id),
    INDEX idx_start_date (start_date),
    INDEX idx_status (status),
    INDEX idx_department_head_approval (department_head_approval),
    INDEX idx_hr_approval (hr_approval),
    FOREIGN KEY (faculty_id) REFERENCES faculty(id) ON DELETE CASCADE,
    FOREIGN KEY (leave_type_id) REFERENCES leave_types(id) ON DELETE CASCADE,
    FOREIGN KEY (department_head_id) REFERENCES faculty(id) ON DELETE SET NULL,
    FOREIGN KEY (hr_approver_id) REFERENCES faculty(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Employee Leave Requests Table
CREATE TABLE IF NOT EXISTS employee_leave_requests (
    id INT(11) NOT NULL AUTO_INCREMENT,
    employee_id INT(11) NOT NULL,
    leave_type_id INT(11) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_days DECIMAL(5,2) NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('pending','approved_by_head','approved_by_hr','rejected','cancelled') DEFAULT 'pending',
    department_head_approval ENUM('pending','approved','rejected') DEFAULT 'pending',
    hr_approval ENUM('pending','approved','rejected') DEFAULT 'pending',
    department_head_id INT(11) NULL,
    hr_approver_id INT(11) NULL,
    department_head_comment TEXT NULL,
    hr_comment TEXT NULL,
    department_head_approved_at TIMESTAMP NULL,
    hr_approved_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_employee_id (employee_id),
    INDEX idx_leave_type_id (leave_type_id),
    INDEX idx_start_date (start_date),
    INDEX idx_status (status),
    INDEX idx_department_head_approval (department_head_approval),
    INDEX idx_hr_approval (hr_approval),
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (leave_type_id) REFERENCES leave_types(id) ON DELETE CASCADE,
    FOREIGN KEY (department_head_id) REFERENCES employees(id) ON DELETE SET NULL,
    FOREIGN KEY (hr_approver_id) REFERENCES employees(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migrate existing data from leave_requests table to appropriate new tables
-- This will help preserve existing leave request data

-- Migrate faculty leave requests
INSERT INTO faculty_leave_requests (
    faculty_id, leave_type_id, start_date, end_date, total_days, reason, 
    status, department_head_approval, hr_approval, department_head_id, hr_approver_id,
    department_head_comment, hr_comment, department_head_approved_at, hr_approved_at,
    created_at, updated_at
)
SELECT 
    lr.employee_id, lr.leave_type_id, lr.start_date, lr.end_date, lr.total_days, lr.reason,
    lr.status, lr.department_head_approval, lr.hr_approval, lr.department_head_id, lr.hr_approver_id,
    lr.department_head_comment, lr.hr_comment, lr.department_head_approved_at, lr.hr_approved_at,
    lr.created_at, lr.updated_at
FROM leave_requests lr
JOIN faculty f ON lr.employee_id = f.id;

-- Migrate employee leave requests
INSERT INTO employee_leave_requests (
    employee_id, leave_type_id, start_date, end_date, total_days, reason, 
    status, department_head_approval, hr_approval, department_head_id, hr_approver_id,
    department_head_comment, hr_comment, department_head_approved_at, hr_approved_at,
    created_at, updated_at
)
SELECT 
    lr.employee_id, lr.leave_type_id, lr.start_date, lr.end_date, lr.total_days, lr.reason,
    lr.status, lr.department_head_approval, lr.hr_approval, lr.department_head_id, lr.hr_approver_id,
    lr.department_head_comment, lr.hr_comment, lr.department_head_approved_at, lr.hr_approved_at,
    lr.created_at, lr.updated_at
FROM leave_requests lr
JOIN employees e ON lr.employee_id = e.id;

-- Create separate leave balance tables for faculty and employees
-- Faculty Leave Balances
CREATE TABLE IF NOT EXISTS faculty_leave_balances (
    id INT(11) NOT NULL AUTO_INCREMENT,
    faculty_id INT(11) NOT NULL,
    leave_type_id INT(11) NOT NULL,
    year INT(4) NOT NULL,
    total_days INT(11) NOT NULL DEFAULT 0,
    used_days INT(11) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_faculty_leave_year (faculty_id, leave_type_id, year),
    INDEX idx_faculty_id (faculty_id),
    INDEX idx_leave_type_id (leave_type_id),
    INDEX idx_year (year),
    FOREIGN KEY (faculty_id) REFERENCES faculty(id) ON DELETE CASCADE,
    FOREIGN KEY (leave_type_id) REFERENCES leave_types(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Employee Leave Balances
CREATE TABLE IF NOT EXISTS employee_leave_balances (
    id INT(11) NOT NULL AUTO_INCREMENT,
    employee_id INT(11) NOT NULL,
    leave_type_id INT(11) NOT NULL,
    year INT(4) NOT NULL,
    total_days INT(11) NOT NULL DEFAULT 0,
    used_days INT(11) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_employee_leave_year (employee_id, leave_type_id, year),
    INDEX idx_employee_id (employee_id),
    INDEX idx_leave_type_id (leave_type_id),
    INDEX idx_year (year),
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (leave_type_id) REFERENCES leave_types(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migrate existing leave balance data
-- Faculty leave balances
INSERT INTO faculty_leave_balances (faculty_id, leave_type_id, year, total_days, used_days, created_at, updated_at)
SELECT 
    lb.employee_id, lb.leave_type_id, lb.year, lb.total_days, lb.used_days, lb.created_at, lb.updated_at
FROM leave_balances lb
JOIN faculty f ON lb.employee_id = f.id;

-- Employee leave balances
INSERT INTO employee_leave_balances (employee_id, leave_type_id, year, total_days, used_days, created_at, updated_at)
SELECT 
    lb.employee_id, lb.leave_type_id, lb.year, lb.total_days, lb.used_days, lb.created_at, lb.updated_at
FROM leave_balances lb
JOIN employees e ON lb.employee_id = e.id;

-- Note: After confirming the migration worked correctly, you can drop the old tables:
-- DROP TABLE IF EXISTS leave_requests;
-- DROP TABLE IF EXISTS leave_balances;
