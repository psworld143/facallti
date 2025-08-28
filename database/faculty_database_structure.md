# Faculty Database Structure

## Overview
The faculty system now uses a two-table structure with a foreign key relationship to separate basic faculty information from comprehensive HR details.

## Database Tables

### 1. `faculty` (Main Table)
**Purpose**: Stores basic faculty information used across the system

**Fields**:
- `id` (int, PK, auto_increment) - Primary key
- `first_name` (varchar(50), NOT NULL) - First name
- `last_name` (varchar(50), NOT NULL) - Last name
- `email` (varchar(100), NOT NULL, UNIQUE) - Email address
- `password` (varchar(255)) - Password hash (for login)
- `position` (varchar(100), NOT NULL) - Job position/title
- `department` (varchar(100)) - Department/college
- `bio` (text) - Biography
- `image_url` (varchar(255)) - Profile image path
- `is_active` (tinyint(1), DEFAULT 1) - Active status
- `created_at` (timestamp, DEFAULT current_timestamp) - Creation date

**Indexes**:
- PRIMARY KEY on `id`
- UNIQUE KEY on `email`
- INDEX on `department`
- INDEX on `is_active`

### 2. `faculty_details` (Details Table)
**Purpose**: Stores comprehensive HR information for faculty members

**Fields**:
- `id` (int, PK, auto_increment) - Primary key
- `faculty_id` (int, NOT NULL, UNIQUE) - Foreign key to faculty table
- `middle_name` (varchar(50)) - Middle name
- `date_of_birth` (date) - Date of birth
- `gender` (enum: 'Male','Female','Other') - Gender
- `civil_status` (enum: 'Single','Married','Widowed','Divorced','Separated') - Civil status
- `nationality` (varchar(50)) - Nationality
- `religion` (varchar(100)) - Religion
- `phone` (varchar(20)) - Phone number
- `emergency_contact_name` (varchar(100)) - Emergency contact name
- `emergency_contact_number` (varchar(20)) - Emergency contact number
- `address` (text) - Complete address
- `employee_id` (varchar(50)) - Employee ID
- `date_of_hire` (date) - Date of hire
- `employment_type` (enum: 'Full-time','Part-time','Contract','Temporary','Probationary') - Employment type
- `basic_salary` (decimal(10,2)) - Basic salary
- `salary_grade` (varchar(20)) - Salary grade
- `allowances` (decimal(10,2)) - Allowances
- `pay_schedule` (enum: 'Monthly','Bi-weekly','Weekly') - Pay schedule
- `highest_education` (enum: 'High School','Associate Degree','Bachelor's Degree','Master's Degree','Doctorate','Post-Doctorate') - Highest education
- `field_of_study` (varchar(100)) - Field of study
- `school_university` (varchar(200)) - School/university
- `year_graduated` (int(4)) - Year graduated
- `tin_number` (varchar(20)) - TIN number
- `sss_number` (varchar(20)) - SSS number
- `philhealth_number` (varchar(20)) - PhilHealth number
- `pagibig_number` (varchar(20)) - PAG-IBIG number
- `created_at` (timestamp, DEFAULT current_timestamp) - Creation date
- `updated_at` (timestamp, ON UPDATE current_timestamp) - Update date

**Indexes**:
- PRIMARY KEY on `id`
- UNIQUE KEY on `faculty_id`
- INDEX on `employee_id`
- INDEX on `employment_type`
- INDEX on `date_of_hire`
- INDEX on `created_at`

**Foreign Key**:
- `fk_faculty_details_faculty` on `faculty_id` REFERENCES `faculty(id)` ON DELETE CASCADE ON UPDATE CASCADE

## Relationship
- **One-to-One**: Each faculty member can have one detail record
- **Foreign Key**: `faculty_details.faculty_id` → `faculty.id`
- **Cascade**: If a faculty member is deleted, their details are automatically deleted

## Benefits of This Structure

### 1. **Separation of Concerns**
- Basic faculty info (name, email, position) is separate from detailed HR info
- Basic info can be used across the system without loading unnecessary details
- HR details are only loaded when needed

### 2. **Performance**
- Faster queries when only basic faculty info is needed
- Reduced memory usage for faculty listings
- Optimized indexes for common queries

### 3. **Flexibility**
- HR details can be updated independently
- New HR fields can be added without affecting the main faculty table
- Backward compatibility with existing faculty data

### 4. **Data Integrity**
- Foreign key ensures referential integrity
- Cascade delete ensures no orphaned detail records
- Unique constraint ensures one detail record per faculty member

## Usage Examples

### Adding a New Faculty Member
```php
// 1. Insert into faculty table
INSERT INTO faculty (first_name, last_name, email, position, department) 
VALUES ('John', 'Doe', 'john@example.com', 'Professor', 'Computer Science');

// 2. Get the faculty ID
$faculty_id = mysqli_insert_id($conn);

// 3. Insert into faculty_details table
INSERT INTO faculty_details (faculty_id, employee_id, date_of_hire, employment_type, ...)
VALUES ($faculty_id, 'EMP001', '2024-01-15', 'Full-time', ...);
```

### Retrieving Faculty with Details
```sql
SELECT f.*, fd.*
FROM faculty f
LEFT JOIN faculty_details fd ON f.id = fd.faculty_id
WHERE f.id = ?
```

### Retrieving Only Basic Faculty Info
```sql
SELECT id, first_name, last_name, email, position, department
FROM faculty
WHERE is_active = 1
ORDER BY last_name, first_name;
```

## Files Created/Modified

### Database Files
- `database/faculty_details_table.sql` - Creates the faculty_details table
- `database/faculty_table_revert.sql` - Reverts faculty table changes
- `database/faculty_database_structure.md` - This documentation

### PHP Files
- `human-resource/add-faculty.php` - Updated to handle both tables with transactions
- `human-resource/get-faculty-with-details.php` - New file to retrieve faculty with details

## Current Status
✅ **Database Structure**: Complete with proper foreign key relationship  
✅ **Backend Logic**: Updated to handle both tables with transaction safety  
✅ **Data Integrity**: Foreign key constraints and cascade rules in place  
✅ **Performance**: Optimized with appropriate indexes  
✅ **Documentation**: Complete with usage examples  

The system is now ready to handle comprehensive faculty information while maintaining performance and data integrity.
