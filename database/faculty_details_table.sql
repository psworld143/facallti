-- Faculty Details Table
-- Stores comprehensive HR information for faculty members
-- Related to the main faculty table via foreign key

CREATE TABLE `faculty_details` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `faculty_id` int(11) NOT NULL COMMENT 'Foreign key to faculty table',
  `middle_name` varchar(50) DEFAULT NULL COMMENT 'Middle name of the faculty member',
  `date_of_birth` date DEFAULT NULL COMMENT 'Date of birth of the faculty member',
  `gender` enum('Male','Female','Other') DEFAULT NULL COMMENT 'Gender of the faculty member',
  `civil_status` enum('Single','Married','Widowed','Divorced','Separated') DEFAULT NULL COMMENT 'Civil status of the faculty member',
  `nationality` varchar(50) DEFAULT NULL COMMENT 'Nationality of the faculty member',
  `religion` varchar(100) DEFAULT NULL COMMENT 'Religion of the faculty member',
  `phone` varchar(20) DEFAULT NULL COMMENT 'Phone number of the faculty member',
  `emergency_contact_name` varchar(100) DEFAULT NULL COMMENT 'Name of emergency contact person',
  `emergency_contact_number` varchar(20) DEFAULT NULL COMMENT 'Phone number of emergency contact',
  `address` text DEFAULT NULL COMMENT 'Complete address of the faculty member',
  `employee_id` varchar(50) DEFAULT NULL COMMENT 'Employee ID number',
  `date_of_hire` date DEFAULT NULL COMMENT 'Date when faculty member was hired',
  `employment_type` enum('Full-time','Part-time','Contract','Temporary','Probationary') DEFAULT NULL COMMENT 'Type of employment',
  `basic_salary` decimal(10,2) DEFAULT NULL COMMENT 'Basic salary amount',
  `salary_grade` varchar(20) DEFAULT NULL COMMENT 'Salary grade level',
  `allowances` decimal(10,2) DEFAULT NULL COMMENT 'Additional allowances amount',
  `pay_schedule` enum('Monthly','Bi-weekly','Weekly') DEFAULT NULL COMMENT 'Payment schedule',
  `highest_education` enum('High School','Associate Degree','Bachelor\'s Degree','Master\'s Degree','Doctorate','Post-Doctorate') DEFAULT NULL COMMENT 'Highest educational attainment',
  `field_of_study` varchar(100) DEFAULT NULL COMMENT 'Field of study or specialization',
  `school_university` varchar(200) DEFAULT NULL COMMENT 'School or university attended',
  `year_graduated` int(4) DEFAULT NULL COMMENT 'Year of graduation',
  `tin_number` varchar(20) DEFAULT NULL COMMENT 'Tax Identification Number',
  `sss_number` varchar(20) DEFAULT NULL COMMENT 'Social Security System number',
  `philhealth_number` varchar(20) DEFAULT NULL COMMENT 'PhilHealth number',
  `pagibig_number` varchar(20) DEFAULT NULL COMMENT 'PAG-IBIG number',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Record creation timestamp',
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'Record update timestamp',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_faculty_id` (`faculty_id`) COMMENT 'One detail record per faculty member',
  KEY `idx_employee_id` (`employee_id`) COMMENT 'Index for employee ID lookups',
  KEY `idx_employment_type` (`employment_type`) COMMENT 'Index for employment type filtering',
  KEY `idx_date_of_hire` (`date_of_hire`) COMMENT 'Index for hire date queries',
  KEY `idx_created_at` (`created_at`) COMMENT 'Index for creation date queries',
  CONSTRAINT `fk_faculty_details_faculty` FOREIGN KEY (`faculty_id`) REFERENCES `faculty` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Comprehensive HR details for faculty members';

-- Insert sample data for existing faculty members (optional)
-- This can be used to populate details for existing faculty if needed
-- INSERT INTO faculty_details (faculty_id, employee_id, employment_type, date_of_hire) 
-- SELECT id, CONCAT('EMP', LPAD(id, 4, '0')), 'Full-time', created_at 
-- FROM faculty WHERE id NOT IN (SELECT faculty_id FROM faculty_details);
