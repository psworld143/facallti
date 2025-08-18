-- Fix Lessons Table - Migration Script
-- This script fixes the file_type column size issue

-- Check if lessons table exists and fix the file_type column
-- If the table doesn't exist, this will create it with the correct structure

-- First, try to alter the existing table if it exists
ALTER TABLE `lessons` MODIFY COLUMN `file_type` varchar(255) DEFAULT NULL COMMENT 'MIME type of the file';

-- If the above fails (table doesn't exist), create the table
-- This will be handled by the lessons_table.sql file

-- Also ensure the lesson_class_assignments table exists
CREATE TABLE IF NOT EXISTS `lesson_class_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lesson_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_lesson_class` (`lesson_id`, `class_id`),
  KEY `lesson_id` (`lesson_id`),
  KEY `class_id` (`class_id`),
  CONSTRAINT `fk_lesson_assignments_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lesson_assignments_class` FOREIGN KEY (`class_id`) REFERENCES `teacher_classes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci; 