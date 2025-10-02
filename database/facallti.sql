-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Sep 11, 2025 at 05:09 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.1.17

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `facallti`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `GenerateTrainingSuggestions` (IN `teacher_id` INT)   BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE sub_cat_id INT;
    DECLARE sub_cat_name VARCHAR(255);
    DECLARE avg_rating DECIMAL(3,2);
    DECLARE priority VARCHAR(20);

    DECLARE score_cursor CURSOR FOR
        SELECT 
            esc.id,
            esc.name,
            AVG(er.rating_value) as avg_rating
        FROM evaluation_sub_categories esc
        JOIN main_evaluation_categories mec ON esc.main_category_id = mec.id
        JOIN evaluation_sessions es ON mec.id = es.main_category_id
        JOIN evaluation_questionnaires eq ON esc.id = eq.sub_category_id
        JOIN evaluation_responses er ON eq.id = er.questionnaire_id AND es.id = er.evaluation_session_id
        WHERE es.evaluatee_id = teacher_id 
            AND es.evaluatee_type = 'teacher'
            AND es.status = 'completed'
            AND er.rating_value IS NOT NULL
        GROUP BY esc.id
        HAVING COUNT(er.id) >= 3;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    OPEN score_cursor;

    read_loop: LOOP
        FETCH score_cursor INTO sub_cat_id, sub_cat_name, avg_rating;
        IF done THEN
            LEAVE read_loop;
        END IF;

        SET priority = CASE 
            WHEN avg_rating < 3.0 THEN 'critical'
            WHEN avg_rating < 3.5 THEN 'high'
            WHEN avg_rating < 4.0 THEN 'medium'
            ELSE 'low'
        END;

        IF priority IN ('medium', 'high', 'critical') THEN
            INSERT IGNORE INTO training_suggestions 
                (user_id, training_id, suggestion_reason, evaluation_category_id, evaluation_score, priority_level, suggested_by)
            SELECT 
                teacher_id,
                ts.id,
                CONCAT('Based on your evaluation score of ', ROUND(avg_rating, 2), ' in ', sub_cat_name, ' (', priority, ' priority)'),
                sub_cat_id,
                avg_rating,
                priority,
                1 
            FROM trainings_seminars ts
            WHERE ts.sub_category_id = sub_cat_id 
                AND ts.status = 'published'
                AND ts.start_date > NOW()
                AND NOT EXISTS (
                    SELECT 1 FROM training_suggestions ts2 
                    WHERE ts2.user_id = teacher_id 
                        AND ts2.training_id = ts.id 
                        AND ts2.status IN ('pending', 'accepted')
                );
        END IF;

    END LOOP;

    CLOSE score_cursor;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `GetTeacherTrainingRecommendations` (IN `teacher_id` INT)   BEGIN
    SELECT 
        ts.id as training_id,
        ts.title,
        ts.description,
        ts.type,
        ts.start_date,
        ts.end_date,
        ts.venue,
        ts.duration_hours,
        ts.cost,
        esc.name as related_category,
        tsg.priority_level,
        tsg.suggestion_reason,
        tsg.evaluation_score,
        CASE 
            WHEN tr.id IS NOT NULL THEN 'registered'
            WHEN tsg.id IS NOT NULL THEN tsg.status
            ELSE 'available'
        END as status
    FROM trainings_seminars ts
    JOIN evaluation_sub_categories esc ON ts.sub_category_id = esc.id
    LEFT JOIN training_suggestions tsg ON ts.id = tsg.training_id AND tsg.user_id = teacher_id
    LEFT JOIN training_registrations tr ON ts.id = tr.training_id AND tr.user_id = teacher_id
    WHERE ts.status = 'published'
        AND ts.start_date > NOW()
        AND (tsg.user_id = teacher_id OR tsg.id IS NULL)
    ORDER BY 
        CASE tsg.priority_level
            WHEN 'critical' THEN 1
            WHEN 'high' THEN 2
            WHEN 'medium' THEN 3
            WHEN 'low' THEN 4
            ELSE 5
        END,
        ts.start_date;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `MarkTeacherAvailable` (IN `p_teacher_id` INT, IN `p_notes` TEXT)   BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    
    INSERT INTO teacher_availability (teacher_id, availability_date, status, notes, last_activity)
    VALUES (p_teacher_id, CURDATE(), 'available', p_notes, NOW())
    ON DUPLICATE KEY UPDATE
        status = 'available',
        scan_time = NOW(),
        last_activity = NOW(),
        notes = COALESCE(p_notes, notes),
        updated_at = NOW();
    
    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `MarkTeacherUnavailable` (IN `p_teacher_id` INT, IN `p_notes` TEXT)   BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    
    UPDATE teacher_availability 
    SET status = 'unavailable',
        last_activity = NOW(),
        notes = COALESCE(p_notes, notes),
        updated_at = NOW()
    WHERE teacher_id = p_teacher_id 
    AND availability_date = CURDATE();
    
    COMMIT;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Stand-in structure for view `active_consultation_leaves`
-- (See below for the actual view)
--
CREATE TABLE `active_consultation_leaves` (
`id` int(11)
,`teacher_id` int(11)
,`leave_date` date
,`reason` text
,`created_at` timestamp
,`first_name` varchar(50)
,`last_name` varchar(50)
,`department` varchar(255)
,`position` varchar(100)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `active_consultation_requests`
-- (See below for the actual view)
--
CREATE TABLE `active_consultation_requests` (
`id` int(11)
,`teacher_id` int(11)
,`teacher_first_name` varchar(50)
,`teacher_last_name` varchar(50)
,`teacher_department` varchar(255)
,`student_name` varchar(255)
,`student_dept` varchar(255)
,`student_id` varchar(255)
,`status` enum('pending','accepted','declined','completed','cancelled')
,`session_id` varchar(255)
,`request_time` timestamp
,`response_time` timestamp
,`start_time` timestamp
,`end_time` timestamp
,`duration_minutes` int(11)
,`notes` text
,`minutes_since_request` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `active_students_view`
-- (See below for the actual view)
--
CREATE TABLE `active_students_view` (
`id` int(11)
,`student_id` varchar(50)
,`first_name` varchar(100)
,`middle_name` varchar(100)
,`last_name` varchar(100)
,`email` varchar(255)
,`status` enum('active','pending','inactive','deleted')
,`created_at` timestamp
,`full_name` varchar(201)
,`phone` varchar(20)
,`date_of_birth` date
,`program_id` int(11)
,`year_level` varchar(20)
,`academic_status` enum('regular','probation','suspended','graduated','withdrawn')
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `active_teachers_today`
-- (See below for the actual view)
--
CREATE TABLE `active_teachers_today` (
`id` int(11)
,`teacher_id` int(11)
,`first_name` varchar(50)
,`last_name` varchar(50)
,`email` varchar(100)
,`department` varchar(255)
,`position` varchar(100)
,`image_url` varchar(255)
,`availability_date` date
,`scan_time` timestamp
,`status` enum('available','unavailable')
,`last_activity` timestamp
,`minutes_since_last_activity` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `colleges`
--

CREATE TABLE `colleges` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `short_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `logo_url` varchar(255) DEFAULT NULL,
  `color_theme` varchar(7) DEFAULT '#FF6B35',
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `colleges`
--

INSERT INTO `colleges` (`id`, `name`, `short_name`, `description`, `logo_url`, `color_theme`, `is_active`, `sort_order`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'College of Business and Good Governance', 'CBGG', 'Fostering ethical business practices and effective governance through comprehensive business education and leadership development.', 'assets/images/colleges/college_1754571516.jpg', '#ecb10e', 1, 1, 1, '2025-08-05 11:51:59', '2025-08-07 12:58:36'),
(3, 'College of Information and Communication Technology', 'CICT', 'Empowering digital transformation through comprehensive IT education and innovative technology solutions.', 'assets/images/colleges/college_1754571466.jpg', '#f263a1', 1, 3, 1, '2025-08-05 11:51:59', '2025-08-07 12:57:46'),
(5, 'College of Teacher Education', 'COE', 'Shaping future educators and leaders through innovative teaching methodologies and educational research.', 'assets/images/colleges/college_1754571704.jpg', '#4046e7', 1, 5, 1, '2025-08-05 11:51:59', '2025-08-07 13:01:44'),
(6, 'Department of Civil Engineering', 'DCE', '', 'assets/images/colleges/college_1754571645.jpg', '#86442d', 1, 4, 3, '2025-08-07 13:00:45', '2025-08-07 13:00:45'),
(7, 'College of Criminal Justice Education', 'CCJE', 'The College of Criminal Justice Education is a premier academic institution dedicated to the development of future professionals in the field of law enforcement, criminology, corrections, and public safety. With a strong commitment to academic excellence, ethical leadership, and community service, the college equips students with the knowledge, skills, and values necessary to contribute effectively to the justice system', 'assets/images/colleges/college_1754571977.jpg', '#ff6b35', 1, 0, 3, '2025-08-07 13:06:17', '2025-08-07 13:06:17'),
(8, 'College of Agriculture and Fishiries', 'CAF', 'The College of Agriculture and Fisheries is a vital academic institution committed to advancing sustainable agriculture, aquaculture, and food security through education, research, and community engagement. Rooted in innovation and environmental stewardship, the college prepares students to become skilled professionals, researchers, and leaders in the agricultural and fisheries sectors.', 'assets/images/colleges/college_1754572098.jpg', '#29a027', 1, 0, 3, '2025-08-07 13:08:18', '2025-08-07 13:08:18');

-- --------------------------------------------------------

--
-- Table structure for table `consultation_hours`
--

CREATE TABLE `consultation_hours` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `semester` varchar(20) NOT NULL,
  `academic_year` varchar(10) NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `room` varchar(50) DEFAULT 'Faculty Office',
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `consultation_hours`
--

INSERT INTO `consultation_hours` (`id`, `teacher_id`, `semester`, `academic_year`, `day_of_week`, `start_time`, `end_time`, `room`, `notes`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 2, 'First Semester', '2024-2025', 'Friday', '13:00:00', '19:00:00', 'Faculty Office', '', 1, 7, '2025-08-29 07:51:16', NULL),
(2, 2, 'First Semester', '2024-2025', 'Sunday', '10:00:00', '12:00:00', 'Faculty Office', '', 1, 7, '2025-08-31 01:55:49', NULL),
(3, 9, 'First Semester', '2024-2025', 'Sunday', '12:00:00', '14:00:00', 'Faculty Office', '', 1, 7, '2025-08-31 01:56:13', NULL),
(4, 9, 'First Semester', '2024-2025', 'Sunday', '10:00:00', '12:00:00', 'Deans Office', '', 1, 7, '2025-08-31 02:48:33', NULL),
(5, 9, 'First Semester', '2024-2025', 'Sunday', '10:00:00', '12:00:00', 'Deans Office', '', 1, 7, '2025-08-31 03:04:31', NULL);

-- --------------------------------------------------------

--
-- Stand-in structure for view `consultation_hours_summary`
-- (See below for the actual view)
--
CREATE TABLE `consultation_hours_summary` (
`id` int(11)
,`teacher_id` int(11)
,`first_name` varchar(50)
,`last_name` varchar(50)
,`email` varchar(100)
,`department` varchar(255)
,`semester` varchar(20)
,`academic_year` varchar(10)
,`day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday')
,`start_time` time
,`end_time` time
,`room` varchar(50)
,`notes` text
,`is_active` tinyint(1)
,`created_at` timestamp
,`updated_at` timestamp
);

-- --------------------------------------------------------

--
-- Table structure for table `consultation_leave`
--

CREATE TABLE `consultation_leave` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `leave_date` date NOT NULL,
  `reason` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `consultation_requests`
--

CREATE TABLE `consultation_requests` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `student_name` varchar(255) NOT NULL,
  `student_dept` varchar(255) DEFAULT NULL,
  `student_id` varchar(255) DEFAULT NULL,
  `status` enum('pending','accepted','declined','completed','cancelled') NOT NULL DEFAULT 'pending',
  `decline_reason` varchar(255) DEFAULT NULL,
  `teacher_response_notes` text DEFAULT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `request_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `response_time` timestamp NULL DEFAULT NULL,
  `response_duration_seconds` int(11) DEFAULT NULL,
  `start_time` timestamp NULL DEFAULT NULL,
  `end_time` timestamp NULL DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `consultation_requests`
--

INSERT INTO `consultation_requests` (`id`, `teacher_id`, `student_name`, `student_dept`, `student_id`, `status`, `decline_reason`, `teacher_response_notes`, `session_id`, `request_time`, `response_time`, `response_duration_seconds`, `start_time`, `end_time`, `duration_minutes`, `notes`, `created_at`, `updated_at`) VALUES
(1, 9, 'Student 2017-00202', 'General', '2017', 'pending', NULL, NULL, 'consultation_68c2e5e1b28e79.22888433', '2025-09-11 15:08:17', NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-11 15:08:17', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `subject` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `contact_messages`
--

INSERT INTO `contact_messages` (`id`, `name`, `email`, `subject`, `message`, `is_read`, `created_at`) VALUES
(1, 'Juan Dela Cruz', 'juan.delacruz@email.com', 'Inquiry about Computer Engineering Program', 'I am interested in the Computer Engineering program and would like to know more about the admission requirements and available scholarships.', 0, '2024-12-01 02:30:00'),
(2, 'Maria Santos', 'maria.santos@email.com', 'Faculty Position Application', 'I am applying for the Assistant Professor position in Computer Science. Please find my application attached.', 0, '2024-12-05 06:20:00'),
(3, 'Pedro Reyes', 'pedro.reyes@email.com', 'Partnership Proposal', 'Our company would like to discuss potential partnerships with SEAIT for student internships and research collaboration.', 0, '2024-12-10 01:45:00'),
(4, 'Ana Martinez', 'ana.martinez@email.com', 'Alumni Information Update', 'I would like to update my contact information and learn about upcoming alumni events.', 0, '2024-12-15 08:15:00'),
(5, 'Luis Fernandez', 'luis.fernandez@email.com', 'Technology Summit Registration', 'I am interested in registering for the Technology Innovation Summit. Please provide registration details.', 0, '2024-12-20 03:30:00');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(100) DEFAULT NULL,
  `color_theme` varchar(7) DEFAULT '#FF6B35',
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `name`, `description`, `icon`, `color_theme`, `sort_order`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Admissions Office', 'Handles student admissions, applications, and enrollment inquiries', 'fas fa-user-graduate', '#FF6B35', 1, 1, 1, '2025-08-05 16:50:53', '2025-08-05 16:50:53'),
(2, 'Academic Affairs', 'Manages academic programs, curriculum, and faculty matters', 'fas fa-graduation-cap', '#2C3E50', 2, 1, 1, '2025-08-05 16:50:53', '2025-08-05 16:50:53'),
(3, 'Student Services', 'Provides student support, counseling, and campus life assistance', 'fas fa-users', '#3498DB', 3, 1, 1, '2025-08-05 16:50:53', '2025-08-05 16:50:53'),
(4, 'IT Support', 'Technical support and computer services for students and staff', 'fas fa-laptop', '#E74C3C', 4, 1, 1, '2025-08-05 16:50:53', '2025-08-05 16:50:53'),
(5, 'Finance Office', 'Handles tuition, fees, and financial aid matters', 'fas fa-calculator', '#27AE60', 5, 1, 1, '2025-08-05 16:50:53', '2025-08-05 16:50:53'),
(6, 'Human Resources', 'Staff recruitment, benefits, and employment inquiries', 'fas fa-user-tie', '#9B59B6', 6, 1, 1, '2025-08-05 16:50:53', '2025-08-05 16:50:53');

-- --------------------------------------------------------

--
-- Table structure for table `error_logs`
--

CREATE TABLE `error_logs` (
  `id` int(11) NOT NULL,
  `error_type` varchar(50) NOT NULL COMMENT 'Type of error (404, 500, etc.)',
  `requested_url` text NOT NULL COMMENT 'The URL that caused the error',
  `referrer` text DEFAULT NULL COMMENT 'The referring page',
  `user_agent` text DEFAULT NULL COMMENT 'User agent string',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IP address of the user',
  `user_id` int(11) DEFAULT NULL COMMENT 'User ID if logged in',
  `session_id` varchar(255) DEFAULT NULL COMMENT 'Session ID',
  `error_message` text DEFAULT NULL COMMENT 'Additional error details',
  `stack_trace` text DEFAULT NULL COMMENT 'Stack trace for debugging',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Logs for tracking 404 errors and other issues';

--
-- Dumping data for table `error_logs`
--

INSERT INTO `error_logs` (`id`, `error_type`, `requested_url`, `referrer`, `user_agent`, `ip_address`, `user_id`, `session_id`, `error_message`, `stack_trace`, `created_at`) VALUES
(1, '404', '/seait/login.php', 'http://localhost/seait/human-resource/admin-employee.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 16:54:12'),
(2, '404', '/seait/404.php', 'http://localhost/seait/human-resource/manage-departments.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 16:58:52'),
(3, '404', '/seait/404.php', 'http://localhost/seait/human-resource/manage-departments.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 17:00:43'),
(4, '404', '/seait/human-resource/job-postings.php', 'http://localhost/seait/human-resource/manage-departments.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 17:01:30'),
(5, '404', '/seait/human-resource/assets/css/dark-mode.css', 'http://localhost/seait/human-resource/job-postings.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 17:01:30'),
(6, '404', '/seait/human-resource/assets/js/dark-mode.js', 'http://localhost/seait/human-resource/job-postings.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 17:01:30'),
(7, '404', '/seait/404.php', 'http://localhost/seait/human-resource/manage-departments.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 17:01:38'),
(8, '404', '/seait/404.php', 'http://localhost/seait/human-resource/manage-departments.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 17:04:29'),
(9, '404', '/seait/404.php', 'http://localhost/seait/human-resource/manage-departments.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 17:05:04'),
(10, '505', '/seait/505.php', 'http://localhost/seait/human-resource/manage-departments.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 17:06:31'),
(11, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 17:07:01'),
(12, '505', '/seait/505.php', 'http://localhost/seait/human-resource/manage-departments.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 17:07:22'),
(13, '505', '/seait/505.php', 'http://localhost/seait/human-resource/manage-departments.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 17:07:29'),
(14, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 17:08:00'),
(15, '505', '/seait/505.php', 'http://localhost/seait/human-resource/manage-departments.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 17:10:33'),
(16, '505', '/seait/505.php', 'http://localhost/seait/human-resource/manage-departments.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 17:12:01'),
(17, '404', '/seait/human-resource/job-postings.php', 'http://localhost/seait/human-resource/index.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 17:13:20'),
(18, '404', '/seait/human-resource/assets/css/dark-mode.css', 'http://localhost/seait/human-resource/job-postings.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 17:13:20'),
(19, '404', '/seait/human-resource/assets/js/dark-mode.js', 'http://localhost/seait/human-resource/job-postings.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 17:13:20'),
(20, '404', '/seait/human-resource/view-employee.php?id=oqONqAMxjJq1Z-GIjj4DU2dMjk3rTdcmRGIU5QWzuIA=', 'http://localhost/seait/human-resource/admin-employee.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 17:18:14'),
(21, '404', '/seait/human-resource/assets/css/dark-mode.css', 'http://localhost/seait/human-resource/view-employee.php?id=oqONqAMxjJq1Z-GIjj4DU2dMjk3rTdcmRGIU5QWzuIA=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 17:18:14'),
(22, '404', '/seait/human-resource/assets/js/dark-mode.js', 'http://localhost/seait/human-resource/view-employee.php?id=oqONqAMxjJq1Z-GIjj4DU2dMjk3rTdcmRGIU5QWzuIA=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 17:18:14'),
(23, '404', '/seait/human-resource/leave-balances.php', 'http://localhost/seait/human-resource/index.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 17:19:29'),
(24, '404', '/seait/human-resource/assets/css/dark-mode.css', 'http://localhost/seait/human-resource/leave-balances.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 17:19:29'),
(25, '404', '/seait/human-resource/assets/js/dark-mode.js', 'http://localhost/seait/human-resource/leave-balances.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 17:19:29'),
(26, '404', '/seait/human-resource/leave-reports.php', 'http://localhost/seait/human-resource/index.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 17:19:40'),
(27, '404', '/seait/human-resource/assets/css/dark-mode.css', 'http://localhost/seait/human-resource/leave-reports.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 17:19:40'),
(28, '404', '/seait/human-resource/assets/js/dark-mode.js', 'http://localhost/seait/human-resource/leave-reports.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 17:19:40'),
(29, '404', '/seait/human-resource/job-postings.php', 'http://localhost/seait/human-resource/view-employee.php?id=_CIobUvQHfYoTq54Rgok2G3kCK_VZRbBww6tj54KpvA=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 17:28:17'),
(30, '404', '/seait/human-resource/assets/css/dark-mode.css', 'http://localhost/seait/human-resource/job-postings.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 17:28:17'),
(31, '404', '/seait/human-resource/assets/js/dark-mode.js', 'http://localhost/seait/human-resource/job-postings.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 17:28:17'),
(32, '404', '/seait/human-resource/edit-employee.php?id=WB3ZXcibtuVB847tKu5hdzZy0GrTtkOPWyB_3Y7VRwI=', 'http://localhost/seait/human-resource/view-employee.php?id=WB3ZXcibtuVB847tKu5hdzZy0GrTtkOPWyB_3Y7VRwI=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 17:37:46'),
(33, '404', '/seait/human-resource/assets/css/dark-mode.css', 'http://localhost/seait/human-resource/edit-employee.php?id=WB3ZXcibtuVB847tKu5hdzZy0GrTtkOPWyB_3Y7VRwI=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 17:37:46'),
(34, '404', '/seait/human-resource/assets/js/dark-mode.js', 'http://localhost/seait/human-resource/edit-employee.php?id=WB3ZXcibtuVB847tKu5hdzZy0GrTtkOPWyB_3Y7VRwI=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 17:37:46'),
(35, '505', '/seait/505.php', 'http://localhost/seait/human-resource/leave-management.php?tab=admin', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 17:52:54'),
(36, '404', '/seait/404.php', 'http://localhost/seait/human-resource/leave-management.php?tab=all', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 17:52:56'),
(37, '505', '/seait/505.php', 'http://localhost/seait/human-resource/leave-management.php?tab=admin', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 17:52:58'),
(38, '404', '/seait/404.php', 'http://localhost/seait/human-resource/leave-management.php?tab=faculty', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 17:52:59'),
(39, '505', '/seait/505.php', 'http://localhost/seait/human-resource/leave-management.php?tab=admin', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 17:53:00'),
(40, '404', '/seait/404.php', 'http://localhost/seait/human-resource/leave-management.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 17:53:01'),
(41, '505', '/seait/505.php', 'http://localhost/seait/human-resource/view-employee.php?id=WB3ZXcibtuVB847tKu5hdzZy0GrTtkOPWyB_3Y7VRwI=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 17:53:01'),
(42, '505', '/seait/505.php', 'http://localhost/seait/human-resource/view-employee.php?id=WB3ZXcibtuVB847tKu5hdzZy0GrTtkOPWyB_3Y7VRwI=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 17:53:07'),
(43, '505', '/seait/505.php', 'http://localhost/seait/human-resource/view-employee.php?id=WB3ZXcibtuVB847tKu5hdzZy0GrTtkOPWyB_3Y7VRwI=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 17:54:24'),
(44, '505', '/seait/505.php', 'http://localhost/seait/human-resource/view-employee.php?id=WB3ZXcibtuVB847tKu5hdzZy0GrTtkOPWyB_3Y7VRwI=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 17:54:55'),
(45, '505', '/seait/505.php', 'http://localhost/seait/human-resource/view-employee.php?id=WB3ZXcibtuVB847tKu5hdzZy0GrTtkOPWyB_3Y7VRwI=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 17:57:43'),
(46, '505', '/seait/505.php', 'http://localhost/seait/human-resource/view-employee.php?id=WB3ZXcibtuVB847tKu5hdzZy0GrTtkOPWyB_3Y7VRwI=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:00:36'),
(47, '505', '/seait/505.php', 'http://localhost/seait/human-resource/view-employee.php?id=WB3ZXcibtuVB847tKu5hdzZy0GrTtkOPWyB_3Y7VRwI=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:01:55'),
(48, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:02:26'),
(49, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:02:57'),
(50, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:03:28'),
(51, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:03:59'),
(52, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:04:30'),
(53, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:05:01'),
(54, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:05:32'),
(55, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:06:03'),
(56, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:06:34'),
(57, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:07:05'),
(58, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:07:36'),
(59, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:08:07'),
(60, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:08:38'),
(61, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:09:09'),
(62, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:09:40'),
(63, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:10:11'),
(64, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:10:42'),
(65, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:11:13'),
(66, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:11:44'),
(67, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:12:15'),
(68, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:12:46'),
(69, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:13:17'),
(70, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:13:48'),
(71, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:14:19'),
(72, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:14:50'),
(73, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:15:21'),
(74, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:15:52'),
(75, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:16:23'),
(76, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:16:54'),
(77, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:17:25'),
(78, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:17:56'),
(79, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:18:27'),
(80, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:18:58'),
(81, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:19:29'),
(82, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:20:00'),
(83, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:20:31'),
(84, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:21:02'),
(85, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:21:33'),
(86, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:22:04'),
(87, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:22:35'),
(88, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:23:06'),
(89, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:23:37'),
(90, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:24:08'),
(91, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:24:39'),
(92, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:25:10'),
(93, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:25:41'),
(94, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:26:12'),
(95, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:26:43'),
(96, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:27:14'),
(97, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:27:45'),
(98, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:28:16'),
(99, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:28:47'),
(100, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:29:18'),
(101, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:29:49'),
(102, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:30:20'),
(103, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:30:51'),
(104, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:31:22'),
(105, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:31:53'),
(106, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:32:24'),
(107, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:32:55'),
(108, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:33:26'),
(109, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:33:57'),
(110, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:34:28'),
(111, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:34:59'),
(112, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:35:30'),
(113, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:36:01'),
(114, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:36:32'),
(115, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:37:03'),
(116, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:37:34'),
(117, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:38:05'),
(118, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:38:36'),
(119, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:39:07'),
(120, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:39:38'),
(121, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:40:09'),
(122, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:40:40'),
(123, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:41:11'),
(124, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:41:42'),
(125, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:42:13'),
(126, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 18:42:44'),
(127, '404', '/seait/human-resource/leave-balances.php', 'http://localhost/seait/human-resource/leave-management.php?tab=faculty', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 23:46:36'),
(128, '404', '/seait/human-resource/assets/css/dark-mode.css', 'http://localhost/seait/human-resource/leave-balances.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 23:46:36'),
(129, '404', '/seait/human-resource/assets/js/dark-mode.js', 'http://localhost/seait/human-resource/leave-balances.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 23:46:36'),
(130, '404', '/seait/human-resource/leave-balances.php', 'http://localhost/seait/human-resource/leave-management.php?tab=faculty', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 23:46:41'),
(131, '404', '/seait/human-resource/assets/css/dark-mode.css', 'http://localhost/seait/human-resource/leave-balances.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 23:46:41'),
(132, '404', '/seait/human-resource/assets/js/dark-mode.js', 'http://localhost/seait/human-resource/leave-balances.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 23:46:41'),
(133, '505', '/seait/505.php', 'http://localhost/seait/heads/leave-requests.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-28 23:47:10'),
(134, '404', '/seait/human-resource/leave-balances.php', 'http://localhost/seait/human-resource/leave-management.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-29 00:57:13'),
(135, '404', '/seait/human-resource/assets/css/dark-mode.css', 'http://localhost/seait/human-resource/leave-balances.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-29 00:57:13'),
(136, '404', '/seait/human-resource/assets/js/dark-mode.js', 'http://localhost/seait/human-resource/leave-balances.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-29 00:57:13'),
(137, '505', '/seait/505.php', 'http://localhost/seait/human-resource/leave-management.php?tab=faculty', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-29 00:58:08'),
(138, '505', '/seait/505.php', 'http://localhost/seait/human-resource/leave-management.php?tab=faculty', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-29 00:58:14'),
(139, '505', '/seait/505.php', 'http://localhost/seait/human-resource/leave-management.php?tab=faculty', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-29 00:58:53'),
(140, '404', '/seait/human-resource/leave-balances.php', 'http://localhost/seait/human-resource/leave-management.php?tab=employee&status=&department=&date_from=&date_to=&search=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-29 01:18:52'),
(141, '404', '/seait/human-resource/assets/css/dark-mode.css', 'http://localhost/seait/human-resource/leave-balances.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-29 01:18:52'),
(142, '404', '/seait/human-resource/assets/js/dark-mode.js', 'http://localhost/seait/human-resource/leave-balances.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-29 01:18:52'),
(143, '505', '/seait/505.php', 'http://localhost/seait/heads/leave-requests.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-29 01:38:49'),
(144, '505', '/seait/505.php', 'http://localhost/seait/heads/leave-requests.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-29 01:41:39'),
(145, '505', '/seait/505.php', 'http://localhost/seait/heads/leave-requests.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-29 01:42:56'),
(146, '505', '/seait/505.php', 'http://localhost/seait/heads/leave-requests.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-29 01:47:32'),
(147, '505', '/seait/505.php', 'http://localhost/seait/faculty/dashboard.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-29 02:16:24'),
(148, '505', '/seait/505.php', 'http://localhost/seait/faculty/leave-history.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-29 02:18:15'),
(149, '505', '/seait/505.php', 'http://localhost/seait/faculty/leave-history.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-29 02:21:12'),
(150, '505', '/seait/505.php', 'http://localhost/seait/faculty/leave-history.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-29 02:23:04'),
(151, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-29 02:23:35'),
(152, '505', '/seait/505.php', 'http://localhost/seait/faculty/leave-history.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-29 02:24:00'),
(153, '505', '/seait/505.php', 'http://localhost/seait/faculty/leave-history.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-29 02:28:25'),
(154, '505', '/seait/505.php', 'http://localhost/seait/faculty/leave-history.php', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-29 02:28:58'),
(155, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-29 02:29:28'),
(156, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-29 02:29:59'),
(157, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-29 02:30:30'),
(158, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-29 02:31:01'),
(159, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-29 02:31:32'),
(160, '505', '/seait/505.php', 'http://localhost/seait/admin/settings.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 03:56:02'),
(161, '505', '/seait/505.php', 'http://localhost/seait/admin/dashboard.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 03:56:05'),
(162, '505', '/seait/505.php', 'http://localhost/seait/index.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 03:56:06'),
(163, '505', '/seait/505.php', 'http://localhost/seait/index.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 03:56:16'),
(164, '505', '/seait/505.php', 'http://localhost/seait/index.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 03:56:34'),
(165, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/training/problem-solving.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:38:13'),
(166, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/training/customer-service.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:38:16'),
(167, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/training/scenarios.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:38:17'),
(168, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/training/training-dashboard.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:38:18'),
(169, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/requests.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:38:18'),
(170, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/room-status.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:38:19'),
(171, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/room-status.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:38:19'),
(172, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/room-status.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:38:28'),
(173, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/room-status.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:38:30'),
(174, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/room-status.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:38:34'),
(175, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/room-status.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:38:36'),
(176, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/room-status.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:38:40'),
(177, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/room-status.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:38:44'),
(178, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/room-status.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:38:46'),
(179, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/room-status.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:38:48'),
(180, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/room-status.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:38:52'),
(181, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/room-status.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:38:54'),
(182, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/room-status.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:38:57'),
(183, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/room-status.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:38:59'),
(184, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/room-status.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:39:01'),
(185, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/room-status.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:39:04'),
(186, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:39:34'),
(187, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:40:05'),
(188, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/room-status.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:40:32'),
(189, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/room-status.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:40:34');
INSERT INTO `error_logs` (`id`, `error_type`, `requested_url`, `referrer`, `user_agent`, `ip_address`, `user_id`, `session_id`, `error_message`, `stack_trace`, `created_at`) VALUES
(190, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/index.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:40:35'),
(191, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/index.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:40:35'),
(192, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/index.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:40:35'),
(193, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/index.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:40:37'),
(194, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/index.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:40:38'),
(195, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/index.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:40:38'),
(196, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/index.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:40:38'),
(197, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/index.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:41:29'),
(198, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/index.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:41:29'),
(199, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/index.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:41:29'),
(200, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/index.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:42:39'),
(201, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/index.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:42:39'),
(202, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/index.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:42:39'),
(203, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/index.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:42:41'),
(204, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/index.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:42:43'),
(205, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/index.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:42:43'),
(206, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/index.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:42:43'),
(207, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/index.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:42:45'),
(208, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/index.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:42:47'),
(209, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/index.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:42:47'),
(210, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/index.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:42:47'),
(211, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/index.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:42:50'),
(212, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/index.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:42:52'),
(213, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/index.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:42:52'),
(214, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/index.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:42:52'),
(215, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/index.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:44:07'),
(216, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/index.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:44:07'),
(217, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/index.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:44:07'),
(218, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/manage-reservations.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:44:09'),
(219, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/manage-reservations.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:44:09'),
(220, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/manage-reservations.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:44:13'),
(221, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/check-in.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:44:14'),
(222, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/check-in.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:44:14'),
(223, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/check-out.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:44:16'),
(224, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/check-out.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:44:16'),
(225, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/service-management.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:44:17'),
(226, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/new-reservation.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:44:18'),
(227, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/service-management.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:44:19'),
(228, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/guest-management.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:44:22'),
(229, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/guest-management.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:44:22'),
(230, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/check-in.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:44:27'),
(231, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/check-in.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:44:27'),
(232, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/manage-reservations.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:44:30'),
(233, '505', '/seait/505.php', 'http://localhost/seait/pms/booking/modules/front-desk/manage-reservations.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 10:44:30'),
(234, '404', '/seait/human-resource/training-programs.php', 'http://localhost/seait/human-resource/view-faculty.php?id=cnZxkdSzAEv8Hj-aYJ923UcGe59gBjFveiiRaiUkN9s=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 15:08:06'),
(235, '404', '/seait/human-resource/assets/css/dark-mode.css', 'http://localhost/seait/human-resource/training-programs.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 15:08:06'),
(236, '404', '/seait/human-resource/assets/js/dark-mode.js', 'http://localhost/seait/human-resource/training-programs.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 15:08:06'),
(237, '404', '/seait/human-resource/hr-reports.php', 'http://localhost/seait/human-resource/view-faculty.php?id=cnZxkdSzAEv8Hj-aYJ923UcGe59gBjFveiiRaiUkN9s=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 15:08:12'),
(238, '404', '/seait/human-resource/assets/js/dark-mode.js', 'http://localhost/seait/human-resource/hr-reports.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 15:08:12'),
(239, '404', '/seait/human-resource/assets/css/dark-mode.css', 'http://localhost/seait/human-resource/hr-reports.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 15:08:12'),
(240, '404', '/seait/human-resource/analytics.php', 'http://localhost/seait/human-resource/view-faculty.php?id=cnZxkdSzAEv8Hj-aYJ923UcGe59gBjFveiiRaiUkN9s=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 15:08:16'),
(241, '404', '/seait/human-resource/assets/css/dark-mode.css', 'http://localhost/seait/human-resource/analytics.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 15:08:16'),
(242, '404', '/seait/human-resource/assets/js/dark-mode.js', 'http://localhost/seait/human-resource/analytics.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 15:08:16'),
(243, '404', '/seait/human-resource/training-programs.php', 'http://localhost/seait/human-resource/view-faculty.php?id=cnZxkdSzAEv8Hj-aYJ923UcGe59gBjFveiiRaiUkN9s=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 15:08:20'),
(244, '404', '/seait/human-resource/assets/css/dark-mode.css', 'http://localhost/seait/human-resource/training-programs.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 15:08:20'),
(245, '404', '/seait/human-resource/assets/js/dark-mode.js', 'http://localhost/seait/human-resource/training-programs.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 15:08:20'),
(246, '404', '/seait/human-resource/performance-reviews.php', 'http://localhost/seait/human-resource/view-faculty.php?id=cnZxkdSzAEv8Hj-aYJ923UcGe59gBjFveiiRaiUkN9s=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 15:08:24'),
(247, '404', '/seait/human-resource/assets/css/dark-mode.css', 'http://localhost/seait/human-resource/performance-reviews.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 15:08:24'),
(248, '404', '/seait/human-resource/assets/js/dark-mode.js', 'http://localhost/seait/human-resource/performance-reviews.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 15:08:24'),
(249, '404', '/seait/human-resource/interviews.php', 'http://localhost/seait/human-resource/view-faculty.php?id=cnZxkdSzAEv8Hj-aYJ923UcGe59gBjFveiiRaiUkN9s=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 15:08:27'),
(250, '404', '/seait/human-resource/assets/js/dark-mode.js', 'http://localhost/seait/human-resource/interviews.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 15:08:27'),
(251, '404', '/seait/human-resource/assets/css/dark-mode.css', 'http://localhost/seait/human-resource/interviews.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 15:08:27'),
(252, '404', '/seait/human-resource/applications.php', 'http://localhost/seait/human-resource/view-faculty.php?id=cnZxkdSzAEv8Hj-aYJ923UcGe59gBjFveiiRaiUkN9s=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 15:08:30'),
(253, '404', '/seait/human-resource/assets/css/dark-mode.css', 'http://localhost/seait/human-resource/applications.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 15:08:30'),
(254, '404', '/seait/human-resource/assets/js/dark-mode.js', 'http://localhost/seait/human-resource/applications.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 15:08:30'),
(255, '404', '/seait/human-resource/job-postings.php', 'http://localhost/seait/human-resource/view-faculty.php?id=cnZxkdSzAEv8Hj-aYJ923UcGe59gBjFveiiRaiUkN9s=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 15:08:33'),
(256, '404', '/seait/human-resource/assets/css/dark-mode.css', 'http://localhost/seait/human-resource/job-postings.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 15:08:33'),
(257, '404', '/seait/human-resource/assets/js/dark-mode.js', 'http://localhost/seait/human-resource/job-postings.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-08-31 15:08:33'),
(258, '404', '/seait/human-resource/view-department.php?id=aC9PyBxCW8iXtqOIm98mXTpwp3K8CCBvVMI7Wjmj3Hg=', 'http://localhost/seait/human-resource/manage-departments.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-01 04:12:27'),
(259, '404', '/seait/human-resource/assets/css/dark-mode.css', 'http://localhost/seait/human-resource/view-department.php?id=aC9PyBxCW8iXtqOIm98mXTpwp3K8CCBvVMI7Wjmj3Hg=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-01 04:12:27'),
(260, '404', '/seait/human-resource/assets/js/dark-mode.js', 'http://localhost/seait/human-resource/view-department.php?id=aC9PyBxCW8iXtqOIm98mXTpwp3K8CCBvVMI7Wjmj3Hg=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-01 04:12:27'),
(261, '404', '/seait/human-resource/assets/images/seait-logo.png', 'http://localhost/seait/human-resource/view-department.php?id=aC9PyBxCW8iXtqOIm98mXTpwp3K8CCBvVMI7Wjmj3Hg=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-01 04:12:27'),
(262, '404', '/seait/human-resource/leave-balances.php', 'http://localhost/seait/human-resource/leave-management.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-01 04:13:15'),
(263, '404', '/seait/human-resource/assets/css/dark-mode.css', 'http://localhost/seait/human-resource/leave-balances.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-01 04:13:15'),
(264, '404', '/seait/human-resource/assets/js/dark-mode.js', 'http://localhost/seait/human-resource/leave-balances.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-01 04:13:15'),
(265, '404', '/seait/human-resource/leave-balances.php', 'http://localhost/seait/human-resource/leave-management.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-01 04:13:28'),
(266, '404', '/seait/human-resource/assets/js/dark-mode.js', 'http://localhost/seait/human-resource/leave-balances.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-01 04:13:28'),
(267, '404', '/seait/human-resource/assets/css/dark-mode.css', 'http://localhost/seait/human-resource/leave-balances.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-01 04:13:28'),
(268, '505', '/seait/505.php', 'http://localhost/seait/human-resource/manage-departments.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-01 04:17:43'),
(269, '505', '/seait/505.php', 'http://localhost/seait/human-resource/leave-management.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-01 04:18:27'),
(270, '505', '/seait/505.php', 'http://localhost/seait/human-resource/leave-management.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-01 04:50:25'),
(271, '404', '/seait/human-resource/leave-reports.php', 'http://localhost/seait/human-resource/leave-management.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-01 04:51:34'),
(272, '404', '/seait/human-resource/assets/css/dark-mode.css', 'http://localhost/seait/human-resource/leave-reports.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-01 04:51:34'),
(273, '404', '/seait/human-resource/assets/js/dark-mode.js', 'http://localhost/seait/human-resource/leave-reports.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-01 04:51:34'),
(274, '505', '/seait/505.php', 'http://localhost/seait/human-resource/leave-management.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-01 04:51:37'),
(275, '505', '/seait/505.php', 'http://localhost/seait/human-resource/leave-management.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-01 04:52:52'),
(276, '404', '/seait/404.php', 'Direct Access', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-01 04:57:07'),
(277, '404', '/seait/404.php', 'Direct Access', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-01 04:58:40'),
(278, '505', '/seait/505.php', 'http://localhost/seait/human-resource/leave-management.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-01 04:59:17'),
(279, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-01 04:59:47'),
(280, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-01 05:00:18'),
(281, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-01 05:00:49'),
(282, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-01 05:01:20'),
(283, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-01 05:01:51'),
(284, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-01 05:02:22'),
(285, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-01 05:02:53'),
(286, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-01 05:03:24'),
(287, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-01 05:03:55'),
(288, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-01 05:04:26'),
(289, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-01 05:04:57'),
(290, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-01 05:05:28'),
(291, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-01 05:05:59'),
(292, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-01 05:06:30'),
(293, '505', '/seait/505.php', 'http://localhost/seait/human-resource/leave-management.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-01 05:07:01'),
(294, '404', '/seait/human-resource/leave-reports.php', 'http://localhost/seait/human-resource/leave-balances.php?tab=faculty&year=2025', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-01 06:20:17'),
(295, '404', '/seait/human-resource/assets/css/dark-mode.css', 'http://localhost/seait/human-resource/leave-reports.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-01 06:20:18'),
(296, '404', '/seait/human-resource/assets/js/dark-mode.js', 'http://localhost/seait/human-resource/leave-reports.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-01 06:20:18'),
(297, '404', '/seait/human-resource/leave-reports.php', 'http://localhost/seait/human-resource/leave-balances.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-01 06:21:21'),
(298, '404', '/seait/human-resource/assets/css/dark-mode.css', 'http://localhost/seait/human-resource/leave-reports.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-01 06:21:21'),
(299, '404', '/seait/human-resource/assets/js/dark-mode.js', 'http://localhost/seait/human-resource/leave-reports.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-01 06:21:21'),
(300, '200', '/seait/social-media/assets/images/news/news_68b8587ce5a2e8.96765703.jpg', 'http://localhost/seait/social-media/approved-posts.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-03 15:15:24'),
(301, '200', '/seait/social-media/assets/images/news/news_68b8587ce5a2e8.96765703.jpg', 'http://localhost/seait/social-media/approved-posts.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-03 15:15:38'),
(302, '200', '/seait/social-media/assets/images/news/news_68b8587ce5a2e8.96765703.jpg', 'http://localhost/seait/social-media/approved-posts.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-03 15:16:00'),
(303, '200', '/seait/social-media/assets/images/news/news_68b8587ce5a2e8.96765703.jpg', 'http://localhost/seait/social-media/approved-posts.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-03 15:18:56'),
(304, '200', '/seait/social-media/assets/images/news/news_68b8587ce5a2e8.96765703.jpg', 'http://localhost/seait/social-media/approved-posts.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-03 15:18:59'),
(305, '200', '/seait/social-media/assets/images/news/news_68b8587ce5a2e8.96765703.jpg', 'http://localhost/seait/social-media/approved-posts.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-03 15:19:01'),
(306, '200', '/seait/social-media/assets/images/news/news_68b8587ce5a2e8.96765703.jpg', 'http://localhost/seait/social-media/approved-posts.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-03 15:26:16'),
(307, '200', '/seait/social-media/assets/images/news/news_68b8587ce5a2e8.96765703.jpg', 'http://localhost/seait/social-media/approved-posts.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-03 15:27:59'),
(308, '200', '/seait/social-media/assets/images/news/news_68b8587ce5a2e8.96765703.jpg', 'http://localhost/seait/social-media/approved-posts.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-03 15:28:04'),
(309, '200', '/seait/social-media/assets/images/news/news_68b8587ce5a2e8.96765703.jpg', 'http://localhost/seait/social-media/approved-posts.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-03 15:28:43'),
(310, '200', '/seait/social-media/assets/images/news/news_68b8587ce5a2e8.96765703.jpg', 'http://localhost/seait/social-media/approved-posts.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-03 15:30:50'),
(311, '200', '/seait/social-media/assets/images/news/news_68b8587ce5a2e8.96765703.jpg', 'http://localhost/seait/social-media/approved-posts.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-03 15:31:07'),
(312, '200', '/seait/social-media/assets/images/news/news_68b8587ce5a2e8.96765703.jpg', 'http://localhost/seait/social-media/approved-posts.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-03 15:33:12'),
(313, '200', '/seait/social-media/assets/images/news/news_68b8587ce5a2e8.96765703.jpg', 'http://localhost/seait/social-media/approved-posts.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-03 15:41:43'),
(314, '200', '/seait/social-media/assets/images/news/news_68b8587ce5a2e8.96765703.jpg', 'http://localhost/seait/social-media/approved-posts.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-03 15:45:05'),
(315, '200', '/seait/social-media/assets/images/news/news_68b8587ce5a2e8.96765703.jpg', 'http://localhost/seait/social-media/approved-posts.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-03 15:47:13'),
(316, '200', '/seait/social-media/assets/images/news/news_68b8587ce5a2e8.96765703.jpg', 'http://localhost/seait/social-media/approved-posts.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-03 15:47:31'),
(317, '200', '/seait/social-media/assets/images/news/news_68b8587ce5a2e8.96765703.jpg', 'http://localhost/seait/social-media/approved-posts.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-03 15:49:18'),
(318, '200', '/seait/social-media/assets/images/news/news_68b8587ce5a2e8.96765703.jpg', 'http://localhost/seait/social-media/approved-posts.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-03 15:50:31'),
(319, '200', '/seait/social-media/assets/images/news/news_68b8587ce5a2e8.96765703.jpg', 'http://localhost/seait/social-media/approved-posts.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-03 15:50:40'),
(320, '200', '/seait/social-media/assets/images/news/news_68b8587ce5a2e8.96765703.jpg', 'http://localhost/seait/social-media/approved-posts.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-03 15:50:55'),
(321, '200', '/seait/social-media/assets/images/news/news_68b8587ce5a2e8.96765703.jpg', 'http://localhost/seait/social-media/approved-posts.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-03 15:51:24'),
(322, '200', '/seait/social-media/assets/images/news/news_68b8587ce5a2e8.96765703.jpg', 'http://localhost/seait/social-media/approved-posts.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-03 15:57:26'),
(323, '200', '/seait/social-media/assets/images/news/news_68b8587ce5a2e8.96765703.jpg', 'http://localhost/seait/social-media/approved-posts.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-03 16:04:35'),
(324, '200', '/seait/admin/api/check-db-connections.php', 'http://localhost/seait/admin/database-sync.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-03 16:15:34'),
(325, '200', '/seait/admin/api/check-db-connections.php', 'http://localhost/seait/admin/database-sync.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-03 16:15:36'),
(326, '200', '/seait/admin/api/check-db-connections.php', 'http://localhost/seait/admin/database-sync.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-03 16:15:43'),
(327, '200', '/seait/heads/room-allocation.php', 'http://localhost/seait/heads/schedule-management.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-03 18:08:51'),
(328, '200', '/seait/heads/assets/css/dark-mode.css', 'http://localhost/seait/heads/room-allocation.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-03 18:08:51'),
(329, '200', '/seait/heads/assets/js/dark-mode.js', 'http://localhost/seait/heads/room-allocation.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-03 18:08:51'),
(330, '200', '/seait/heads/assets/images/seait-logo.png', 'http://localhost/seait/heads/room-allocation.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-03 18:08:51'),
(331, '200', '/seait/heads/assets/images/favicon.ico', 'http://localhost/seait/heads/room-allocation.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-03 18:08:51'),
(332, '200', '/seait/heads/assets/images/seait-logo.png', 'http://localhost/seait/heads/room-allocation.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-03 18:08:51'),
(333, '200', '/seait/heads/assets/images/favicon.ico', 'http://localhost/seait/heads/room-allocation.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-03 18:08:51'),
(334, '200', '/seait/heads/lms-monitoring.php', 'http://localhost/seait/heads/schedule-management.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-03 18:14:33'),
(335, '200', '/seait/heads/assets/css/dark-mode.css', 'http://localhost/seait/heads/lms-monitoring.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-03 18:14:33'),
(336, '200', '/seait/heads/assets/js/dark-mode.js', 'http://localhost/seait/heads/lms-monitoring.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-03 18:14:33'),
(337, '200', '/seait/heads/assets/css/dark-mode.css', 'http://localhost/seait/heads/lms-monitoring.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-03 18:14:33'),
(338, '200', '/seait/heads/assets/images/seait-logo.png', 'http://localhost/seait/heads/lms-monitoring.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-03 18:14:33'),
(339, '200', '/seait/heads/assets/images/favicon.ico', 'http://localhost/seait/heads/lms-monitoring.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-03 18:14:33'),
(340, '200', '/seait/heads/assets/images/seait-logo.png', 'http://localhost/seait/heads/lms-monitoring.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-03 18:14:33'),
(341, '200', '/seait/heads/assets/images/favicon.ico', 'http://localhost/seait/heads/lms-monitoring.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-03 18:14:33'),
(342, '200', '/seait/admin/api/check-db-connections.php', 'http://localhost/seait/admin/database-sync.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-03 21:14:35'),
(343, '200', '/seait/admin/api/check-db-connections.php', 'http://localhost/seait/admin/database-sync.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-03 21:54:04'),
(344, '200', '/seait/admin/api/check-db-connections.php', 'http://localhost/seait/admin/database-sync.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-03 21:54:57'),
(345, '200', '/seait/admin/api/check-db-connections.php', 'http://localhost/seait/admin/database-sync.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-03 22:11:54'),
(346, '200', '/seait/admin/api/check-db-connections.php', 'http://localhost/seait/admin/database-sync.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-03 22:14:51'),
(347, '200', '/seait/admin/api/check-db-connections.php', 'http://localhost/seait/admin/database-sync.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-03 22:14:53'),
(348, '200', '/seait/login.php', 'http://localhost/seait/faculty/class_students.php?class_id=2In2e75oUWseP0Jwj1eZfY6p5gHPtZrkpW2-YcDDa-g=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-04 04:17:58'),
(349, '200', '/seait/login.php', 'http://localhost/seait/faculty/class_dashboard.php?class_id=s0JMvGTpk-N5L0Dl_BUmaSdjdbg8ht8E2rO4wazqylw=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-04 04:17:59'),
(350, '200', '/seait/students/lms_grades.php?class_id=mMSUtfk1g5THeL_43bOGgjB8EHHgN8cG8PtOhD7-u8I=', 'http://localhost/seait/students/lms_discussions.php?class_id=6BIZQak_pnnsX9vh68nq0vzD6JmxqDN8YWJITGXZbmY=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-04 04:37:48'),
(351, '200', '/seait/students/assets/css/dark-mode.css', 'http://localhost/seait/students/lms_grades.php?class_id=mMSUtfk1g5THeL_43bOGgjB8EHHgN8cG8PtOhD7-u8I=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-04 04:37:48'),
(352, '200', '/seait/students/assets/js/dark-mode.js', 'http://localhost/seait/students/lms_grades.php?class_id=mMSUtfk1g5THeL_43bOGgjB8EHHgN8cG8PtOhD7-u8I=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-04 04:37:48'),
(353, '200', '/seait/students/assets/images/seait-logo.png', 'http://localhost/seait/students/lms_grades.php?class_id=mMSUtfk1g5THeL_43bOGgjB8EHHgN8cG8PtOhD7-u8I=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-04 04:37:48'),
(354, '200', '/seait/students/assets/images/favicon.ico', 'http://localhost/seait/students/lms_grades.php?class_id=mMSUtfk1g5THeL_43bOGgjB8EHHgN8cG8PtOhD7-u8I=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-04 04:37:48'),
(355, '200', '/seait/students/assets/images/seait-logo.png', 'http://localhost/seait/students/lms_grades.php?class_id=mMSUtfk1g5THeL_43bOGgjB8EHHgN8cG8PtOhD7-u8I=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-04 04:37:48'),
(356, '200', '/seait/students/assets/images/favicon.ico', 'http://localhost/seait/students/lms_grades.php?class_id=mMSUtfk1g5THeL_43bOGgjB8EHHgN8cG8PtOhD7-u8I=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-04 04:37:48'),
(357, '200', '/seait/students/lms_resources.php?class_id=nfRBu3KUIFUgr22K5rd5QaH6B9gk0Ljlu9e1yo9DFAI=', 'http://localhost/seait/students/lms_discussions.php?class_id=6BIZQak_pnnsX9vh68nq0vzD6JmxqDN8YWJITGXZbmY=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-04 04:37:52'),
(358, '200', '/seait/students/assets/css/dark-mode.css', 'http://localhost/seait/students/lms_resources.php?class_id=nfRBu3KUIFUgr22K5rd5QaH6B9gk0Ljlu9e1yo9DFAI=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-04 04:37:52'),
(359, '200', '/seait/students/assets/js/dark-mode.js', 'http://localhost/seait/students/lms_resources.php?class_id=nfRBu3KUIFUgr22K5rd5QaH6B9gk0Ljlu9e1yo9DFAI=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-04 04:37:52');
INSERT INTO `error_logs` (`id`, `error_type`, `requested_url`, `referrer`, `user_agent`, `ip_address`, `user_id`, `session_id`, `error_message`, `stack_trace`, `created_at`) VALUES
(360, '200', '/seait/students/assets/images/seait-logo.png', 'http://localhost/seait/students/lms_resources.php?class_id=nfRBu3KUIFUgr22K5rd5QaH6B9gk0Ljlu9e1yo9DFAI=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-04 04:37:52'),
(361, '200', '/seait/students/assets/images/favicon.ico', 'http://localhost/seait/students/lms_resources.php?class_id=nfRBu3KUIFUgr22K5rd5QaH6B9gk0Ljlu9e1yo9DFAI=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-04 04:37:52'),
(362, '200', '/seait/students/assets/images/seait-logo.png', 'http://localhost/seait/students/lms_resources.php?class_id=nfRBu3KUIFUgr22K5rd5QaH6B9gk0Ljlu9e1yo9DFAI=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-04 04:37:52'),
(363, '200', '/seait/students/assets/images/favicon.ico', 'http://localhost/seait/students/lms_resources.php?class_id=nfRBu3KUIFUgr22K5rd5QaH6B9gk0Ljlu9e1yo9DFAI=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-04 04:37:52'),
(364, '200', '/seait/faculty/debug_evaluation_results.php', 'Direct Access', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-04 10:55:04'),
(365, '200', '/seait/faculty/assets/css/dark-mode.css', 'http://localhost/seait/faculty/debug_evaluation_results.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-04 10:55:04'),
(366, '200', '/seait/faculty/assets/js/dark-mode.js', 'http://localhost/seait/faculty/debug_evaluation_results.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-04 10:55:04'),
(367, '200', '/seait/faculty/assets/css/dark-mode.css', 'http://localhost/seait/faculty/debug_evaluation_results.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-04 10:55:04'),
(368, '200', '/seait/faculty/assets/images/seait-logo.png', 'http://localhost/seait/faculty/debug_evaluation_results.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-04 10:55:04'),
(369, '200', '/seait/faculty/assets/images/favicon.ico', 'http://localhost/seait/faculty/debug_evaluation_results.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-04 10:55:04'),
(370, '200', '/seait/faculty/assets/images/seait-logo.png', 'http://localhost/seait/faculty/debug_evaluation_results.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-04 10:55:04'),
(371, '200', '/seait/faculty/assets/images/favicon.ico', 'http://localhost/seait/faculty/debug_evaluation_results.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-04 10:55:04'),
(372, '200', '/seait/faculty/debug_evaluation_results.php', 'Direct Access', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-04 10:55:34'),
(373, '200', '/seait/faculty/assets/css/dark-mode.css', 'http://localhost/seait/faculty/debug_evaluation_results.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-04 10:55:34'),
(374, '200', '/seait/faculty/assets/js/dark-mode.js', 'http://localhost/seait/faculty/debug_evaluation_results.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-04 10:55:34'),
(375, '200', '/seait/faculty/assets/images/seait-logo.png', 'http://localhost/seait/faculty/debug_evaluation_results.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-04 10:55:34'),
(376, '200', '/seait/faculty/assets/images/favicon.ico', 'http://localhost/seait/faculty/debug_evaluation_results.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-04 10:55:34'),
(377, '200', '/seait/faculty/assets/images/seait-logo.png', 'http://localhost/seait/faculty/debug_evaluation_results.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-04 10:55:34'),
(378, '200', '/seait/faculty/assets/images/favicon.ico', 'http://localhost/seait/faculty/debug_evaluation_results.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-04 10:55:34'),
(379, '200', '/seait/faculty/trainings.php', 'http://localhost/seait/faculty/evaluation-results.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-04 10:56:55'),
(380, '200', '/seait/faculty/assets/css/dark-mode.css', 'http://localhost/seait/faculty/trainings.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-04 10:56:55'),
(381, '200', '/seait/faculty/assets/js/dark-mode.js', 'http://localhost/seait/faculty/trainings.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-04 10:56:55'),
(382, '200', '/seait/faculty/assets/css/dark-mode.css', 'http://localhost/seait/faculty/trainings.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-04 10:56:56'),
(383, '200', '/seait/faculty/assets/images/seait-logo.png', 'http://localhost/seait/faculty/trainings.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-04 10:56:56'),
(384, '200', '/seait/faculty/assets/images/favicon.ico', 'http://localhost/seait/faculty/trainings.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-04 10:56:56'),
(385, '200', '/seait/faculty/assets/images/seait-logo.png', 'http://localhost/seait/faculty/trainings.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-04 10:56:56'),
(386, '200', '/seait/faculty/assets/images/favicon.ico', 'http://localhost/seait/faculty/trainings.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-04 10:56:56'),
(387, '200', '/seait/faculty/api/generate-ai-questions.php', 'http://localhost/seait/faculty/view-quiz.php?id=N3lC2F73-7pCIYFp73iDl5-PoQkbUiU7KsIZaP4PJMk=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-04 17:25:50'),
(388, '200', '/seait/faculty/api/generate-ai-questions.php', 'http://localhost/seait/faculty/view-quiz.php?id=ebnhPQWx9yKnw09V_Yf616EHeNGpDjd7nr24qZO9RI0=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-04 17:31:00'),
(389, '200', '/seait/faculty/api/generate-ai-questions.php', 'http://localhost/seait/faculty/view-quiz.php?id=ebnhPQWx9yKnw09V_Yf616EHeNGpDjd7nr24qZO9RI0=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-04 17:34:04'),
(390, '200', '/seait/login.php', 'Direct Access', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-04 17:40:30'),
(391, '200', '/seait/faculty/api/generate-ai-questions.php', 'http://localhost/seait/faculty/view-quiz.php?id=B2WHRc6IXv4tygafvPyh3UmsLgp9ipDr5iY-HEOXRDc=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-04 17:45:22'),
(392, '200', '/seait/faculty/api/generate-ai-questions.php', 'http://localhost/seait/faculty/view-quiz.php?id=B2WHRc6IXv4tygafvPyh3UmsLgp9ipDr5iY-HEOXRDc=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-04 17:45:27'),
(393, '200', '/seait/faculty/api/generate-ai-questions.php', 'http://localhost/seait/faculty/view-quiz.php?id=WX5cRUQ4_twsuWMcO49VRFn5YtZbmMPE5533a8kVMKk=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-04 17:53:04'),
(394, '200', '/seait/faculty/api/export-syllabus-pdf.php', 'http://localhost/seait/faculty/comprehensive_syllabus_preview.php?class_id=0im-J9AWgrTe5Mo-ELbjoz39ccgBiMxVrjvfiWSRAYE=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-05 16:23:44'),
(395, '200', '/seait/faculty/api/assets/css/dark-mode.css', 'http://localhost/seait/faculty/api/export-syllabus-pdf.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-05 16:23:44'),
(396, '200', '/seait/faculty/api/assets/js/dark-mode.js', 'http://localhost/seait/faculty/api/export-syllabus-pdf.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-05 16:23:44'),
(397, '200', '/seait/faculty/api/assets/css/dark-mode.css', 'http://localhost/seait/faculty/api/export-syllabus-pdf.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-05 16:23:44'),
(398, '200', '/seait/faculty/api/assets/images/seait-logo.png', 'http://localhost/seait/faculty/api/export-syllabus-pdf.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-05 16:23:44'),
(399, '200', '/seait/faculty/api/assets/images/favicon.ico', 'http://localhost/seait/faculty/api/export-syllabus-pdf.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-05 16:23:44'),
(400, '200', '/seait/faculty/api/assets/images/seait-logo.png', 'http://localhost/seait/faculty/api/export-syllabus-pdf.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-05 16:23:44'),
(401, '200', '/seait/faculty/api/assets/images/favicon.ico', 'http://localhost/seait/faculty/api/export-syllabus-pdf.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-05 16:23:44'),
(402, '200', '/seait/faculty/api/class-management.php?message=No+class+ID+provided.&type=error', 'http://localhost/seait/faculty/comprehensive_syllabus_preview.php?class_id=0im-J9AWgrTe5Mo-ELbjoz39ccgBiMxVrjvfiWSRAYE=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-05 16:31:03'),
(403, '200', '/seait/faculty/api/assets/css/dark-mode.css', 'http://localhost/seait/faculty/api/class-management.php?message=No+class+ID+provided.&type=error', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-05 16:31:03'),
(404, '200', '/seait/faculty/api/assets/js/dark-mode.js', 'http://localhost/seait/faculty/api/class-management.php?message=No+class+ID+provided.&type=error', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-05 16:31:03'),
(405, '200', '/seait/faculty/api/assets/css/dark-mode.css', 'http://localhost/seait/faculty/api/class-management.php?message=No+class+ID+provided.&type=error', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-05 16:31:03'),
(406, '200', '/seait/faculty/api/assets/images/seait-logo.png', 'http://localhost/seait/faculty/api/class-management.php?message=No+class+ID+provided.&type=error', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-05 16:31:03'),
(407, '200', '/seait/faculty/api/assets/images/favicon.ico', 'http://localhost/seait/faculty/api/class-management.php?message=No+class+ID+provided.&type=error', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-05 16:31:03'),
(408, '200', '/seait/faculty/api/assets/images/seait-logo.png', 'http://localhost/seait/faculty/api/class-management.php?message=No+class+ID+provided.&type=error', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-05 16:31:03'),
(409, '200', '/seait/faculty/api/assets/images/favicon.ico', 'http://localhost/seait/faculty/api/class-management.php?message=No+class+ID+provided.&type=error', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-05 16:31:03'),
(410, '200', '/seait/faculty/api/class-management.php?message=No+class+ID+provided.&type=error', 'http://localhost/seait/faculty/comprehensive_syllabus_preview.php?class_id=0im-J9AWgrTe5Mo-ELbjoz39ccgBiMxVrjvfiWSRAYE=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-05 16:33:57'),
(411, '200', '/seait/faculty/api/assets/js/dark-mode.js', 'http://localhost/seait/faculty/api/class-management.php?message=No+class+ID+provided.&type=error', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-05 16:33:57'),
(412, '200', '/seait/faculty/api/assets/css/dark-mode.css', 'http://localhost/seait/faculty/api/class-management.php?message=No+class+ID+provided.&type=error', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-05 16:33:57'),
(413, '200', '/seait/faculty/api/assets/css/dark-mode.css', 'http://localhost/seait/faculty/api/class-management.php?message=No+class+ID+provided.&type=error', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-05 16:33:57'),
(414, '200', '/seait/faculty/api/assets/images/seait-logo.png', 'http://localhost/seait/faculty/api/class-management.php?message=No+class+ID+provided.&type=error', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-05 16:33:57'),
(415, '200', '/seait/faculty/api/assets/images/favicon.ico', 'http://localhost/seait/faculty/api/class-management.php?message=No+class+ID+provided.&type=error', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-05 16:33:57'),
(416, '200', '/seait/faculty/api/assets/images/seait-logo.png', 'http://localhost/seait/faculty/api/class-management.php?message=No+class+ID+provided.&type=error', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-05 16:33:57'),
(417, '200', '/seait/faculty/api/assets/images/favicon.ico', 'http://localhost/seait/faculty/api/class-management.php?message=No+class+ID+provided.&type=error', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-05 16:33:57'),
(418, '200', '/seait/faculty/class_assignments.php?class_id=5bSSgoSLSdfy2v93ufrVr3RT5fiyLCTB7bdexcujoGw=', 'http://localhost/seait/faculty/syllabus_topics.php?class_id=zKfqu7xzwJ1kksZHAQEFB0BOXNefJI6qpNySZxxrJGs=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:55:58'),
(419, '200', '/seait/faculty/assets/css/dark-mode.css', 'http://localhost/seait/faculty/class_assignments.php?class_id=5bSSgoSLSdfy2v93ufrVr3RT5fiyLCTB7bdexcujoGw=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:55:58'),
(420, '200', '/seait/faculty/assets/js/dark-mode.js', 'http://localhost/seait/faculty/class_assignments.php?class_id=5bSSgoSLSdfy2v93ufrVr3RT5fiyLCTB7bdexcujoGw=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:55:58'),
(421, '200', '/seait/faculty/assets/images/seait-logo.png', 'http://localhost/seait/faculty/class_assignments.php?class_id=5bSSgoSLSdfy2v93ufrVr3RT5fiyLCTB7bdexcujoGw=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:55:58'),
(422, '200', '/seait/faculty/assets/images/favicon.ico', 'http://localhost/seait/faculty/class_assignments.php?class_id=5bSSgoSLSdfy2v93ufrVr3RT5fiyLCTB7bdexcujoGw=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:55:58'),
(423, '200', '/seait/faculty/assets/images/seait-logo.png', 'http://localhost/seait/faculty/class_assignments.php?class_id=5bSSgoSLSdfy2v93ufrVr3RT5fiyLCTB7bdexcujoGw=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:55:58'),
(424, '200', '/seait/faculty/assets/images/favicon.ico', 'http://localhost/seait/faculty/class_assignments.php?class_id=5bSSgoSLSdfy2v93ufrVr3RT5fiyLCTB7bdexcujoGw=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:55:58'),
(425, '200', '/seait/faculty/class_discussions.php?class_id=t63HmV0QFYcAjphBz75FiM4WWuR5pJoQs5MPOFaYJMw=', 'http://localhost/seait/faculty/class_quizzes.php?class_id=lFboM3E9aVyvHjo877l391J8vOl57yCiMSmekmidaqs=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:56:27'),
(426, '200', '/seait/faculty/assets/css/dark-mode.css', 'http://localhost/seait/faculty/class_discussions.php?class_id=t63HmV0QFYcAjphBz75FiM4WWuR5pJoQs5MPOFaYJMw=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:56:27'),
(427, '200', '/seait/faculty/assets/js/dark-mode.js', 'http://localhost/seait/faculty/class_discussions.php?class_id=t63HmV0QFYcAjphBz75FiM4WWuR5pJoQs5MPOFaYJMw=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:56:27'),
(428, '200', '/seait/faculty/assets/images/seait-logo.png', 'http://localhost/seait/faculty/class_discussions.php?class_id=t63HmV0QFYcAjphBz75FiM4WWuR5pJoQs5MPOFaYJMw=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:56:27'),
(429, '200', '/seait/faculty/assets/images/favicon.ico', 'http://localhost/seait/faculty/class_discussions.php?class_id=t63HmV0QFYcAjphBz75FiM4WWuR5pJoQs5MPOFaYJMw=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:56:27'),
(430, '200', '/seait/faculty/assets/images/seait-logo.png', 'http://localhost/seait/faculty/class_discussions.php?class_id=t63HmV0QFYcAjphBz75FiM4WWuR5pJoQs5MPOFaYJMw=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:56:27'),
(431, '200', '/seait/faculty/assets/images/favicon.ico', 'http://localhost/seait/faculty/class_discussions.php?class_id=t63HmV0QFYcAjphBz75FiM4WWuR5pJoQs5MPOFaYJMw=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:56:27'),
(432, '200', '/seait/faculty/class_grades.php?class_id=kEzmskhAfYueLzuorIIKzH3pTsOfxlMOKruTYnHHSwY=', 'http://localhost/seait/faculty/class_quizzes.php?class_id=lFboM3E9aVyvHjo877l391J8vOl57yCiMSmekmidaqs=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:56:31'),
(433, '200', '/seait/faculty/assets/js/dark-mode.js', 'http://localhost/seait/faculty/class_grades.php?class_id=kEzmskhAfYueLzuorIIKzH3pTsOfxlMOKruTYnHHSwY=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:56:31'),
(434, '200', '/seait/faculty/assets/css/dark-mode.css', 'http://localhost/seait/faculty/class_grades.php?class_id=kEzmskhAfYueLzuorIIKzH3pTsOfxlMOKruTYnHHSwY=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:56:31'),
(435, '200', '/seait/faculty/assets/images/seait-logo.png', 'http://localhost/seait/faculty/class_grades.php?class_id=kEzmskhAfYueLzuorIIKzH3pTsOfxlMOKruTYnHHSwY=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:56:31'),
(436, '200', '/seait/faculty/assets/images/favicon.ico', 'http://localhost/seait/faculty/class_grades.php?class_id=kEzmskhAfYueLzuorIIKzH3pTsOfxlMOKruTYnHHSwY=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:56:31'),
(437, '200', '/seait/faculty/assets/images/seait-logo.png', 'http://localhost/seait/faculty/class_grades.php?class_id=kEzmskhAfYueLzuorIIKzH3pTsOfxlMOKruTYnHHSwY=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:56:31'),
(438, '200', '/seait/faculty/assets/images/favicon.ico', 'http://localhost/seait/faculty/class_grades.php?class_id=kEzmskhAfYueLzuorIIKzH3pTsOfxlMOKruTYnHHSwY=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:56:31'),
(439, '200', '/seait/faculty/class_evaluations.php?class_id=dBgzZnBIrNKyNSffq1OWhgkw8EwoILEDzwACtNQXoMk=', 'http://localhost/seait/faculty/class_quizzes.php?class_id=lFboM3E9aVyvHjo877l391J8vOl57yCiMSmekmidaqs=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:56:36'),
(440, '200', '/seait/faculty/assets/css/dark-mode.css', 'http://localhost/seait/faculty/class_evaluations.php?class_id=dBgzZnBIrNKyNSffq1OWhgkw8EwoILEDzwACtNQXoMk=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:56:36'),
(441, '200', '/seait/faculty/assets/js/dark-mode.js', 'http://localhost/seait/faculty/class_evaluations.php?class_id=dBgzZnBIrNKyNSffq1OWhgkw8EwoILEDzwACtNQXoMk=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:56:36'),
(442, '200', '/seait/faculty/assets/images/seait-logo.png', 'http://localhost/seait/faculty/class_evaluations.php?class_id=dBgzZnBIrNKyNSffq1OWhgkw8EwoILEDzwACtNQXoMk=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:56:36'),
(443, '200', '/seait/faculty/assets/images/favicon.ico', 'http://localhost/seait/faculty/class_evaluations.php?class_id=dBgzZnBIrNKyNSffq1OWhgkw8EwoILEDzwACtNQXoMk=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:56:36'),
(444, '200', '/seait/faculty/assets/images/seait-logo.png', 'http://localhost/seait/faculty/class_evaluations.php?class_id=dBgzZnBIrNKyNSffq1OWhgkw8EwoILEDzwACtNQXoMk=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:56:36'),
(445, '200', '/seait/faculty/assets/images/favicon.ico', 'http://localhost/seait/faculty/class_evaluations.php?class_id=dBgzZnBIrNKyNSffq1OWhgkw8EwoILEDzwACtNQXoMk=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:56:36'),
(446, '200', '/seait/faculty/class_calendar.php?class_id=OTtltanwBUJdkaN012xqnfvl9ewFYnsZ0MKKzVKI7QE=', 'http://localhost/seait/faculty/class_quizzes.php?class_id=lFboM3E9aVyvHjo877l391J8vOl57yCiMSmekmidaqs=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:56:40'),
(447, '200', '/seait/faculty/assets/js/dark-mode.js', 'http://localhost/seait/faculty/class_calendar.php?class_id=OTtltanwBUJdkaN012xqnfvl9ewFYnsZ0MKKzVKI7QE=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:56:40'),
(448, '200', '/seait/faculty/assets/css/dark-mode.css', 'http://localhost/seait/faculty/class_calendar.php?class_id=OTtltanwBUJdkaN012xqnfvl9ewFYnsZ0MKKzVKI7QE=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:56:40'),
(449, '200', '/seait/faculty/assets/images/seait-logo.png', 'http://localhost/seait/faculty/class_calendar.php?class_id=OTtltanwBUJdkaN012xqnfvl9ewFYnsZ0MKKzVKI7QE=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:56:40'),
(450, '200', '/seait/faculty/assets/images/favicon.ico', 'http://localhost/seait/faculty/class_calendar.php?class_id=OTtltanwBUJdkaN012xqnfvl9ewFYnsZ0MKKzVKI7QE=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:56:40'),
(451, '200', '/seait/faculty/assets/images/seait-logo.png', 'http://localhost/seait/faculty/class_calendar.php?class_id=OTtltanwBUJdkaN012xqnfvl9ewFYnsZ0MKKzVKI7QE=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:56:40'),
(452, '200', '/seait/faculty/assets/images/favicon.ico', 'http://localhost/seait/faculty/class_calendar.php?class_id=OTtltanwBUJdkaN012xqnfvl9ewFYnsZ0MKKzVKI7QE=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:56:40'),
(453, '200', '/seait/faculty/class_assignments.php?class_id=i340aL0Ntf0WH2Hl6W6rxF9lqBQ2htBC_RgMCVg1QTQ=', 'http://localhost/seait/faculty/syllabus_topics.php?class_id=F5183x5SVfYDLPbBJj-7378jZDAVWJ3Ko_HLFSJTJwM=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:57:16'),
(454, '200', '/seait/faculty/assets/css/dark-mode.css', 'http://localhost/seait/faculty/class_assignments.php?class_id=i340aL0Ntf0WH2Hl6W6rxF9lqBQ2htBC_RgMCVg1QTQ=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:57:16'),
(455, '200', '/seait/faculty/assets/js/dark-mode.js', 'http://localhost/seait/faculty/class_assignments.php?class_id=i340aL0Ntf0WH2Hl6W6rxF9lqBQ2htBC_RgMCVg1QTQ=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:57:16'),
(456, '200', '/seait/faculty/assets/images/seait-logo.png', 'http://localhost/seait/faculty/class_assignments.php?class_id=i340aL0Ntf0WH2Hl6W6rxF9lqBQ2htBC_RgMCVg1QTQ=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:57:16'),
(457, '200', '/seait/faculty/assets/images/favicon.ico', 'http://localhost/seait/faculty/class_assignments.php?class_id=i340aL0Ntf0WH2Hl6W6rxF9lqBQ2htBC_RgMCVg1QTQ=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:57:16'),
(458, '200', '/seait/faculty/assets/images/seait-logo.png', 'http://localhost/seait/faculty/class_assignments.php?class_id=i340aL0Ntf0WH2Hl6W6rxF9lqBQ2htBC_RgMCVg1QTQ=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:57:16'),
(459, '200', '/seait/faculty/assets/images/favicon.ico', 'http://localhost/seait/faculty/class_assignments.php?class_id=i340aL0Ntf0WH2Hl6W6rxF9lqBQ2htBC_RgMCVg1QTQ=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:57:16'),
(460, '200', '/seait/faculty/class_discussions.php?class_id=qKErWEfmot-2T2pJmoUWtC_lYqS2E5jOHlvI0xVCueQ=', 'http://localhost/seait/faculty/class_quizzes.php?class_id=xaXEzWDx8gS-OLUOGgbARj150XNx1Lmd4P5cUu2tsu0=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:57:35'),
(461, '200', '/seait/faculty/assets/css/dark-mode.css', 'http://localhost/seait/faculty/class_discussions.php?class_id=qKErWEfmot-2T2pJmoUWtC_lYqS2E5jOHlvI0xVCueQ=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:57:35'),
(462, '200', '/seait/faculty/assets/js/dark-mode.js', 'http://localhost/seait/faculty/class_discussions.php?class_id=qKErWEfmot-2T2pJmoUWtC_lYqS2E5jOHlvI0xVCueQ=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:57:35'),
(463, '200', '/seait/faculty/assets/images/seait-logo.png', 'http://localhost/seait/faculty/class_discussions.php?class_id=qKErWEfmot-2T2pJmoUWtC_lYqS2E5jOHlvI0xVCueQ=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:57:35'),
(464, '200', '/seait/faculty/assets/images/favicon.ico', 'http://localhost/seait/faculty/class_discussions.php?class_id=qKErWEfmot-2T2pJmoUWtC_lYqS2E5jOHlvI0xVCueQ=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:57:35'),
(465, '200', '/seait/faculty/assets/images/seait-logo.png', 'http://localhost/seait/faculty/class_discussions.php?class_id=qKErWEfmot-2T2pJmoUWtC_lYqS2E5jOHlvI0xVCueQ=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:57:35'),
(466, '200', '/seait/faculty/assets/images/favicon.ico', 'http://localhost/seait/faculty/class_discussions.php?class_id=qKErWEfmot-2T2pJmoUWtC_lYqS2E5jOHlvI0xVCueQ=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:57:35'),
(467, '200', '/seait/faculty/class_discussions.php?class_id=qKErWEfmot-2T2pJmoUWtC_lYqS2E5jOHlvI0xVCueQ=', 'http://localhost/seait/faculty/class_quizzes.php?class_id=xaXEzWDx8gS-OLUOGgbARj150XNx1Lmd4P5cUu2tsu0=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:57:46'),
(468, '200', '/seait/faculty/assets/css/dark-mode.css', 'http://localhost/seait/faculty/class_discussions.php?class_id=qKErWEfmot-2T2pJmoUWtC_lYqS2E5jOHlvI0xVCueQ=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:57:46'),
(469, '200', '/seait/faculty/assets/js/dark-mode.js', 'http://localhost/seait/faculty/class_discussions.php?class_id=qKErWEfmot-2T2pJmoUWtC_lYqS2E5jOHlvI0xVCueQ=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:57:46'),
(470, '200', '/seait/faculty/assets/images/seait-logo.png', 'http://localhost/seait/faculty/class_discussions.php?class_id=qKErWEfmot-2T2pJmoUWtC_lYqS2E5jOHlvI0xVCueQ=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:57:46'),
(471, '200', '/seait/faculty/assets/images/favicon.ico', 'http://localhost/seait/faculty/class_discussions.php?class_id=qKErWEfmot-2T2pJmoUWtC_lYqS2E5jOHlvI0xVCueQ=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:57:46'),
(472, '200', '/seait/faculty/assets/images/seait-logo.png', 'http://localhost/seait/faculty/class_discussions.php?class_id=qKErWEfmot-2T2pJmoUWtC_lYqS2E5jOHlvI0xVCueQ=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:57:46'),
(473, '200', '/seait/faculty/assets/images/favicon.ico', 'http://localhost/seait/faculty/class_discussions.php?class_id=qKErWEfmot-2T2pJmoUWtC_lYqS2E5jOHlvI0xVCueQ=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:57:46'),
(474, '200', '/seait/faculty/class_grades.php?class_id=PBweUUZCcLWRuksJaFQjpvhWMZSPejDq0X17C6XM1ac=', 'http://localhost/seait/faculty/class_quizzes.php?class_id=xaXEzWDx8gS-OLUOGgbARj150XNx1Lmd4P5cUu2tsu0=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:57:49'),
(475, '200', '/seait/faculty/assets/css/dark-mode.css', 'http://localhost/seait/faculty/class_grades.php?class_id=PBweUUZCcLWRuksJaFQjpvhWMZSPejDq0X17C6XM1ac=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:57:49'),
(476, '200', '/seait/faculty/assets/js/dark-mode.js', 'http://localhost/seait/faculty/class_grades.php?class_id=PBweUUZCcLWRuksJaFQjpvhWMZSPejDq0X17C6XM1ac=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:57:49'),
(477, '200', '/seait/faculty/assets/images/seait-logo.png', 'http://localhost/seait/faculty/class_grades.php?class_id=PBweUUZCcLWRuksJaFQjpvhWMZSPejDq0X17C6XM1ac=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:57:49'),
(478, '200', '/seait/faculty/assets/images/favicon.ico', 'http://localhost/seait/faculty/class_grades.php?class_id=PBweUUZCcLWRuksJaFQjpvhWMZSPejDq0X17C6XM1ac=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:57:49'),
(479, '200', '/seait/faculty/assets/images/seait-logo.png', 'http://localhost/seait/faculty/class_grades.php?class_id=PBweUUZCcLWRuksJaFQjpvhWMZSPejDq0X17C6XM1ac=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:57:49'),
(480, '200', '/seait/faculty/assets/images/favicon.ico', 'http://localhost/seait/faculty/class_grades.php?class_id=PBweUUZCcLWRuksJaFQjpvhWMZSPejDq0X17C6XM1ac=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:57:49'),
(481, '200', '/seait/faculty/class_evaluations.php?class_id=UrtZIIgUjXKvhSMnLiUCXlWZ5IvK3o_K-ENiDoMCF4I=', 'http://localhost/seait/faculty/class_quizzes.php?class_id=xaXEzWDx8gS-OLUOGgbARj150XNx1Lmd4P5cUu2tsu0=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:57:59'),
(482, '200', '/seait/faculty/assets/css/dark-mode.css', 'http://localhost/seait/faculty/class_evaluations.php?class_id=UrtZIIgUjXKvhSMnLiUCXlWZ5IvK3o_K-ENiDoMCF4I=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:57:59'),
(483, '200', '/seait/faculty/assets/js/dark-mode.js', 'http://localhost/seait/faculty/class_evaluations.php?class_id=UrtZIIgUjXKvhSMnLiUCXlWZ5IvK3o_K-ENiDoMCF4I=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:57:59'),
(484, '200', '/seait/faculty/assets/images/seait-logo.png', 'http://localhost/seait/faculty/class_evaluations.php?class_id=UrtZIIgUjXKvhSMnLiUCXlWZ5IvK3o_K-ENiDoMCF4I=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:58:00'),
(485, '200', '/seait/faculty/assets/images/favicon.ico', 'http://localhost/seait/faculty/class_evaluations.php?class_id=UrtZIIgUjXKvhSMnLiUCXlWZ5IvK3o_K-ENiDoMCF4I=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:58:00'),
(486, '200', '/seait/faculty/assets/images/seait-logo.png', 'http://localhost/seait/faculty/class_evaluations.php?class_id=UrtZIIgUjXKvhSMnLiUCXlWZ5IvK3o_K-ENiDoMCF4I=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:58:00'),
(487, '200', '/seait/faculty/assets/images/favicon.ico', 'http://localhost/seait/faculty/class_evaluations.php?class_id=UrtZIIgUjXKvhSMnLiUCXlWZ5IvK3o_K-ENiDoMCF4I=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:58:00'),
(488, '200', '/seait/faculty/class_calendar.php?class_id=yxjtCyHVQLRLP3tc7spgxMTINIMCryce2ku18F-rqso=', 'http://localhost/seait/faculty/class_quizzes.php?class_id=xaXEzWDx8gS-OLUOGgbARj150XNx1Lmd4P5cUu2tsu0=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:58:15'),
(489, '200', '/seait/faculty/assets/css/dark-mode.css', 'http://localhost/seait/faculty/class_calendar.php?class_id=yxjtCyHVQLRLP3tc7spgxMTINIMCryce2ku18F-rqso=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:58:15'),
(490, '200', '/seait/faculty/assets/js/dark-mode.js', 'http://localhost/seait/faculty/class_calendar.php?class_id=yxjtCyHVQLRLP3tc7spgxMTINIMCryce2ku18F-rqso=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:58:15'),
(491, '200', '/seait/faculty/assets/images/seait-logo.png', 'http://localhost/seait/faculty/class_calendar.php?class_id=yxjtCyHVQLRLP3tc7spgxMTINIMCryce2ku18F-rqso=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:58:15'),
(492, '200', '/seait/faculty/assets/images/favicon.ico', 'http://localhost/seait/faculty/class_calendar.php?class_id=yxjtCyHVQLRLP3tc7spgxMTINIMCryce2ku18F-rqso=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:58:15'),
(493, '200', '/seait/faculty/assets/images/seait-logo.png', 'http://localhost/seait/faculty/class_calendar.php?class_id=yxjtCyHVQLRLP3tc7spgxMTINIMCryce2ku18F-rqso=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:58:15'),
(494, '200', '/seait/faculty/assets/images/favicon.ico', 'http://localhost/seait/faculty/class_calendar.php?class_id=yxjtCyHVQLRLP3tc7spgxMTINIMCryce2ku18F-rqso=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-06 05:58:15'),
(495, '200', '/seait/faculty/view-assignment.php?assignment_id=dT-BDcECNFVUmqlEQcIO46-Oz9zsIKgeqvkggeY7Sd8=', 'http://localhost/seait/faculty/class_assignments.php?class_id=B_IQLjNdXfnFx6qTp2J8dsq5smArP-m6EyMg8d2MiHY=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-07 05:45:31'),
(496, '200', '/seait/faculty/assets/css/dark-mode.css', 'http://localhost/seait/faculty/view-assignment.php?assignment_id=dT-BDcECNFVUmqlEQcIO46-Oz9zsIKgeqvkggeY7Sd8=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-07 05:45:31'),
(497, '200', '/seait/faculty/assets/js/dark-mode.js', 'http://localhost/seait/faculty/view-assignment.php?assignment_id=dT-BDcECNFVUmqlEQcIO46-Oz9zsIKgeqvkggeY7Sd8=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-07 05:45:31'),
(498, '200', '/seait/faculty/assets/css/dark-mode.css', 'http://localhost/seait/faculty/view-assignment.php?assignment_id=dT-BDcECNFVUmqlEQcIO46-Oz9zsIKgeqvkggeY7Sd8=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-07 05:45:31'),
(499, '200', '/seait/faculty/assets/images/seait-logo.png', 'http://localhost/seait/faculty/view-assignment.php?assignment_id=dT-BDcECNFVUmqlEQcIO46-Oz9zsIKgeqvkggeY7Sd8=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-07 05:45:32'),
(500, '200', '/seait/faculty/assets/images/favicon.ico', 'http://localhost/seait/faculty/view-assignment.php?assignment_id=dT-BDcECNFVUmqlEQcIO46-Oz9zsIKgeqvkggeY7Sd8=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-07 05:45:32'),
(501, '200', '/seait/faculty/assets/images/seait-logo.png', 'http://localhost/seait/faculty/view-assignment.php?assignment_id=dT-BDcECNFVUmqlEQcIO46-Oz9zsIKgeqvkggeY7Sd8=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-07 05:45:32'),
(502, '200', '/seait/faculty/assets/images/favicon.ico', 'http://localhost/seait/faculty/view-assignment.php?assignment_id=dT-BDcECNFVUmqlEQcIO46-Oz9zsIKgeqvkggeY7Sd8=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-07 05:45:32'),
(503, '200', '/seait/faculty/edit-assignment.php?assignment_id=oMZQiG5N1O7zSrnezC32cbbn5JrbYOWL1wa9VKO0LOs=', 'http://localhost/seait/faculty/class_assignments.php?class_id=B_IQLjNdXfnFx6qTp2J8dsq5smArP-m6EyMg8d2MiHY=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-07 05:46:16'),
(504, '200', '/seait/faculty/assets/css/dark-mode.css', 'http://localhost/seait/faculty/edit-assignment.php?assignment_id=oMZQiG5N1O7zSrnezC32cbbn5JrbYOWL1wa9VKO0LOs=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-07 05:46:16'),
(505, '200', '/seait/faculty/assets/js/dark-mode.js', 'http://localhost/seait/faculty/edit-assignment.php?assignment_id=oMZQiG5N1O7zSrnezC32cbbn5JrbYOWL1wa9VKO0LOs=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-07 05:46:16'),
(506, '200', '/seait/faculty/assets/images/seait-logo.png', 'http://localhost/seait/faculty/edit-assignment.php?assignment_id=oMZQiG5N1O7zSrnezC32cbbn5JrbYOWL1wa9VKO0LOs=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-07 05:46:16'),
(507, '200', '/seait/faculty/assets/images/favicon.ico', 'http://localhost/seait/faculty/edit-assignment.php?assignment_id=oMZQiG5N1O7zSrnezC32cbbn5JrbYOWL1wa9VKO0LOs=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-07 05:46:16'),
(508, '200', '/seait/faculty/assets/images/seait-logo.png', 'http://localhost/seait/faculty/edit-assignment.php?assignment_id=oMZQiG5N1O7zSrnezC32cbbn5JrbYOWL1wa9VKO0LOs=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-07 05:46:16');
INSERT INTO `error_logs` (`id`, `error_type`, `requested_url`, `referrer`, `user_agent`, `ip_address`, `user_id`, `session_id`, `error_message`, `stack_trace`, `created_at`) VALUES
(509, '200', '/seait/faculty/assets/images/favicon.ico', 'http://localhost/seait/faculty/edit-assignment.php?assignment_id=oMZQiG5N1O7zSrnezC32cbbn5JrbYOWL1wa9VKO0LOs=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-07 05:46:16'),
(510, '200', '/seait/faculty/view-assignment.php?assignment_id=droZESsYyJ3XDrvVUUM1ZjpUMnRDr7li39TaOtoKFgc=', 'http://localhost/seait/faculty/class_assignments.php?class_id=31eB9dVnY2BHzSOW2Y5Yh_LE22N_sQok2fjkjcNHsfY=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-07 06:19:44'),
(511, '200', '/seait/faculty/assets/css/dark-mode.css', 'http://localhost/seait/faculty/view-assignment.php?assignment_id=droZESsYyJ3XDrvVUUM1ZjpUMnRDr7li39TaOtoKFgc=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-07 06:19:44'),
(512, '200', '/seait/faculty/assets/js/dark-mode.js', 'http://localhost/seait/faculty/view-assignment.php?assignment_id=droZESsYyJ3XDrvVUUM1ZjpUMnRDr7li39TaOtoKFgc=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-07 06:19:44'),
(513, '200', '/seait/faculty/assets/css/dark-mode.css', 'http://localhost/seait/faculty/view-assignment.php?assignment_id=droZESsYyJ3XDrvVUUM1ZjpUMnRDr7li39TaOtoKFgc=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-07 06:19:44'),
(514, '200', '/seait/faculty/assets/images/seait-logo.png', 'http://localhost/seait/faculty/view-assignment.php?assignment_id=droZESsYyJ3XDrvVUUM1ZjpUMnRDr7li39TaOtoKFgc=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-07 06:19:44'),
(515, '200', '/seait/faculty/assets/images/favicon.ico', 'http://localhost/seait/faculty/view-assignment.php?assignment_id=droZESsYyJ3XDrvVUUM1ZjpUMnRDr7li39TaOtoKFgc=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-07 06:19:44'),
(516, '200', '/seait/faculty/assets/images/seait-logo.png', 'http://localhost/seait/faculty/view-assignment.php?assignment_id=droZESsYyJ3XDrvVUUM1ZjpUMnRDr7li39TaOtoKFgc=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-07 06:19:44'),
(517, '200', '/seait/faculty/assets/images/favicon.ico', 'http://localhost/seait/faculty/view-assignment.php?assignment_id=droZESsYyJ3XDrvVUUM1ZjpUMnRDr7li39TaOtoKFgc=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-07 06:19:44'),
(518, '200', '/seait/faculty/view-assignment.php?assignment_id=w-LFP9_t7IFOr6kuL8hGv55btG4tUxzmgQkhmdEXQWM=', 'http://localhost/seait/faculty/class_assignments.php?class_id=q42P0b9x9ErXcpXlpeUd-ecW61yI3L-I6HZMu-PMeEE=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-07 06:27:42'),
(519, '200', '/seait/faculty/assets/css/dark-mode.css', 'http://localhost/seait/faculty/view-assignment.php?assignment_id=w-LFP9_t7IFOr6kuL8hGv55btG4tUxzmgQkhmdEXQWM=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-07 06:27:42'),
(520, '200', '/seait/faculty/assets/js/dark-mode.js', 'http://localhost/seait/faculty/view-assignment.php?assignment_id=w-LFP9_t7IFOr6kuL8hGv55btG4tUxzmgQkhmdEXQWM=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-07 06:27:42'),
(521, '200', '/seait/faculty/assets/images/seait-logo.png', 'http://localhost/seait/faculty/view-assignment.php?assignment_id=w-LFP9_t7IFOr6kuL8hGv55btG4tUxzmgQkhmdEXQWM=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-07 06:27:42'),
(522, '200', '/seait/faculty/assets/images/favicon.ico', 'http://localhost/seait/faculty/view-assignment.php?assignment_id=w-LFP9_t7IFOr6kuL8hGv55btG4tUxzmgQkhmdEXQWM=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-07 06:27:42'),
(523, '200', '/seait/faculty/assets/images/seait-logo.png', 'http://localhost/seait/faculty/view-assignment.php?assignment_id=w-LFP9_t7IFOr6kuL8hGv55btG4tUxzmgQkhmdEXQWM=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-07 06:27:42'),
(524, '200', '/seait/faculty/assets/images/favicon.ico', 'http://localhost/seait/faculty/view-assignment.php?assignment_id=w-LFP9_t7IFOr6kuL8hGv55btG4tUxzmgQkhmdEXQWM=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-07 06:27:42'),
(525, '200', '/seait/students/portfolio.php', 'http://localhost/seait/students/profile.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 08:43:52'),
(526, '200', '/seait/students/assets/css/dark-mode.css', 'http://localhost/seait/students/portfolio.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 08:43:52'),
(527, '200', '/seait/students/assets/js/dark-mode.js', 'http://localhost/seait/students/portfolio.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 08:43:52'),
(528, '200', '/seait/students/assets/css/dark-mode.css', 'http://localhost/seait/students/portfolio.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 08:43:52'),
(529, '200', '/seait/students/assets/images/seait-logo.png', 'http://localhost/seait/students/portfolio.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 08:43:52'),
(530, '200', '/seait/students/assets/images/favicon.ico', 'http://localhost/seait/students/portfolio.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 08:43:52'),
(531, '200', '/seait/students/assets/images/seait-logo.png', 'http://localhost/seait/students/portfolio.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 08:43:52'),
(532, '200', '/seait/students/assets/images/favicon.ico', 'http://localhost/seait/students/portfolio.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 08:43:52'),
(533, '200', '/seait/students/lms_grades.php?class_id=86Tnn0RmFtBTdkDrsvrJSjc1x6zCzIv3fVWl0SPMGoI=', 'http://localhost/seait/students/lms_quizzes.php?class_id=Ysgu00TwS6zV2QurAV-0Fbvx6qEfiPCC_KggwhSygXE=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 11:01:38'),
(534, '200', '/seait/students/assets/css/dark-mode.css', 'http://localhost/seait/students/lms_grades.php?class_id=86Tnn0RmFtBTdkDrsvrJSjc1x6zCzIv3fVWl0SPMGoI=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 11:01:38'),
(535, '200', '/seait/students/assets/js/dark-mode.js', 'http://localhost/seait/students/lms_grades.php?class_id=86Tnn0RmFtBTdkDrsvrJSjc1x6zCzIv3fVWl0SPMGoI=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 11:01:38'),
(536, '200', '/seait/students/assets/images/seait-logo.png', 'http://localhost/seait/students/lms_grades.php?class_id=86Tnn0RmFtBTdkDrsvrJSjc1x6zCzIv3fVWl0SPMGoI=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 11:01:38'),
(537, '200', '/seait/students/assets/images/favicon.ico', 'http://localhost/seait/students/lms_grades.php?class_id=86Tnn0RmFtBTdkDrsvrJSjc1x6zCzIv3fVWl0SPMGoI=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 11:01:38'),
(538, '200', '/seait/students/assets/images/seait-logo.png', 'http://localhost/seait/students/lms_grades.php?class_id=86Tnn0RmFtBTdkDrsvrJSjc1x6zCzIv3fVWl0SPMGoI=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 11:01:38'),
(539, '200', '/seait/students/assets/images/favicon.ico', 'http://localhost/seait/students/lms_grades.php?class_id=86Tnn0RmFtBTdkDrsvrJSjc1x6zCzIv3fVWl0SPMGoI=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 11:01:38'),
(540, '200', '/seait/students/lms_resources.php?class_id=6Hsd3KX88dl8cgQRXZWWQQjBgELlKTDm8W28-fwwkho=', 'http://localhost/seait/students/lms_quizzes.php?class_id=Ysgu00TwS6zV2QurAV-0Fbvx6qEfiPCC_KggwhSygXE=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 11:01:43'),
(541, '200', '/seait/students/assets/css/dark-mode.css', 'http://localhost/seait/students/lms_resources.php?class_id=6Hsd3KX88dl8cgQRXZWWQQjBgELlKTDm8W28-fwwkho=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 11:01:43'),
(542, '200', '/seait/students/assets/js/dark-mode.js', 'http://localhost/seait/students/lms_resources.php?class_id=6Hsd3KX88dl8cgQRXZWWQQjBgELlKTDm8W28-fwwkho=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 11:01:43'),
(543, '200', '/seait/students/assets/images/seait-logo.png', 'http://localhost/seait/students/lms_resources.php?class_id=6Hsd3KX88dl8cgQRXZWWQQjBgELlKTDm8W28-fwwkho=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 11:01:43'),
(544, '200', '/seait/students/assets/images/favicon.ico', 'http://localhost/seait/students/lms_resources.php?class_id=6Hsd3KX88dl8cgQRXZWWQQjBgELlKTDm8W28-fwwkho=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 11:01:43'),
(545, '200', '/seait/students/assets/images/seait-logo.png', 'http://localhost/seait/students/lms_resources.php?class_id=6Hsd3KX88dl8cgQRXZWWQQjBgELlKTDm8W28-fwwkho=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 11:01:43'),
(546, '200', '/seait/students/assets/images/favicon.ico', 'http://localhost/seait/students/lms_resources.php?class_id=6Hsd3KX88dl8cgQRXZWWQQjBgELlKTDm8W28-fwwkho=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 11:01:43'),
(547, '200', '/seait/students/lms_grades.php?class_id=90DCi21asDg7mUrHE_dgwIRrAR_DheJd4CRow54X8rE=', 'http://localhost/seait/students/lms_quizzes.php?class_id=WXEgBLKvXRnYQ1DGbHoG0hAPnB7PgSJaIHu2RdrZzR4=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 11:03:04'),
(548, '200', '/seait/students/assets/css/dark-mode.css', 'http://localhost/seait/students/lms_grades.php?class_id=90DCi21asDg7mUrHE_dgwIRrAR_DheJd4CRow54X8rE=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 11:03:04'),
(549, '200', '/seait/students/assets/js/dark-mode.js', 'http://localhost/seait/students/lms_grades.php?class_id=90DCi21asDg7mUrHE_dgwIRrAR_DheJd4CRow54X8rE=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 11:03:04'),
(550, '200', '/seait/students/assets/images/seait-logo.png', 'http://localhost/seait/students/lms_grades.php?class_id=90DCi21asDg7mUrHE_dgwIRrAR_DheJd4CRow54X8rE=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 11:03:04'),
(551, '200', '/seait/students/assets/images/favicon.ico', 'http://localhost/seait/students/lms_grades.php?class_id=90DCi21asDg7mUrHE_dgwIRrAR_DheJd4CRow54X8rE=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 11:03:05'),
(552, '200', '/seait/students/assets/images/seait-logo.png', 'http://localhost/seait/students/lms_grades.php?class_id=90DCi21asDg7mUrHE_dgwIRrAR_DheJd4CRow54X8rE=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 11:03:05'),
(553, '200', '/seait/students/assets/images/favicon.ico', 'http://localhost/seait/students/lms_grades.php?class_id=90DCi21asDg7mUrHE_dgwIRrAR_DheJd4CRow54X8rE=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 11:03:05'),
(554, '200', '/seait/students/lms_resources.php?class_id=_bh2ZC4weeuEd8sW8mcwtAQCIX6YtR1x_o-R5GgRxkw=', 'http://localhost/seait/students/lms_quizzes.php?class_id=WXEgBLKvXRnYQ1DGbHoG0hAPnB7PgSJaIHu2RdrZzR4=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 11:03:09'),
(555, '200', '/seait/students/assets/css/dark-mode.css', 'http://localhost/seait/students/lms_resources.php?class_id=_bh2ZC4weeuEd8sW8mcwtAQCIX6YtR1x_o-R5GgRxkw=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 11:03:09'),
(556, '200', '/seait/students/assets/js/dark-mode.js', 'http://localhost/seait/students/lms_resources.php?class_id=_bh2ZC4weeuEd8sW8mcwtAQCIX6YtR1x_o-R5GgRxkw=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 11:03:09'),
(557, '200', '/seait/students/assets/images/seait-logo.png', 'http://localhost/seait/students/lms_resources.php?class_id=_bh2ZC4weeuEd8sW8mcwtAQCIX6YtR1x_o-R5GgRxkw=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 11:03:09'),
(558, '200', '/seait/students/assets/images/favicon.ico', 'http://localhost/seait/students/lms_resources.php?class_id=_bh2ZC4weeuEd8sW8mcwtAQCIX6YtR1x_o-R5GgRxkw=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 11:03:09'),
(559, '200', '/seait/students/assets/images/seait-logo.png', 'http://localhost/seait/students/lms_resources.php?class_id=_bh2ZC4weeuEd8sW8mcwtAQCIX6YtR1x_o-R5GgRxkw=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 11:03:09'),
(560, '200', '/seait/students/assets/images/favicon.ico', 'http://localhost/seait/students/lms_resources.php?class_id=_bh2ZC4weeuEd8sW8mcwtAQCIX6YtR1x_o-R5GgRxkw=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 11:03:09'),
(561, '200', '/seait/students/assignment_detail.php?assignment_id=11&class_id=DMtX70ZFRNzXo2KccUqF9Q9JtuqYj8Uz7x5gdY908wA=', 'http://localhost/seait/students/lms_assignments.php?class_id=OaWFcZG5IRKThNUzYbpTF1eBcH7VM5pXcY3RDWoRdMo=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 11:39:13'),
(562, '200', '/seait/students/assets/css/dark-mode.css', 'http://localhost/seait/students/assignment_detail.php?assignment_id=11&class_id=DMtX70ZFRNzXo2KccUqF9Q9JtuqYj8Uz7x5gdY908wA=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 11:39:13'),
(563, '200', '/seait/students/assets/js/dark-mode.js', 'http://localhost/seait/students/assignment_detail.php?assignment_id=11&class_id=DMtX70ZFRNzXo2KccUqF9Q9JtuqYj8Uz7x5gdY908wA=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 11:39:13'),
(564, '200', '/seait/students/assets/css/dark-mode.css', 'http://localhost/seait/students/assignment_detail.php?assignment_id=11&class_id=DMtX70ZFRNzXo2KccUqF9Q9JtuqYj8Uz7x5gdY908wA=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 11:39:13'),
(565, '200', '/seait/students/assets/images/seait-logo.png', 'http://localhost/seait/students/assignment_detail.php?assignment_id=11&class_id=DMtX70ZFRNzXo2KccUqF9Q9JtuqYj8Uz7x5gdY908wA=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 11:39:13'),
(566, '200', '/seait/students/assets/images/favicon.ico', 'http://localhost/seait/students/assignment_detail.php?assignment_id=11&class_id=DMtX70ZFRNzXo2KccUqF9Q9JtuqYj8Uz7x5gdY908wA=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 11:39:13'),
(567, '200', '/seait/students/assets/images/seait-logo.png', 'http://localhost/seait/students/assignment_detail.php?assignment_id=11&class_id=DMtX70ZFRNzXo2KccUqF9Q9JtuqYj8Uz7x5gdY908wA=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 11:39:13'),
(568, '200', '/seait/students/assets/images/favicon.ico', 'http://localhost/seait/students/assignment_detail.php?assignment_id=11&class_id=DMtX70ZFRNzXo2KccUqF9Q9JtuqYj8Uz7x5gdY908wA=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 11:39:13'),
(569, '200', '/seait/students/assignment_detail.php?assignment_id=11&class_id=ogjk7Pjf0ZWMekLaPGmr9luIv7zqeaAPEt5XbTuWDfs=', 'http://localhost/seait/students/lms_assignments.php?class_id=fQA1_-HjPTIPRRRA8yvUui_9Y8ivtiLiTC8taWbZo88=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 11:41:31'),
(570, '200', '/seait/students/assets/css/dark-mode.css', 'http://localhost/seait/students/assignment_detail.php?assignment_id=11&class_id=ogjk7Pjf0ZWMekLaPGmr9luIv7zqeaAPEt5XbTuWDfs=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 11:41:31'),
(571, '200', '/seait/students/assets/js/dark-mode.js', 'http://localhost/seait/students/assignment_detail.php?assignment_id=11&class_id=ogjk7Pjf0ZWMekLaPGmr9luIv7zqeaAPEt5XbTuWDfs=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 11:41:31'),
(572, '200', '/seait/students/assets/images/seait-logo.png', 'http://localhost/seait/students/assignment_detail.php?assignment_id=11&class_id=ogjk7Pjf0ZWMekLaPGmr9luIv7zqeaAPEt5XbTuWDfs=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 11:41:31'),
(573, '200', '/seait/students/assets/images/favicon.ico', 'http://localhost/seait/students/assignment_detail.php?assignment_id=11&class_id=ogjk7Pjf0ZWMekLaPGmr9luIv7zqeaAPEt5XbTuWDfs=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 11:41:31'),
(574, '200', '/seait/students/assets/images/seait-logo.png', 'http://localhost/seait/students/assignment_detail.php?assignment_id=11&class_id=ogjk7Pjf0ZWMekLaPGmr9luIv7zqeaAPEt5XbTuWDfs=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 11:41:31'),
(575, '200', '/seait/students/assets/images/favicon.ico', 'http://localhost/seait/students/assignment_detail.php?assignment_id=11&class_id=ogjk7Pjf0ZWMekLaPGmr9luIv7zqeaAPEt5XbTuWDfs=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 11:41:31'),
(576, '200', '/seait/uploads/assignments/programming_quiz_answers_pablo_miguel.pdf', 'http://localhost/seait/students/assignment_detail.php?assignment_id=11&class_id=ZhCooLcOBY8MXX0z8MVyHj-WNxn9nm7tjk971KXqP48=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 11:56:40'),
(577, '200', '/seait/students/quiz-results.php?submission_id=1', 'http://localhost/seait/students/take-quiz-focused.php?quiz_id=6h70nJAeiPG4zjySqLwvFSQkcspusz7RgCIJaVWW6Uo=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 17:36:37'),
(578, '200', '/seait/students/assets/css/dark-mode.css', 'http://localhost/seait/students/quiz-results.php?submission_id=1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 17:36:37'),
(579, '200', '/seait/students/assets/js/dark-mode.js', 'http://localhost/seait/students/quiz-results.php?submission_id=1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 17:36:37'),
(580, '200', '/seait/students/assets/css/dark-mode.css', 'http://localhost/seait/students/quiz-results.php?submission_id=1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 17:36:37'),
(581, '200', '/seait/students/assets/images/seait-logo.png', 'http://localhost/seait/students/quiz-results.php?submission_id=1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 17:36:37'),
(582, '200', '/seait/students/assets/images/favicon.ico', 'http://localhost/seait/students/quiz-results.php?submission_id=1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 17:36:37'),
(583, '200', '/seait/students/assets/images/seait-logo.png', 'http://localhost/seait/students/quiz-results.php?submission_id=1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 17:36:37'),
(584, '200', '/seait/students/assets/images/favicon.ico', 'http://localhost/seait/students/quiz-results.php?submission_id=1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-08 17:36:37'),
(585, '200', '/seait/faculty/assets/images/school_logo_1757318301.png', 'http://localhost/seait/faculty/comprehensive_syllabus_preview.php?class_id=x_OGDVC9_-cYVR5CpS0bHpwlbMhDom0yk32MgdUuqAo=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 04:49:49'),
(586, '200', '/seait/faculty/assets/images/school_logo_1757318301.png', 'http://localhost/seait/faculty/comprehensive_syllabus_preview.php?class_id=x_OGDVC9_-cYVR5CpS0bHpwlbMhDom0yk32MgdUuqAo=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 04:52:26'),
(587, '200', '/seait/faculty/assets/images/school_logo_1757318301.png', 'http://localhost/seait/faculty/comprehensive_syllabus_preview.php?class_id=val9CJfnX67__mUuMf7Yjo0covTTa_LIlBSgcwPLaGg=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 04:57:12'),
(588, '200', '/seait/faculty/assets/images/school_logo_1757318301.png', 'http://localhost/seait/faculty/comprehensive_syllabus_preview.php?class_id=val9CJfnX67__mUuMf7Yjo0covTTa_LIlBSgcwPLaGg=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 04:59:23'),
(589, '200', '/seait/faculty/assets/images/school_logo_1757318301.png', 'http://localhost/seait/faculty/comprehensive_syllabus_preview.php?class_id=val9CJfnX67__mUuMf7Yjo0covTTa_LIlBSgcwPLaGg=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 05:00:10'),
(590, '200', '/seait/students/assets/images/school_logo_1757318301.png', 'http://localhost/seait/students/class_syllabus.php?class_id=l5zZawTtd-Hg_AMNBhGIT_NNk6BYhv35VBhFybVK1ZE=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 17:24:38'),
(591, '200', '/seait/students/class_announcements.php?class_id=92T5zWDxmuI-AJuy1P08qexj3m_I3P8VtCARAIPvGLY=', 'http://localhost/seait/students/class_dashboard.php?class_id=MM-zEAmJWfhE_CAgSsNXZnPUx77VHC0He_ngwEq6TzI=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 17:31:20'),
(592, '200', '/seait/students/assets/css/dark-mode.css', 'http://localhost/seait/students/class_announcements.php?class_id=92T5zWDxmuI-AJuy1P08qexj3m_I3P8VtCARAIPvGLY=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 17:31:20'),
(593, '200', '/seait/students/assets/js/dark-mode.js', 'http://localhost/seait/students/class_announcements.php?class_id=92T5zWDxmuI-AJuy1P08qexj3m_I3P8VtCARAIPvGLY=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 17:31:20'),
(594, '200', '/seait/students/assets/css/dark-mode.css', 'http://localhost/seait/students/class_announcements.php?class_id=92T5zWDxmuI-AJuy1P08qexj3m_I3P8VtCARAIPvGLY=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 17:31:20'),
(595, '200', '/seait/students/assets/images/seait-logo.png', 'http://localhost/seait/students/class_announcements.php?class_id=92T5zWDxmuI-AJuy1P08qexj3m_I3P8VtCARAIPvGLY=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 17:31:20'),
(596, '200', '/seait/students/assets/images/favicon.ico', 'http://localhost/seait/students/class_announcements.php?class_id=92T5zWDxmuI-AJuy1P08qexj3m_I3P8VtCARAIPvGLY=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 17:31:20'),
(597, '200', '/seait/students/assets/images/seait-logo.png', 'http://localhost/seait/students/class_announcements.php?class_id=92T5zWDxmuI-AJuy1P08qexj3m_I3P8VtCARAIPvGLY=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 17:31:20'),
(598, '200', '/seait/students/assets/images/favicon.ico', 'http://localhost/seait/students/class_announcements.php?class_id=92T5zWDxmuI-AJuy1P08qexj3m_I3P8VtCARAIPvGLY=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 17:31:20'),
(599, '200', '/seait/students/quiz-results.php?submission_id=', 'http://localhost/seait/students/lms_quizzes.php?class_id=UO4NgTblGzoWAaH3P1niols-RjAskmvgG1qDOqyEXmY=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 17:37:19'),
(600, '200', '/seait/students/assets/css/dark-mode.css', 'http://localhost/seait/students/quiz-results.php?submission_id=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 17:37:19'),
(601, '200', '/seait/students/assets/js/dark-mode.js', 'http://localhost/seait/students/quiz-results.php?submission_id=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 17:37:19'),
(602, '200', '/seait/students/assets/images/seait-logo.png', 'http://localhost/seait/students/quiz-results.php?submission_id=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 17:37:19'),
(603, '200', '/seait/students/assets/images/favicon.ico', 'http://localhost/seait/students/quiz-results.php?submission_id=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 17:37:19'),
(604, '200', '/seait/students/assets/images/seait-logo.png', 'http://localhost/seait/students/quiz-results.php?submission_id=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 17:37:19'),
(605, '200', '/seait/students/assets/images/favicon.ico', 'http://localhost/seait/students/quiz-results.php?submission_id=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 17:37:19'),
(606, '200', '/seait/students/quiz-results.php?submission_id=', 'http://localhost/seait/students/lms_quizzes.php?class_id=UO4NgTblGzoWAaH3P1niols-RjAskmvgG1qDOqyEXmY=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 17:37:27'),
(607, '200', '/seait/students/assets/css/dark-mode.css', 'http://localhost/seait/students/quiz-results.php?submission_id=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 17:37:27'),
(608, '200', '/seait/students/assets/js/dark-mode.js', 'http://localhost/seait/students/quiz-results.php?submission_id=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 17:37:27'),
(609, '200', '/seait/students/assets/images/seait-logo.png', 'http://localhost/seait/students/quiz-results.php?submission_id=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 17:37:27'),
(610, '200', '/seait/students/assets/images/favicon.ico', 'http://localhost/seait/students/quiz-results.php?submission_id=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 17:37:27'),
(611, '200', '/seait/students/assets/images/seait-logo.png', 'http://localhost/seait/students/quiz-results.php?submission_id=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 17:37:27'),
(612, '200', '/seait/students/assets/images/favicon.ico', 'http://localhost/seait/students/quiz-results.php?submission_id=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 17:37:27'),
(613, '200', '/seait/students/lms_grades.php?class_id=vWw3soy0LjPMQIOKichDVOrLHlm10SJk52p7zh-QiGY=', 'http://localhost/seait/students/lms_discussions.php?class_id=PqqymlI6YFZujFgNvWgBZn2rLbM-GtP3cG1vH2ObMu0=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 17:57:50'),
(614, '200', '/seait/students/assets/css/dark-mode.css', 'http://localhost/seait/students/lms_grades.php?class_id=vWw3soy0LjPMQIOKichDVOrLHlm10SJk52p7zh-QiGY=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 17:57:50'),
(615, '200', '/seait/students/assets/js/dark-mode.js', 'http://localhost/seait/students/lms_grades.php?class_id=vWw3soy0LjPMQIOKichDVOrLHlm10SJk52p7zh-QiGY=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 17:57:50'),
(616, '200', '/seait/students/assets/css/dark-mode.css', 'http://localhost/seait/students/lms_grades.php?class_id=vWw3soy0LjPMQIOKichDVOrLHlm10SJk52p7zh-QiGY=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 17:57:50'),
(617, '200', '/seait/students/assets/images/seait-logo.png', 'http://localhost/seait/students/lms_grades.php?class_id=vWw3soy0LjPMQIOKichDVOrLHlm10SJk52p7zh-QiGY=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 17:57:50'),
(618, '200', '/seait/students/assets/images/favicon.ico', 'http://localhost/seait/students/lms_grades.php?class_id=vWw3soy0LjPMQIOKichDVOrLHlm10SJk52p7zh-QiGY=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 17:57:50'),
(619, '200', '/seait/students/assets/images/seait-logo.png', 'http://localhost/seait/students/lms_grades.php?class_id=vWw3soy0LjPMQIOKichDVOrLHlm10SJk52p7zh-QiGY=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 17:57:50'),
(620, '200', '/seait/students/assets/images/favicon.ico', 'http://localhost/seait/students/lms_grades.php?class_id=vWw3soy0LjPMQIOKichDVOrLHlm10SJk52p7zh-QiGY=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 17:57:50'),
(621, '200', '/seait/students/lms_resources.php?class_id=qx5v4wrJfB9f0Poe44LVttzFJ5UpgbI7IBavFbOpudU=', 'http://localhost/seait/students/lms_discussions.php?class_id=PqqymlI6YFZujFgNvWgBZn2rLbM-GtP3cG1vH2ObMu0=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 17:57:53'),
(622, '200', '/seait/students/assets/css/dark-mode.css', 'http://localhost/seait/students/lms_resources.php?class_id=qx5v4wrJfB9f0Poe44LVttzFJ5UpgbI7IBavFbOpudU=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 17:57:53'),
(623, '200', '/seait/students/assets/js/dark-mode.js', 'http://localhost/seait/students/lms_resources.php?class_id=qx5v4wrJfB9f0Poe44LVttzFJ5UpgbI7IBavFbOpudU=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 17:57:53'),
(624, '200', '/seait/students/assets/images/seait-logo.png', 'http://localhost/seait/students/lms_resources.php?class_id=qx5v4wrJfB9f0Poe44LVttzFJ5UpgbI7IBavFbOpudU=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 17:57:53'),
(625, '200', '/seait/students/assets/images/favicon.ico', 'http://localhost/seait/students/lms_resources.php?class_id=qx5v4wrJfB9f0Poe44LVttzFJ5UpgbI7IBavFbOpudU=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 17:57:53'),
(626, '200', '/seait/students/assets/images/seait-logo.png', 'http://localhost/seait/students/lms_resources.php?class_id=qx5v4wrJfB9f0Poe44LVttzFJ5UpgbI7IBavFbOpudU=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 17:57:53'),
(627, '200', '/seait/students/assets/images/favicon.ico', 'http://localhost/seait/students/lms_resources.php?class_id=qx5v4wrJfB9f0Poe44LVttzFJ5UpgbI7IBavFbOpudU=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 17:57:53'),
(628, '200', '/seait/students/lms_grades.php?class_id=TYJ7PEDLWkw99rYpHKMsCfdLs2shMX2exG0o1HGBSRo=', 'http://localhost/seait/students/lms_quizzes.php?class_id=5QC41pDfkhULEPjmnRo0uh0NzYGA2ZocUd35Qx-BrtU=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 18:00:44'),
(629, '200', '/seait/students/assets/css/dark-mode.css', 'http://localhost/seait/students/lms_grades.php?class_id=TYJ7PEDLWkw99rYpHKMsCfdLs2shMX2exG0o1HGBSRo=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 18:00:44'),
(630, '200', '/seait/students/assets/js/dark-mode.js', 'http://localhost/seait/students/lms_grades.php?class_id=TYJ7PEDLWkw99rYpHKMsCfdLs2shMX2exG0o1HGBSRo=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 18:00:44'),
(631, '200', '/seait/students/assets/images/seait-logo.png', 'http://localhost/seait/students/lms_grades.php?class_id=TYJ7PEDLWkw99rYpHKMsCfdLs2shMX2exG0o1HGBSRo=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 18:00:44'),
(632, '200', '/seait/students/assets/images/favicon.ico', 'http://localhost/seait/students/lms_grades.php?class_id=TYJ7PEDLWkw99rYpHKMsCfdLs2shMX2exG0o1HGBSRo=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 18:00:44'),
(633, '200', '/seait/students/assets/images/seait-logo.png', 'http://localhost/seait/students/lms_grades.php?class_id=TYJ7PEDLWkw99rYpHKMsCfdLs2shMX2exG0o1HGBSRo=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 18:00:44'),
(634, '200', '/seait/students/assets/images/favicon.ico', 'http://localhost/seait/students/lms_grades.php?class_id=TYJ7PEDLWkw99rYpHKMsCfdLs2shMX2exG0o1HGBSRo=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 18:00:44'),
(635, '200', '/seait/api/get-lesson-notes.php?lesson_id=16&student_id=2', 'http://localhost/seait/students/view_lesson.php?lesson_id=16&class_id=qtSVMGRHwaYYk5GJXIfa3zz3W1-GgJsmEYzTuiInZV0=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 18:27:52'),
(636, '200', '/seait/api/update-lesson-progress.php', 'http://localhost/seait/students/view_lesson.php?lesson_id=16&class_id=qtSVMGRHwaYYk5GJXIfa3zz3W1-GgJsmEYzTuiInZV0=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 18:28:22'),
(637, '200', '/seait/api/update-lesson-progress.php', 'http://localhost/seait/students/view_lesson.php?lesson_id=16&class_id=qtSVMGRHwaYYk5GJXIfa3zz3W1-GgJsmEYzTuiInZV0=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 18:28:28'),
(638, '200', '/seait/api/update-lesson-progress.php', 'http://localhost/seait/students/view_lesson.php?lesson_id=16&class_id=qtSVMGRHwaYYk5GJXIfa3zz3W1-GgJsmEYzTuiInZV0=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 18:28:33'),
(639, '200', '/seait/api/save-lesson-notes.php', 'http://localhost/seait/students/view_lesson.php?lesson_id=16&class_id=qtSVMGRHwaYYk5GJXIfa3zz3W1-GgJsmEYzTuiInZV0=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 18:28:41'),
(640, '200', '/seait/api/update-lesson-progress.php', 'http://localhost/seait/students/view_lesson.php?lesson_id=16&class_id=qtSVMGRHwaYYk5GJXIfa3zz3W1-GgJsmEYzTuiInZV0=', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 18:28:52'),
(641, '200', '/seait/api/update-lesson-progress.php', 'http://localhost/seait/students/view_lesson.php?lesson_id=16&class_id=qtSVMGRHwaYYk5GJXIfa3zz3W1-GgJsmEYzTuiInZV0=', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 18:29:22'),
(642, '200', '/seait/api/update-lesson-progress.php', 'http://localhost/seait/students/view_lesson.php?lesson_id=16&class_id=qtSVMGRHwaYYk5GJXIfa3zz3W1-GgJsmEYzTuiInZV0=', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 18:29:52'),
(643, '200', '/seait/api/update-lesson-progress.php', 'http://localhost/seait/students/view_lesson.php?lesson_id=16&class_id=qtSVMGRHwaYYk5GJXIfa3zz3W1-GgJsmEYzTuiInZV0=', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 18:30:22'),
(644, '200', '/seait/api/update-lesson-progress.php', 'http://localhost/seait/students/view_lesson.php?lesson_id=16&class_id=qtSVMGRHwaYYk5GJXIfa3zz3W1-GgJsmEYzTuiInZV0=', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 18:30:52'),
(645, '200', '/seait/api/update-lesson-progress.php', 'http://localhost/seait/students/view_lesson.php?lesson_id=16&class_id=qtSVMGRHwaYYk5GJXIfa3zz3W1-GgJsmEYzTuiInZV0=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 18:31:13'),
(646, '200', '/seait/api/update-lesson-progress.php', 'http://localhost/seait/students/view_lesson.php?lesson_id=16&class_id=qtSVMGRHwaYYk5GJXIfa3zz3W1-GgJsmEYzTuiInZV0=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 18:31:14'),
(647, '200', '/seait/api/update-lesson-progress.php', 'http://localhost/seait/students/view_lesson.php?lesson_id=16&class_id=qtSVMGRHwaYYk5GJXIfa3zz3W1-GgJsmEYzTuiInZV0=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 18:31:25'),
(648, '200', '/seait/api/ai-lesson-assistant.php', 'http://localhost/seait/students/view_lesson.php?lesson_id=16&class_id=qtSVMGRHwaYYk5GJXIfa3zz3W1-GgJsmEYzTuiInZV0=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 18:31:29'),
(649, '200', '/seait/api/update-lesson-progress.php', 'http://localhost/seait/students/view_lesson.php?lesson_id=16&class_id=qtSVMGRHwaYYk5GJXIfa3zz3W1-GgJsmEYzTuiInZV0=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 18:31:55'),
(650, '200', '/seait/api/update-lesson-progress.php', 'http://localhost/seait/students/view_lesson.php?lesson_id=16&class_id=qtSVMGRHwaYYk5GJXIfa3zz3W1-GgJsmEYzTuiInZV0=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 18:32:25'),
(651, '200', '/seait/api/update-lesson-progress.php', 'http://localhost/seait/students/view_lesson.php?lesson_id=16&class_id=qtSVMGRHwaYYk5GJXIfa3zz3W1-GgJsmEYzTuiInZV0=', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-09 18:32:55'),
(652, '200', '/seait/faculty/upload-image.php', 'http://localhost/seait/faculty/create-lesson.php', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-10 07:52:26'),
(653, '200', '/seait/faculty/upload-image.php', 'http://localhost/seait/faculty/create-lesson.php', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-10 07:56:19'),
(654, '200', '/seait/faculty/upload-image.php', 'http://localhost/seait/faculty/create-lesson.php', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-10 07:57:54'),
(655, '200', '/seait/faculty/upload-image.php', 'http://localhost/seait/faculty/create-lesson.php', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-10 08:00:38');
INSERT INTO `error_logs` (`id`, `error_type`, `requested_url`, `referrer`, `user_agent`, `ip_address`, `user_id`, `session_id`, `error_message`, `stack_trace`, `created_at`) VALUES
(656, '200', '/seait/faculty/upload-image.php', 'http://localhost/seait/faculty/create-lesson.php', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-10 08:02:32'),
(657, '200', '/seait/faculty/upload-image.php', 'http://localhost/seait/faculty/create-lesson.php', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-10 08:03:59'),
(658, '200', '/seait/faculty/upload-image.php', 'http://localhost/seait/faculty/create-lesson.php', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-10 08:05:33'),
(659, '200', '/seait/faculty/test-upload-simple.php', 'http://localhost/seait/faculty/create-lesson.php', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-10 08:07:19'),
(660, '200', '/seait/faculty/upload-image.php', 'http://localhost/seait/faculty/create-lesson.php', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-10 08:08:38'),
(661, '200', '/seait/faculty/upload-image.php', 'http://localhost/seait/faculty/create-lesson.php', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-10 08:10:15'),
(662, '200', '/seait/faculty/test-upload-minimal.php', 'http://localhost/seait/faculty/create-lesson.php', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-10 08:11:45'),
(663, '505', '/seait/505.php', 'http://localhost/seait/human-resource/manage-departments.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-10 08:57:54'),
(664, '200', '/facallti/admin/dashboard.php', 'http://localhost/facallti/admin/login.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-11 05:02:48'),
(665, '200', '/facallti/admin/assets/css/dark-mode.css', 'http://localhost/facallti/admin/dashboard.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-11 05:02:48'),
(666, '200', '/facallti/admin/assets/js/dark-mode.js', 'http://localhost/facallti/admin/dashboard.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-11 05:02:48'),
(667, '200', '/facallti/admin/assets/css/dark-mode.css', 'http://localhost/facallti/admin/dashboard.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-11 05:02:48'),
(668, '200', '/facallti/admin/assets/images/seait-logo.png', 'http://localhost/facallti/admin/dashboard.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-11 05:02:48'),
(669, '200', '/facallti/admin/assets/images/favicon.ico', 'http://localhost/facallti/admin/dashboard.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-11 05:02:48'),
(670, '200', '/facallti/admin/assets/images/seait-logo.png', 'http://localhost/facallti/admin/dashboard.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-11 05:02:48'),
(671, '200', '/facallti/admin/assets/images/favicon.ico', 'http://localhost/facallti/admin/dashboard.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-11 05:02:48'),
(672, '505', '/seait/505.php', 'Direct Access', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-11 05:06:37'),
(673, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-11 05:07:08'),
(674, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-11 05:07:39'),
(675, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-11 05:08:10'),
(676, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-11 05:08:34'),
(677, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-11 05:08:34'),
(678, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-11 05:09:05'),
(679, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-11 05:09:36'),
(680, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-11 05:09:54'),
(681, '505', '/seait/505.php', 'http://localhost/seait/505.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-11 05:09:55'),
(682, '200', '/facallti/admin/dashboard.php', 'http://localhost/facallti/admin/login.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-11 05:09:58'),
(683, '200', '/facallti/admin/assets/css/dark-mode.css', 'http://localhost/facallti/admin/dashboard.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-11 05:09:58'),
(684, '200', '/facallti/admin/assets/js/dark-mode.js', 'http://localhost/facallti/admin/dashboard.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-11 05:09:58'),
(685, '200', '/facallti/admin/assets/images/seait-logo.png', 'http://localhost/facallti/admin/dashboard.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-11 05:09:58'),
(686, '200', '/facallti/admin/assets/images/favicon.ico', 'http://localhost/facallti/admin/dashboard.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-11 05:09:58'),
(687, '200', '/facallti/admin/assets/images/seait-logo.png', 'http://localhost/facallti/admin/dashboard.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-11 05:09:58'),
(688, '200', '/facallti/admin/assets/images/favicon.ico', 'http://localhost/facallti/admin/dashboard.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-11 05:09:58'),
(689, '200', '/facallti/admin/login.php', 'http://localhost/facallti/admin/qr-codes.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-11 05:10:00'),
(690, '200', '/facallti/admin/assets/css/dark-mode.css', 'http://localhost/facallti/admin/login.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-11 05:10:00'),
(691, '200', '/facallti/admin/assets/js/dark-mode.js', 'http://localhost/facallti/admin/login.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-11 05:10:00'),
(692, '200', '/facallti/admin/assets/images/seait-logo.png', 'http://localhost/facallti/admin/login.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-11 05:10:00'),
(693, '200', '/facallti/admin/assets/images/favicon.ico', 'http://localhost/facallti/admin/login.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-11 05:10:00'),
(694, '200', '/facallti/admin/assets/images/seait-logo.png', 'http://localhost/facallti/admin/login.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-11 05:10:00'),
(695, '200', '/facallti/admin/assets/images/favicon.ico', 'http://localhost/facallti/admin/login.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-11 05:10:00'),
(696, '200', '/facallti/admin/qr-codes.php', 'http://localhost/facallti/admin/teachers.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-11 05:10:01'),
(697, '200', '/facallti/admin/assets/css/dark-mode.css', 'http://localhost/facallti/admin/qr-codes.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-11 05:10:01'),
(698, '200', '/facallti/admin/assets/js/dark-mode.js', 'http://localhost/facallti/admin/qr-codes.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-11 05:10:01'),
(699, '200', '/facallti/admin/assets/images/seait-logo.png', 'http://localhost/facallti/admin/qr-codes.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-11 05:10:01'),
(700, '200', '/facallti/admin/assets/images/favicon.ico', 'http://localhost/facallti/admin/qr-codes.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-11 05:10:01'),
(701, '200', '/facallti/admin/assets/images/seait-logo.png', 'http://localhost/facallti/admin/qr-codes.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-11 05:10:01'),
(702, '200', '/facallti/admin/assets/images/favicon.ico', 'http://localhost/facallti/admin/qr-codes.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-11 05:10:01'),
(703, '200', '/facallti/admin/teachers.php', 'http://localhost/facallti/admin/dashboard.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-11 05:10:02'),
(704, '200', '/facallti/admin/assets/css/dark-mode.css', 'http://localhost/facallti/admin/teachers.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-11 05:10:02'),
(705, '200', '/facallti/admin/assets/js/dark-mode.js', 'http://localhost/facallti/admin/teachers.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-11 05:10:02'),
(706, '200', '/facallti/admin/assets/images/seait-logo.png', 'http://localhost/facallti/admin/teachers.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-11 05:10:02'),
(707, '200', '/facallti/admin/assets/images/favicon.ico', 'http://localhost/facallti/admin/teachers.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-11 05:10:02'),
(708, '200', '/facallti/admin/assets/images/seait-logo.png', 'http://localhost/facallti/admin/teachers.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-11 05:10:02'),
(709, '200', '/facallti/admin/assets/images/favicon.ico', 'http://localhost/facallti/admin/teachers.php', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-11 05:10:02'),
(710, '200', '/facallti/facallti/facallti', 'http://localhost/facallti/', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-11 05:13:47'),
(711, '200', '/facallti/facallti/assets/css/dark-mode.css', 'http://localhost/facallti/facallti/facallti', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-11 05:13:47'),
(712, '200', '/facallti/facallti/assets/js/dark-mode.js', 'http://localhost/facallti/facallti/facallti', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-11 05:13:47'),
(713, '200', '/facallti/facallti/assets/images/seait-logo.png', 'http://localhost/facallti/facallti/facallti', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-11 05:13:47'),
(714, '200', '/facallti/facallti/assets/images/favicon.ico', 'http://localhost/facallti/facallti/facallti', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-11 05:13:47'),
(715, '200', '/facallti/facallti/assets/images/seait-logo.png', 'http://localhost/facallti/facallti/facallti', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-11 05:13:47'),
(716, '200', '/facallti/facallti/assets/images/favicon.ico', 'http://localhost/facallti/facallti/facallti', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '::1', NULL, NULL, NULL, NULL, '2025-09-11 05:13:47');

-- --------------------------------------------------------

--
-- Stand-in structure for view `evaluation_summary_view`
-- (See below for the actual view)
--
CREATE TABLE `evaluation_summary_view` (
`id` int(11)
,`evaluator_id` int(11)
,`evaluator_type` enum('student','teacher','head')
,`evaluatee_id` int(11)
,`evaluatee_type` enum('teacher','student','head')
,`main_category_id` int(11)
,`main_category_name` varchar(255)
,`evaluation_type` enum('student_to_teacher','peer_to_peer','head_to_teacher')
,`evaluation_date` date
,`status` enum('draft','completed','archived','cancelled')
,`notes` text
,`total_responses` bigint(21)
,`average_rating` decimal(14,4)
,`excellent_count` bigint(21)
,`very_satisfactory_count` bigint(21)
,`satisfactory_count` bigint(21)
,`good_count` bigint(21)
,`poor_count` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `faculty`
--

CREATE TABLE `faculty` (
  `id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `position` varchar(100) NOT NULL,
  `department` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `qrcode` varchar(255) DEFAULT NULL COMMENT 'QR code identifier for teacher'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Faculty members with comprehensive HR information';

--
-- Dumping data for table `faculty`
--

INSERT INTO `faculty` (`id`, `first_name`, `last_name`, `middle_name`, `email`, `phone`, `address`, `password`, `position`, `department`, `bio`, `image_url`, `is_active`, `created_at`, `qrcode`) VALUES
(2, 'Updated', 'Name', NULL, 'updated@test.com', NULL, NULL, '$2y$10$KM8tKMYTqJ3De938qIT21OHM0oI7xCgcU1cqZmVDMwdh4aiqGoula', 'Updated', 'Updated', 'Specialist in software engineering and web development', '', 0, '2025-08-05 10:14:11', '2025-0002'),
(3, 'Robert', 'Johnson', NULL, 'robert.johnson@seait.edu.ph', NULL, NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Professor', 'Information Technology', NULL, NULL, 1, '2025-08-28 14:32:49', '2025-0003'),
(4, 'Ana', 'Martinez', NULL, 'ana.martinez@seait.edu.ph', NULL, NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Instructor', 'Information Technology', NULL, NULL, 1, '2025-08-28 14:32:49', '2025-0004'),
(5, 'David', 'Brown', NULL, 'david.brown@seait.edu.ph', NULL, NULL, NULL, 'Associate Professor', 'Computer Engineering', NULL, NULL, 1, '2025-08-28 14:32:49', '2025-0005'),
(6, 'Lisa', 'Davis', NULL, 'lisa.davis@seait.edu.ph', NULL, NULL, NULL, 'Assistant Professor', 'Computer Engineering', NULL, NULL, 1, '2025-08-28 14:32:49', '2025-0006'),
(8, 'Sarah', 'Cohh', NULL, 'sarah.anderson@seait.edu.ph', NULL, NULL, NULL, 'Professor', 'College of Business and Good Governance', NULL, NULL, 1, '2025-08-28 14:32:49', '2025-0008'),
(9, 'Michael Paul', 'Sebando', NULL, 'paul.sebando@seait.edu.ph', NULL, NULL, '$2y$10$R24anZMA596YhGqULkFnd.5i3Rc8d4KP/G6FNNEsYgh53XRjw6kEK', 'Program Head', 'College of Information and Communication Technology', '', 'uploads/faculty_photos/faculty_9_1756736301.jpg', 1, '2025-08-10 14:46:51', '2025-0009'),
(10, 'Emily', 'Thomas', NULL, 'emily.thomas@seait.edu.ph', NULL, NULL, NULL, 'Assistant Professor', 'Computer Science', NULL, NULL, 1, '2025-08-28 14:32:49', '2025-0010'),
(24, 'Jesseryn', 'Olangca', NULL, 'jolangca@seait.edu.ph', NULL, NULL, NULL, 'Instructor', 'College of Information and Communication Technology', NULL, NULL, 1, '2025-08-31 13:04:27', '2025-0011'),
(36, 'Paul', 'Lander', NULL, 'paul.lander@seait.edu.ph', NULL, NULL, '$2y$10$7iFZWEsHdsLpIF.oIpdnUeXZqaOfVXJ7zsdepDIMk8lVVDwFChIbi', 'Instructor', 'College of Information and Communication Technology', 'Faculty member in the College of Information and Communication Technology department. Profile to be updated by HR.', 'uploads/faculty_photos/2017-0202_1756735391.jpg', 1, '2025-09-01 14:03:11', '2017-0202'),
(44, 'Sample', 'Teacher', NULL, 'sample.teacher@seait.edu.ph', NULL, NULL, '$2y$10$zB9u8E35r2e6YYGe1YUOjutsD5lVWZ5RmgCodceej3Ost8.zCxqX6', 'Instructor', 'College of Information and Communication Technology', 'Faculty member in the College of Information and Communication Technology department. Profile to be updated by HR.', 'uploads/faculty_photos/2021-123_1756920892.jpg', 1, '2025-09-03 17:34:52', '2021-123'),
(46, 'Sample', 'Faculty', NULL, 'sample.faculty@seait.edu.ph', NULL, NULL, '$2y$10$x.lEPLPPTeFGhEDy/a.5POV1iRUYSlHjEHUrAQcWGg3LzH51KTRbC', 'Faculty', 'College of Information and Communication Technology', 'Faculty member in the College of Information and Communication Technology department. Profile to be updated by HR.', 'uploads/faculty_photos/2025-11111_1757574235.jpg', 1, '2025-09-11 07:03:55', '2025-11111');

-- --------------------------------------------------------

--
-- Table structure for table `heads`
--

CREATE TABLE `heads` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `department` varchar(100) NOT NULL,
  `position` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `heads`
--

INSERT INTO `heads` (`id`, `user_id`, `department`, `position`, `phone`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 'Computer Science', 'Department Head', '+63 912 345 6789', 'active', '2025-08-10 14:08:33', NULL),
(2, 2, 'Mathematics', 'Department Head', '+63 923 456 7890', 'active', '2025-08-10 14:08:33', NULL),
(3, 3, 'English', 'Department Head', '+63 934 567 8901', 'active', '2025-08-10 14:08:33', NULL),
(4, 5, 'History', 'Department Head', '+63 956 789 0123', 'active', '2025-08-10 14:08:33', NULL),
(6, 7, 'College of Information and Communication Technology', 'Dean', '09123456789', 'active', '2025-08-10 14:52:19', NULL),
(7, 11, 'College of Business and Good Governance', 'Dean', '09123456789', 'active', '2025-08-12 10:11:32', '2025-08-13 13:42:53');

-- --------------------------------------------------------

--
-- Stand-in structure for view `lms_assignments_view`
-- (See below for the actual view)
--
CREATE TABLE `lms_assignments_view` (
`id` int(11)
,`class_id` int(11)
,`category_id` int(11)
,`title` varchar(255)
,`description` text
,`instructions` longtext
,`due_date` datetime
,`max_score` int(11)
,`allow_late_submission` tinyint(1)
,`late_penalty` decimal(5,2)
,`file_required` tinyint(1)
,`max_file_size` int(11)
,`allowed_file_types` varchar(255)
,`status` enum('draft','published','closed')
,`created_by` int(11)
,`created_at` timestamp
,`updated_at` timestamp
,`category_name` varchar(255)
,`category_color` varchar(20)
,`submission_count` bigint(21)
,`graded_count` bigint(21)
,`created_by_name` varchar(50)
,`created_by_last_name` varchar(50)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `lms_discussion_activity`
-- (See below for the actual view)
--
CREATE TABLE `lms_discussion_activity` (
`id` int(11)
,`class_id` int(11)
,`title` varchar(255)
,`description` text
,`is_pinned` tinyint(1)
,`is_locked` tinyint(1)
,`allow_replies` tinyint(1)
,`status` enum('active','inactive','archived')
,`created_by` int(11)
,`created_at` timestamp
,`updated_at` timestamp
,`post_count` bigint(21)
,`participant_count` bigint(21)
,`last_activity` timestamp
,`created_by_name` varchar(50)
,`created_by_last_name` varchar(50)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `lms_materials_view`
-- (See below for the actual view)
--
CREATE TABLE `lms_materials_view` (
`id` int(11)
,`class_id` int(11)
,`category_id` int(11)
,`title` varchar(255)
,`description` text
,`file_path` varchar(500)
,`file_name` varchar(255)
,`file_size` int(11)
,`mime_type` varchar(100)
,`external_url` varchar(500)
,`content` longtext
,`type` enum('file','url','text','video','audio')
,`order_number` int(11)
,`is_public` tinyint(1)
,`status` enum('active','inactive','draft')
,`created_by` int(11)
,`created_at` timestamp
,`updated_at` timestamp
,`category_name` varchar(255)
,`category_icon` varchar(50)
,`category_color` varchar(20)
,`access_count` bigint(21)
,`created_by_name` varchar(50)
,`created_by_last_name` varchar(50)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `lms_student_grades_summary`
-- (See below for the actual view)
--
CREATE TABLE `lms_student_grades_summary` (
`class_id` int(11)
,`student_id` int(11)
,`first_name` varchar(100)
,`last_name` varchar(100)
,`student_number` varchar(50)
,`category_name` varchar(255)
,`weight` decimal(5,2)
,`grade_count` bigint(21)
,`average_percentage` decimal(9,6)
,`total_score` decimal(27,2)
,`total_max_score` decimal(27,2)
);

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '2019_12_14_000001_create_personal_access_tokens_table', 1),
(2, '2025_08_22_191733_create_users_table', 1),
(3, '2025_08_22_191735_create_students_table', 1),
(4, '2025_08_22_191739_create_faculties_table', 1),
(5, '2025_08_22_191742_create_courses_table', 1),
(6, '2025_08_22_191819_create_departments_table', 1),
(7, '2025_08_22_191821_create_subjects_table', 1),
(8, '2025_08_22_191824_create_colleges_table', 1);

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES
(1, 'site_title', 'SEAIT - South East Asian Institute of Technology, Inc.', '2025-08-05 10:14:11'),
(2, 'site_description', 'SEAIT', '2025-09-10 06:49:24'),
(3, 'contact_email', 'info@seait.edu.ph', '2025-08-05 10:14:11'),
(4, 'contact_phone', '+63 123 456 7890', '2025-08-05 10:14:11'),
(5, 'contact_address', 'National Highway, Purok 7, Crossing Rubber, Tupi, South Cotabato, 9505', '2025-09-10 06:41:52'),
(6, 'site_name', 'South East Asian Institute of Technology, Inc.', '2025-09-10 06:41:52'),
(12, 'school_logo', 'assets/images/school_logo_1757486512.png', '2025-09-10 06:41:52'),
(47, 'social_facebook', 'https://facebook.com/seait', '2025-09-10 06:47:53'),
(48, 'social_twitter', 'https://twitter.com/seait', '2025-09-10 06:47:53'),
(49, 'social_instagram', 'https://instagram.com/seait', '2025-09-10 06:47:53'),
(50, 'social_linkedin', 'https://linkedin.com/company/seait', '2025-09-10 06:47:53');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `status` enum('active','pending','inactive','deleted') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `student_id`, `first_name`, `middle_name`, `last_name`, `email`, `password_hash`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, '2024-0001', 'Juan', 'Santos', 'Dela Cruz', 'juan.delacruz@seait.edu.ph', '$2y$10$cs/XVTHbrKfGE5zqAFf/s.E85rPqhbUjl9QOMOesnNEHBk9UhxIyi', 'active', '2025-08-10 11:44:42', '2025-08-17 17:50:10', NULL),
(2, '2024-0002', 'Pablo', 'Miguel', 'Miguel', 'pablo.miguel@seait.edu.ph', '$2y$10$g5F5UI3JzdlCNul52Qaqxe1h9gk/aAHTRm9AR8DTepzzNpd6gtQ4.', 'active', '2025-09-08 10:24:39', '2025-09-08 10:29:25', NULL),
(4, '2024-0004', 'Ana', 'Martinez', 'Gonzales', 'ana.gonzales@seait.edu.ph', '$2y$10$g5F5UI3JzdlCNul52Qaqxe1h9gk/aAHTRm9AR8DTepzzNpd6gtQ4.', 'active', '2025-08-10 11:44:42', '2025-08-13 16:26:12', NULL);

-- --------------------------------------------------------

--
-- Stand-in structure for view `student_statistics_view`
-- (See below for the actual view)
--
CREATE TABLE `student_statistics_view` (
`total_students` bigint(21)
,`active_students` decimal(22,0)
,`pending_students` decimal(22,0)
,`inactive_students` decimal(22,0)
,`today_registrations` decimal(22,0)
,`this_month_registrations` decimal(22,0)
);

-- --------------------------------------------------------

--
-- Table structure for table `teacher_availability`
--

CREATE TABLE `teacher_availability` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL COMMENT 'Foreign key to faculty table',
  `availability_date` date NOT NULL COMMENT 'Date when teacher is available',
  `scan_time` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'When the QR code was scanned',
  `status` enum('available','unavailable') NOT NULL DEFAULT 'available' COMMENT 'Current availability status',
  `last_activity` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'Last activity timestamp',
  `notes` text DEFAULT NULL COMMENT 'Optional notes about availability',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Teacher availability tracking through QR code scanning';

--
-- Dumping data for table `teacher_availability`
--

INSERT INTO `teacher_availability` (`id`, `teacher_id`, `availability_date`, `scan_time`, `status`, `last_activity`, `notes`, `created_at`, `updated_at`) VALUES
(1, 3, '2025-09-11', '2025-09-11 11:49:04', 'available', '2025-09-11 11:54:48', 'Teacher confirmed via QR scan - mark_available', '2025-09-11 11:49:04', '2025-09-11 11:54:48'),
(2, 36, '2025-09-11', '2025-09-11 11:51:43', 'unavailable', '2025-09-11 14:38:46', 'Teacher marked as unavailable via QR scan', '2025-09-11 11:51:43', '2025-09-11 14:38:46');

-- --------------------------------------------------------

--
-- Stand-in structure for view `teacher_dashboard_stats`
-- (See below for the actual view)
--
CREATE TABLE `teacher_dashboard_stats` (
`teacher_id` int(11)
,`total_classes` bigint(21)
,`active_classes` bigint(21)
,`total_enrollments` bigint(21)
,`active_enrollments` bigint(21)
,`total_evaluations` bigint(21)
,`completed_evaluations` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `training_summary_view`
-- (See below for the actual view)
--
CREATE TABLE `training_summary_view` (
`id` int(11)
,`title` varchar(255)
,`type` enum('training','seminar','workshop','conference')
,`status` enum('draft','published','ongoing','completed','cancelled')
,`start_date` datetime
,`end_date` datetime
,`category_name` varchar(255)
,`main_category_name` varchar(255)
,`sub_category_name` varchar(255)
,`max_participants` int(11)
,`registered_count` bigint(21)
,`completed_count` bigint(21)
,`average_feedback_rating` decimal(14,4)
);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `role` enum('admin','social_media_manager','content_creator','guidance_officer','teacher','head','student','human_resource') NOT NULL DEFAULT 'student',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `phone`, `bio`, `password`, `first_name`, `last_name`, `profile_photo`, `role`, `status`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@seait.edu.ph', NULL, NULL, '$2y$10$cMOojXGmDMOhjndcskIz7.SiyLe5qaJYxrtNrlLgpvpmUovMHFwPS', 'Admin', 'User', NULL, 'admin', 'active', '2025-08-05 10:14:11', '2025-08-28 03:11:58'),
(2, 'social_manager', 'social@seait.edu.ph', NULL, NULL, '$2y$10$cMOojXGmDMOhjndcskIz7.SiyLe5qaJYxrtNrlLgpvpmUovMHFwPS', 'Cedie', 'Gabriel', 'uploads/profile-photos/profile_2_1756916062.jpg', 'social_media_manager', 'active', '2025-08-05 10:14:11', '2025-09-03 16:14:22'),
(3, 'content_creator', 'content@seait.edu.ph', NULL, NULL, '$2y$10$cMOojXGmDMOhjndcskIz7.SiyLe5qaJYxrtNrlLgpvpmUovMHFwPS', 'Charlon', 'Bullos', 'uploads/profile-photos/profile_3_1756916827.jpg', 'content_creator', 'active', '2025-08-05 10:14:11', '2025-09-03 16:27:07'),
(5, 'guidance', 'guidance@seait.edu.ph', NULL, NULL, '$2y$10$cMOojXGmDMOhjndcskIz7.SiyLe5qaJYxrtNrlLgpvpmUovMHFwPS', 'Guidance', 'Officer', NULL, 'guidance_officer', 'active', '2025-08-10 12:41:23', '2025-08-10 17:21:54'),
(7, 'rprudente@seait.edu.ph', 'rprudente@seait.edu.ph', NULL, NULL, '$2y$10$iyZsgr6HgTKTGtYWO0rZZeKPeZEItJCSQqjToYBV3vfoDPzaxui6u', 'Reginald', 'Prudente', 'uploads/profile-photos/profile_7_1756925207.jpg', 'head', 'active', '2025-08-10 14:52:19', '2025-09-03 18:46:47'),
(11, 'jpalate', 'jpalate@seait.edu.ph', NULL, NULL, '$2y$10$5uzRpQGhSmOqFmTrmwgw8uRN45yEL81Cvqj4QZ.rQnOjIPslRBuRO', 'Jestone', 'Palate', NULL, 'head', 'active', '2025-08-12 10:11:32', '2025-08-12 10:11:32'),
(20, 'hr_manager', 'hr@seait.edu.ph', NULL, NULL, '$2y$10$cMOojXGmDMOhjndcskIz7.SiyLe5qaJYxrtNrlLgpvpmUovMHFwPS', 'HR', 'Manager', NULL, 'human_resource', 'active', '2025-08-28 03:18:17', '2025-08-28 03:42:23');

-- --------------------------------------------------------

--
-- Table structure for table `user_inquiries`
--

CREATE TABLE `user_inquiries` (
  `id` int(11) NOT NULL,
  `user_question` text NOT NULL,
  `bot_response` text DEFAULT NULL,
  `user_email` varchar(255) DEFAULT NULL,
  `user_name` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `is_resolved` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_inquiries`
--

INSERT INTO `user_inquiries` (`id`, `user_question`, `bot_response`, `user_email`, `user_name`, `ip_address`, `user_agent`, `is_resolved`, `created_at`) VALUES
(1, 'What programs does SEAIT offer?', 'SEAIT offers various academic programs across different colleges. You can explore our Academic Programs section to see all available courses. Each program has detailed information about curriculum, requirements, and career opportunities.', '', '', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 0, '2025-08-08 02:34:10'),
(2, 'How to enroll?', 'You can apply for admission by visiting our Admission Process section on the website, or contact our admission office directly. We offer various programs including undergraduate and graduate degrees. You can also start your application through our pre-registration form.', '', '', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 0, '2025-08-08 03:54:30'),
(3, 'how to enroll', 'You can apply for admission by visiting our Admission Process section on the website, or contact our admission office directly. We offer various programs including undergraduate and graduate degrees. You can also start your application through our pre-registration form.', '', '', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 0, '2025-08-08 05:29:52'),
(4, 'Publications', 'Thank you for your question! For specific inquiries, I recommend contacting our relevant department directly. You can find contact information in the Contact Us section, or visit our main office during business hours.', '', '', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 0, '2025-08-08 07:12:45'),
(5, 'How to enroll?', 'You can apply for admission by visiting our Admission Process section on the website, or contact our admission office directly. We offer various programs including undergraduate and graduate degrees. You can also start your application through our pre-registration form.', '', '', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', 0, '2025-08-19 02:30:02');

-- --------------------------------------------------------

--
-- Structure for view `active_consultation_leaves`
--
DROP TABLE IF EXISTS `active_consultation_leaves`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `active_consultation_leaves`  AS SELECT `cl`.`id` AS `id`, `cl`.`teacher_id` AS `teacher_id`, `cl`.`leave_date` AS `leave_date`, `cl`.`reason` AS `reason`, `cl`.`created_at` AS `created_at`, `f`.`first_name` AS `first_name`, `f`.`last_name` AS `last_name`, `f`.`department` AS `department`, `f`.`position` AS `position` FROM (`seait_website`.`consultation_leave` `cl` join `seait_website`.`faculty` `f` on(`cl`.`teacher_id` = `f`.`id`)) WHERE `cl`.`leave_date` >= curdate() ORDER BY `cl`.`leave_date` ASC, `f`.`last_name` ASC, `f`.`first_name` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `active_consultation_requests`
--
DROP TABLE IF EXISTS `active_consultation_requests`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `active_consultation_requests`  AS SELECT `cr`.`id` AS `id`, `cr`.`teacher_id` AS `teacher_id`, `f`.`first_name` AS `teacher_first_name`, `f`.`last_name` AS `teacher_last_name`, `f`.`department` AS `teacher_department`, `cr`.`student_name` AS `student_name`, `cr`.`student_dept` AS `student_dept`, `cr`.`student_id` AS `student_id`, `cr`.`status` AS `status`, `cr`.`session_id` AS `session_id`, `cr`.`request_time` AS `request_time`, `cr`.`response_time` AS `response_time`, `cr`.`start_time` AS `start_time`, `cr`.`end_time` AS `end_time`, `cr`.`duration_minutes` AS `duration_minutes`, `cr`.`notes` AS `notes`, timestampdiff(MINUTE,`cr`.`request_time`,current_timestamp()) AS `minutes_since_request` FROM (`seait_website`.`consultation_requests` `cr` join `seait_website`.`faculty` `f` on(`cr`.`teacher_id` = `f`.`id`)) WHERE `cr`.`status` in ('pending','accepted') AND `cr`.`request_time` > current_timestamp() - interval 10 minute ORDER BY `cr`.`request_time` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `active_students_view`
--
DROP TABLE IF EXISTS `active_students_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `active_students_view`  AS SELECT `s`.`id` AS `id`, `s`.`student_id` AS `student_id`, `s`.`first_name` AS `first_name`, `s`.`middle_name` AS `middle_name`, `s`.`last_name` AS `last_name`, `s`.`email` AS `email`, `s`.`status` AS `status`, `s`.`created_at` AS `created_at`, concat(`s`.`first_name`,' ',`s`.`last_name`) AS `full_name`, `sp`.`phone` AS `phone`, `sp`.`date_of_birth` AS `date_of_birth`, `sai`.`program_id` AS `program_id`, `sai`.`year_level` AS `year_level`, `sai`.`academic_status` AS `academic_status` FROM ((`seait_website`.`students` `s` left join `seait_website`.`student_profiles` `sp` on(`s`.`id` = `sp`.`student_id`)) left join `seait_website`.`student_academic_info` `sai` on(`s`.`id` = `sai`.`student_id`)) WHERE `s`.`status` = 'active' ;

-- --------------------------------------------------------

--
-- Structure for view `active_teachers_today`
--
DROP TABLE IF EXISTS `active_teachers_today`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `active_teachers_today`  AS SELECT `ta`.`id` AS `id`, `ta`.`teacher_id` AS `teacher_id`, `f`.`first_name` AS `first_name`, `f`.`last_name` AS `last_name`, `f`.`email` AS `email`, `f`.`department` AS `department`, `f`.`position` AS `position`, `f`.`image_url` AS `image_url`, `ta`.`availability_date` AS `availability_date`, `ta`.`scan_time` AS `scan_time`, `ta`.`status` AS `status`, `ta`.`last_activity` AS `last_activity`, timestampdiff(MINUTE,`ta`.`last_activity`,current_timestamp()) AS `minutes_since_last_activity` FROM (`seait_website`.`teacher_availability` `ta` join `seait_website`.`faculty` `f` on(`ta`.`teacher_id` = `f`.`id`)) WHERE `ta`.`availability_date` = curdate() AND `ta`.`status` = 'available' AND `f`.`is_active` = 1 ORDER BY `ta`.`scan_time` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `consultation_hours_summary`
--
DROP TABLE IF EXISTS `consultation_hours_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `consultation_hours_summary`  AS SELECT `ch`.`id` AS `id`, `ch`.`teacher_id` AS `teacher_id`, `f`.`first_name` AS `first_name`, `f`.`last_name` AS `last_name`, `f`.`email` AS `email`, `f`.`department` AS `department`, `ch`.`semester` AS `semester`, `ch`.`academic_year` AS `academic_year`, `ch`.`day_of_week` AS `day_of_week`, `ch`.`start_time` AS `start_time`, `ch`.`end_time` AS `end_time`, `ch`.`room` AS `room`, `ch`.`notes` AS `notes`, `ch`.`is_active` AS `is_active`, `ch`.`created_at` AS `created_at`, `ch`.`updated_at` AS `updated_at` FROM (`seait_website`.`consultation_hours` `ch` join `seait_website`.`faculty` `f` on(`ch`.`teacher_id` = `f`.`id`)) WHERE `ch`.`is_active` = 1 ORDER BY `f`.`last_name` ASC, `f`.`first_name` ASC, `ch`.`day_of_week` ASC, `ch`.`start_time` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `evaluation_summary_view`
--
DROP TABLE IF EXISTS `evaluation_summary_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `evaluation_summary_view`  AS SELECT `es`.`id` AS `id`, `es`.`evaluator_id` AS `evaluator_id`, `es`.`evaluator_type` AS `evaluator_type`, `es`.`evaluatee_id` AS `evaluatee_id`, `es`.`evaluatee_type` AS `evaluatee_type`, `es`.`main_category_id` AS `main_category_id`, `mec`.`name` AS `main_category_name`, `mec`.`evaluation_type` AS `evaluation_type`, `es`.`evaluation_date` AS `evaluation_date`, `es`.`status` AS `status`, `es`.`notes` AS `notes`, count(`er`.`id`) AS `total_responses`, avg(`er`.`rating_value`) AS `average_rating`, count(case when `er`.`rating_value` = 5 then 1 end) AS `excellent_count`, count(case when `er`.`rating_value` = 4 then 1 end) AS `very_satisfactory_count`, count(case when `er`.`rating_value` = 3 then 1 end) AS `satisfactory_count`, count(case when `er`.`rating_value` = 2 then 1 end) AS `good_count`, count(case when `er`.`rating_value` = 1 then 1 end) AS `poor_count` FROM ((`seait_website`.`evaluation_sessions` `es` join `seait_website`.`main_evaluation_categories` `mec` on(`es`.`main_category_id` = `mec`.`id`)) left join `seait_website`.`evaluation_responses` `er` on(`es`.`id` = `er`.`evaluation_session_id`)) GROUP BY `es`.`id` ;

-- --------------------------------------------------------

--
-- Structure for view `lms_assignments_view`
--
DROP TABLE IF EXISTS `lms_assignments_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `lms_assignments_view`  AS SELECT `a`.`id` AS `id`, `a`.`class_id` AS `class_id`, `a`.`category_id` AS `category_id`, `a`.`title` AS `title`, `a`.`description` AS `description`, `a`.`instructions` AS `instructions`, `a`.`due_date` AS `due_date`, `a`.`max_score` AS `max_score`, `a`.`allow_late_submission` AS `allow_late_submission`, `a`.`late_penalty` AS `late_penalty`, `a`.`file_required` AS `file_required`, `a`.`max_file_size` AS `max_file_size`, `a`.`allowed_file_types` AS `allowed_file_types`, `a`.`status` AS `status`, `a`.`created_by` AS `created_by`, `a`.`created_at` AS `created_at`, `a`.`updated_at` AS `updated_at`, `ac`.`name` AS `category_name`, `ac`.`color` AS `category_color`, count(`s`.`id`) AS `submission_count`, count(case when `s`.`status` = 'graded' then 1 end) AS `graded_count`, `u`.`first_name` AS `created_by_name`, `u`.`last_name` AS `created_by_last_name` FROM (((`seait_website`.`lms_assignments` `a` join `seait_website`.`lms_assignment_categories` `ac` on(`a`.`category_id` = `ac`.`id`)) join `seait_website`.`users` `u` on(`a`.`created_by` = `u`.`id`)) left join `seait_website`.`lms_assignment_submissions` `s` on(`a`.`id` = `s`.`assignment_id`)) WHERE `a`.`status` <> 'draft' GROUP BY `a`.`id` ;

-- --------------------------------------------------------

--
-- Structure for view `lms_discussion_activity`
--
DROP TABLE IF EXISTS `lms_discussion_activity`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `lms_discussion_activity`  AS SELECT `d`.`id` AS `id`, `d`.`class_id` AS `class_id`, `d`.`title` AS `title`, `d`.`description` AS `description`, `d`.`is_pinned` AS `is_pinned`, `d`.`is_locked` AS `is_locked`, `d`.`allow_replies` AS `allow_replies`, `d`.`status` AS `status`, `d`.`created_by` AS `created_by`, `d`.`created_at` AS `created_at`, `d`.`updated_at` AS `updated_at`, count(`p`.`id`) AS `post_count`, count(distinct `p`.`author_id`) AS `participant_count`, max(`p`.`created_at`) AS `last_activity`, `u`.`first_name` AS `created_by_name`, `u`.`last_name` AS `created_by_last_name` FROM ((`seait_website`.`lms_discussions` `d` join `seait_website`.`users` `u` on(`d`.`created_by` = `u`.`id`)) left join `seait_website`.`lms_discussion_posts` `p` on(`d`.`id` = `p`.`discussion_id` and `p`.`status` = 'active')) WHERE `d`.`status` = 'active' GROUP BY `d`.`id` ;

-- --------------------------------------------------------

--
-- Structure for view `lms_materials_view`
--
DROP TABLE IF EXISTS `lms_materials_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `lms_materials_view`  AS SELECT `m`.`id` AS `id`, `m`.`class_id` AS `class_id`, `m`.`category_id` AS `category_id`, `m`.`title` AS `title`, `m`.`description` AS `description`, `m`.`file_path` AS `file_path`, `m`.`file_name` AS `file_name`, `m`.`file_size` AS `file_size`, `m`.`mime_type` AS `mime_type`, `m`.`external_url` AS `external_url`, `m`.`content` AS `content`, `m`.`type` AS `type`, `m`.`order_number` AS `order_number`, `m`.`is_public` AS `is_public`, `m`.`status` AS `status`, `m`.`created_by` AS `created_by`, `m`.`created_at` AS `created_at`, `m`.`updated_at` AS `updated_at`, `mc`.`name` AS `category_name`, `mc`.`icon` AS `category_icon`, `mc`.`color` AS `category_color`, count(`ml`.`id`) AS `access_count`, `u`.`first_name` AS `created_by_name`, `u`.`last_name` AS `created_by_last_name` FROM (((`seait_website`.`lms_materials` `m` join `seait_website`.`lms_material_categories` `mc` on(`m`.`category_id` = `mc`.`id`)) join `seait_website`.`users` `u` on(`m`.`created_by` = `u`.`id`)) left join `seait_website`.`lms_material_access_logs` `ml` on(`m`.`id` = `ml`.`material_id`)) WHERE `m`.`status` = 'active' GROUP BY `m`.`id` ;

-- --------------------------------------------------------

--
-- Structure for view `lms_student_grades_summary`
--
DROP TABLE IF EXISTS `lms_student_grades_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `lms_student_grades_summary`  AS SELECT `sg`.`class_id` AS `class_id`, `sg`.`student_id` AS `student_id`, `s`.`first_name` AS `first_name`, `s`.`last_name` AS `last_name`, `s`.`student_id` AS `student_number`, `gc`.`name` AS `category_name`, `gc`.`weight` AS `weight`, count(`sg`.`id`) AS `grade_count`, avg(`sg`.`percentage`) AS `average_percentage`, sum(`sg`.`score`) AS `total_score`, sum(`sg`.`max_score`) AS `total_max_score` FROM ((`seait_website`.`lms_student_grades` `sg` join `seait_website`.`students` `s` on(`sg`.`student_id` = `s`.`id`)) join `seait_website`.`lms_grade_categories` `gc` on(`sg`.`category_id` = `gc`.`id`)) WHERE `sg`.`status` = 'published' GROUP BY `sg`.`class_id`, `sg`.`student_id`, `gc`.`id` ;

-- --------------------------------------------------------

--
-- Structure for view `student_statistics_view`
--
DROP TABLE IF EXISTS `student_statistics_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `student_statistics_view`  AS SELECT count(0) AS `total_students`, sum(case when `seait_website`.`students`.`status` = 'active' then 1 else 0 end) AS `active_students`, sum(case when `seait_website`.`students`.`status` = 'pending' then 1 else 0 end) AS `pending_students`, sum(case when `seait_website`.`students`.`status` = 'inactive' then 1 else 0 end) AS `inactive_students`, sum(case when cast(`seait_website`.`students`.`created_at` as date) = curdate() then 1 else 0 end) AS `today_registrations`, sum(case when month(`seait_website`.`students`.`created_at`) = month(curdate()) and year(`seait_website`.`students`.`created_at`) = year(curdate()) then 1 else 0 end) AS `this_month_registrations` FROM `seait_website`.`students` WHERE `seait_website`.`students`.`status` <> 'deleted' ;

-- --------------------------------------------------------

--
-- Structure for view `teacher_dashboard_stats`
--
DROP TABLE IF EXISTS `teacher_dashboard_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `teacher_dashboard_stats`  AS SELECT `t`.`id` AS `teacher_id`, count(distinct `tc`.`id`) AS `total_classes`, count(distinct case when `tc`.`status` = 'active' then `tc`.`id` end) AS `active_classes`, count(distinct `ce`.`id`) AS `total_enrollments`, count(distinct case when `ce`.`status` = 'active' then `ce`.`id` end) AS `active_enrollments`, count(distinct `es`.`id`) AS `total_evaluations`, count(distinct case when `es`.`status` = 'completed' then `es`.`id` end) AS `completed_evaluations` FROM (((`seait_website`.`users` `t` left join `seait_website`.`teacher_classes` `tc` on(`t`.`id` = `tc`.`teacher_id`)) left join `seait_website`.`class_enrollments` `ce` on(`tc`.`id` = `ce`.`class_id`)) left join `seait_website`.`evaluation_sessions` `es` on(`t`.`id` = `es`.`evaluator_id`)) WHERE `t`.`role` = 'teacher' GROUP BY `t`.`id` ;

-- --------------------------------------------------------

--
-- Structure for view `training_summary_view`
--
DROP TABLE IF EXISTS `training_summary_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `training_summary_view`  AS SELECT `ts`.`id` AS `id`, `ts`.`title` AS `title`, `ts`.`type` AS `type`, `ts`.`status` AS `status`, `ts`.`start_date` AS `start_date`, `ts`.`end_date` AS `end_date`, `tc`.`name` AS `category_name`, `mec`.`name` AS `main_category_name`, `esc`.`name` AS `sub_category_name`, `ts`.`max_participants` AS `max_participants`, count(`tr`.`id`) AS `registered_count`, count(case when `tr`.`status` = 'completed' then 1 end) AS `completed_count`, avg(`tr`.`feedback_rating`) AS `average_feedback_rating` FROM ((((`seait_website`.`trainings_seminars` `ts` left join `seait_website`.`training_categories` `tc` on(`ts`.`category_id` = `tc`.`id`)) left join `seait_website`.`main_evaluation_categories` `mec` on(`ts`.`main_category_id` = `mec`.`id`)) left join `seait_website`.`evaluation_sub_categories` `esc` on(`ts`.`sub_category_id` = `esc`.`id`)) left join `seait_website`.`training_registrations` `tr` on(`ts`.`id` = `tr`.`training_id`)) GROUP BY `ts`.`id` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `colleges`
--
ALTER TABLE `colleges`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `consultation_hours`
--
ALTER TABLE `consultation_hours`
  ADD PRIMARY KEY (`id`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `semester` (`semester`),
  ADD KEY `academic_year` (`academic_year`),
  ADD KEY `day_of_week` (`day_of_week`),
  ADD KEY `is_active` (`is_active`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_consultation_teacher_semester` (`teacher_id`,`semester`,`academic_year`),
  ADD KEY `idx_consultation_active` (`is_active`,`semester`,`academic_year`),
  ADD KEY `idx_consultation_day_time` (`day_of_week`,`start_time`,`end_time`),
  ADD KEY `idx_consultation_semester_active` (`semester`,`academic_year`,`is_active`),
  ADD KEY `idx_consultation_teacher_active` (`teacher_id`,`is_active`);

--
-- Indexes for table `consultation_leave`
--
ALTER TABLE `consultation_leave`
  ADD PRIMARY KEY (`id`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `idx_consultation_leave_current_date` (`leave_date`);

--
-- Indexes for table `consultation_requests`
--
ALTER TABLE `consultation_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `status` (`status`),
  ADD KEY `request_time` (`request_time`),
  ADD KEY `session_id` (`session_id`),
  ADD KEY `idx_consultation_teacher_status` (`teacher_id`,`status`),
  ADD KEY `idx_consultation_pending` (`status`,`request_time`),
  ADD KEY `idx_consultation_requests_recent` (`teacher_id`,`status`,`request_time`),
  ADD KEY `idx_consultation_requests_session` (`session_id`,`status`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `error_logs`
--
ALTER TABLE `error_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_error_type` (`error_type`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_ip_address` (`ip_address`);

--
-- Indexes for table `faculty`
--
ALTER TABLE `faculty`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_department` (`department`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_qrcode` (`qrcode`) COMMENT 'Index for QR code lookups';

--
-- Indexes for table `heads`
--
ALTER TABLE `heads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `department` (`department`),
  ADD KEY `status` (`status`),
  ADD KEY `idx_heads_department_status` (`department`,`status`),
  ADD KEY `idx_heads_position` (`position`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_id` (`student_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `status` (`status`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `idx_students_status_created` (`status`,`created_at`),
  ADD KEY `idx_students_email_status` (`email`,`status`),
  ADD KEY `idx_students_student_id_status` (`student_id`,`status`);

--
-- Indexes for table `teacher_availability`
--
ALTER TABLE `teacher_availability`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_teacher_date` (`teacher_id`,`availability_date`) COMMENT 'One availability record per teacher per day',
  ADD KEY `idx_teacher_id` (`teacher_id`) COMMENT 'Index for teacher lookups',
  ADD KEY `idx_availability_date` (`availability_date`) COMMENT 'Index for date queries',
  ADD KEY `idx_status` (`status`) COMMENT 'Index for status filtering',
  ADD KEY `idx_scan_time` (`scan_time`) COMMENT 'Index for scan time queries',
  ADD KEY `idx_active_teachers` (`teacher_id`,`availability_date`,`status`) COMMENT 'Composite index for active teacher queries';

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_status` (`status`);

--
-- Indexes for table `user_inquiries`
--
ALTER TABLE `user_inquiries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_inquiries_created` (`created_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `colleges`
--
ALTER TABLE `colleges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `consultation_hours`
--
ALTER TABLE `consultation_hours`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `consultation_leave`
--
ALTER TABLE `consultation_leave`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `consultation_requests`
--
ALTER TABLE `consultation_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `error_logs`
--
ALTER TABLE `error_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=717;

--
-- AUTO_INCREMENT for table `faculty`
--
ALTER TABLE `faculty`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `heads`
--
ALTER TABLE `heads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=283;

--
-- AUTO_INCREMENT for table `teacher_availability`
--
ALTER TABLE `teacher_availability`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `user_inquiries`
--
ALTER TABLE `user_inquiries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `colleges`
--
ALTER TABLE `colleges`
  ADD CONSTRAINT `colleges_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `consultation_hours`
--
ALTER TABLE `consultation_hours`
  ADD CONSTRAINT `fk_consultation_hours_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_consultation_hours_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `faculty` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `consultation_leave`
--
ALTER TABLE `consultation_leave`
  ADD CONSTRAINT `consultation_leave_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `faculty` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `consultation_requests`
--
ALTER TABLE `consultation_requests`
  ADD CONSTRAINT `fk_consultation_requests_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `faculty` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `departments_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `heads`
--
ALTER TABLE `heads`
  ADD CONSTRAINT `fk_heads_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `teacher_availability`
--
ALTER TABLE `teacher_availability`
  ADD CONSTRAINT `fk_teacher_availability_faculty` FOREIGN KEY (`teacher_id`) REFERENCES `faculty` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
