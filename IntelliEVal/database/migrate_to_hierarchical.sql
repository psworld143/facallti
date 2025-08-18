-- IntelliEVal System - Migration Script
-- Migrate from old evaluation structure to new hierarchical structure
-- Run this script after creating the new tables from evaluation_hierarchical_schema.sql

-- =====================================================
-- MIGRATION SCRIPT
-- =====================================================

-- Step 1: Create backup of existing data
CREATE TABLE IF NOT EXISTS `backup_evaluation_categories` AS SELECT * FROM `evaluation_categories`;
CREATE TABLE IF NOT EXISTS `backup_questionnaires` AS SELECT * FROM `questionnaires`;
CREATE TABLE IF NOT EXISTS `backup_student_evaluations` AS SELECT * FROM `student_evaluations`;
CREATE TABLE IF NOT EXISTS `backup_evaluation_responses` AS SELECT * FROM `evaluation_responses`;

-- Step 2: Insert main evaluation categories (if not already inserted)
INSERT IGNORE INTO `main_evaluation_categories` (`name`, `description`, `evaluation_type`, `created_by`) VALUES
('Student to Teacher Evaluation', 'Students evaluate their teachers on various aspects of teaching and classroom management', 'student_to_teacher', 1),
('Peer to Peer Evaluation', 'Teachers evaluate their colleagues on professional competence and collaboration', 'peer_to_peer', 1),
('Head to Teacher Evaluation', 'Department heads and administrators evaluate teachers on leadership and administrative skills', 'head_to_teacher', 1);

-- Step 3: Migrate existing evaluation categories to sub-categories
-- This will create sub-categories under "Student to Teacher Evaluation" by default
INSERT INTO `evaluation_sub_categories` (`main_category_id`, `name`, `description`, `order_number`, `status`, `created_by`, `created_at`)
SELECT 
    1 as main_category_id, -- Default to Student to Teacher Evaluation
    ec.name,
    ec.description,
    ec.id as order_number,
    ec.status,
    ec.created_by,
    ec.created_at
FROM `backup_evaluation_categories` ec
WHERE ec.status = 'active';

-- Step 4: Migrate existing questionnaires to new structure
INSERT INTO `evaluation_questionnaires` (`sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`)
SELECT 
    esc.id as sub_category_id,
    q.question_text,
    CASE 
        WHEN q.question_type = 'rating' THEN 'rating_1_5'
        ELSE q.question_type
    END as question_type,
    CASE 
        WHEN q.question_type = 'rating' THEN '["1 - Poor", "2 - Good", "3 - Satisfactory", "4 - Very Satisfactory", "5 - Excellent"]'
        ELSE NULL
    END as rating_labels,
    q.options,
    q.required,
    q.order_number,
    q.status,
    q.created_by,
    q.created_at
FROM `backup_questionnaires` q
JOIN `backup_evaluation_categories` ec ON q.category_id = ec.id
JOIN `evaluation_sub_categories` esc ON ec.name = esc.name AND esc.main_category_id = 1
WHERE q.status = 'active';

-- Step 5: Migrate existing evaluation sessions
INSERT INTO `evaluation_sessions` (`evaluator_id`, `evaluator_type`, `evaluatee_id`, `evaluatee_type`, `main_category_id`, `evaluation_date`, `status`, `notes`, `created_at`)
SELECT 
    se.evaluated_by as evaluator_id,
    'guidance_officer' as evaluator_type, -- Default evaluator type
    se.student_id as evaluatee_id,
    'student' as evaluatee_type, -- Default evaluatee type
    1 as main_category_id, -- Default to Student to Teacher Evaluation
    se.evaluation_date,
    se.status,
    se.notes,
    se.created_at
FROM `backup_student_evaluations` se;

-- Step 6: Migrate existing evaluation responses
INSERT INTO `evaluation_responses` (`evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`)
SELECT 
    es.id as evaluation_session_id,
    eq.id as questionnaire_id,
    CASE 
        WHEN eq.question_type = 'rating_1_5' THEN CAST(er.response AS UNSIGNED)
        ELSE NULL
    END as rating_value,
    CASE 
        WHEN eq.question_type = 'text' THEN er.response
        ELSE NULL
    END as text_response,
    CASE 
        WHEN eq.question_type = 'multiple_choice' THEN er.response
        ELSE NULL
    END as multiple_choice_response,
    CASE 
        WHEN eq.question_type = 'yes_no' THEN er.response
        ELSE NULL
    END as yes_no_response,
    er.created_at
FROM `backup_evaluation_responses` er
JOIN `backup_questionnaires` bq ON er.questionnaire_id = bq.id
JOIN `evaluation_questionnaires` eq ON bq.question_text = eq.question_text
JOIN `backup_student_evaluations` bse ON er.evaluation_id = bse.id
JOIN `evaluation_sessions` es ON bse.student_id = es.evaluatee_id AND bse.evaluation_date = es.evaluation_date;

-- Step 7: Update any existing rating responses to use the new 1-5 scale
-- This assumes old ratings were on a different scale and need to be converted
UPDATE `evaluation_responses` 
SET rating_value = CASE 
    WHEN rating_value = 1 THEN 1  -- Poor
    WHEN rating_value = 2 THEN 2  -- Good
    WHEN rating_value = 3 THEN 3  -- Satisfactory
    WHEN rating_value = 4 THEN 4  -- Very Satisfactory
    WHEN rating_value = 5 THEN 5  -- Excellent
    ELSE rating_value
END
WHERE rating_value IS NOT NULL;

-- Step 8: Create indexes for better performance (if not already created)
CREATE INDEX IF NOT EXISTS `idx_evaluation_sessions_evaluator_type` ON `evaluation_sessions` (`evaluator_id`, `evaluator_type`);
CREATE INDEX IF NOT EXISTS `idx_evaluation_sessions_evaluatee_type` ON `evaluation_sessions` (`evaluatee_id`, `evaluatee_type`);
CREATE INDEX IF NOT EXISTS `idx_evaluation_sessions_main_category_status` ON `evaluation_sessions` (`main_category_id`, `status`);
CREATE INDEX IF NOT EXISTS `idx_evaluation_responses_session_questionnaire` ON `evaluation_responses` (`evaluation_session_id`, `questionnaire_id`);
CREATE INDEX IF NOT EXISTS `idx_evaluation_responses_rating` ON `evaluation_responses` (`rating_value`);

-- Step 9: Verify migration
SELECT 'Migration Summary' as info;
SELECT COUNT(*) as 'Main Categories Created' FROM main_evaluation_categories;
SELECT COUNT(*) as 'Sub-Categories Migrated' FROM evaluation_sub_categories;
SELECT COUNT(*) as 'Questionnaires Migrated' FROM evaluation_questionnaires;
SELECT COUNT(*) as 'Evaluation Sessions Migrated' FROM evaluation_sessions;
SELECT COUNT(*) as 'Evaluation Responses Migrated' FROM evaluation_responses;

-- Step 10: Show sample data for verification
SELECT 'Sample Main Categories:' as info;
SELECT name, evaluation_type FROM main_evaluation_categories;

SELECT 'Sample Sub-Categories:' as info;
SELECT esc.name, mec.name as main_category FROM evaluation_sub_categories esc 
JOIN main_evaluation_categories mec ON esc.main_category_id = mec.id 
LIMIT 5;

SELECT 'Sample Questionnaires:' as info;
SELECT eq.question_text, eq.question_type, esc.name as sub_category 
FROM evaluation_questionnaires eq 
JOIN evaluation_sub_categories esc ON eq.sub_category_id = esc.id 
LIMIT 5;

-- =====================================================
-- CLEANUP (Optional - Run after verifying migration)
-- =====================================================

-- Uncomment the following lines after verifying the migration was successful
-- DROP TABLE IF EXISTS backup_evaluation_categories;
-- DROP TABLE IF EXISTS backup_questionnaires;
-- DROP TABLE IF EXISTS backup_student_evaluations;
-- DROP TABLE IF EXISTS backup_evaluation_responses;

-- =====================================================
-- ROLLBACK SCRIPT (If needed)
-- =====================================================

-- If you need to rollback, uncomment and run these commands:
/*
-- Restore from backup
DROP TABLE IF EXISTS evaluation_responses;
DROP TABLE IF EXISTS evaluation_sessions;
DROP TABLE IF EXISTS evaluation_questionnaires;
DROP TABLE IF EXISTS evaluation_sub_categories;
DROP TABLE IF EXISTS main_evaluation_categories;

-- Rename backup tables back to original names
RENAME TABLE backup_evaluation_responses TO evaluation_responses;
RENAME TABLE backup_student_evaluations TO student_evaluations;
RENAME TABLE backup_questionnaires TO questionnaires;
RENAME TABLE backup_evaluation_categories TO evaluation_categories;
*/ 