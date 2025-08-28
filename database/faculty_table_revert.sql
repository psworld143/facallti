-- Faculty Table Revert Migration
-- Remove all added columns to revert back to original structure

-- Remove all the added columns (using IF EXISTS to avoid errors)
ALTER TABLE `faculty` 
DROP COLUMN IF EXISTS `middle_name`,
DROP COLUMN IF EXISTS `date_of_birth`,
DROP COLUMN IF EXISTS `gender`,
DROP COLUMN IF EXISTS `civil_status`,
DROP COLUMN IF EXISTS `nationality`,
DROP COLUMN IF EXISTS `religion`,
DROP COLUMN IF EXISTS `phone`,
DROP COLUMN IF EXISTS `emergency_contact_name`,
DROP COLUMN IF EXISTS `emergency_contact_number`,
DROP COLUMN IF EXISTS `address`,
DROP COLUMN IF EXISTS `employee_id`,
DROP COLUMN IF EXISTS `date_of_hire`,
DROP COLUMN IF EXISTS `employment_type`,
DROP COLUMN IF EXISTS `basic_salary`,
DROP COLUMN IF EXISTS `salary_grade`,
DROP COLUMN IF EXISTS `allowances`,
DROP COLUMN IF EXISTS `pay_schedule`,
DROP COLUMN IF EXISTS `highest_education`,
DROP COLUMN IF EXISTS `field_of_study`,
DROP COLUMN IF EXISTS `school_university`,
DROP COLUMN IF EXISTS `year_graduated`,
DROP COLUMN IF EXISTS `tin_number`,
DROP COLUMN IF EXISTS `sss_number`,
DROP COLUMN IF EXISTS `philhealth_number`,
DROP COLUMN IF EXISTS `pagibig_number`;

-- Remove the added indexes (only if they exist)
DROP INDEX IF EXISTS `idx_employee_id` ON `faculty`;
DROP INDEX IF EXISTS `idx_employment_type` ON `faculty`;

-- Note: idx_department and idx_is_active will remain as they might be needed for performance
