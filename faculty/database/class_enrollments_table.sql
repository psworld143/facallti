-- Class Enrollments Table for Faculty Module
-- This table tracks students who join teacher classes using join codes

CREATE TABLE IF NOT EXISTS `class_enrollments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `join_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('enrolled','dropped','completed') DEFAULT 'enrolled',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_enrollment` (`class_id`, `student_id`),
  KEY `class_id` (`class_id`),
  KEY `student_id` (`student_id`),
  KEY `status` (`status`),
  KEY `join_date` (`join_date`),
  CONSTRAINT `fk_class_enrollments_class` FOREIGN KEY (`class_id`) REFERENCES `teacher_classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_class_enrollments_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add indexes for better performance
CREATE INDEX IF NOT EXISTS `idx_class_enrollments_class_status` ON `class_enrollments` (`class_id`, `status`);
CREATE INDEX IF NOT EXISTS `idx_class_enrollments_student_status` ON `class_enrollments` (`student_id`, `status`);
CREATE INDEX IF NOT EXISTS `idx_class_enrollments_join_date` ON `class_enrollments` (`join_date`); 