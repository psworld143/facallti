-- Consultation Leave Table
-- This table manages consultation leave for teachers

CREATE TABLE IF NOT EXISTS `consultation_leave` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `leave_date` date NOT NULL,
  `reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `teacher_id` (`teacher_id`),
  KEY `leave_date` (`leave_date`),
  KEY `idx_consultation_leave_teacher_date` (`teacher_id`, `leave_date`),
  CONSTRAINT `fk_consultation_leave_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `faculty` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add indexes for better performance
CREATE INDEX IF NOT EXISTS `idx_consultation_leave_current_date` ON `consultation_leave` (`leave_date`);
CREATE INDEX IF NOT EXISTS `idx_consultation_leave_future` ON `consultation_leave` (`leave_date`) WHERE `leave_date` >= CURDATE();

-- Create a view for active consultation leaves
CREATE OR REPLACE VIEW `active_consultation_leaves` AS
SELECT 
    cl.id,
    cl.teacher_id,
    cl.leave_date,
    cl.reason,
    cl.created_at,
    f.first_name,
    f.last_name,
    f.department,
    f.position
FROM consultation_leave cl
JOIN faculty f ON cl.teacher_id = f.id
WHERE cl.leave_date >= CURDATE()
ORDER BY cl.leave_date ASC, f.last_name ASC, f.first_name ASC;

-- =====================================================
-- SAMPLE DATA FOR CONSULTATION LEAVE
-- =====================================================

-- Sample consultation leave data for testing
-- Note: Replace teacher_id values with actual faculty IDs from your database

-- Today's leave (teacher will be unavailable today)
INSERT INTO `consultation_leave` (`teacher_id`, `leave_date`, `reason`) VALUES
(1, CURDATE(), 'Medical appointment - Annual checkup'),
(3, CURDATE(), 'Personal emergency - Family matter'),
(5, CURDATE(), 'Conference attendance - Educational seminar');

-- Tomorrow's leave (teacher will be unavailable tomorrow)
INSERT INTO `consultation_leave` (`teacher_id`, `leave_date`, `reason`) VALUES
(2, DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'Department meeting - Curriculum planning'),
(4, DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'Training session - New software implementation');

-- Next week's leave (teacher will be unavailable next week)
INSERT INTO `consultation_leave` (`teacher_id`, `leave_date`, `reason`) VALUES
(6, DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'Research presentation - Academic conference'),
(8, DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'Workshop facilitation - Student development program');

-- Past leave (should not affect current availability)
INSERT INTO `consultation_leave` (`teacher_id`, `leave_date`, `reason`) VALUES
(7, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'Previous day leave - Already completed'),
(9, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'Past leave - No longer relevant');

-- =====================================================
-- SAMPLE FACULTY DATA (if not exists)
-- =====================================================

-- Insert sample faculty members if they don't exist
-- Adjust the IDs and details according to your existing faculty table

INSERT IGNORE INTO `faculty` (`id`, `first_name`, `last_name`, `email`, `department`, `position`, `is_active`) VALUES
(1, 'John', 'Smith', 'john.smith@seait.edu.ph', 'Computer Science', 'Associate Professor', 1),
(2, 'Maria', 'Garcia', 'maria.garcia@seait.edu.ph', 'Computer Science', 'Assistant Professor', 1),
(3, 'Robert', 'Johnson', 'robert.johnson@seait.edu.ph', 'Information Technology', 'Professor', 1),
(4, 'Ana', 'Martinez', 'ana.martinez@seait.edu.ph', 'Information Technology', 'Instructor', 1),
(5, 'David', 'Brown', 'david.brown@seait.edu.ph', 'Computer Engineering', 'Associate Professor', 1),
(6, 'Lisa', 'Davis', 'lisa.davis@seait.edu.ph', 'Computer Engineering', 'Assistant Professor', 1),
(7, 'Michael', 'Wilson', 'michael.wilson@seait.edu.ph', 'Computer Science', 'Instructor', 1),
(8, 'Sarah', 'Anderson', 'sarah.anderson@seait.edu.ph', 'Information Technology', 'Professor', 1),
(9, 'James', 'Taylor', 'james.taylor@seait.edu.ph', 'Computer Engineering', 'Instructor', 1),
(10, 'Emily', 'Thomas', 'emily.thomas@seait.edu.ph', 'Computer Science', 'Assistant Professor', 1);

-- =====================================================
-- SAMPLE CONSULTATION HOURS DATA (if not exists)
-- =====================================================

-- Insert sample consultation hours for the faculty members
-- This ensures teachers have scheduled consultation hours to appear as available

INSERT IGNORE INTO `consultation_hours` (`teacher_id`, `semester`, `academic_year`, `day_of_week`, `start_time`, `end_time`, `room`, `notes`, `is_active`, `created_by`) VALUES
-- Available teachers (no leave today)
(2, 'First Semester', '2024-2025', DAYNAME(CURDATE()), '09:00:00', '11:00:00', 'Room 101', 'Available for consultation', 1, 1),
(4, 'First Semester', '2024-2025', DAYNAME(CURDATE()), '10:00:00', '12:00:00', 'Room 102', 'Open consultation hours', 1, 1),
(6, 'First Semester', '2024-2025', DAYNAME(CURDATE()), '13:00:00', '15:00:00', 'Room 103', 'Afternoon consultation', 1, 1),
(7, 'First Semester', '2024-2025', DAYNAME(CURDATE()), '14:00:00', '16:00:00', 'Room 104', 'Student consultation time', 1, 1),
(8, 'First Semester', '2024-2025', DAYNAME(CURDATE()), '15:00:00', '17:00:00', 'Room 105', 'Late afternoon consultation', 1, 1),
(9, 'First Semester', '2024-2025', DAYNAME(CURDATE()), '16:00:00', '18:00:00', 'Room 106', 'Evening consultation', 1, 1),
(10, 'First Semester', '2024-2025', DAYNAME(CURDATE()), '08:00:00', '10:00:00', 'Room 107', 'Morning consultation', 1, 1),

-- Unavailable teachers (on leave today) - but still have consultation hours
(1, 'First Semester', '2024-2025', DAYNAME(CURDATE()), '09:00:00', '11:00:00', 'Room 201', 'On leave today', 1, 1),
(3, 'First Semester', '2024-2025', DAYNAME(CURDATE()), '10:00:00', '12:00:00', 'Room 202', 'On leave today', 1, 1),
(5, 'First Semester', '2024-2025', DAYNAME(CURDATE()), '13:00:00', '15:00:00', 'Room 203', 'On leave today', 1, 1);

-- =====================================================
-- TESTING QUERIES
-- =====================================================

-- Query to see all teachers with consultation hours for today
-- SELECT 
--     f.id,
--     f.first_name,
--     f.last_name,
--     f.department,
--     ch.start_time,
--     ch.end_time,
--     ch.room,
--     CASE 
--         WHEN cl.teacher_id IS NOT NULL THEN 'On Leave'
--         ELSE 'Available'
--     END as status
-- FROM faculty f
-- INNER JOIN consultation_hours ch ON f.id = ch.teacher_id
-- LEFT JOIN consultation_leave cl ON f.id = cl.teacher_id AND cl.leave_date = CURDATE()
-- WHERE f.is_active = 1 
-- AND ch.day_of_week = DAYNAME(CURDATE())
-- AND ch.is_active = 1
-- AND ch.start_time <= CURTIME()
-- AND ch.end_time >= CURTIME()
-- ORDER BY f.first_name, f.last_name;

-- Query to see only available teachers (not on leave)
-- SELECT 
--     f.id,
--     f.first_name,
--     f.last_name,
--     f.department,
--     ch.start_time,
--     ch.end_time,
--     ch.room
-- FROM faculty f
-- INNER JOIN consultation_hours ch ON f.id = ch.teacher_id
-- WHERE f.is_active = 1 
-- AND ch.day_of_week = DAYNAME(CURDATE())
-- AND ch.is_active = 1
-- AND ch.start_time <= CURTIME()
-- AND ch.end_time >= CURTIME()
-- AND f.id NOT IN (
--     SELECT teacher_id 
--     FROM consultation_leave 
--     WHERE leave_date = CURDATE()
-- )
-- ORDER BY f.first_name, f.last_name;
