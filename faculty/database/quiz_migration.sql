-- Quiz System Migration Script
-- Update existing quizzes table to match new schema

-- Update quiz_type enum to include new values
ALTER TABLE `quizzes` MODIFY COLUMN `quiz_type` enum('general','lesson_specific','multiple_choice','true_false','essay','mixed') DEFAULT 'general';

-- Add lesson_id column if it doesn't exist
ALTER TABLE `quizzes` ADD COLUMN IF NOT EXISTS `lesson_id` int(11) DEFAULT NULL AFTER `quiz_type`;

-- Add max_attempts column if it doesn't exist
ALTER TABLE `quizzes` ADD COLUMN IF NOT EXISTS `max_attempts` int(11) DEFAULT 1 COMMENT 'Maximum attempts allowed' AFTER `passing_score`;

-- Update status enum to use 'published' instead of 'active'
ALTER TABLE `quizzes` MODIFY COLUMN `status` enum('draft','published','archived') DEFAULT 'draft';

-- Update existing 'active' status to 'published'
UPDATE `quizzes` SET `status` = 'published' WHERE `status` = 'active';

-- Add foreign key constraint for lesson_id if it doesn't exist
-- Note: This will only work if the lessons table exists
-- ALTER TABLE `quizzes` ADD CONSTRAINT `fk_quizzes_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE SET NULL;

-- Add index for lesson_id if it doesn't exist
ALTER TABLE `quizzes` ADD INDEX IF NOT EXISTS `lesson_id` (`lesson_id`);

-- Update any existing quizzes that might have invalid quiz_type values
UPDATE `quizzes` SET `quiz_type` = 'general' WHERE `quiz_type` NOT IN ('general','lesson_specific','multiple_choice','true_false','essay','mixed'); 