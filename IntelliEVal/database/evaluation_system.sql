-- IntelliEVal System - Complete Database Schema
-- Multi-Role Evaluation System for Students, Teachers, and Heads

-- =====================================================
-- USER ROLES AND AUTHENTICATION
-- =====================================================

-- Update users table to include new roles
ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'social_media_manager', 'content_creator', 'guidance_officer', 'teacher', 'head', 'student') NOT NULL DEFAULT 'student';

-- =====================================================
-- SUBJECTS AND COURSES
-- =====================================================

-- Subjects table
CREATE TABLE IF NOT EXISTS `subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `units` int(11) DEFAULT 3,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `created_by` (`created_by`),
  KEY `status` (`status`),
  CONSTRAINT `fk_subjects_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SEMESTERS AND ACADEMIC PERIODS
-- =====================================================

-- Semesters table
CREATE TABLE IF NOT EXISTS `semesters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('active','inactive','completed') DEFAULT 'active',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `academic_year` (`academic_year`),
  KEY `status` (`status`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `fk_semesters_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TEACHER-SUBJECT ASSIGNMENTS
-- =====================================================

-- Teacher subject assignments
CREATE TABLE IF NOT EXISTS `teacher_subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `semester_id` int(11) NOT NULL,
  `section` varchar(20) DEFAULT NULL,
  `schedule` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_assignment` (`teacher_id`, `subject_id`, `semester_id`, `section`),
  KEY `teacher_id` (`teacher_id`),
  KEY `subject_id` (`subject_id`),
  KEY `semester_id` (`semester_id`),
  KEY `status` (`status`),
  CONSTRAINT `fk_teacher_subjects_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_teacher_subjects_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_teacher_subjects_semester` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- STUDENT ENROLLMENTS
-- =====================================================

-- Student enrollments (linking students to teacher-subject assignments)
CREATE TABLE IF NOT EXISTS `student_enrollments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `teacher_subject_id` int(11) NOT NULL,
  `enrollment_date` date NOT NULL,
  `status` enum('enrolled','dropped','completed') DEFAULT 'enrolled',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_enrollment` (`student_id`, `teacher_subject_id`),
  KEY `student_id` (`student_id`),
  KEY `teacher_subject_id` (`teacher_subject_id`),
  KEY `status` (`status`),
  CONSTRAINT `fk_student_enrollments_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_student_enrollments_teacher_subject` FOREIGN KEY (`teacher_subject_id`) REFERENCES `teacher_subjects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- EVALUATION SCHEDULES
-- =====================================================

-- Evaluation schedules (set by guidance)
CREATE TABLE IF NOT EXISTS `evaluation_schedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `semester_id` int(11) NOT NULL,
  `evaluation_type` enum('student_to_teacher','teacher_to_teacher','head_to_teacher') NOT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `status` enum('scheduled','active','completed','cancelled') DEFAULT 'scheduled',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `semester_id` (`semester_id`),
  KEY `evaluation_type` (`evaluation_type`),
  KEY `status` (`status`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `fk_evaluation_schedules_semester` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_evaluation_schedules_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- EVALUATION CATEGORIES (ROLE-SPECIFIC)
-- =====================================================

-- Update evaluation_categories to include role-specific categories
ALTER TABLE `evaluation_categories` 
ADD COLUMN `evaluation_type` enum('student_to_teacher','teacher_to_teacher','head_to_teacher') NOT NULL DEFAULT 'student_to_teacher' AFTER `description`,
ADD COLUMN `semester_id` int(11) DEFAULT NULL AFTER `evaluation_type`,
ADD KEY `evaluation_type` (`evaluation_type`),
ADD KEY `semester_id` (`semester_id`),
ADD CONSTRAINT `fk_evaluation_categories_semester` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE SET NULL;

-- =====================================================
-- EVALUATION SESSIONS
-- =====================================================

-- Evaluation sessions (tracks individual evaluations)
CREATE TABLE IF NOT EXISTS `evaluation_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `evaluator_id` int(11) NOT NULL,
  `evaluator_type` enum('student','teacher','head') NOT NULL,
  `evaluatee_id` int(11) NOT NULL,
  `evaluatee_type` enum('teacher') NOT NULL,
  `semester_id` int(11) NOT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `evaluation_type` enum('student_to_teacher','teacher_to_teacher','head_to_teacher') NOT NULL,
  `status` enum('draft','submitted','completed') DEFAULT 'draft',
  `submitted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_evaluation` (`evaluator_id`, `evaluator_type`, `evaluatee_id`, `semester_id`, `subject_id`, `evaluation_type`),
  KEY `evaluator_id` (`evaluator_id`),
  KEY `evaluatee_id` (`evaluatee_id`),
  KEY `semester_id` (`semester_id`),
  KEY `subject_id` (`subject_id`),
  KEY `evaluation_type` (`evaluation_type`),
  KEY `status` (`status`),
  CONSTRAINT `fk_evaluation_sessions_evaluator` FOREIGN KEY (`evaluator_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_evaluation_sessions_evaluatee` FOREIGN KEY (`evaluatee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_evaluation_sessions_semester` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_evaluation_sessions_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- EVALUATION RESPONSES
-- =====================================================

-- Update evaluation_responses to link to sessions
ALTER TABLE `evaluation_responses` 
ADD COLUMN `evaluation_session_id` int(11) DEFAULT NULL AFTER `evaluation_id`,
ADD KEY `evaluation_session_id` (`evaluation_session_id`),
ADD CONSTRAINT `fk_evaluation_responses_session` FOREIGN KEY (`evaluation_session_id`) REFERENCES `evaluation_sessions` (`id`) ON DELETE CASCADE;

-- =====================================================
-- SAMPLE DATA
-- =====================================================

-- Insert sample semesters
INSERT INTO `semesters` (`name`, `academic_year`, `start_date`, `end_date`, `created_by`) VALUES
('First Semester', '2024-2025', '2024-08-01', '2024-12-15', 1),
('Second Semester', '2024-2025', '2025-01-15', '2025-05-30', 1),
('Summer', '2024-2025', '2025-06-01', '2025-07-31', 1);

-- Insert sample subjects
INSERT INTO `subjects` (`code`, `name`, `description`, `units`, `created_by`) VALUES
('MATH101', 'College Algebra', 'Fundamental concepts of algebra', 3, 1),
('ENG101', 'English Composition', 'Basic writing and communication skills', 3, 1),
('SCI101', 'General Science', 'Introduction to scientific principles', 3, 1),
('HIST101', 'Philippine History', 'History of the Philippines', 3, 1),
('COMP101', 'Computer Fundamentals', 'Basic computer concepts and applications', 3, 1);

-- Insert sample evaluation categories for different roles
INSERT INTO `evaluation_categories` (`name`, `description`, `evaluation_type`, `created_by`) VALUES
-- Student to Teacher categories
('Teaching Effectiveness', 'Evaluation of teacher\'s teaching methods and delivery', 'student_to_teacher', 1),
('Subject Knowledge', 'Assessment of teacher\'s mastery of the subject matter', 'student_to_teacher', 1),
('Classroom Management', 'Evaluation of classroom discipline and organization', 'student_to_teacher', 1),
('Communication Skills', 'Assessment of teacher\'s communication with students', 'student_to_teacher', 1),

-- Teacher to Teacher categories
('Professional Competence', 'Evaluation of colleague\'s professional skills', 'teacher_to_teacher', 1),
('Collaboration', 'Assessment of teamwork and cooperation', 'teacher_to_teacher', 1),
('Innovation', 'Evaluation of teaching innovations and creativity', 'teacher_to_teacher', 1),

-- Head to Teacher categories
('Leadership', 'Assessment of leadership qualities and initiative', 'head_to_teacher', 1),
('Administrative Skills', 'Evaluation of administrative and organizational skills', 'head_to_teacher', 1),
('Professional Development', 'Assessment of continuous learning and growth', 'head_to_teacher', 1);

-- Insert sample evaluation schedules
INSERT INTO `evaluation_schedules` (`semester_id`, `evaluation_type`, `start_date`, `end_date`, `created_by`) VALUES
(1, 'student_to_teacher', '2024-11-15 08:00:00', '2024-11-30 17:00:00', 1),
(1, 'teacher_to_teacher', '2024-12-01 08:00:00', '2024-12-10 17:00:00', 1),
(1, 'head_to_teacher', '2024-12-05 08:00:00', '2024-12-15 17:00:00', 1);

-- =====================================================
-- INDEXES FOR PERFORMANCE
-- =====================================================

-- Add performance indexes
CREATE INDEX `idx_evaluation_sessions_semester_type` ON `evaluation_sessions` (`semester_id`, `evaluation_type`);
CREATE INDEX `idx_evaluation_sessions_evaluator` ON `evaluation_sessions` (`evaluator_id`, `evaluator_type`);
CREATE INDEX `idx_evaluation_sessions_evaluatee` ON `evaluation_sessions` (`evaluatee_id`, `evaluatee_type`);
CREATE INDEX `idx_teacher_subjects_semester` ON `teacher_subjects` (`semester_id`, `status`);
CREATE INDEX `idx_student_enrollments_status` ON `student_enrollments` (`status`);
CREATE INDEX `idx_evaluation_schedules_active` ON `evaluation_schedules` (`status`, `start_date`, `end_date`);

-- =====================================================
-- VIEWS FOR REPORTING
-- =====================================================

-- Evaluation summary view
CREATE OR REPLACE VIEW `evaluation_summary_view` AS
SELECT 
    es.id as session_id,
    es.evaluation_type,
    es.status as evaluation_status,
    es.submitted_at,
    s.name as semester_name,
    s.academic_year,
    sub.name as subject_name,
    sub.code as subject_code,
    evaluator.first_name as evaluator_first_name,
    evaluator.last_name as evaluator_last_name,
    evaluator.role as evaluator_role,
    evaluatee.first_name as evaluatee_first_name,
    evaluatee.last_name as evaluatee_last_name,
    evaluatee.role as evaluatee_role,
    COUNT(er.id) as total_responses
FROM evaluation_sessions es
LEFT JOIN semesters s ON es.semester_id = s.id
LEFT JOIN subjects sub ON es.subject_id = sub.id
LEFT JOIN users evaluator ON es.evaluator_id = evaluator.id
LEFT JOIN users evaluatee ON es.evaluatee_id = evaluatee.id
LEFT JOIN evaluation_responses er ON es.id = er.evaluation_session_id
GROUP BY es.id, es.evaluation_type, es.status, es.submitted_at, s.name, s.academic_year, sub.name, sub.code, 
         evaluator.first_name, evaluator.last_name, evaluator.role, evaluatee.first_name, evaluatee.last_name, evaluatee.role; 