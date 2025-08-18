-- Quiz System Database Tables
-- This file creates all necessary tables for the quiz functionality

-- =====================================================
-- QUIZZES TABLE
-- =====================================================

CREATE TABLE IF NOT EXISTS `quizzes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `quiz_type` enum('general','lesson_specific','multiple_choice','true_false','essay','mixed') DEFAULT 'general',
  `lesson_id` int(11) DEFAULT NULL,
  `time_limit` int(11) DEFAULT NULL COMMENT 'Time limit in minutes, NULL for no limit',
  `passing_score` int(11) DEFAULT 70 COMMENT 'Passing score percentage',
  `max_attempts` int(11) DEFAULT 1 COMMENT 'Maximum attempts allowed',
  `status` enum('draft','published','archived') DEFAULT 'draft',
  `is_randomized` tinyint(1) DEFAULT 0 COMMENT 'Randomize question order',
  `show_correct_answers` tinyint(1) DEFAULT 1 COMMENT 'Show correct answers after submission',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `teacher_id` (`teacher_id`),
  KEY `status` (`status`),
  KEY `quiz_type` (`quiz_type`),
  KEY `lesson_id` (`lesson_id`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `fk_quizzes_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_quizzes_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- QUIZ QUESTIONS TABLE
-- =====================================================

CREATE TABLE IF NOT EXISTS `quiz_questions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quiz_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `question_type` enum('multiple_choice','true_false','essay','short_answer') DEFAULT 'multiple_choice',
  `points` int(11) DEFAULT 1 COMMENT 'Points for this question',
  `order_number` int(11) DEFAULT 0 COMMENT 'Question order in quiz',
  `is_required` tinyint(1) DEFAULT 1 COMMENT 'Is this question required',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `quiz_id` (`quiz_id`),
  KEY `question_type` (`question_type`),
  KEY `order_number` (`order_number`),
  CONSTRAINT `fk_quiz_questions_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- QUIZ QUESTION OPTIONS TABLE
-- =====================================================

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

-- =====================================================
-- QUIZ CLASS ASSIGNMENTS TABLE (Updated)
-- =====================================================

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

-- =====================================================
-- QUIZ SUBMISSIONS TABLE
-- =====================================================

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

-- =====================================================
-- QUIZ SUBMISSION ANSWERS TABLE
-- =====================================================

CREATE TABLE IF NOT EXISTS `quiz_submission_answers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `submission_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `selected_option_id` int(11) DEFAULT NULL COMMENT 'For multiple choice/true false',
  `text_answer` text DEFAULT NULL COMMENT 'For essay/short answer questions',
  `is_correct` tinyint(1) DEFAULT NULL COMMENT 'Whether the answer is correct',
  `points_earned` decimal(5,2) DEFAULT 0 COMMENT 'Points earned for this answer',
  `feedback` text DEFAULT NULL COMMENT 'Teacher feedback for essay questions',
  `answered_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `submission_id` (`submission_id`),
  KEY `question_id` (`question_id`),
  KEY `selected_option_id` (`selected_option_id`),
  KEY `is_correct` (`is_correct`),
  CONSTRAINT `fk_submission_answers_submission` FOREIGN KEY (`submission_id`) REFERENCES `quiz_submissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_submission_answers_question` FOREIGN KEY (`question_id`) REFERENCES `quiz_questions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_submission_answers_option` FOREIGN KEY (`selected_option_id`) REFERENCES `quiz_question_options` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- INDEXES FOR PERFORMANCE
-- =====================================================

-- Add additional indexes for better performance
CREATE INDEX `idx_quizzes_teacher_status` ON `quizzes` (`teacher_id`, `status`);
CREATE INDEX `idx_quiz_questions_quiz_order` ON `quiz_questions` (`quiz_id`, `order_number`);
CREATE INDEX `idx_quiz_options_question_order` ON `quiz_question_options` (`question_id`, `order_number`);
CREATE INDEX `idx_quiz_assignments_due_date` ON `quiz_class_assignments` (`due_date`, `status`);
CREATE INDEX `idx_quiz_submissions_assignment_student` ON `quiz_submissions` (`assignment_id`, `student_id`);
CREATE INDEX `idx_quiz_submissions_score` ON `quiz_submissions` (`score` DESC);
CREATE INDEX `idx_submission_answers_submission_question` ON `quiz_submission_answers` (`submission_id`, `question_id`);

-- =====================================================
-- VIEWS FOR COMMON QUERIES
-- =====================================================

-- View for quiz statistics
CREATE OR REPLACE VIEW `quiz_statistics` AS
SELECT 
    q.id as quiz_id,
    q.title as quiz_title,
    q.teacher_id,
    COUNT(DISTINCT qq.id) as total_questions,
    COUNT(DISTINCT qca.id) as total_assignments,
    COUNT(DISTINCT qs.id) as total_submissions,
    COUNT(DISTINCT CASE WHEN qs.status = 'completed' THEN qs.id END) as completed_submissions,
    AVG(CASE WHEN qs.score IS NOT NULL THEN qs.score END) as average_score,
    MIN(CASE WHEN qs.score IS NOT NULL THEN qs.score END) as lowest_score,
    MAX(CASE WHEN qs.score IS NOT NULL THEN qs.score END) as highest_score
FROM quizzes q
LEFT JOIN quiz_questions qq ON q.id = qq.quiz_id
LEFT JOIN quiz_class_assignments qca ON q.id = qca.quiz_id
LEFT JOIN quiz_submissions qs ON qca.id = qs.assignment_id
GROUP BY q.id, q.title, q.teacher_id;

-- View for class quiz assignments with statistics
CREATE OR REPLACE VIEW `class_quiz_assignments_stats` AS
SELECT 
    qca.id as assignment_id,
    qca.quiz_id,
    qca.class_id,
    qca.due_date,
    qca.time_limit,
    qca.max_attempts,
    qca.status as assignment_status,
    q.title as quiz_title,
    q.quiz_type,
    tc.section,
    cc.subject_title,
    COUNT(DISTINCT qs.id) as total_submissions,
    COUNT(DISTINCT CASE WHEN qs.status = 'completed' THEN qs.id END) as completed_submissions,
    AVG(CASE WHEN qs.score IS NOT NULL THEN qs.score END) as average_score
FROM quiz_class_assignments qca
JOIN quizzes q ON qca.quiz_id = q.id
JOIN teacher_classes tc ON qca.class_id = tc.id
JOIN course_curriculum cc ON tc.subject_id = cc.id
LEFT JOIN quiz_submissions qs ON qca.id = qs.assignment_id
GROUP BY qca.id, qca.quiz_id, qca.class_id, qca.due_date, qca.time_limit, qca.max_attempts, qca.status, q.title, q.quiz_type, tc.section, cc.subject_title;

-- =====================================================
-- SAMPLE DATA (Optional)
-- =====================================================

-- Insert sample quiz (optional - remove if not needed)
INSERT INTO `quizzes` (`teacher_id`, `title`, `description`, `quiz_type`, `time_limit`, `passing_score`, `status`, `created_at`) VALUES
(1, 'Introduction to Programming Quiz', 'Basic concepts of programming and algorithms', 'multiple_choice', 30, 70, 'active', NOW()),
(1, 'Database Fundamentals', 'SQL basics and database design principles', 'mixed', 45, 75, 'active', NOW());

-- Insert sample questions (optional - remove if not needed)
INSERT INTO `quiz_questions` (`quiz_id`, `question_text`, `question_type`, `points`, `order_number`, `created_at`) VALUES
(1, 'What is a variable in programming?', 'multiple_choice', 2, 1, NOW()),
(1, 'Which of the following is a programming language?', 'multiple_choice', 2, 2, NOW()),
(1, 'Programming is the process of creating instructions for a computer to follow.', 'true_false', 1, 3, NOW()),
(2, 'What does SQL stand for?', 'multiple_choice', 2, 1, NOW()),
(2, 'Explain the difference between a primary key and a foreign key.', 'essay', 5, 2, NOW());

-- Insert sample options (optional - remove if not needed)
INSERT INTO `quiz_question_options` (`question_id`, `option_text`, `is_correct`, `order_number`, `created_at`) VALUES
(1, 'A container that stores data values', 1, 1, NOW()),
(1, 'A type of computer hardware', 0, 2, NOW()),
(1, 'A programming language', 0, 3, NOW()),
(1, 'A database table', 0, 4, NOW()),
(2, 'Python', 1, 1, NOW()),
(2, 'HTML', 0, 2, NOW()),
(2, 'CSS', 0, 3, NOW()),
(2, 'JPEG', 0, 4, NOW()),
(3, 'True', 1, 1, NOW()),
(3, 'False', 0, 2, NOW()),
(4, 'Structured Query Language', 1, 1, NOW()),
(4, 'Simple Query Language', 0, 2, NOW()),
(4, 'Standard Query Language', 0, 3, NOW()),
(4, 'System Query Language', 0, 4, NOW()); 