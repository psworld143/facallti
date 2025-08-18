-- IntelliEVal System Backup
-- Generated: 2025-08-18 12:10:59
-- Backup Type: Evaluations
-- Include Data: Yes

SET FOREIGN_KEY_CHECKS = 0;

-- Table structure for table `main_evaluation_categories`
DROP TABLE IF EXISTS `main_evaluation_categories`;
CREATE TABLE `main_evaluation_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `evaluation_type` enum('student_to_teacher','peer_to_peer','head_to_teacher') NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `status` (`status`),
  KEY `idx_main_evaluation_categories_type_status` (`evaluation_type`,`status`),
  CONSTRAINT `fk_main_evaluation_categories_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for table `main_evaluation_categories`
INSERT INTO `main_evaluation_categories` (`id`, `name`, `description`, `evaluation_type`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('1', 'Student to Teacher Evaluation', 'Students evaluate their teachers on various aspects of teaching and classroom management', 'student_to_teacher', 'active', '1', '2025-08-10 23:14:16', NULL);
INSERT INTO `main_evaluation_categories` (`id`, `name`, `description`, `evaluation_type`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('3', 'Head to Teacher Evaluation', 'Department heads and administrators evaluate teachers on leadership and administrative skills', 'head_to_teacher', 'active', '1', '2025-08-10 23:14:16', NULL);
INSERT INTO `main_evaluation_categories` (`id`, `name`, `description`, `evaluation_type`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('5', 'Peer to Peer Evaluation', 'Teachers evaluate their colleagues on professional competence and collaboration', 'peer_to_peer', 'active', '1', '2025-08-14 18:47:27', NULL);

-- Table structure for table `evaluation_sub_categories`
DROP TABLE IF EXISTS `evaluation_sub_categories`;
CREATE TABLE `evaluation_sub_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `main_category_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `order_number` int(11) DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `main_category_id` (`main_category_id`),
  KEY `created_by` (`created_by`),
  KEY `status` (`status`),
  KEY `order_number` (`order_number`),
  KEY `idx_evaluation_sub_categories_main_order` (`main_category_id`,`order_number`,`status`),
  CONSTRAINT `fk_evaluation_sub_categories_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_evaluation_sub_categories_main` FOREIGN KEY (`main_category_id`) REFERENCES `main_evaluation_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for table `evaluation_sub_categories`
INSERT INTO `evaluation_sub_categories` (`id`, `main_category_id`, `name`, `description`, `status`, `order_number`, `created_by`, `created_at`, `updated_at`) VALUES ('1', '1', 'Classroom Management', 'Evaluation of teacher\'s ability to maintain order and create a conducive learning environment', 'active', '1', '1', '2025-08-10 23:14:16', NULL);
INSERT INTO `evaluation_sub_categories` (`id`, `main_category_id`, `name`, `description`, `status`, `order_number`, `created_by`, `created_at`, `updated_at`) VALUES ('2', '1', 'Teaching Skills', 'Assessment of teacher\'s instructional methods and delivery', 'active', '2', '1', '2025-08-10 23:14:16', NULL);
INSERT INTO `evaluation_sub_categories` (`id`, `main_category_id`, `name`, `description`, `status`, `order_number`, `created_by`, `created_at`, `updated_at`) VALUES ('3', '1', 'Subject Knowledge', 'Evaluation of teacher\'s mastery of the subject matter', 'active', '3', '1', '2025-08-10 23:14:16', NULL);
INSERT INTO `evaluation_sub_categories` (`id`, `main_category_id`, `name`, `description`, `status`, `order_number`, `created_by`, `created_at`, `updated_at`) VALUES ('4', '1', 'Communication Skills', 'Assessment of teacher\'s ability to communicate effectively with students', 'active', '4', '1', '2025-08-10 23:14:16', NULL);
INSERT INTO `evaluation_sub_categories` (`id`, `main_category_id`, `name`, `description`, `status`, `order_number`, `created_by`, `created_at`, `updated_at`) VALUES ('5', '1', 'Student Engagement', 'Evaluation of how well the teacher engages students in learning', 'active', '5', '1', '2025-08-10 23:14:16', NULL);
INSERT INTO `evaluation_sub_categories` (`id`, `main_category_id`, `name`, `description`, `status`, `order_number`, `created_by`, `created_at`, `updated_at`) VALUES ('10', '3', 'Leadership', 'Assessment of leadership qualities and initiative', 'active', '1', '1', '2025-08-10 23:14:16', NULL);
INSERT INTO `evaluation_sub_categories` (`id`, `main_category_id`, `name`, `description`, `status`, `order_number`, `created_by`, `created_at`, `updated_at`) VALUES ('11', '3', 'Administrative Skills', 'Evaluation of administrative and organizational skills', 'active', '2', '1', '2025-08-10 23:14:16', NULL);
INSERT INTO `evaluation_sub_categories` (`id`, `main_category_id`, `name`, `description`, `status`, `order_number`, `created_by`, `created_at`, `updated_at`) VALUES ('12', '3', 'Professional Development', 'Assessment of continuous learning and growth', 'active', '3', '1', '2025-08-10 23:14:16', NULL);
INSERT INTO `evaluation_sub_categories` (`id`, `main_category_id`, `name`, `description`, `status`, `order_number`, `created_by`, `created_at`, `updated_at`) VALUES ('13', '3', 'Compliance', 'Evaluation of adherence to policies and procedures', 'active', '4', '1', '2025-08-10 23:14:16', NULL);
INSERT INTO `evaluation_sub_categories` (`id`, `main_category_id`, `name`, `description`, `status`, `order_number`, `created_by`, `created_at`, `updated_at`) VALUES ('18', '5', 'Professional Competence', 'Evaluation of colleague\'s professional skills and knowledge', 'active', '1', '1', '2025-08-14 18:47:27', NULL);
INSERT INTO `evaluation_sub_categories` (`id`, `main_category_id`, `name`, `description`, `status`, `order_number`, `created_by`, `created_at`, `updated_at`) VALUES ('19', '5', 'Collaboration', 'Assessment of teamwork and cooperation with colleagues', 'active', '2', '1', '2025-08-14 18:47:27', NULL);
INSERT INTO `evaluation_sub_categories` (`id`, `main_category_id`, `name`, `description`, `status`, `order_number`, `created_by`, `created_at`, `updated_at`) VALUES ('20', '5', 'Innovation', 'Evaluation of teaching innovations and creativity', 'active', '3', '1', '2025-08-14 18:47:27', NULL);
INSERT INTO `evaluation_sub_categories` (`id`, `main_category_id`, `name`, `description`, `status`, `order_number`, `created_by`, `created_at`, `updated_at`) VALUES ('21', '5', 'Mentoring', 'Assessment of ability to mentor and support other teachers', 'active', '4', '1', '2025-08-14 18:47:27', NULL);

-- Table structure for table `evaluation_questionnaires`
DROP TABLE IF EXISTS `evaluation_questionnaires`;
CREATE TABLE `evaluation_questionnaires` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sub_category_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `question_type` enum('rating_1_5','text','yes_no','multiple_choice') NOT NULL DEFAULT 'rating_1_5',
  `rating_labels` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Custom labels for 1-5 scale' CHECK (json_valid(`rating_labels`)),
  `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'For multiple choice questions' CHECK (json_valid(`options`)),
  `required` tinyint(1) DEFAULT 1,
  `order_number` int(11) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `sub_category_id` (`sub_category_id`),
  KEY `created_by` (`created_by`),
  KEY `status` (`status`),
  KEY `order_number` (`order_number`),
  KEY `idx_evaluation_questionnaires_sub_order` (`sub_category_id`,`order_number`,`status`),
  CONSTRAINT `fk_evaluation_questionnaires_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_evaluation_questionnaires_sub_category` FOREIGN KEY (`sub_category_id`) REFERENCES `evaluation_sub_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=155 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for table `evaluation_questionnaires`
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('1', '1', 'How well does the teacher maintain classroom discipline?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '1', 'active', '1', '2025-08-10 23:14:16', '2025-08-13 23:33:34');
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('2', '1', 'How organized is the classroom environment?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '2', 'active', '1', '2025-08-10 23:14:16', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('6', '2', 'How clear and understandable are the teacher\'s explanations?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '1', 'active', '1', '2025-08-10 23:14:16', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('7', '2', 'How well does the teacher use different teaching methods?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '2', 'active', '1', '2025-08-10 23:14:16', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('11', '5', 'How well does the colleague demonstrate subject matter expertise?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '1', 'active', '1', '2025-08-10 23:14:16', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('12', '5', 'How effectively does the colleague plan and organize lessons?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '2', 'active', '1', '2025-08-10 23:14:16', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('19', '1', 'How well does the teacher maintain classroom discipline?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '1', 'active', '1', '2025-08-12 15:34:33', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('20', '1', 'Does the teacher start and end classes on time?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '2', 'active', '1', '2025-08-12 15:34:33', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('26', '2', 'How clear and understandable are the teacher\'s explanations?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '1', 'active', '1', '2025-08-12 15:34:33', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('27', '2', 'Does the teacher use effective teaching methods and strategies?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '2', 'active', '1', '2025-08-12 15:34:33', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('34', '3', 'How well does the teacher demonstrate mastery of the subject matter?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '1', 'active', '1', '2025-08-12 15:34:33', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('35', '3', 'Does the teacher provide accurate and up-to-date information?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '2', 'active', '1', '2025-08-12 15:34:33', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('36', '3', 'How well does the teacher connect concepts to real-world applications?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '3', 'active', '1', '2025-08-12 15:34:33', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('37', '3', 'Does the teacher answer student questions accurately and thoroughly?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '4', 'active', '1', '2025-08-12 15:34:33', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('41', '4', 'How clear and audible is the teacher\'s voice?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '1', 'active', '1', '2025-08-12 15:34:33', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('42', '4', 'Does the teacher speak at an appropriate pace for students to follow?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '2', 'active', '1', '2025-08-12 15:34:33', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('43', '4', 'How well does the teacher use body language and gestures?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '3', 'active', '1', '2025-08-12 15:34:33', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('44', '4', 'Does the teacher listen actively to student questions and concerns?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '4', 'active', '1', '2025-08-12 15:34:33', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('49', '5', 'How well does the teacher motivate students to participate?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '1', 'active', '1', '2025-08-12 15:34:33', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('50', '5', 'Does the teacher create interesting and engaging lessons?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '2', 'active', '1', '2025-08-12 15:34:33', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('89', '10', 'How well does the teacher demonstrate leadership qualities?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '1', 'active', '1', '2025-08-12 15:34:33', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('90', '10', 'Does the teacher take initiative in school improvement projects?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '2', 'active', '1', '2025-08-12 15:34:33', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('91', '10', 'How well does the teacher inspire and motivate other staff members?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '3', 'active', '1', '2025-08-12 15:34:33', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('92', '10', 'Does the teacher demonstrate vision and strategic thinking?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '4', 'active', '1', '2025-08-12 15:34:33', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('97', '11', 'How well does the teacher manage administrative tasks and paperwork?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '1', 'active', '1', '2025-08-12 15:34:33', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('98', '11', 'Does the teacher submit reports and documents on time?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '2', 'active', '1', '2025-08-12 15:34:33', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('99', '11', 'How well does the teacher organize and maintain records?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '3', 'active', '1', '2025-08-12 15:34:33', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('100', '11', 'Does the teacher follow administrative procedures correctly?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '4', 'active', '1', '2025-08-12 15:34:33', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('105', '12', 'How committed is the teacher to continuous professional learning?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '1', 'active', '1', '2025-08-12 15:34:33', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('106', '12', 'Does the teacher actively participate in training and workshops?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '2', 'active', '1', '2025-08-12 15:34:33', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('107', '12', 'How well does the teacher apply new learning to their practice?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '3', 'active', '1', '2025-08-12 15:34:33', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('108', '12', 'Does the teacher seek feedback and reflect on their teaching?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '4', 'active', '1', '2025-08-12 15:34:33', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('113', '13', 'How well does the teacher follow school policies and procedures?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '1', 'active', '1', '2025-08-12 15:34:33', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('114', '13', 'Does the teacher comply with curriculum standards and requirements?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '2', 'active', '1', '2025-08-12 15:34:33', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('115', '13', 'How well does the teacher adhere to safety and security protocols?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '3', 'active', '1', '2025-08-12 15:34:33', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('116', '13', 'Does the teacher follow ethical guidelines and professional standards?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '4', 'active', '1', '2025-08-12 15:34:33', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('121', '1', 'What specific issues have you observed with classroom discipline?', 'text', NULL, NULL, '0', '5', 'active', '1', '2025-08-14 13:28:27', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('122', '1', 'How does the teacher handle disruptive students?', 'text', NULL, NULL, '0', '6', 'active', '1', '2025-08-14 13:28:27', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('123', '2', 'What teaching methods do you find most ineffective?', 'text', NULL, NULL, '0', '5', 'active', '1', '2025-08-14 13:28:27', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('124', '2', 'How could the teacher improve their lesson delivery?', 'text', NULL, NULL, '0', '6', 'active', '1', '2025-08-14 13:28:27', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('125', '3', 'What topics does the teacher struggle to explain clearly?', 'text', NULL, NULL, '0', '5', 'active', '1', '2025-08-14 13:28:27', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('126', '3', 'What mistakes have you noticed in the teacher\'s subject knowledge?', 'text', NULL, NULL, '0', '6', 'active', '1', '2025-08-14 13:28:27', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('127', '4', 'What communication problems have you experienced with this teacher?', 'text', NULL, NULL, '0', '5', 'active', '1', '2025-08-14 13:28:27', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('128', '4', 'How does the teacher respond to student questions?', 'text', NULL, NULL, '0', '6', 'active', '1', '2025-08-14 13:28:27', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('129', '5', 'What makes the class boring or unengaging?', 'text', NULL, NULL, '0', '5', 'active', '1', '2025-08-14 13:28:27', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('130', '5', 'How could the teacher better motivate students?', 'text', NULL, NULL, '0', '6', 'active', '1', '2025-08-14 13:28:27', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('131', '18', 'How well does the colleague demonstrate subject matter expertise?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '1', 'active', '1', '2025-08-14 18:47:27', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('132', '18', 'How effectively does the colleague plan and organize lessons?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '2', 'active', '1', '2025-08-14 18:47:27', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('133', '18', 'How well does the colleague assess student learning?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '3', 'active', '1', '2025-08-14 18:47:27', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('134', '18', 'How committed is the colleague to professional development?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '4', 'active', '1', '2025-08-14 18:47:27', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('135', '18', 'How well does the colleague stay updated with current educational trends?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '5', 'active', '1', '2025-08-14 18:47:27', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('136', '19', 'How well does the colleague work with other faculty members?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '1', 'active', '1', '2025-08-14 18:47:27', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('137', '19', 'How effectively does the colleague share resources and materials?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '2', 'active', '1', '2025-08-14 18:47:27', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('138', '19', 'How well does the colleague participate in department meetings?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '3', 'active', '1', '2025-08-14 18:47:27', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('139', '19', 'How supportive is the colleague in team projects?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '4', 'active', '1', '2025-08-14 18:47:27', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('140', '19', 'How well does the colleague communicate with other staff members?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '5', 'active', '1', '2025-08-14 18:47:27', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('141', '20', 'How creative is the colleague in developing teaching methods?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '1', 'active', '1', '2025-08-14 18:47:27', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('142', '20', 'How well does the colleague incorporate new technologies in teaching?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '2', 'active', '1', '2025-08-14 18:47:27', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('143', '20', 'How innovative is the colleague in curriculum development?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '3', 'active', '1', '2025-08-14 18:47:27', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('144', '20', 'How well does the colleague adapt to new educational approaches?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '4', 'active', '1', '2025-08-14 18:47:27', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('145', '20', 'How effectively does the colleague implement new ideas in the classroom?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '5', 'active', '1', '2025-08-14 18:47:27', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('146', '21', 'How well does the colleague mentor new faculty members?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '1', 'active', '1', '2025-08-14 18:47:27', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('147', '21', 'How effectively does the colleague provide guidance to colleagues?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '2', 'active', '1', '2025-08-14 18:47:27', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('148', '21', 'How supportive is the colleague in professional development?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '3', 'active', '1', '2025-08-14 18:47:27', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('149', '21', 'How well does the colleague share knowledge and expertise?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '4', 'active', '1', '2025-08-14 18:47:27', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('150', '21', 'How approachable is the colleague for advice and consultation?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, '1', '5', 'active', '1', '2025-08-14 18:47:27', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('151', '18', 'What specific areas of professional development would you recommend for this colleague?', 'text', NULL, NULL, '0', '6', 'active', '1', '2025-08-15 01:22:44', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('152', '19', 'How could this colleague improve their collaboration with the team?', 'text', NULL, NULL, '0', '6', 'active', '1', '2025-08-15 01:22:49', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('153', '20', 'What innovative teaching approaches have you observed from this colleague?', 'text', NULL, NULL, '0', '6', 'active', '1', '2025-08-15 01:22:55', NULL);
INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('154', '21', 'What specific mentoring strengths does this colleague demonstrate?', 'text', NULL, NULL, '0', '6', 'active', '1', '2025-08-15 01:23:04', NULL);

-- Table structure for table `evaluation_sessions`
DROP TABLE IF EXISTS `evaluation_sessions`;
CREATE TABLE `evaluation_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `evaluator_id` int(11) NOT NULL COMMENT 'ID from either users or faculty table',
  `evaluator_type` enum('student','teacher','head') NOT NULL,
  `evaluatee_id` int(11) NOT NULL COMMENT 'ID from either users or faculty table',
  `evaluatee_type` enum('teacher','student','head') NOT NULL,
  `main_category_id` int(11) NOT NULL,
  `semester_id` int(11) DEFAULT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `evaluation_date` date NOT NULL,
  `status` enum('draft','completed','archived','cancelled') DEFAULT 'draft',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `evaluator_id` (`evaluator_id`),
  KEY `evaluatee_id` (`evaluatee_id`),
  KEY `main_category_id` (`main_category_id`),
  KEY `semester_id` (`semester_id`),
  KEY `subject_id` (`subject_id`),
  KEY `status` (`status`),
  KEY `evaluation_date` (`evaluation_date`),
  KEY `idx_evaluation_sessions_evaluator_type` (`evaluator_id`,`evaluator_type`),
  KEY `idx_evaluation_sessions_evaluatee_type` (`evaluatee_id`,`evaluatee_type`),
  KEY `idx_evaluation_sessions_main_category_status` (`main_category_id`,`status`),
  KEY `idx_evaluation_sessions_evaluator` (`evaluator_id`,`evaluator_type`),
  KEY `idx_evaluation_sessions_evaluatee` (`evaluatee_id`,`evaluatee_type`),
  KEY `idx_evaluation_sessions_status` (`status`),
  KEY `idx_evaluation_sessions_date` (`evaluation_date`),
  CONSTRAINT `fk_evaluation_sessions_main_category` FOREIGN KEY (`main_category_id`) REFERENCES `main_evaluation_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=337 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for table `evaluation_sessions`
INSERT INTO `evaluation_sessions` (`id`, `evaluator_id`, `evaluator_type`, `evaluatee_id`, `evaluatee_type`, `main_category_id`, `semester_id`, `subject_id`, `evaluation_date`, `status`, `notes`, `created_at`, `updated_at`) VALUES ('328', '1', 'student', '2', 'teacher', '1', '1', NULL, '2025-08-14', 'draft', '', '2025-08-14 23:37:11', NULL);
INSERT INTO `evaluation_sessions` (`id`, `evaluator_id`, `evaluator_type`, `evaluatee_id`, `evaluatee_type`, `main_category_id`, `semester_id`, `subject_id`, `evaluation_date`, `status`, `notes`, `created_at`, `updated_at`) VALUES ('329', '4', 'student', '2', 'teacher', '1', '1', NULL, '2025-08-14', 'completed', '', '2025-08-14 23:37:11', '2025-08-15 00:23:28');
INSERT INTO `evaluation_sessions` (`id`, `evaluator_id`, `evaluator_type`, `evaluatee_id`, `evaluatee_type`, `main_category_id`, `semester_id`, `subject_id`, `evaluation_date`, `status`, `notes`, `created_at`, `updated_at`) VALUES ('330', '9', 'teacher', '2', 'teacher', '5', '1', NULL, '2025-08-14', 'completed', '', '2025-08-14 23:53:06', '2025-08-15 01:25:20');
INSERT INTO `evaluation_sessions` (`id`, `evaluator_id`, `evaluator_type`, `evaluatee_id`, `evaluatee_type`, `main_category_id`, `semester_id`, `subject_id`, `evaluation_date`, `status`, `notes`, `created_at`, `updated_at`) VALUES ('331', '7', 'teacher', '3', 'teacher', '5', '1', NULL, '2025-08-14', 'draft', '', '2025-08-14 23:53:06', NULL);
INSERT INTO `evaluation_sessions` (`id`, `evaluator_id`, `evaluator_type`, `evaluatee_id`, `evaluatee_type`, `main_category_id`, `semester_id`, `subject_id`, `evaluation_date`, `status`, `notes`, `created_at`, `updated_at`) VALUES ('332', '3', 'teacher', '7', 'teacher', '5', '1', NULL, '2025-08-14', 'draft', '', '2025-08-14 23:53:06', NULL);
INSERT INTO `evaluation_sessions` (`id`, `evaluator_id`, `evaluator_type`, `evaluatee_id`, `evaluatee_type`, `main_category_id`, `semester_id`, `subject_id`, `evaluation_date`, `status`, `notes`, `created_at`, `updated_at`) VALUES ('333', '2', 'teacher', '9', 'teacher', '5', '1', NULL, '2025-08-14', 'draft', '', '2025-08-14 23:53:06', NULL);
INSERT INTO `evaluation_sessions` (`id`, `evaluator_id`, `evaluator_type`, `evaluatee_id`, `evaluatee_type`, `main_category_id`, `semester_id`, `subject_id`, `evaluation_date`, `status`, `notes`, `created_at`, `updated_at`) VALUES ('334', '2', 'student', '2', 'teacher', '1', '1', NULL, '2025-08-15', 'completed', NULL, '2025-08-15 00:30:53', '2025-08-18 15:50:43');

-- Table structure for table `evaluation_responses`
DROP TABLE IF EXISTS `evaluation_responses`;
CREATE TABLE `evaluation_responses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `evaluation_session_id` int(11) NOT NULL,
  `questionnaire_id` int(11) NOT NULL,
  `rating_value` int(11) DEFAULT NULL COMMENT '1-5 rating value',
  `text_response` text DEFAULT NULL,
  `multiple_choice_response` varchar(255) DEFAULT NULL,
  `yes_no_response` enum('yes','no') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `evaluation_questionnaire` (`evaluation_session_id`,`questionnaire_id`),
  KEY `questionnaire_id` (`questionnaire_id`),
  KEY `rating_value` (`rating_value`),
  KEY `idx_evaluation_responses_session_questionnaire` (`evaluation_session_id`,`questionnaire_id`),
  KEY `idx_evaluation_responses_rating` (`rating_value`),
  CONSTRAINT `fk_evaluation_responses_questionnaire` FOREIGN KEY (`questionnaire_id`) REFERENCES `evaluation_questionnaires` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_evaluation_responses_session` FOREIGN KEY (`evaluation_session_id`) REFERENCES `evaluation_sessions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=176 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for table `evaluation_responses`
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('92', '329', '1', '1', NULL, NULL, NULL, '2025-08-15 00:23:28');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('93', '329', '19', '2', NULL, NULL, NULL, '2025-08-15 00:23:28');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('94', '329', '2', '1', NULL, NULL, NULL, '2025-08-15 00:23:28');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('95', '329', '20', '1', NULL, NULL, NULL, '2025-08-15 00:23:28');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('96', '329', '121', NULL, 'The teacher doesn&#039;t know how to teach the student, He&#039;s so annoying!', NULL, NULL, '2025-08-15 00:23:28');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('97', '329', '122', NULL, 'He let the students disrupt the class. I hate him!', NULL, NULL, '2025-08-15 00:23:28');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('98', '329', '6', '2', NULL, NULL, NULL, '2025-08-15 00:23:28');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('99', '329', '26', '3', NULL, NULL, NULL, '2025-08-15 00:23:28');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('100', '329', '7', '3', NULL, NULL, NULL, '2025-08-15 00:23:28');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('101', '329', '27', '2', NULL, NULL, NULL, '2025-08-15 00:23:28');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('102', '329', '123', NULL, 'The assessment.', NULL, NULL, '2025-08-15 00:23:28');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('103', '329', '124', NULL, 'I don&#039;t know, he needs to undergo training', NULL, NULL, '2025-08-15 00:23:28');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('104', '329', '34', '2', NULL, NULL, NULL, '2025-08-15 00:23:28');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('105', '329', '35', '3', NULL, NULL, NULL, '2025-08-15 00:23:28');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('106', '329', '36', '4', NULL, NULL, NULL, '2025-08-15 00:23:28');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('107', '329', '37', '4', NULL, NULL, NULL, '2025-08-15 00:23:28');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('108', '329', '125', NULL, 'Nakakadismaya lang kasi di siya nakakaintindi.', NULL, NULL, '2025-08-15 00:23:28');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('109', '329', '126', NULL, 'He&#039;s so good in the subject.', NULL, NULL, '2025-08-15 00:23:28');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('110', '329', '41', '3', NULL, NULL, NULL, '2025-08-15 00:23:28');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('111', '329', '42', '3', NULL, NULL, NULL, '2025-08-15 00:23:28');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('112', '329', '43', '2', NULL, NULL, NULL, '2025-08-15 00:23:28');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('113', '329', '44', '3', NULL, NULL, NULL, '2025-08-15 00:23:28');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('114', '329', '127', NULL, 'None so far.', NULL, NULL, '2025-08-15 00:23:28');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('115', '329', '128', NULL, 'He respond very well. He answers the question aligned to the topic. Looking Great!', NULL, NULL, '2025-08-15 00:23:28');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('116', '329', '11', '2', NULL, NULL, NULL, '2025-08-15 00:23:28');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('117', '329', '49', '3', NULL, NULL, NULL, '2025-08-15 00:23:28');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('118', '329', '12', '2', NULL, NULL, NULL, '2025-08-15 00:23:28');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('119', '329', '50', '3', NULL, NULL, NULL, '2025-08-15 00:23:28');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('120', '329', '129', NULL, 'Sometimes if the topic is hard to understand.', NULL, NULL, '2025-08-15 00:23:28');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('121', '329', '130', NULL, 'He gave class motivation by showing real world example.', NULL, NULL, '2025-08-15 00:23:28');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('122', '334', '1', '2', NULL, NULL, NULL, '2025-08-15 00:36:30');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('123', '334', '19', '3', NULL, NULL, NULL, '2025-08-15 00:36:30');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('124', '334', '2', '4', NULL, NULL, NULL, '2025-08-15 00:36:30');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('125', '334', '20', '3', NULL, NULL, NULL, '2025-08-15 00:36:30');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('126', '334', '121', NULL, 'Students frequently interrupt lessons, making it difficult for others to focus.', NULL, NULL, '2025-08-15 00:36:30');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('127', '334', '122', NULL, 'The teacher calmly addresses the behavior, reminds the student of classroom expectations, and may move them to a different seat if the disruption continues.', NULL, NULL, '2025-08-15 00:36:30');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('128', '334', '6', '2', NULL, NULL, NULL, '2025-08-15 00:36:30');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('129', '334', '26', '3', NULL, NULL, NULL, '2025-08-15 00:36:30');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('130', '334', '7', '4', NULL, NULL, NULL, '2025-08-15 00:36:30');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('131', '334', '27', '2', NULL, NULL, NULL, '2025-08-15 00:36:30');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('132', '334', '123', NULL, 'When teachers just read from the textbook without any interaction, it feels boring and disengaging.', NULL, NULL, '2025-08-15 00:36:30');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('133', '334', '124', NULL, 'The teacher could use more visuals, real-life examples, and interactive activities to make lessons more engaging and easier to understand.', NULL, NULL, '2025-08-15 00:36:30');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('134', '334', '34', '2', NULL, NULL, NULL, '2025-08-15 00:36:30');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('135', '334', '35', '3', NULL, NULL, NULL, '2025-08-15 00:36:30');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('136', '334', '36', '3', NULL, NULL, NULL, '2025-08-15 00:36:30');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('137', '334', '37', '3', NULL, NULL, NULL, '2025-08-15 00:36:30');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('138', '334', '125', NULL, 'The teacher often struggles to explain complex math concepts like algebra, which leaves many students confused.', NULL, NULL, '2025-08-15 00:36:30');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('139', '334', '126', NULL, 'Sometimes the teacher mispronounces scientific terms or gives outdated information, which can be a bit concerning.', NULL, NULL, '2025-08-15 00:36:30');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('140', '334', '41', '1', NULL, NULL, NULL, '2025-08-15 00:36:30');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('141', '334', '42', '2', NULL, NULL, NULL, '2025-08-15 00:36:30');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('142', '334', '43', '2', NULL, NULL, NULL, '2025-08-15 00:36:30');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('143', '334', '44', '4', NULL, NULL, NULL, '2025-08-15 00:36:30');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('144', '334', '127', NULL, 'The teacher sometimes dismisses questions too quickly or doesn&#039;t give clear answers, which makes it hard to follow the material.', NULL, NULL, '2025-08-15 00:36:30');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('145', '334', '128', NULL, 'The teacher usually answers questions patiently and encourages students to think critically, which makes the class feel supportive.', NULL, NULL, '2025-08-15 00:36:30');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('146', '334', '11', '2', NULL, NULL, NULL, '2025-08-15 00:36:30');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('147', '334', '49', '4', NULL, NULL, NULL, '2025-08-15 00:36:30');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('148', '334', '12', '4', NULL, NULL, NULL, '2025-08-15 00:36:30');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('149', '334', '50', '3', NULL, NULL, NULL, '2025-08-15 00:36:30');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('150', '334', '129', NULL, 'Long lectures without any group activities or discussions make the class feel dull and hard to stay focused on. (', NULL, NULL, '2025-08-15 00:36:30');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('151', '334', '130', NULL, 'The teacher could offer more praise and rewards for effort, and connect lessons to students’ personal interests to spark their enthusiasm.', NULL, NULL, '2025-08-15 00:36:30');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('152', '330', '131', '5', NULL, NULL, NULL, '2025-08-15 01:20:07');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('153', '330', '132', '4', NULL, NULL, NULL, '2025-08-15 01:20:07');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('154', '330', '133', '3', NULL, NULL, NULL, '2025-08-15 01:20:07');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('155', '330', '134', '4', NULL, NULL, NULL, '2025-08-15 01:20:07');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('156', '330', '135', '4', NULL, NULL, NULL, '2025-08-15 01:20:07');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('157', '330', '151', NULL, 'I think they would benefit from training in classroom management and using technology effectively to engage students better.', NULL, NULL, '2025-08-15 01:23:57');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('158', '330', '136', '5', NULL, NULL, NULL, '2025-08-15 01:24:06');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('159', '330', '137', '5', NULL, NULL, NULL, '2025-08-15 01:24:06');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('160', '330', '138', '4', NULL, NULL, NULL, '2025-08-15 01:24:06');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('161', '330', '139', '5', NULL, NULL, NULL, '2025-08-15 01:24:06');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('162', '330', '140', '4', NULL, NULL, NULL, '2025-08-15 01:24:06');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('163', '330', '152', NULL, 'They could improve by actively listening to others\' ideas and sharing their own feedback more openly during meetings.', NULL, NULL, '2025-08-15 01:24:25');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('164', '330', '141', '5', NULL, NULL, NULL, '2025-08-15 01:24:51');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('165', '330', '142', '5', NULL, NULL, NULL, '2025-08-15 01:24:51');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('166', '330', '143', '5', NULL, NULL, NULL, '2025-08-15 01:24:51');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('167', '330', '144', '3', NULL, NULL, NULL, '2025-08-15 01:24:51');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('168', '330', '145', '4', NULL, NULL, NULL, '2025-08-15 01:24:51');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('169', '330', '153', NULL, 'They’ve started incorporating game-based learning and interactive quizzes, which really energize the students and make lessons fun.', NULL, NULL, '2025-08-15 01:24:51');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('170', '330', '146', '4', NULL, NULL, NULL, '2025-08-15 01:24:59');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('171', '330', '147', '4', NULL, NULL, NULL, '2025-08-15 01:24:59');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('172', '330', '148', '5', NULL, NULL, NULL, '2025-08-15 01:24:59');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('173', '330', '149', '3', NULL, NULL, NULL, '2025-08-15 01:24:59');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('174', '330', '150', '5', NULL, NULL, NULL, '2025-08-15 01:24:59');
INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES ('175', '330', '154', NULL, 'They are very patient and approachable, always willing to listen and offer thoughtful advice to newer teachers.', NULL, NULL, '2025-08-15 01:25:20');

-- Table structure for table `training_suggestions`
DROP TABLE IF EXISTS `training_suggestions`;
CREATE TABLE `training_suggestions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `training_id` int(11) NOT NULL,
  `suggestion_reason` text NOT NULL COMMENT 'Why this training is suggested',
  `evaluation_category_id` int(11) DEFAULT NULL COMMENT 'Related evaluation category',
  `evaluation_score` decimal(3,2) DEFAULT NULL COMMENT 'Teacher''s score in this category',
  `priority_level` enum('low','medium','high','critical') DEFAULT 'medium',
  `suggested_by` int(11) NOT NULL,
  `suggestion_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','accepted','declined','completed') DEFAULT 'pending',
  `response_date` datetime DEFAULT NULL,
  `response_notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `training_id` (`training_id`),
  KEY `evaluation_category_id` (`evaluation_category_id`),
  KEY `priority_level` (`priority_level`),
  KEY `status` (`status`),
  KEY `suggested_by` (`suggested_by`),
  KEY `idx_training_suggestions_user_status` (`user_id`,`status`),
  KEY `idx_training_suggestions_priority` (`priority_level`,`status`),
  KEY `idx_training_suggestions_category` (`evaluation_category_id`),
  CONSTRAINT `fk_training_suggestions_category` FOREIGN KEY (`evaluation_category_id`) REFERENCES `evaluation_sub_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_training_suggestions_suggested_by` FOREIGN KEY (`suggested_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_training_suggestions_training` FOREIGN KEY (`training_id`) REFERENCES `trainings_seminars` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_training_suggestions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for table `training_suggestions`
INSERT INTO `training_suggestions` (`id`, `user_id`, `training_id`, `suggestion_reason`, `evaluation_category_id`, `evaluation_score`, `priority_level`, `suggested_by`, `suggestion_date`, `status`, `response_date`, `response_notes`) VALUES ('1', '2', '1', 'Based on your evaluation score of 2.13 in Classroom Management (Student to Teacher Evaluation), which is below the recommended threshold of 4.0. This training will help improve your performance in this area.', '1', '2.13', 'critical', '5', '2025-08-18 17:24:55', 'pending', NULL, NULL);
INSERT INTO `training_suggestions` (`id`, `user_id`, `training_id`, `suggestion_reason`, `evaluation_category_id`, `evaluation_score`, `priority_level`, `suggested_by`, `suggestion_date`, `status`, `response_date`, `response_notes`) VALUES ('2', '2', '3', 'Based on your evaluation score of 2.5 in Communication Skills (Student to Teacher Evaluation), which is below the recommended threshold of 4.0. This training will help improve your performance in this area.', '4', '2.50', 'high', '5', '2025-08-18 17:24:55', 'pending', NULL, NULL);
INSERT INTO `training_suggestions` (`id`, `user_id`, `training_id`, `suggestion_reason`, `evaluation_category_id`, `evaluation_score`, `priority_level`, `suggested_by`, `suggestion_date`, `status`, `response_date`, `response_notes`) VALUES ('3', '2', '2', 'Based on your evaluation score of 2.63 in Teaching Skills (Student to Teacher Evaluation), which is below the recommended threshold of 4.0. This training will help improve your performance in this area.', '2', '2.63', 'high', '5', '2025-08-18 17:24:55', 'pending', NULL, NULL);

-- Table structure for table `trainings_seminars`
DROP TABLE IF EXISTS `trainings_seminars`;
CREATE TABLE `trainings_seminars` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `type` enum('training','seminar','workshop','conference') NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `main_category_id` int(11) DEFAULT NULL COMMENT 'Linked to evaluation main category',
  `sub_category_id` int(11) DEFAULT NULL COMMENT 'Linked to evaluation sub-category',
  `duration_hours` decimal(5,2) DEFAULT NULL,
  `max_participants` int(11) DEFAULT NULL,
  `venue` varchar(255) DEFAULT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `registration_deadline` datetime DEFAULT NULL,
  `status` enum('draft','published','ongoing','completed','cancelled') DEFAULT 'draft',
  `is_mandatory` tinyint(1) DEFAULT 0,
  `certificate_provided` tinyint(1) DEFAULT 0,
  `materials_provided` tinyint(1) DEFAULT 0,
  `cost` decimal(10,2) DEFAULT 0.00,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  KEY `main_category_id` (`main_category_id`),
  KEY `sub_category_id` (`sub_category_id`),
  KEY `status` (`status`),
  KEY `start_date` (`start_date`),
  KEY `created_by` (`created_by`),
  KEY `idx_trainings_seminars_type_status` (`type`,`status`),
  KEY `idx_trainings_seminars_dates` (`start_date`,`end_date`),
  KEY `idx_trainings_seminars_category_status` (`category_id`,`status`),
  KEY `idx_trainings_seminars_main_category` (`main_category_id`,`status`),
  CONSTRAINT `fk_trainings_seminars_category` FOREIGN KEY (`category_id`) REFERENCES `training_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_trainings_seminars_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_trainings_seminars_main_category` FOREIGN KEY (`main_category_id`) REFERENCES `main_evaluation_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_trainings_seminars_sub_category` FOREIGN KEY (`sub_category_id`) REFERENCES `evaluation_sub_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for table `trainings_seminars`
INSERT INTO `trainings_seminars` (`id`, `title`, `description`, `type`, `category_id`, `main_category_id`, `sub_category_id`, `duration_hours`, `max_participants`, `venue`, `start_date`, `end_date`, `registration_deadline`, `status`, `is_mandatory`, `certificate_provided`, `materials_provided`, `cost`, `created_by`, `created_at`, `updated_at`) VALUES ('1', 'Effective Classroom Management Strategies', 'Comprehensive workshop on effective classroom management strategies, behavior management techniques, and creating positive learning environments.', 'training', '1', '1', '1', '8.00', '25', 'Conference Room A', '2025-09-15 09:00:00', '2025-09-15 17:00:00', '2024-03-10 17:00:00', 'published', '0', '1', '1', '0.00', '1', '2025-08-13 12:23:02', '2025-08-14 15:47:30');
INSERT INTO `trainings_seminars` (`id`, `title`, `description`, `type`, `category_id`, `main_category_id`, `sub_category_id`, `duration_hours`, `max_participants`, `venue`, `start_date`, `end_date`, `registration_deadline`, `status`, `is_mandatory`, `certificate_provided`, `materials_provided`, `cost`, `created_by`, `created_at`, `updated_at`) VALUES ('2', 'Modern Teaching Methodologies Workshop', 'Advanced training on modern teaching methodologies, instructional design, and active learning strategies for enhanced student engagement.', 'workshop', '2', '1', '2', '6.00', '20', 'Training Hall B', '2025-09-20 09:00:00', '2025-09-20 15:00:00', '2024-03-15 17:00:00', 'published', '0', '1', '1', '500.00', '1', '2025-08-13 12:23:02', '2025-08-14 15:47:30');
INSERT INTO `trainings_seminars` (`id`, `title`, `description`, `type`, `category_id`, `main_category_id`, `sub_category_id`, `duration_hours`, `max_participants`, `venue`, `start_date`, `end_date`, `registration_deadline`, `status`, `is_mandatory`, `certificate_provided`, `materials_provided`, `cost`, `created_by`, `created_at`, `updated_at`) VALUES ('3', 'Technology Integration in Education', 'Workshop on integrating technology tools and digital resources into classroom instruction for improved learning outcomes.', 'seminar', '3', '1', '4', '4.00', '30', 'Computer Lab 1', '2025-09-25 13:00:00', '2025-09-25 17:00:00', '2024-03-20 17:00:00', 'published', '0', '1', '0', '0.00', '1', '2025-08-13 12:23:02', '2025-08-14 15:47:30');
INSERT INTO `trainings_seminars` (`id`, `title`, `description`, `type`, `category_id`, `main_category_id`, `sub_category_id`, `duration_hours`, `max_participants`, `venue`, `start_date`, `end_date`, `registration_deadline`, `status`, `is_mandatory`, `certificate_provided`, `materials_provided`, `cost`, `created_by`, `created_at`, `updated_at`) VALUES ('4', 'Student Engagement Techniques', 'Discover methods to increase student participation and motivation', 'training', '4', '1', '5', '6.00', '25', 'Conference Room C', '2024-04-01 09:00:00', '2024-04-01 15:00:00', '2024-03-27 17:00:00', 'draft', '0', '1', '1', '0.00', '1', '2025-08-13 12:23:02', NULL);
INSERT INTO `trainings_seminars` (`id`, `title`, `description`, `type`, `category_id`, `main_category_id`, `sub_category_id`, `duration_hours`, `max_participants`, `venue`, `start_date`, `end_date`, `registration_deadline`, `status`, `is_mandatory`, `certificate_provided`, `materials_provided`, `cost`, `created_by`, `created_at`, `updated_at`) VALUES ('5', 'Assessment and Evaluation Best Practices', 'Learn effective assessment strategies and evaluation methods', 'seminar', '5', '1', '2', '4.00', '35', 'Lecture Hall', '2024-04-05 14:00:00', '2024-04-05 18:00:00', '2024-04-01 17:00:00', 'draft', '0', '1', '0', '0.00', '1', '2025-08-13 12:23:02', NULL);

-- Table structure for table `training_registrations`
DROP TABLE IF EXISTS `training_registrations`;
CREATE TABLE `training_registrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `training_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `registration_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('registered','attended','completed','no_show','cancelled') DEFAULT 'registered',
  `attendance_date` datetime DEFAULT NULL,
  `completion_date` datetime DEFAULT NULL,
  `certificate_issued` tinyint(1) DEFAULT 0,
  `certificate_issued_date` datetime DEFAULT NULL,
  `feedback_rating` int(11) DEFAULT NULL COMMENT '1-5 rating',
  `feedback_comments` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `training_user` (`training_id`,`user_id`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`),
  KEY `registration_date` (`registration_date`),
  KEY `idx_training_registrations_user_status` (`user_id`,`status`),
  KEY `idx_training_registrations_training_status` (`training_id`,`status`),
  KEY `idx_training_registrations_date` (`registration_date`),
  CONSTRAINT `fk_training_registrations_training` FOREIGN KEY (`training_id`) REFERENCES `trainings_seminars` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_training_registrations_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `training_categories`
DROP TABLE IF EXISTS `training_categories`;
CREATE TABLE `training_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `status` (`status`),
  CONSTRAINT `fk_training_categories_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for table `training_categories`
INSERT INTO `training_categories` (`id`, `name`, `description`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('1', 'Classroom Management', 'Trainings focused on improving classroom discipline and management skills', 'active', '1', '2025-08-13 12:23:02', NULL);
INSERT INTO `training_categories` (`id`, `name`, `description`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('2', 'Teaching Methodologies', 'Seminars on modern teaching techniques and strategies', 'active', '1', '2025-08-13 12:23:02', NULL);
INSERT INTO `training_categories` (`id`, `name`, `description`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('3', 'Technology Integration', 'Workshops on incorporating technology in teaching', 'active', '1', '2025-08-13 12:23:02', NULL);
INSERT INTO `training_categories` (`id`, `name`, `description`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('4', 'Student Engagement', 'Training on methods to increase student participation and engagement', 'active', '1', '2025-08-13 12:23:02', NULL);
INSERT INTO `training_categories` (`id`, `name`, `description`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('5', 'Assessment Strategies', 'Seminars on effective assessment and evaluation methods', 'active', '1', '2025-08-13 12:23:02', NULL);
INSERT INTO `training_categories` (`id`, `name`, `description`, `status`, `created_by`, `created_at`, `updated_at`) VALUES ('6', 'Professional Development', 'General professional development and career advancement', 'active', '1', '2025-08-13 12:23:02', NULL);

-- Table structure for table `training_materials`
DROP TABLE IF EXISTS `training_materials`;
CREATE TABLE `training_materials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `training_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `download_count` int(11) DEFAULT 0,
  `is_public` tinyint(1) DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `training_id` (`training_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `fk_training_materials_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_training_materials_training` FOREIGN KEY (`training_id`) REFERENCES `trainings_seminars` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
