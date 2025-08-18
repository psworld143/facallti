-- Subject Syllabus Table for Faculty Module
-- This table stores syllabus information for each class/subject

CREATE TABLE IF NOT EXISTS `class_syllabus` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `course_objectives` longtext DEFAULT NULL,
  `learning_outcomes` longtext DEFAULT NULL,
  `prerequisites` text DEFAULT NULL,
  `course_requirements` longtext DEFAULT NULL,
  `grading_system` longtext DEFAULT NULL,
  `course_policies` longtext DEFAULT NULL,
  `academic_integrity` text DEFAULT NULL,
  `attendance_policy` text DEFAULT NULL,
  `late_submission_policy` text DEFAULT NULL,
  `office_hours` text DEFAULT NULL,
  `contact_information` text DEFAULT NULL,
  `textbooks` longtext DEFAULT NULL,
  `references` longtext DEFAULT NULL,
  `schedule` longtext DEFAULT NULL,
  `assessment_methods` longtext DEFAULT NULL,
  `is_published` tinyint(1) DEFAULT 0,
  `published_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_class_syllabus` (`class_id`),
  KEY `teacher_id` (`teacher_id`),
  KEY `is_published` (`is_published`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `fk_syllabus_class` FOREIGN KEY (`class_id`) REFERENCES `teacher_classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_syllabus_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Syllabus Topics/Units Table
CREATE TABLE IF NOT EXISTS `syllabus_topics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `syllabus_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `week_number` int(11) DEFAULT NULL,
  `duration_hours` int(11) DEFAULT NULL,
  `learning_objectives` text DEFAULT NULL,
  `activities` text DEFAULT NULL,
  `assessments` text DEFAULT NULL,
  `materials` text DEFAULT NULL,
  `order_number` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `syllabus_id` (`syllabus_id`),
  KEY `week_number` (`week_number`),
  KEY `order_number` (`order_number`),
  CONSTRAINT `fk_syllabus_topics` FOREIGN KEY (`syllabus_id`) REFERENCES `class_syllabus` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Syllabus Files Table (for attachments)
CREATE TABLE IF NOT EXISTS `syllabus_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `syllabus_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) NOT NULL,
  `file_type` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `syllabus_id` (`syllabus_id`),
  CONSTRAINT `fk_syllabus_files` FOREIGN KEY (`syllabus_id`) REFERENCES `class_syllabus` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add indexes for better performance
CREATE INDEX `idx_syllabus_teacher_date` ON `class_syllabus` (`teacher_id`, `created_at`);
CREATE INDEX `idx_syllabus_published_date` ON `class_syllabus` (`is_published`, `published_at`);
CREATE INDEX `idx_topics_syllabus_order` ON `syllabus_topics` (`syllabus_id`, `order_number`); 