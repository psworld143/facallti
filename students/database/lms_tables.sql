-- LMS System Database Tables
-- Complete Learning Management System for SEAIT

-- =====================================================
-- LEARNING MATERIALS
-- =====================================================

-- Learning materials categories
CREATE TABLE IF NOT EXISTS `lms_material_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT 'fas fa-folder',
  `color` varchar(20) DEFAULT '#3B82F6',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `status` (`status`),
  CONSTRAINT `fk_material_categories_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Learning materials
CREATE TABLE IF NOT EXISTS `lms_materials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `external_url` varchar(500) DEFAULT NULL,
  `content` longtext DEFAULT NULL,
  `type` enum('file','url','text','video','audio') NOT NULL,
  `order_number` int(11) DEFAULT 0,
  `is_public` tinyint(1) DEFAULT 1,
  `status` enum('active','inactive','draft') DEFAULT 'active',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `class_id` (`class_id`),
  KEY `category_id` (`category_id`),
  KEY `created_by` (`created_by`),
  KEY `status` (`status`),
  KEY `order_number` (`order_number`),
  CONSTRAINT `fk_materials_class` FOREIGN KEY (`class_id`) REFERENCES `teacher_classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_materials_category` FOREIGN KEY (`category_id`) REFERENCES `lms_material_categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_materials_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Material access logs
CREATE TABLE IF NOT EXISTS `lms_material_access_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `material_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `access_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `material_id` (`material_id`),
  KEY `student_id` (`student_id`),
  KEY `access_time` (`access_time`),
  CONSTRAINT `fk_material_logs_material` FOREIGN KEY (`material_id`) REFERENCES `lms_materials` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_material_logs_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- ASSIGNMENTS
-- =====================================================

-- Assignment categories
CREATE TABLE IF NOT EXISTS `lms_assignment_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `color` varchar(20) DEFAULT '#10B981',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `status` (`status`),
  CONSTRAINT `fk_assignment_categories_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Assignments
CREATE TABLE IF NOT EXISTS `lms_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `instructions` longtext DEFAULT NULL,
  `due_date` datetime NOT NULL,
  `max_score` int(11) DEFAULT 100,
  `allow_late_submission` tinyint(1) DEFAULT 0,
  `late_penalty` decimal(5,2) DEFAULT 0.00,
  `file_required` tinyint(1) DEFAULT 0,
  `max_file_size` int(11) DEFAULT 10485760, -- 10MB default
  `allowed_file_types` varchar(255) DEFAULT NULL,
  `status` enum('draft','published','closed') DEFAULT 'draft',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `class_id` (`class_id`),
  KEY `category_id` (`category_id`),
  KEY `created_by` (`created_by`),
  KEY `due_date` (`due_date`),
  KEY `status` (`status`),
  CONSTRAINT `fk_assignments_class` FOREIGN KEY (`class_id`) REFERENCES `teacher_classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assignments_category` FOREIGN KEY (`category_id`) REFERENCES `lms_assignment_categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assignments_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Assignment submissions
CREATE TABLE IF NOT EXISTS `lms_assignment_submissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `assignment_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `submission_text` longtext DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `score` decimal(5,2) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `graded_by` int(11) DEFAULT NULL,
  `graded_at` timestamp NULL DEFAULT NULL,
  `status` enum('submitted','late','graded','returned') DEFAULT 'submitted',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_submission` (`assignment_id`, `student_id`),
  KEY `assignment_id` (`assignment_id`),
  KEY `student_id` (`student_id`),
  KEY `graded_by` (`graded_by`),
  KEY `submitted_at` (`submitted_at`),
  KEY `status` (`status`),
  CONSTRAINT `fk_submissions_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `lms_assignments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_submissions_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_submissions_graded_by` FOREIGN KEY (`graded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- DISCUSSIONS
-- =====================================================

-- Discussion forums
CREATE TABLE IF NOT EXISTS `lms_discussions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `is_pinned` tinyint(1) DEFAULT 0,
  `is_locked` tinyint(1) DEFAULT 0,
  `allow_replies` tinyint(1) DEFAULT 1,
  `status` enum('active','inactive','archived') DEFAULT 'active',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `class_id` (`class_id`),
  KEY `created_by` (`created_by`),
  KEY `status` (`status`),
  KEY `is_pinned` (`is_pinned`),
  CONSTRAINT `fk_discussions_class` FOREIGN KEY (`class_id`) REFERENCES `teacher_classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_discussions_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Discussion posts
CREATE TABLE IF NOT EXISTS `lms_discussion_posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `discussion_id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `author_id` int(11) NOT NULL,
  `author_type` enum('student','teacher') NOT NULL,
  `content` longtext NOT NULL,
  `is_edited` tinyint(1) DEFAULT 0,
  `edited_at` timestamp NULL DEFAULT NULL,
  `edited_by` int(11) DEFAULT NULL,
  `is_pinned` tinyint(1) DEFAULT 0,
  `status` enum('active','hidden','deleted') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `discussion_id` (`discussion_id`),
  KEY `parent_id` (`parent_id`),
  KEY `author_id` (`author_id`),
  KEY `author_type` (`author_type`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `fk_posts_discussion` FOREIGN KEY (`discussion_id`) REFERENCES `lms_discussions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_posts_parent` FOREIGN KEY (`parent_id`) REFERENCES `lms_discussion_posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_posts_edited_by` FOREIGN KEY (`edited_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Post reactions
CREATE TABLE IF NOT EXISTS `lms_post_reactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_type` enum('student','teacher') NOT NULL,
  `reaction_type` enum('like','love','helpful','insightful') DEFAULT 'like',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_reaction` (`post_id`, `user_id`, `user_type`),
  KEY `post_id` (`post_id`),
  KEY `user_id` (`user_id`),
  KEY `reaction_type` (`reaction_type`),
  CONSTRAINT `fk_reactions_post` FOREIGN KEY (`post_id`) REFERENCES `lms_discussion_posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- GRADES AND PROGRESS
-- =====================================================

-- Grade categories
CREATE TABLE IF NOT EXISTS `lms_grade_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `weight` decimal(5,2) DEFAULT 0.00,
  `color` varchar(20) DEFAULT '#8B5CF6',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `class_id` (`class_id`),
  KEY `created_by` (`created_by`),
  KEY `status` (`status`),
  CONSTRAINT `fk_grade_categories_class` FOREIGN KEY (`class_id`) REFERENCES `teacher_classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_grade_categories_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Student grades
CREATE TABLE IF NOT EXISTS `lms_student_grades` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `assignment_id` int(11) DEFAULT NULL,
  `grade_type` enum('assignment','quiz','exam','participation','other') NOT NULL,
  `title` varchar(255) NOT NULL,
  `score` decimal(5,2) NOT NULL,
  `max_score` decimal(5,2) NOT NULL,
  `percentage` decimal(5,2) GENERATED ALWAYS AS ((score / max_score) * 100) STORED,
  `letter_grade` varchar(5) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `graded_by` int(11) NOT NULL,
  `graded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('draft','published','final') DEFAULT 'draft',
  PRIMARY KEY (`id`),
  KEY `class_id` (`class_id`),
  KEY `student_id` (`student_id`),
  KEY `category_id` (`category_id`),
  KEY `assignment_id` (`assignment_id`),
  KEY `graded_by` (`graded_by`),
  KEY `status` (`status`),
  KEY `graded_at` (`graded_at`),
  CONSTRAINT `fk_grades_class` FOREIGN KEY (`class_id`) REFERENCES `teacher_classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_grades_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_grades_category` FOREIGN KEY (`category_id`) REFERENCES `lms_grade_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_grades_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `lms_assignments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_grades_graded_by` FOREIGN KEY (`graded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Progress tracking
CREATE TABLE IF NOT EXISTS `lms_student_progress` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `material_id` int(11) DEFAULT NULL,
  `assignment_id` int(11) DEFAULT NULL,
  `discussion_id` int(11) DEFAULT NULL,
  `activity_type` enum('material_view','assignment_submit','discussion_post','grade_received') NOT NULL,
  `progress_percentage` decimal(5,2) DEFAULT 0.00,
  `time_spent` int(11) DEFAULT 0, -- in seconds
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `class_id` (`class_id`),
  KEY `student_id` (`student_id`),
  KEY `material_id` (`material_id`),
  KEY `assignment_id` (`assignment_id`),
  KEY `discussion_id` (`discussion_id`),
  KEY `activity_type` (`activity_type`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `fk_progress_class` FOREIGN KEY (`class_id`) REFERENCES `teacher_classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_progress_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_progress_material` FOREIGN KEY (`material_id`) REFERENCES `lms_materials` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_progress_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `lms_assignments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_progress_discussion` FOREIGN KEY (`discussion_id`) REFERENCES `lms_discussions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- RESOURCES
-- =====================================================

-- Resource categories
CREATE TABLE IF NOT EXISTS `lms_resource_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT 'fas fa-link',
  `color` varchar(20) DEFAULT '#F59E0B',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `status` (`status`),
  CONSTRAINT `fk_resource_categories_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Resources
CREATE TABLE IF NOT EXISTS `lms_resources` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `url` varchar(500) NOT NULL,
  `resource_type` enum('website','video','document','tool','other') NOT NULL,
  `is_external` tinyint(1) DEFAULT 1,
  `is_featured` tinyint(1) DEFAULT 0,
  `order_number` int(11) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `class_id` (`class_id`),
  KEY `category_id` (`category_id`),
  KEY `created_by` (`created_by`),
  KEY `status` (`status`),
  KEY `order_number` (`order_number`),
  CONSTRAINT `fk_resources_class` FOREIGN KEY (`class_id`) REFERENCES `teacher_classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_resources_category` FOREIGN KEY (`category_id`) REFERENCES `lms_resource_categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_resources_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- NOTIFICATIONS
-- =====================================================

-- LMS notifications
CREATE TABLE IF NOT EXISTS `lms_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `recipient_type` enum('student','teacher') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','error','assignment','grade','discussion') DEFAULT 'info',
  `related_id` int(11) DEFAULT NULL,
  `related_type` varchar(50) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `class_id` (`class_id`),
  KEY `recipient_id` (`recipient_id`),
  KEY `recipient_type` (`recipient_type`),
  KEY `type` (`type`),
  KEY `is_read` (`is_read`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `fk_notifications_class` FOREIGN KEY (`class_id`) REFERENCES `teacher_classes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SAMPLE DATA
-- =====================================================

-- Insert sample material categories
INSERT INTO `lms_material_categories` (`name`, `description`, `icon`, `color`, `created_by`) VALUES
('Syllabus', 'Course syllabus and overview', 'fas fa-file-alt', '#3B82F6', 1),
('Lectures', 'Lecture notes and presentations', 'fas fa-chalkboard-teacher', '#10B981', 1),
('Readings', 'Required and optional readings', 'fas fa-book', '#F59E0B', 1),
('Videos', 'Video lectures and tutorials', 'fas fa-video', '#EF4444', 1),
('Assignments', 'Assignment instructions and resources', 'fas fa-tasks', '#8B5CF6', 1);

-- Insert sample assignment categories
INSERT INTO `lms_assignment_categories` (`name`, `description`, `color`, `created_by`) VALUES
('Homework', 'Regular homework assignments', '#10B981', 1),
('Projects', 'Major projects and research', '#8B5CF6', 1),
('Quizzes', 'Short quizzes and tests', '#F59E0B', 1),
('Presentations', 'Oral presentations and reports', '#EF4444', 1),
('Participation', 'Class participation and engagement', '#6B7280', 1);

-- Insert sample grade categories
INSERT INTO `lms_grade_categories` (`name`, `description`, `weight`, `color`, `created_by`) VALUES
('Assignments', 'Homework and projects', 30.00, '#8B5CF6', 1),
('Quizzes', 'Short quizzes and tests', 20.00, '#F59E0B', 1),
('Midterm Exam', 'Midterm examination', 25.00, '#EF4444', 1),
('Final Exam', 'Final examination', 25.00, '#EF4444', 1);

-- Insert sample resource categories
INSERT INTO `lms_resource_categories` (`name`, `description`, `icon`, `color`, `created_by`) VALUES
('Textbooks', 'Required and recommended textbooks', 'fas fa-book', '#F59E0B', 1),
('Online Tools', 'Useful online tools and software', 'fas fa-tools', '#10B981', 1),
('Research Databases', 'Academic databases and journals', 'fas fa-database', '#3B82F6', 1),
('Tutorials', 'Step-by-step tutorials and guides', 'fas fa-graduation-cap', '#8B5CF6', 1),
('External Links', 'Additional resources and references', 'fas fa-external-link-alt', '#6B7280', 1);

-- =====================================================
-- INDEXES FOR PERFORMANCE
-- =====================================================

-- Material indexes
CREATE INDEX idx_materials_class_status ON lms_materials(class_id, status);
CREATE INDEX idx_materials_category_order ON lms_materials(category_id, order_number);
CREATE INDEX idx_material_access_student ON lms_material_access_logs(student_id, access_time);

-- Assignment indexes
CREATE INDEX idx_assignments_class_status ON lms_assignments(class_id, status);
CREATE INDEX idx_assignments_due_date ON lms_assignments(due_date);
CREATE INDEX idx_submissions_assignment_student ON lms_assignment_submissions(assignment_id, student_id);
CREATE INDEX idx_submissions_status ON lms_assignment_submissions(status);

-- Discussion indexes
CREATE INDEX idx_discussions_class_status ON lms_discussions(class_id, status);
CREATE INDEX idx_posts_discussion_parent ON lms_discussion_posts(discussion_id, parent_id);
CREATE INDEX idx_posts_author ON lms_discussion_posts(author_id, author_type);
CREATE INDEX idx_reactions_post_type ON lms_post_reactions(post_id, reaction_type);

-- Grade indexes
CREATE INDEX idx_grades_class_student ON lms_student_grades(class_id, student_id);
CREATE INDEX idx_grades_category ON lms_student_grades(category_id);
CREATE INDEX idx_grades_status ON lms_student_grades(status);

-- Progress indexes
CREATE INDEX idx_progress_class_student ON lms_student_progress(class_id, student_id);
CREATE INDEX idx_progress_activity ON lms_student_progress(activity_type, created_at);

-- Resource indexes
CREATE INDEX idx_resources_class_status ON lms_resources(class_id, status);
CREATE INDEX idx_resources_category_order ON lms_resources(category_id, order_number);

-- Notification indexes
CREATE INDEX idx_notifications_recipient ON lms_notifications(recipient_id, recipient_type);
CREATE INDEX idx_notifications_unread ON lms_notifications(is_read, created_at);

-- =====================================================
-- VIEWS FOR COMMON QUERIES
-- =====================================================

-- View for class materials with access counts
CREATE VIEW `lms_materials_view` AS
SELECT 
    m.*,
    mc.name as category_name,
    mc.icon as category_icon,
    mc.color as category_color,
    COUNT(ml.id) as access_count,
    u.first_name as created_by_name,
    u.last_name as created_by_last_name
FROM lms_materials m
JOIN lms_material_categories mc ON m.category_id = mc.id
JOIN users u ON m.created_by = u.id
LEFT JOIN lms_material_access_logs ml ON m.id = ml.material_id
WHERE m.status = 'active'
GROUP BY m.id;

-- View for assignment submissions with grades
CREATE VIEW `lms_assignments_view` AS
SELECT 
    a.*,
    ac.name as category_name,
    ac.color as category_color,
    COUNT(s.id) as submission_count,
    COUNT(CASE WHEN s.status = 'graded' THEN 1 END) as graded_count,
    u.first_name as created_by_name,
    u.last_name as created_by_last_name
FROM lms_assignments a
JOIN lms_assignment_categories ac ON a.category_id = ac.id
JOIN users u ON a.created_by = u.id
LEFT JOIN lms_assignment_submissions s ON a.id = s.assignment_id
WHERE a.status != 'draft'
GROUP BY a.id;

-- View for student grades summary
CREATE VIEW `lms_student_grades_summary` AS
SELECT 
    sg.class_id,
    sg.student_id,
    s.first_name,
    s.last_name,
    s.student_id as student_number,
    gc.name as category_name,
    gc.weight,
    COUNT(sg.id) as grade_count,
    AVG(sg.percentage) as average_percentage,
    SUM(sg.score) as total_score,
    SUM(sg.max_score) as total_max_score
FROM lms_student_grades sg
JOIN students s ON sg.student_id = s.id
JOIN lms_grade_categories gc ON sg.category_id = gc.id
WHERE sg.status = 'published'
GROUP BY sg.class_id, sg.student_id, gc.id;

-- View for discussion activity
CREATE VIEW `lms_discussion_activity` AS
SELECT 
    d.*,
    COUNT(p.id) as post_count,
    COUNT(DISTINCT p.author_id) as participant_count,
    MAX(p.created_at) as last_activity,
    u.first_name as created_by_name,
    u.last_name as created_by_last_name
FROM lms_discussions d
JOIN users u ON d.created_by = u.id
LEFT JOIN lms_discussion_posts p ON d.id = p.discussion_id AND p.status = 'active'
WHERE d.status = 'active'
GROUP BY d.id; 