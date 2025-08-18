-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Aug 17, 2025 at 05:20 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `seait_website`
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

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `academic_programs`
--

CREATE TABLE `academic_programs` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `level` enum('undergraduate','graduate','postgraduate') NOT NULL,
  `duration` varchar(50) DEFAULT NULL,
  `credits` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `academic_programs`
--

INSERT INTO `academic_programs` (`id`, `name`, `description`, `level`, `duration`, `credits`, `is_active`, `created_at`) VALUES
(1, 'Bachelor of Science in Computer Engineering', 'Comprehensive program covering hardware and software engineering principles', 'undergraduate', '4 years', 144, 1, '2025-08-05 10:14:11'),
(2, 'Bachelor of Science in Information Technology', 'Focus on software development and information systems', 'undergraduate', '4 years', 144, 1, '2025-08-05 10:14:11'),
(3, 'Bachelor of Science in Business Administration', 'Comprehensive business management education', 'undergraduate', '4 years', 144, 1, '2025-08-05 10:14:11'),
(4, 'Master of Science in Computer Science', 'Advanced studies in computer science and technology', 'graduate', '2 years', 36, 1, '2025-08-05 10:14:11'),
(5, 'Bachelor of Science in Electronics Engineering', 'Comprehensive program covering electronic systems, telecommunications, and embedded technologies', 'undergraduate', '4 years', 144, 1, '2025-08-05 10:17:11'),
(6, 'Bachelor of Science in Information Systems', 'Focus on business information systems, database management, and enterprise solutions', 'undergraduate', '4 years', 144, 1, '2025-08-05 10:17:11'),
(7, 'Bachelor of Science in Accountancy', 'Professional accounting education with technology integration', 'undergraduate', '4 years', 144, 1, '2025-08-05 10:17:11'),
(8, 'Master of Science in Information Technology', 'Advanced studies in IT management, cybersecurity, and digital transformation', 'graduate', '2 years', 36, 1, '2025-08-05 10:17:11'),
(9, 'Doctor of Philosophy in Computer Science', 'Research-focused program for advanced computer science studies', 'postgraduate', '3-5 years', 60, 1, '2025-08-05 10:17:11');

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
-- Table structure for table `admin_activity_logs`
--

CREATE TABLE `admin_activity_logs` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_activity_logs`
--

INSERT INTO `admin_activity_logs` (`id`, `admin_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'login', 'Admin logged in', NULL, NULL, '2025-08-10 11:45:31'),
(2, 1, 'login', 'Admin logged in', NULL, NULL, '2025-08-10 11:45:36'),
(3, 1, 'login', 'Admin logged in', NULL, NULL, '2025-08-10 11:45:43'),
(4, 1, 'login', 'Admin logged in', NULL, NULL, '2025-08-10 11:45:48'),
(5, 1, 'login', 'Admin logged in', NULL, NULL, '2025-08-10 11:45:53'),
(6, 1, 'login', 'Admin logged in', NULL, NULL, '2025-08-10 11:45:56'),
(7, 1, 'login', 'Admin logged in', NULL, NULL, '2025-08-10 11:46:38'),
(8, 1, 'login', 'Admin logged in', NULL, NULL, '2025-08-10 11:47:57'),
(9, 1, 'login', 'Admin logged in', NULL, NULL, '2025-08-10 11:48:53'),
(10, 1, 'login', 'Admin logged in', NULL, NULL, '2025-08-10 11:48:56'),
(11, 1, 'login', 'Admin logged in', NULL, NULL, '2025-08-10 11:48:58'),
(12, 1, 'login', 'Admin logged in', NULL, NULL, '2025-08-10 11:49:00'),
(13, 1, 'login', 'Admin logged in', NULL, NULL, '2025-08-10 11:53:53'),
(14, 1, 'login', 'Admin logged in', NULL, NULL, '2025-08-10 11:56:21'),
(15, 1, 'login', 'Admin logged in', NULL, NULL, '2025-08-10 12:02:18'),
(16, 1, 'login', 'Admin logged in', NULL, NULL, '2025-08-10 12:02:19'),
(17, 1, 'login', 'Admin logged in', NULL, NULL, '2025-08-10 12:07:05'),
(18, 1, 'login', 'Admin logged in', NULL, NULL, '2025-08-10 12:08:22'),
(19, 1, 'login', 'Admin logged in', NULL, NULL, '2025-08-10 12:08:44'),
(20, 1, 'login', 'Admin logged in', NULL, NULL, '2025-08-10 12:15:33'),
(21, 1, 'login', 'Admin logged in', NULL, NULL, '2025-08-10 12:15:36'),
(22, 1, 'login', 'Admin logged in', NULL, NULL, '2025-08-10 12:16:01'),
(23, 1, 'login', 'Admin logged in', NULL, NULL, '2025-08-10 12:16:03'),
(24, 1, 'login', 'Admin logged in', NULL, NULL, '2025-08-10 12:19:57'),
(25, 1, 'login', 'Admin logged in', NULL, NULL, '2025-08-10 12:27:19'),
(26, 1, 'login', 'Admin logged in', NULL, NULL, '2025-08-10 12:27:25'),
(27, 1, 'login', 'Admin logged in', NULL, NULL, '2025-08-10 12:34:18'),
(28, 1, 'login', 'Admin logged in', NULL, NULL, '2025-08-10 12:34:25'),
(29, 1, 'login', 'Admin logged in', NULL, NULL, '2025-08-10 12:34:33'),
(30, 1, 'login', 'Admin logged in', NULL, NULL, '2025-08-10 12:35:26');

-- --------------------------------------------------------

--
-- Table structure for table `admission_contacts`
--

CREATE TABLE `admission_contacts` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `contact_info` varchar(255) NOT NULL,
  `additional_info` varchar(255) DEFAULT NULL,
  `icon` varchar(100) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admission_contacts`
--

INSERT INTO `admission_contacts` (`id`, `title`, `description`, `contact_info`, `additional_info`, `icon`, `sort_order`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Call Us', 'Get immediate assistance from our admission team', '+63 123 456 7890', 'Monday - Friday, 8:00 AM - 5:00 PM', 'fas fa-phone', 1, 1, 1, '2025-08-05 15:23:07', '2025-08-05 15:23:07'),
(2, 'Email Us', 'Send us your questions and inquiries', 'admissions@seait.edu.ph', 'We\'ll respond within 24 hours', 'fas fa-envelope', 2, 1, 1, '2025-08-05 15:23:07', '2025-08-05 15:23:07'),
(3, 'Schedule a Visit', 'Experience SEAIT firsthand with a campus tour', 'Book a campus tour', 'Experience SEAIT firsthand', 'fas fa-calendar-alt', 3, 1, 1, '2025-08-05 15:23:07', '2025-08-05 15:23:07'),
(4, 'Admissions Office', 'Main admissions contact', '+63 123 456 7890', 'Available Monday to Friday, 8:00 AM - 5:00 PM', 'fas fa-phone', 1, 1, 1, '2025-08-07 14:04:49', '2025-08-07 14:04:49'),
(5, 'Email Admissions', 'Email inquiries', 'admissions@seait.edu.ph', 'Response within 24 hours', 'fas fa-envelope', 2, 1, 1, '2025-08-07 14:04:49', '2025-08-07 14:04:49'),
(6, 'Student Services', 'General student inquiries', '+63 123 456 7891', 'Student support and guidance', 'fas fa-users', 3, 1, 1, '2025-08-07 14:04:49', '2025-08-07 14:04:49');

-- --------------------------------------------------------

--
-- Table structure for table `admission_levels`
--

CREATE TABLE `admission_levels` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(100) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admission_levels`
--

INSERT INTO `admission_levels` (`id`, `name`, `description`, `icon`, `sort_order`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Basic Education', 'SEAIT provides quality basic education from Kindergarten to Grade 10, preparing students for academic success and character development.', 'fas fa-child', 1, 1, 1, '2025-08-05 15:23:07', '2025-08-05 15:23:07'),
(2, 'Senior High School', 'Prepare for college and career success with our specialized Senior High School programs designed to develop critical thinking and practical skills.', 'fas fa-graduation-cap', 2, 1, 1, '2025-08-05 15:23:07', '2025-08-05 15:23:07'),
(3, 'College', 'Embark on your professional journey with our comprehensive college programs designed to prepare you for successful careers in technology and business.', 'fas fa-university', 3, 1, 1, '2025-08-05 15:23:07', '2025-08-05 15:23:07'),
(6, 'Graduate', 'Master\'s degree programs', 'fas fa-user-graduate', 3, 1, 1, '2025-08-07 14:04:49', '2025-08-07 14:04:49'),
(7, 'Postgraduate', 'Doctoral degree programs', 'fas fa-user-tie', 4, 1, 1, '2025-08-07 14:04:49', '2025-08-07 14:04:49'),
(8, 'TESDA Program', 'Technical Education for Skills Development Authority Program', 'fas fa-certificate', 6, 1, 3, '2025-08-07 14:44:28', '2025-08-07 14:44:28');

-- --------------------------------------------------------

--
-- Table structure for table `admission_programs`
--

CREATE TABLE `admission_programs` (
  `id` int(11) NOT NULL,
  `level_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admission_programs`
--

INSERT INTO `admission_programs` (`id`, `level_id`, `name`, `description`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 'Kindergarten to Grade 6 (Elementary)', 'Comprehensive elementary education program', 1, 1, '2025-08-05 15:23:07', '2025-08-05 15:23:07'),
(2, 1, 'Grade 7 to Grade 10 (Junior High School)', 'Junior high school program with enhanced curriculum', 1, 1, '2025-08-05 15:23:07', '2025-08-05 15:23:07'),
(3, 1, 'Special Education Programs', 'Specialized programs for students with diverse learning needs', 1, 1, '2025-08-05 15:23:07', '2025-08-05 15:23:07'),
(4, 1, 'Enhanced Curriculum with Technology Integration', 'Modern curriculum incorporating technology in learning', 1, 1, '2025-08-05 15:23:07', '2025-08-05 15:23:07'),
(5, 2, 'STEM (Science, Technology, Engineering, Mathematics)', 'Focus on science and technology disciplines', 1, 1, '2025-08-05 15:23:07', '2025-08-05 15:23:07'),
(6, 2, 'ABM (Accountancy, Business, Management)', 'Business and management focused curriculum', 1, 1, '2025-08-05 15:23:07', '2025-08-05 15:23:07'),
(7, 2, 'HUMSS (Humanities and Social Sciences)', 'Humanities and social sciences program', 1, 1, '2025-08-05 15:23:07', '2025-08-05 15:23:07'),
(8, 2, 'GAS (General Academic Strand)', 'General academic preparation for various fields', 1, 1, '2025-08-05 15:23:07', '2025-08-05 15:23:07'),
(9, 3, 'Bachelor of Science in Information Technology', 'Comprehensive IT program with modern technologies', 1, 1, '2025-08-05 15:23:07', '2025-08-05 15:23:07'),
(10, 3, 'Bachelor of Science in Computer Science', 'Advanced computer science and programming', 1, 1, '2025-08-05 15:23:07', '2025-08-05 15:23:07'),
(11, 3, 'Bachelor of Science in Business Administration', 'Business management and administration', 1, 1, '2025-08-05 15:23:07', '2025-08-05 15:23:07'),
(12, 3, 'Bachelor of Science in Hospitality Management', 'Hospitality and tourism management', 1, 1, '2025-08-05 15:23:07', '2025-08-05 15:23:07'),
(13, 3, 'Bachelor of Science in Accountancy', 'Professional accountancy and financial management', 1, 1, '2025-08-05 15:23:07', '2025-08-05 15:23:07'),
(14, 8, 'Farm Machinery Operation and Maintenance', 'Learn how to operate and maintain farm machinery, from tractors to harvesters, to enhance farm productivity and reduce operational costs.', 1, 3, '2025-08-07 14:45:45', '2025-08-07 14:45:45');

-- --------------------------------------------------------

--
-- Table structure for table `admission_requirements`
--

CREATE TABLE `admission_requirements` (
  `id` int(11) NOT NULL,
  `level_id` int(11) NOT NULL,
  `step_number` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admission_requirements`
--

INSERT INTO `admission_requirements` (`id`, `level_id`, `step_number`, `title`, `description`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'Application Form', 'Complete the online application form with accurate information', 1, 1, '2025-08-05 15:23:07', '2025-08-05 15:23:07'),
(2, 1, 2, 'Required Documents', 'Birth certificate, report cards, and other academic records', 1, 1, '2025-08-05 15:23:07', '2025-08-05 15:23:07'),
(3, 1, 3, 'Assessment Test', 'Take the entrance examination to evaluate academic readiness', 1, 1, '2025-08-05 15:23:07', '2025-08-05 15:23:07'),
(4, 1, 4, 'Interview', 'Student and parent interview with school administrators', 1, 1, '2025-08-05 15:23:07', '2025-08-05 15:23:07'),
(5, 1, 5, 'Enrollment', 'Complete enrollment process and submit required fees', 1, 1, '2025-08-05 15:23:07', '2025-08-05 15:23:07'),
(6, 2, 1, 'Application Form', 'Submit completed application with strand preference', 1, 1, '2025-08-05 15:23:07', '2025-08-05 15:23:07'),
(7, 2, 2, 'Academic Records', 'Grade 10 completion certificate and report cards', 1, 1, '2025-08-05 15:23:07', '2025-08-05 15:23:07'),
(8, 2, 3, 'Aptitude Test', 'Take the SHS entrance examination and career assessment', 1, 1, '2025-08-05 15:23:07', '2025-08-05 15:23:07'),
(9, 2, 4, 'Strand Selection', 'Choose your preferred academic strand based on interests', 1, 1, '2025-08-05 15:23:07', '2025-08-05 15:23:07'),
(10, 2, 5, 'Enrollment', 'Complete enrollment and attend orientation program', 1, 1, '2025-08-05 15:23:07', '2025-08-05 15:23:07'),
(11, 3, 1, 'Application Form', 'Complete online application with program preference', 1, 1, '2025-08-05 15:23:07', '2025-08-05 15:23:07'),
(12, 3, 2, 'Academic Requirements', 'SHS diploma, transcript of records, and certificates', 1, 1, '2025-08-05 15:23:07', '2025-08-05 15:23:07'),
(13, 3, 3, 'College Entrance Exam', 'Take the SEAIT College Admission Test (SCAT)', 1, 1, '2025-08-05 15:23:07', '2025-08-05 15:23:07'),
(14, 3, 4, 'Interview & Assessment', 'Student interview and skills assessment', 1, 1, '2025-08-05 15:23:07', '2025-08-05 15:23:07'),
(15, 3, 5, 'Enrollment', 'Complete enrollment and attend freshman orientation', 1, 1, '2025-08-05 15:23:07', '2025-08-05 15:23:07');

-- --------------------------------------------------------

--
-- Table structure for table `board_directors`
--

CREATE TABLE `board_directors` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `position` varchar(255) NOT NULL,
  `bio` text DEFAULT NULL,
  `photo_url` varchar(500) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `linkedin_url` varchar(500) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `board_directors`
--

INSERT INTO `board_directors` (`id`, `name`, `position`, `bio`, `photo_url`, `email`, `phone`, `linkedin_url`, `sort_order`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Engr. John Paul S. Tamayo, MCE/SG', 'School President', 'Former Dean of Engineering at University of Manila with 25+ years in academia and industry leadership. Dr. Santos brings extensive experience in educational administration and strategic planning.', '', 'jptamayo@seait.edu.ph', '', '', 1, 1, 1, '2025-08-05 14:58:28', '2025-08-07 14:17:06'),
(2, 'Engr. Milagros S. Tamayo, MIM', 'Vice President for Admin and Fiannce', 'Engr. Tamayo has led multiple successful technology startups and brings valuable industry insights.', '', 'mst@seait.edu.ph', '', '', 2, 1, 1, '2025-08-05 14:58:28', '2025-08-07 14:18:04'),
(3, 'Engr. Reynaldo S. Tamayo, Sr.', 'Board of Director', 'Former Director of Research at National Science Foundation with PhD in Computer Science. Dr. Reyes has published over 50 research papers and holds 15 patents in software engineering.', '', 'rsts@seait.edu.ph', '', '', 3, 1, 1, '2025-08-05 14:58:28', '2025-08-07 14:19:02'),
(4, 'Reynaldo S. Tamayo Jr., MIT', 'Board of Director', '', '', 'rstjr@seait.edu.ph', '', '', 4, 1, 1, '2025-08-05 14:58:28', '2025-08-07 14:20:31'),
(5, 'Atty. Gizelle Jean Tamayo-Jimenea', 'Director, Office of the School Registrar', 'Professor Emeritus of Information Technology with 30+ years of teaching and research experience. Prof. Mendoza has authored numerous textbooks and served on various academic committees.', '', 'agjtj@seait.edu.ph', '', '', 5, 1, 1, '2025-08-05 14:58:28', '2025-08-07 14:22:27'),
(6, 'Gizhelle Joan Tamayo Sardido', 'Director, Accounting Office', 'Research Director at Asian Technology Institute with expertise in artificial intelligence and robotics. Dr. Rodriguez leads innovative research projects and fosters industry partnerships.', '', 'gtss@seait.edu.ph', '', '', 6, 1, 1, '2025-08-05 14:58:28', '2025-08-07 14:23:16'),
(7, 'Dr. Jeffrey S. Tamayo, MD', 'Board of Director', '', '', 'djst@seait.edu.ph', '', '', 8, 1, 3, '2025-08-07 14:24:16', '2025-08-07 14:24:34');

-- --------------------------------------------------------

--
-- Table structure for table `carousel_slides`
--

CREATE TABLE `carousel_slides` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `subtitle` varchar(500) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `image_url` varchar(255) NOT NULL,
  `button_text` varchar(100) DEFAULT NULL,
  `button_link` varchar(255) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `status` enum('draft','pending','approved','rejected') DEFAULT 'draft',
  `created_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `rejected_by` int(11) DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `carousel_slides`
--

INSERT INTO `carousel_slides` (`id`, `title`, `subtitle`, `description`, `image_url`, `button_text`, `button_link`, `sort_order`, `is_active`, `status`, `created_by`, `approved_by`, `rejected_by`, `rejected_at`, `rejection_reason`, `created_at`, `updated_at`) VALUES
(6, 'Scholarship Granting', 'JY CORPORATION LTD. FOUNDATION', 'January 24, 2025 – The JY Corporation Ltd. Foundation a Korean company, has announced its commitment to empowering young minds by granting scholarships to five deserving students from the South East Asian Institute of Technology, Inc. The scholarship initiative aims to provide financial support and opportunities for academically exceptional and financially challenged students, ensuring they can achieve their educational goals.rn', 'assets/images/carousel/carousel_1754471967.png', 'Know More', '#', 0, 1, 'approved', 3, 2, NULL, NULL, NULL, '2025-08-06 09:19:27', '2025-08-07 12:36:50'),
(7, 'INSET 2021', 'Empowering Teachers in Sustaining Quality of Education', 'Anchored to its mission of providing quality education, the South East Asian Institute of Technology, Inc. launched the In-Service Training (INSET) 2021 to empower teachers in sustaining quality education amidst the new normal.', 'assets/images/carousel/carousel_1754552753.jpg', 'Read More', 'http://seait-edu.ph/blog_inset.php', 2, 1, 'approved', 3, 2, NULL, NULL, NULL, '2025-08-07 07:45:53', '2025-08-07 07:46:26'),
(8, 'GAWAD PARANGAL', 'SEAIT receives a Plaque of Recognition', 'South East Asian Institute of Technology, Inc. has been recognized as a Higher Education institution in Tupi, South Cotabato that has consistently adhered to its advocacy and program of affordable higher education for the Indigenous people and neighboring tribes during the Gawad Parangal for HEIs in Region 12 last July 23, 2021 at South Cotabato Gymnasium, Koronadal City', 'assets/images/carousel/carousel_1754552919.jpg', 'Read More', 'http://seait-edu.ph/blog_gawad.php', 3, 1, 'approved', 3, 2, NULL, NULL, NULL, '2025-08-07 07:48:39', '2025-08-07 07:50:55'),
(9, 'SEAIT facilitates Padyak 2021 with Chairman De Vera', '\"Today marks the celebration of the graduates who are beneficiaries of free education, De Vera said during his speech.', 'The South East Asian Institute of Technology, Inc. facilitated the Padyak 2021: \"Padyak sa Edukasyong Tumpak\" in coordination with the Commission on Higher Education (CHED) Region XII, with Dr. J. Prospero \"Popoy\" E. De Vera III, DPA as the guest last July 22, 202. The opening and kick-off ceremony was held at Britannika Golf Course and Country Club at Brgy. Linan, Tupi South Cotabato.The primary goal of the activity is to commemorate the success of access to free education across the region.', 'assets/images/carousel/carousel_1754553032.jpg', 'Read More', 'http://seait-edu.ph/blog_padyak.php', 4, 1, 'approved', 3, 2, NULL, NULL, NULL, '2025-08-07 07:50:32', '2025-08-07 12:36:50');

-- --------------------------------------------------------

--
-- Table structure for table `class_announcements`
--

CREATE TABLE `class_announcements` (
  `id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `is_pinned` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `class_enrollments`
--

CREATE TABLE `class_enrollments` (
  `id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `join_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('enrolled','dropped','completed') DEFAULT 'enrolled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `class_enrollments`
--

INSERT INTO `class_enrollments` (`id`, `class_id`, `student_id`, `join_date`, `status`, `created_at`, `updated_at`) VALUES
(4, 5, 1, '2025-08-14 15:12:49', 'enrolled', '2025-08-14 15:12:49', NULL),
(5, 4, 2, '2025-08-14 15:22:16', 'enrolled', '2025-08-14 15:22:16', NULL),
(6, 3, 4, '2025-08-14 15:24:52', 'enrolled', '2025-08-14 15:24:52', NULL),
(7, 5, 2, '2025-08-14 16:30:37', 'enrolled', '2025-08-14 16:30:37', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `class_materials`
--

CREATE TABLE `class_materials` (
  `id` int(11) NOT NULL,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
-- Table structure for table `core_values`
--

CREATE TABLE `core_values` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(100) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `core_values`
--

INSERT INTO `core_values` (`id`, `title`, `description`, `icon`, `sort_order`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(4, 'Innovation', 'Embracing creativity and forward-thinking approaches to solve challenges and advance technology education.', 'fas fa-lightbulb', 4, 1, 1, '2025-08-05 15:56:30', '2025-08-05 15:56:30'),
(5, 'Teamwork', 'Fostering collaboration, mutual respect, and collective effort to achieve common goals and shared success.', 'fas fa-users', 5, 1, 1, '2025-08-05 15:56:30', '2025-08-05 15:56:30'),
(6, 'Service', 'Dedicated to providing exceptional service to our students, community, and stakeholders with unwavering commitment to their success and growth.', 'fas fa-hands-helping', 1, 1, 1, '2025-08-05 16:26:14', '2025-08-05 16:26:14'),
(7, 'Excellence', 'Pursuing the highest standards of academic quality, research innovation, and institutional performance in all our endeavors.', 'fas fa-star', 2, 1, 1, '2025-08-05 16:26:14', '2025-08-05 16:26:14'),
(8, 'Accountability', 'Taking responsibility for our actions, decisions, and outcomes while maintaining transparency and ethical practices.', 'fas fa-balance-scale', 3, 1, 1, '2025-08-05 16:26:14', '2025-08-05 16:26:14');

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` int(11) NOT NULL,
  `college_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `short_name` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `logo_url` varchar(255) DEFAULT NULL,
  `level` enum('undergraduate','graduate','postgraduate') NOT NULL,
  `duration` varchar(50) DEFAULT NULL,
  `credits` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `college_id`, `name`, `short_name`, `description`, `logo_url`, `level`, `duration`, `credits`, `is_active`, `sort_order`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 'Bachelor of Science in Hospitality Management', 'BSHM', 'Comprehensive program preparing students for leadership roles in the hospitality and tourism industry.', NULL, 'undergraduate', '4 years', 144, 1, 1, 1, '2025-08-05 11:51:59', '2025-08-05 11:51:59'),
(2, 1, 'Bachelor of Science in Business Administration', 'BSBA', 'Comprehensive business education covering management, marketing, finance, and entrepreneurship.', NULL, 'undergraduate', '4 years', 144, 1, 2, 1, '2025-08-05 11:51:59', '2025-08-05 11:51:59'),
(4, 1, 'Bachelor of Science in Tourism Management', 'BSTM', 'Specialized program for tourism industry leadership and sustainable tourism development.', NULL, 'undergraduate', '4 years', 144, 1, 4, 1, '2025-08-05 11:51:59', '2025-08-05 11:51:59'),
(10, 3, 'Bachelor of Science in Information Technology', 'BSIT', 'Software development and information systems management education.', NULL, 'undergraduate', '4 years', 144, 1, 1, 1, '2025-08-05 11:51:59', '2025-08-05 11:51:59'),
(18, 5, 'Bachelor of Elementary Education', 'BEED', 'Elementary education with specialization in early childhood development.', NULL, 'undergraduate', '4 years', 144, 1, 1, 1, '2025-08-05 11:51:59', '2025-08-05 11:51:59'),
(19, 5, 'Bachelor of Secondary Education', 'BSED', 'Secondary education with subject area specializations.', NULL, 'undergraduate', '4 years', 144, 1, 2, 1, '2025-08-05 11:51:59', '2025-08-05 11:51:59'),
(20, 5, 'Bachelor of Early Childhood Education', 'BECED', 'Early childhood education and development specialization.', NULL, 'undergraduate', '4 years', 144, 1, 3, 1, '2025-08-05 11:51:59', '2025-08-05 11:51:59'),
(22, 8, 'Bachelor of Science in Fishiries', 'BSF', '', '', 'undergraduate', '4 years', 144, 1, 0, 3, '2025-08-07 13:09:43', '2025-08-07 13:09:43'),
(23, 7, 'Bachelor of Science in Criminology', 'BSCrim', '', '', 'undergraduate', '4 years', 144, 1, 0, 3, '2025-08-07 13:10:41', '2025-08-07 13:10:41'),
(24, 6, 'Bachelor of Science in Civil Engineering major in Structural Engineering and Genetics', 'BSCE', '', '', 'undergraduate', '4 years', 144, 1, 0, 3, '2025-08-07 13:13:28', '2025-08-07 13:13:28');

-- --------------------------------------------------------

--
-- Table structure for table `course_curriculum`
--

CREATE TABLE `course_curriculum` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `year_level` enum('first_year','second_year','third_year','fourth_year') NOT NULL,
  `semester` enum('first_semester','second_semester','summer') NOT NULL,
  `subject_code` varchar(20) NOT NULL,
  `subject_title` varchar(255) NOT NULL,
  `units` decimal(3,1) NOT NULL,
  `lecture_hours` int(11) DEFAULT 0,
  `laboratory_hours` int(11) DEFAULT 0,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `prerequisite_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `course_curriculum`
--

INSERT INTO `course_curriculum` (`id`, `course_id`, `year_level`, `semester`, `subject_code`, `subject_title`, `units`, `lecture_hours`, `laboratory_hours`, `description`, `is_active`, `sort_order`, `created_by`, `created_at`, `updated_at`, `prerequisite_id`) VALUES
(1, 1, 'first_year', 'first_semester', 'GE 101', 'Understanding the Self', 3.0, 3, 0, 'Introduction to self-awareness and personal development', 1, 1, 1, '2025-08-05 12:24:37', '2025-08-05 12:24:37', NULL),
(2, 1, 'first_year', 'first_semester', 'GE 102', 'Readings in Philippine History', 3.0, 3, 0, 'Study of Philippine history and culture', 1, 2, 1, '2025-08-05 12:24:37', '2025-08-05 12:24:37', NULL),
(3, 1, 'first_year', 'first_semester', 'GE 103', 'Mathematics in the Modern World', 3.0, 3, 0, 'Application of mathematics in daily life', 1, 3, 1, '2025-08-05 12:24:37', '2025-08-05 12:24:37', NULL),
(4, 1, 'first_year', 'first_semester', 'GE 104', 'Purposive Communication', 3.0, 3, 0, 'Effective communication skills development', 1, 4, 1, '2025-08-05 12:24:37', '2025-08-05 12:24:37', NULL),
(5, 1, 'first_year', 'first_semester', 'BSHM 101', 'Introduction to Hospitality Management', 3.0, 3, 0, 'Overview of hospitality industry and management', 1, 5, 1, '2025-08-05 12:24:37', '2025-08-05 12:24:37', NULL),
(6, 1, 'first_year', 'first_semester', 'BSHM 102', 'Food Safety and Sanitation', 3.0, 2, 3, 'Food safety principles and practices', 1, 6, 1, '2025-08-05 12:24:37', '2025-08-05 12:24:37', NULL),
(7, 1, 'first_year', 'second_semester', 'GE 105', 'Science, Technology and Society', 3.0, 3, 0, 'Impact of science and technology on society', 1, 7, 1, '2025-08-05 12:24:37', '2025-08-05 12:24:37', NULL),
(8, 1, 'first_year', 'second_semester', 'GE 106', 'Art Appreciation', 3.0, 3, 0, 'Understanding and appreciating various art forms', 1, 8, 1, '2025-08-05 12:24:37', '2025-08-05 12:24:37', NULL),
(9, 1, 'first_year', 'second_semester', 'BSHM 103', 'Principles of Tourism', 3.0, 3, 0, 'Fundamentals of tourism industry', 1, 9, 1, '2025-08-05 12:24:37', '2025-08-05 12:24:37', NULL),
(10, 1, 'first_year', 'second_semester', 'BSHM 104', 'Food and Beverage Service', 3.0, 2, 3, 'Food and beverage service techniques', 1, 10, 1, '2025-08-05 12:24:37', '2025-08-05 12:24:37', NULL),
(11, 1, 'first_year', 'second_semester', 'BSHM 105', 'Front Office Operations', 3.0, 2, 3, 'Hotel front office management', 1, 11, 1, '2025-08-05 12:24:37', '2025-08-05 12:24:37', NULL),
(12, 1, 'first_year', 'second_semester', 'BSHM 106', 'Housekeeping Operations', 3.0, 2, 3, 'Hotel housekeeping management', 1, 12, 1, '2025-08-05 12:24:37', '2025-08-05 12:24:37', NULL),
(13, 1, 'second_year', 'first_semester', 'GE 107', 'Ethics', 3.0, 3, 0, 'Moral philosophy and ethical decision making', 1, 13, 1, '2025-08-05 12:24:37', '2025-08-05 12:24:37', NULL),
(14, 1, 'second_year', 'first_semester', 'BSHM 201', 'Hospitality Marketing', 3.0, 3, 0, 'Marketing strategies for hospitality industry', 1, 14, 1, '2025-08-05 12:24:37', '2025-08-05 12:24:37', NULL),
(15, 1, 'second_year', 'first_semester', 'BSHM 202', 'Food Production', 3.0, 2, 3, 'Culinary arts and food preparation', 1, 15, 1, '2025-08-05 12:24:37', '2025-08-05 12:24:37', NULL),
(16, 1, 'second_year', 'first_semester', 'BSHM 203', 'Events Management', 3.0, 3, 0, 'Planning and managing events', 1, 16, 1, '2025-08-05 12:24:37', '2025-08-05 12:24:37', NULL),
(17, 1, 'second_year', 'first_semester', 'BSHM 204', 'Revenue Management', 3.0, 3, 0, 'Revenue optimization strategies', 1, 17, 1, '2025-08-05 12:24:37', '2025-08-05 12:24:37', NULL),
(18, 1, 'second_year', 'second_semester', 'GE 108', 'Contemporary World', 3.0, 3, 0, 'Global issues and contemporary challenges', 1, 18, 1, '2025-08-05 12:24:37', '2025-08-05 12:24:37', NULL),
(19, 1, 'second_year', 'second_semester', 'BSHM 205', 'Hotel Operations Management', 3.0, 3, 0, 'Comprehensive hotel management', 1, 19, 1, '2025-08-05 12:24:37', '2025-08-05 12:24:37', NULL),
(20, 1, 'second_year', 'second_semester', 'BSHM 206', 'Restaurant Management', 3.0, 2, 3, 'Restaurant operations and management', 1, 20, 1, '2025-08-05 12:24:37', '2025-08-05 12:24:37', NULL),
(21, 1, 'second_year', 'second_semester', 'BSHM 207', 'Tourism Planning and Development', 3.0, 3, 0, 'Tourism destination planning', 1, 21, 1, '2025-08-05 12:24:37', '2025-08-05 12:24:37', NULL),
(22, 1, 'second_year', 'second_semester', 'BSHM 208', 'Quality Service Management', 3.0, 3, 0, 'Service quality standards and management', 1, 22, 1, '2025-08-05 12:24:37', '2025-08-05 12:24:37', NULL),
(23, 1, 'third_year', 'first_semester', 'BSHM 301', 'Strategic Management in Hospitality', 3.0, 3, 0, 'Strategic planning for hospitality businesses', 1, 23, 1, '2025-08-05 12:24:37', '2025-08-05 12:24:37', NULL),
(24, 1, 'third_year', 'first_semester', 'BSHM 302', 'Financial Management for Hospitality', 3.0, 3, 0, 'Financial management in hospitality industry', 1, 24, 1, '2025-08-05 12:24:37', '2025-08-05 12:24:37', NULL),
(25, 1, 'third_year', 'first_semester', 'BSHM 303', 'Human Resource Management', 3.0, 3, 0, 'HR management in hospitality', 1, 25, 1, '2025-08-05 12:24:37', '2025-08-05 12:24:37', NULL),
(26, 1, 'third_year', 'first_semester', 'BSHM 304', 'International Tourism', 3.0, 3, 0, 'Global tourism trends and practices', 1, 26, 1, '2025-08-05 12:24:37', '2025-08-05 12:24:37', NULL),
(27, 1, 'third_year', 'second_semester', 'BSHM 305', 'Research Methods in Hospitality', 3.0, 3, 0, 'Research methodology for hospitality studies', 1, 27, 1, '2025-08-05 12:24:37', '2025-08-05 12:24:37', NULL),
(28, 1, 'third_year', 'second_semester', 'BSHM 306', 'Sustainable Tourism', 3.0, 3, 0, 'Sustainable tourism practices', 1, 28, 1, '2025-08-05 12:24:37', '2025-08-05 12:24:37', NULL),
(29, 1, 'third_year', 'second_semester', 'BSHM 307', 'Crisis Management', 3.0, 3, 0, 'Crisis management in hospitality', 1, 29, 1, '2025-08-05 12:24:37', '2025-08-05 12:24:37', NULL),
(30, 1, 'third_year', 'second_semester', 'BSHM 308', 'Internship I', 6.0, 0, 18, 'First internship experience', 1, 30, 1, '2025-08-05 12:24:37', '2025-08-05 12:24:37', NULL),
(31, 1, 'fourth_year', 'first_semester', 'BSHM 401', 'Thesis Writing I', 3.0, 3, 0, 'Research project development', 1, 31, 1, '2025-08-05 12:24:37', '2025-08-05 12:24:37', NULL),
(32, 1, 'fourth_year', 'first_semester', 'BSHM 402', 'Advanced Hospitality Management', 3.0, 3, 0, 'Advanced management concepts', 1, 32, 1, '2025-08-05 12:24:37', '2025-08-05 12:24:37', NULL),
(33, 1, 'fourth_year', 'first_semester', 'BSHM 403', 'Entrepreneurship in Hospitality', 3.0, 3, 0, 'Starting hospitality businesses', 1, 33, 1, '2025-08-05 12:24:37', '2025-08-05 12:24:37', NULL),
(34, 1, 'fourth_year', 'first_semester', 'BSHM 404', 'Elective Course I', 3.0, 3, 0, 'Specialized hospitality course', 1, 34, 1, '2025-08-05 12:24:37', '2025-08-05 12:24:37', NULL),
(35, 1, 'fourth_year', 'second_semester', 'BSHM 405', 'Thesis Writing II', 3.0, 3, 0, 'Research project completion', 1, 35, 1, '2025-08-05 12:24:37', '2025-08-05 12:24:37', NULL),
(36, 1, 'fourth_year', 'second_semester', 'BSHM 406', 'Internship II', 6.0, 0, 18, 'Final internship experience', 1, 36, 1, '2025-08-05 12:24:37', '2025-08-05 12:24:37', NULL),
(37, 1, 'fourth_year', 'second_semester', 'BSHM 407', 'Elective Course II', 3.0, 3, 0, 'Specialized hospitality course', 1, 37, 1, '2025-08-05 12:24:37', '2025-08-05 12:24:37', NULL),
(38, 1, 'fourth_year', 'second_semester', 'BSHM 408', 'Comprehensive Examination', 0.0, 0, 0, 'Final comprehensive examination', 1, 38, 1, '2025-08-05 12:24:37', '2025-08-05 12:24:37', NULL),
(39, 1, 'first_year', 'first_semester', 'GE 101', 'Understanding the Self', 3.0, 3, 0, 'Introduction to self-awareness and personal development', 1, 1, 1, '2025-08-05 13:12:45', '2025-08-05 13:12:45', NULL),
(40, 1, 'first_year', 'first_semester', 'GE 102', 'Readings in Philippine History', 3.0, 3, 0, 'Study of Philippine history and culture', 1, 2, 1, '2025-08-05 13:12:45', '2025-08-05 13:12:45', NULL),
(41, 1, 'first_year', 'first_semester', 'GE 103', 'Mathematics in the Modern World', 3.0, 3, 0, 'Application of mathematics in daily life', 1, 3, 1, '2025-08-05 13:12:45', '2025-08-05 13:12:45', NULL),
(42, 1, 'first_year', 'first_semester', 'GE 104', 'Purposive Communication', 3.0, 3, 0, 'Effective communication skills development', 1, 4, 1, '2025-08-05 13:12:45', '2025-08-05 13:12:45', NULL),
(43, 1, 'first_year', 'first_semester', 'BSHM 101', 'Introduction to Hospitality Management', 3.0, 3, 0, 'Overview of hospitality industry and management', 1, 5, 1, '2025-08-05 13:12:45', '2025-08-05 13:12:45', NULL),
(44, 1, 'first_year', 'first_semester', 'BSHM 102', 'Food Safety and Sanitation', 3.0, 2, 3, 'Food safety principles and practices', 1, 6, 1, '2025-08-05 13:12:45', '2025-08-05 13:12:45', NULL),
(45, 1, 'first_year', 'second_semester', 'GE 105', 'Science, Technology and Society', 3.0, 3, 0, 'Impact of science and technology on society', 1, 7, 1, '2025-08-05 13:12:45', '2025-08-05 13:12:45', NULL),
(46, 1, 'first_year', 'second_semester', 'GE 106', 'Art Appreciation', 3.0, 3, 0, 'Understanding and appreciating various art forms', 1, 8, 1, '2025-08-05 13:12:45', '2025-08-05 13:12:45', NULL),
(47, 1, 'first_year', 'second_semester', 'BSHM 103', 'Principles of Tourism', 3.0, 3, 0, 'Fundamentals of tourism industry', 1, 9, 1, '2025-08-05 13:12:45', '2025-08-05 13:12:45', NULL),
(48, 1, 'first_year', 'second_semester', 'BSHM 104', 'Food and Beverage Service', 3.0, 2, 3, 'Food and beverage service techniques', 1, 10, 1, '2025-08-05 13:12:45', '2025-08-05 13:12:45', NULL),
(49, 1, 'first_year', 'second_semester', 'BSHM 105', 'Front Office Operations', 3.0, 2, 3, 'Hotel front office management', 1, 11, 1, '2025-08-05 13:12:45', '2025-08-05 13:12:45', NULL),
(50, 1, 'first_year', 'second_semester', 'BSHM 106', 'Housekeeping Operations', 3.0, 2, 3, 'Hotel housekeeping management', 1, 12, 1, '2025-08-05 13:12:45', '2025-08-05 13:12:45', NULL),
(51, 1, 'second_year', 'first_semester', 'GE 107', 'Ethics', 3.0, 3, 0, 'Moral philosophy and ethical decision making', 1, 13, 1, '2025-08-05 13:12:45', '2025-08-05 13:12:45', NULL),
(52, 1, 'second_year', 'first_semester', 'BSHM 201', 'Hospitality Marketing', 3.0, 3, 0, 'Marketing strategies for hospitality industry', 1, 14, 1, '2025-08-05 13:12:45', '2025-08-05 13:12:45', NULL),
(53, 1, 'second_year', 'first_semester', 'BSHM 202', 'Food Production', 3.0, 2, 3, 'Culinary arts and food preparation', 1, 15, 1, '2025-08-05 13:12:45', '2025-08-05 13:12:45', NULL),
(54, 1, 'second_year', 'first_semester', 'BSHM 203', 'Events Management', 3.0, 3, 0, 'Planning and managing events', 1, 16, 1, '2025-08-05 13:12:45', '2025-08-05 13:12:45', NULL),
(55, 1, 'second_year', 'first_semester', 'BSHM 204', 'Revenue Management', 3.0, 3, 0, 'Revenue optimization strategies', 1, 17, 1, '2025-08-05 13:12:45', '2025-08-05 13:12:45', NULL),
(56, 1, 'second_year', 'second_semester', 'GE 108', 'Contemporary World', 3.0, 3, 0, 'Global issues and contemporary challenges', 1, 18, 1, '2025-08-05 13:12:45', '2025-08-05 13:12:45', NULL),
(57, 1, 'second_year', 'second_semester', 'BSHM 205', 'Hotel Operations Management', 3.0, 3, 0, 'Comprehensive hotel management', 1, 19, 1, '2025-08-05 13:12:45', '2025-08-05 13:12:45', NULL),
(58, 1, 'second_year', 'second_semester', 'BSHM 206', 'Restaurant Management', 3.0, 2, 3, 'Restaurant operations and management', 1, 20, 1, '2025-08-05 13:12:45', '2025-08-05 13:12:45', NULL),
(59, 1, 'second_year', 'second_semester', 'BSHM 207', 'Tourism Planning and Development', 3.0, 3, 0, 'Tourism destination planning', 1, 21, 1, '2025-08-05 13:12:45', '2025-08-05 13:12:45', NULL),
(60, 1, 'second_year', 'second_semester', 'BSHM 208', 'Quality Service Management', 3.0, 3, 0, 'Service quality standards and management', 1, 22, 1, '2025-08-05 13:12:45', '2025-08-05 13:12:45', NULL),
(61, 1, 'third_year', 'first_semester', 'BSHM 301', 'Strategic Management in Hospitality', 3.0, 3, 0, 'Strategic planning for hospitality businesses', 1, 23, 1, '2025-08-05 13:12:45', '2025-08-05 13:12:45', NULL),
(62, 1, 'third_year', 'first_semester', 'BSHM 302', 'Financial Management for Hospitality', 3.0, 3, 0, 'Financial management in hospitality industry', 1, 24, 1, '2025-08-05 13:12:45', '2025-08-05 13:12:45', NULL),
(63, 1, 'third_year', 'first_semester', 'BSHM 303', 'Human Resource Management', 3.0, 3, 0, 'HR management in hospitality', 1, 25, 1, '2025-08-05 13:12:45', '2025-08-05 13:12:45', NULL),
(64, 1, 'third_year', 'first_semester', 'BSHM 304', 'International Tourism', 3.0, 3, 0, 'Global tourism trends and practices', 1, 26, 1, '2025-08-05 13:12:45', '2025-08-05 13:12:45', NULL),
(65, 1, 'third_year', 'second_semester', 'BSHM 305', 'Research Methods in Hospitality', 3.0, 3, 0, 'Research methodology for hospitality studies', 1, 27, 1, '2025-08-05 13:12:45', '2025-08-05 13:12:45', NULL),
(66, 1, 'third_year', 'second_semester', 'BSHM 306', 'Sustainable Tourism', 3.0, 3, 0, 'Sustainable tourism practices', 1, 28, 1, '2025-08-05 13:12:45', '2025-08-05 13:12:45', NULL),
(67, 1, 'third_year', 'second_semester', 'BSHM 307', 'Crisis Management', 3.0, 3, 0, 'Crisis management in hospitality', 1, 29, 1, '2025-08-05 13:12:45', '2025-08-05 13:12:45', NULL),
(68, 1, 'third_year', 'second_semester', 'BSHM 308', 'Internship I', 6.0, 0, 18, 'First internship experience', 1, 30, 1, '2025-08-05 13:12:45', '2025-08-05 13:12:45', NULL),
(69, 1, 'fourth_year', 'first_semester', 'BSHM 401', 'Thesis Writing I', 3.0, 3, 0, 'Research project development', 1, 31, 1, '2025-08-05 13:12:45', '2025-08-05 13:12:45', NULL),
(70, 1, 'fourth_year', 'first_semester', 'BSHM 402', 'Advanced Hospitality Management', 3.0, 3, 0, 'Advanced management concepts', 1, 32, 1, '2025-08-05 13:12:45', '2025-08-05 13:12:45', NULL),
(71, 1, 'fourth_year', 'first_semester', 'BSHM 403', 'Entrepreneurship in Hospitality', 3.0, 3, 0, 'Starting hospitality businesses', 1, 33, 1, '2025-08-05 13:12:45', '2025-08-05 13:12:45', NULL),
(72, 1, 'fourth_year', 'first_semester', 'BSHM 404', 'Elective Course I', 3.0, 3, 0, 'Specialized hospitality course', 1, 34, 1, '2025-08-05 13:12:45', '2025-08-05 13:12:45', NULL),
(73, 1, 'fourth_year', 'second_semester', 'BSHM 405', 'Thesis Writing II', 3.0, 3, 0, 'Research project completion', 1, 35, 1, '2025-08-05 13:12:45', '2025-08-05 13:12:45', NULL),
(74, 1, 'fourth_year', 'second_semester', 'BSHM 406', 'Internship II', 6.0, 0, 18, 'Final internship experience', 1, 36, 1, '2025-08-05 13:12:45', '2025-08-05 13:12:45', NULL),
(75, 1, 'fourth_year', 'second_semester', 'BSHM 407', 'Elective Course II', 3.0, 3, 0, 'Specialized hospitality course', 1, 37, 1, '2025-08-05 13:12:45', '2025-08-05 13:12:45', NULL),
(76, 1, 'fourth_year', 'second_semester', 'BSHM 408', 'Comprehensive Examination', 0.0, 0, 0, 'Final comprehensive examination', 1, 38, 1, '2025-08-05 13:12:45', '2025-08-05 13:12:45', NULL),
(77, 10, 'first_year', 'first_semester', 'ITCC111', 'Introduction to Computing', 3.0, 3, 0, '0', 1, 1, 3, '2025-08-14 15:06:54', '2025-08-14 15:06:54', NULL),
(78, 10, 'second_year', 'first_semester', 'ITCC115', 'Information Management', 3.0, 2, 3, '0', 1, 1, 3, '2025-08-14 15:07:40', '2025-08-14 15:07:40', NULL),
(79, 10, 'third_year', 'first_semester', 'ITCC116', 'Application Development and Emerging Technologies', 3.0, 2, 3, '0', 1, 1, 3, '2025-08-14 15:08:33', '2025-08-14 15:08:33', 77);

-- --------------------------------------------------------

--
-- Table structure for table `course_requirements`
--

CREATE TABLE `course_requirements` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `requirement_type` enum('admission','graduation','prerequisite') NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `is_required` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `course_requirements`
--

INSERT INTO `course_requirements` (`id`, `course_id`, `requirement_type`, `title`, `description`, `is_required`, `sort_order`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 'admission', 'High School Diploma', 'Must have completed high school education or equivalent', 1, 1, 1, '2025-08-05 12:24:37', '2025-08-05 12:24:37'),
(2, 1, 'admission', 'English Proficiency', 'Must demonstrate proficiency in English language', 1, 2, 1, '2025-08-05 12:24:37', '2025-08-05 12:24:37'),
(3, 1, 'admission', 'Mathematics Background', 'Basic mathematics knowledge required', 1, 3, 1, '2025-08-05 12:24:37', '2025-08-05 12:24:37'),
(4, 1, 'graduation', 'Complete 144 Credit Units', 'Must complete all required credit units', 1, 4, 1, '2025-08-05 12:24:37', '2025-08-05 12:24:37'),
(5, 1, 'graduation', 'Internship Completion', 'Must complete required internship hours', 1, 5, 1, '2025-08-05 12:24:37', '2025-08-05 12:24:37'),
(6, 1, 'graduation', 'Thesis/Research Project', 'Must complete final research project', 1, 6, 1, '2025-08-05 12:24:37', '2025-08-05 12:24:37'),
(7, 1, 'admission', 'High School Diploma', 'Must have completed high school education or equivalent', 1, 1, 1, '2025-08-05 13:12:45', '2025-08-05 13:12:45'),
(8, 1, 'admission', 'English Proficiency', 'Must demonstrate proficiency in English language', 1, 2, 1, '2025-08-05 13:12:45', '2025-08-05 13:12:45'),
(9, 1, 'admission', 'Mathematics Background', 'Basic mathematics knowledge required', 1, 3, 1, '2025-08-05 13:12:45', '2025-08-05 13:12:45'),
(10, 1, 'graduation', 'Complete 144 Credit Units', 'Must complete all required credit units', 1, 4, 1, '2025-08-05 13:12:45', '2025-08-05 13:12:45'),
(11, 1, 'graduation', 'Internship Completion', 'Must complete required internship hours', 1, 5, 1, '2025-08-05 13:12:45', '2025-08-05 13:12:45'),
(12, 1, 'graduation', 'Thesis/Research Project', 'Must complete final research project', 1, 6, 1, '2025-08-05 13:12:45', '2025-08-05 13:12:45');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
-- Table structure for table `department_contacts`
--

CREATE TABLE `department_contacts` (
  `id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `contact_type` enum('phone','email','address','social_media','website','office_hours') NOT NULL,
  `title` varchar(255) NOT NULL,
  `contact_value` text NOT NULL,
  `icon` varchar(100) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `department_contacts`
--

INSERT INTO `department_contacts` (`id`, `department_id`, `contact_type`, `title`, `contact_value`, `icon`, `sort_order`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 'phone', 'Main Phone', '+63 123 456 7890', 'fas fa-phone', 1, 1, 1, '2025-08-05 16:50:53', '2025-08-05 16:50:53'),
(2, 1, 'email', 'General Email', 'admissions@seait.edu.ph', 'fas fa-envelope', 2, 1, 1, '2025-08-05 16:50:53', '2025-08-05 16:50:53'),
(3, 1, 'address', 'Office Location', 'Room 101, Main Building, SEAIT Campus', 'fas fa-map-marker-alt', 3, 1, 1, '2025-08-05 16:50:53', '2025-08-05 16:50:53'),
(4, 1, 'office_hours', 'Office Hours', 'Monday - Friday: 8:00 AM - 5:00 PM', 'fas fa-clock', 4, 1, 1, '2025-08-05 16:50:53', '2025-08-05 16:50:53'),
(5, 2, 'phone', 'Department Phone', '+63 123 456 7891', 'fas fa-phone', 1, 1, 1, '2025-08-05 16:50:53', '2025-08-05 16:50:53'),
(6, 2, 'email', 'Academic Email', 'academic@seait.edu.ph', 'fas fa-envelope', 2, 1, 1, '2025-08-05 16:50:53', '2025-08-05 16:50:53'),
(7, 2, 'address', 'Office Location', 'Room 201, Academic Building', 'fas fa-map-marker-alt', 3, 1, 1, '2025-08-05 16:50:53', '2025-08-05 16:50:53'),
(8, 2, 'office_hours', 'Office Hours', 'Monday - Friday: 8:00 AM - 6:00 PM', 'fas fa-clock', 4, 1, 1, '2025-08-05 16:50:53', '2025-08-05 16:50:53'),
(9, 3, 'phone', 'Student Services', '+63 123 456 7892', 'fas fa-phone', 1, 1, 1, '2025-08-05 16:50:53', '2025-08-05 16:50:53'),
(10, 3, 'email', 'Student Support', 'studentservices@seait.edu.ph', 'fas fa-envelope', 2, 1, 1, '2025-08-05 16:50:53', '2025-08-05 16:50:53'),
(11, 3, 'address', 'Office Location', 'Student Center, Ground Floor', 'fas fa-map-marker-alt', 3, 1, 1, '2025-08-05 16:50:53', '2025-08-05 16:50:53'),
(12, 3, 'office_hours', 'Office Hours', 'Monday - Saturday: 8:00 AM - 7:00 PM', 'fas fa-clock', 4, 1, 1, '2025-08-05 16:50:53', '2025-08-05 16:50:53'),
(13, 4, 'phone', 'IT Help Desk', '+63 123 456 7893', 'fas fa-phone', 1, 1, 1, '2025-08-05 16:50:53', '2025-08-05 16:50:53'),
(14, 4, 'email', 'Technical Support', 'itsupport@seait.edu.ph', 'fas fa-envelope', 2, 1, 1, '2025-08-05 16:50:53', '2025-08-05 16:50:53'),
(15, 4, 'address', 'Office Location', 'IT Center, Basement Level', 'fas fa-map-marker-alt', 3, 1, 1, '2025-08-05 16:50:53', '2025-08-05 16:50:53'),
(16, 4, 'office_hours', 'Office Hours', 'Monday - Friday: 7:00 AM - 8:00 PM', 'fas fa-clock', 4, 1, 1, '2025-08-05 16:50:53', '2025-08-05 16:50:53'),
(17, 5, 'phone', 'Finance Department', '+63 123 456 7894', 'fas fa-phone', 1, 1, 1, '2025-08-05 16:50:53', '2025-08-05 16:50:53'),
(18, 5, 'email', 'Finance Email', 'finance@seait.edu.ph', 'fas fa-envelope', 2, 1, 1, '2025-08-05 16:50:53', '2025-08-05 16:50:53'),
(19, 5, 'address', 'Office Location', 'Finance Building, Room 301', 'fas fa-map-marker-alt', 3, 1, 1, '2025-08-05 16:50:53', '2025-08-05 16:50:53'),
(20, 5, 'office_hours', 'Office Hours', 'Monday - Friday: 8:00 AM - 5:00 PM', 'fas fa-clock', 4, 1, 1, '2025-08-05 16:50:53', '2025-08-05 16:50:53'),
(21, 6, 'phone', 'HR Department', '+63 123 456 7895', 'fas fa-phone', 1, 1, 1, '2025-08-05 16:50:53', '2025-08-05 16:50:53'),
(22, 6, 'email', 'HR Email', 'hr@seait.edu.ph', 'fas fa-envelope', 2, 1, 1, '2025-08-05 16:50:53', '2025-08-05 16:50:53'),
(23, 6, 'address', 'Office Location', 'Admin Building, Room 401', 'fas fa-map-marker-alt', 3, 1, 1, '2025-08-05 16:50:53', '2025-08-05 16:50:53'),
(24, 6, 'office_hours', 'Office Hours', 'Monday - Friday: 8:00 AM - 5:00 PM', 'fas fa-clock', 4, 1, 1, '2025-08-05 16:50:53', '2025-08-05 16:50:53');

-- --------------------------------------------------------

--
-- Table structure for table `evaluation_categories`
--

CREATE TABLE `evaluation_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `evaluation_type` enum('student_to_teacher','teacher_to_teacher','head_to_teacher') NOT NULL DEFAULT 'student_to_teacher',
  `semester_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `evaluation_status` enum('enabled','disabled') NOT NULL DEFAULT 'disabled',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `evaluation_categories`
--

INSERT INTO `evaluation_categories` (`id`, `name`, `description`, `evaluation_type`, `semester_id`, `status`, `evaluation_status`, `created_by`, `created_at`, `updated_at`) VALUES
(2, 'Behavioral Assessment', 'Assessment of student behavior and conduct', 'student_to_teacher', NULL, 'active', 'disabled', 1, '2025-08-10 12:54:03', NULL),
(3, 'Social Skills', 'Evaluation of interpersonal and social interaction skills', 'student_to_teacher', NULL, 'active', 'disabled', 1, '2025-08-10 12:54:03', NULL),
(7, 'Subject Knowledge', 'Assessment of teacher\'s mastery of the subject matter', 'student_to_teacher', NULL, 'active', 'disabled', 1, '2025-08-10 13:37:28', NULL),
(8, 'Classroom Management', 'Evaluation of classroom discipline and organization', 'student_to_teacher', NULL, 'active', 'disabled', 1, '2025-08-10 13:37:28', NULL),
(9, 'Communication Skills', 'Assessment of teacher\'s communication with students', 'student_to_teacher', NULL, 'active', 'disabled', 1, '2025-08-10 13:37:28', NULL),
(10, 'Professional Competence', 'Evaluation of colleague\'s professional skills', 'teacher_to_teacher', NULL, 'active', 'disabled', 1, '2025-08-10 13:37:28', NULL),
(11, 'Collaboration', 'Assessment of teamwork and cooperation', 'teacher_to_teacher', NULL, 'active', 'disabled', 1, '2025-08-10 13:37:28', NULL),
(12, 'Innovation', 'Evaluation of teaching innovations and creativity', 'teacher_to_teacher', NULL, 'active', 'disabled', 1, '2025-08-10 13:37:28', NULL),
(13, 'Leadership', 'Assessment of leadership qualities and initiative', 'head_to_teacher', NULL, 'active', 'disabled', 1, '2025-08-10 13:37:28', NULL),
(14, 'Administrative Skills', 'Evaluation of administrative and organizational skills', 'head_to_teacher', NULL, 'active', 'disabled', 1, '2025-08-10 13:37:28', NULL),
(15, 'Professional Development', 'Assessment of continuous learning and growth', 'head_to_teacher', NULL, 'active', 'disabled', 1, '2025-08-10 13:37:28', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `evaluation_questionnaires`
--

CREATE TABLE `evaluation_questionnaires` (
  `id` int(11) NOT NULL,
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
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `evaluation_questionnaires`
--

INSERT INTO `evaluation_questionnaires` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 'How well does the teacher maintain classroom discipline?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 1, 'active', 1, '2025-08-10 15:14:16', '2025-08-13 15:33:34'),
(2, 1, 'How organized is the classroom environment?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 2, 'active', 1, '2025-08-10 15:14:16', NULL),
(6, 2, 'How clear and understandable are the teacher\'s explanations?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 1, 'active', 1, '2025-08-10 15:14:16', NULL),
(7, 2, 'How well does the teacher use different teaching methods?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 2, 'active', 1, '2025-08-10 15:14:16', NULL),
(11, 5, 'How well does the colleague demonstrate subject matter expertise?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 1, 'active', 1, '2025-08-10 15:14:16', NULL),
(12, 5, 'How effectively does the colleague plan and organize lessons?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 2, 'active', 1, '2025-08-10 15:14:16', NULL),
(19, 1, 'How well does the teacher maintain classroom discipline?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 1, 'active', 1, '2025-08-12 07:34:33', NULL),
(20, 1, 'Does the teacher start and end classes on time?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 2, 'active', 1, '2025-08-12 07:34:33', NULL),
(26, 2, 'How clear and understandable are the teacher\'s explanations?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 1, 'active', 1, '2025-08-12 07:34:33', NULL),
(27, 2, 'Does the teacher use effective teaching methods and strategies?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 2, 'active', 1, '2025-08-12 07:34:33', NULL),
(34, 3, 'How well does the teacher demonstrate mastery of the subject matter?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 1, 'active', 1, '2025-08-12 07:34:33', NULL),
(35, 3, 'Does the teacher provide accurate and up-to-date information?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 2, 'active', 1, '2025-08-12 07:34:33', NULL),
(36, 3, 'How well does the teacher connect concepts to real-world applications?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 3, 'active', 1, '2025-08-12 07:34:33', NULL),
(37, 3, 'Does the teacher answer student questions accurately and thoroughly?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 4, 'active', 1, '2025-08-12 07:34:33', NULL),
(41, 4, 'How clear and audible is the teacher\'s voice?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 1, 'active', 1, '2025-08-12 07:34:33', NULL),
(42, 4, 'Does the teacher speak at an appropriate pace for students to follow?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 2, 'active', 1, '2025-08-12 07:34:33', NULL),
(43, 4, 'How well does the teacher use body language and gestures?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 3, 'active', 1, '2025-08-12 07:34:33', NULL),
(44, 4, 'Does the teacher listen actively to student questions and concerns?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 4, 'active', 1, '2025-08-12 07:34:33', NULL),
(49, 5, 'How well does the teacher motivate students to participate?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 1, 'active', 1, '2025-08-12 07:34:33', NULL),
(50, 5, 'Does the teacher create interesting and engaging lessons?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 2, 'active', 1, '2025-08-12 07:34:33', NULL),
(89, 10, 'How well does the teacher demonstrate leadership qualities?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 1, 'active', 1, '2025-08-12 07:34:33', NULL),
(90, 10, 'Does the teacher take initiative in school improvement projects?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 2, 'active', 1, '2025-08-12 07:34:33', NULL),
(91, 10, 'How well does the teacher inspire and motivate other staff members?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 3, 'active', 1, '2025-08-12 07:34:33', NULL),
(92, 10, 'Does the teacher demonstrate vision and strategic thinking?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 4, 'active', 1, '2025-08-12 07:34:33', NULL),
(97, 11, 'How well does the teacher manage administrative tasks and paperwork?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 1, 'active', 1, '2025-08-12 07:34:33', NULL),
(98, 11, 'Does the teacher submit reports and documents on time?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 2, 'active', 1, '2025-08-12 07:34:33', NULL),
(99, 11, 'How well does the teacher organize and maintain records?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 3, 'active', 1, '2025-08-12 07:34:33', NULL),
(100, 11, 'Does the teacher follow administrative procedures correctly?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 4, 'active', 1, '2025-08-12 07:34:33', NULL),
(105, 12, 'How committed is the teacher to continuous professional learning?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 1, 'active', 1, '2025-08-12 07:34:33', NULL),
(106, 12, 'Does the teacher actively participate in training and workshops?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 2, 'active', 1, '2025-08-12 07:34:33', NULL),
(107, 12, 'How well does the teacher apply new learning to their practice?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 3, 'active', 1, '2025-08-12 07:34:33', NULL),
(108, 12, 'Does the teacher seek feedback and reflect on their teaching?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 4, 'active', 1, '2025-08-12 07:34:33', NULL),
(113, 13, 'How well does the teacher follow school policies and procedures?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 1, 'active', 1, '2025-08-12 07:34:33', NULL),
(114, 13, 'Does the teacher comply with curriculum standards and requirements?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 2, 'active', 1, '2025-08-12 07:34:33', NULL),
(115, 13, 'How well does the teacher adhere to safety and security protocols?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 3, 'active', 1, '2025-08-12 07:34:33', NULL),
(116, 13, 'Does the teacher follow ethical guidelines and professional standards?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 4, 'active', 1, '2025-08-12 07:34:33', NULL),
(121, 1, 'What specific issues have you observed with classroom discipline?', 'text', NULL, NULL, 0, 5, 'active', 1, '2025-08-14 05:28:27', NULL),
(122, 1, 'How does the teacher handle disruptive students?', 'text', NULL, NULL, 0, 6, 'active', 1, '2025-08-14 05:28:27', NULL),
(123, 2, 'What teaching methods do you find most ineffective?', 'text', NULL, NULL, 0, 5, 'active', 1, '2025-08-14 05:28:27', NULL),
(124, 2, 'How could the teacher improve their lesson delivery?', 'text', NULL, NULL, 0, 6, 'active', 1, '2025-08-14 05:28:27', NULL),
(125, 3, 'What topics does the teacher struggle to explain clearly?', 'text', NULL, NULL, 0, 5, 'active', 1, '2025-08-14 05:28:27', NULL),
(126, 3, 'What mistakes have you noticed in the teacher\'s subject knowledge?', 'text', NULL, NULL, 0, 6, 'active', 1, '2025-08-14 05:28:27', NULL),
(127, 4, 'What communication problems have you experienced with this teacher?', 'text', NULL, NULL, 0, 5, 'active', 1, '2025-08-14 05:28:27', NULL),
(128, 4, 'How does the teacher respond to student questions?', 'text', NULL, NULL, 0, 6, 'active', 1, '2025-08-14 05:28:27', NULL),
(129, 5, 'What makes the class boring or unengaging?', 'text', NULL, NULL, 0, 5, 'active', 1, '2025-08-14 05:28:27', NULL),
(130, 5, 'How could the teacher better motivate students?', 'text', NULL, NULL, 0, 6, 'active', 1, '2025-08-14 05:28:27', NULL),
(131, 18, 'How well does the colleague demonstrate subject matter expertise?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 1, 'active', 1, '2025-08-14 10:47:27', NULL),
(132, 18, 'How effectively does the colleague plan and organize lessons?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 2, 'active', 1, '2025-08-14 10:47:27', NULL),
(133, 18, 'How well does the colleague assess student learning?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 3, 'active', 1, '2025-08-14 10:47:27', NULL),
(134, 18, 'How committed is the colleague to professional development?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 4, 'active', 1, '2025-08-14 10:47:27', NULL),
(135, 18, 'How well does the colleague stay updated with current educational trends?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 5, 'active', 1, '2025-08-14 10:47:27', NULL),
(136, 19, 'How well does the colleague work with other faculty members?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 1, 'active', 1, '2025-08-14 10:47:27', NULL),
(137, 19, 'How effectively does the colleague share resources and materials?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 2, 'active', 1, '2025-08-14 10:47:27', NULL),
(138, 19, 'How well does the colleague participate in department meetings?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 3, 'active', 1, '2025-08-14 10:47:27', NULL),
(139, 19, 'How supportive is the colleague in team projects?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 4, 'active', 1, '2025-08-14 10:47:27', NULL),
(140, 19, 'How well does the colleague communicate with other staff members?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 5, 'active', 1, '2025-08-14 10:47:27', NULL),
(141, 20, 'How creative is the colleague in developing teaching methods?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 1, 'active', 1, '2025-08-14 10:47:27', NULL),
(142, 20, 'How well does the colleague incorporate new technologies in teaching?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 2, 'active', 1, '2025-08-14 10:47:27', NULL),
(143, 20, 'How innovative is the colleague in curriculum development?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 3, 'active', 1, '2025-08-14 10:47:27', NULL),
(144, 20, 'How well does the colleague adapt to new educational approaches?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 4, 'active', 1, '2025-08-14 10:47:27', NULL),
(145, 20, 'How effectively does the colleague implement new ideas in the classroom?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 5, 'active', 1, '2025-08-14 10:47:27', NULL),
(146, 21, 'How well does the colleague mentor new faculty members?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 1, 'active', 1, '2025-08-14 10:47:27', NULL),
(147, 21, 'How effectively does the colleague provide guidance to colleagues?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 2, 'active', 1, '2025-08-14 10:47:27', NULL),
(148, 21, 'How supportive is the colleague in professional development?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 3, 'active', 1, '2025-08-14 10:47:27', NULL),
(149, 21, 'How well does the colleague share knowledge and expertise?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 4, 'active', 1, '2025-08-14 10:47:27', NULL),
(150, 21, 'How approachable is the colleague for advice and consultation?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 5, 'active', 1, '2025-08-14 10:47:27', NULL),
(151, 18, 'What specific areas of professional development would you recommend for this colleague?', 'text', NULL, NULL, 0, 6, 'active', 1, '2025-08-14 17:22:44', NULL),
(152, 19, 'How could this colleague improve their collaboration with the team?', 'text', NULL, NULL, 0, 6, 'active', 1, '2025-08-14 17:22:49', NULL),
(153, 20, 'What innovative teaching approaches have you observed from this colleague?', 'text', NULL, NULL, 0, 6, 'active', 1, '2025-08-14 17:22:55', NULL),
(154, 21, 'What specific mentoring strengths does this colleague demonstrate?', 'text', NULL, NULL, 0, 6, 'active', 1, '2025-08-14 17:23:04', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `evaluation_questionnaires_backup`
--

CREATE TABLE `evaluation_questionnaires_backup` (
  `id` int(11) NOT NULL DEFAULT 0,
  `sub_category_id` int(11) NOT NULL,
  `question_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `question_type` enum('rating_1_5','text','yes_no','multiple_choice') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'rating_1_5',
  `rating_labels` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Custom labels for 1-5 scale' CHECK (json_valid(`rating_labels`)),
  `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'For multiple choice questions' CHECK (json_valid(`options`)),
  `required` tinyint(1) DEFAULT 1,
  `order_number` int(11) DEFAULT 0,
  `status` enum('active','inactive') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `evaluation_questionnaires_backup`
--

INSERT INTO `evaluation_questionnaires_backup` (`id`, `sub_category_id`, `question_text`, `question_type`, `rating_labels`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 'How well does the teacher maintain classroom discipline?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 1, 'active', 1, '2025-08-10 15:14:16', NULL),
(2, 1, 'How organized is the classroom environment?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 2, 'active', 1, '2025-08-10 15:14:16', NULL),
(3, 1, 'How effectively does the teacher handle disruptive behavior?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 3, 'active', 1, '2025-08-10 15:14:16', NULL),
(4, 1, 'How well does the teacher manage time during lessons?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 4, 'active', 1, '2025-08-10 15:14:16', NULL),
(5, 1, 'How conducive is the learning atmosphere in the classroom?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 5, 'active', 1, '2025-08-10 15:14:16', NULL),
(6, 2, 'How clear and understandable are the teacher\'s explanations?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 1, 'active', 1, '2025-08-10 15:14:16', NULL),
(7, 2, 'How well does the teacher use different teaching methods?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 2, 'active', 1, '2025-08-10 15:14:16', NULL),
(8, 2, 'How effectively does the teacher use teaching aids and technology?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 3, 'active', 1, '2025-08-10 15:14:16', NULL),
(9, 2, 'How well does the teacher provide feedback on assignments?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 4, 'active', 1, '2025-08-10 15:14:16', NULL),
(10, 2, 'How accessible is the teacher for questions and clarifications?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 5, 'active', 1, '2025-08-10 15:14:16', NULL),
(11, 5, 'How well does the colleague demonstrate subject matter expertise?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 1, 'active', 1, '2025-08-10 15:14:16', NULL),
(12, 5, 'How effectively does the colleague plan and organize lessons?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 2, 'active', 1, '2025-08-10 15:14:16', NULL),
(13, 5, 'How well does the colleague assess student learning?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 3, 'active', 1, '2025-08-10 15:14:16', NULL),
(14, 5, 'How committed is the colleague to professional development?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 4, 'active', 1, '2025-08-10 15:14:16', NULL),
(15, 9, 'How well does the teacher demonstrate leadership in the department?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 1, 'active', 1, '2025-08-10 15:14:16', NULL),
(16, 9, 'How effectively does the teacher take initiative in projects?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 2, 'active', 1, '2025-08-10 15:14:16', NULL),
(17, 9, 'How well does the teacher mentor other faculty members?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 3, 'active', 1, '2025-08-10 15:14:16', NULL),
(18, 9, 'How effectively does the teacher represent the department?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 4, 'active', 1, '2025-08-10 15:14:16', NULL),
(19, 1, 'How well does the teacher maintain classroom discipline?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 1, 'active', 1, '2025-08-12 07:34:33', NULL),
(20, 1, 'Does the teacher start and end classes on time?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 2, 'active', 1, '2025-08-12 07:34:33', NULL),
(21, 1, 'How organized is the teacher\'s classroom setup?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 3, 'active', 1, '2025-08-12 07:34:33', NULL),
(22, 1, 'Does the teacher handle disruptive behavior effectively?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 4, 'active', 1, '2025-08-12 07:34:33', NULL),
(23, 1, 'How well does the teacher manage classroom activities and transitions?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 5, 'active', 1, '2025-08-12 07:34:33', NULL),
(24, 1, 'Does the teacher create a safe and respectful learning environment?', 'yes_no', NULL, NULL, 1, 6, 'active', 1, '2025-08-12 07:34:33', NULL),
(25, 1, 'What suggestions do you have for improving classroom management?', 'text', NULL, NULL, 0, 7, 'active', 1, '2025-08-12 07:34:33', NULL),
(26, 2, 'How clear and understandable are the teacher\'s explanations?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 1, 'active', 1, '2025-08-12 07:34:33', NULL),
(27, 2, 'Does the teacher use effective teaching methods and strategies?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 2, 'active', 1, '2025-08-12 07:34:33', NULL),
(28, 2, 'How well does the teacher adapt teaching to different learning styles?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 3, 'active', 1, '2025-08-12 07:34:33', NULL),
(29, 2, 'Does the teacher provide clear learning objectives for each lesson?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 4, 'active', 1, '2025-08-12 07:34:33', NULL),
(30, 2, 'How effective are the teacher\'s examples and demonstrations?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 5, 'active', 1, '2025-08-12 07:34:33', NULL),
(31, 2, 'Does the teacher use technology and resources effectively?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 6, 'active', 1, '2025-08-12 07:34:33', NULL),
(32, 2, 'How well does the teacher assess student understanding during lessons?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 7, 'active', 1, '2025-08-12 07:34:33', NULL),
(33, 2, 'What teaching methods do you find most effective?', 'text', NULL, NULL, 0, 8, 'active', 1, '2025-08-12 07:34:33', NULL),
(34, 3, 'How well does the teacher demonstrate mastery of the subject matter?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 1, 'active', 1, '2025-08-12 07:34:33', NULL),
(35, 3, 'Does the teacher provide accurate and up-to-date information?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 2, 'active', 1, '2025-08-12 07:34:33', NULL),
(36, 3, 'How well does the teacher connect concepts to real-world applications?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 3, 'active', 1, '2025-08-12 07:34:33', NULL),
(37, 3, 'Does the teacher answer student questions accurately and thoroughly?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 4, 'active', 1, '2025-08-12 07:34:33', NULL),
(38, 3, 'How well does the teacher explain complex topics in simple terms?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 5, 'active', 1, '2025-08-12 07:34:33', NULL),
(39, 3, 'Does the teacher stay current with developments in their field?', 'yes_no', NULL, NULL, 1, 6, 'active', 1, '2025-08-12 07:34:33', NULL),
(40, 3, 'What topics would you like the teacher to explain better?', 'text', NULL, NULL, 0, 7, 'active', 1, '2025-08-12 07:34:33', NULL),
(41, 4, 'How clear and audible is the teacher\'s voice?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 1, 'active', 1, '2025-08-12 07:34:33', NULL),
(42, 4, 'Does the teacher speak at an appropriate pace for students to follow?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 2, 'active', 1, '2025-08-12 07:34:33', NULL),
(43, 4, 'How well does the teacher use body language and gestures?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 3, 'active', 1, '2025-08-12 07:34:33', NULL),
(44, 4, 'Does the teacher listen actively to student questions and concerns?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 4, 'active', 1, '2025-08-12 07:34:33', NULL),
(45, 4, 'How well does the teacher provide constructive feedback?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 5, 'active', 1, '2025-08-12 07:34:33', NULL),
(46, 4, 'Does the teacher encourage open communication in the classroom?', 'yes_no', NULL, NULL, 1, 6, 'active', 1, '2025-08-12 07:34:33', NULL),
(47, 4, 'How approachable is the teacher for questions outside of class?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 7, 'active', 1, '2025-08-12 07:34:33', NULL),
(48, 4, 'What communication improvements would you suggest?', 'text', NULL, NULL, 0, 8, 'active', 1, '2025-08-12 07:34:33', NULL),
(49, 5, 'How well does the teacher motivate students to participate?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 1, 'active', 1, '2025-08-12 07:34:33', NULL),
(50, 5, 'Does the teacher create interesting and engaging lessons?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 2, 'active', 1, '2025-08-12 07:34:33', NULL),
(51, 5, 'How well does the teacher encourage critical thinking and discussion?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 3, 'active', 1, '2025-08-12 07:34:33', NULL),
(52, 5, 'Does the teacher use interactive activities and group work effectively?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 4, 'active', 1, '2025-08-12 07:34:33', NULL),
(53, 5, 'How well does the teacher recognize and respond to student interests?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 5, 'active', 1, '2025-08-12 07:34:33', NULL),
(54, 5, 'Does the teacher provide opportunities for hands-on learning?', 'yes_no', NULL, NULL, 1, 6, 'active', 1, '2025-08-12 07:34:33', NULL),
(55, 5, 'How well does the teacher maintain student attention throughout the lesson?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 7, 'active', 1, '2025-08-12 07:34:33', NULL),
(56, 5, 'What activities do you find most engaging in this class?', 'text', NULL, NULL, 0, 8, 'active', 1, '2025-08-12 07:34:33', NULL),
(57, 6, 'How well does the colleague demonstrate expertise in their subject area?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 1, 'active', 1, '2025-08-12 07:34:33', NULL),
(58, 6, 'Does the colleague stay updated with current educational practices?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 2, 'active', 1, '2025-08-12 07:34:33', NULL),
(59, 6, 'How well does the colleague plan and organize their lessons?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 3, 'active', 1, '2025-08-12 07:34:33', NULL),
(60, 6, 'Does the colleague demonstrate strong pedagogical skills?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 4, 'active', 1, '2025-08-12 07:34:33', NULL),
(61, 6, 'How well does the colleague assess and evaluate student learning?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 5, 'active', 1, '2025-08-12 07:34:33', NULL),
(62, 6, 'Does the colleague maintain professional standards in their work?', 'yes_no', NULL, NULL, 1, 6, 'active', 1, '2025-08-12 07:34:33', NULL),
(63, 6, 'How well does the colleague handle classroom challenges?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 7, 'active', 1, '2025-08-12 07:34:33', NULL),
(64, 6, 'What areas of professional development would you recommend?', 'text', NULL, NULL, 0, 8, 'active', 1, '2025-08-12 07:34:33', NULL),
(65, 7, 'How well does the colleague work with other teachers?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 1, 'active', 1, '2025-08-12 07:34:33', NULL),
(66, 7, 'Does the colleague share resources and ideas with the team?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 2, 'active', 1, '2025-08-12 07:34:33', NULL),
(67, 7, 'How well does the colleague participate in team meetings and discussions?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 3, 'active', 1, '2025-08-12 07:34:33', NULL),
(68, 7, 'Does the colleague support and help other teachers when needed?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 4, 'active', 1, '2025-08-12 07:34:33', NULL),
(69, 7, 'How well does the colleague contribute to school-wide initiatives?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 5, 'active', 1, '2025-08-12 07:34:33', NULL),
(70, 7, 'Does the colleague respect different viewpoints and approaches?', 'yes_no', NULL, NULL, 1, 6, 'active', 1, '2025-08-12 07:34:33', NULL),
(71, 7, 'How well does the colleague communicate with other staff members?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 7, 'active', 1, '2025-08-12 07:34:33', NULL),
(72, 7, 'What suggestions do you have for improving collaboration?', 'text', NULL, NULL, 0, 8, 'active', 1, '2025-08-12 07:34:33', NULL),
(73, 8, 'How creative and innovative is the colleague in their teaching methods?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 1, 'active', 1, '2025-08-12 07:34:33', NULL),
(74, 8, 'Does the colleague try new approaches and technologies?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 2, 'active', 1, '2025-08-12 07:34:33', NULL),
(75, 8, 'How well does the colleague adapt to changing educational needs?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 3, 'active', 1, '2025-08-12 07:34:33', NULL),
(76, 8, 'Does the colleague suggest improvements to existing programs?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 4, 'active', 1, '2025-08-12 07:34:33', NULL),
(77, 8, 'How well does the colleague integrate new ideas into their teaching?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 5, 'active', 1, '2025-08-12 07:34:33', NULL),
(78, 8, 'Does the colleague experiment with different assessment methods?', 'yes_no', NULL, NULL, 1, 6, 'active', 1, '2025-08-12 07:34:33', NULL),
(79, 8, 'How well does the colleague inspire creativity in students?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 7, 'active', 1, '2025-08-12 07:34:33', NULL),
(80, 8, 'What innovative practices have you observed from this colleague?', 'text', NULL, NULL, 0, 8, 'active', 1, '2025-08-12 07:34:33', NULL),
(81, 9, 'How well does the colleague mentor new or less experienced teachers?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 1, 'active', 1, '2025-08-12 07:34:33', NULL),
(82, 9, 'Does the colleague provide constructive feedback to other teachers?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 2, 'active', 1, '2025-08-12 07:34:33', NULL),
(83, 9, 'How well does the colleague share their expertise and knowledge?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 3, 'active', 1, '2025-08-12 07:34:33', NULL),
(84, 9, 'Does the colleague serve as a positive role model for other teachers?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 4, 'active', 1, '2025-08-12 07:34:33', NULL),
(85, 9, 'How well does the colleague support professional development of others?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 5, 'active', 1, '2025-08-12 07:34:33', NULL),
(86, 9, 'Does the colleague create opportunities for peer learning?', 'yes_no', NULL, NULL, 1, 6, 'active', 1, '2025-08-12 07:34:33', NULL),
(87, 9, 'How well does the colleague guide others in improving their teaching?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 7, 'active', 1, '2025-08-12 07:34:33', NULL),
(88, 9, 'What mentoring strengths does this colleague demonstrate?', 'text', NULL, NULL, 0, 8, 'active', 1, '2025-08-12 07:34:33', NULL),
(89, 10, 'How well does the teacher demonstrate leadership qualities?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 1, 'active', 1, '2025-08-12 07:34:33', NULL),
(90, 10, 'Does the teacher take initiative in school improvement projects?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 2, 'active', 1, '2025-08-12 07:34:33', NULL),
(91, 10, 'How well does the teacher inspire and motivate other staff members?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 3, 'active', 1, '2025-08-12 07:34:33', NULL),
(92, 10, 'Does the teacher demonstrate vision and strategic thinking?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 4, 'active', 1, '2025-08-12 07:34:33', NULL),
(93, 10, 'How well does the teacher handle conflicts and difficult situations?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 5, 'active', 1, '2025-08-12 07:34:33', NULL),
(94, 10, 'Does the teacher lead by example in professional conduct?', 'yes_no', NULL, NULL, 1, 6, 'active', 1, '2025-08-12 07:34:33', NULL),
(95, 10, 'How well does the teacher represent the school in external activities?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 7, 'active', 1, '2025-08-12 07:34:33', NULL),
(96, 10, 'What leadership opportunities would you recommend for this teacher?', 'text', NULL, NULL, 0, 8, 'active', 1, '2025-08-12 07:34:33', NULL),
(97, 11, 'How well does the teacher manage administrative tasks and paperwork?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 1, 'active', 1, '2025-08-12 07:34:33', NULL),
(98, 11, 'Does the teacher submit reports and documents on time?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 2, 'active', 1, '2025-08-12 07:34:33', NULL),
(99, 11, 'How well does the teacher organize and maintain records?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 3, 'active', 1, '2025-08-12 07:34:33', NULL),
(100, 11, 'Does the teacher follow administrative procedures correctly?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 4, 'active', 1, '2025-08-12 07:34:33', NULL),
(101, 11, 'How well does the teacher manage time and prioritize tasks?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 5, 'active', 1, '2025-08-12 07:34:33', NULL),
(102, 11, 'Does the teacher coordinate effectively with other departments?', 'yes_no', NULL, NULL, 1, 6, 'active', 1, '2025-08-12 07:34:33', NULL),
(103, 11, 'How well does the teacher handle budget and resource management?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 7, 'active', 1, '2025-08-12 07:34:33', NULL),
(104, 11, 'What administrative improvements would you suggest?', 'text', NULL, NULL, 0, 8, 'active', 1, '2025-08-12 07:34:33', NULL),
(105, 12, 'How committed is the teacher to continuous professional learning?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 1, 'active', 1, '2025-08-12 07:34:33', NULL),
(106, 12, 'Does the teacher actively participate in training and workshops?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 2, 'active', 1, '2025-08-12 07:34:33', NULL),
(107, 12, 'How well does the teacher apply new learning to their practice?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 3, 'active', 1, '2025-08-12 07:34:33', NULL),
(108, 12, 'Does the teacher seek feedback and reflect on their teaching?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 4, 'active', 1, '2025-08-12 07:34:33', NULL),
(109, 12, 'How well does the teacher stay current with educational trends?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 5, 'active', 1, '2025-08-12 07:34:33', NULL),
(110, 12, 'Does the teacher pursue advanced degrees or certifications?', 'yes_no', NULL, NULL, 1, 6, 'active', 1, '2025-08-12 07:34:33', NULL),
(111, 12, 'How well does the teacher share new knowledge with colleagues?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 7, 'active', 1, '2025-08-12 07:34:33', NULL),
(112, 12, 'What professional development goals would you recommend?', 'text', NULL, NULL, 0, 8, 'active', 1, '2025-08-12 07:34:33', NULL),
(113, 13, 'How well does the teacher follow school policies and procedures?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 1, 'active', 1, '2025-08-12 07:34:33', NULL),
(114, 13, 'Does the teacher comply with curriculum standards and requirements?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 2, 'active', 1, '2025-08-12 07:34:33', NULL),
(115, 13, 'How well does the teacher adhere to safety and security protocols?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 3, 'active', 1, '2025-08-12 07:34:33', NULL),
(116, 13, 'Does the teacher follow ethical guidelines and professional standards?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 4, 'active', 1, '2025-08-12 07:34:33', NULL),
(117, 13, 'How well does the teacher maintain confidentiality when required?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 5, 'active', 1, '2025-08-12 07:34:33', NULL),
(118, 13, 'Does the teacher attend required meetings and events?', 'yes_no', NULL, NULL, 1, 6, 'active', 1, '2025-08-12 07:34:33', NULL),
(119, 13, 'How well does the teacher follow assessment and grading policies?', 'rating_1_5', '[\"1 - Poor\", \"2 - Good\", \"3 - Satisfactory\", \"4 - Very Satisfactory\", \"5 - Excellent\"]', NULL, 1, 7, 'active', 1, '2025-08-12 07:34:33', NULL),
(120, 13, 'What compliance issues, if any, need to be addressed?', 'text', NULL, NULL, 0, 8, 'active', 1, '2025-08-12 07:34:33', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `evaluation_responses`
--

CREATE TABLE `evaluation_responses` (
  `id` int(11) NOT NULL,
  `evaluation_session_id` int(11) NOT NULL,
  `questionnaire_id` int(11) NOT NULL,
  `rating_value` int(11) DEFAULT NULL COMMENT '1-5 rating value',
  `text_response` text DEFAULT NULL,
  `multiple_choice_response` varchar(255) DEFAULT NULL,
  `yes_no_response` enum('yes','no') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `evaluation_responses`
--

INSERT INTO `evaluation_responses` (`id`, `evaluation_session_id`, `questionnaire_id`, `rating_value`, `text_response`, `multiple_choice_response`, `yes_no_response`, `created_at`) VALUES
(92, 329, 1, 1, NULL, NULL, NULL, '2025-08-14 16:23:28'),
(93, 329, 19, 2, NULL, NULL, NULL, '2025-08-14 16:23:28'),
(94, 329, 2, 1, NULL, NULL, NULL, '2025-08-14 16:23:28'),
(95, 329, 20, 1, NULL, NULL, NULL, '2025-08-14 16:23:28'),
(96, 329, 121, NULL, 'The teacher doesn&#039;t know how to teach the student, He&#039;s so annoying!', NULL, NULL, '2025-08-14 16:23:28'),
(97, 329, 122, NULL, 'He let the students disrupt the class. I hate him!', NULL, NULL, '2025-08-14 16:23:28'),
(98, 329, 6, 2, NULL, NULL, NULL, '2025-08-14 16:23:28'),
(99, 329, 26, 3, NULL, NULL, NULL, '2025-08-14 16:23:28'),
(100, 329, 7, 3, NULL, NULL, NULL, '2025-08-14 16:23:28'),
(101, 329, 27, 2, NULL, NULL, NULL, '2025-08-14 16:23:28'),
(102, 329, 123, NULL, 'The assessment.', NULL, NULL, '2025-08-14 16:23:28'),
(103, 329, 124, NULL, 'I don&#039;t know, he needs to undergo training', NULL, NULL, '2025-08-14 16:23:28'),
(104, 329, 34, 2, NULL, NULL, NULL, '2025-08-14 16:23:28'),
(105, 329, 35, 3, NULL, NULL, NULL, '2025-08-14 16:23:28'),
(106, 329, 36, 4, NULL, NULL, NULL, '2025-08-14 16:23:28'),
(107, 329, 37, 4, NULL, NULL, NULL, '2025-08-14 16:23:28'),
(108, 329, 125, NULL, 'Nakakadismaya lang kasi di siya nakakaintindi.', NULL, NULL, '2025-08-14 16:23:28'),
(109, 329, 126, NULL, 'He&#039;s so good in the subject.', NULL, NULL, '2025-08-14 16:23:28'),
(110, 329, 41, 3, NULL, NULL, NULL, '2025-08-14 16:23:28'),
(111, 329, 42, 3, NULL, NULL, NULL, '2025-08-14 16:23:28'),
(112, 329, 43, 2, NULL, NULL, NULL, '2025-08-14 16:23:28'),
(113, 329, 44, 3, NULL, NULL, NULL, '2025-08-14 16:23:28'),
(114, 329, 127, NULL, 'None so far.', NULL, NULL, '2025-08-14 16:23:28'),
(115, 329, 128, NULL, 'He respond very well. He answers the question aligned to the topic. Looking Great!', NULL, NULL, '2025-08-14 16:23:28'),
(116, 329, 11, 2, NULL, NULL, NULL, '2025-08-14 16:23:28'),
(117, 329, 49, 3, NULL, NULL, NULL, '2025-08-14 16:23:28'),
(118, 329, 12, 2, NULL, NULL, NULL, '2025-08-14 16:23:28'),
(119, 329, 50, 3, NULL, NULL, NULL, '2025-08-14 16:23:28'),
(120, 329, 129, NULL, 'Sometimes if the topic is hard to understand.', NULL, NULL, '2025-08-14 16:23:28'),
(121, 329, 130, NULL, 'He gave class motivation by showing real world example.', NULL, NULL, '2025-08-14 16:23:28'),
(122, 334, 1, 2, NULL, NULL, NULL, '2025-08-14 16:36:30'),
(123, 334, 19, 3, NULL, NULL, NULL, '2025-08-14 16:36:30'),
(124, 334, 2, 4, NULL, NULL, NULL, '2025-08-14 16:36:30'),
(125, 334, 20, 3, NULL, NULL, NULL, '2025-08-14 16:36:30'),
(126, 334, 121, NULL, 'Students frequently interrupt lessons, making it difficult for others to focus.', NULL, NULL, '2025-08-14 16:36:30'),
(127, 334, 122, NULL, 'The teacher calmly addresses the behavior, reminds the student of classroom expectations, and may move them to a different seat if the disruption continues.', NULL, NULL, '2025-08-14 16:36:30'),
(128, 334, 6, 2, NULL, NULL, NULL, '2025-08-14 16:36:30'),
(129, 334, 26, 3, NULL, NULL, NULL, '2025-08-14 16:36:30'),
(130, 334, 7, 4, NULL, NULL, NULL, '2025-08-14 16:36:30'),
(131, 334, 27, 2, NULL, NULL, NULL, '2025-08-14 16:36:30'),
(132, 334, 123, NULL, 'When teachers just read from the textbook without any interaction, it feels boring and disengaging.', NULL, NULL, '2025-08-14 16:36:30'),
(133, 334, 124, NULL, 'The teacher could use more visuals, real-life examples, and interactive activities to make lessons more engaging and easier to understand.', NULL, NULL, '2025-08-14 16:36:30'),
(134, 334, 34, 2, NULL, NULL, NULL, '2025-08-14 16:36:30'),
(135, 334, 35, 3, NULL, NULL, NULL, '2025-08-14 16:36:30'),
(136, 334, 36, 3, NULL, NULL, NULL, '2025-08-14 16:36:30'),
(137, 334, 37, 3, NULL, NULL, NULL, '2025-08-14 16:36:30'),
(138, 334, 125, NULL, 'The teacher often struggles to explain complex math concepts like algebra, which leaves many students confused.', NULL, NULL, '2025-08-14 16:36:30'),
(139, 334, 126, NULL, 'Sometimes the teacher mispronounces scientific terms or gives outdated information, which can be a bit concerning.', NULL, NULL, '2025-08-14 16:36:30'),
(140, 334, 41, 1, NULL, NULL, NULL, '2025-08-14 16:36:30'),
(141, 334, 42, 2, NULL, NULL, NULL, '2025-08-14 16:36:30'),
(142, 334, 43, 2, NULL, NULL, NULL, '2025-08-14 16:36:30'),
(143, 334, 44, 4, NULL, NULL, NULL, '2025-08-14 16:36:30'),
(144, 334, 127, NULL, 'The teacher sometimes dismisses questions too quickly or doesn&#039;t give clear answers, which makes it hard to follow the material.', NULL, NULL, '2025-08-14 16:36:30'),
(145, 334, 128, NULL, 'The teacher usually answers questions patiently and encourages students to think critically, which makes the class feel supportive.', NULL, NULL, '2025-08-14 16:36:30'),
(146, 334, 11, 2, NULL, NULL, NULL, '2025-08-14 16:36:30'),
(147, 334, 49, 4, NULL, NULL, NULL, '2025-08-14 16:36:30'),
(148, 334, 12, 4, NULL, NULL, NULL, '2025-08-14 16:36:30'),
(149, 334, 50, 3, NULL, NULL, NULL, '2025-08-14 16:36:30'),
(150, 334, 129, NULL, 'Long lectures without any group activities or discussions make the class feel dull and hard to stay focused on. (', NULL, NULL, '2025-08-14 16:36:30'),
(151, 334, 130, NULL, 'The teacher could offer more praise and rewards for effort, and connect lessons to students’ personal interests to spark their enthusiasm.', NULL, NULL, '2025-08-14 16:36:30'),
(152, 330, 131, 5, NULL, NULL, NULL, '2025-08-14 17:20:07'),
(153, 330, 132, 4, NULL, NULL, NULL, '2025-08-14 17:20:07'),
(154, 330, 133, 3, NULL, NULL, NULL, '2025-08-14 17:20:07'),
(155, 330, 134, 4, NULL, NULL, NULL, '2025-08-14 17:20:07'),
(156, 330, 135, 4, NULL, NULL, NULL, '2025-08-14 17:20:07'),
(157, 330, 151, NULL, 'I think they would benefit from training in classroom management and using technology effectively to engage students better.', NULL, NULL, '2025-08-14 17:23:57'),
(158, 330, 136, 5, NULL, NULL, NULL, '2025-08-14 17:24:06'),
(159, 330, 137, 5, NULL, NULL, NULL, '2025-08-14 17:24:06'),
(160, 330, 138, 4, NULL, NULL, NULL, '2025-08-14 17:24:06'),
(161, 330, 139, 5, NULL, NULL, NULL, '2025-08-14 17:24:06'),
(162, 330, 140, 4, NULL, NULL, NULL, '2025-08-14 17:24:06'),
(163, 330, 152, NULL, 'They could improve by actively listening to others\' ideas and sharing their own feedback more openly during meetings.', NULL, NULL, '2025-08-14 17:24:25'),
(164, 330, 141, 5, NULL, NULL, NULL, '2025-08-14 17:24:51'),
(165, 330, 142, 5, NULL, NULL, NULL, '2025-08-14 17:24:51'),
(166, 330, 143, 5, NULL, NULL, NULL, '2025-08-14 17:24:51'),
(167, 330, 144, 3, NULL, NULL, NULL, '2025-08-14 17:24:51'),
(168, 330, 145, 4, NULL, NULL, NULL, '2025-08-14 17:24:51'),
(169, 330, 153, NULL, 'They’ve started incorporating game-based learning and interactive quizzes, which really energize the students and make lessons fun.', NULL, NULL, '2025-08-14 17:24:51'),
(170, 330, 146, 4, NULL, NULL, NULL, '2025-08-14 17:24:59'),
(171, 330, 147, 4, NULL, NULL, NULL, '2025-08-14 17:24:59'),
(172, 330, 148, 5, NULL, NULL, NULL, '2025-08-14 17:24:59'),
(173, 330, 149, 3, NULL, NULL, NULL, '2025-08-14 17:24:59'),
(174, 330, 150, 5, NULL, NULL, NULL, '2025-08-14 17:24:59'),
(175, 330, 154, NULL, 'They are very patient and approachable, always willing to listen and offer thoughtful advice to newer teachers.', NULL, NULL, '2025-08-14 17:25:20');

-- --------------------------------------------------------

--
-- Table structure for table `evaluation_schedules`
--

CREATE TABLE `evaluation_schedules` (
  `id` int(11) NOT NULL,
  `semester_id` int(11) NOT NULL,
  `evaluation_type` enum('student_to_teacher','teacher_to_teacher','head_to_teacher') NOT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `status` enum('scheduled','active','completed','cancelled') DEFAULT 'scheduled',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `evaluation_schedules`
--

INSERT INTO `evaluation_schedules` (`id`, `semester_id`, `evaluation_type`, `start_date`, `end_date`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 'student_to_teacher', '2025-08-14 17:37:00', '2025-08-21 17:37:00', 'active', 1, '2025-08-10 13:37:28', '2025-08-14 15:37:11'),
(2, 1, 'teacher_to_teacher', '2025-08-14 17:52:00', '2025-08-21 17:52:00', 'active', 1, '2025-08-10 13:37:28', '2025-08-14 15:53:06'),
(3, 1, 'head_to_teacher', '2024-12-05 08:00:00', '2024-12-15 17:00:00', 'scheduled', 1, '2025-08-10 13:37:28', NULL),
(4, 1, 'student_to_teacher', '2024-11-15 08:00:00', '2024-11-30 17:00:00', 'completed', 1, '2025-08-10 13:40:41', '2025-08-12 06:39:28'),
(5, 1, 'teacher_to_teacher', '2024-12-01 08:00:00', '2024-12-10 17:00:00', 'scheduled', 1, '2025-08-10 13:40:41', NULL),
(6, 1, 'head_to_teacher', '2024-12-05 08:00:00', '2024-12-15 17:00:00', 'scheduled', 1, '2025-08-10 13:40:41', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `evaluation_sessions`
--

CREATE TABLE `evaluation_sessions` (
  `id` int(11) NOT NULL,
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
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `evaluation_sessions`
--

INSERT INTO `evaluation_sessions` (`id`, `evaluator_id`, `evaluator_type`, `evaluatee_id`, `evaluatee_type`, `main_category_id`, `semester_id`, `subject_id`, `evaluation_date`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(328, 1, 'student', 2, 'teacher', 1, 1, NULL, '2025-08-14', 'draft', '', '2025-08-14 15:37:11', NULL),
(329, 4, 'student', 2, 'teacher', 1, 1, NULL, '2025-08-14', 'completed', '', '2025-08-14 15:37:11', '2025-08-14 16:23:28'),
(330, 9, 'teacher', 2, 'teacher', 5, 1, NULL, '2025-08-14', 'completed', '', '2025-08-14 15:53:06', '2025-08-14 17:25:20'),
(331, 7, 'teacher', 3, 'teacher', 5, 1, NULL, '2025-08-14', 'draft', '', '2025-08-14 15:53:06', NULL),
(332, 3, 'teacher', 7, 'teacher', 5, 1, NULL, '2025-08-14', 'draft', '', '2025-08-14 15:53:06', NULL),
(333, 2, 'teacher', 9, 'teacher', 5, 1, NULL, '2025-08-14', 'draft', '', '2025-08-14 15:53:06', NULL),
(334, 2, 'student', 2, 'teacher', 1, NULL, NULL, '2025-08-15', 'completed', NULL, '2025-08-14 16:30:53', '2025-08-14 16:36:30');

-- --------------------------------------------------------

--
-- Table structure for table `evaluation_sub_categories`
--

CREATE TABLE `evaluation_sub_categories` (
  `id` int(11) NOT NULL,
  `main_category_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `order_number` int(11) DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `evaluation_sub_categories`
--

INSERT INTO `evaluation_sub_categories` (`id`, `main_category_id`, `name`, `description`, `status`, `order_number`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 'Classroom Management', 'Evaluation of teacher\'s ability to maintain order and create a conducive learning environment', 'active', 1, 1, '2025-08-10 15:14:16', NULL),
(2, 1, 'Teaching Skills', 'Assessment of teacher\'s instructional methods and delivery', 'active', 2, 1, '2025-08-10 15:14:16', NULL),
(3, 1, 'Subject Knowledge', 'Evaluation of teacher\'s mastery of the subject matter', 'active', 3, 1, '2025-08-10 15:14:16', NULL),
(4, 1, 'Communication Skills', 'Assessment of teacher\'s ability to communicate effectively with students', 'active', 4, 1, '2025-08-10 15:14:16', NULL),
(5, 1, 'Student Engagement', 'Evaluation of how well the teacher engages students in learning', 'active', 5, 1, '2025-08-10 15:14:16', NULL),
(10, 3, 'Leadership', 'Assessment of leadership qualities and initiative', 'active', 1, 1, '2025-08-10 15:14:16', NULL),
(11, 3, 'Administrative Skills', 'Evaluation of administrative and organizational skills', 'active', 2, 1, '2025-08-10 15:14:16', NULL),
(12, 3, 'Professional Development', 'Assessment of continuous learning and growth', 'active', 3, 1, '2025-08-10 15:14:16', NULL),
(13, 3, 'Compliance', 'Evaluation of adherence to policies and procedures', 'active', 4, 1, '2025-08-10 15:14:16', NULL),
(18, 5, 'Professional Competence', 'Evaluation of colleague\'s professional skills and knowledge', 'active', 1, 1, '2025-08-14 10:47:27', NULL),
(19, 5, 'Collaboration', 'Assessment of teamwork and cooperation with colleagues', 'active', 2, 1, '2025-08-14 10:47:27', NULL),
(20, 5, 'Innovation', 'Evaluation of teaching innovations and creativity', 'active', 3, 1, '2025-08-14 10:47:27', NULL),
(21, 5, 'Mentoring', 'Assessment of ability to mentor and support other teachers', 'active', 4, 1, '2025-08-14 10:47:27', NULL);

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
  `email` varchar(100) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `position` varchar(100) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `faculty`
--

INSERT INTO `faculty` (`id`, `first_name`, `last_name`, `email`, `password`, `position`, `department`, `bio`, `image_url`, `is_active`, `created_at`) VALUES
(1, 'Dr. Maria', 'Santos', 'msantos@seait.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dean', 'College of Engineering', 'Expert in computer engineering with 15 years of experience', NULL, 1, '2025-08-05 10:14:11'),
(2, 'Michael Paul', 'Sebando', 'msebando@seait.edu.ph', '$2y$10$KM8tKMYTqJ3De938qIT21OHM0oI7xCgcU1cqZmVDMwdh4aiqGoula', 'Professor', 'College of Information and Communication Technology', 'Specialist in software engineering and web development', '', 1, '2025-08-05 10:14:11'),
(3, 'Dr. Ana', 'Reyes', 'areyes@seait.edu.ph', NULL, 'Associate Professor', 'College of Business', 'Expert in business management and entrepreneurship', NULL, 1, '2025-08-05 10:14:11'),
(4, 'Dr. Roberto', 'Garcia', 'rgarcia@seait.edu.ph', NULL, 'Dean', 'College of Information Technology', 'Expert in software engineering and artificial intelligence with 20 years of experience', NULL, 1, '2025-08-05 10:17:11'),
(5, 'Prof. Maria', 'Lopez', 'mlopez@seait.edu.ph', NULL, 'Professor', 'Department of Computer Science', 'Specialist in data science and machine learning applications', NULL, 1, '2025-08-05 10:17:11'),
(6, 'Dr. Carlos', 'Martinez', 'cmartinez@seait.edu.ph', NULL, 'Associate Professor', 'Department of Electronics Engineering', 'Expert in embedded systems and IoT technologies', NULL, 1, '2025-08-05 10:17:11'),
(7, 'Prof. Ana', 'Gonzalez', 'agonzalez@seait.edu.ph', NULL, 'Assistant Professor', 'College of Business', 'Specialist in digital marketing and e-commerce', NULL, 1, '2025-08-05 10:17:11'),
(8, 'Dr. Jose', 'Rodriguez', 'jrodriguez@seait.edu.ph', NULL, 'Professor', 'Department of Information Systems', 'Expert in enterprise systems and business intelligence', NULL, 1, '2025-08-05 10:17:11'),
(9, 'Mary Joy', 'Fernandez', 'mjfernandez@seait.edu.ph', '$2y$10$R24anZMA596YhGqULkFnd.5i3Rc8d4KP/G6FNNEsYgh53XRjw6kEK', 'Program Head', 'College of Information and Communication Technology', '', 'uploads/faculty/6898b0db649c3.jpg', 1, '2025-08-10 14:46:51');

-- --------------------------------------------------------

--
-- Table structure for table `faculty_events`
--

CREATE TABLE `faculty_events` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `event_date` date NOT NULL,
  `event_type` enum('class','exam','assignment','meeting','other') DEFAULT 'other',
  `class_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `faculty_events`
--

INSERT INTO `faculty_events` (`id`, `teacher_id`, `title`, `description`, `event_date`, `event_type`, `class_id`, `created_at`, `updated_at`) VALUES
(1, 1, 'First Class Meeting', 'Introduction to the course and syllabus review', '2025-08-11', 'class', NULL, '2025-08-10 23:45:43', NULL),
(2, 1, 'Midterm Exam', 'Comprehensive exam covering chapters 1-5', '2025-08-18', 'exam', NULL, '2025-08-10 23:45:43', NULL),
(3, 1, 'Assignment Submission', 'Final project submission deadline', '2025-08-25', 'assignment', NULL, '2025-08-10 23:45:43', NULL),
(4, 1, 'Department Meeting', 'Monthly faculty meeting to discuss curriculum updates', '2025-08-14', 'meeting', NULL, '2025-08-10 23:45:43', NULL),
(5, 1, 'First Class Meeting', 'Introduction to the course and syllabus review', '2025-08-11', 'class', NULL, '2025-08-10 23:46:46', NULL),
(6, 1, 'Midterm Exam', 'Comprehensive exam covering chapters 1-5', '2025-08-18', 'exam', NULL, '2025-08-10 23:46:46', NULL),
(7, 1, 'Assignment Submission', 'Final project submission deadline', '2025-08-25', 'assignment', NULL, '2025-08-10 23:46:46', NULL),
(8, 1, 'Department Meeting', 'Monthly faculty meeting to discuss curriculum updates', '2025-08-14', 'meeting', NULL, '2025-08-10 23:46:46', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `faculty_notifications`
--

CREATE TABLE `faculty_notifications` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','error','announcement','evaluation','class') DEFAULT 'info',
  `related_id` int(11) DEFAULT NULL,
  `related_type` varchar(50) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `faculty_notifications`
--

INSERT INTO `faculty_notifications` (`id`, `teacher_id`, `title`, `message`, `type`, `related_id`, `related_type`, `is_read`, `read_at`, `created_at`) VALUES
(1, 1, 'New Student Enrollment', 'A new student has enrolled in your class \"Introduction to Programming\".', 'info', 1, 'class', 0, NULL, '2025-08-10 23:45:44'),
(2, 1, 'Evaluation Completed', 'Your peer evaluation for Dr. Smith has been completed successfully.', 'success', 1, 'evaluation', 0, NULL, '2025-08-10 23:45:44'),
(3, 1, 'Class Reminder', 'You have a class scheduled in 30 minutes.', 'warning', 1, 'class', 0, NULL, '2025-08-10 23:45:44'),
(4, 1, 'New Student Enrollment', 'A new student has enrolled in your class \"Introduction to Programming\".', 'info', 1, 'class', 0, NULL, '2025-08-10 23:46:46'),
(5, 1, 'Evaluation Completed', 'Your peer evaluation for Dr. Smith has been completed successfully.', 'success', 1, 'evaluation', 0, NULL, '2025-08-10 23:46:46'),
(6, 1, 'Class Reminder', 'You have a class scheduled in 30 minutes.', 'warning', 1, 'class', 0, NULL, '2025-08-10 23:46:46');

-- --------------------------------------------------------

--
-- Table structure for table `faqs`
--

CREATE TABLE `faqs` (
  `id` int(11) NOT NULL,
  `question` text NOT NULL,
  `answer` text NOT NULL,
  `keywords` text DEFAULT NULL,
  `category` varchar(100) DEFAULT 'general',
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `faqs`
--

INSERT INTO `faqs` (`id`, `question`, `answer`, `keywords`, `category`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'How do I apply for admission?', 'You can apply for admission by visiting our Admission Process section on the website, or contact our admission office directly. We offer various programs including undergraduate and graduate degrees. You can also start your application through our pre-registration form.', 'admission, apply, enroll, application', 'admission', 1, 1, '2025-08-07 15:16:04', '2025-08-07 15:16:04'),
(2, 'What programs does SEAIT offer?', 'SEAIT offers various academic programs across different colleges. You can explore our Academic Programs section to see all available courses. Each program has detailed information about curriculum, requirements, and career opportunities.', 'program, course, degree, curriculum', 'academics', 1, 2, '2025-08-07 15:16:04', '2025-08-07 15:16:04'),
(3, 'How can I contact SEAIT?', 'You can find our contact information in the Contact Us section. We have different departments with specific contact details. For general inquiries, you can reach us through the contact form or call our main office.', 'contact, phone, email, reach', 'contact', 1, 3, '2025-08-07 15:16:04', '2025-08-07 15:16:04'),
(4, 'Where is SEAIT located?', 'SEAIT is located in [City, Province]. You can find our exact address and directions in the Contact Us section. We also have virtual tours available for prospective students.', 'location, address, where, directions', 'location', 1, 4, '2025-08-07 15:16:04', '2025-08-07 15:16:04'),
(5, 'What are the tuition fees?', 'Tuition fees vary by program and level. For detailed information about fees and payment options, please contact our finance office or check our admission guide. We also offer scholarships and financial aid programs.', 'fee, tuition, cost, price, payment', 'fees', 1, 5, '2025-08-07 15:16:04', '2025-08-07 15:16:04'),
(6, 'What are the class schedules?', 'Class schedules vary by program and semester. You can check our academic calendar for important dates. For specific class schedules, please contact your department or check the student portal.', 'schedule, time, when, class', 'schedule', 1, 6, '2025-08-07 15:16:04', '2025-08-07 15:16:04'),
(7, 'Do you offer scholarships?', 'Yes, we offer various scholarships and financial aid programs. Please contact our finance office for detailed information about available scholarships and eligibility requirements.', 'scholarship, financial aid, funding', 'fees', 1, 7, '2025-08-07 15:16:04', '2025-08-07 15:16:04'),
(8, 'What are the admission requirements?', 'Admission requirements vary by program. You can find detailed requirements in our Admission Process section. Generally, we require academic transcripts, recommendation letters, and entrance exam results.', 'requirements, admission, documents, transcripts', 'admission', 1, 8, '2025-08-07 15:16:04', '2025-08-07 15:16:04'),
(9, 'How long does the application process take?', 'The application process typically takes 2-4 weeks for complete applications. We recommend applying early to ensure all documents are processed on time.', 'application, process, time, duration', 'admission', 1, 9, '2025-08-07 15:16:04', '2025-08-07 15:16:04'),
(10, 'Can I transfer credits from another school?', 'Yes, we accept transfer credits from accredited institutions. Please contact our registrar office with your transcripts for evaluation.', 'transfer, credits, previous school', 'admission', 1, 10, '2025-08-07 15:16:04', '2025-08-07 15:16:04');

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
-- Table structure for table `head_teacher_assignments`
--

CREATE TABLE `head_teacher_assignments` (
  `id` int(11) NOT NULL,
  `head_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `assigned_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `head_teacher_assignments`
--

INSERT INTO `head_teacher_assignments` (`id`, `head_id`, `teacher_id`, `assigned_date`, `status`, `created_at`, `updated_at`) VALUES
(1, 11, 1, '2025-08-13 13:59:33', 'active', '2025-08-13 13:59:33', NULL),
(2, 11, 2, '2025-08-13 13:59:33', 'active', '2025-08-13 13:59:33', NULL),
(3, 7, 3, '2025-08-13 13:59:33', 'active', '2025-08-13 13:59:33', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `lessons`
--

CREATE TABLE `lessons` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `content` longtext DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_type` varchar(255) DEFAULT NULL COMMENT 'MIME type of the file',
  `file_size` int(11) DEFAULT NULL,
  `lesson_type` enum('text','video','document','presentation','link') DEFAULT 'text',
  `status` enum('draft','published','archived') DEFAULT 'draft',
  `order_number` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lessons`
--

INSERT INTO `lessons` (`id`, `teacher_id`, `title`, `description`, `content`, `file_path`, `file_name`, `file_type`, `file_size`, `lesson_type`, `status`, `order_number`, `created_at`, `updated_at`) VALUES
(2, 2, 'Introduction to Application Development', 'Overview of modern application development concepts, tools, and methodologies', '<h2>Lesson 1: Introduction to Application Development</h2><h3>Learning Objectives</h3><p>By the end of this lesson, students will be able to:</p><ul><li>Understand the fundamentals of application development</li><li>Identify different types of applications and their purposes</li><li>Recognize modern development methodologies</li><li>Explore emerging technologies in software development</li></ul><h3>Key Concepts</h3><h4>1. What is Application Development?</h4><p>Application development is the process of creating software applications that run on various platforms including:</p><ul><li>Web applications</li><li>Mobile applications</li><li>Desktop applications</li><li>Cloud-based applications</li></ul><h4>2. Types of Applications</h4><ul><li><strong>Web Applications</strong>: Browser-based applications (e.g., Gmail, Facebook)</li><li><strong>Mobile Applications</strong>: Native or hybrid apps for smartphones and tablets</li><li><strong>Desktop Applications</strong>: Software installed on personal computers</li><li><strong>Enterprise Applications</strong>: Business software for organizations</li></ul><h4>3. Modern Development Methodologies</h4><ul><li><strong>Agile Development</strong>: Iterative approach with frequent feedback</li><li><strong>DevOps</strong>: Integration of development and operations</li><li><strong>Continuous Integration/Continuous Deployment (CI/CD)</strong>: Automated testing and deployment</li><li><strong>Microservices Architecture</strong>: Breaking applications into smaller, independent services</li></ul><h4>4. Emerging Technologies</h4><ul><li><strong>Artificial Intelligence and Machine Learning</strong>: AI-powered features and automation</li><li><strong>Internet of Things (IoT)</strong>: Connected devices and sensors</li><li><strong>Blockchain Technology</strong>: Decentralized applications and smart contracts</li><li><strong>Augmented Reality (AR) and Virtual Reality (VR)</strong>: Immersive user experiences</li><li><strong>Cloud Computing</strong>: Scalable infrastructure and services</li></ul><h3>Development Tools and Technologies</h3><h4>Programming Languages</h4><ul><li><strong>JavaScript/TypeScript</strong>: For web and mobile development</li><li><strong>Python</strong>: For AI/ML and backend development</li><li><strong>Java/Kotlin</strong>: For Android development</li><li><strong>Swift</strong>: For iOS development</li><li><strong>C#</strong>: For Windows applications</li></ul><h4>Frameworks and Libraries</h4><ul><li><strong>React/Vue/Angular</strong>: Frontend frameworks</li><li><strong>Node.js/Django/Flask</strong>: Backend frameworks</li><li><strong>React Native/Flutter</strong>: Cross-platform mobile development</li><li><strong>TensorFlow/PyTorch</strong>: Machine learning libraries</li></ul><h4>Development Tools</h4><ul><li><strong>Git</strong>: Version control</li><li><strong>Docker</strong>: Containerization</li><li><strong>VS Code/IntelliJ</strong>: Integrated development environments</li><li><strong>Postman</strong>: API testing</li><li><strong>Figma</strong>: UI/UX design</li></ul><h3>Best Practices in Application Development</h3><ol><li><strong>Code Quality</strong>: Write clean, readable, and maintainable code</li><li><strong>Security</strong>: Implement proper authentication, authorization, and data protection</li><li><strong>Performance</strong>: Optimize for speed and efficiency</li><li><strong>Scalability</strong>: Design applications to handle growth</li><li><strong>User Experience</strong>: Focus on intuitive and accessible interfaces</li><li><strong>Testing</strong>: Implement comprehensive testing strategies</li><li><strong>Documentation</strong>: Maintain clear and up-to-date documentation</li></ol><h3>Assignment</h3><p>Create a simple web application that demonstrates basic HTML, CSS, and JavaScript concepts. The application should include:</p><ul><li>A responsive design</li><li>Interactive elements</li><li>Form validation</li><li>Basic styling</li></ul><h3>Resources</h3><ul><li>MDN Web Docs: <a href=\"https://developer.mozilla.org/\">https://developer.mozilla.org/</a></li><li>W3Schools: <a href=\"https://www.w3schools.com/\">https://www.w3schools.com/</a></li><li>GitHub: <a href=\"https://github.com/\">https://github.com/</a></li><li>Stack Overflow: <a href=\"https://stackoverflow.com/\">https://stackoverflow.com/</a></li></ul><h3>Next Steps</h3><p>In the upcoming lessons, we will dive deeper into specific technologies and frameworks used in modern application development.</p>', NULL, NULL, NULL, NULL, 'text', 'published', 1, '2025-08-16 13:38:11', '2025-08-16 23:31:51'),
(3, 3, 'Introduction to Application Development', 'This lesson covers the fundamental concepts of application development, including different types of applications, development methodologies, and basic programming concepts.', '<h2>Introduction to Application Development</h2>\n\n<h3>What is Application Development?</h3>\n<p>Application development is the process of creating software applications that run on various platforms and devices. It involves designing, coding, testing, and maintaining software solutions to meet specific user needs.</p>\n\n<h3>Types of Applications</h3>\n<ul>\n<li><strong>Web Applications:</strong> Run in web browsers and are accessible from any device with internet connection</li>\n<li><strong>Mobile Applications:</strong> Designed specifically for smartphones and tablets</li>\n<li><strong>Desktop Applications:</strong> Run on personal computers and laptops</li>\n<li><strong>Enterprise Applications:</strong> Large-scale applications used by organizations</li>\n</ul>\n\n<h3>Development Methodologies</h3>\n<ul>\n<li><strong>Waterfall:</strong> Linear, sequential approach</li>\n<li><strong>Agile:</strong> Iterative and incremental development</li>\n<li><strong>Scrum:</strong> Framework within Agile methodology</li>\n<li><strong>DevOps:</strong> Integration of development and operations</li>\n</ul>\n\n<h3>Key Concepts</h3>\n<ul>\n<li>User Interface (UI) Design</li>\n<li>User Experience (UX) Design</li>\n<li>Database Management</li>\n<li>API Development</li>\n<li>Security Implementation</li>\n<li>Testing and Quality Assurance</li>\n</ul>', NULL, NULL, NULL, NULL, 'text', 'published', 1, '2025-08-17 13:28:50', NULL),
(4, 3, 'Introduction to Application Development', 'This lesson covers the fundamental concepts of application development, including different types of applications, development methodologies, and basic programming concepts.', '<h2>Introduction to Application Development</h2>\n\n<h3>What is Application Development?</h3>\n<p>Application development is the process of creating software applications that run on various platforms and devices. It involves designing, coding, testing, and maintaining software solutions to meet specific user needs.</p>\n\n<h3>Types of Applications</h3>\n<ul>\n<li><strong>Web Applications:</strong> Run in web browsers and are accessible from any device with internet connection</li>\n<li><strong>Mobile Applications:</strong> Designed specifically for smartphones and tablets</li>\n<li><strong>Desktop Applications:</strong> Run on personal computers and laptops</li>\n<li><strong>Enterprise Applications:</strong> Large-scale applications used by organizations</li>\n</ul>\n\n<h3>Development Methodologies</h3>\n<ul>\n<li><strong>Waterfall:</strong> Linear, sequential approach</li>\n<li><strong>Agile:</strong> Iterative and incremental development</li>\n<li><strong>Scrum:</strong> Framework within Agile methodology</li>\n<li><strong>DevOps:</strong> Integration of development and operations</li>\n</ul>\n\n<h3>Key Concepts</h3>\n<ul>\n<li>User Interface (UI) Design</li>\n<li>User Experience (UX) Design</li>\n<li>Database Management</li>\n<li>API Development</li>\n<li>Security Implementation</li>\n<li>Testing and Quality Assurance</li>\n</ul>', NULL, NULL, NULL, NULL, 'text', 'published', 1, '2025-08-17 13:29:40', NULL),
(5, 3, 'Introduction to Application Development', 'This lesson covers the fundamental concepts of application development, including different types of applications, development methodologies, and basic programming concepts.', '<h2>Introduction to Application Development</h2>\n\n<h3>What is Application Development?</h3>\n<p>Application development is the process of creating software applications that run on various platforms and devices. It involves designing, coding, testing, and maintaining software solutions to meet specific user needs.</p>\n\n<h3>Types of Applications</h3>\n<ul>\n<li><strong>Web Applications:</strong> Run in web browsers and are accessible from any device with internet connection</li>\n<li><strong>Mobile Applications:</strong> Designed specifically for smartphones and tablets</li>\n<li><strong>Desktop Applications:</strong> Run on personal computers and laptops</li>\n<li><strong>Enterprise Applications:</strong> Large-scale applications used by organizations</li>\n</ul>\n\n<h3>Development Methodologies</h3>\n<ul>\n<li><strong>Waterfall:</strong> Linear, sequential approach</li>\n<li><strong>Agile:</strong> Iterative and incremental development</li>\n<li><strong>Scrum:</strong> Framework within Agile methodology</li>\n<li><strong>DevOps:</strong> Integration of development and operations</li>\n</ul>\n\n<h3>Key Concepts</h3>\n<ul>\n<li>User Interface (UI) Design</li>\n<li>User Experience (UX) Design</li>\n<li>Database Management</li>\n<li>API Development</li>\n<li>Security Implementation</li>\n<li>Testing and Quality Assurance</li>\n</ul>', NULL, NULL, NULL, NULL, 'text', 'published', 1, '2025-08-17 13:30:40', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `lesson_class_assignments`
--

CREATE TABLE `lesson_class_assignments` (
  `id` int(11) NOT NULL,
  `lesson_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lesson_class_assignments`
--

INSERT INTO `lesson_class_assignments` (`id`, `lesson_id`, `class_id`, `created_at`) VALUES
(6, 2, 5, '2025-08-16 23:31:51'),
(7, 2, 6, '2025-08-16 23:31:51'),
(8, 3, 5, '2025-08-17 13:28:50'),
(9, 4, 5, '2025-08-17 13:29:40'),
(10, 5, 5, '2025-08-17 13:30:40');

-- --------------------------------------------------------

--
-- Table structure for table `lms_assignments`
--

CREATE TABLE `lms_assignments` (
  `id` int(11) NOT NULL,
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
  `max_file_size` int(11) DEFAULT 10485760,
  `allowed_file_types` varchar(255) DEFAULT NULL,
  `status` enum('draft','published','closed') DEFAULT 'draft',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
-- Table structure for table `lms_assignment_categories`
--

CREATE TABLE `lms_assignment_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `color` varchar(20) DEFAULT '#10B981',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lms_assignment_categories`
--

INSERT INTO `lms_assignment_categories` (`id`, `name`, `description`, `color`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Homework', 'Regular homework assignments', '#10B981', 'active', 1, '2025-08-10 18:29:26', NULL),
(2, 'Projects', 'Major projects and research', '#8B5CF6', 'active', 1, '2025-08-10 18:29:26', NULL),
(3, 'Quizzes', 'Short quizzes and tests', '#F59E0B', 'active', 1, '2025-08-10 18:29:26', NULL),
(4, 'Presentations', 'Oral presentations and reports', '#EF4444', 'active', 1, '2025-08-10 18:29:26', NULL),
(5, 'Participation', 'Class participation and engagement', '#6B7280', 'active', 1, '2025-08-10 18:29:26', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `lms_assignment_submissions`
--

CREATE TABLE `lms_assignment_submissions` (
  `id` int(11) NOT NULL,
  `assignment_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `submission_text` longtext DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `score` decimal(5,2) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `graded_by` int(11) DEFAULT NULL,
  `graded_at` timestamp NULL DEFAULT NULL,
  `status` enum('submitted','late','graded','returned') DEFAULT 'submitted'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lms_discussions`
--

CREATE TABLE `lms_discussions` (
  `id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `is_pinned` tinyint(1) DEFAULT 0,
  `is_locked` tinyint(1) DEFAULT 0,
  `allow_replies` tinyint(1) DEFAULT 1,
  `status` enum('active','inactive','archived') DEFAULT 'active',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
-- Table structure for table `lms_discussion_posts`
--

CREATE TABLE `lms_discussion_posts` (
  `id` int(11) NOT NULL,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lms_grade_categories`
--

CREATE TABLE `lms_grade_categories` (
  `id` int(11) NOT NULL,
  `class_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `weight` decimal(5,2) DEFAULT 0.00,
  `color` varchar(20) DEFAULT '#8B5CF6',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lms_grade_categories`
--

INSERT INTO `lms_grade_categories` (`id`, `class_id`, `name`, `description`, `weight`, `color`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, NULL, 'Assignments', 'Homework and projects', 30.00, '#8B5CF6', 'active', 1, '2025-08-10 18:29:26', NULL),
(2, NULL, 'Quizzes', 'Short quizzes and tests', 20.00, '#F59E0B', 'active', 1, '2025-08-10 18:29:26', NULL),
(3, NULL, 'Midterm Exam', 'Midterm examination', 25.00, '#EF4444', 'active', 1, '2025-08-10 18:29:26', NULL),
(4, NULL, 'Final Exam', 'Final examination', 25.00, '#EF4444', 'active', 1, '2025-08-10 18:29:26', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `lms_materials`
--

CREATE TABLE `lms_materials` (
  `id` int(11) NOT NULL,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
-- Table structure for table `lms_material_access_logs`
--

CREATE TABLE `lms_material_access_logs` (
  `id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `access_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lms_material_categories`
--

CREATE TABLE `lms_material_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT 'fas fa-folder',
  `color` varchar(20) DEFAULT '#3B82F6',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lms_material_categories`
--

INSERT INTO `lms_material_categories` (`id`, `name`, `description`, `icon`, `color`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Syllabus', 'Course syllabus and overview', 'fas fa-file-alt', '#3B82F6', 'active', 1, '2025-08-10 18:29:26', NULL),
(2, 'Lectures', 'Lecture notes and presentations', 'fas fa-chalkboard-teacher', '#10B981', 'active', 1, '2025-08-10 18:29:26', NULL),
(3, 'Readings', 'Required and optional readings', 'fas fa-book', '#F59E0B', 'active', 1, '2025-08-10 18:29:26', NULL),
(4, 'Videos', 'Video lectures and tutorials', 'fas fa-video', '#EF4444', 'active', 1, '2025-08-10 18:29:26', NULL),
(5, 'Assignments', 'Assignment instructions and resources', 'fas fa-tasks', '#8B5CF6', 'active', 1, '2025-08-10 18:29:26', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `lms_notifications`
--

CREATE TABLE `lms_notifications` (
  `id` int(11) NOT NULL,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lms_post_reactions`
--

CREATE TABLE `lms_post_reactions` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_type` enum('student','teacher') NOT NULL,
  `reaction_type` enum('like','love','helpful','insightful') DEFAULT 'like',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lms_resources`
--

CREATE TABLE `lms_resources` (
  `id` int(11) NOT NULL,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lms_resource_categories`
--

CREATE TABLE `lms_resource_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT 'fas fa-link',
  `color` varchar(20) DEFAULT '#F59E0B',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lms_resource_categories`
--

INSERT INTO `lms_resource_categories` (`id`, `name`, `description`, `icon`, `color`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Textbooks', 'Required and recommended textbooks', 'fas fa-book', '#F59E0B', 'active', 1, '2025-08-10 18:29:26', NULL),
(2, 'Online Tools', 'Useful online tools and software', 'fas fa-tools', '#10B981', 'active', 1, '2025-08-10 18:29:26', NULL),
(3, 'Research Databases', 'Academic databases and journals', 'fas fa-database', '#3B82F6', 'active', 1, '2025-08-10 18:29:26', NULL),
(4, 'Tutorials', 'Step-by-step tutorials and guides', 'fas fa-graduation-cap', '#8B5CF6', 'active', 1, '2025-08-10 18:29:26', NULL),
(5, 'External Links', 'Additional resources and references', 'fas fa-external-link-alt', '#6B7280', 'active', 1, '2025-08-10 18:29:26', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `lms_student_grades`
--

CREATE TABLE `lms_student_grades` (
  `id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `assignment_id` int(11) DEFAULT NULL,
  `grade_type` enum('assignment','quiz','exam','participation','other') NOT NULL,
  `title` varchar(255) NOT NULL,
  `score` decimal(5,2) NOT NULL,
  `max_score` decimal(5,2) NOT NULL,
  `percentage` decimal(5,2) GENERATED ALWAYS AS (`score` / `max_score` * 100) STORED,
  `letter_grade` varchar(5) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `graded_by` int(11) NOT NULL,
  `graded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('draft','published','final') DEFAULT 'draft'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
-- Table structure for table `lms_student_progress`
--

CREATE TABLE `lms_student_progress` (
  `id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `material_id` int(11) DEFAULT NULL,
  `assignment_id` int(11) DEFAULT NULL,
  `discussion_id` int(11) DEFAULT NULL,
  `activity_type` enum('material_view','assignment_submit','discussion_post','grade_received') NOT NULL,
  `progress_percentage` decimal(5,2) DEFAULT 0.00,
  `time_spent` int(11) DEFAULT 0,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `main_evaluation_categories`
--

CREATE TABLE `main_evaluation_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `evaluation_type` enum('student_to_teacher','peer_to_peer','head_to_teacher') NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `main_evaluation_categories`
--

INSERT INTO `main_evaluation_categories` (`id`, `name`, `description`, `evaluation_type`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Student to Teacher Evaluation', 'Students evaluate their teachers on various aspects of teaching and classroom management', 'student_to_teacher', 'active', 1, '2025-08-10 15:14:16', NULL),
(3, 'Head to Teacher Evaluation', 'Department heads and administrators evaluate teachers on leadership and administrative skills', 'head_to_teacher', 'active', 1, '2025-08-10 15:14:16', NULL),
(5, 'Peer to Peer Evaluation', 'Teachers evaluate their colleagues on professional competence and collaboration', 'peer_to_peer', 'active', 1, '2025-08-14 10:47:27', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `mission_vision`
--

CREATE TABLE `mission_vision` (
  `id` int(11) NOT NULL,
  `type` enum('mission','vision') NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `mission_vision`
--

INSERT INTO `mission_vision` (`id`, `type`, `title`, `content`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'mission', 'Our Mission', 'To provide innovative, technology-driven education that empowers students to become leaders in their chosen fields while contributing to the advancement of society through excellence in teaching, research, and community service.', 1, 1, '2025-08-05 15:56:30', '2025-08-05 15:56:30'),
(2, 'vision', 'Our Vision', 'To be the premier technology institution in Southeast Asia, recognized globally for academic excellence, research innovation, and community impact, shaping the future of technology education and industry development.', 1, 1, '2025-08-05 15:56:30', '2025-08-05 15:56:30');

-- --------------------------------------------------------

--
-- Table structure for table `posts`
--

CREATE TABLE `posts` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `type` enum('news','announcement','hiring','event','article') NOT NULL,
  `status` enum('draft','pending','approved','rejected') DEFAULT 'draft',
  `author_id` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `rejected_by` int(11) DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `posts`
--

INSERT INTO `posts` (`id`, `title`, `content`, `type`, `status`, `author_id`, `approved_by`, `rejected_by`, `rejected_at`, `rejection_reason`, `deleted_at`, `image_url`, `created_at`, `updated_at`) VALUES
(2, 'New Computer Engineering Laboratory Opens', 'SEAIT is excited to announce the opening of our state-of-the-art Computer Engineering Laboratory. This new facility features the latest hardware and software technologies, providing students with hands-on experience in computer architecture, embedded systems, and digital design. The laboratory is equipped with modern workstations, development boards, and simulation tools that will enhance our students\' learning experience and prepare them for the rapidly evolving technology industry.', 'news', 'approved', 3, NULL, NULL, NULL, NULL, NULL, NULL, '2024-12-05 06:30:00', '2025-08-05 11:09:14'),
(3, 'SEAIT Students Win National Programming Competition', 'Congratulations to our SEAIT programming team for winning first place in the National Programming Competition 2024! The team, consisting of Computer Science and Information Technology students, demonstrated exceptional problem-solving skills and technical expertise. This victory highlights the quality of our education and the dedication of our faculty in preparing students for real-world challenges.', 'news', 'approved', 3, NULL, NULL, NULL, NULL, NULL, NULL, '2024-12-10 01:15:00', '2025-08-05 11:09:17'),
(4, '2025 Academic Calendar Released', 'SEAIT is pleased to announce the release of the 2025 Academic Calendar. Key dates include: First Semester (June 2 - October 15), Second Semester (November 3 - March 20), and Summer Term (April 7 - May 30). Registration for new students begins on May 15, 2025. Please visit our admissions office or website for detailed information about enrollment procedures and requirements.', 'announcement', 'approved', 3, NULL, NULL, NULL, NULL, NULL, NULL, '2024-12-15 03:00:00', '2025-08-05 11:09:20'),
(5, 'Scholarship Applications Now Open', 'SEAIT is accepting applications for various scholarship programs for the 2025-2026 academic year. Available scholarships include: Academic Excellence Scholarship, Technology Innovation Grant, and Community Service Award. Application deadline is March 31, 2025. For eligibility requirements and application forms, please contact the Student Affairs Office.', 'announcement', 'approved', 3, NULL, NULL, NULL, NULL, NULL, NULL, '2024-12-20 05:45:00', '2025-08-05 11:09:22'),
(6, 'Library Hours Extended for Final Exams', 'To support students during the final examination period, the SEAIT Library will extend its operating hours from December 15, 2024 to January 15, 2025. New hours: Monday to Friday (7:00 AM - 10:00 PM), Saturday (8:00 AM - 8:00 PM), Sunday (9:00 AM - 6:00 PM). Study rooms and computer stations are available on a first-come, first-served basis.', 'announcement', 'approved', 3, NULL, NULL, NULL, NULL, NULL, NULL, '2024-12-12 08:20:00', '2025-08-05 11:09:24'),
(7, 'Faculty Position: Assistant Professor in Computer Science', 'SEAIT is seeking qualified candidates for the position of Assistant Professor in Computer Science. Requirements: PhD in Computer Science or related field, strong research background, teaching experience preferred. Responsibilities include teaching undergraduate and graduate courses, conducting research, and contributing to curriculum development. Competitive salary and benefits package. Application deadline: January 31, 2025.', 'hiring', 'approved', 3, NULL, NULL, NULL, NULL, NULL, NULL, '2024-12-08 02:30:00', '2025-08-05 11:09:29'),
(8, 'IT Support Specialist Position Available', 'SEAIT is hiring an IT Support Specialist to join our Information Technology Department. Requirements: Bachelor\'s degree in IT or related field, 2+ years experience in technical support, knowledge of Windows/Linux systems, networking skills. Responsibilities include maintaining computer systems, providing technical support to faculty and staff, and managing IT infrastructure. Full-time position with benefits.', 'hiring', 'approved', 3, NULL, NULL, NULL, NULL, NULL, NULL, '2024-12-18 06:15:00', '2025-08-05 11:09:32'),
(9, 'SEAIT Technology Innovation Summit 2025', 'Join us for the SEAIT Technology Innovation Summit 2025, scheduled for February 15-17, 2025. This three-day event will feature keynote speakers from leading technology companies, workshops on emerging technologies, student project showcases, and networking opportunities. Topics include Artificial Intelligence, Cybersecurity, Sustainable Technology, and Digital Transformation. Registration opens January 15, 2025.', 'event', 'approved', 3, NULL, NULL, NULL, NULL, NULL, NULL, '2024-12-22 01:00:00', '2025-08-05 11:09:36'),
(10, 'Alumni Homecoming 2025', 'SEAIT invites all alumni to our annual Homecoming celebration on March 8, 2025. This special event will include campus tours, networking sessions, career development workshops, and a gala dinner. Reconnect with former classmates, meet current students, and learn about SEAIT\'s latest developments. Early bird registration available until February 15, 2025.', 'event', 'approved', 3, NULL, NULL, NULL, NULL, NULL, NULL, '2024-12-25 07:30:00', '2025-08-05 11:09:40'),
(11, 'The Future of Technology Education in Southeast Asia', 'As technology continues to evolve at an unprecedented pace, educational institutions must adapt their curricula to prepare students for the challenges and opportunities of the digital age. SEAIT is at the forefront of this transformation, implementing innovative teaching methods and cutting-edge technologies in our classrooms. This article explores how we are shaping the future of technology education and preparing our students for successful careers in the global technology sector.', 'article', 'approved', 3, NULL, NULL, NULL, NULL, NULL, NULL, '2024-12-03 04:00:00', '2025-08-05 11:09:43'),
(12, 'Sustainable Technology: SEAIT\'s Commitment to Green Innovation', 'Sustainability is not just a trend but a necessity in today\'s world. SEAIT is committed to integrating sustainable technology practices into our curriculum and research initiatives. From renewable energy projects to eco-friendly campus initiatives, we are leading the way in green technology education. Learn about our sustainable technology programs and how we are preparing students to address environmental challenges through innovation.', 'article', 'approved', 3, NULL, NULL, NULL, NULL, NULL, NULL, '2024-12-07 08:45:00', '2025-08-05 11:09:46');

-- --------------------------------------------------------

--
-- Table structure for table `publications`
--

CREATE TABLE `publications` (
  `id` int(11) NOT NULL,
  `title` varchar(500) NOT NULL,
  `abstract` text DEFAULT NULL,
  `research_category_id` int(11) DEFAULT NULL,
  `publication_date` date DEFAULT NULL,
  `journal_name` varchar(255) DEFAULT NULL,
  `doi_link` varchar(500) DEFAULT NULL,
  `research_link` varchar(500) DEFAULT NULL,
  `keywords` varchar(500) DEFAULT NULL,
  `status` enum('published','in_progress','submitted') DEFAULT 'published',
  `featured` tinyint(1) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `publications`
--

INSERT INTO `publications` (`id`, `title`, `abstract`, `research_category_id`, `publication_date`, `journal_name`, `doi_link`, `research_link`, `keywords`, `status`, `featured`, `sort_order`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(7, 'Optimizing User Interface and User Experience: Exploring Design Improvements for the School Library System', 'This study explores the impact of modern User Interface (UI) and User Experience (UX) principles on the effectiveness of a school library system. With advancements in Human-Computer Interaction (HCI) and an increasing emphasis on user-centered design, the current library system, which faces usability challenges and outdated design practices, has become a barrier to user satisfaction and engagement. The research aims to identify specific usability issues and apply UI/UX best practices to enhance the system\\\\\\\'s functionality, ease of use, and overall user experience. Through usability testing and user feedback, the study evaluates design flaws such as inefficient navigation, poor color schemes, and cumbersome data entry processes, and investigates how their resolution can improve system performance. Findings reveal that incorporating intuitive navigation, clearer labeling, and modern visual elements, such as optimized button sizes and improved color contrast, significantly enhance task completion efficiency and user satisfaction. Despite some lingering response time issues, the research highlights the critical role of UI/UX design in creating more accessible, engaging, and efficient digital platforms, contributing to improved user experiences and overall system effectiveness in library management.', 2, '2024-12-01', 'International Journal of Scientific and Academic Research', 'https://doi.org/10.54756/IJSAR.2024.19', 'https://doi.org/10.54756/IJSAR.2024.19', 'Library Management, Navigation Efficiency, User Interface, User Experience, Usability Testing', 'published', 1, 0, 1, 3, '2025-08-08 07:36:00', '2025-08-08 07:36:52'),
(8, 'Understanding the Role of OLAP and OLTP in Managing and Interpreting Student Data for SEAIT Scholarship System', 'This research examines the integration of Online Analytical Processing (OLAP) and Online Transaction Processing (OLTP) technologies to enhance the SEAIT Scholarship System. Using qualitative methods, including semi-structured interviews and focus group discussions with administrative staff, the study identifies critical challenges in the current system, such as inefficiencies in real-time data handling, inaccuracies, and limited analytical capabilities. The findings highlight the benefits of integrating OLAP for advanced reporting and OLTP for real-time data processing. This dual approach aims to improve decision-making, optimize resource allocation, and ensure system scalability to meet future demands. The study underscores the transformative potential of combining transactional and analytical technologies within a Human-Computer Interaction (HCI) framework, offering a robust solution to the data management challenges faced by educational institutions.', 2, '2024-12-01', 'International Journal for Scientific and Academic Research', 'https://doi.org/10.54756/IJSAR.2024.18', 'https://ijsar.net/index.php/ijsar/article/view/142', '', 'published', 1, 1, 1, 3, '2025-08-08 08:05:10', '2025-08-08 08:05:10');

-- --------------------------------------------------------

--
-- Table structure for table `publication_authors`
--

CREATE TABLE `publication_authors` (
  `id` int(11) NOT NULL,
  `publication_id` int(11) NOT NULL,
  `author_name` varchar(255) NOT NULL,
  `author_title` varchar(255) DEFAULT NULL,
  `author_department` varchar(255) DEFAULT NULL,
  `author_photo_url` varchar(500) DEFAULT NULL,
  `author_email` varchar(255) DEFAULT NULL,
  `author_bio` text DEFAULT NULL,
  `is_primary_author` tinyint(1) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `publication_authors`
--

INSERT INTO `publication_authors` (`id`, `publication_id`, `author_name`, `author_title`, `author_department`, `author_photo_url`, `author_email`, `author_bio`, `is_primary_author`, `sort_order`, `created_by`, `created_at`, `updated_at`) VALUES
(13, 7, 'Reginald S. Prudente', 'College Dean', 'College of Information and Communication Technology', '', 'germsbound@gmail.com', 'College Dean', 1, 0, 3, '2025-08-08 07:55:04', '2025-08-08 07:55:04'),
(14, 7, 'Cedie E. Gabriel', 'Faculty', 'College of Information and Communication Technology', '', 'cedgabriel@gmail.com', 'College of Information and Communication Technology Faculty', 0, 1, 3, '2025-08-08 07:58:39', '2025-08-08 07:58:39'),
(15, 7, 'Michael Paul Sebando', 'Faculty', 'College of Information and Communication Technology', '', 'psworld143@gmail.com', '', 0, 2, 3, '2025-08-09 15:25:16', '2025-08-09 15:25:16');

-- --------------------------------------------------------

--
-- Table structure for table `questionnaires`
--

CREATE TABLE `questionnaires` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `question_type` enum('multiple_choice','text','rating','yes_no') NOT NULL,
  `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`options`)),
  `required` tinyint(1) DEFAULT 1,
  `order_number` int(11) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `questionnaires`
--

INSERT INTO `questionnaires` (`id`, `category_id`, `question_text`, `question_type`, `options`, `required`, `order_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(6, 2, 'How would you rate the student\'s classroom behavior?', 'rating', '[\"Poor\", \"Fair\", \"Good\", \"Very Good\", \"Excellent\"]', 1, 1, 'active', 1, '2025-08-10 12:54:03', NULL),
(7, 2, 'Does the student follow classroom rules?', 'yes_no', NULL, 1, 2, 'active', 1, '2025-08-10 12:54:03', NULL),
(8, 2, 'How does the student interact with peers?', 'multiple_choice', '[\"Very Well\", \"Well\", \"Average\", \"Poorly\", \"Very Poorly\"]', 1, 3, 'active', 1, '2025-08-10 12:54:03', NULL),
(9, 2, 'Describe any behavioral concerns:', 'text', NULL, 0, 4, 'active', 1, '2025-08-10 12:54:03', NULL),
(10, 2, 'What positive behaviors have you observed?', 'text', NULL, 0, 5, 'active', 1, '2025-08-10 12:54:03', NULL),
(11, 3, 'How would you rate the student\'s communication skills?', 'rating', '[\"Poor\", \"Fair\", \"Good\", \"Very Good\", \"Excellent\"]', 1, 1, 'active', 1, '2025-08-10 12:54:03', NULL),
(12, 3, 'Does the student participate in group activities?', 'yes_no', NULL, 1, 2, 'active', 1, '2025-08-10 12:54:03', NULL),
(13, 3, 'How does the student handle conflicts?', 'multiple_choice', '[\"Very Well\", \"Well\", \"Average\", \"Poorly\", \"Very Poorly\"]', 1, 3, 'active', 1, '2025-08-10 12:54:03', NULL),
(14, 3, 'Describe the student\'s leadership qualities:', 'text', NULL, 0, 4, 'active', 1, '2025-08-10 12:54:03', NULL),
(15, 3, 'What social skills need development?', 'text', NULL, 1, 5, 'active', 1, '2025-08-10 12:54:03', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `quizzes`
--

CREATE TABLE `quizzes` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `class_id` int(11) DEFAULT NULL,
  `lesson_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `quiz_type` enum('general','lesson_specific','multiple_choice','true_false','essay','mixed') DEFAULT 'general',
  `time_limit` int(11) DEFAULT NULL,
  `passing_score` int(11) DEFAULT 70,
  `max_attempts` int(11) DEFAULT 1,
  `status` enum('draft','published','archived') DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `quizzes`
--

INSERT INTO `quizzes` (`id`, `teacher_id`, `class_id`, `lesson_id`, `title`, `description`, `quiz_type`, `time_limit`, `passing_score`, `max_attempts`, `status`, `created_at`, `updated_at`) VALUES
(2, 2, NULL, 4, 'Introduction to Application Development Quiz', 'This quiz covers the fundamental concepts of application development including types of applications, development methodologies, and key concepts. The quiz consists of multiple choice questions and has a time limit of 30 minutes.', 'lesson_specific', 30, 70, 2, 'published', '2025-08-17 13:29:40', '2025-08-17 14:05:39'),
(3, 2, NULL, 5, 'Introduction to Application Development Quiz', 'This quiz covers the fundamental concepts of application development including types of applications, development methodologies, and key concepts. The quiz consists of multiple choice questions and has a time limit of 30 minutes.', 'lesson_specific', 30, 70, 2, 'published', '2025-08-17 13:30:40', '2025-08-17 14:05:39'),
(4, 2, NULL, NULL, 'Sample Quiz', 'This is a sample quiz to test the system', 'general', 30, 70, 1, 'draft', '2025-08-17 13:59:06', '2025-08-17 14:05:51');

-- --------------------------------------------------------

--
-- Table structure for table `quiz_answers`
--

CREATE TABLE `quiz_answers` (
  `id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `answer_text` text NOT NULL,
  `is_correct` tinyint(1) DEFAULT 0,
  `order_number` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quiz_class_assignments`
--

CREATE TABLE `quiz_class_assignments` (
  `id` int(11) NOT NULL,
  `quiz_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `due_date` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'Due date and time for the quiz',
  `time_limit` int(11) DEFAULT NULL COMMENT 'Time limit in minutes for this assignment',
  `max_attempts` int(11) DEFAULT 1 COMMENT 'Maximum attempts allowed',
  `assigned_by` int(11) NOT NULL DEFAULT 1 COMMENT 'Teacher who assigned the quiz',
  `status` enum('active','inactive','expired') DEFAULT 'active',
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'When the quiz was assigned'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `quiz_class_assignments`
--

INSERT INTO `quiz_class_assignments` (`id`, `quiz_id`, `class_id`, `due_date`, `time_limit`, `max_attempts`, `assigned_by`, `status`, `assigned_at`) VALUES
(2, 3, 5, '2025-08-24 21:30:40', 30, 2, 3, 'active', '2025-08-17 13:30:40');

-- --------------------------------------------------------

--
-- Table structure for table `quiz_questions`
--

CREATE TABLE `quiz_questions` (
  `id` int(11) NOT NULL,
  `quiz_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `question_type` enum('multiple_choice','true_false','fill_blank','short_answer','essay') DEFAULT 'multiple_choice',
  `points` int(11) DEFAULT 1,
  `order_number` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `quiz_questions`
--

INSERT INTO `quiz_questions` (`id`, `quiz_id`, `question_text`, `question_type`, `points`, `order_number`, `created_at`) VALUES
(4, 2, 'What is the primary purpose of application development?', 'multiple_choice', 5, 1, '2025-08-17 13:29:40'),
(5, 2, 'Which type of application runs in web browsers and is accessible from any device with internet connection?', 'multiple_choice', 5, 2, '2025-08-17 13:29:40'),
(6, 2, 'Which development methodology follows a linear, sequential approach?', 'multiple_choice', 5, 3, '2025-08-17 13:29:40'),
(7, 2, 'What does UI stand for in application development?', 'multiple_choice', 5, 4, '2025-08-17 13:29:40'),
(8, 2, 'Which of the following is NOT a type of application?', 'multiple_choice', 5, 5, '2025-08-17 13:29:40'),
(9, 2, 'What is the main characteristic of Agile development methodology?', 'multiple_choice', 5, 6, '2025-08-17 13:29:40'),
(10, 2, 'Which concept involves creating user-friendly and intuitive interfaces?', 'multiple_choice', 5, 7, '2025-08-17 13:29:40'),
(11, 2, 'What is the purpose of API development in applications?', 'multiple_choice', 5, 8, '2025-08-17 13:29:40'),
(12, 2, 'Which development framework is commonly used within Agile methodology?', 'multiple_choice', 5, 9, '2025-08-17 13:29:40'),
(13, 2, 'What is the final step in the application development process?', 'multiple_choice', 5, 10, '2025-08-17 13:29:40'),
(14, 3, 'What is the primary purpose of application development?', 'multiple_choice', 5, 1, '2025-08-17 13:30:40'),
(15, 3, 'Which type of application runs in web browsers and is accessible from any device with internet connection?', 'multiple_choice', 5, 2, '2025-08-17 13:30:40'),
(16, 3, 'Which development methodology follows a linear, sequential approach?', 'multiple_choice', 5, 3, '2025-08-17 13:30:40'),
(17, 3, 'What does UI stand for in application development?', 'multiple_choice', 5, 4, '2025-08-17 13:30:40'),
(18, 3, 'Which of the following is NOT a type of application?', 'multiple_choice', 5, 5, '2025-08-17 13:30:40'),
(19, 3, 'What is the main characteristic of Agile development methodology?', 'multiple_choice', 5, 6, '2025-08-17 13:30:40'),
(20, 3, 'Which concept involves creating user-friendly and intuitive interfaces?', 'multiple_choice', 5, 7, '2025-08-17 13:30:40'),
(21, 3, 'What is the purpose of API development in applications?', 'multiple_choice', 5, 8, '2025-08-17 13:30:40'),
(22, 3, 'Which development framework is commonly used within Agile methodology?', 'multiple_choice', 5, 9, '2025-08-17 13:30:40'),
(23, 3, 'What is the final step in the application development process?', 'multiple_choice', 5, 10, '2025-08-17 13:30:40');

-- --------------------------------------------------------

--
-- Table structure for table `quiz_question_options`
--

CREATE TABLE `quiz_question_options` (
  `id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `option_text` text NOT NULL,
  `is_correct` tinyint(1) DEFAULT 0,
  `option_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `quiz_question_options`
--

INSERT INTO `quiz_question_options` (`id`, `question_id`, `option_text`, `is_correct`, `option_order`, `created_at`) VALUES
(1, 14, 'To create software applications that meet specific user needs', 1, 1, '2025-08-17 13:30:40'),
(2, 14, 'To only make money from software sales', 0, 2, '2025-08-17 13:30:40'),
(3, 14, 'To compete with other developers', 0, 3, '2025-08-17 13:30:40'),
(4, 14, 'To learn programming languages', 0, 4, '2025-08-17 13:30:40'),
(5, 15, 'Web Applications', 1, 1, '2025-08-17 13:30:40'),
(6, 15, 'Mobile Applications', 0, 2, '2025-08-17 13:30:40'),
(7, 15, 'Desktop Applications', 0, 3, '2025-08-17 13:30:40'),
(8, 15, 'Enterprise Applications', 0, 4, '2025-08-17 13:30:40'),
(9, 16, 'Waterfall', 1, 1, '2025-08-17 13:30:40'),
(10, 16, 'Agile', 0, 2, '2025-08-17 13:30:40'),
(11, 16, 'Scrum', 0, 3, '2025-08-17 13:30:40'),
(12, 16, 'DevOps', 0, 4, '2025-08-17 13:30:40'),
(13, 17, 'User Interface', 1, 1, '2025-08-17 13:30:40'),
(14, 17, 'User Integration', 0, 2, '2025-08-17 13:30:40'),
(15, 17, 'User Implementation', 0, 3, '2025-08-17 13:30:40'),
(16, 17, 'User Interaction', 0, 4, '2025-08-17 13:30:40'),
(17, 18, 'Cloud Applications', 0, 1, '2025-08-17 13:30:40'),
(18, 18, 'Web Applications', 0, 2, '2025-08-17 13:30:40'),
(19, 18, 'Mobile Applications', 0, 3, '2025-08-17 13:30:40'),
(20, 18, 'Paper Applications', 1, 4, '2025-08-17 13:30:40'),
(21, 19, 'Iterative and incremental development', 1, 1, '2025-08-17 13:30:40'),
(22, 19, 'Linear and sequential approach', 0, 2, '2025-08-17 13:30:40'),
(23, 19, 'Single phase development', 0, 3, '2025-08-17 13:30:40'),
(24, 19, 'Documentation-heavy process', 0, 4, '2025-08-17 13:30:40'),
(25, 20, 'UX Design', 1, 1, '2025-08-17 13:30:40'),
(26, 20, 'Database Management', 0, 2, '2025-08-17 13:30:40'),
(27, 20, 'API Development', 0, 3, '2025-08-17 13:30:40'),
(28, 20, 'Security Implementation', 0, 4, '2025-08-17 13:30:40'),
(29, 21, 'To enable communication between different software systems', 1, 1, '2025-08-17 13:30:40'),
(30, 21, 'To make applications run faster', 0, 2, '2025-08-17 13:30:40'),
(31, 21, 'To reduce development costs', 0, 3, '2025-08-17 13:30:40'),
(32, 21, 'To improve user interface design', 0, 4, '2025-08-17 13:30:40'),
(33, 22, 'Scrum', 1, 1, '2025-08-17 13:30:40'),
(34, 22, 'Waterfall', 0, 2, '2025-08-17 13:30:40'),
(35, 22, 'Spiral', 0, 3, '2025-08-17 13:30:40'),
(36, 22, 'V-Model', 0, 4, '2025-08-17 13:30:40'),
(37, 23, 'Testing and Quality Assurance', 1, 1, '2025-08-17 13:30:40'),
(38, 23, 'Planning and Analysis', 0, 2, '2025-08-17 13:30:40'),
(39, 23, 'Design and Development', 0, 3, '2025-08-17 13:30:40'),
(40, 23, 'Deployment and Maintenance', 0, 4, '2025-08-17 13:30:40');

-- --------------------------------------------------------

--
-- Table structure for table `quiz_submissions`
--

CREATE TABLE `quiz_submissions` (
  `id` int(11) NOT NULL,
  `assignment_id` int(11) NOT NULL COMMENT 'Reference to quiz_class_assignments',
  `student_id` int(11) NOT NULL,
  `attempt_number` int(11) DEFAULT 1 COMMENT 'Which attempt this is',
  `start_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `end_time` timestamp NULL DEFAULT NULL,
  `score` decimal(5,2) DEFAULT NULL COMMENT 'Final score percentage',
  `status` enum('in_progress','completed','abandoned','expired') DEFAULT 'in_progress',
  `time_taken` int(11) DEFAULT NULL COMMENT 'Time taken in seconds',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IP address of submission',
  `user_agent` text DEFAULT NULL COMMENT 'Browser/user agent info',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quiz_submission_answers`
--

CREATE TABLE `quiz_submission_answers` (
  `id` int(11) NOT NULL,
  `submission_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `selected_option_id` int(11) DEFAULT NULL COMMENT 'For multiple choice/true false',
  `text_answer` text DEFAULT NULL COMMENT 'For essay/short answer questions',
  `is_correct` tinyint(1) DEFAULT NULL COMMENT 'Whether the answer is correct',
  `points_earned` decimal(5,2) DEFAULT 0.00 COMMENT 'Points earned for this answer',
  `feedback` text DEFAULT NULL COMMENT 'Teacher feedback for essay questions',
  `answered_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `research_categories`
--

CREATE TABLE `research_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `color_theme` varchar(7) DEFAULT '#FF6B35',
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `research_categories`
--

INSERT INTO `research_categories` (`id`, `name`, `description`, `color_theme`, `sort_order`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Computer Science', 'Research in software engineering, artificial intelligence, and computer systems', '#FF6B35', 1, 1, 1, '2025-08-05 17:05:53', '2025-08-05 17:05:53'),
(2, 'Information Technology', 'Studies in IT infrastructure, cybersecurity, and digital transformation', '#2C3E50', 2, 1, 1, '2025-08-05 17:05:53', '2025-08-05 17:05:53'),
(3, 'Engineering', 'Research in various engineering disciplines and technological innovations', '#3498DB', 3, 1, 1, '2025-08-05 17:05:53', '2025-08-05 17:05:53'),
(4, 'Business & Management', 'Studies in business administration, entrepreneurship, and organizational behavior', '#27AE60', 4, 1, 1, '2025-08-05 17:05:53', '2025-08-05 17:05:53'),
(5, 'Education Technology', 'Research in educational methodologies and technology-enhanced learning', '#9B59B6', 5, 1, 1, '2025-08-05 17:05:53', '2025-08-05 17:05:53'),
(6, 'Environmental Science', 'Studies in sustainability, environmental protection, and green technologies', '#E67E22', 6, 1, 1, '2025-08-05 17:05:53', '2025-08-05 17:05:53');

-- --------------------------------------------------------

--
-- Table structure for table `semesters`
--

CREATE TABLE `semesters` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('active','inactive','completed') DEFAULT 'active',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `semesters`
--

INSERT INTO `semesters` (`id`, `name`, `academic_year`, `start_date`, `end_date`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'First Semester', '2024-2025', '2025-08-01', '2025-12-15', 'active', 1, '2025-08-10 13:37:28', '2025-08-12 06:52:14');

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(100) DEFAULT 'fas fa-cog',
  `color_theme` varchar(20) DEFAULT '#FF6B35',
  `category_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `name`, `description`, `icon`, `color_theme`, `category_id`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'Library Services', 'Comprehensive library resources and research support', 'fas fa-book', '#FF6B35', 1, 1, 1, '2025-08-06 13:16:30', '2025-08-06 13:16:30'),
(2, 'Student Counseling', 'Professional counseling and mental health support', 'fas fa-user-graduate', '#3B82F6', 2, 1, 1, '2025-08-06 13:16:30', '2025-08-06 13:16:30'),
(3, 'Career Services', 'Career guidance, job placement, and professional development', 'fas fa-briefcase', '#10B981', 2, 1, 2, '2025-08-06 13:16:30', '2025-08-06 13:16:30'),
(4, 'Health Services', 'On-campus health clinic and medical support', 'fas fa-heartbeat', '#EF4444', 4, 1, 1, '2025-08-06 13:16:30', '2025-08-06 13:16:30'),
(5, 'IT Support', 'Technical support and computer services', 'fas fa-laptop', '#10B981', 3, 1, 1, '2025-08-06 13:16:30', '2025-08-06 13:16:30'),
(6, 'Transportation', 'Campus transportation and parking services', 'fas fa-bus', '#8B5CF6', 5, 1, 1, '2025-08-06 13:16:30', '2025-08-06 13:16:30'),
(7, 'Virtual Learning Environment', 'Online learning platforms and digital resources', 'fas fa-desktop', '#F59E0B', 6, 1, 1, '2025-08-06 13:16:30', '2025-08-06 13:16:30');

-- --------------------------------------------------------

--
-- Table structure for table `service_categories`
--

CREATE TABLE `service_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(100) DEFAULT 'fas fa-folder',
  `color_theme` varchar(20) DEFAULT '#FF6B35',
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `service_categories`
--

INSERT INTO `service_categories` (`id`, `name`, `description`, `icon`, `color_theme`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'Academic Services', 'Educational and learning support services', 'fas fa-graduation-cap', '#FF6B35', 1, 1, '2025-08-06 13:16:30', '2025-08-06 13:16:30'),
(2, 'Student Support', 'Services to support student well-being and success', 'fas fa-user-graduate', '#3B82F6', 1, 2, '2025-08-06 13:16:30', '2025-08-06 13:16:30'),
(3, 'Technology Services', 'IT and technical support services', 'fas fa-laptop', '#10B981', 1, 3, '2025-08-06 13:16:30', '2025-08-06 13:16:30'),
(4, 'Health & Wellness', 'Health and wellness related services', 'fas fa-heartbeat', '#EF4444', 1, 4, '2025-08-06 13:16:30', '2025-08-06 13:16:30'),
(5, 'Transportation', 'Transportation and mobility services', 'fas fa-bus', '#8B5CF6', 1, 5, '2025-08-06 13:16:30', '2025-08-06 13:16:30'),
(6, 'Virtual Learning', 'Online and virtual learning environment services', 'fas fa-desktop', '#F59E0B', 1, 6, '2025-08-06 13:16:30', '2025-08-06 13:16:30');

-- --------------------------------------------------------

--
-- Table structure for table `service_details`
--

CREATE TABLE `service_details` (
  `id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text DEFAULT NULL,
  `icon` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `service_details`
--

INSERT INTO `service_details` (`id`, `service_id`, `title`, `content`, `icon`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 1, 'Research Support', 'Access to academic databases, research guides, and citation tools', 'fas fa-search', 1, 1, '2025-08-06 13:16:30', '2025-08-06 13:16:30'),
(2, 1, 'Study Spaces', 'Quiet study areas, group study rooms, and computer workstations', 'fas fa-users', 1, 2, '2025-08-06 13:16:30', '2025-08-06 13:16:30'),
(3, 1, 'Digital Resources', 'E-books, online journals, and multimedia collections', 'fas fa-tablet-alt', 1, 3, '2025-08-06 13:16:30', '2025-08-06 13:16:30'),
(4, 2, 'Academic Counseling', 'Help with course selection, academic planning, and study strategies', 'fas fa-chalkboard-teacher', 1, 1, '2025-08-06 13:16:30', '2025-08-06 13:16:30'),
(5, 2, 'Personal Counseling', 'Individual and group counseling for personal and emotional support', 'fas fa-user-friends', 1, 2, '2025-08-06 13:16:30', '2025-08-06 13:16:30'),
(6, 2, 'Crisis Intervention', '24/7 crisis support and emergency counseling services', 'fas fa-phone', 1, 3, '2025-08-06 13:16:30', '2025-08-06 13:16:30'),
(7, 3, 'Career Planning', 'Career assessment, goal setting, and professional development planning', 'fas fa-route', 1, 1, '2025-08-06 13:16:30', '2025-08-06 13:16:30'),
(8, 3, 'Job Placement', 'Resume writing, interview preparation, and job placement assistance', 'fas fa-briefcase', 1, 2, '2025-08-06 13:16:30', '2025-08-06 13:16:30'),
(9, 3, 'Internship Programs', 'Internship opportunities and professional experience programs', 'fas fa-building', 1, 3, '2025-08-06 13:16:30', '2025-08-06 13:16:30'),
(10, 4, 'Primary Care', 'General health services, physical exams, and basic medical care', 'fas fa-stethoscope', 1, 1, '2025-08-06 13:16:30', '2025-08-06 13:16:30'),
(11, 4, 'Mental Health', 'Mental health screening, counseling, and psychiatric services', 'fas fa-brain', 1, 2, '2025-08-06 13:16:30', '2025-08-06 13:16:30'),
(12, 4, 'Health Education', 'Health promotion, wellness programs, and health education', 'fas fa-heart', 1, 3, '2025-08-06 13:16:30', '2025-08-06 13:16:30'),
(13, 5, 'Technical Support', 'Computer troubleshooting, software installation, and technical assistance', 'fas fa-tools', 1, 1, '2025-08-06 13:16:30', '2025-08-06 13:16:30'),
(14, 5, 'Network Support', 'WiFi assistance, network connectivity, and internet support', 'fas fa-wifi', 1, 2, '2025-08-06 13:16:30', '2025-08-06 13:16:30'),
(15, 5, 'Equipment Loans', 'Laptop, tablet, and equipment lending services', 'fas fa-laptop', 1, 3, '2025-08-06 13:16:30', '2025-08-06 13:16:30'),
(16, 6, 'Campus Shuttle', 'Free campus shuttle service with regular routes and schedules', 'fas fa-shuttle-van', 1, 1, '2025-08-06 13:16:30', '2025-08-06 13:16:30'),
(17, 6, 'Parking Services', 'Parking permits, parking lot management, and parking assistance', 'fas fa-parking', 1, 2, '2025-08-06 13:16:30', '2025-08-06 13:16:30'),
(18, 6, 'Bike Services', 'Bike rental, bike repair, and cycling support services', 'fas fa-bicycle', 1, 3, '2025-08-06 13:16:30', '2025-08-06 13:16:30'),
(19, 7, 'Learning Management System', 'Access to course materials, assignments, and online discussions', 'fas fa-chalkboard', 1, 1, '2025-08-06 13:16:30', '2025-08-06 13:16:30'),
(20, 7, 'Virtual Classrooms', 'Live online classes, webinars, and virtual meeting spaces', 'fas fa-video', 1, 2, '2025-08-06 13:16:30', '2025-08-06 13:16:30'),
(21, 7, 'Digital Tools', 'Access to educational software, apps, and digital learning tools', 'fas fa-tablet-alt', 1, 3, '2025-08-06 13:16:30', '2025-08-06 13:16:30');

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
(2, 'site_description', 'Empowering minds, shaping futures through excellence in technology education. SEAIT is committed to providing innovative, industry-relevant programs that prepare students for successful careers in the digital age.', '2025-08-05 10:17:11'),
(3, 'contact_email', 'info@seait.edu.ph', '2025-08-05 10:14:11'),
(4, 'contact_phone', '+63 123 456 7890', '2025-08-05 10:14:11'),
(5, 'contact_address', '123 SEAIT Street, Technology District, Metro Manila, Philippines 1234', '2025-08-05 10:17:11');

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
  `email` varchar(255) NOT NULL,
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
(1, '2024-0001', 'Juan', 'Santos', 'Dela Cruz', 'juan.delacruz@seait.edu.ph', '$2y$10$g5F5UI3JzdlCNul52Qaqxe1h9gk/aAHTRm9AR8DTepzzNpd6gtQ4.', 'active', '2025-08-10 11:44:42', '2025-08-13 16:26:12', NULL),
(2, '2024-0002', 'Maria', 'Garcia', 'Santos', 'maria.santos@seait.edu.ph', '$2y$10$g5F5UI3JzdlCNul52Qaqxe1h9gk/aAHTRm9AR8DTepzzNpd6gtQ4.', 'active', '2025-08-10 11:44:42', '2025-08-13 16:25:12', NULL),
(4, '2024-0004', 'Ana', 'Martinez', 'Gonzales', 'ana.gonzales@seait.edu.ph', '$2y$10$g5F5UI3JzdlCNul52Qaqxe1h9gk/aAHTRm9AR8DTepzzNpd6gtQ4.', 'active', '2025-08-10 11:44:42', '2025-08-13 16:26:12', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `student_academic_info`
--

CREATE TABLE `student_academic_info` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `program_id` int(11) DEFAULT NULL,
  `year_level` varchar(20) DEFAULT NULL,
  `section` varchar(50) DEFAULT NULL,
  `enrollment_date` date DEFAULT NULL,
  `expected_graduation` date DEFAULT NULL,
  `gpa` decimal(3,2) DEFAULT NULL,
  `units_completed` int(11) DEFAULT 0,
  `units_remaining` int(11) DEFAULT 0,
  `academic_status` enum('regular','probation','suspended','graduated','withdrawn') DEFAULT 'regular',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `student_academic_info`
--

INSERT INTO `student_academic_info` (`id`, `student_id`, `program_id`, `year_level`, `section`, `enrollment_date`, `expected_graduation`, `gpa`, `units_completed`, `units_remaining`, `academic_status`, `created_at`, `updated_at`) VALUES
(19, 1, 10, '2nd Year', 'A', '2024-01-15', '2026-05-30', NULL, 0, 0, 'regular', '2025-08-10 12:00:45', NULL),
(20, 2, 1, '3rd Year', 'B', '2023-08-20', '2025-05-30', NULL, 0, 0, 'regular', '2025-08-10 12:00:45', NULL),
(22, 4, 18, '4th Year', 'A', '2021-08-15', '2025-05-30', NULL, 0, 0, 'regular', '2025-08-10 12:00:45', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `student_documents`
--

CREATE TABLE `student_documents` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `document_type` varchar(100) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_enrollments`
--

CREATE TABLE `student_enrollments` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `teacher_subject_id` int(11) NOT NULL,
  `enrollment_date` date NOT NULL,
  `status` enum('enrolled','dropped','completed') DEFAULT 'enrolled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_evaluations`
--

CREATE TABLE `student_evaluations` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `evaluated_by` int(11) NOT NULL,
  `evaluation_date` date NOT NULL,
  `status` enum('draft','completed','archived') DEFAULT 'draft',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_login_history`
--

CREATE TABLE `student_login_history` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `login_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `success` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_notifications`
--

CREATE TABLE `student_notifications` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','error') DEFAULT 'info',
  `read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_password_resets`
--

CREATE TABLE `student_password_resets` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_profiles`
--

CREATE TABLE `student_profiles` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `zip_code` varchar(20) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'Philippines',
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `emergency_contact_name` varchar(200) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `emergency_contact_relationship` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_registration_logs`
--

CREATE TABLE `student_registration_logs` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `registration_method` enum('manual','excel_import','api') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `student_registration_logs`
--

INSERT INTO `student_registration_logs` (`id`, `student_id`, `admin_id`, `registration_method`, `created_at`) VALUES
(1, 1, 1, 'manual', '2025-08-10 11:44:42'),
(2, 2, 1, 'manual', '2025-08-10 11:44:42'),
(4, 4, 1, 'manual', '2025-08-10 11:44:42');

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
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `units` int(11) DEFAULT 3,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`id`, `code`, `name`, `description`, `units`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'MATH101', 'College Algebra', 'Fundamental concepts of algebra', 3, 'active', 1, '2025-08-10 13:37:28', NULL),
(2, 'ENG101', 'English Composition', 'Basic writing and communication skills', 3, 'active', 1, '2025-08-10 13:37:28', NULL),
(3, 'SCI101', 'General Science', 'Introduction to scientific principles', 3, 'active', 1, '2025-08-10 13:37:28', NULL),
(4, 'HIST101', 'Philippine History', 'History of the Philippines', 3, 'active', 1, '2025-08-10 13:37:28', NULL),
(5, 'COMP101', 'Computer Fundamentals', 'Basic computer concepts and applications', 3, 'active', 1, '2025-08-10 13:37:28', NULL),
(23, 'CS401', 'Application Development and Emerging Technologies', 'Advanced course covering modern application development practices and emerging technologies', 3, 'active', 2, '2025-08-16 13:18:03', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `teachers`
--

CREATE TABLE `teachers` (
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
-- Dumping data for table `teachers`
--

INSERT INTO `teachers` (`id`, `user_id`, `department`, `position`, `phone`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 'Computer Science', 'Assistant Professor', '+63 912 345 6789', 'active', '2025-08-10 14:08:33', NULL),
(2, 2, 'Mathematics', 'Associate Professor', '+63 923 456 7890', 'active', '2025-08-10 14:08:33', NULL),
(3, 3, 'English', 'Instructor', '+63 934 567 8901', 'active', '2025-08-10 14:08:33', NULL),
(4, 5, 'History', 'Instructor', '+63 956 789 0123', 'active', '2025-08-10 14:08:33', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `teacher_classes`
--

CREATE TABLE `teacher_classes` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `section` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `join_code` varchar(20) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `teacher_classes`
--

INSERT INTO `teacher_classes` (`id`, `teacher_id`, `subject_id`, `section`, `description`, `join_code`, `status`, `created_at`, `updated_at`) VALUES
(3, 2, 77, 'IT1C', '', 'CD1C3939', 'active', '2025-08-14 15:10:36', '2025-08-16 13:35:07'),
(4, 2, 78, 'IT2A', '', 'D80DB3F2', 'active', '2025-08-14 15:10:57', '2025-08-16 13:33:49'),
(5, 2, 79, 'IT3A', '', '7F3E95FD', 'active', '2025-08-14 15:11:10', '2025-08-16 13:30:11'),
(6, 2, 79, 'IT3B', '', 'A66CD9B6', 'active', '2025-08-16 13:35:58', NULL);

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
-- Stand-in structure for view `teacher_recent_activities`
-- (See below for the actual view)
--
CREATE TABLE `teacher_recent_activities` (
`activity_type` varchar(12)
,`activity_id` int(11)
,`teacher_id` int(11)
,`description` varchar(323)
,`activity_date` timestamp
);

-- --------------------------------------------------------

--
-- Table structure for table `teacher_settings`
--

CREATE TABLE `teacher_settings` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `email_notifications` tinyint(1) DEFAULT 1,
  `announcement_notifications` tinyint(1) DEFAULT 1,
  `student_join_notifications` tinyint(1) DEFAULT 1,
  `evaluation_notifications` tinyint(1) DEFAULT 1,
  `default_class_status` enum('active','inactive') DEFAULT 'active',
  `auto_approve_students` tinyint(1) DEFAULT 0,
  `default_announcement_priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `teacher_subjects`
--

CREATE TABLE `teacher_subjects` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `semester_id` int(11) NOT NULL,
  `section` varchar(20) DEFAULT NULL,
  `schedule` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `teacher_training_needs_view`
-- (See below for the actual view)
--
CREATE TABLE `teacher_training_needs_view` (
`user_id` int(11)
,`first_name` varchar(50)
,`last_name` varchar(50)
,`email` varchar(100)
,`sub_category_id` int(11)
,`sub_category_name` varchar(255)
,`main_category_name` varchar(255)
,`average_rating` decimal(14,4)
,`total_ratings` bigint(21)
,`priority_level` varchar(8)
);

-- --------------------------------------------------------

--
-- Table structure for table `timeline_events`
--

CREATE TABLE `timeline_events` (
  `id` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `timeline_events`
--

INSERT INTO `timeline_events` (`id`, `year`, `title`, `description`, `sort_order`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 2006, 'Foundation Established', 'SEAIT was founded with a vision to provide quality technology education in Southeast Asia, starting with just 50 students and 5 faculty members.', 1, 1, 1, '2025-08-05 15:56:30', '2025-08-05 15:56:30'),
(7, 2010, 'First Accreditation', 'Achieved full accreditation for all engineering programs, marking a significant milestone in our commitment to academic excellence.', 2, 1, 1, '2025-08-05 16:26:14', '2025-08-05 16:26:14'),
(8, 2015, 'Campus Expansion', 'Expanded to a modern 50-acre campus with state-of-the-art laboratories and research facilities.', 3, 1, 1, '2025-08-05 16:26:14', '2025-08-05 16:26:14'),
(9, 2020, 'Digital Transformation', 'Implemented comprehensive digital learning platforms and modernized our educational delivery systems.', 4, 1, 1, '2025-08-05 16:26:14', '2025-08-05 16:26:14'),
(10, 2024, 'Global Recognition', 'Recognized as one of the top technology institutions in Southeast Asia with over 10,000 students and 500 faculty members.', 5, 1, 1, '2025-08-05 16:26:14', '2025-08-05 16:26:14');

-- --------------------------------------------------------

--
-- Table structure for table `trainings_seminars`
--

CREATE TABLE `trainings_seminars` (
  `id` int(11) NOT NULL,
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
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `trainings_seminars`
--

INSERT INTO `trainings_seminars` (`id`, `title`, `description`, `type`, `category_id`, `main_category_id`, `sub_category_id`, `duration_hours`, `max_participants`, `venue`, `start_date`, `end_date`, `registration_deadline`, `status`, `is_mandatory`, `certificate_provided`, `materials_provided`, `cost`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Effective Classroom Management Strategies', 'Comprehensive workshop on effective classroom management strategies, behavior management techniques, and creating positive learning environments.', 'training', 1, 1, 1, 8.00, 25, 'Conference Room A', '2025-09-15 09:00:00', '2025-09-15 17:00:00', '2024-03-10 17:00:00', 'published', 0, 1, 1, 0.00, 1, '2025-08-13 04:23:02', '2025-08-14 07:47:30'),
(2, 'Modern Teaching Methodologies Workshop', 'Advanced training on modern teaching methodologies, instructional design, and active learning strategies for enhanced student engagement.', 'workshop', 2, 1, 2, 6.00, 20, 'Training Hall B', '2025-09-20 09:00:00', '2025-09-20 15:00:00', '2024-03-15 17:00:00', 'published', 0, 1, 1, 500.00, 1, '2025-08-13 04:23:02', '2025-08-14 07:47:30'),
(3, 'Technology Integration in Education', 'Workshop on integrating technology tools and digital resources into classroom instruction for improved learning outcomes.', 'seminar', 3, 1, 4, 4.00, 30, 'Computer Lab 1', '2025-09-25 13:00:00', '2025-09-25 17:00:00', '2024-03-20 17:00:00', 'published', 0, 1, 0, 0.00, 1, '2025-08-13 04:23:02', '2025-08-14 07:47:30'),
(4, 'Student Engagement Techniques', 'Discover methods to increase student participation and motivation', 'training', 4, 1, 5, 6.00, 25, 'Conference Room C', '2024-04-01 09:00:00', '2024-04-01 15:00:00', '2024-03-27 17:00:00', 'draft', 0, 1, 1, 0.00, 1, '2025-08-13 04:23:02', NULL),
(5, 'Assessment and Evaluation Best Practices', 'Learn effective assessment strategies and evaluation methods', 'seminar', 5, 1, 2, 4.00, 35, 'Lecture Hall', '2024-04-05 14:00:00', '2024-04-05 18:00:00', '2024-04-01 17:00:00', 'draft', 0, 1, 0, 0.00, 1, '2025-08-13 04:23:02', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `training_categories`
--

CREATE TABLE `training_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `training_categories`
--

INSERT INTO `training_categories` (`id`, `name`, `description`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Classroom Management', 'Trainings focused on improving classroom discipline and management skills', 'active', 1, '2025-08-13 04:23:02', NULL),
(2, 'Teaching Methodologies', 'Seminars on modern teaching techniques and strategies', 'active', 1, '2025-08-13 04:23:02', NULL),
(3, 'Technology Integration', 'Workshops on incorporating technology in teaching', 'active', 1, '2025-08-13 04:23:02', NULL),
(4, 'Student Engagement', 'Training on methods to increase student participation and engagement', 'active', 1, '2025-08-13 04:23:02', NULL),
(5, 'Assessment Strategies', 'Seminars on effective assessment and evaluation methods', 'active', 1, '2025-08-13 04:23:02', NULL),
(6, 'Professional Development', 'General professional development and career advancement', 'active', 1, '2025-08-13 04:23:02', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `training_materials`
--

CREATE TABLE `training_materials` (
  `id` int(11) NOT NULL,
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
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `training_registrations`
--

CREATE TABLE `training_registrations` (
  `id` int(11) NOT NULL,
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
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `training_suggestions`
--

CREATE TABLE `training_suggestions` (
  `id` int(11) NOT NULL,
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
  `response_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `password` varchar(255) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `role` enum('admin','social_media_manager','content_creator','guidance_officer','teacher','head','student') NOT NULL DEFAULT 'student',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `first_name`, `last_name`, `role`, `status`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@seait.edu.ph', '$2y$10$cMOojXGmDMOhjndcskIz7.SiyLe5qaJYxrtNrlLgpvpmUovMHFwPS', 'Admin', 'User', 'admin', 'active', '2025-08-05 10:14:11', '2025-08-10 17:21:54'),
(2, 'social_manager', 'social@seait.edu.ph', '$2y$10$cMOojXGmDMOhjndcskIz7.SiyLe5qaJYxrtNrlLgpvpmUovMHFwPS', 'Social', 'Manager', 'social_media_manager', 'active', '2025-08-05 10:14:11', '2025-08-10 17:21:54'),
(3, 'content_creator', 'content@seait.edu.ph', '$2y$10$cMOojXGmDMOhjndcskIz7.SiyLe5qaJYxrtNrlLgpvpmUovMHFwPS', 'Content', 'Creator', 'content_creator', 'active', '2025-08-05 10:14:11', '2025-08-17 13:28:48'),
(5, 'guidance', 'guidance@seait.edu.ph', '$2y$10$cMOojXGmDMOhjndcskIz7.SiyLe5qaJYxrtNrlLgpvpmUovMHFwPS', 'Guidance', 'Officer', 'guidance_officer', 'active', '2025-08-10 12:41:23', '2025-08-10 17:21:54'),
(7, 'rprudente@seait.edu.ph', 'rprudente@seait.edu.ph', '$2y$10$cMOojXGmDMOhjndcskIz7.SiyLe5qaJYxrtNrlLgpvpmUovMHFwPS', 'Reginald', 'Prudente', 'head', 'active', '2025-08-10 14:52:19', '2025-08-10 17:21:54'),
(11, 'jpalate', 'jpalate@seait.edu.ph', '$2y$10$5uzRpQGhSmOqFmTrmwgw8uRN45yEL81Cvqj4QZ.rQnOjIPslRBuRO', 'Jestone', 'Palate', 'head', 'active', '2025-08-12 10:11:32', '2025-08-12 10:11:32');

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
(4, 'Publications', 'Thank you for your question! For specific inquiries, I recommend contacting our relevant department directly. You can find contact information in the Contact Us section, or visit our main office during business hours.', '', '', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 0, '2025-08-08 07:12:45');

-- --------------------------------------------------------

--
-- Structure for view `active_students_view`
--
DROP TABLE IF EXISTS `active_students_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `active_students_view`  AS SELECT `s`.`id` AS `id`, `s`.`student_id` AS `student_id`, `s`.`first_name` AS `first_name`, `s`.`middle_name` AS `middle_name`, `s`.`last_name` AS `last_name`, `s`.`email` AS `email`, `s`.`status` AS `status`, `s`.`created_at` AS `created_at`, concat(`s`.`first_name`,' ',`s`.`last_name`) AS `full_name`, `sp`.`phone` AS `phone`, `sp`.`date_of_birth` AS `date_of_birth`, `sai`.`program_id` AS `program_id`, `sai`.`year_level` AS `year_level`, `sai`.`academic_status` AS `academic_status` FROM ((`students` `s` left join `student_profiles` `sp` on(`s`.`id` = `sp`.`student_id`)) left join `student_academic_info` `sai` on(`s`.`id` = `sai`.`student_id`)) WHERE `s`.`status` = 'active' ;

-- --------------------------------------------------------

--
-- Structure for view `evaluation_summary_view`
--
DROP TABLE IF EXISTS `evaluation_summary_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `evaluation_summary_view`  AS SELECT `es`.`id` AS `id`, `es`.`evaluator_id` AS `evaluator_id`, `es`.`evaluator_type` AS `evaluator_type`, `es`.`evaluatee_id` AS `evaluatee_id`, `es`.`evaluatee_type` AS `evaluatee_type`, `es`.`main_category_id` AS `main_category_id`, `mec`.`name` AS `main_category_name`, `mec`.`evaluation_type` AS `evaluation_type`, `es`.`evaluation_date` AS `evaluation_date`, `es`.`status` AS `status`, `es`.`notes` AS `notes`, count(`er`.`id`) AS `total_responses`, avg(`er`.`rating_value`) AS `average_rating`, count(case when `er`.`rating_value` = 5 then 1 end) AS `excellent_count`, count(case when `er`.`rating_value` = 4 then 1 end) AS `very_satisfactory_count`, count(case when `er`.`rating_value` = 3 then 1 end) AS `satisfactory_count`, count(case when `er`.`rating_value` = 2 then 1 end) AS `good_count`, count(case when `er`.`rating_value` = 1 then 1 end) AS `poor_count` FROM ((`evaluation_sessions` `es` join `main_evaluation_categories` `mec` on(`es`.`main_category_id` = `mec`.`id`)) left join `evaluation_responses` `er` on(`es`.`id` = `er`.`evaluation_session_id`)) GROUP BY `es`.`id` ;

-- --------------------------------------------------------

--
-- Structure for view `lms_assignments_view`
--
DROP TABLE IF EXISTS `lms_assignments_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `lms_assignments_view`  AS SELECT `a`.`id` AS `id`, `a`.`class_id` AS `class_id`, `a`.`category_id` AS `category_id`, `a`.`title` AS `title`, `a`.`description` AS `description`, `a`.`instructions` AS `instructions`, `a`.`due_date` AS `due_date`, `a`.`max_score` AS `max_score`, `a`.`allow_late_submission` AS `allow_late_submission`, `a`.`late_penalty` AS `late_penalty`, `a`.`file_required` AS `file_required`, `a`.`max_file_size` AS `max_file_size`, `a`.`allowed_file_types` AS `allowed_file_types`, `a`.`status` AS `status`, `a`.`created_by` AS `created_by`, `a`.`created_at` AS `created_at`, `a`.`updated_at` AS `updated_at`, `ac`.`name` AS `category_name`, `ac`.`color` AS `category_color`, count(`s`.`id`) AS `submission_count`, count(case when `s`.`status` = 'graded' then 1 end) AS `graded_count`, `u`.`first_name` AS `created_by_name`, `u`.`last_name` AS `created_by_last_name` FROM (((`lms_assignments` `a` join `lms_assignment_categories` `ac` on(`a`.`category_id` = `ac`.`id`)) join `users` `u` on(`a`.`created_by` = `u`.`id`)) left join `lms_assignment_submissions` `s` on(`a`.`id` = `s`.`assignment_id`)) WHERE `a`.`status` <> 'draft' GROUP BY `a`.`id` ;

-- --------------------------------------------------------

--
-- Structure for view `lms_discussion_activity`
--
DROP TABLE IF EXISTS `lms_discussion_activity`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `lms_discussion_activity`  AS SELECT `d`.`id` AS `id`, `d`.`class_id` AS `class_id`, `d`.`title` AS `title`, `d`.`description` AS `description`, `d`.`is_pinned` AS `is_pinned`, `d`.`is_locked` AS `is_locked`, `d`.`allow_replies` AS `allow_replies`, `d`.`status` AS `status`, `d`.`created_by` AS `created_by`, `d`.`created_at` AS `created_at`, `d`.`updated_at` AS `updated_at`, count(`p`.`id`) AS `post_count`, count(distinct `p`.`author_id`) AS `participant_count`, max(`p`.`created_at`) AS `last_activity`, `u`.`first_name` AS `created_by_name`, `u`.`last_name` AS `created_by_last_name` FROM ((`lms_discussions` `d` join `users` `u` on(`d`.`created_by` = `u`.`id`)) left join `lms_discussion_posts` `p` on(`d`.`id` = `p`.`discussion_id` and `p`.`status` = 'active')) WHERE `d`.`status` = 'active' GROUP BY `d`.`id` ;

-- --------------------------------------------------------

--
-- Structure for view `lms_materials_view`
--
DROP TABLE IF EXISTS `lms_materials_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `lms_materials_view`  AS SELECT `m`.`id` AS `id`, `m`.`class_id` AS `class_id`, `m`.`category_id` AS `category_id`, `m`.`title` AS `title`, `m`.`description` AS `description`, `m`.`file_path` AS `file_path`, `m`.`file_name` AS `file_name`, `m`.`file_size` AS `file_size`, `m`.`mime_type` AS `mime_type`, `m`.`external_url` AS `external_url`, `m`.`content` AS `content`, `m`.`type` AS `type`, `m`.`order_number` AS `order_number`, `m`.`is_public` AS `is_public`, `m`.`status` AS `status`, `m`.`created_by` AS `created_by`, `m`.`created_at` AS `created_at`, `m`.`updated_at` AS `updated_at`, `mc`.`name` AS `category_name`, `mc`.`icon` AS `category_icon`, `mc`.`color` AS `category_color`, count(`ml`.`id`) AS `access_count`, `u`.`first_name` AS `created_by_name`, `u`.`last_name` AS `created_by_last_name` FROM (((`lms_materials` `m` join `lms_material_categories` `mc` on(`m`.`category_id` = `mc`.`id`)) join `users` `u` on(`m`.`created_by` = `u`.`id`)) left join `lms_material_access_logs` `ml` on(`m`.`id` = `ml`.`material_id`)) WHERE `m`.`status` = 'active' GROUP BY `m`.`id` ;

-- --------------------------------------------------------

--
-- Structure for view `lms_student_grades_summary`
--
DROP TABLE IF EXISTS `lms_student_grades_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `lms_student_grades_summary`  AS SELECT `sg`.`class_id` AS `class_id`, `sg`.`student_id` AS `student_id`, `s`.`first_name` AS `first_name`, `s`.`last_name` AS `last_name`, `s`.`student_id` AS `student_number`, `gc`.`name` AS `category_name`, `gc`.`weight` AS `weight`, count(`sg`.`id`) AS `grade_count`, avg(`sg`.`percentage`) AS `average_percentage`, sum(`sg`.`score`) AS `total_score`, sum(`sg`.`max_score`) AS `total_max_score` FROM ((`lms_student_grades` `sg` join `students` `s` on(`sg`.`student_id` = `s`.`id`)) join `lms_grade_categories` `gc` on(`sg`.`category_id` = `gc`.`id`)) WHERE `sg`.`status` = 'published' GROUP BY `sg`.`class_id`, `sg`.`student_id`, `gc`.`id` ;

-- --------------------------------------------------------

--
-- Structure for view `student_statistics_view`
--
DROP TABLE IF EXISTS `student_statistics_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `student_statistics_view`  AS SELECT count(0) AS `total_students`, sum(case when `students`.`status` = 'active' then 1 else 0 end) AS `active_students`, sum(case when `students`.`status` = 'pending' then 1 else 0 end) AS `pending_students`, sum(case when `students`.`status` = 'inactive' then 1 else 0 end) AS `inactive_students`, sum(case when cast(`students`.`created_at` as date) = curdate() then 1 else 0 end) AS `today_registrations`, sum(case when month(`students`.`created_at`) = month(curdate()) and year(`students`.`created_at`) = year(curdate()) then 1 else 0 end) AS `this_month_registrations` FROM `students` WHERE `students`.`status` <> 'deleted' ;

-- --------------------------------------------------------

--
-- Structure for view `teacher_dashboard_stats`
--
DROP TABLE IF EXISTS `teacher_dashboard_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `teacher_dashboard_stats`  AS SELECT `t`.`id` AS `teacher_id`, count(distinct `tc`.`id`) AS `total_classes`, count(distinct case when `tc`.`status` = 'active' then `tc`.`id` end) AS `active_classes`, count(distinct `ce`.`id`) AS `total_enrollments`, count(distinct case when `ce`.`status` = 'active' then `ce`.`id` end) AS `active_enrollments`, count(distinct `es`.`id`) AS `total_evaluations`, count(distinct case when `es`.`status` = 'completed' then `es`.`id` end) AS `completed_evaluations` FROM (((`users` `t` left join `teacher_classes` `tc` on(`t`.`id` = `tc`.`teacher_id`)) left join `class_enrollments` `ce` on(`tc`.`id` = `ce`.`class_id`)) left join `evaluation_sessions` `es` on(`t`.`id` = `es`.`evaluator_id`)) WHERE `t`.`role` = 'teacher' GROUP BY `t`.`id` ;

-- --------------------------------------------------------

--
-- Structure for view `teacher_recent_activities`
--
DROP TABLE IF EXISTS `teacher_recent_activities`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `teacher_recent_activities`  AS SELECT 'class' AS `activity_type`, `tc`.`id` AS `activity_id`, `tc`.`teacher_id` AS `teacher_id`, concat('Created class: ',`cc`.`subject_title`,' - ',`tc`.`section`) FROM (`teacher_classes` `tc` join `course_curriculum` `cc` on(`tc`.`subject_id` = `cc`.`id`))union all select 'announcement' AS `activity_type`,`ca`.`id` AS `activity_id`,`ca`.`teacher_id` AS `teacher_id`,concat('Posted announcement: ',`ca`.`title`) collate utf8mb4_unicode_ci AS `description`,`ca`.`created_at` AS `activity_date` from `class_announcements` `ca` union all select 'evaluation' AS `activity_type`,`es`.`id` AS `activity_id`,`es`.`evaluator_id` AS `teacher_id`,concat('Completed evaluation for ',`u`.`first_name`,' ',`u`.`last_name`) collate utf8mb4_unicode_ci AS `description`,`es`.`updated_at` AS `activity_date` from (`evaluation_sessions` `es` join `users` `u` on(`es`.`evaluatee_id` = `u`.`id`)) where `es`.`status` = 'completed' order by `activity_date` desc  ;

-- --------------------------------------------------------

--
-- Structure for view `teacher_training_needs_view`
--
DROP TABLE IF EXISTS `teacher_training_needs_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `teacher_training_needs_view`  AS SELECT `u`.`id` AS `user_id`, `u`.`first_name` AS `first_name`, `u`.`last_name` AS `last_name`, `u`.`email` AS `email`, `esc`.`id` AS `sub_category_id`, `esc`.`name` AS `sub_category_name`, `mec`.`name` AS `main_category_name`, avg(`er`.`rating_value`) AS `average_rating`, count(`er`.`id`) AS `total_ratings`, CASE WHEN avg(`er`.`rating_value`) < 3.0 THEN 'critical' WHEN avg(`er`.`rating_value`) < 3.5 THEN 'high' WHEN avg(`er`.`rating_value`) < 4.0 THEN 'medium' ELSE 'low' END AS `priority_level` FROM (((((`users` `u` join `evaluation_sessions` `es` on(`u`.`id` = `es`.`evaluatee_id`)) join `main_evaluation_categories` `mec` on(`es`.`main_category_id` = `mec`.`id`)) join `evaluation_sub_categories` `esc` on(`mec`.`id` = `esc`.`main_category_id`)) left join `evaluation_questionnaires` `eq` on(`esc`.`id` = `eq`.`sub_category_id`)) left join `evaluation_responses` `er` on(`eq`.`id` = `er`.`questionnaire_id` and `es`.`id` = `er`.`evaluation_session_id`)) WHERE `u`.`role` = 'teacher' AND `es`.`evaluatee_type` = 'teacher' AND `es`.`status` = 'completed' AND `er`.`rating_value` is not null GROUP BY `u`.`id`, `esc`.`id` HAVING `total_ratings` >= 3 ;

-- --------------------------------------------------------

--
-- Structure for view `training_summary_view`
--
DROP TABLE IF EXISTS `training_summary_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `training_summary_view`  AS SELECT `ts`.`id` AS `id`, `ts`.`title` AS `title`, `ts`.`type` AS `type`, `ts`.`status` AS `status`, `ts`.`start_date` AS `start_date`, `ts`.`end_date` AS `end_date`, `tc`.`name` AS `category_name`, `mec`.`name` AS `main_category_name`, `esc`.`name` AS `sub_category_name`, `ts`.`max_participants` AS `max_participants`, count(`tr`.`id`) AS `registered_count`, count(case when `tr`.`status` = 'completed' then 1 end) AS `completed_count`, avg(`tr`.`feedback_rating`) AS `average_feedback_rating` FROM ((((`trainings_seminars` `ts` left join `training_categories` `tc` on(`ts`.`category_id` = `tc`.`id`)) left join `main_evaluation_categories` `mec` on(`ts`.`main_category_id` = `mec`.`id`)) left join `evaluation_sub_categories` `esc` on(`ts`.`sub_category_id` = `esc`.`id`)) left join `training_registrations` `tr` on(`ts`.`id` = `tr`.`training_id`)) GROUP BY `ts`.`id` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `academic_programs`
--
ALTER TABLE `academic_programs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `admin_activity_logs`
--
ALTER TABLE `admin_activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `action` (`action`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `admission_contacts`
--
ALTER TABLE `admission_contacts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `admission_levels`
--
ALTER TABLE `admission_levels`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `admission_programs`
--
ALTER TABLE `admission_programs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `level_id` (`level_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `admission_requirements`
--
ALTER TABLE `admission_requirements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `level_id` (`level_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `board_directors`
--
ALTER TABLE `board_directors`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `carousel_slides`
--
ALTER TABLE `carousel_slides`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `fk_carousel_approved_by` (`approved_by`),
  ADD KEY `fk_carousel_rejected_by` (`rejected_by`);

--
-- Indexes for table `class_announcements`
--
ALTER TABLE `class_announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `priority` (`priority`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `idx_announcements_teacher_date` (`teacher_id`,`created_at`),
  ADD KEY `idx_announcements_pinned_date` (`is_pinned`,`created_at`);

--
-- Indexes for table `class_enrollments`
--
ALTER TABLE `class_enrollments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_enrollment` (`class_id`,`student_id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `status` (`status`),
  ADD KEY `join_date` (`join_date`),
  ADD KEY `idx_class_enrollments_class_status` (`class_id`,`status`),
  ADD KEY `idx_class_enrollments_student_status` (`student_id`,`status`),
  ADD KEY `idx_class_enrollments_join_date` (`join_date`);

--
-- Indexes for table `class_materials`
--
ALTER TABLE `class_materials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `category` (`category`),
  ADD KEY `is_public` (`is_public`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `colleges`
--
ALTER TABLE `colleges`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `core_values`
--
ALTER TABLE `core_values`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `college_id` (`college_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `course_curriculum`
--
ALTER TABLE `course_curriculum`
  ADD PRIMARY KEY (`id`),
  ADD KEY `course_id` (`course_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_course_curriculum_prerequisite` (`prerequisite_id`);

--
-- Indexes for table `course_requirements`
--
ALTER TABLE `course_requirements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `course_id` (`course_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `department_contacts`
--
ALTER TABLE `department_contacts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `evaluation_categories`
--
ALTER TABLE `evaluation_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `status` (`status`),
  ADD KEY `idx_evaluation_categories_status_created` (`status`,`created_at`),
  ADD KEY `evaluation_type` (`evaluation_type`),
  ADD KEY `semester_id` (`semester_id`),
  ADD KEY `idx_evaluation_categories_evaluation_status` (`evaluation_status`);

--
-- Indexes for table `evaluation_questionnaires`
--
ALTER TABLE `evaluation_questionnaires`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sub_category_id` (`sub_category_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `status` (`status`),
  ADD KEY `order_number` (`order_number`),
  ADD KEY `idx_evaluation_questionnaires_sub_order` (`sub_category_id`,`order_number`,`status`);

--
-- Indexes for table `evaluation_responses`
--
ALTER TABLE `evaluation_responses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `evaluation_questionnaire` (`evaluation_session_id`,`questionnaire_id`),
  ADD KEY `questionnaire_id` (`questionnaire_id`),
  ADD KEY `rating_value` (`rating_value`),
  ADD KEY `idx_evaluation_responses_session_questionnaire` (`evaluation_session_id`,`questionnaire_id`),
  ADD KEY `idx_evaluation_responses_rating` (`rating_value`);

--
-- Indexes for table `evaluation_schedules`
--
ALTER TABLE `evaluation_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `semester_id` (`semester_id`),
  ADD KEY `evaluation_type` (`evaluation_type`),
  ADD KEY `status` (`status`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_evaluation_schedules_active` (`status`,`start_date`,`end_date`);

--
-- Indexes for table `evaluation_sessions`
--
ALTER TABLE `evaluation_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `evaluator_id` (`evaluator_id`),
  ADD KEY `evaluatee_id` (`evaluatee_id`),
  ADD KEY `main_category_id` (`main_category_id`),
  ADD KEY `semester_id` (`semester_id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `status` (`status`),
  ADD KEY `evaluation_date` (`evaluation_date`),
  ADD KEY `idx_evaluation_sessions_evaluator_type` (`evaluator_id`,`evaluator_type`),
  ADD KEY `idx_evaluation_sessions_evaluatee_type` (`evaluatee_id`,`evaluatee_type`),
  ADD KEY `idx_evaluation_sessions_main_category_status` (`main_category_id`,`status`),
  ADD KEY `idx_evaluation_sessions_evaluator` (`evaluator_id`,`evaluator_type`),
  ADD KEY `idx_evaluation_sessions_evaluatee` (`evaluatee_id`,`evaluatee_type`),
  ADD KEY `idx_evaluation_sessions_status` (`status`),
  ADD KEY `idx_evaluation_sessions_date` (`evaluation_date`);

--
-- Indexes for table `evaluation_sub_categories`
--
ALTER TABLE `evaluation_sub_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `main_category_id` (`main_category_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `status` (`status`),
  ADD KEY `order_number` (`order_number`),
  ADD KEY `idx_evaluation_sub_categories_main_order` (`main_category_id`,`order_number`,`status`);

--
-- Indexes for table `faculty`
--
ALTER TABLE `faculty`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `faculty_events`
--
ALTER TABLE `faculty_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `event_date` (`event_date`),
  ADD KEY `event_type` (`event_type`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `idx_events_teacher_date` (`teacher_id`,`event_date`);

--
-- Indexes for table `faculty_notifications`
--
ALTER TABLE `faculty_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `type` (`type`),
  ADD KEY `is_read` (`is_read`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `idx_notifications_teacher_read` (`teacher_id`,`is_read`,`created_at`);

--
-- Indexes for table `faqs`
--
ALTER TABLE `faqs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_faqs_keywords` (`keywords`(768)),
  ADD KEY `idx_faqs_category` (`category`),
  ADD KEY `idx_faqs_active` (`is_active`);

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
-- Indexes for table `head_teacher_assignments`
--
ALTER TABLE `head_teacher_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_head_teacher` (`head_id`,`teacher_id`),
  ADD KEY `head_id` (`head_id`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `status` (`status`),
  ADD KEY `assigned_date` (`assigned_date`),
  ADD KEY `idx_head_teacher_assignments_head_status` (`head_id`,`status`),
  ADD KEY `idx_head_teacher_assignments_teacher_status` (`teacher_id`,`status`),
  ADD KEY `idx_head_teacher_assignments_assigned_date` (`assigned_date`);

--
-- Indexes for table `lessons`
--
ALTER TABLE `lessons`
  ADD PRIMARY KEY (`id`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `status` (`status`),
  ADD KEY `order_number` (`order_number`),
  ADD KEY `idx_lessons_teacher_status` (`teacher_id`,`status`),
  ADD KEY `idx_lessons_type_status` (`lesson_type`,`status`);

--
-- Indexes for table `lesson_class_assignments`
--
ALTER TABLE `lesson_class_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_lesson_class` (`lesson_id`,`class_id`),
  ADD KEY `lesson_id` (`lesson_id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `idx_lesson_assignments_class` (`class_id`);

--
-- Indexes for table `lms_assignments`
--
ALTER TABLE `lms_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `due_date` (`due_date`),
  ADD KEY `status` (`status`),
  ADD KEY `idx_assignments_class_status` (`class_id`,`status`),
  ADD KEY `idx_assignments_due_date` (`due_date`);

--
-- Indexes for table `lms_assignment_categories`
--
ALTER TABLE `lms_assignment_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `lms_assignment_submissions`
--
ALTER TABLE `lms_assignment_submissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_submission` (`assignment_id`,`student_id`),
  ADD KEY `assignment_id` (`assignment_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `graded_by` (`graded_by`),
  ADD KEY `submitted_at` (`submitted_at`),
  ADD KEY `status` (`status`),
  ADD KEY `idx_submissions_assignment_student` (`assignment_id`,`student_id`),
  ADD KEY `idx_submissions_status` (`status`);

--
-- Indexes for table `lms_discussions`
--
ALTER TABLE `lms_discussions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `status` (`status`),
  ADD KEY `is_pinned` (`is_pinned`),
  ADD KEY `idx_discussions_class_status` (`class_id`,`status`);

--
-- Indexes for table `lms_discussion_posts`
--
ALTER TABLE `lms_discussion_posts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `discussion_id` (`discussion_id`),
  ADD KEY `parent_id` (`parent_id`),
  ADD KEY `author_id` (`author_id`),
  ADD KEY `author_type` (`author_type`),
  ADD KEY `status` (`status`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `fk_posts_edited_by` (`edited_by`),
  ADD KEY `idx_posts_discussion_parent` (`discussion_id`,`parent_id`),
  ADD KEY `idx_posts_author` (`author_id`,`author_type`);

--
-- Indexes for table `lms_grade_categories`
--
ALTER TABLE `lms_grade_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `lms_materials`
--
ALTER TABLE `lms_materials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `status` (`status`),
  ADD KEY `order_number` (`order_number`),
  ADD KEY `idx_materials_class_status` (`class_id`,`status`),
  ADD KEY `idx_materials_category_order` (`category_id`,`order_number`);

--
-- Indexes for table `lms_material_access_logs`
--
ALTER TABLE `lms_material_access_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `material_id` (`material_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `access_time` (`access_time`),
  ADD KEY `idx_material_access_student` (`student_id`,`access_time`);

--
-- Indexes for table `lms_material_categories`
--
ALTER TABLE `lms_material_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `lms_notifications`
--
ALTER TABLE `lms_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `recipient_id` (`recipient_id`),
  ADD KEY `recipient_type` (`recipient_type`),
  ADD KEY `type` (`type`),
  ADD KEY `is_read` (`is_read`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `idx_notifications_recipient` (`recipient_id`,`recipient_type`),
  ADD KEY `idx_notifications_unread` (`is_read`,`created_at`);

--
-- Indexes for table `lms_post_reactions`
--
ALTER TABLE `lms_post_reactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_reaction` (`post_id`,`user_id`,`user_type`),
  ADD KEY `post_id` (`post_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `reaction_type` (`reaction_type`),
  ADD KEY `idx_reactions_post_type` (`post_id`,`reaction_type`);

--
-- Indexes for table `lms_resources`
--
ALTER TABLE `lms_resources`
  ADD PRIMARY KEY (`id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `status` (`status`),
  ADD KEY `order_number` (`order_number`),
  ADD KEY `idx_resources_class_status` (`class_id`,`status`),
  ADD KEY `idx_resources_category_order` (`category_id`,`order_number`);

--
-- Indexes for table `lms_resource_categories`
--
ALTER TABLE `lms_resource_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `lms_student_grades`
--
ALTER TABLE `lms_student_grades`
  ADD PRIMARY KEY (`id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `assignment_id` (`assignment_id`),
  ADD KEY `graded_by` (`graded_by`),
  ADD KEY `status` (`status`),
  ADD KEY `graded_at` (`graded_at`),
  ADD KEY `idx_grades_class_student` (`class_id`,`student_id`),
  ADD KEY `idx_grades_category` (`category_id`),
  ADD KEY `idx_grades_status` (`status`);

--
-- Indexes for table `lms_student_progress`
--
ALTER TABLE `lms_student_progress`
  ADD PRIMARY KEY (`id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `material_id` (`material_id`),
  ADD KEY `assignment_id` (`assignment_id`),
  ADD KEY `discussion_id` (`discussion_id`),
  ADD KEY `activity_type` (`activity_type`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `idx_progress_class_student` (`class_id`,`student_id`),
  ADD KEY `idx_progress_activity` (`activity_type`,`created_at`);

--
-- Indexes for table `main_evaluation_categories`
--
ALTER TABLE `main_evaluation_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `status` (`status`),
  ADD KEY `idx_main_evaluation_categories_type_status` (`evaluation_type`,`status`);

--
-- Indexes for table `mission_vision`
--
ALTER TABLE `mission_vision`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `posts`
--
ALTER TABLE `posts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `author_id` (`author_id`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `rejected_by` (`rejected_by`),
  ADD KEY `idx_posts_status` (`status`),
  ADD KEY `idx_posts_created_at` (`created_at`),
  ADD KEY `idx_posts_updated_at` (`updated_at`);

--
-- Indexes for table `publications`
--
ALTER TABLE `publications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `research_category_id` (`research_category_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `publication_authors`
--
ALTER TABLE `publication_authors`
  ADD PRIMARY KEY (`id`),
  ADD KEY `publication_id` (`publication_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `questionnaires`
--
ALTER TABLE `questionnaires`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `status` (`status`),
  ADD KEY `order_number` (`order_number`),
  ADD KEY `idx_questionnaires_category_order` (`category_id`,`order_number`,`status`);

--
-- Indexes for table `quizzes`
--
ALTER TABLE `quizzes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `lesson_id` (`lesson_id`),
  ADD KEY `status` (`status`),
  ADD KEY `idx_quizzes_teacher_status` (`teacher_id`,`status`);

--
-- Indexes for table `quiz_answers`
--
ALTER TABLE `quiz_answers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `question_id` (`question_id`),
  ADD KEY `is_correct` (`is_correct`),
  ADD KEY `order_number` (`order_number`);

--
-- Indexes for table `quiz_class_assignments`
--
ALTER TABLE `quiz_class_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_quiz_class` (`quiz_id`,`class_id`),
  ADD KEY `quiz_id` (`quiz_id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `idx_quiz_assignments_quiz` (`quiz_id`),
  ADD KEY `idx_quiz_assignments_class` (`class_id`),
  ADD KEY `idx_due_date` (`due_date`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_assigned_by` (`assigned_by`),
  ADD KEY `idx_quiz_assignments_due_date` (`due_date`,`status`);

--
-- Indexes for table `quiz_questions`
--
ALTER TABLE `quiz_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `quiz_id` (`quiz_id`),
  ADD KEY `order_number` (`order_number`),
  ADD KEY `idx_quiz_questions_quiz_order` (`quiz_id`,`order_number`);

--
-- Indexes for table `quiz_question_options`
--
ALTER TABLE `quiz_question_options`
  ADD PRIMARY KEY (`id`),
  ADD KEY `question_id` (`question_id`),
  ADD KEY `option_order` (`option_order`);

--
-- Indexes for table `quiz_submissions`
--
ALTER TABLE `quiz_submissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assignment_id` (`assignment_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `status` (`status`),
  ADD KEY `score` (`score`),
  ADD KEY `start_time` (`start_time`),
  ADD KEY `end_time` (`end_time`),
  ADD KEY `idx_quiz_submissions_assignment_student` (`assignment_id`,`student_id`),
  ADD KEY `idx_quiz_submissions_score` (`score`);

--
-- Indexes for table `quiz_submission_answers`
--
ALTER TABLE `quiz_submission_answers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `submission_id` (`submission_id`),
  ADD KEY `question_id` (`question_id`),
  ADD KEY `selected_option_id` (`selected_option_id`),
  ADD KEY `is_correct` (`is_correct`);

--
-- Indexes for table `research_categories`
--
ALTER TABLE `research_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `semesters`
--
ALTER TABLE `semesters`
  ADD PRIMARY KEY (`id`),
  ADD KEY `academic_year` (`academic_year`),
  ADD KEY `status` (`status`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `service_categories`
--
ALTER TABLE `service_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `service_details`
--
ALTER TABLE `service_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `service_id` (`service_id`);

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
-- Indexes for table `student_academic_info`
--
ALTER TABLE `student_academic_info`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_id` (`student_id`),
  ADD KEY `program_id` (`program_id`),
  ADD KEY `academic_status` (`academic_status`);

--
-- Indexes for table `student_documents`
--
ALTER TABLE `student_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `document_type` (`document_type`),
  ADD KEY `status` (`status`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Indexes for table `student_enrollments`
--
ALTER TABLE `student_enrollments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_enrollment` (`student_id`,`teacher_subject_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `teacher_subject_id` (`teacher_subject_id`),
  ADD KEY `status` (`status`),
  ADD KEY `idx_student_enrollments_status` (`status`);

--
-- Indexes for table `student_evaluations`
--
ALTER TABLE `student_evaluations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `evaluated_by` (`evaluated_by`),
  ADD KEY `status` (`status`),
  ADD KEY `evaluation_date` (`evaluation_date`),
  ADD KEY `idx_student_evaluations_student_date` (`student_id`,`evaluation_date`);

--
-- Indexes for table `student_login_history`
--
ALTER TABLE `student_login_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `login_time` (`login_time`),
  ADD KEY `success` (`success`);

--
-- Indexes for table `student_notifications`
--
ALTER TABLE `student_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `read` (`read`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `student_password_resets`
--
ALTER TABLE `student_password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `expires_at` (`expires_at`),
  ADD KEY `used` (`used`);

--
-- Indexes for table `student_profiles`
--
ALTER TABLE `student_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_id` (`student_id`);

--
-- Indexes for table `student_registration_logs`
--
ALTER TABLE `student_registration_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `registration_method` (`registration_method`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `department` (`department`),
  ADD KEY `status` (`status`),
  ADD KEY `idx_teachers_department_status` (`department`,`status`),
  ADD KEY `idx_teachers_position` (`position`);

--
-- Indexes for table `teacher_classes`
--
ALTER TABLE `teacher_classes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `join_code` (`join_code`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `status` (`status`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `idx_teacher_classes_join_code` (`join_code`),
  ADD KEY `idx_teacher_classes_teacher_status` (`teacher_id`,`status`);

--
-- Indexes for table `teacher_settings`
--
ALTER TABLE `teacher_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `teacher_id` (`teacher_id`),
  ADD KEY `teacher_id_2` (`teacher_id`);

--
-- Indexes for table `teacher_subjects`
--
ALTER TABLE `teacher_subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_assignment` (`teacher_id`,`subject_id`,`semester_id`,`section`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `semester_id` (`semester_id`),
  ADD KEY `status` (`status`),
  ADD KEY `idx_teacher_subjects_semester` (`semester_id`,`status`);

--
-- Indexes for table `timeline_events`
--
ALTER TABLE `timeline_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `trainings_seminars`
--
ALTER TABLE `trainings_seminars`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `main_category_id` (`main_category_id`),
  ADD KEY `sub_category_id` (`sub_category_id`),
  ADD KEY `status` (`status`),
  ADD KEY `start_date` (`start_date`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_trainings_seminars_type_status` (`type`,`status`),
  ADD KEY `idx_trainings_seminars_dates` (`start_date`,`end_date`),
  ADD KEY `idx_trainings_seminars_category_status` (`category_id`,`status`),
  ADD KEY `idx_trainings_seminars_main_category` (`main_category_id`,`status`);

--
-- Indexes for table `training_categories`
--
ALTER TABLE `training_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `training_materials`
--
ALTER TABLE `training_materials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `training_id` (`training_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `training_registrations`
--
ALTER TABLE `training_registrations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `training_user` (`training_id`,`user_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `status` (`status`),
  ADD KEY `registration_date` (`registration_date`),
  ADD KEY `idx_training_registrations_user_status` (`user_id`,`status`),
  ADD KEY `idx_training_registrations_training_status` (`training_id`,`status`),
  ADD KEY `idx_training_registrations_date` (`registration_date`);

--
-- Indexes for table `training_suggestions`
--
ALTER TABLE `training_suggestions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `training_id` (`training_id`),
  ADD KEY `evaluation_category_id` (`evaluation_category_id`),
  ADD KEY `priority_level` (`priority_level`),
  ADD KEY `status` (`status`),
  ADD KEY `suggested_by` (`suggested_by`),
  ADD KEY `idx_training_suggestions_user_status` (`user_id`,`status`),
  ADD KEY `idx_training_suggestions_priority` (`priority_level`,`status`),
  ADD KEY `idx_training_suggestions_category` (`evaluation_category_id`);

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
-- AUTO_INCREMENT for table `academic_programs`
--
ALTER TABLE `academic_programs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `admin_activity_logs`
--
ALTER TABLE `admin_activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `admission_contacts`
--
ALTER TABLE `admission_contacts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `admission_levels`
--
ALTER TABLE `admission_levels`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `admission_programs`
--
ALTER TABLE `admission_programs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `admission_requirements`
--
ALTER TABLE `admission_requirements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `board_directors`
--
ALTER TABLE `board_directors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `carousel_slides`
--
ALTER TABLE `carousel_slides`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `class_announcements`
--
ALTER TABLE `class_announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `class_enrollments`
--
ALTER TABLE `class_enrollments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `class_materials`
--
ALTER TABLE `class_materials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `colleges`
--
ALTER TABLE `colleges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `core_values`
--
ALTER TABLE `core_values`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `course_curriculum`
--
ALTER TABLE `course_curriculum`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

--
-- AUTO_INCREMENT for table `course_requirements`
--
ALTER TABLE `course_requirements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `department_contacts`
--
ALTER TABLE `department_contacts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `evaluation_categories`
--
ALTER TABLE `evaluation_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `evaluation_questionnaires`
--
ALTER TABLE `evaluation_questionnaires`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=155;

--
-- AUTO_INCREMENT for table `evaluation_responses`
--
ALTER TABLE `evaluation_responses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=176;

--
-- AUTO_INCREMENT for table `evaluation_schedules`
--
ALTER TABLE `evaluation_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `evaluation_sessions`
--
ALTER TABLE `evaluation_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=335;

--
-- AUTO_INCREMENT for table `evaluation_sub_categories`
--
ALTER TABLE `evaluation_sub_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `faculty`
--
ALTER TABLE `faculty`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `faculty_events`
--
ALTER TABLE `faculty_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `faculty_notifications`
--
ALTER TABLE `faculty_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `faqs`
--
ALTER TABLE `faqs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `heads`
--
ALTER TABLE `heads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `head_teacher_assignments`
--
ALTER TABLE `head_teacher_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `lessons`
--
ALTER TABLE `lessons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `lesson_class_assignments`
--
ALTER TABLE `lesson_class_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `lms_assignments`
--
ALTER TABLE `lms_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `lms_assignment_categories`
--
ALTER TABLE `lms_assignment_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `lms_assignment_submissions`
--
ALTER TABLE `lms_assignment_submissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lms_discussions`
--
ALTER TABLE `lms_discussions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `lms_discussion_posts`
--
ALTER TABLE `lms_discussion_posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `lms_grade_categories`
--
ALTER TABLE `lms_grade_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `lms_materials`
--
ALTER TABLE `lms_materials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `lms_material_access_logs`
--
ALTER TABLE `lms_material_access_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `lms_material_categories`
--
ALTER TABLE `lms_material_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `lms_notifications`
--
ALTER TABLE `lms_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lms_post_reactions`
--
ALTER TABLE `lms_post_reactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lms_resources`
--
ALTER TABLE `lms_resources`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `lms_resource_categories`
--
ALTER TABLE `lms_resource_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `lms_student_grades`
--
ALTER TABLE `lms_student_grades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lms_student_progress`
--
ALTER TABLE `lms_student_progress`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `main_evaluation_categories`
--
ALTER TABLE `main_evaluation_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `mission_vision`
--
ALTER TABLE `mission_vision`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `posts`
--
ALTER TABLE `posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `publications`
--
ALTER TABLE `publications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `publication_authors`
--
ALTER TABLE `publication_authors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `questionnaires`
--
ALTER TABLE `questionnaires`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `quizzes`
--
ALTER TABLE `quizzes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `quiz_answers`
--
ALTER TABLE `quiz_answers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `quiz_class_assignments`
--
ALTER TABLE `quiz_class_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `quiz_questions`
--
ALTER TABLE `quiz_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `quiz_question_options`
--
ALTER TABLE `quiz_question_options`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `quiz_submissions`
--
ALTER TABLE `quiz_submissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quiz_submission_answers`
--
ALTER TABLE `quiz_submission_answers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `research_categories`
--
ALTER TABLE `research_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `semesters`
--
ALTER TABLE `semesters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `service_categories`
--
ALTER TABLE `service_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `service_details`
--
ALTER TABLE `service_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `student_academic_info`
--
ALTER TABLE `student_academic_info`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `student_documents`
--
ALTER TABLE `student_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_enrollments`
--
ALTER TABLE `student_enrollments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_evaluations`
--
ALTER TABLE `student_evaluations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_login_history`
--
ALTER TABLE `student_login_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_notifications`
--
ALTER TABLE `student_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_password_resets`
--
ALTER TABLE `student_password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_profiles`
--
ALTER TABLE `student_profiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_registration_logs`
--
ALTER TABLE `student_registration_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `teacher_classes`
--
ALTER TABLE `teacher_classes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `teacher_settings`
--
ALTER TABLE `teacher_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `teacher_subjects`
--
ALTER TABLE `teacher_subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `timeline_events`
--
ALTER TABLE `timeline_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `trainings_seminars`
--
ALTER TABLE `trainings_seminars`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `training_categories`
--
ALTER TABLE `training_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `training_materials`
--
ALTER TABLE `training_materials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `training_registrations`
--
ALTER TABLE `training_registrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `training_suggestions`
--
ALTER TABLE `training_suggestions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `user_inquiries`
--
ALTER TABLE `user_inquiries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_activity_logs`
--
ALTER TABLE `admin_activity_logs`
  ADD CONSTRAINT `fk_activity_logs_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `admission_contacts`
--
ALTER TABLE `admission_contacts`
  ADD CONSTRAINT `admission_contacts_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `admission_levels`
--
ALTER TABLE `admission_levels`
  ADD CONSTRAINT `admission_levels_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `admission_programs`
--
ALTER TABLE `admission_programs`
  ADD CONSTRAINT `admission_programs_ibfk_1` FOREIGN KEY (`level_id`) REFERENCES `admission_levels` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `admission_programs_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `admission_requirements`
--
ALTER TABLE `admission_requirements`
  ADD CONSTRAINT `admission_requirements_ibfk_1` FOREIGN KEY (`level_id`) REFERENCES `admission_levels` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `admission_requirements_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `board_directors`
--
ALTER TABLE `board_directors`
  ADD CONSTRAINT `board_directors_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `carousel_slides`
--
ALTER TABLE `carousel_slides`
  ADD CONSTRAINT `carousel_slides_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_carousel_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_carousel_rejected_by` FOREIGN KEY (`rejected_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `class_announcements`
--
ALTER TABLE `class_announcements`
  ADD CONSTRAINT `fk_announcements_class` FOREIGN KEY (`class_id`) REFERENCES `teacher_classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_announcements_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `class_enrollments`
--
ALTER TABLE `class_enrollments`
  ADD CONSTRAINT `fk_class_enrollments_class` FOREIGN KEY (`class_id`) REFERENCES `teacher_classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_class_enrollments_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `colleges`
--
ALTER TABLE `colleges`
  ADD CONSTRAINT `colleges_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `core_values`
--
ALTER TABLE `core_values`
  ADD CONSTRAINT `core_values_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `courses`
--
ALTER TABLE `courses`
  ADD CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`college_id`) REFERENCES `colleges` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `courses_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `course_curriculum`
--
ALTER TABLE `course_curriculum`
  ADD CONSTRAINT `course_curriculum_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `course_curriculum_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `course_curriculum_ibfk_3` FOREIGN KEY (`prerequisite_id`) REFERENCES `course_curriculum` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `course_requirements`
--
ALTER TABLE `course_requirements`
  ADD CONSTRAINT `course_requirements_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `course_requirements_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `departments_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `department_contacts`
--
ALTER TABLE `department_contacts`
  ADD CONSTRAINT `department_contacts_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `department_contacts_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `evaluation_categories`
--
ALTER TABLE `evaluation_categories`
  ADD CONSTRAINT `fk_evaluation_categories_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_evaluation_categories_semester` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `evaluation_questionnaires`
--
ALTER TABLE `evaluation_questionnaires`
  ADD CONSTRAINT `fk_evaluation_questionnaires_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_evaluation_questionnaires_sub_category` FOREIGN KEY (`sub_category_id`) REFERENCES `evaluation_sub_categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `evaluation_responses`
--
ALTER TABLE `evaluation_responses`
  ADD CONSTRAINT `fk_evaluation_responses_questionnaire` FOREIGN KEY (`questionnaire_id`) REFERENCES `evaluation_questionnaires` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_evaluation_responses_session` FOREIGN KEY (`evaluation_session_id`) REFERENCES `evaluation_sessions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `evaluation_schedules`
--
ALTER TABLE `evaluation_schedules`
  ADD CONSTRAINT `fk_evaluation_schedules_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_evaluation_schedules_semester` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `evaluation_sessions`
--
ALTER TABLE `evaluation_sessions`
  ADD CONSTRAINT `fk_evaluation_sessions_main_category` FOREIGN KEY (`main_category_id`) REFERENCES `main_evaluation_categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `evaluation_sub_categories`
--
ALTER TABLE `evaluation_sub_categories`
  ADD CONSTRAINT `fk_evaluation_sub_categories_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_evaluation_sub_categories_main` FOREIGN KEY (`main_category_id`) REFERENCES `main_evaluation_categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `faculty_events`
--
ALTER TABLE `faculty_events`
  ADD CONSTRAINT `fk_events_class` FOREIGN KEY (`class_id`) REFERENCES `teacher_classes` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_events_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `faculty_notifications`
--
ALTER TABLE `faculty_notifications`
  ADD CONSTRAINT `fk_notifications_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `heads`
--
ALTER TABLE `heads`
  ADD CONSTRAINT `fk_heads_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `head_teacher_assignments`
--
ALTER TABLE `head_teacher_assignments`
  ADD CONSTRAINT `fk_head_teacher_assignments_head` FOREIGN KEY (`head_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_head_teacher_assignments_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `faculty` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lessons`
--
ALTER TABLE `lessons`
  ADD CONSTRAINT `fk_lessons_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lesson_class_assignments`
--
ALTER TABLE `lesson_class_assignments`
  ADD CONSTRAINT `fk_lesson_assignments_class` FOREIGN KEY (`class_id`) REFERENCES `teacher_classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_lesson_assignments_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lms_assignments`
--
ALTER TABLE `lms_assignments`
  ADD CONSTRAINT `fk_assignments_category` FOREIGN KEY (`category_id`) REFERENCES `lms_assignment_categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_assignments_class` FOREIGN KEY (`class_id`) REFERENCES `teacher_classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_assignments_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lms_assignment_categories`
--
ALTER TABLE `lms_assignment_categories`
  ADD CONSTRAINT `fk_assignment_categories_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lms_assignment_submissions`
--
ALTER TABLE `lms_assignment_submissions`
  ADD CONSTRAINT `fk_submissions_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `lms_assignments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_submissions_graded_by` FOREIGN KEY (`graded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_submissions_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lms_discussions`
--
ALTER TABLE `lms_discussions`
  ADD CONSTRAINT `fk_discussions_class` FOREIGN KEY (`class_id`) REFERENCES `teacher_classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_discussions_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lms_discussion_posts`
--
ALTER TABLE `lms_discussion_posts`
  ADD CONSTRAINT `fk_posts_discussion` FOREIGN KEY (`discussion_id`) REFERENCES `lms_discussions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_posts_edited_by` FOREIGN KEY (`edited_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_posts_parent` FOREIGN KEY (`parent_id`) REFERENCES `lms_discussion_posts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lms_grade_categories`
--
ALTER TABLE `lms_grade_categories`
  ADD CONSTRAINT `fk_grade_categories_class` FOREIGN KEY (`class_id`) REFERENCES `teacher_classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_grade_categories_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lms_materials`
--
ALTER TABLE `lms_materials`
  ADD CONSTRAINT `fk_materials_category` FOREIGN KEY (`category_id`) REFERENCES `lms_material_categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_materials_class` FOREIGN KEY (`class_id`) REFERENCES `teacher_classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_materials_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lms_material_access_logs`
--
ALTER TABLE `lms_material_access_logs`
  ADD CONSTRAINT `fk_material_logs_material` FOREIGN KEY (`material_id`) REFERENCES `lms_materials` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_material_logs_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lms_material_categories`
--
ALTER TABLE `lms_material_categories`
  ADD CONSTRAINT `fk_material_categories_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lms_notifications`
--
ALTER TABLE `lms_notifications`
  ADD CONSTRAINT `fk_notifications_class` FOREIGN KEY (`class_id`) REFERENCES `teacher_classes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lms_post_reactions`
--
ALTER TABLE `lms_post_reactions`
  ADD CONSTRAINT `fk_reactions_post` FOREIGN KEY (`post_id`) REFERENCES `lms_discussion_posts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lms_resources`
--
ALTER TABLE `lms_resources`
  ADD CONSTRAINT `fk_resources_category` FOREIGN KEY (`category_id`) REFERENCES `lms_resource_categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_resources_class` FOREIGN KEY (`class_id`) REFERENCES `teacher_classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_resources_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lms_resource_categories`
--
ALTER TABLE `lms_resource_categories`
  ADD CONSTRAINT `fk_resource_categories_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lms_student_grades`
--
ALTER TABLE `lms_student_grades`
  ADD CONSTRAINT `fk_grades_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `lms_assignments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_grades_category` FOREIGN KEY (`category_id`) REFERENCES `lms_grade_categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_grades_class` FOREIGN KEY (`class_id`) REFERENCES `teacher_classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_grades_graded_by` FOREIGN KEY (`graded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_grades_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lms_student_progress`
--
ALTER TABLE `lms_student_progress`
  ADD CONSTRAINT `fk_progress_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `lms_assignments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_progress_class` FOREIGN KEY (`class_id`) REFERENCES `teacher_classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_progress_discussion` FOREIGN KEY (`discussion_id`) REFERENCES `lms_discussions` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_progress_material` FOREIGN KEY (`material_id`) REFERENCES `lms_materials` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_progress_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `main_evaluation_categories`
--
ALTER TABLE `main_evaluation_categories`
  ADD CONSTRAINT `fk_main_evaluation_categories_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `mission_vision`
--
ALTER TABLE `mission_vision`
  ADD CONSTRAINT `mission_vision_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `posts`
--
ALTER TABLE `posts`
  ADD CONSTRAINT `posts_ibfk_1` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `posts_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `posts_ibfk_3` FOREIGN KEY (`rejected_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `publications`
--
ALTER TABLE `publications`
  ADD CONSTRAINT `publications_ibfk_1` FOREIGN KEY (`research_category_id`) REFERENCES `research_categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `publications_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `publication_authors`
--
ALTER TABLE `publication_authors`
  ADD CONSTRAINT `publication_authors_ibfk_1` FOREIGN KEY (`publication_id`) REFERENCES `publications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `publication_authors_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `questionnaires`
--
ALTER TABLE `questionnaires`
  ADD CONSTRAINT `fk_questionnaires_category` FOREIGN KEY (`category_id`) REFERENCES `evaluation_categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_questionnaires_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `quizzes`
--
ALTER TABLE `quizzes`
  ADD CONSTRAINT `fk_quizzes_class` FOREIGN KEY (`class_id`) REFERENCES `teacher_classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_quizzes_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_quizzes_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `quiz_answers`
--
ALTER TABLE `quiz_answers`
  ADD CONSTRAINT `fk_quiz_answers_question` FOREIGN KEY (`question_id`) REFERENCES `quiz_questions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `quiz_class_assignments`
--
ALTER TABLE `quiz_class_assignments`
  ADD CONSTRAINT `fk_quiz_assignments_class` FOREIGN KEY (`class_id`) REFERENCES `teacher_classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_quiz_assignments_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_quiz_assignments_teacher` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `quiz_questions`
--
ALTER TABLE `quiz_questions`
  ADD CONSTRAINT `fk_quiz_questions_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `quiz_question_options`
--
ALTER TABLE `quiz_question_options`
  ADD CONSTRAINT `fk_quiz_options_question` FOREIGN KEY (`question_id`) REFERENCES `quiz_questions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `quiz_submissions`
--
ALTER TABLE `quiz_submissions`
  ADD CONSTRAINT `fk_quiz_submissions_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `quiz_class_assignments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_quiz_submissions_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `quiz_submission_answers`
--
ALTER TABLE `quiz_submission_answers`
  ADD CONSTRAINT `fk_submission_answers_option` FOREIGN KEY (`selected_option_id`) REFERENCES `quiz_question_options` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_submission_answers_question` FOREIGN KEY (`question_id`) REFERENCES `quiz_questions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_submission_answers_submission` FOREIGN KEY (`submission_id`) REFERENCES `quiz_submissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `research_categories`
--
ALTER TABLE `research_categories`
  ADD CONSTRAINT `research_categories_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `semesters`
--
ALTER TABLE `semesters`
  ADD CONSTRAINT `fk_semesters_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `services`
--
ALTER TABLE `services`
  ADD CONSTRAINT `services_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `service_categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `service_details`
--
ALTER TABLE `service_details`
  ADD CONSTRAINT `service_details_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_academic_info`
--
ALTER TABLE `student_academic_info`
  ADD CONSTRAINT `fk_academic_info_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_documents`
--
ALTER TABLE `student_documents`
  ADD CONSTRAINT `fk_documents_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_documents_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `student_enrollments`
--
ALTER TABLE `student_enrollments`
  ADD CONSTRAINT `fk_student_enrollments_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_student_enrollments_teacher_subject` FOREIGN KEY (`teacher_subject_id`) REFERENCES `teacher_subjects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_evaluations`
--
ALTER TABLE `student_evaluations`
  ADD CONSTRAINT `fk_student_evaluations_category` FOREIGN KEY (`category_id`) REFERENCES `evaluation_categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_student_evaluations_evaluated_by` FOREIGN KEY (`evaluated_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_student_evaluations_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_login_history`
--
ALTER TABLE `student_login_history`
  ADD CONSTRAINT `fk_login_history_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_notifications`
--
ALTER TABLE `student_notifications`
  ADD CONSTRAINT `fk_notifications_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_password_resets`
--
ALTER TABLE `student_password_resets`
  ADD CONSTRAINT `fk_password_resets_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_profiles`
--
ALTER TABLE `student_profiles`
  ADD CONSTRAINT `fk_student_profiles_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_registration_logs`
--
ALTER TABLE `student_registration_logs`
  ADD CONSTRAINT `fk_registration_logs_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_registration_logs_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `subjects`
--
ALTER TABLE `subjects`
  ADD CONSTRAINT `fk_subjects_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `teachers`
--
ALTER TABLE `teachers`
  ADD CONSTRAINT `fk_teachers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `teacher_classes`
--
ALTER TABLE `teacher_classes`
  ADD CONSTRAINT `fk_teacher_classes_subject` FOREIGN KEY (`subject_id`) REFERENCES `course_curriculum` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_teacher_classes_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `faculty` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `teacher_settings`
--
ALTER TABLE `teacher_settings`
  ADD CONSTRAINT `fk_teacher_settings_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `teacher_subjects`
--
ALTER TABLE `teacher_subjects`
  ADD CONSTRAINT `fk_teacher_subjects_semester` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_teacher_subjects_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_teacher_subjects_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `timeline_events`
--
ALTER TABLE `timeline_events`
  ADD CONSTRAINT `timeline_events_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `trainings_seminars`
--
ALTER TABLE `trainings_seminars`
  ADD CONSTRAINT `fk_trainings_seminars_category` FOREIGN KEY (`category_id`) REFERENCES `training_categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_trainings_seminars_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_trainings_seminars_main_category` FOREIGN KEY (`main_category_id`) REFERENCES `main_evaluation_categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_trainings_seminars_sub_category` FOREIGN KEY (`sub_category_id`) REFERENCES `evaluation_sub_categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `training_categories`
--
ALTER TABLE `training_categories`
  ADD CONSTRAINT `fk_training_categories_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `training_materials`
--
ALTER TABLE `training_materials`
  ADD CONSTRAINT `fk_training_materials_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_training_materials_training` FOREIGN KEY (`training_id`) REFERENCES `trainings_seminars` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `training_registrations`
--
ALTER TABLE `training_registrations`
  ADD CONSTRAINT `fk_training_registrations_training` FOREIGN KEY (`training_id`) REFERENCES `trainings_seminars` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_training_registrations_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `training_suggestions`
--
ALTER TABLE `training_suggestions`
  ADD CONSTRAINT `fk_training_suggestions_category` FOREIGN KEY (`evaluation_category_id`) REFERENCES `evaluation_sub_categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_training_suggestions_suggested_by` FOREIGN KEY (`suggested_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_training_suggestions_training` FOREIGN KEY (`training_id`) REFERENCES `trainings_seminars` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_training_suggestions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
