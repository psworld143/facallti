-- Add missing indexes and views for Faculty Module
-- This file only adds indexes and views that don't already exist

-- =====================================================
-- ADD INDEXES (IF NOT EXISTS)
-- =====================================================

-- Add index for announcements if it doesn't exist
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA = 'seait_website' 
     AND TABLE_NAME = 'class_announcements' 
     AND INDEX_NAME = 'idx_announcements_teacher_date') = 0,
    'CREATE INDEX idx_announcements_teacher_date ON class_announcements (teacher_id, created_at)',
    'SELECT "Index idx_announcements_teacher_date already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index for events if it doesn't exist
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA = 'seait_website' 
     AND TABLE_NAME = 'faculty_events' 
     AND INDEX_NAME = 'idx_events_teacher_date') = 0,
    'CREATE INDEX idx_events_teacher_date ON faculty_events (teacher_id, event_date)',
    'SELECT "Index idx_events_teacher_date already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index for notifications if it doesn't exist
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA = 'seait_website' 
     AND TABLE_NAME = 'faculty_notifications' 
     AND INDEX_NAME = 'idx_notifications_teacher_read') = 0,
    'CREATE INDEX idx_notifications_teacher_read ON faculty_notifications (teacher_id, is_read, created_at)',
    'SELECT "Index idx_notifications_teacher_read already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- CREATE OR REPLACE VIEWS
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