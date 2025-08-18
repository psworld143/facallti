-- Class Materials Table for Faculty Module
-- This table stores learning materials uploaded by teachers for their classes

CREATE TABLE IF NOT EXISTS `class_materials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) NOT NULL,
  `file_type` varchar(100) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT 0,
  `download_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `class_id` (`class_id`),
  KEY `teacher_id` (`teacher_id`),
  KEY `category` (`category`),
  KEY `is_public` (`is_public`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `fk_materials_class` FOREIGN KEY (`class_id`) REFERENCES `teacher_classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_materials_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add indexes for better performance
CREATE INDEX `idx_materials_teacher_date` ON `class_materials` (`teacher_id`, `created_at`);
CREATE INDEX `idx_materials_class_category` ON `class_materials` (`class_id`, `category`);
CREATE INDEX `idx_materials_public_date` ON `class_materials` (`is_public`, `created_at`); 