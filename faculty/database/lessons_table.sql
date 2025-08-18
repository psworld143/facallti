-- Lessons Table for Faculty Module
-- This table stores lesson materials created by teachers

CREATE TABLE IF NOT EXISTS `lessons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `content` longtext DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_type` varchar(255) DEFAULT NULL COMMENT 'MIME type of the file',
  `file_size` int(11) DEFAULT NULL,
  `lesson_type` enum('text','video','document','presentation','link') DEFAULT 'text',
  `status` enum('draft','published','archived') DEFAULT 'draft',
  `order_number` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `teacher_id` (`teacher_id`),
  KEY `lesson_type` (`lesson_type`),
  KEY `status` (`status`),
  KEY `order_number` (`order_number`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `fk_lessons_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lesson Class Assignments Table
-- This table links lessons to specific classes
CREATE TABLE IF NOT EXISTS `lesson_class_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lesson_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_lesson_class` (`lesson_id`, `class_id`),
  KEY `lesson_id` (`lesson_id`),
  KEY `class_id` (`class_id`),
  CONSTRAINT `fk_lesson_assignments_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lesson_assignments_class` FOREIGN KEY (`class_id`) REFERENCES `teacher_classes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add indexes for better performance
CREATE INDEX `idx_lessons_teacher_status` ON `lessons` (`teacher_id`, `status`);
CREATE INDEX `idx_lessons_type_status` ON `lessons` (`lesson_type`, `status`);
CREATE INDEX `idx_lesson_assignments_class` ON `lesson_class_assignments` (`class_id`); 