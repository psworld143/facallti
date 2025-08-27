-- Consultation Hours Management Tables
-- This script creates the necessary tables for managing teacher consultation hours

-- =====================================================
-- CONSULTATION HOURS TABLE
-- =====================================================

CREATE TABLE IF NOT EXISTS `consultation_hours` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `semester` varchar(20) NOT NULL,
  `academic_year` varchar(10) NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `room` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `teacher_id` (`teacher_id`),
  KEY `semester` (`semester`),
  KEY `academic_year` (`academic_year`),
  KEY `day_of_week` (`day_of_week`),
  KEY `is_active` (`is_active`),
  KEY `created_by` (`created_by`),
  KEY `idx_consultation_teacher_semester` (`teacher_id`, `semester`, `academic_year`),
  KEY `idx_consultation_active` (`is_active`, `semester`, `academic_year`),
  CONSTRAINT `fk_consultation_hours_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `faculty` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_consultation_hours_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SEMESTERS TABLE (if not exists)
-- =====================================================

CREATE TABLE IF NOT EXISTS `semesters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `academic_year` varchar(10) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_active` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_semester_year` (`name`, `academic_year`),
  KEY `is_active` (`is_active`),
  KEY `start_date` (`start_date`),
  KEY `end_date` (`end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SAMPLE DATA FOR SEMESTERS
-- =====================================================

INSERT IGNORE INTO `semesters` (`name`, `academic_year`, `start_date`, `end_date`, `is_active`) VALUES
('First Semester', '2024-2025', '2024-08-26', '2024-12-20', 1),
('Second Semester', '2024-2025', '2025-01-13', '2025-05-16', 0),
('Summer', '2024-2025', '2025-06-02', '2025-07-25', 0),
('First Semester', '2025-2026', '2025-08-25', '2025-12-19', 0),
('Second Semester', '2025-2026', '2026-01-12', '2026-05-15', 0);

-- =====================================================
-- INDEXES FOR PERFORMANCE
-- =====================================================

-- Add additional performance indexes
CREATE INDEX IF NOT EXISTS `idx_consultation_day_time` ON `consultation_hours` (`day_of_week`, `start_time`, `end_time`);
CREATE INDEX IF NOT EXISTS `idx_consultation_semester_active` ON `consultation_hours` (`semester`, `academic_year`, `is_active`);
CREATE INDEX IF NOT EXISTS `idx_consultation_teacher_active` ON `consultation_hours` (`teacher_id`, `is_active`);

-- =====================================================
-- VIEW FOR CONSULTATION HOURS SUMMARY
-- =====================================================

CREATE OR REPLACE VIEW `consultation_hours_summary` AS
SELECT 
    ch.id,
    ch.teacher_id,
    f.first_name,
    f.last_name,
    f.email,
    f.department,
    ch.semester,
    ch.academic_year,
    ch.day_of_week,
    ch.start_time,
    ch.end_time,
    ch.room,
    ch.notes,
    ch.is_active,
    ch.created_at,
    ch.updated_at
FROM consultation_hours ch
JOIN faculty f ON ch.teacher_id = f.id
WHERE ch.is_active = 1
ORDER BY f.last_name, f.first_name, ch.day_of_week, ch.start_time;

-- =====================================================
-- SAMPLE CONSULTATION HOURS DATA
-- =====================================================

-- Insert sample consultation hours (uncomment and modify as needed)
-- Note: Make sure the teacher_id values exist in your faculty table

/*
INSERT INTO `consultation_hours` (`teacher_id`, `semester`, `academic_year`, `day_of_week`, `start_time`, `end_time`, `room`, `notes`, `created_by`) VALUES
(2, 'First Semester', '2024-2025', 'Monday', '08:00:00', '10:00:00', 'Room 101', 'Software Engineering Consultation', 1),
(2, 'First Semester', '2024-2025', 'Tuesday', '10:00:00', '11:00:00', 'Room 101', 'Web Development Consultation', 1),
(2, 'First Semester', '2024-2025', 'Wednesday', '14:00:00', '16:00:00', 'Room 101', 'Database Systems Consultation', 1),
(5, 'First Semester', '2024-2025', 'Monday', '08:00:00', '10:00:00', 'Room 102', 'Data Science Consultation', 1),
(5, 'First Semester', '2024-2025', 'Thursday', '13:00:00', '15:00:00', 'Room 102', 'Machine Learning Consultation', 1),
(6, 'First Semester', '2024-2025', 'Tuesday', '09:00:00', '11:00:00', 'Room 103', 'Embedded Systems Consultation', 1),
(6, 'First Semester', '2024-2025', 'Friday', '14:00:00', '16:00:00', 'Room 103', 'IoT Consultation', 1);
*/
