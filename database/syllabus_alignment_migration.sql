-- Syllabus Alignment Migration Script
-- This script ensures all syllabus-related files and database fields are aligned

-- 1. Update class_syllabus table to ensure all fields exist and are correctly named
ALTER TABLE `class_syllabus` 
ADD COLUMN IF NOT EXISTS `course_units` varchar(10) DEFAULT NULL AFTER `assessment_methods`,
ADD COLUMN IF NOT EXISTS `course_credits` varchar(10) DEFAULT NULL AFTER `course_units`,
ADD COLUMN IF NOT EXISTS `semester` varchar(50) DEFAULT NULL AFTER `course_credits`,
ADD COLUMN IF NOT EXISTS `academic_year` varchar(20) DEFAULT NULL AFTER `semester`,
ADD COLUMN IF NOT EXISTS `class_schedule` text AFTER `academic_year`,
ADD COLUMN IF NOT EXISTS `classroom_location` varchar(255) DEFAULT NULL AFTER `class_schedule`,
ADD COLUMN IF NOT EXISTS `course_website` varchar(500) DEFAULT NULL AFTER `classroom_location`,
ADD COLUMN IF NOT EXISTS `emergency_contact` text AFTER `course_website`,
ADD COLUMN IF NOT EXISTS `disability_accommodations` text AFTER `emergency_contact`;

-- 2. Ensure the references field is correctly named as course_references
-- First, check if 'references' column exists and rename it to 'course_references'
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'seait_website' 
     AND TABLE_NAME = 'class_syllabus' 
     AND COLUMN_NAME = 'references') > 0,
    'ALTER TABLE class_syllabus CHANGE `references` `course_references` text;',
    'SELECT "course_references column already exists or references column does not exist" as message;'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. Ensure syllabus_topics table has all required fields
ALTER TABLE `syllabus_topics` 
ADD COLUMN IF NOT EXISTS `references_field` text AFTER `materials`,
ADD COLUMN IF NOT EXISTS `values_integration` text AFTER `assessment`,
ADD COLUMN IF NOT EXISTS `target` text AFTER `values_integration`;

-- 4. Update existing data to ensure consistency
-- Update any existing syllabus records to have proper field values
UPDATE `class_syllabus` 
SET `course_units` = COALESCE(`course_units`, '3'),
    `course_credits` = COALESCE(`course_credits`, '3'),
    `semester` = COALESCE(`semester`, 'First Semester'),
    `academic_year` = COALESCE(`academic_year`, '2024-2025'),
    `class_schedule` = COALESCE(`class_schedule`, 'Monday and Wednesday 9:00-10:30 AM'),
    `classroom_location` = COALESCE(`classroom_location`, 'Room 101, Computer Science Building'),
    `course_website` = COALESCE(`course_website`, 'https://canvas.university.edu/courses/12345'),
    `emergency_contact` = COALESCE(`emergency_contact`, 'For emergencies during class, contact campus security at (555) 999-1111'),
    `disability_accommodations` = COALESCE(`disability_accommodations`, 'Students with disabilities should contact the Office of Disability Services for accommodations.')
WHERE `id` > 0;

-- 5. Ensure all alignment tables exist and have proper structure
-- This is handled by the add_class_syllabus_tables.sql file, but let's ensure they exist
CREATE TABLE IF NOT EXISTS `syllabus_peos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `syllabus_id` int(11) NOT NULL,
  `peo_code` varchar(10) NOT NULL,
  `peo_description` text NOT NULL,
  `aligned_to_mission` tinyint(1) DEFAULT 0,
  `order_number` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `syllabus_id` (`syllabus_id`),
  CONSTRAINT `syllabus_peos_ibfk_1` FOREIGN KEY (`syllabus_id`) REFERENCES `class_syllabus` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `syllabus_pos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `syllabus_id` int(11) NOT NULL,
  `po_code` varchar(10) NOT NULL,
  `po_description` text NOT NULL,
  `order_number` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `syllabus_id` (`syllabus_id`),
  CONSTRAINT `syllabus_pos_ibfk_1` FOREIGN KEY (`syllabus_id`) REFERENCES `class_syllabus` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `syllabus_clos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `syllabus_id` int(11) NOT NULL,
  `clo_code` varchar(10) NOT NULL,
  `clo_description` text NOT NULL,
  `order_number` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `syllabus_id` (`syllabus_id`),
  CONSTRAINT `syllabus_clos_ibfk_1` FOREIGN KEY (`syllabus_id`) REFERENCES `class_syllabus` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `syllabus_peo_po_alignment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `syllabus_id` int(11) NOT NULL,
  `peo_id` int(11) NOT NULL,
  `po_id` int(11) NOT NULL,
  `is_aligned` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_peo_po` (`syllabus_id`, `peo_id`, `po_id`),
  KEY `peo_id` (`peo_id`),
  KEY `po_id` (`po_id`),
  CONSTRAINT `syllabus_peo_po_alignment_ibfk_1` FOREIGN KEY (`syllabus_id`) REFERENCES `class_syllabus` (`id`) ON DELETE CASCADE,
  CONSTRAINT `syllabus_peo_po_alignment_ibfk_2` FOREIGN KEY (`peo_id`) REFERENCES `syllabus_peos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `syllabus_peo_po_alignment_ibfk_3` FOREIGN KEY (`po_id`) REFERENCES `syllabus_pos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `syllabus_clo_po_alignment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `syllabus_id` int(11) NOT NULL,
  `clo_id` int(11) NOT NULL,
  `po_id` int(11) NOT NULL,
  `is_aligned` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_clo_po` (`syllabus_id`, `clo_id`, `po_id`),
  KEY `clo_id` (`clo_id`),
  KEY `po_id` (`po_id`),
  CONSTRAINT `syllabus_clo_po_alignment_ibfk_1` FOREIGN KEY (`syllabus_id`) REFERENCES `class_syllabus` (`id`) ON DELETE CASCADE,
  CONSTRAINT `syllabus_clo_po_alignment_ibfk_2` FOREIGN KEY (`clo_id`) REFERENCES `syllabus_clos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `syllabus_clo_po_alignment_ibfk_3` FOREIGN KEY (`po_id`) REFERENCES `syllabus_pos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `syllabus_topic_clo_alignment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `syllabus_id` int(11) NOT NULL,
  `topic_id` int(11) NOT NULL,
  `clo_id` int(11) NOT NULL,
  `is_aligned` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_topic_clo` (`syllabus_id`, `topic_id`, `clo_id`),
  KEY `topic_id` (`topic_id`),
  KEY `clo_id` (`clo_id`),
  CONSTRAINT `syllabus_topic_clo_alignment_ibfk_1` FOREIGN KEY (`syllabus_id`) REFERENCES `class_syllabus` (`id`) ON DELETE CASCADE,
  CONSTRAINT `syllabus_topic_clo_alignment_ibfk_2` FOREIGN KEY (`topic_id`) REFERENCES `syllabus_topics` (`id`) ON DELETE CASCADE,
  CONSTRAINT `syllabus_topic_clo_alignment_ibfk_3` FOREIGN KEY (`clo_id`) REFERENCES `syllabus_clos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `syllabus_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `syllabus_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `description` text,
  `uploaded_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `syllabus_id` (`syllabus_id`),
  CONSTRAINT `syllabus_files_ibfk_1` FOREIGN KEY (`syllabus_id`) REFERENCES `class_syllabus` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Verify the alignment by showing the final table structure
SELECT 'Syllabus Alignment Migration Completed Successfully' as status;
