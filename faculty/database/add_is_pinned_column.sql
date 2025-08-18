-- Add is_pinned column to class_announcements table
-- This migration adds the missing is_pinned column that the PHP code expects

ALTER TABLE `class_announcements` 
ADD COLUMN `is_pinned` tinyint(1) DEFAULT 0 AFTER `priority`;

-- Add index for better performance when sorting by pinned status
CREATE INDEX `idx_announcements_pinned_date` ON `class_announcements` (`is_pinned`, `created_at`); 