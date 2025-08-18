-- IntelliEVal System - Database Cleanup Script
-- This script identifies and fixes redundant data across all tables
-- Run this script to clean up your database

-- =====================================================
-- DATABASE CLEANUP SCRIPT
-- =====================================================

-- Start transaction for safety
START TRANSACTION;

-- =====================================================
-- 1. CLEANUP DUPLICATE EVALUATION CATEGORIES
-- =====================================================

-- Check for duplicate evaluation categories in old system
SELECT 'Checking for duplicate evaluation categories...' as status;

-- Find duplicates in evaluation_categories
CREATE TEMPORARY TABLE IF NOT EXISTS temp_duplicate_categories AS
SELECT name, COUNT(*) as count
FROM evaluation_categories 
WHERE status = 'active'
GROUP BY name 
HAVING COUNT(*) > 1;

-- Show duplicates found
SELECT 'Duplicate categories found:' as message;
SELECT * FROM temp_duplicate_categories;

-- Keep only the first occurrence of each duplicate category
DELETE ec1 FROM evaluation_categories ec1
INNER JOIN evaluation_categories ec2 
WHERE ec1.name = ec2.name 
AND ec1.id > ec2.id 
AND ec1.status = 'active';

-- =====================================================
-- 2. CLEANUP DUPLICATE QUESTIONNAIRES
-- =====================================================

-- Check for duplicate questionnaires
SELECT 'Checking for duplicate questionnaires...' as status;

-- Find exact duplicate questionnaires (same text, category, type)
CREATE TEMPORARY TABLE IF NOT EXISTS temp_duplicate_questionnaires AS
SELECT q1.id as duplicate_id, q1.category_id, q1.question_text, q1.question_type
FROM questionnaires q1
INNER JOIN questionnaires q2 
WHERE q1.id != q2.id 
AND q1.category_id = q2.category_id 
AND q1.question_text = q2.question_text 
AND q1.question_type = q2.question_type
AND q1.status = 'active' 
AND q2.status = 'active'
AND q1.id > q2.id;

-- Show duplicates found
SELECT 'Duplicate questionnaires found:' as message;
SELECT * FROM temp_duplicate_questionnaires;

-- Keep only the first occurrence of each duplicate questionnaire
DELETE q1 FROM questionnaires q1
INNER JOIN questionnaires q2 
WHERE q1.id != q2.id 
AND q1.category_id = q2.category_id 
AND q1.question_text = q2.question_text 
AND q1.question_type = q2.question_type
AND q1.status = 'active' 
AND q2.status = 'active'
AND q1.id > q2.id;

-- =====================================================
-- 3. CLEANUP DUPLICATE EVALUATION SESSIONS
-- =====================================================

-- Check for duplicate evaluation sessions
SELECT 'Checking for duplicate evaluation sessions...' as status;

-- Find duplicate evaluation sessions (same evaluator, evaluatee, category, date)
CREATE TEMPORARY TABLE IF NOT EXISTS temp_duplicate_sessions AS
SELECT es1.id as duplicate_id, es1.evaluator_id, es1.evaluatee_id, es1.main_category_id, es1.evaluation_date
FROM evaluation_sessions es1
INNER JOIN evaluation_sessions es2 
WHERE es1.id != es2.id 
AND es1.evaluator_id = es2.evaluator_id 
AND es1.evaluatee_id = es2.evaluatee_id 
AND es1.main_category_id = es2.main_category_id 
AND DATE(es1.evaluation_date) = DATE(es2.evaluation_date)
AND es1.status = 'draft' 
AND es2.status = 'draft'
AND es1.id > es2.id;

-- Show duplicates found
SELECT 'Duplicate evaluation sessions found:' as message;
SELECT * FROM temp_duplicate_sessions;

-- Keep only the first occurrence of each duplicate session
DELETE es1 FROM evaluation_sessions es1
INNER JOIN evaluation_sessions es2 
WHERE es1.id != es2.id 
AND es1.evaluator_id = es2.evaluator_id 
AND es1.evaluatee_id = es2.evaluatee_id 
AND es1.main_category_id = es2.main_category_id 
AND DATE(es1.evaluation_date) = DATE(es2.evaluation_date)
AND es1.status = 'draft' 
AND es2.status = 'draft'
AND es1.id > es2.id;

-- =====================================================
-- 4. CLEANUP ORPHANED EVALUATION RESPONSES
-- =====================================================

-- Check for orphaned evaluation responses
SELECT 'Checking for orphaned evaluation responses...' as status;

-- Find responses without valid evaluation sessions
CREATE TEMPORARY TABLE IF NOT EXISTS temp_orphaned_responses AS
SELECT er.id as orphaned_id, er.evaluation_session_id
FROM evaluation_responses er
LEFT JOIN evaluation_sessions es ON er.evaluation_session_id = es.id
WHERE es.id IS NULL;

-- Show orphaned responses found
SELECT 'Orphaned evaluation responses found:' as message;
SELECT COUNT(*) as count FROM temp_orphaned_responses;

-- Delete orphaned responses
DELETE er FROM evaluation_responses er
LEFT JOIN evaluation_sessions es ON er.evaluation_session_id = es.id
WHERE es.id IS NULL;

-- Find responses without valid questionnaires
CREATE TEMPORARY TABLE IF NOT EXISTS temp_orphaned_questionnaire_responses AS
SELECT er.id as orphaned_id, er.questionnaire_id
FROM evaluation_responses er
LEFT JOIN evaluation_questionnaires eq ON er.questionnaire_id = eq.id
WHERE eq.id IS NULL;

-- Show orphaned questionnaire responses found
SELECT 'Orphaned questionnaire responses found:' as message;
SELECT COUNT(*) as count FROM temp_orphaned_questionnaire_responses;

-- Delete orphaned questionnaire responses
DELETE er FROM evaluation_responses er
LEFT JOIN evaluation_questionnaires eq ON er.questionnaire_id = eq.id
WHERE eq.id IS NULL;

-- =====================================================
-- 5. CLEANUP ORPHANED QUESTIONNAIRES
-- =====================================================

-- Check for orphaned questionnaires in old system
SELECT 'Checking for orphaned questionnaires...' as status;

-- Find questionnaires without valid categories
CREATE TEMPORARY TABLE IF NOT EXISTS temp_orphaned_questionnaires AS
SELECT q.id as orphaned_id, q.category_id
FROM questionnaires q
LEFT JOIN evaluation_categories ec ON q.category_id = ec.id
WHERE ec.id IS NULL;

-- Show orphaned questionnaires found
SELECT 'Orphaned questionnaires found:' as message;
SELECT COUNT(*) as count FROM temp_orphaned_questionnaires;

-- Delete orphaned questionnaires
DELETE q FROM questionnaires q
LEFT JOIN evaluation_categories ec ON q.category_id = ec.id
WHERE ec.id IS NULL;

-- =====================================================
-- 6. CLEANUP ORPHANED SUB-CATEGORIES
-- =====================================================

-- Check for orphaned sub-categories
SELECT 'Checking for orphaned sub-categories...' as status;

-- Find sub-categories without valid main categories
CREATE TEMPORARY TABLE IF NOT EXISTS temp_orphaned_sub_categories AS
SELECT esc.id as orphaned_id, esc.main_category_id
FROM evaluation_sub_categories esc
LEFT JOIN main_evaluation_categories mec ON esc.main_category_id = mec.id
WHERE mec.id IS NULL;

-- Show orphaned sub-categories found
SELECT 'Orphaned sub-categories found:' as message;
SELECT COUNT(*) as count FROM temp_orphaned_sub_categories;

-- Delete orphaned sub-categories
DELETE esc FROM evaluation_sub_categories esc
LEFT JOIN main_evaluation_categories mec ON esc.main_category_id = mec.id
WHERE mec.id IS NULL;

-- =====================================================
-- 7. CLEANUP ORPHANED QUESTIONNAIRES IN NEW SYSTEM
-- =====================================================

-- Check for orphaned questionnaires in new system
SELECT 'Checking for orphaned questionnaires in new system...' as status;

-- Find questionnaires without valid sub-categories
CREATE TEMPORARY TABLE IF NOT EXISTS temp_orphaned_new_questionnaires AS
SELECT eq.id as orphaned_id, eq.sub_category_id
FROM evaluation_questionnaires eq
LEFT JOIN evaluation_sub_categories esc ON eq.sub_category_id = esc.id
WHERE esc.id IS NULL;

-- Show orphaned new questionnaires found
SELECT 'Orphaned new questionnaires found:' as message;
SELECT COUNT(*) as count FROM temp_orphaned_new_questionnaires;

-- Delete orphaned new questionnaires
DELETE eq FROM evaluation_questionnaires eq
LEFT JOIN evaluation_sub_categories esc ON eq.sub_category_id = esc.id
WHERE esc.id IS NULL;

-- =====================================================
-- 8. CLEANUP DUPLICATE USERS
-- =====================================================

-- Check for duplicate users (same email)
SELECT 'Checking for duplicate users...' as status;

-- Find duplicate users by email
CREATE TEMPORARY TABLE IF NOT EXISTS temp_duplicate_users AS
SELECT email, COUNT(*) as count
FROM users 
WHERE status = 'active'
GROUP BY email 
HAVING COUNT(*) > 1;

-- Show duplicates found
SELECT 'Duplicate users found:' as message;
SELECT * FROM temp_duplicate_users;

-- Keep only the first occurrence of each duplicate user
DELETE u1 FROM users u1
INNER JOIN users u2 
WHERE u1.email = u2.email 
AND u1.status = 'active' 
AND u2.status = 'active'
AND u1.id > u2.id;

-- =====================================================
-- 9. CLEANUP DUPLICATE STUDENTS
-- =====================================================

-- Check for duplicate students
SELECT 'Checking for duplicate students...' as status;

-- Find duplicate students by student_id
CREATE TEMPORARY TABLE IF NOT EXISTS temp_duplicate_students AS
SELECT student_id, COUNT(*) as count
FROM students 
WHERE status = 'active'
GROUP BY student_id 
HAVING COUNT(*) > 1;

-- Show duplicates found
SELECT 'Duplicate students found:' as message;
SELECT * FROM temp_duplicate_students;

-- Keep only the first occurrence of each duplicate student
DELETE s1 FROM students s1
INNER JOIN students s2 
WHERE s1.student_id = s2.student_id 
AND s1.status = 'active' 
AND s2.status = 'active'
AND s1.id > s2.id;

-- =====================================================
-- 10. CLEANUP DUPLICATE TEACHERS
-- =====================================================

-- Check for duplicate teachers
SELECT 'Checking for duplicate teachers...' as status;

-- Find duplicate teachers by user_id
CREATE TEMPORARY TABLE IF NOT EXISTS temp_duplicate_teachers AS
SELECT user_id, COUNT(*) as count
FROM teachers 
WHERE status = 'active'
GROUP BY user_id 
HAVING COUNT(*) > 1;

-- Show duplicates found
SELECT 'Duplicate teachers found:' as message;
SELECT * FROM temp_duplicate_teachers;

-- Keep only the first occurrence of each duplicate teacher
DELETE t1 FROM teachers t1
INNER JOIN teachers t2 
WHERE t1.user_id = t2.user_id 
AND t1.status = 'active' 
AND t2.status = 'active'
AND t1.id > t2.id;

-- =====================================================
-- 11. CLEANUP DUPLICATE HEADS
-- =====================================================

-- Check for duplicate heads
SELECT 'Checking for duplicate heads...' as status;

-- Find duplicate heads by user_id
CREATE TEMPORARY TABLE IF NOT EXISTS temp_duplicate_heads AS
SELECT user_id, COUNT(*) as count
FROM heads 
WHERE status = 'active'
GROUP BY user_id 
HAVING COUNT(*) > 1;

-- Show duplicates found
SELECT 'Duplicate heads found:' as message;
SELECT * FROM temp_duplicate_heads;

-- Keep only the first occurrence of each duplicate head
DELETE h1 FROM heads h1
INNER JOIN heads h2 
WHERE h1.user_id = h2.user_id 
AND h1.status = 'active' 
AND h2.status = 'active'
AND h1.id > h2.id;

-- =====================================================
-- 12. CLEANUP DUPLICATE SEMESTERS
-- =====================================================

-- Check for duplicate semesters
SELECT 'Checking for duplicate semesters...' as status;

-- Find duplicate semesters by name and academic_year
CREATE TEMPORARY TABLE IF NOT EXISTS temp_duplicate_semesters AS
SELECT name, academic_year, COUNT(*) as count
FROM semesters 
WHERE status = 'active'
GROUP BY name, academic_year 
HAVING COUNT(*) > 1;

-- Show duplicates found
SELECT 'Duplicate semesters found:' as message;
SELECT * FROM temp_duplicate_semesters;

-- Keep only the first occurrence of each duplicate semester
DELETE s1 FROM semesters s1
INNER JOIN semesters s2 
WHERE s1.name = s2.name 
AND s1.academic_year = s2.academic_year
AND s1.status = 'active' 
AND s2.status = 'active'
AND s1.id > s2.id;

-- =====================================================
-- 13. CLEANUP DUPLICATE SUBJECTS
-- =====================================================

-- Check for duplicate subjects
SELECT 'Checking for duplicate subjects...' as status;

-- Find duplicate subjects by code
CREATE TEMPORARY TABLE IF NOT EXISTS temp_duplicate_subjects AS
SELECT code, COUNT(*) as count
FROM subjects 
WHERE status = 'active'
GROUP BY code 
HAVING COUNT(*) > 1;

-- Show duplicates found
SELECT 'Duplicate subjects found:' as message;
SELECT * FROM temp_duplicate_subjects;

-- Keep only the first occurrence of each duplicate subject
DELETE sub1 FROM subjects sub1
INNER JOIN subjects sub2 
WHERE sub1.code = sub2.code 
AND sub1.status = 'active' 
AND sub2.status = 'active'
AND sub1.id > sub2.id;

-- =====================================================
-- 14. CLEANUP OLD EVALUATION SYSTEM DATA
-- =====================================================

-- Check if we should migrate old evaluation data to new system
SELECT 'Checking for old evaluation system data...' as status;

-- Count old evaluation categories
SELECT 'Old evaluation categories count:' as message;
SELECT COUNT(*) as count FROM evaluation_categories WHERE status = 'active';

-- Count old questionnaires
SELECT 'Old questionnaires count:' as message;
SELECT COUNT(*) as count FROM questionnaires WHERE status = 'active';

-- Count old student evaluations
SELECT 'Old student evaluations count:' as message;
SELECT COUNT(*) as count FROM student_evaluations WHERE status != 'deleted';

-- =====================================================
-- 15. FINAL CLEANUP - REMOVE TEMPORARY TABLES
-- =====================================================

-- Clean up temporary tables
DROP TEMPORARY TABLE IF EXISTS temp_duplicate_categories;
DROP TEMPORARY TABLE IF EXISTS temp_duplicate_questionnaires;
DROP TEMPORARY TABLE IF EXISTS temp_duplicate_sessions;
DROP TEMPORARY TABLE IF EXISTS temp_orphaned_responses;
DROP TEMPORARY TABLE IF EXISTS temp_orphaned_questionnaire_responses;
DROP TEMPORARY TABLE IF EXISTS temp_orphaned_questionnaires;
DROP TEMPORARY TABLE IF EXISTS temp_orphaned_sub_categories;
DROP TEMPORARY TABLE IF EXISTS temp_orphaned_new_questionnaires;
DROP TEMPORARY TABLE IF EXISTS temp_duplicate_users;
DROP TEMPORARY TABLE IF EXISTS temp_duplicate_students;
DROP TEMPORARY TABLE IF EXISTS temp_duplicate_teachers;
DROP TEMPORARY TABLE IF EXISTS temp_duplicate_heads;
DROP TEMPORARY TABLE IF EXISTS temp_duplicate_semesters;
DROP TEMPORARY TABLE IF EXISTS temp_duplicate_subjects;

-- =====================================================
-- 16. OPTIMIZE TABLES
-- =====================================================

-- Optimize all tables for better performance
SELECT 'Optimizing tables...' as status;

OPTIMIZE TABLE users;
OPTIMIZE TABLE students;
OPTIMIZE TABLE teachers;
OPTIMIZE TABLE heads;
OPTIMIZE TABLE semesters;
OPTIMIZE TABLE subjects;
OPTIMIZE TABLE evaluation_categories;
OPTIMIZE TABLE questionnaires;
OPTIMIZE TABLE student_evaluations;
OPTIMIZE TABLE evaluation_responses;
OPTIMIZE TABLE main_evaluation_categories;
OPTIMIZE TABLE evaluation_sub_categories;
OPTIMIZE TABLE evaluation_questionnaires;
OPTIMIZE TABLE evaluation_sessions;

-- =====================================================
-- 17. FINAL SUMMARY
-- =====================================================

-- Show final database statistics
SELECT '=== DATABASE CLEANUP COMPLETE ===' as message;

-- Count active records in each table
SELECT 'Final database statistics:' as message;

SELECT 'users' as table_name, COUNT(*) as count FROM users WHERE status = 'active'
UNION ALL
SELECT 'students' as table_name, COUNT(*) as count FROM students WHERE status = 'active'
UNION ALL
SELECT 'teachers' as table_name, COUNT(*) as count FROM teachers WHERE status = 'active'
UNION ALL
SELECT 'heads' as table_name, COUNT(*) as count FROM heads WHERE status = 'active'
UNION ALL
SELECT 'semesters' as table_name, COUNT(*) as count FROM semesters WHERE status = 'active'
UNION ALL
SELECT 'subjects' as table_name, COUNT(*) as count FROM subjects WHERE status = 'active'
UNION ALL
SELECT 'main_evaluation_categories' as table_name, COUNT(*) as count FROM main_evaluation_categories WHERE status = 'active'
UNION ALL
SELECT 'evaluation_sub_categories' as table_name, COUNT(*) as count FROM evaluation_sub_categories WHERE status = 'active'
UNION ALL
SELECT 'evaluation_questionnaires' as table_name, COUNT(*) as count FROM evaluation_questionnaires WHERE status = 'active'
UNION ALL
SELECT 'evaluation_sessions' as table_name, COUNT(*) as count FROM evaluation_sessions
UNION ALL
SELECT 'evaluation_responses' as table_name, COUNT(*) as count FROM evaluation_responses;

-- Commit all changes
COMMIT;

SELECT 'Database cleanup completed successfully!' as final_message; 