-- Syllabus Fields Migration Script
-- This script adds additional fields to the existing class_syllabus table

-- Add new fields to class_syllabus table
ALTER TABLE `class_syllabus` 
ADD COLUMN `course_units` varchar(10) DEFAULT NULL AFTER `assessment_methods`,
ADD COLUMN `course_credits` varchar(10) DEFAULT NULL AFTER `course_units`,
ADD COLUMN `semester` varchar(50) DEFAULT NULL AFTER `course_credits`,
ADD COLUMN `academic_year` varchar(20) DEFAULT NULL AFTER `semester`,
ADD COLUMN `class_schedule` text AFTER `academic_year`,
ADD COLUMN `classroom_location` varchar(255) DEFAULT NULL AFTER `class_schedule`,
ADD COLUMN `course_website` varchar(500) DEFAULT NULL AFTER `classroom_location`,
ADD COLUMN `emergency_contact` text AFTER `course_website`,
ADD COLUMN `disability_accommodations` text AFTER `emergency_contact`;

-- Update existing records with default values if needed
-- This is optional and can be customized based on your needs
-- UPDATE class_syllabus SET semester = 'Current Semester' WHERE semester IS NULL;
-- UPDATE class_syllabus SET academic_year = '2024-2025' WHERE academic_year IS NULL;
