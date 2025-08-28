-- Leave Management System Database Structure
-- This file contains all the necessary tables for the leave management system

-- Table for employees (faculty/staff)
CREATE TABLE IF NOT EXISTS `employees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` varchar(20) NOT NULL UNIQUE,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL UNIQUE,
  `password` varchar(255) DEFAULT NULL,
  `position` varchar(100) NOT NULL,
  `department` varchar(100) NOT NULL,
  `employee_type` enum('faculty','staff','admin') DEFAULT 'staff',
  `hire_date` date NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_department` (`department`),
  KEY `idx_employee_type` (`employee_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for leave types
CREATE TABLE IF NOT EXISTS `leave_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `default_days_per_year` int(11) DEFAULT 0,
  `requires_approval` tinyint(1) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for leave requests
CREATE TABLE IF NOT EXISTS `leave_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `total_days` decimal(5,2) NOT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved_by_head','approved_by_hr','rejected','cancelled') DEFAULT 'pending',
  `department_head_approval` enum('pending','approved','rejected') DEFAULT 'pending',
  `hr_approval` enum('pending','approved','rejected') DEFAULT 'pending',
  `department_head_id` int(11) DEFAULT NULL,
  `hr_approver_id` int(11) DEFAULT NULL,
  `department_head_comment` text DEFAULT NULL,
  `hr_comment` text DEFAULT NULL,
  `department_head_approved_at` timestamp NULL DEFAULT NULL,
  `hr_approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_employee_id` (`employee_id`),
  KEY `idx_leave_type_id` (`leave_type_id`),
  KEY `idx_status` (`status`),
  KEY `idx_department_head_approval` (`department_head_approval`),
  KEY `idx_hr_approval` (`hr_approval`),
  KEY `idx_start_date` (`start_date`),
  KEY `idx_end_date` (`end_date`),
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for leave balances
CREATE TABLE IF NOT EXISTS `leave_balances` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `year` int(4) NOT NULL,
  `total_days` decimal(5,2) NOT NULL DEFAULT 0,
  `used_days` decimal(5,2) NOT NULL DEFAULT 0,
  `remaining_days` decimal(5,2) GENERATED ALWAYS AS (`total_days` - `used_days`) STORED,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_employee_leave_year` (`employee_id`, `leave_type_id`, `year`),
  KEY `idx_employee_id` (`employee_id`),
  KEY `idx_leave_type_id` (`leave_type_id`),
  KEY `idx_year` (`year`),
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for leave notifications
CREATE TABLE IF NOT EXISTS `leave_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `recipient_id` int(11) NOT NULL,
  `recipient_type` enum('employee','department_head','hr') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('leave_request','leave_approved','leave_rejected','leave_cancelled','reminder') DEFAULT 'leave_request',
  `related_leave_request_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_recipient_id` (`recipient_id`),
  KEY `idx_recipient_type` (`recipient_type`),
  KEY `idx_is_read` (`is_read`),
  KEY `idx_related_leave_request_id` (`related_leave_request_id`),
  FOREIGN KEY (`related_leave_request_id`) REFERENCES `leave_requests`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for department heads (linking to existing heads table)
CREATE TABLE IF NOT EXISTS `department_heads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `department` varchar(100) NOT NULL,
  `position` varchar(100) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_department_head` (`department`, `employee_id`),
  KEY `idx_employee_id` (`employee_id`),
  KEY `idx_department` (`department`),
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default leave types
INSERT INTO `leave_types` (`name`, `description`, `default_days_per_year`, `requires_approval`) VALUES
('Vacation Leave', 'Annual vacation leave for rest and recreation', 15, 1),
('Sick Leave', 'Medical leave for illness or health-related reasons', 15, 1),
('Maternity Leave', 'Leave for expecting mothers', 105, 1),
('Paternity Leave', 'Leave for new fathers', 7, 1),
('Study Leave', 'Leave for educational purposes and training', 6, 1),
('Personal Leave', 'Personal or emergency leave', 3, 1),
('Bereavement Leave', 'Leave due to death of immediate family member', 5, 1),
('Official Business', 'Leave for official business or conferences', 10, 1);

-- Insert sample employees (you can modify these as needed)
INSERT INTO `employees` (`employee_id`, `first_name`, `last_name`, `email`, `password`, `position`, `department`, `employee_type`, `hire_date`, `phone`) VALUES
('EMP001', 'John', 'Doe', 'john.doe@seait.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Professor', 'College of Information and Communication Technology', 'faculty', '2020-01-15', '+63 912 345 6789'),
('EMP002', 'Jane', 'Smith', 'jane.smith@seait.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Associate Professor', 'College of Business and Good Governance', 'faculty', '2019-03-20', '+63 923 456 7890'),
('EMP003', 'Michael', 'Johnson', 'michael.johnson@seait.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'HR Manager', 'Human Resources', 'admin', '2018-06-10', '+63 934 567 8901'),
('EMP004', 'Sarah', 'Williams', 'sarah.williams@seait.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Department Head', 'College of Information and Communication Technology', 'faculty', '2017-08-05', '+63 945 678 9012'),
('EMP005', 'David', 'Brown', 'david.brown@seait.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrative Staff', 'Administration', 'staff', '2021-02-14', '+63 956 789 0123');

-- Insert department heads
INSERT INTO `department_heads` (`employee_id`, `department`, `position`) VALUES
(4, 'College of Information and Communication Technology', 'Department Head'),
(2, 'College of Business and Good Governance', 'Department Head');

-- Insert initial leave balances for current year
INSERT INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `total_days`, `used_days`) VALUES
(1, 1, 2025, 15.00, 0.00), -- Vacation Leave
(1, 2, 2025, 15.00, 0.00), -- Sick Leave
(1, 6, 2025, 3.00, 0.00),  -- Personal Leave
(2, 1, 2025, 15.00, 0.00), -- Vacation Leave
(2, 2, 2025, 15.00, 0.00), -- Sick Leave
(2, 6, 2025, 3.00, 0.00),  -- Personal Leave
(3, 1, 2025, 15.00, 0.00), -- Vacation Leave
(3, 2, 2025, 15.00, 0.00), -- Sick Leave
(3, 6, 2025, 3.00, 0.00),  -- Personal Leave
(4, 1, 2025, 15.00, 0.00), -- Vacation Leave
(4, 2, 2025, 15.00, 0.00), -- Sick Leave
(4, 6, 2025, 3.00, 0.00),  -- Personal Leave
(5, 1, 2025, 15.00, 0.00), -- Vacation Leave
(5, 2, 2025, 15.00, 0.00), -- Sick Leave
(5, 6, 2025, 3.00, 0.00);  -- Personal Leave
