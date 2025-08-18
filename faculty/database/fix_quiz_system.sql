-- Fix Quiz System Database Issues
-- Run this script to fix all quiz-related database problems

-- 1. Update quizzes table structure
ALTER TABLE `quizzes` MODIFY COLUMN `quiz_type` enum('general','lesson_specific','multiple_choice','true_false','essay','mixed') DEFAULT 'general';
ALTER TABLE `quizzes` MODIFY COLUMN `status` enum('draft','published','archived') DEFAULT 'draft';

-- 2. Add missing columns to quizzes table
ALTER TABLE `quizzes` ADD COLUMN IF NOT EXISTS `lesson_id` int(11) DEFAULT NULL AFTER `quiz_type`;
ALTER TABLE `quizzes` ADD COLUMN IF NOT EXISTS `max_attempts` int(11) DEFAULT 1 COMMENT 'Maximum attempts allowed' AFTER `passing_score`;

-- 3. Update existing status values
UPDATE `quizzes` SET `status` = 'published' WHERE `status` = 'active';

-- 4. Update existing quiz_type values to valid ones
UPDATE `quizzes` SET `quiz_type` = 'general' WHERE `quiz_type` NOT IN ('general','lesson_specific','multiple_choice','true_false','essay','mixed');

-- 5. Add indexes
ALTER TABLE `quizzes` ADD INDEX IF NOT EXISTS `lesson_id` (`lesson_id`);

-- 6. Create quiz_question_options table if it doesn't exist
CREATE TABLE IF NOT EXISTS `quiz_question_options` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `question_id` int(11) NOT NULL,
  `option_text` text NOT NULL,
  `is_correct` tinyint(1) DEFAULT 0 COMMENT 'Is this the correct answer',
  `order_number` int(11) DEFAULT 0 COMMENT 'Option order',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `question_id` (`question_id`),
  KEY `is_correct` (`is_correct`),
  KEY `order_number` (`order_number`),
  CONSTRAINT `fk_quiz_options_question` FOREIGN KEY (`question_id`) REFERENCES `quiz_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Create quiz_answers table if it doesn't exist (for student submissions)
CREATE TABLE IF NOT EXISTS `quiz_answers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `submission_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `answer` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `submission_id` (`submission_id`),
  KEY `question_id` (`question_id`),
  CONSTRAINT `fk_quiz_answers_submission` FOREIGN KEY (`submission_id`) REFERENCES `quiz_submissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_quiz_answers_question` FOREIGN KEY (`question_id`) REFERENCES `quiz_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Create quiz_submissions table if it doesn't exist
CREATE TABLE IF NOT EXISTS `quiz_submissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `assignment_id` int(11) NOT NULL COMMENT 'Reference to quiz_class_assignments',
  `student_id` int(11) NOT NULL,
  `attempt_number` int(11) DEFAULT 1 COMMENT 'Which attempt this is',
  `start_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `end_time` timestamp NULL DEFAULT NULL,
  `score` decimal(5,2) DEFAULT NULL COMMENT 'Final score percentage',
  `status` enum('in_progress','completed','abandoned','expired') DEFAULT 'in_progress',
  `time_taken` int(11) DEFAULT NULL COMMENT 'Time taken in seconds',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IP address of submission',
  `user_agent` text DEFAULT NULL COMMENT 'Browser/user agent info',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `assignment_id` (`assignment_id`),
  KEY `student_id` (`student_id`),
  KEY `status` (`status`),
  KEY `score` (`score`),
  KEY `start_time` (`start_time`),
  KEY `end_time` (`end_time`),
  CONSTRAINT `fk_quiz_submissions_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `quiz_class_assignments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_quiz_submissions_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Create quiz_class_assignments table if it doesn't exist
CREATE TABLE IF NOT EXISTS `quiz_class_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quiz_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `due_date` datetime NOT NULL COMMENT 'Due date and time for the quiz',
  `time_limit` int(11) DEFAULT NULL COMMENT 'Time limit in minutes for this assignment',
  `max_attempts` int(11) DEFAULT 1 COMMENT 'Maximum attempts allowed',
  `assigned_by` int(11) NOT NULL COMMENT 'Teacher who assigned the quiz',
  `assigned_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('active','inactive','expired') DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_quiz_class` (`quiz_id`, `class_id`),
  KEY `quiz_id` (`quiz_id`),
  KEY `class_id` (`class_id`),
  KEY `due_date` (`due_date`),
  KEY `status` (`status`),
  KEY `assigned_by` (`assigned_by`),
  CONSTRAINT `fk_quiz_assignments_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_quiz_assignments_class` FOREIGN KEY (`class_id`) REFERENCES `teacher_classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_quiz_assignments_teacher` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Add additional indexes for better performance
CREATE INDEX IF NOT EXISTS `idx_quizzes_teacher_status` ON `quizzes` (`teacher_id`, `status`);
CREATE INDEX IF NOT EXISTS `idx_quiz_questions_quiz_order` ON `quiz_questions` (`quiz_id`, `order_number`);
CREATE INDEX IF NOT EXISTS `idx_quiz_options_question_order` ON `quiz_question_options` (`question_id`, `order_number`);
CREATE INDEX IF NOT EXISTS `idx_quiz_assignments_due_date` ON `quiz_class_assignments` (`due_date`, `status`);
CREATE INDEX IF NOT EXISTS `idx_quiz_submissions_assignment_student` ON `quiz_submissions` (`assignment_id`, `student_id`);
CREATE INDEX IF NOT EXISTS `idx_quiz_submissions_score` ON `quiz_submissions` (`score` DESC);

-- 11. Insert sample quiz if no quizzes exist
INSERT IGNORE INTO `quizzes` (`teacher_id`, `title`, `description`, `quiz_type`, `time_limit`, `passing_score`, `max_attempts`, `status`, `created_at`) VALUES
(1, 'Sample Quiz', 'This is a sample quiz to test the system', 'general', 30, 70, 1, 'draft', NOW());

-- 12. Show current quiz count
SELECT COUNT(*) as total_quizzes FROM quizzes; 