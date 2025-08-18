-- IntelliEVal System - Training and Seminar Management Schema
-- This module allows guidance officers to create trainings/seminars aligned with evaluation categories

-- Training/Seminar Categories
CREATE TABLE IF NOT EXISTS `training_categories` (
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
  CONSTRAINT `fk_training_categories_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Trainings and Seminars
CREATE TABLE IF NOT EXISTS `trainings_seminars` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `type` enum('training','seminar','workshop','conference') NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `main_category_id` int(11) DEFAULT NULL COMMENT 'Linked to evaluation main category',
  `sub_category_id` int(11) DEFAULT NULL COMMENT 'Linked to evaluation sub-category',
  `duration_hours` decimal(5,2) DEFAULT NULL,
  `max_participants` int(11) DEFAULT NULL,
  `venue` varchar(255) DEFAULT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `registration_deadline` datetime DEFAULT NULL,
  `status` enum('draft','published','ongoing','completed','cancelled') DEFAULT 'draft',
  `is_mandatory` tinyint(1) DEFAULT 0,
  `certificate_provided` tinyint(1) DEFAULT 0,
  `materials_provided` tinyint(1) DEFAULT 0,
  `cost` decimal(10,2) DEFAULT 0.00,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  KEY `main_category_id` (`main_category_id`),
  KEY `sub_category_id` (`sub_category_id`),
  KEY `status` (`status`),
  KEY `start_date` (`start_date`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `fk_trainings_seminars_category` FOREIGN KEY (`category_id`) REFERENCES `training_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_trainings_seminars_main_category` FOREIGN KEY (`main_category_id`) REFERENCES `main_evaluation_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_trainings_seminars_sub_category` FOREIGN KEY (`sub_category_id`) REFERENCES `evaluation_sub_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_trainings_seminars_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Training/Seminar Registration
CREATE TABLE IF NOT EXISTS `training_registrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `training_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `registration_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('registered','attended','completed','no_show','cancelled') DEFAULT 'registered',
  `attendance_date` datetime DEFAULT NULL,
  `completion_date` datetime DEFAULT NULL,
  `certificate_issued` tinyint(1) DEFAULT 0,
  `certificate_issued_date` datetime DEFAULT NULL,
  `feedback_rating` int(11) DEFAULT NULL COMMENT '1-5 rating',
  `feedback_comments` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `training_user` (`training_id`, `user_id`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`),
  KEY `registration_date` (`registration_date`),
  CONSTRAINT `fk_training_registrations_training` FOREIGN KEY (`training_id`) REFERENCES `trainings_seminars` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_training_registrations_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Training Suggestions (AI-based recommendations)
CREATE TABLE IF NOT EXISTS `training_suggestions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `training_id` int(11) NOT NULL,
  `suggestion_reason` text NOT NULL COMMENT 'Why this training is suggested',
  `evaluation_category_id` int(11) DEFAULT NULL COMMENT 'Related evaluation category',
  `evaluation_score` decimal(3,2) DEFAULT NULL COMMENT 'Teacher\'s score in this category',
  `priority_level` enum('low','medium','high','critical') DEFAULT 'medium',
  `suggested_by` int(11) NOT NULL,
  `suggestion_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('pending','accepted','declined','completed') DEFAULT 'pending',
  `response_date` datetime DEFAULT NULL,
  `response_notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `training_id` (`training_id`),
  KEY `evaluation_category_id` (`evaluation_category_id`),
  KEY `priority_level` (`priority_level`),
  KEY `status` (`status`),
  KEY `suggested_by` (`suggested_by`),
  CONSTRAINT `fk_training_suggestions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_training_suggestions_training` FOREIGN KEY (`training_id`) REFERENCES `trainings_seminars` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_training_suggestions_category` FOREIGN KEY (`evaluation_category_id`) REFERENCES `evaluation_sub_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_training_suggestions_suggested_by` FOREIGN KEY (`suggested_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Training Materials
CREATE TABLE IF NOT EXISTS `training_materials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `training_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `download_count` int(11) DEFAULT 0,
  `is_public` tinyint(1) DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `training_id` (`training_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `fk_training_materials_training` FOREIGN KEY (`training_id`) REFERENCES `trainings_seminars` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_training_materials_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SAMPLE DATA
-- =====================================================

-- Insert sample training categories
INSERT INTO `training_categories` (`name`, `description`, `created_by`) VALUES
('Classroom Management', 'Trainings focused on improving classroom discipline and management skills', 1),
('Teaching Methodologies', 'Seminars on modern teaching techniques and strategies', 1),
('Technology Integration', 'Workshops on incorporating technology in teaching', 1),
('Student Engagement', 'Training on methods to increase student participation and engagement', 1),
('Assessment Strategies', 'Seminars on effective assessment and evaluation methods', 1),
('Professional Development', 'General professional development and career advancement', 1);

-- Insert sample trainings/seminars
INSERT INTO `trainings_seminars` (`title`, `description`, `type`, `category_id`, `main_category_id`, `sub_category_id`, `duration_hours`, `max_participants`, `venue`, `start_date`, `end_date`, `registration_deadline`, `status`, `is_mandatory`, `certificate_provided`, `materials_provided`, `cost`, `created_by`) VALUES
('Effective Classroom Management Strategies', 'Learn proven techniques for maintaining classroom discipline and creating a positive learning environment', 'training', 1, 1, 1, 8.00, 25, 'Conference Room A', '2024-03-15 09:00:00', '2024-03-15 17:00:00', '2024-03-10 17:00:00', 'published', 0, 1, 1, 0.00, 1),
('Modern Teaching Methodologies Workshop', 'Explore innovative teaching methods and strategies for better student engagement', 'workshop', 2, 1, 2, 6.00, 20, 'Training Room B', '2024-03-20 09:00:00', '2024-03-20 15:00:00', '2024-03-15 17:00:00', 'published', 0, 1, 1, 0.00, 1),
('Technology Integration in Education', 'Learn how to effectively use technology tools in your teaching practice', 'seminar', 3, 1, 4, 4.00, 30, 'Computer Lab', '2024-03-25 13:00:00', '2024-03-25 17:00:00', '2024-03-20 17:00:00', 'published', 0, 1, 0, 0.00, 1),
('Student Engagement Techniques', 'Discover methods to increase student participation and motivation', 'training', 4, 1, 5, 6.00, 25, 'Conference Room C', '2024-04-01 09:00:00', '2024-04-01 15:00:00', '2024-03-27 17:00:00', 'draft', 0, 1, 1, 0.00, 1),
('Assessment and Evaluation Best Practices', 'Learn effective assessment strategies and evaluation methods', 'seminar', 5, 1, 2, 4.00, 35, 'Lecture Hall', '2024-04-05 14:00:00', '2024-04-05 18:00:00', '2024-04-01 17:00:00', 'draft', 0, 1, 0, 0.00, 1);

-- =====================================================
-- INDEXES FOR PERFORMANCE
-- =====================================================

-- Training and seminar indexes
CREATE INDEX `idx_trainings_seminars_type_status` ON `trainings_seminars` (`type`, `status`);
CREATE INDEX `idx_trainings_seminars_dates` ON `trainings_seminars` (`start_date`, `end_date`);
CREATE INDEX `idx_trainings_seminars_category_status` ON `trainings_seminars` (`category_id`, `status`);
CREATE INDEX `idx_trainings_seminars_main_category` ON `trainings_seminars` (`main_category_id`, `status`);

-- Registration indexes
CREATE INDEX `idx_training_registrations_user_status` ON `training_registrations` (`user_id`, `status`);
CREATE INDEX `idx_training_registrations_training_status` ON `training_registrations` (`training_id`, `status`);
CREATE INDEX `idx_training_registrations_date` ON `training_registrations` (`registration_date`);

-- Suggestion indexes
CREATE INDEX `idx_training_suggestions_user_status` ON `training_suggestions` (`user_id`, `status`);
CREATE INDEX `idx_training_suggestions_priority` ON `training_suggestions` (`priority_level`, `status`);
CREATE INDEX `idx_training_suggestions_category` ON `training_suggestions` (`evaluation_category_id`);

-- =====================================================
-- VIEWS FOR REPORTING
-- =====================================================

-- Training summary view
CREATE OR REPLACE VIEW `training_summary_view` AS
SELECT 
    ts.id,
    ts.title,
    ts.type,
    ts.status,
    ts.start_date,
    ts.end_date,
    tc.name as category_name,
    mec.name as main_category_name,
    esc.name as sub_category_name,
    ts.max_participants,
    COUNT(tr.id) as registered_count,
    COUNT(CASE WHEN tr.status = 'completed' THEN 1 END) as completed_count,
    AVG(tr.feedback_rating) as average_feedback_rating
FROM trainings_seminars ts
LEFT JOIN training_categories tc ON ts.category_id = tc.id
LEFT JOIN main_evaluation_categories mec ON ts.main_category_id = mec.id
LEFT JOIN evaluation_sub_categories esc ON ts.sub_category_id = esc.id
LEFT JOIN training_registrations tr ON ts.id = tr.training_id
GROUP BY ts.id;

-- Teacher training needs view (based on evaluation scores)
CREATE OR REPLACE VIEW `teacher_training_needs_view` AS
SELECT 
    u.id as user_id,
    u.first_name,
    u.last_name,
    u.email,
    esc.id as sub_category_id,
    esc.name as sub_category_name,
    mec.name as main_category_name,
    AVG(er.rating_value) as average_rating,
    COUNT(er.id) as total_ratings,
    CASE 
        WHEN AVG(er.rating_value) < 3.0 THEN 'critical'
        WHEN AVG(er.rating_value) < 3.5 THEN 'high'
        WHEN AVG(er.rating_value) < 4.0 THEN 'medium'
        ELSE 'low'
    END as priority_level
FROM users u
JOIN evaluation_sessions es ON u.id = es.evaluatee_id
JOIN main_evaluation_categories mec ON es.main_category_id = mec.id
JOIN evaluation_sub_categories esc ON mec.id = esc.main_category_id
LEFT JOIN evaluation_questionnaires eq ON esc.id = eq.sub_category_id
LEFT JOIN evaluation_responses er ON eq.id = er.questionnaire_id AND es.id = er.evaluation_session_id
WHERE u.role = 'teacher' 
    AND es.evaluatee_type = 'teacher'
    AND es.status = 'completed'
    AND er.rating_value IS NOT NULL
GROUP BY u.id, esc.id
HAVING total_ratings >= 3; -- Minimum 3 ratings for reliable assessment

-- =====================================================
-- STORED PROCEDURES
-- =====================================================

-- Procedure to generate training suggestions based on evaluation scores (below 4.0 threshold)
DELIMITER //
CREATE PROCEDURE GenerateTrainingSuggestions(IN teacher_id INT)
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE sub_cat_id INT;
    DECLARE sub_cat_name VARCHAR(255);
    DECLARE main_cat_name VARCHAR(255);
    DECLARE avg_rating DECIMAL(3,2);
    DECLARE priority VARCHAR(20);
    DECLARE total_ratings INT;
    
    -- Cursor for teacher's evaluation scores by sub-category (only those below 4.0)
    DECLARE score_cursor CURSOR FOR
        SELECT 
            esc.id,
            esc.name,
            mec.name,
            AVG(er.rating_value) as avg_rating,
            COUNT(er.id) as total_ratings
        FROM evaluation_sub_categories esc
        JOIN main_evaluation_categories mec ON esc.main_category_id = mec.id
        JOIN evaluation_sessions es ON mec.id = es.main_category_id
        JOIN evaluation_questionnaires eq ON esc.id = eq.sub_category_id
        JOIN evaluation_responses er ON eq.id = er.questionnaire_id AND es.id = er.evaluation_session_id
        WHERE es.evaluatee_id = teacher_id 
            AND es.evaluatee_type = 'teacher'
            AND es.status = 'completed'
            AND er.rating_value IS NOT NULL
        GROUP BY esc.id
        HAVING AVG(er.rating_value) < 4.0 AND COUNT(er.id) >= 3;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN score_cursor;
    
    read_loop: LOOP
        FETCH score_cursor INTO sub_cat_id, sub_cat_name, main_cat_name, avg_rating, total_ratings;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        -- Determine priority based on average rating
        SET priority = CASE 
            WHEN avg_rating < 2.5 THEN 'critical'
            WHEN avg_rating < 3.0 THEN 'high'
            WHEN avg_rating < 3.5 THEN 'medium'
            ELSE 'low'
        END;
        
        -- Insert suggestions for available trainings in this sub-category
        INSERT IGNORE INTO training_suggestions 
            (user_id, training_id, suggestion_reason, evaluation_category_id, evaluation_score, priority_level, suggested_by)
        SELECT 
            teacher_id,
            ts.id,
            CONCAT('Based on your evaluation score of ', ROUND(avg_rating, 2), ' in ', sub_cat_name, ' (', main_cat_name, '), which is below the recommended threshold of 4.0. This training will help improve your performance in this area.'),
            sub_cat_id,
            avg_rating,
            priority,
            1 -- Guidance officer ID
        FROM trainings_seminars ts
        WHERE ts.sub_category_id = sub_cat_id 
            AND ts.status = 'published'
            AND ts.start_date > NOW()
            AND NOT EXISTS (
                SELECT 1 FROM training_suggestions ts2 
                WHERE ts2.user_id = teacher_id 
                    AND ts2.training_id = ts.id 
                    AND ts2.status IN ('pending', 'accepted')
            );
        
    END LOOP;
    
    CLOSE score_cursor;
END //
DELIMITER ;

-- Procedure to get teacher training recommendations (updated for 4.0 threshold)
DELIMITER //
CREATE PROCEDURE GetTeacherTrainingRecommendations(IN teacher_id INT)
BEGIN
    SELECT 
        ts.id as training_id,
        ts.title,
        ts.description,
        ts.type,
        ts.start_date,
        ts.end_date,
        ts.venue,
        ts.duration_hours,
        ts.cost,
        esc.name as related_category,
        tsg.priority_level,
        tsg.suggestion_reason,
        tsg.evaluation_score,
        CASE 
            WHEN tr.id IS NOT NULL THEN 'registered'
            WHEN tsg.id IS NOT NULL THEN tsg.status
            ELSE 'available'
        END as status
    FROM trainings_seminars ts
    JOIN evaluation_sub_categories esc ON ts.sub_category_id = esc.id
    LEFT JOIN training_suggestions tsg ON ts.id = tsg.training_id AND tsg.user_id = teacher_id
    LEFT JOIN training_registrations tr ON ts.id = tr.training_id AND tr.user_id = teacher_id
    WHERE ts.status = 'published'
        AND ts.start_date > NOW()
        AND (tsg.user_id = teacher_id OR tsg.id IS NULL)
        AND EXISTS (
            -- Only show trainings for categories where teacher has scores below 4.0
            SELECT 1 FROM evaluation_sessions es
            JOIN evaluation_questionnaires eq ON esc.id = eq.sub_category_id
            JOIN evaluation_responses er ON eq.id = er.questionnaire_id AND es.id = er.evaluation_session_id
            WHERE es.evaluatee_id = teacher_id
                AND es.evaluatee_type = 'teacher'
                AND es.status = 'completed'
                AND er.rating_value IS NOT NULL
            GROUP BY esc.id
            HAVING AVG(er.rating_value) < 4.0 AND COUNT(er.id) >= 3
        )
    ORDER BY 
        CASE tsg.priority_level
            WHEN 'critical' THEN 1
            WHEN 'high' THEN 2
            WHEN 'medium' THEN 3
            WHEN 'low' THEN 4
            ELSE 5
        END,
        ts.start_date;
END //
DELIMITER ; 