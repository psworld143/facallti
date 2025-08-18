-- Teacher Classes Table for Faculty Module
-- This table stores classes created by teachers with subjects from course_curriculum

CREATE TABLE IF NOT EXISTS `teacher_classes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `section` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `join_code` varchar(20) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `join_code` (`join_code`),
  KEY `teacher_id` (`teacher_id`),
  KEY `subject_id` (`subject_id`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `fk_teacher_classes_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_teacher_classes_subject` FOREIGN KEY (`subject_id`) REFERENCES `course_curriculum` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add indexes for better performance
CREATE INDEX IF NOT EXISTS `idx_teacher_classes_teacher_status` ON `teacher_classes` (`teacher_id`, `status`);
CREATE INDEX IF NOT EXISTS `idx_teacher_classes_join_code` ON `teacher_classes` (`join_code`); 