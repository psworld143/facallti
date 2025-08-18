-- Additional tables for Faculty Module features
-- Run this file to create the necessary tables for announcements, events, and other features

-- =====================================================
-- CLASS ANNOUNCEMENTS
-- =====================================================

CREATE TABLE IF NOT EXISTS `class_announcements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `is_pinned` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `class_id` (`class_id`),
  KEY `teacher_id` (`teacher_id`),
  KEY `priority` (`priority`),
  KEY `is_pinned` (`is_pinned`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `fk_announcements_class` FOREIGN KEY (`class_id`) REFERENCES `teacher_classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_announcements_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- FACULTY EVENTS
-- =====================================================

CREATE TABLE IF NOT EXISTS `faculty_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `event_date` date NOT NULL,
  `event_type` enum('class','exam','assignment','meeting','other') DEFAULT 'other',
  `class_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `teacher_id` (`teacher_id`),
  KEY `event_date` (`event_date`),
  KEY `event_type` (`event_type`),
  KEY `class_id` (`class_id`),
  CONSTRAINT `fk_events_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_events_class` FOREIGN KEY (`class_id`) REFERENCES `teacher_classes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- FACULTY NOTIFICATIONS
-- =====================================================

CREATE TABLE IF NOT EXISTS `faculty_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','error','announcement','evaluation','class') DEFAULT 'info',
  `related_id` int(11) DEFAULT NULL,
  `related_type` varchar(50) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `teacher_id` (`teacher_id`),
  KEY `type` (`type`),
  KEY `is_read` (`is_read`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `fk_notifications_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SAMPLE DATA
-- =====================================================

-- Insert sample announcements (optional - remove if not needed)
INSERT INTO `class_announcements` (`class_id`, `teacher_id`, `title`, `content`, `priority`, `created_at`) VALUES
(1, 1, 'Welcome to the Course', 'Welcome everyone! This is our first announcement for the semester. Please make sure to review the syllabus and course requirements.', 'medium', NOW()),
(1, 1, 'Assignment Due Date Extended', 'Due to technical difficulties, the assignment due date has been extended to next Friday. Please take advantage of this extra time.', 'high', NOW()),
(1, 1, 'Exam Schedule Update', 'The midterm exam has been rescheduled to next Monday. Please check your email for the detailed schedule.', 'urgent', NOW());

-- Insert sample events (optional - remove if not needed)
INSERT INTO `faculty_events` (`teacher_id`, `title`, `description`, `event_date`, `event_type`, `class_id`, `created_at`) VALUES
(1, 'First Class Meeting', 'Introduction to the course and syllabus review', CURDATE(), 'class', 1, NOW()),
(1, 'Midterm Exam', 'Comprehensive exam covering chapters 1-5', DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'exam', 1, NOW()),
(1, 'Assignment Submission', 'Final project submission deadline', DATE_ADD(CURDATE(), INTERVAL 14 DAY), 'assignment', 1, NOW()),
(1, 'Department Meeting', 'Monthly faculty meeting to discuss curriculum updates', DATE_ADD(CURDATE(), INTERVAL 3 DAY), 'meeting', NULL, NOW());

-- Insert sample notifications (optional - remove if not needed)
INSERT INTO `faculty_notifications` (`teacher_id`, `title`, `message`, `type`, `related_id`, `related_type`, `created_at`) VALUES
(1, 'New Student Enrollment', 'A new student has enrolled in your class "Introduction to Programming".', 'info', 1, 'class', NOW()),
(1, 'Evaluation Completed', 'Your peer evaluation for Dr. Smith has been completed successfully.', 'success', 1, 'evaluation', NOW()),
(1, 'Class Reminder', 'You have a class scheduled in 30 minutes.', 'warning', 1, 'class', NOW());

-- =====================================================
-- INDEXES FOR PERFORMANCE
-- =====================================================

-- Add additional indexes for better performance
CREATE INDEX `idx_announcements_teacher_date` ON `class_announcements` (`teacher_id`, `created_at`);
CREATE INDEX `idx_events_teacher_date` ON `faculty_events` (`teacher_id`, `event_date`);
CREATE INDEX `idx_notifications_teacher_read` ON `faculty_notifications` (`teacher_id`, `is_read`, `created_at`);

-- =====================================================
-- VIEWS FOR COMMON QUERIES
-- =====================================================

-- View for teacher dashboard statistics
CREATE OR REPLACE VIEW `teacher_dashboard_stats` AS
SELECT 
    t.id as teacher_id,
    COUNT(DISTINCT tc.id) as total_classes,
    COUNT(DISTINCT CASE WHEN tc.status = 'active' THEN tc.id END) as active_classes,
    COUNT(DISTINCT ce.id) as total_enrollments,
    COUNT(DISTINCT CASE WHEN ce.status = 'active' THEN ce.id END) as active_enrollments,
    COUNT(DISTINCT es.id) as total_evaluations,
    COUNT(DISTINCT CASE WHEN es.status = 'completed' THEN es.id END) as completed_evaluations
FROM users t
LEFT JOIN teacher_classes tc ON t.id = tc.teacher_id
LEFT JOIN class_enrollments ce ON tc.id = ce.class_id
LEFT JOIN evaluation_sessions es ON t.id = es.evaluator_id
WHERE t.role = 'teacher'
GROUP BY t.id;

-- View for recent activities
CREATE OR REPLACE VIEW `teacher_recent_activities` AS
SELECT 
    'class' as activity_type,
    tc.id as activity_id,
    tc.teacher_id,
    CONCAT('Created class: ', cc.subject_title, ' - ', tc.section) COLLATE utf8mb4_unicode_ci as description,
    tc.created_at as activity_date
FROM teacher_classes tc
JOIN course_curriculum cc ON tc.subject_id = cc.id
UNION ALL
SELECT 
    'announcement' as activity_type,
    ca.id as activity_id,
    ca.teacher_id,
    CONCAT('Posted announcement: ', ca.title) COLLATE utf8mb4_unicode_ci as description,
    ca.created_at as activity_date
FROM class_announcements ca
UNION ALL
SELECT 
    'evaluation' as activity_type,
    es.id as activity_id,
    es.evaluator_id as teacher_id,
    CONCAT('Completed evaluation for ', u.first_name, ' ', u.last_name) COLLATE utf8mb4_unicode_ci as description,
    es.updated_at as activity_date
FROM evaluation_sessions es
JOIN users u ON es.evaluatee_id = u.id
WHERE es.status = 'completed'
ORDER BY activity_date DESC; 