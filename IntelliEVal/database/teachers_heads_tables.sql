-- Teachers and Heads Tables for IntelliEVal System
-- This script creates the necessary tables for teacher and head management

-- =====================================================
-- TEACHERS TABLE
-- =====================================================

CREATE TABLE IF NOT EXISTS `teachers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `department` varchar(100) NOT NULL,
  `position` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `department` (`department`),
  KEY `status` (`status`),
  CONSTRAINT `fk_teachers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- HEADS TABLE
-- =====================================================

CREATE TABLE IF NOT EXISTS `heads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `department` varchar(100) NOT NULL,
  `position` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `department` (`department`),
  KEY `status` (`status`),
  CONSTRAINT `fk_heads_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SAMPLE DATA
-- =====================================================

-- Insert sample teachers (if users exist)
INSERT IGNORE INTO `teachers` (`user_id`, `department`, `position`, `phone`) VALUES
(1, 'Computer Science', 'Assistant Professor', '+63 912 345 6789'),
(2, 'Mathematics', 'Associate Professor', '+63 923 456 7890'),
(3, 'English', 'Instructor', '+63 934 567 8901'),
(4, 'Science', 'Assistant Professor', '+63 945 678 9012'),
(5, 'History', 'Instructor', '+63 956 789 0123');

-- Insert sample heads (if users exist)
INSERT IGNORE INTO `heads` (`user_id`, `department`, `position`, `phone`) VALUES
(1, 'Computer Science', 'Department Head', '+63 912 345 6789'),
(2, 'Mathematics', 'Department Head', '+63 923 456 7890'),
(3, 'English', 'Department Head', '+63 934 567 8901'),
(4, 'Science', 'Department Head', '+63 945 678 9012'),
(5, 'History', 'Department Head', '+63 956 789 0123');

-- =====================================================
-- INDEXES FOR PERFORMANCE
-- =====================================================

-- Add performance indexes
CREATE INDEX IF NOT EXISTS `idx_teachers_department_status` ON `teachers` (`department`, `status`);
CREATE INDEX IF NOT EXISTS `idx_heads_department_status` ON `heads` (`department`, `status`);
CREATE INDEX IF NOT EXISTS `idx_teachers_position` ON `teachers` (`position`);
CREATE INDEX IF NOT EXISTS `idx_heads_position` ON `heads` (`position`); 