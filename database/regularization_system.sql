-- Regularization System Database Structure
-- Handles Teaching and Non-Teaching staff regularization with different review periods

-- 1. Staff Categories Table
CREATE TABLE `staff_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL COMMENT 'Category name (Teaching/Non-Teaching)',
  `description` text DEFAULT NULL COMMENT 'Description of the category',
  `regularization_period_months` int(11) NOT NULL COMMENT 'Months required before regularization review',
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'Active status',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Staff categories with regularization periods';

-- 2. Regularization Status Table
CREATE TABLE `regularization_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL COMMENT 'Status name',
  `description` text DEFAULT NULL COMMENT 'Status description',
  `color` varchar(20) DEFAULT '#6B7280' COMMENT 'Color for UI display',
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'Active status',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Regularization status options';

-- 3. Faculty Regularization Table
CREATE TABLE `faculty_regularization` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `faculty_id` int(11) NOT NULL COMMENT 'Foreign key to faculty table',
  `staff_category_id` int(11) NOT NULL COMMENT 'Foreign key to staff_categories table',
  `current_status_id` int(11) NOT NULL COMMENT 'Foreign key to regularization_status table',
  `date_of_hire` date NOT NULL COMMENT 'Original hire date',
  `probation_start_date` date NOT NULL COMMENT 'Start of probation period',
  `probation_end_date` date NOT NULL COMMENT 'End of probation period',
  `regularization_review_date` date DEFAULT NULL COMMENT 'Date when regularization review is due',
  `regularization_date` date DEFAULT NULL COMMENT 'Date when regularized',
  `review_notes` text DEFAULT NULL COMMENT 'Notes from review process',
  `reviewed_by` int(11) DEFAULT NULL COMMENT 'User ID who reviewed',
  `reviewed_at` timestamp NULL DEFAULT NULL COMMENT 'When review was conducted',
  `next_review_date` date DEFAULT NULL COMMENT 'Next review date if applicable',
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'Active status',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_faculty_id` (`faculty_id`) COMMENT 'One regularization record per faculty',
  KEY `idx_staff_category` (`staff_category_id`),
  KEY `idx_current_status` (`current_status_id`),
  KEY `idx_regularization_review_date` (`regularization_review_date`),
  KEY `idx_probation_end_date` (`probation_end_date`),
  KEY `idx_is_active` (`is_active`),
  CONSTRAINT `fk_faculty_regularization_faculty` FOREIGN KEY (`faculty_id`) REFERENCES `faculty` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_faculty_regularization_category` FOREIGN KEY (`staff_category_id`) REFERENCES `staff_categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_faculty_regularization_status` FOREIGN KEY (`current_status_id`) REFERENCES `regularization_status` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Faculty regularization tracking';

-- 4. Regularization Reviews Table
CREATE TABLE `regularization_reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `faculty_regularization_id` int(11) NOT NULL COMMENT 'Foreign key to faculty_regularization table',
  `review_type` enum('Initial','Follow-up','Final') NOT NULL COMMENT 'Type of review',
  `review_date` date NOT NULL COMMENT 'Date of review',
  `reviewer_id` int(11) NOT NULL COMMENT 'User ID who conducted review',
  `status_before` int(11) NOT NULL COMMENT 'Status before review',
  `status_after` int(11) NOT NULL COMMENT 'Status after review',
  `performance_rating` decimal(3,2) DEFAULT NULL COMMENT 'Performance rating (1.00-5.00)',
  `attendance_score` decimal(3,2) DEFAULT NULL COMMENT 'Attendance score (1.00-5.00)',
  `work_quality_score` decimal(3,2) DEFAULT NULL COMMENT 'Work quality score (1.00-5.00)',
  `teamwork_score` decimal(3,2) DEFAULT NULL COMMENT 'Teamwork score (1.00-5.00)',
  `overall_rating` decimal(3,2) DEFAULT NULL COMMENT 'Overall rating (1.00-5.00)',
  `strengths` text DEFAULT NULL COMMENT 'Employee strengths',
  `areas_for_improvement` text DEFAULT NULL COMMENT 'Areas for improvement',
  `recommendations` text DEFAULT NULL COMMENT 'Reviewer recommendations',
  `decision` enum('Continue_Probation','Recommend_Regularization','Extend_Probation','Terminate') NOT NULL COMMENT 'Review decision',
  `next_review_date` date DEFAULT NULL COMMENT 'Next review date if applicable',
  `notes` text DEFAULT NULL COMMENT 'Additional notes',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_faculty_regularization` (`faculty_regularization_id`),
  KEY `idx_review_date` (`review_date`),
  KEY `idx_reviewer` (`reviewer_id`),
  KEY `idx_decision` (`decision`),
  CONSTRAINT `fk_regularization_reviews_regularization` FOREIGN KEY (`faculty_regularization_id`) REFERENCES `faculty_regularization` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Detailed review records for regularization process';

-- 5. Regularization Notifications Table
CREATE TABLE `regularization_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `faculty_regularization_id` int(11) NOT NULL COMMENT 'Foreign key to faculty_regularization table',
  `notification_type` enum('Review_Due','Review_Overdue','Regularization_Approved','Regularization_Denied','Probation_Extended') NOT NULL COMMENT 'Type of notification',
  `recipient_id` int(11) NOT NULL COMMENT 'User ID to receive notification',
  `subject` varchar(255) NOT NULL COMMENT 'Notification subject',
  `message` text NOT NULL COMMENT 'Notification message',
  `is_read` tinyint(1) DEFAULT 0 COMMENT 'Read status',
  `read_at` timestamp NULL DEFAULT NULL COMMENT 'When notification was read',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_faculty_regularization` (`faculty_regularization_id`),
  KEY `idx_recipient` (`recipient_id`),
  KEY `idx_notification_type` (`notification_type`),
  KEY `idx_is_read` (`is_read`),
  CONSTRAINT `fk_regularization_notifications_regularization` FOREIGN KEY (`faculty_regularization_id`) REFERENCES `faculty_regularization` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Notifications for regularization process';

-- Insert default data

-- Staff Categories
INSERT INTO `staff_categories` (`name`, `description`, `regularization_period_months`, `is_active`) VALUES
('Teaching', 'Teaching staff including professors, instructors, and academic personnel', 36, 1),
('Non-Teaching', 'Non-teaching staff including administrative, support, and technical personnel', 6, 1);

-- Regularization Status
INSERT INTO `regularization_status` (`name`, `description`, `color`, `is_active`) VALUES
('Probationary', 'Currently in probation period', '#F59E0B', 1),
('Under Review', 'Currently under review for regularization', '#3B82F6', 1),
('Regular', 'Successfully regularized', '#10B981', 1),
('Extended Probation', 'Probation period extended', '#F97316', 1),
('Terminated', 'Employment terminated', '#EF4444', 1),
('Pending Review', 'Awaiting review process', '#8B5CF6', 1);

-- Create indexes for better performance
CREATE INDEX `idx_faculty_regularization_dates` ON `faculty_regularization` (`probation_start_date`, `probation_end_date`, `regularization_review_date`);
CREATE INDEX `idx_regularization_reviews_comprehensive` ON `regularization_reviews` (`review_date`, `decision`, `overall_rating`);
CREATE INDEX `idx_notifications_unread` ON `regularization_notifications` (`recipient_id`, `is_read`, `created_at`);

-- Add comments to tables
ALTER TABLE `staff_categories` COMMENT = 'Defines staff categories with different regularization periods';
ALTER TABLE `regularization_status` COMMENT = 'Available statuses for regularization process';
ALTER TABLE `faculty_regularization` COMMENT = 'Main table tracking faculty regularization progress';
ALTER TABLE `regularization_reviews` COMMENT = 'Detailed review records for regularization decisions';
ALTER TABLE `regularization_notifications` COMMENT = 'System notifications for regularization process';
