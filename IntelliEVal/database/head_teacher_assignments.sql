-- Head Teacher Assignments Table for IntelliEVal System
-- This table manages the relationship between department heads and teachers

-- =====================================================
-- HEAD TEACHER ASSIGNMENTS TABLE
-- =====================================================

CREATE TABLE IF NOT EXISTS `head_teacher_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `head_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `assigned_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_head_teacher` (`head_id`, `teacher_id`),
  KEY `head_id` (`head_id`),
  KEY `teacher_id` (`teacher_id`),
  KEY `status` (`status`),
  KEY `assigned_date` (`assigned_date`),
  CONSTRAINT `fk_head_teacher_assignments_head` FOREIGN KEY (`head_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_head_teacher_assignments_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `faculty` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- INDEXES FOR PERFORMANCE
-- =====================================================

-- Add performance indexes
CREATE INDEX IF NOT EXISTS `idx_head_teacher_assignments_head_status` ON `head_teacher_assignments` (`head_id`, `status`);
CREATE INDEX IF NOT EXISTS `idx_head_teacher_assignments_teacher_status` ON `head_teacher_assignments` (`teacher_id`, `status`);
CREATE INDEX IF NOT EXISTS `idx_head_teacher_assignments_assigned_date` ON `head_teacher_assignments` (`assigned_date`);

-- =====================================================
-- SAMPLE DATA (Optional)
-- =====================================================

-- Insert sample assignments (uncomment and modify as needed)
-- Note: Make sure the head_id and teacher_id values exist in your users and faculty tables

-- INSERT IGNORE INTO `head_teacher_assignments` (`head_id`, `teacher_id`, `assigned_date`) VALUES
-- (1, 1, NOW()),
-- (1, 2, NOW()),
-- (2, 3, NOW()),
-- (2, 4, NOW()),
-- (3, 5, NOW());

-- =====================================================
-- NOTES
-- =====================================================

-- This table creates a many-to-many relationship between department heads and teachers
-- - A head can be assigned multiple teachers
-- - A teacher can be assigned to multiple heads (if needed)
-- - The unique constraint prevents duplicate assignments
-- - Foreign key constraints ensure data integrity
-- - The status field allows for soft deletion/deactivation of assignments 