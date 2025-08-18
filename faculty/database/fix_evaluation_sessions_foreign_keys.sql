-- Fix Evaluation Sessions Table Foreign Key Constraints
-- This script updates the evaluation_sessions table to handle both users and faculty IDs

-- Drop the existing foreign key constraints
ALTER TABLE `evaluation_sessions` DROP FOREIGN KEY `fk_evaluation_sessions_evaluator`;
ALTER TABLE `evaluation_sessions` DROP FOREIGN KEY `fk_evaluation_sessions_evaluatee`;

-- Add new foreign key constraints that allow both users and faculty IDs
-- Note: We'll use a different approach - we'll disable foreign key checks during operations
-- and handle the validation in the application code

-- For now, we'll remove the foreign key constraints entirely to allow flexibility
-- The application will handle the validation of IDs

-- Update the table structure to be more flexible
ALTER TABLE `evaluation_sessions` 
MODIFY COLUMN `evaluator_id` int(11) NOT NULL COMMENT 'ID from either users or faculty table',
MODIFY COLUMN `evaluatee_id` int(11) NOT NULL COMMENT 'ID from either users or faculty table';

-- Add indexes for better performance
CREATE INDEX IF NOT EXISTS `idx_evaluation_sessions_evaluator` ON `evaluation_sessions` (`evaluator_id`, `evaluator_type`);
CREATE INDEX IF NOT EXISTS `idx_evaluation_sessions_evaluatee` ON `evaluation_sessions` (`evaluatee_id`, `evaluatee_type`);
CREATE INDEX IF NOT EXISTS `idx_evaluation_sessions_status` ON `evaluation_sessions` (`status`);
CREATE INDEX IF NOT EXISTS `idx_evaluation_sessions_date` ON `evaluation_sessions` (`evaluation_date`);

-- Note: The application code will need to handle the validation of IDs
-- and ensure that the correct table is referenced based on the evaluator_type and evaluatee_type 