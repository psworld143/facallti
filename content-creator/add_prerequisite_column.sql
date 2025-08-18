-- Add prerequisite_id column to course_curriculum table
-- This script should be run in your MySQL database

ALTER TABLE course_curriculum 
ADD COLUMN prerequisite_id INT NULL,
ADD FOREIGN KEY (prerequisite_id) REFERENCES course_curriculum(id) ON DELETE SET NULL;

-- Add index for better performance
CREATE INDEX idx_course_curriculum_prerequisite ON course_curriculum(prerequisite_id); 