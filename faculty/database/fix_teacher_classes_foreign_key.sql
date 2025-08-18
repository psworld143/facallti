-- Fix Teacher Classes Table Foreign Key Constraint
-- This script updates the teacher_classes table to reference faculty table instead of users table

-- Drop the existing foreign key constraint
ALTER TABLE `teacher_classes` DROP FOREIGN KEY `fk_teacher_classes_teacher`;

-- Add the new foreign key constraint to reference faculty table
ALTER TABLE `teacher_classes` 
ADD CONSTRAINT `fk_teacher_classes_teacher` 
FOREIGN KEY (`teacher_id`) REFERENCES `faculty` (`id`) ON DELETE CASCADE;

-- Update the index name to reflect the change
DROP INDEX `idx_teacher_classes_teacher_status` ON `teacher_classes`;
CREATE INDEX `idx_teacher_classes_teacher_status` ON `teacher_classes` (`teacher_id`, `status`);

-- Note: This change assumes that teacher_id in teacher_classes now references faculty.id
-- All existing queries that join teacher_classes with users table need to be updated
-- to join with faculty table instead 