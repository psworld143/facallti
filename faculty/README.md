# Faculty Module - Teacher Portal

This module provides a comprehensive portal for teachers to manage their classes and conduct evaluations within the IntelliEVal system.

## Features

### 🎓 Class Management
- **Create Classes**: Teachers can create new classes with subjects from the `course_curriculum` table
- **Unique Join Codes**: Each class gets a unique 8-character join code for student enrollment
- **Subject Selection**: Dropdown populated with subjects from the course curriculum
- **Class Management**: View, edit, and manage all created classes
- **Status Control**: Activate/deactivate classes as needed
- **Student Management**: View and manage enrolled students in each class
- **Class Dashboard**: Dedicated LMS-style dashboard for each class with comprehensive management tools

### 📢 Announcements
- **Create Announcements**: Post announcements for specific classes with priority levels
- **Priority System**: Low, Medium, High, and Urgent priority levels with color coding
- **Rich Content**: Support for formatted text and detailed announcements
- **Class-Specific**: Target announcements to specific classes or all classes
- **Search & Filter**: Advanced search and filtering capabilities
- **Management**: Edit, delete, and manage all announcements

### 📅 Calendar & Events
- **Event Management**: Create and manage various types of events
- **Event Types**: Class, Exam, Assignment, Meeting, and Other events
- **Class Integration**: Link events to specific classes
- **Visual Calendar**: Monthly calendar view with event indicators
- **Date Navigation**: Easy navigation between months and years
- **Event Details**: View and manage event details with descriptions

### 📊 Reports & Analytics
- **Comprehensive Statistics**: View detailed statistics for classes, enrollments, and evaluations
- **Performance Metrics**: Track class performance and student engagement
- **Evaluation Analytics**: Monitor evaluation completion rates and categories
- **Date Range Filtering**: Filter reports by custom date ranges
- **Visual Charts**: Progress bars and visual representations of data
- **Export Options**: Export reports in PDF, Excel, and CSV formats (planned)
- **Monthly Trends**: Track class creation and activity trends over time

### 📋 Evaluations
- **All Evaluations**: View and manage all evaluations conducted by the teacher
- **Peer to Peer Evaluations**: Specialized evaluation system for faculty members
- **Department Restrictions**: Teachers can only evaluate faculty from their own department
- **Evaluation Categories**: Support for different evaluation types and categories
- **Status Tracking**: Track evaluation progress (draft, completed, in progress)

### 👤 Profile Management
- **Profile Updates**: Update personal information (name, email)
- **Password Changes**: Secure password change functionality
- **Account Statistics**: View comprehensive statistics about classes and evaluations
- **Department Information**: Display teacher's department and position

### 🎯 LMS-Style Class Management
- **Class Dashboard**: Dedicated dashboard for each class with LMS-style interface
- **LMS Sidebar**: Class-specific navigation with all management tools
- **Student Management**: View, search, and manage enrolled students
- **Class-Specific Features**: All features contextualized to the specific class
- **Responsive Design**: Mobile-friendly LMS interface

## File Structure

```
faculty/
├── includes/
│   ├── header.php          # Shared header with sidebar navigation
│   ├── footer.php          # Shared footer
│   ├── lms_header.php      # LMS-style header for class pages
│   └── lms_footer.php      # LMS-style footer for class pages
├── database/
│   ├── teacher_classes_table.sql  # Database schema for teacher classes
│   └── additional_tables.sql      # Database schema for new features
├── dashboard.php           # Main dashboard with statistics
├── class-management.php    # Class creation and management
├── class_dashboard.php     # LMS-style class dashboard
├── class_students.php      # Class-specific student management
├── student-list.php        # Student enrollment management
├── announcements.php       # Announcement creation and management
├── calendar.php           # Calendar and event management
├── reports.php            # Analytics and reporting
├── evaluations.php         # All evaluations view
├── peer-evaluations.php    # Peer to peer evaluation system
├── evaluation-results.php  # Evaluation results and statistics
├── profile.php            # Profile management
├── logout.php             # Logout functionality
└── README.md              # This file
```

## Database Requirements

### Required Tables
1. **users** - User authentication and basic information
2. **teachers** - Teacher-specific information including department
3. **course_curriculum** - Subjects available for class creation
4. **main_evaluation_categories** - Evaluation categories and types
5. **evaluation_sessions** - Evaluation records
6. **teacher_classes** - Classes created by teachers (created by this module)
7. **class_enrollments** - Student enrollments in classes
8. **class_announcements** - Announcements for classes (new)
9. **faculty_events** - Calendar events for faculty (new)
10. **faculty_notifications** - Notifications for faculty (new)

### New Tables Schema

#### Class Announcements Table
```sql
CREATE TABLE `class_announcements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`class_id`) REFERENCES `teacher_classes` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
);
```

#### Faculty Events Table
```sql
CREATE TABLE `faculty_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `event_date` date NOT NULL,
  `event_type` enum('class','exam','assignment','meeting','other') DEFAULT 'other',
  `class_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`class_id`) REFERENCES `teacher_classes` (`id`) ON DELETE SET NULL
);
```

#### Faculty Notifications Table
```sql
CREATE TABLE `faculty_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','error','announcement','evaluation','class') DEFAULT 'info',
  `related_id` int(11) DEFAULT NULL,
  `related_type` varchar(50) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
);
```

## Setup Instructions

1. **Database Setup**:
   ```sql
   -- Run the teacher_classes_table.sql file
   source faculty/database/teacher_classes_table.sql;
   
   -- Run the additional_tables.sql file for new features
   source faculty/database/additional_tables.sql;
   ```

2. **User Role**: Ensure users have the role `teacher` in the `users` table

3. **Teacher Profile**: Each teacher should have a record in the `teachers` table with:
   - `user_id` (references users.id)
   - `department` (required for peer evaluations)
   - `position`
   - `phone` (optional)

4. **Evaluation Categories**: Ensure there are peer evaluation categories in `main_evaluation_categories` with `evaluation_type = 'peer_to_peer'`

## Navigation Structure

### Main Faculty Portal Sidebar
- **Dashboard** - Overview and statistics
- **Class Management** - Create and manage classes
- **Evaluations** (with submenu)
  - **All Evaluations** - View all evaluations
  - **Peer to Peer** - Conduct peer evaluations
- **Announcements** - Create and manage class announcements
- **Calendar** - Manage events and schedules
- **Reports** - View analytics and statistics
- **Profile** - Manage account information

### LMS-Style Class Sidebar
When viewing a specific class, teachers get access to a dedicated LMS-style sidebar with:
- **Dashboard** - Class overview and statistics
- **Students** - Manage enrolled students
- **Announcements** - Class-specific announcements
- **Learning Materials** - Upload and manage course materials
- **Assignments** - Create and manage assignments
- **Discussions** - Class discussion forums
- **Grades & Progress** - Track student performance
- **Class Calendar** - Class-specific events and schedules
- **Student Evaluations** - View evaluations from students
- **Back to Classes** - Return to class management

## Security Features

- **Role-based Access**: Only users with `teacher` role can access
- **Department Restrictions**: Peer evaluations limited to same department
- **Session Management**: Secure session handling
- **Input Sanitization**: All user inputs are sanitized
- **SQL Injection Prevention**: Prepared statements used throughout
- **Data Ownership**: Teachers can only access their own data
- **Class Ownership**: Teachers can only access classes they created

## Key Features

### Class Management
- Teachers can create classes with subjects from the course curriculum
- Each class gets a unique join code for student enrollment
- Classes can be managed (edit, delete, regenerate join code)
- Search and filter functionality for classes
- Student enrollment tracking and management

### Class Dashboard (LMS-Style)
- **Dedicated Interface**: Each class has its own LMS-style dashboard
- **Class Information**: Display subject, section, and join code
- **Statistics Overview**: Student count, evaluations, announcements, events
- **Recent Activities**: Latest announcements and upcoming events
- **Quick Actions**: Fast access to common tasks
- **Responsive Design**: Mobile-friendly interface

### Announcements System
- Create announcements with different priority levels
- Target announcements to specific classes
- Rich text content support
- Search and filter capabilities
- Priority-based color coding

### Calendar & Events
- Monthly calendar view with event indicators
- Multiple event types (class, exam, assignment, meeting, other)
- Link events to specific classes
- Easy date navigation
- Event management (create, edit, delete)

### Reports & Analytics
- Comprehensive statistics dashboard
- Date range filtering
- Performance metrics and trends
- Visual data representation
- Export functionality (planned)

### Peer Evaluations
- Teachers can only evaluate faculty from their own department
- Prevents duplicate evaluations for the same faculty member and category
- Integration with the main evaluation system
- Department-based faculty listing

### Student Management
- View all enrolled students in a class
- Search and filter students by name, ID, or email
- Remove students from class (mark as dropped)
- Student status tracking (active/dropped)
- Contact information display

### Responsive Design
- Mobile-friendly interface
- Collapsible sidebar for mobile devices
- Consistent design with the main IntelliEVal system
- Tailwind CSS styling

## Integration Points

- **IntelliEVal System**: Links to evaluation conduction and viewing pages
- **Course Curriculum**: Uses subjects from the existing curriculum system
- **User Management**: Integrates with the main user authentication system
- **Evaluation System**: Connects to the main evaluation framework
- **Student Module**: Students can view announcements and join classes

## Usage Examples

### Creating a Class
1. Navigate to Class Management
2. Click "Add New Class"
3. Select subject from dropdown (populated from course_curriculum)
4. Enter section (e.g., "A", "1A", "2B")
5. Add optional description
6. System generates unique join code automatically

### Accessing Class Dashboard
1. Navigate to Class Management
2. Click the dashboard icon (📊) next to any class
3. Access the LMS-style interface with class-specific features
4. Use the sidebar to navigate between different class management tools

### Managing Class Students
1. Access the class dashboard
2. Click "Students" in the sidebar
3. View all enrolled students
4. Search or filter students as needed
5. Remove students if necessary

### Creating an Announcement
1. Navigate to Announcements or use class dashboard
2. Click "Create Announcement"
3. Select target class
4. Enter title and content
5. Choose priority level
6. Submit to publish announcement

### Adding a Calendar Event
1. Navigate to Calendar
2. Click "Add Event"
3. Enter event details (title, description, date)
4. Select event type
5. Optionally link to a specific class
6. Save event

### Viewing Reports
1. Navigate to Reports
2. Select date range (optional)
3. View comprehensive statistics
4. Analyze performance metrics
5. Export data (planned feature)

### Conducting Peer Evaluation
1. Navigate to Peer to Peer Evaluations
2. Select faculty member from same department
3. Choose evaluation category
4. System creates evaluation session and redirects to evaluation form
5. Complete evaluation using 1-5 rating scale

## Performance Optimizations

- **Database Indexes**: Optimized indexes for common queries
- **Pagination**: Efficient pagination for large datasets
- **Caching**: Query result caching for frequently accessed data
- **Lazy Loading**: Load data on demand to improve page load times

## Future Enhancements

- **Real-time Notifications**: Push notifications for new enrollments and announcements
- **Advanced Analytics**: More detailed charts and graphs
- **Export Functionality**: PDF, Excel, and CSV export for reports
- **Email Integration**: Email notifications for important events
- **Mobile App**: Native mobile application for faculty
- **API Integration**: RESTful API for third-party integrations

## Support

For technical support or questions about the faculty module, please refer to the main IntelliEVal system documentation or contact the development team. 