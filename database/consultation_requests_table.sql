-- Consultation Requests Table
-- This table manages real-time consultation requests between students and teachers

CREATE TABLE IF NOT EXISTS `consultation_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `student_name` varchar(255) NOT NULL,
  `student_dept` varchar(255) DEFAULT NULL,
  `student_id` int(11) DEFAULT NULL,
  `status` enum('pending','accepted','declined','completed','cancelled') NOT NULL DEFAULT 'pending',
  `session_id` varchar(255) DEFAULT NULL,
  `request_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `response_time` timestamp NULL DEFAULT NULL,
  `start_time` timestamp NULL DEFAULT NULL,
  `end_time` timestamp NULL DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `teacher_id` (`teacher_id`),
  KEY `student_id` (`student_id`),
  KEY `status` (`status`),
  KEY `request_time` (`request_time`),
  KEY `session_id` (`session_id`),
  KEY `idx_consultation_teacher_status` (`teacher_id`, `status`),
  KEY `idx_consultation_pending` (`status`, `request_time`),
  CONSTRAINT `fk_consultation_requests_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `faculty` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_consultation_requests_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add indexes for better performance
CREATE INDEX IF NOT EXISTS `idx_consultation_requests_recent` ON `consultation_requests` (`teacher_id`, `status`, `request_time`);
CREATE INDEX IF NOT EXISTS `idx_consultation_requests_session` ON `consultation_requests` (`session_id`, `status`);

-- Create a view for active consultation requests
CREATE OR REPLACE VIEW `active_consultation_requests` AS
SELECT 
    cr.id,
    cr.teacher_id,
    f.first_name as teacher_first_name,
    f.last_name as teacher_last_name,
    f.department as teacher_department,
    cr.student_name,
    cr.student_dept,
    cr.student_id,
    cr.status,
    cr.session_id,
    cr.request_time,
    cr.response_time,
    cr.start_time,
    cr.end_time,
    cr.duration_minutes,
    cr.notes,
    TIMESTAMPDIFF(MINUTE, cr.request_time, NOW()) as minutes_since_request
FROM consultation_requests cr
JOIN faculty f ON cr.teacher_id = f.id
WHERE cr.status IN ('pending', 'accepted')
AND cr.request_time > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
ORDER BY cr.request_time DESC;
