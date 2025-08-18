-- IntelliEVal System Database Tables
-- Evaluation Categories and Questionnaires

-- Evaluation Categories table
CREATE TABLE IF NOT EXISTS `evaluation_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `status` (`status`),
  CONSTRAINT `fk_evaluation_categories_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Questionnaires table
CREATE TABLE IF NOT EXISTS `questionnaires` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `question_type` enum('multiple_choice','text','rating','yes_no') NOT NULL,
  `options` json DEFAULT NULL,
  `required` tinyint(1) DEFAULT 1,
  `order_number` int(11) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  KEY `created_by` (`created_by`),
  KEY `status` (`status`),
  KEY `order_number` (`order_number`),
  CONSTRAINT `fk_questionnaires_category` FOREIGN KEY (`category_id`) REFERENCES `evaluation_categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_questionnaires_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Student Evaluations table
CREATE TABLE IF NOT EXISTS `student_evaluations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `evaluated_by` int(11) NOT NULL,
  `evaluation_date` date NOT NULL,
  `status` enum('draft','completed','archived') DEFAULT 'draft',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `category_id` (`category_id`),
  KEY `evaluated_by` (`evaluated_by`),
  KEY `status` (`status`),
  KEY `evaluation_date` (`evaluation_date`),
  CONSTRAINT `fk_student_evaluations_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_student_evaluations_category` FOREIGN KEY (`category_id`) REFERENCES `evaluation_categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_student_evaluations_evaluated_by` FOREIGN KEY (`evaluated_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Evaluation Responses table
CREATE TABLE IF NOT EXISTS `evaluation_responses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `evaluation_id` int(11) NOT NULL,
  `questionnaire_id` int(11) NOT NULL,
  `response` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `evaluation_questionnaire` (`evaluation_id`, `questionnaire_id`),
  KEY `questionnaire_id` (`questionnaire_id`),
  CONSTRAINT `fk_evaluation_responses_evaluation` FOREIGN KEY (`evaluation_id`) REFERENCES `student_evaluations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_evaluation_responses_questionnaire` FOREIGN KEY (`questionnaire_id`) REFERENCES `questionnaires` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample evaluation categories
INSERT INTO `evaluation_categories` (`name`, `description`, `created_by`) VALUES
('Academic Performance', 'Evaluation of student academic achievements and progress', 1),
('Behavioral Assessment', 'Assessment of student behavior and conduct', 1),
('Social Skills', 'Evaluation of interpersonal and social interaction skills', 1),
('Career Guidance', 'Assessment for career planning and guidance', 1),
('Personal Development', 'Evaluation of personal growth and development areas', 1);

-- Insert sample questionnaires for Academic Performance
INSERT INTO `questionnaires` (`category_id`, `question_text`, `question_type`, `options`, `required`, `order_number`, `created_by`) VALUES
(1, 'How would you rate the student\'s overall academic performance?', 'rating', '["Poor", "Fair", "Good", "Very Good", "Excellent"]', 1, 1, 1),
(1, 'Does the student complete assignments on time?', 'yes_no', NULL, 1, 2, 1),
(1, 'What is the student\'s current GPA?', 'text', NULL, 1, 3, 1),
(1, 'Which subjects does the student excel in?', 'multiple_choice', '["Mathematics", "Science", "English", "History", "Arts", "Physical Education"]', 0, 4, 1),
(1, 'What areas need improvement in academics?', 'text', NULL, 1, 5, 1);

-- Insert sample questionnaires for Behavioral Assessment
INSERT INTO `questionnaires` (`category_id`, `question_text`, `question_type`, `options`, `required`, `order_number`, `created_by`) VALUES
(2, 'How would you rate the student\'s classroom behavior?', 'rating', '["Poor", "Fair", "Good", "Very Good", "Excellent"]', 1, 1, 1),
(2, 'Does the student follow classroom rules?', 'yes_no', NULL, 1, 2, 1),
(2, 'How does the student interact with peers?', 'multiple_choice', '["Very Well", "Well", "Average", "Poorly", "Very Poorly"]', 1, 3, 1),
(2, 'Describe any behavioral concerns:', 'text', NULL, 0, 4, 1),
(2, 'What positive behaviors have you observed?', 'text', NULL, 0, 5, 1);

-- Insert sample questionnaires for Social Skills
INSERT INTO `questionnaires` (`category_id`, `question_text`, `question_type`, `options`, `required`, `order_number`, `created_by`) VALUES
(3, 'How would you rate the student\'s communication skills?', 'rating', '["Poor", "Fair", "Good", "Very Good", "Excellent"]', 1, 1, 1),
(3, 'Does the student participate in group activities?', 'yes_no', NULL, 1, 2, 1),
(3, 'How does the student handle conflicts?', 'multiple_choice', '["Very Well", "Well", "Average", "Poorly", "Very Poorly"]', 1, 3, 1),
(3, 'Describe the student\'s leadership qualities:', 'text', NULL, 0, 4, 1),
(3, 'What social skills need development?', 'text', NULL, 1, 5, 1);

-- Create indexes for better performance
CREATE INDEX idx_evaluation_categories_status_created ON evaluation_categories(status, created_at);
CREATE INDEX idx_questionnaires_category_order ON questionnaires(category_id, order_number, status);
CREATE INDEX idx_student_evaluations_student_date ON student_evaluations(student_id, evaluation_date);
CREATE INDEX idx_evaluation_responses_evaluation ON evaluation_responses(evaluation_id);

-- Create view for evaluation summary
CREATE VIEW `evaluation_summary_view` AS
SELECT 
    se.id,
    se.student_id,
    s.first_name,
    s.last_name,
    s.student_id as student_number,
    ec.name as category_name,
    se.evaluation_date,
    se.status,
    se.notes,
    u.first_name as evaluator_first_name,
    u.last_name as evaluator_last_name,
    COUNT(er.id) as total_responses
FROM student_evaluations se
JOIN students s ON se.student_id = s.id
JOIN evaluation_categories ec ON se.category_id = ec.id
JOIN users u ON se.evaluated_by = u.id
LEFT JOIN evaluation_responses er ON se.id = er.evaluation_id
GROUP BY se.id; 