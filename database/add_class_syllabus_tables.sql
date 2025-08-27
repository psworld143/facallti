-- Add Class Syllabus Tables
-- This script creates the necessary tables for the class syllabus functionality

-- Class Syllabus Table
CREATE TABLE IF NOT EXISTS `class_syllabus` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `course_objectives` text,
  `learning_outcomes` text,
  `prerequisites` text,
  `course_requirements` text,
  `grading_system` text,
  `course_policies` text,
  `academic_integrity` text,
  `attendance_policy` text,
  `late_submission_policy` text,
  `office_hours` text,
  `contact_information` text,
  `textbooks` text,
  `course_references` text,
  `schedule` text,
  `assessment_methods` text,
  `course_units` varchar(10) DEFAULT NULL,
  `course_credits` varchar(10) DEFAULT NULL,
  `semester` varchar(50) DEFAULT NULL,
  `academic_year` varchar(20) DEFAULT NULL,
  `class_schedule` text,
  `classroom_location` varchar(255) DEFAULT NULL,
  `course_website` varchar(500) DEFAULT NULL,
  `emergency_contact` text,
  `disability_accommodations` text,
  `is_published` tinyint(1) DEFAULT 0,
  `published_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `class_id` (`class_id`),
  KEY `teacher_id` (`teacher_id`),
  CONSTRAINT `class_syllabus_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `teacher_classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `class_syllabus_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Program Educational Objectives (PEOs) Table
CREATE TABLE IF NOT EXISTS `syllabus_peos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `syllabus_id` int(11) NOT NULL,
  `peo_code` varchar(10) NOT NULL,
  `peo_description` text NOT NULL,
  `aligned_to_mission` tinyint(1) DEFAULT 0,
  `order_number` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `syllabus_id` (`syllabus_id`),
  CONSTRAINT `syllabus_peos_ibfk_1` FOREIGN KEY (`syllabus_id`) REFERENCES `class_syllabus` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Program Outcomes (POs) Table
CREATE TABLE IF NOT EXISTS `syllabus_pos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `syllabus_id` int(11) NOT NULL,
  `po_code` varchar(10) NOT NULL,
  `po_description` text NOT NULL,
  `order_number` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `syllabus_id` (`syllabus_id`),
  CONSTRAINT `syllabus_pos_ibfk_1` FOREIGN KEY (`syllabus_id`) REFERENCES `class_syllabus` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PEO-PO Alignment Table
CREATE TABLE IF NOT EXISTS `syllabus_peo_po_alignment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `syllabus_id` int(11) NOT NULL,
  `peo_id` int(11) NOT NULL,
  `po_id` int(11) NOT NULL,
  `is_aligned` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_peo_po` (`syllabus_id`, `peo_id`, `po_id`),
  KEY `peo_id` (`peo_id`),
  KEY `po_id` (`po_id`),
  CONSTRAINT `syllabus_peo_po_alignment_ibfk_1` FOREIGN KEY (`syllabus_id`) REFERENCES `class_syllabus` (`id`) ON DELETE CASCADE,
  CONSTRAINT `syllabus_peo_po_alignment_ibfk_2` FOREIGN KEY (`peo_id`) REFERENCES `syllabus_peos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `syllabus_peo_po_alignment_ibfk_3` FOREIGN KEY (`po_id`) REFERENCES `syllabus_pos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Course Learning Outcomes (CLOs) Table
CREATE TABLE IF NOT EXISTS `syllabus_clos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `syllabus_id` int(11) NOT NULL,
  `clo_code` varchar(10) NOT NULL,
  `clo_description` text NOT NULL,
  `order_number` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `syllabus_id` (`syllabus_id`),
  CONSTRAINT `syllabus_clos_ibfk_1` FOREIGN KEY (`syllabus_id`) REFERENCES `class_syllabus` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- CLO-PO Alignment Table
CREATE TABLE IF NOT EXISTS `syllabus_clo_po_alignment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `syllabus_id` int(11) NOT NULL,
  `clo_id` int(11) NOT NULL,
  `po_id` int(11) NOT NULL,
  `is_aligned` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_clo_po` (`syllabus_id`, `clo_id`, `po_id`),
  KEY `clo_id` (`clo_id`),
  KEY `po_id` (`po_id`),
  CONSTRAINT `syllabus_clo_po_alignment_ibfk_1` FOREIGN KEY (`syllabus_id`) REFERENCES `class_syllabus` (`id`) ON DELETE CASCADE,
  CONSTRAINT `syllabus_clo_po_alignment_ibfk_2` FOREIGN KEY (`clo_id`) REFERENCES `syllabus_clos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `syllabus_clo_po_alignment_ibfk_3` FOREIGN KEY (`po_id`) REFERENCES `syllabus_pos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Syllabus Topics Table (Enhanced)
CREATE TABLE IF NOT EXISTS `syllabus_topics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `syllabus_id` int(11) NOT NULL,
  `week_number` int(11) DEFAULT NULL,
  `order_number` int(11) DEFAULT 0,
  `topic_title` varchar(255) NOT NULL,
  `description` text,
  `learning_objectives` text,
  `materials` text,
  `activities` text,
  `assessment` text,
  `values_integration` text,
  `target` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `syllabus_id` (`syllabus_id`),
  CONSTRAINT `syllabus_topics_ibfk_1` FOREIGN KEY (`syllabus_id`) REFERENCES `class_syllabus` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Topic-CLO Alignment Table
CREATE TABLE IF NOT EXISTS `syllabus_topic_clo_alignment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `syllabus_id` int(11) NOT NULL,
  `topic_id` int(11) NOT NULL,
  `clo_id` int(11) NOT NULL,
  `is_aligned` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_topic_clo` (`syllabus_id`, `topic_id`, `clo_id`),
  KEY `topic_id` (`topic_id`),
  KEY `clo_id` (`clo_id`),
  CONSTRAINT `syllabus_topic_clo_alignment_ibfk_1` FOREIGN KEY (`syllabus_id`) REFERENCES `class_syllabus` (`id`) ON DELETE CASCADE,
  CONSTRAINT `syllabus_topic_clo_alignment_ibfk_2` FOREIGN KEY (`topic_id`) REFERENCES `syllabus_topics` (`id`) ON DELETE CASCADE,
  CONSTRAINT `syllabus_topic_clo_alignment_ibfk_3` FOREIGN KEY (`clo_id`) REFERENCES `syllabus_clos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Syllabus Files Table
CREATE TABLE IF NOT EXISTS `syllabus_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `syllabus_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `description` text,
  `uploaded_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `syllabus_id` (`syllabus_id`),
  CONSTRAINT `syllabus_files_ibfk_1` FOREIGN KEY (`syllabus_id`) REFERENCES `class_syllabus` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
