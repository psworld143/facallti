-- Quiz Class Assignments Junction Table
-- This allows quizzes to be assigned to multiple classes

-- Create the junction table for quiz-class assignments
CREATE TABLE IF NOT EXISTS `quiz_class_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quiz_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_quiz_class` (`quiz_id`, `class_id`),
  KEY `quiz_id` (`quiz_id`),
  KEY `class_id` (`class_id`),
  CONSTRAINT `fk_quiz_assignments_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_quiz_assignments_class` FOREIGN KEY (`class_id`) REFERENCES `teacher_classes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add indexes for better performance
CREATE INDEX `idx_quiz_assignments_quiz` ON `quiz_class_assignments` (`quiz_id`);
CREATE INDEX `idx_quiz_assignments_class` ON `quiz_class_assignments` (`class_id`);

-- Note: The class_id column in the quizzes table will be kept for backward compatibility
-- but new quizzes should use the junction table for multiple class assignments 