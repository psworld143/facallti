# Student Module

A comprehensive student portal for class management and teacher evaluation system.

## Overview

The Student Module provides students with the ability to:
- Join classes using unique join codes provided by teachers
- View and manage their enrolled classes
- Evaluate teachers from their enrolled classes
- View their evaluation history
- Manage their profile information

## Features

### 🎓 Class Management
- **Join Classes**: Students can join teacher-created classes using 8-character join codes
- **View Enrolled Classes**: See all classes they're enrolled in with teacher and subject details
- **Drop Classes**: Remove themselves from classes they no longer want to attend
- **Class Statistics**: View enrollment status and class information

### 📝 Teacher Evaluation
- **Evaluate Teachers**: Provide feedback for teachers from enrolled classes
- **Multiple Categories**: Choose from different evaluation categories (Teaching Effectiveness, Classroom Management, etc.)
- **Evaluation History**: View all completed and draft evaluations
- **Integration**: Seamless integration with the IntelliEVal evaluation system

### 👤 Profile Management
- **Personal Information**: Update name, email, contact details
- **Address Information**: Manage address, city, state, country
- **Emergency Contact**: Set up emergency contact information
- **Password Management**: Change account password securely

### 📊 Dashboard
- **Statistics Overview**: View total classes, completed evaluations, pending evaluations
- **Recent Activities**: See recent enrollments and evaluations
- **Quick Actions**: Easy access to join classes and evaluate teachers
- **Evaluation Opportunities**: View classes where evaluation is available

## File Structure

```
students/
├── includes/
│   ├── header.php          # Shared header with sidebar navigation
│   └── footer.php          # Shared footer
├── database/
│   └── class_enrollments_table.sql  # Database schema for enrollments
├── dashboard.php           # Student dashboard with overview
├── join-class.php          # Join classes using join codes
├── my-classes.php          # View and manage enrolled classes
├── evaluate-teacher.php    # Start teacher evaluations
├── evaluations.php         # View evaluation history
├── profile.php             # Manage profile and account settings
├── logout.php              # Logout functionality
└── README.md              # This file
```

## Database Requirements

### Required Tables
1. **users** - User authentication and basic information
2. **students** - Student-specific information
3. **student_profiles** - Extended student profile information
4. **student_academic_info** - Academic information (optional)
5. **teacher_classes** - Classes created by teachers
6. **class_enrollments** - Student enrollments in classes
7. **main_evaluation_categories** - Evaluation categories and types
8. **evaluation_sessions** - Evaluation records

### Class Enrollments Table Schema
```sql
CREATE TABLE `class_enrollments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `join_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('enrolled','dropped','completed') DEFAULT 'enrolled',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_enrollment` (`class_id`, `student_id`),
  FOREIGN KEY (`class_id`) REFERENCES `teacher_classes` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
);
```

## Setup Instructions

1. **Database Setup**:
   ```sql
   -- Run the class_enrollments_table.sql file
   source students/database/class_enrollments_table.sql;
   ```

2. **User Role**: Ensure users have the role `student` in the `users` table

3. **Student Profile**: Each student should have a record in the `students` table with:
   - `id` (references users.id)
   - `student_id` (unique student identifier)
   - `first_name`, `last_name`, `email`
   - `password_hash` (hashed password)

4. **Evaluation Categories**: Ensure there are student-to-teacher evaluation categories in `main_evaluation_categories` with `evaluation_type = 'student_to_teacher'`

## Navigation Structure

### Sidebar Menu
- **Dashboard** - Overview and statistics
- **Join Class** - Join classes using join codes
- **My Classes** - View and manage enrolled classes
- **Evaluations** (with submenu)
  - **My Evaluations** - View evaluation history
  - **Evaluate Teacher** - Start new evaluations
- **Profile** - Manage personal information

## User Workflow

### Joining a Class
1. Student receives join code from teacher
2. Student navigates to "Join Class" page
3. Student enters the 8-character join code
4. System validates code and enrolls student
5. Student can now view class in "My Classes"

### Evaluating a Teacher
1. Student navigates to "Evaluate Teacher"
2. Student selects class and evaluation category
3. System creates evaluation session
4. Student is redirected to IntelliEVal evaluation form
5. Student completes evaluation questions
6. Evaluation is saved and can be viewed in history

### Managing Classes
1. Student views enrolled classes in "My Classes"
2. Student can see class details, teacher information, and join date
3. Student can drop classes if needed
4. Student can start evaluations for active classes

## Security Features

- **Session Management**: Secure session handling with role-based access
- **Input Validation**: All inputs are sanitized and validated
- **SQL Injection Protection**: Prepared statements for all database queries
- **Access Control**: Students can only access their own data
- **Password Security**: Secure password hashing and validation

## Integration Points

### With Teacher Module
- Students join classes created by teachers
- Teachers can view enrolled students in their classes
- Join codes are generated by teachers and used by students

### With IntelliEVal System
- Evaluation sessions are created in student module
- Actual evaluation forms are handled by IntelliEVal
- Results are stored and can be viewed by both students and teachers

### With Main System
- User authentication through main login system
- Role-based access control
- Consistent styling and navigation

## Responsive Design

The student module is fully responsive and works on:
- Desktop computers
- Tablets
- Mobile phones

Features include:
- Collapsible sidebar for mobile devices
- Touch-friendly interface
- Adaptive layouts
- Readable text at all screen sizes

## Error Handling

The module includes comprehensive error handling:
- Invalid join codes
- Duplicate enrollments
- Database connection issues
- Form validation errors
- Session timeout handling

## Future Enhancements

Potential future features:
- Class notifications and announcements
- Assignment submission
- Grade viewing
- Class schedule management
- Student-teacher messaging
- Course materials access

## Support

For technical support or questions about the Student Module, please contact the system administrator. 