-- Faculty Table Migration
-- Add missing fields to match the comprehensive faculty form

-- Add new columns to the faculty table
ALTER TABLE `faculty` 
ADD COLUMN `middle_name` varchar(50) DEFAULT NULL AFTER `last_name`,
ADD COLUMN `date_of_birth` date DEFAULT NULL AFTER `middle_name`,
ADD COLUMN `gender` enum('Male','Female','Other') DEFAULT NULL AFTER `date_of_birth`,
ADD COLUMN `civil_status` enum('Single','Married','Widowed','Divorced','Separated') DEFAULT NULL AFTER `gender`,
ADD COLUMN `nationality` varchar(50) DEFAULT NULL AFTER `civil_status`,
ADD COLUMN `religion` varchar(100) DEFAULT NULL AFTER `nationality`,
ADD COLUMN `phone` varchar(20) DEFAULT NULL AFTER `email`,
ADD COLUMN `emergency_contact_name` varchar(100) DEFAULT NULL AFTER `phone`,
ADD COLUMN `emergency_contact_number` varchar(20) DEFAULT NULL AFTER `emergency_contact_name`,
ADD COLUMN `address` text DEFAULT NULL AFTER `emergency_contact_number`,
ADD COLUMN `employee_id` varchar(50) DEFAULT NULL AFTER `address`,
ADD COLUMN `date_of_hire` date DEFAULT NULL AFTER `employee_id`,
ADD COLUMN `employment_type` enum('Full-time','Part-time','Contract','Temporary','Probationary') DEFAULT NULL AFTER `date_of_hire`,
ADD COLUMN `basic_salary` decimal(10,2) DEFAULT NULL AFTER `employment_type`,
ADD COLUMN `salary_grade` varchar(20) DEFAULT NULL AFTER `basic_salary`,
ADD COLUMN `allowances` decimal(10,2) DEFAULT NULL AFTER `salary_grade`,
ADD COLUMN `pay_schedule` enum('Monthly','Bi-weekly','Weekly') DEFAULT NULL AFTER `allowances`,
ADD COLUMN `highest_education` enum('High School','Associate Degree','Bachelor\'s Degree','Master\'s Degree','Doctorate','Post-Doctorate') DEFAULT NULL AFTER `pay_schedule`,
ADD COLUMN `field_of_study` varchar(100) DEFAULT NULL AFTER `highest_education`,
ADD COLUMN `school_university` varchar(200) DEFAULT NULL AFTER `field_of_study`,
ADD COLUMN `year_graduated` int(4) DEFAULT NULL AFTER `school_university`,
ADD COLUMN `tin_number` varchar(20) DEFAULT NULL AFTER `year_graduated`,
ADD COLUMN `sss_number` varchar(20) DEFAULT NULL AFTER `tin_number`,
ADD COLUMN `philhealth_number` varchar(20) DEFAULT NULL AFTER `sss_number`,
ADD COLUMN `pagibig_number` varchar(20) DEFAULT NULL AFTER `philhealth_number`;

-- Add indexes for better performance
ALTER TABLE `faculty` 
ADD INDEX `idx_employee_id` (`employee_id`),
ADD INDEX `idx_department` (`department`),
ADD INDEX `idx_employment_type` (`employment_type`),
ADD INDEX `idx_is_active` (`is_active`);

-- Add comments to document the new fields
ALTER TABLE `faculty` 
MODIFY COLUMN `middle_name` varchar(50) DEFAULT NULL COMMENT 'Middle name of the faculty member',
MODIFY COLUMN `date_of_birth` date DEFAULT NULL COMMENT 'Date of birth of the faculty member',
MODIFY COLUMN `gender` enum('Male','Female','Other') DEFAULT NULL COMMENT 'Gender of the faculty member',
MODIFY COLUMN `civil_status` enum('Single','Married','Widowed','Divorced','Separated') DEFAULT NULL COMMENT 'Civil status of the faculty member',
MODIFY COLUMN `nationality` varchar(50) DEFAULT NULL COMMENT 'Nationality of the faculty member',
MODIFY COLUMN `religion` varchar(100) DEFAULT NULL COMMENT 'Religion of the faculty member',
MODIFY COLUMN `phone` varchar(20) DEFAULT NULL COMMENT 'Phone number of the faculty member',
MODIFY COLUMN `emergency_contact_name` varchar(100) DEFAULT NULL COMMENT 'Name of emergency contact person',
MODIFY COLUMN `emergency_contact_number` varchar(20) DEFAULT NULL COMMENT 'Phone number of emergency contact',
MODIFY COLUMN `address` text DEFAULT NULL COMMENT 'Complete address of the faculty member',
MODIFY COLUMN `employee_id` varchar(50) DEFAULT NULL COMMENT 'Employee ID number',
MODIFY COLUMN `date_of_hire` date DEFAULT NULL COMMENT 'Date when faculty member was hired',
MODIFY COLUMN `employment_type` enum('Full-time','Part-time','Contract','Temporary','Probationary') DEFAULT NULL COMMENT 'Type of employment',
MODIFY COLUMN `basic_salary` decimal(10,2) DEFAULT NULL COMMENT 'Basic salary amount',
MODIFY COLUMN `salary_grade` varchar(20) DEFAULT NULL COMMENT 'Salary grade level',
MODIFY COLUMN `allowances` decimal(10,2) DEFAULT NULL COMMENT 'Additional allowances amount',
MODIFY COLUMN `pay_schedule` enum('Monthly','Bi-weekly','Weekly') DEFAULT NULL COMMENT 'Payment schedule',
MODIFY COLUMN `highest_education` enum('High School','Associate Degree','Bachelor\'s Degree','Master\'s Degree','Doctorate','Post-Doctorate') DEFAULT NULL COMMENT 'Highest educational attainment',
MODIFY COLUMN `field_of_study` varchar(100) DEFAULT NULL COMMENT 'Field of study or specialization',
MODIFY COLUMN `school_university` varchar(200) DEFAULT NULL COMMENT 'School or university attended',
MODIFY COLUMN `year_graduated` int(4) DEFAULT NULL COMMENT 'Year of graduation',
MODIFY COLUMN `tin_number` varchar(20) DEFAULT NULL COMMENT 'Tax Identification Number',
MODIFY COLUMN `sss_number` varchar(20) DEFAULT NULL COMMENT 'Social Security System number',
MODIFY COLUMN `philhealth_number` varchar(20) DEFAULT NULL COMMENT 'PhilHealth number',
MODIFY COLUMN `pagibig_number` varchar(20) DEFAULT NULL COMMENT 'PAG-IBIG number';

-- Update table comment
ALTER TABLE `faculty` COMMENT = 'Faculty members with comprehensive HR information';
