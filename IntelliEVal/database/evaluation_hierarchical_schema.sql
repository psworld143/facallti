-- IntelliEVal System - Hierarchical Evaluation Schema
-- Supports Main Categories, Sub-Categories, and Questionnaires with 1-5 Rating Scale

-- =====================================================
-- MAIN EVALUATION CATEGORIES
-- =====================================================

-- Main evaluation categories (Student to Teacher, Peer to Peer, Head to Teacher)
CREATE TABLE IF NOT EXISTS `main_evaluation_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `evaluation_type` enum('student_to_teacher','peer_to_peer','head_to_teacher') NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `evaluation_type` (`evaluation_type`),
  KEY `created_by` (`created_by`),
  KEY `status` (`status`),
  CONSTRAINT `fk_main_evaluation_categories_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SUB-CATEGORIES
-- =====================================================

-- Sub-categories (Classroom Management, Teaching Skills, etc.)
CREATE TABLE IF NOT EXISTS `evaluation_sub_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `main_category_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `order_number` int(11) DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `main_category_id` (`main_category_id`),
  KEY `created_by` (`created_by`),
  KEY `status` (`status`),
  KEY `order_number` (`order_number`),
  CONSTRAINT `fk_evaluation_sub_categories_main` FOREIGN KEY (`main_category_id`) REFERENCES `main_evaluation_categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_evaluation_sub_categories_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- QUESTIONNAIRES
-- =====================================================

-- Questionnaires with standardized 1-5 rating scale
CREATE TABLE IF NOT EXISTS `evaluation_questionnaires` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sub_category_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `question_type` enum('rating_1_5','text','yes_no','multiple_choice') NOT NULL DEFAULT 'rating_1_5',
  `rating_labels` json DEFAULT NULL COMMENT 'Custom labels for 1-5 scale',
  `options` json DEFAULT NULL COMMENT 'For multiple choice questions',
  `required` tinyint(1) DEFAULT 1,
  `order_number` int(11) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `sub_category_id` (`sub_category_id`),
  KEY `created_by` (`created_by`),
  KEY `status` (`status`),
  KEY `order_number` (`order_number`),
  CONSTRAINT `fk_evaluation_questionnaires_sub_category` FOREIGN KEY (`sub_category_id`) REFERENCES `evaluation_sub_categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_evaluation_questionnaires_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- EVALUATION SESSIONS
-- =====================================================

-- Evaluation sessions to track individual evaluations
CREATE TABLE IF NOT EXISTS `evaluation_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `evaluator_id` int(11) NOT NULL COMMENT 'Person conducting the evaluation',
  `evaluator_type` enum('student','teacher','head') NOT NULL,
  `evaluatee_id` int(11) NOT NULL COMMENT 'Person being evaluated',
  `evaluatee_type` enum('teacher','student','head') NOT NULL,
  `main_category_id` int(11) NOT NULL,
  `semester_id` int(11) DEFAULT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `evaluation_date` date NOT NULL,
  `status` enum('draft','completed','archived') DEFAULT 'draft',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `evaluator_id` (`evaluator_id`),
  KEY `evaluatee_id` (`evaluatee_id`),
  KEY `main_category_id` (`main_category_id`),
  KEY `semester_id` (`semester_id`),
  KEY `subject_id` (`subject_id`),
  KEY `status` (`status`),
  KEY `evaluation_date` (`evaluation_date`),
  CONSTRAINT `fk_evaluation_sessions_evaluator` FOREIGN KEY (`evaluator_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_evaluation_sessions_evaluatee` FOREIGN KEY (`evaluatee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_evaluation_sessions_main_category` FOREIGN KEY (`main_category_id`) REFERENCES `main_evaluation_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- EVALUATION RESPONSES
-- =====================================================

-- Individual responses to questionnaires
CREATE TABLE IF NOT EXISTS `evaluation_responses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `evaluation_session_id` int(11) NOT NULL,
  `questionnaire_id` int(11) NOT NULL,
  `rating_value` int(11) DEFAULT NULL COMMENT '1-5 rating value',
  `text_response` text DEFAULT NULL,
  `multiple_choice_response` varchar(255) DEFAULT NULL,
  `yes_no_response` enum('yes','no') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `evaluation_questionnaire` (`evaluation_session_id`, `questionnaire_id`),
  KEY `questionnaire_id` (`questionnaire_id`),
  KEY `rating_value` (`rating_value`),
  CONSTRAINT `fk_evaluation_responses_session` FOREIGN KEY (`evaluation_session_id`) REFERENCES `evaluation_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_evaluation_responses_questionnaire` FOREIGN KEY (`questionnaire_id`) REFERENCES `evaluation_questionnaires` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SAMPLE DATA
-- =====================================================

-- Insert main evaluation categories
INSERT INTO `main_evaluation_categories` (`name`, `description`, `evaluation_type`, `created_by`) VALUES
('Student to Teacher Evaluation', 'Students evaluate their teachers on various aspects of teaching and classroom management', 'student_to_teacher', 1),
('Peer to Peer Evaluation', 'Teachers evaluate their colleagues on professional competence and collaboration', 'peer_to_peer', 1),
('Head to Teacher Evaluation', 'Department heads and administrators evaluate teachers on leadership and administrative skills', 'head_to_teacher', 1);

-- Insert sub-categories for Student to Teacher Evaluation
INSERT INTO `evaluation_sub_categories` (`main_category_id`, `name`, `description`, `order_number`, `created_by`) VALUES
(1, 'Classroom Management', 'Evaluation of teacher\'s ability to maintain order and create a conducive learning environment', 1, 1),
(1, 'Teaching Skills', 'Assessment of teacher\'s instructional methods and delivery', 2, 1),
(1, 'Subject Knowledge', 'Evaluation of teacher\'s mastery of the subject matter', 3, 1),
(1, 'Communication Skills', 'Assessment of teacher\'s ability to communicate effectively with students', 4, 1),
(1, 'Student Engagement', 'Evaluation of how well the teacher engages students in learning', 5, 1);

-- Insert sub-categories for Peer to Peer Evaluation
INSERT INTO `evaluation_sub_categories` (`main_category_id`, `name`, `description`, `order_number`, `created_by`) VALUES
(2, 'Professional Competence', 'Evaluation of colleague\'s professional skills and knowledge', 1, 1),
(2, 'Collaboration', 'Assessment of teamwork and cooperation with colleagues', 2, 1),
(2, 'Innovation', 'Evaluation of teaching innovations and creativity', 3, 1),
(2, 'Mentoring', 'Assessment of ability to mentor and support other teachers', 4, 1);

-- Insert sub-categories for Head to Teacher Evaluation
INSERT INTO `evaluation_sub_categories` (`main_category_id`, `name`, `description`, `order_number`, `created_by`) VALUES
(3, 'Leadership', 'Assessment of leadership qualities and initiative', 1, 1),
(3, 'Administrative Skills', 'Evaluation of administrative and organizational skills', 2, 1),
(3, 'Professional Development', 'Assessment of continuous learning and growth', 3, 1),
(3, 'Compliance', 'Evaluation of adherence to policies and procedures', 4, 1);

-- Insert sample questionnaires for Classroom Management (Student to Teacher)
INSERT INTO `evaluation_questionnaires` (`sub_category_id`, `question_text`, `question_type`, `rating_labels`, `required`, `order_number`, `created_by`) VALUES
(1, 'How well does the teacher maintain classroom discipline?', 'rating_1_5', '["1 - Poor", "2 - Good", "3 - Satisfactory", "4 - Very Satisfactory", "5 - Excellent"]', 1, 1, 1),
(1, 'How organized is the classroom environment?', 'rating_1_5', '["1 - Poor", "2 - Good", "3 - Satisfactory", "4 - Very Satisfactory", "5 - Excellent"]', 1, 2, 1),
(1, 'How effectively does the teacher handle disruptive behavior?', 'rating_1_5', '["1 - Poor", "2 - Good", "3 - Satisfactory", "4 - Very Satisfactory", "5 - Excellent"]', 1, 3, 1),
(1, 'How well does the teacher manage time during lessons?', 'rating_1_5', '["1 - Poor", "2 - Good", "3 - Satisfactory", "4 - Very Satisfactory", "5 - Excellent"]', 1, 4, 1),
(1, 'How conducive is the learning atmosphere in the classroom?', 'rating_1_5', '["1 - Poor", "2 - Good", "3 - Satisfactory", "4 - Very Satisfactory", "5 - Excellent"]', 1, 5, 1);

-- Insert sample questionnaires for Teaching Skills (Student to Teacher)
INSERT INTO `evaluation_questionnaires` (`sub_category_id`, `question_text`, `question_type`, `rating_labels`, `required`, `order_number`, `created_by`) VALUES
(2, 'How clear and understandable are the teacher\'s explanations?', 'rating_1_5', '["1 - Poor", "2 - Good", "3 - Satisfactory", "4 - Very Satisfactory", "5 - Excellent"]', 1, 1, 1),
(2, 'How well does the teacher use different teaching methods?', 'rating_1_5', '["1 - Poor", "2 - Good", "3 - Satisfactory", "4 - Very Satisfactory", "5 - Excellent"]', 1, 2, 1),
(2, 'How effectively does the teacher use teaching aids and technology?', 'rating_1_5', '["1 - Poor", "2 - Good", "3 - Satisfactory", "4 - Very Satisfactory", "5 - Excellent"]', 1, 3, 1),
(2, 'How well does the teacher provide feedback on assignments?', 'rating_1_5', '["1 - Poor", "2 - Good", "3 - Satisfactory", "4 - Very Satisfactory", "5 - Excellent"]', 1, 4, 1),
(2, 'How accessible is the teacher for questions and clarifications?', 'rating_1_5', '["1 - Poor", "2 - Good", "3 - Satisfactory", "4 - Very Satisfactory", "5 - Excellent"]', 1, 5, 1);

-- Insert sample questionnaires for Professional Competence (Peer to Peer)
INSERT INTO `evaluation_questionnaires` (`sub_category_id`, `question_text`, `question_type`, `rating_labels`, `required`, `order_number`, `created_by`) VALUES
(5, 'How well does the colleague demonstrate subject matter expertise?', 'rating_1_5', '["1 - Poor", "2 - Good", "3 - Satisfactory", "4 - Very Satisfactory", "5 - Excellent"]', 1, 1, 1),
(5, 'How effectively does the colleague plan and organize lessons?', 'rating_1_5', '["1 - Poor", "2 - Good", "3 - Satisfactory", "4 - Very Satisfactory", "5 - Excellent"]', 1, 2, 1),
(5, 'How well does the colleague assess student learning?', 'rating_1_5', '["1 - Poor", "2 - Good", "3 - Satisfactory", "4 - Very Satisfactory", "5 - Excellent"]', 1, 3, 1),
(5, 'How committed is the colleague to professional development?', 'rating_1_5', '["1 - Poor", "2 - Good", "3 - Satisfactory", "4 - Very Satisfactory", "5 - Excellent"]', 1, 4, 1);

-- Insert sample questionnaires for Leadership (Head to Teacher)
INSERT INTO `evaluation_questionnaires` (`sub_category_id`, `question_text`, `question_type`, `rating_labels`, `required`, `order_number`, `created_by`) VALUES
(9, 'How well does the teacher demonstrate leadership in the department?', 'rating_1_5', '["1 - Poor", "2 - Good", "3 - Satisfactory", "4 - Very Satisfactory", "5 - Excellent"]', 1, 1, 1),
(9, 'How effectively does the teacher take initiative in projects?', 'rating_1_5', '["1 - Poor", "2 - Good", "3 - Satisfactory", "4 - Very Satisfactory", "5 - Excellent"]', 1, 2, 1),
(9, 'How well does the teacher mentor other faculty members?', 'rating_1_5', '["1 - Poor", "2 - Good", "3 - Satisfactory", "4 - Very Satisfactory", "5 - Excellent"]', 1, 3, 1),
(9, 'How effectively does the teacher represent the department?', 'rating_1_5', '["1 - Poor", "2 - Good", "3 - Satisfactory", "4 - Very Satisfactory", "5 - Excellent"]', 1, 4, 1);

-- =====================================================
-- INDEXES FOR PERFORMANCE
-- =====================================================

-- Performance indexes
CREATE INDEX `idx_main_evaluation_categories_type_status` ON `main_evaluation_categories` (`evaluation_type`, `status`);
CREATE INDEX `idx_evaluation_sub_categories_main_order` ON `evaluation_sub_categories` (`main_category_id`, `order_number`, `status`);
CREATE INDEX `idx_evaluation_questionnaires_sub_order` ON `evaluation_questionnaires` (`sub_category_id`, `order_number`, `status`);
CREATE INDEX `idx_evaluation_sessions_evaluator_type` ON `evaluation_sessions` (`evaluator_id`, `evaluator_type`);
CREATE INDEX `idx_evaluation_sessions_evaluatee_type` ON `evaluation_sessions` (`evaluatee_id`, `evaluatee_type`);
CREATE INDEX `idx_evaluation_sessions_main_category_status` ON `evaluation_sessions` (`main_category_id`, `status`);
CREATE INDEX `idx_evaluation_responses_session_questionnaire` ON `evaluation_responses` (`evaluation_session_id`, `questionnaire_id`);
CREATE INDEX `idx_evaluation_responses_rating` ON `evaluation_responses` (`rating_value`);

-- =====================================================
-- VIEWS FOR REPORTING
-- =====================================================

-- Evaluation summary view
CREATE OR REPLACE VIEW `evaluation_summary_view` AS
SELECT 
    es.id,
    es.evaluator_id,
    es.evaluator_type,
    es.evaluatee_id,
    es.evaluatee_type,
    es.main_category_id,
    mec.name as main_category_name,
    mec.evaluation_type,
    es.evaluation_date,
    es.status,
    es.notes,
    COUNT(er.id) as total_responses,
    AVG(er.rating_value) as average_rating,
    COUNT(CASE WHEN er.rating_value = 5 THEN 1 END) as excellent_count,
    COUNT(CASE WHEN er.rating_value = 4 THEN 1 END) as very_satisfactory_count,
    COUNT(CASE WHEN er.rating_value = 3 THEN 1 END) as satisfactory_count,
    COUNT(CASE WHEN er.rating_value = 2 THEN 1 END) as good_count,
    COUNT(CASE WHEN er.rating_value = 1 THEN 1 END) as poor_count
FROM evaluation_sessions es
JOIN main_evaluation_categories mec ON es.main_category_id = mec.id
LEFT JOIN evaluation_responses er ON es.id = er.evaluation_session_id
GROUP BY es.id;

-- Sub-category performance view
CREATE OR REPLACE VIEW `sub_category_performance_view` AS
SELECT 
    esc.id as sub_category_id,
    esc.name as sub_category_name,
    esc.main_category_id,
    mec.name as main_category_name,
    mec.evaluation_type,
    COUNT(er.id) as total_responses,
    AVG(er.rating_value) as average_rating,
    COUNT(CASE WHEN er.rating_value = 5 THEN 1 END) as excellent_count,
    COUNT(CASE WHEN er.rating_value = 4 THEN 1 END) as very_satisfactory_count,
    COUNT(CASE WHEN er.rating_value = 3 THEN 1 END) as satisfactory_count,
    COUNT(CASE WHEN er.rating_value = 2 THEN 1 END) as good_count,
    COUNT(CASE WHEN er.rating_value = 1 THEN 1 END) as poor_count
FROM evaluation_sub_categories esc
JOIN main_evaluation_categories mec ON esc.main_category_id = mec.id
LEFT JOIN evaluation_questionnaires eq ON esc.id = eq.sub_category_id
LEFT JOIN evaluation_responses er ON eq.id = er.questionnaire_id
WHERE esc.status = 'active' AND eq.status = 'active'
GROUP BY esc.id;

-- =====================================================
-- STORED PROCEDURES
-- =====================================================

-- Procedure to get evaluation statistics by main category
DELIMITER //
CREATE PROCEDURE GetEvaluationStatsByMainCategory(IN main_cat_id INT)
BEGIN
    SELECT 
        mec.name as main_category_name,
        mec.evaluation_type,
        COUNT(es.id) as total_evaluations,
        COUNT(CASE WHEN es.status = 'completed' THEN 1 END) as completed_evaluations,
        COUNT(CASE WHEN es.status = 'draft' THEN 1 END) as draft_evaluations,
        AVG(er.rating_value) as overall_average_rating
    FROM main_evaluation_categories mec
    LEFT JOIN evaluation_sessions es ON mec.id = es.main_category_id
    LEFT JOIN evaluation_responses er ON es.id = er.evaluation_session_id
    WHERE mec.id = main_cat_id
    GROUP BY mec.id;
END //
DELIMITER ;

-- Procedure to get sub-category performance
DELIMITER //
CREATE PROCEDURE GetSubCategoryPerformance(IN sub_cat_id INT)
BEGIN
    SELECT 
        esc.name as sub_category_name,
        COUNT(er.id) as total_responses,
        AVG(er.rating_value) as average_rating,
        COUNT(CASE WHEN er.rating_value = 5 THEN 1 END) as excellent_count,
        COUNT(CASE WHEN er.rating_value = 4 THEN 1 END) as very_satisfactory_count,
        COUNT(CASE WHEN er.rating_value = 3 THEN 1 END) as satisfactory_count,
        COUNT(CASE WHEN er.rating_value = 2 THEN 1 END) as good_count,
        COUNT(CASE WHEN er.rating_value = 1 THEN 1 END) as poor_count
    FROM evaluation_sub_categories esc
    LEFT JOIN evaluation_questionnaires eq ON esc.id = eq.sub_category_id
    LEFT JOIN evaluation_responses er ON eq.id = er.questionnaire_id
    WHERE esc.id = sub_cat_id
    GROUP BY esc.id;
END //
DELIMITER ; 