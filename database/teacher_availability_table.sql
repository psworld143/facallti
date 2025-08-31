-- Teacher Availability Table
-- Tracks teacher availability status through QR code scanning
-- This table stores when teachers scan their QR codes to indicate availability

CREATE TABLE IF NOT EXISTS `teacher_availability` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL COMMENT 'Foreign key to faculty table',
  `availability_date` date NOT NULL COMMENT 'Date when teacher is available',
  `scan_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When the QR code was scanned',
  `status` enum('available','unavailable') NOT NULL DEFAULT 'available' COMMENT 'Current availability status',
  `last_activity` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last activity timestamp',
  `notes` text DEFAULT NULL COMMENT 'Optional notes about availability',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_teacher_date` (`teacher_id`, `availability_date`) COMMENT 'One availability record per teacher per day',
  KEY `idx_teacher_id` (`teacher_id`) COMMENT 'Index for teacher lookups',
  KEY `idx_availability_date` (`availability_date`) COMMENT 'Index for date queries',
  KEY `idx_status` (`status`) COMMENT 'Index for status filtering',
  KEY `idx_scan_time` (`scan_time`) COMMENT 'Index for scan time queries',
  KEY `idx_active_teachers` (`teacher_id`, `availability_date`, `status`) COMMENT 'Composite index for active teacher queries',
  CONSTRAINT `fk_teacher_availability_faculty` FOREIGN KEY (`teacher_id`) REFERENCES `faculty` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Teacher availability tracking through QR code scanning';

-- =====================================================
-- INDEXES FOR PERFORMANCE
-- =====================================================

-- Add additional performance indexes
CREATE INDEX IF NOT EXISTS `idx_teacher_availability_active` ON `teacher_availability` (`teacher_id`, `status`, `availability_date`);
CREATE INDEX IF NOT EXISTS `idx_teacher_availability_recent` ON `teacher_availability` (`scan_time`, `status`);

-- =====================================================
-- VIEW FOR ACTIVE TEACHERS
-- =====================================================

CREATE OR REPLACE VIEW `active_teachers_today` AS
SELECT 
    ta.id,
    ta.teacher_id,
    f.first_name,
    f.last_name,
    f.email,
    f.department,
    f.position,
    f.image_url,
    ta.availability_date,
    ta.scan_time,
    ta.status,
    ta.last_activity,
    TIMESTAMPDIFF(MINUTE, ta.last_activity, NOW()) as minutes_since_last_activity
FROM teacher_availability ta
JOIN faculty f ON ta.teacher_id = f.id
WHERE ta.availability_date = CURDATE()
AND ta.status = 'available'
AND f.is_active = 1
ORDER BY ta.scan_time DESC;

-- =====================================================
-- STORED PROCEDURE FOR MARKING TEACHER AVAILABLE
-- =====================================================

DELIMITER //
CREATE PROCEDURE MarkTeacherAvailable(IN p_teacher_id INT, IN p_notes TEXT)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Insert or update teacher availability
    INSERT INTO teacher_availability (teacher_id, availability_date, status, notes, last_activity)
    VALUES (p_teacher_id, CURDATE(), 'available', p_notes, NOW())
    ON DUPLICATE KEY UPDATE
        status = 'available',
        scan_time = NOW(),
        last_activity = NOW(),
        notes = COALESCE(p_notes, notes),
        updated_at = NOW();
    
    COMMIT;
END //
DELIMITER ;

-- =====================================================
-- STORED PROCEDURE FOR MARKING TEACHER UNAVAILABLE
-- =====================================================

DELIMITER //
CREATE PROCEDURE MarkTeacherUnavailable(IN p_teacher_id INT, IN p_notes TEXT)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Update teacher availability to unavailable
    UPDATE teacher_availability 
    SET status = 'unavailable',
        last_activity = NOW(),
        notes = COALESCE(p_notes, notes),
        updated_at = NOW()
    WHERE teacher_id = p_teacher_id 
    AND availability_date = CURDATE();
    
    COMMIT;
END //
DELIMITER ;

-- =====================================================
-- TRIGGER TO AUTO-UPDATE LAST_ACTIVITY
-- =====================================================

DELIMITER //
CREATE TRIGGER update_teacher_activity
BEFORE UPDATE ON teacher_availability
FOR EACH ROW
BEGIN
    SET NEW.last_activity = NOW();
END //
DELIMITER ;
