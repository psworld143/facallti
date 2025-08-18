# IntelliEVal Reports & Statistics System

## Overview
The IntelliEVal Reports & Statistics system provides comprehensive analytics and reporting capabilities for the teacher evaluation system. This system allows guidance officers to view detailed evaluation data with semester-based filtering and export functionality.

## Features

### 1. Semester Filtering System
- **Semester Dropdown**: Select from available active semesters
- **Academic Year Filter**: Filter by specific academic years
- **Date Range Display**: Shows the exact date range for selected semester
- **All Semesters Option**: View overall statistics without semester filtering

### 2. Statistics Dashboard
The main statistics cards display:
- **Total Evaluations**: Overall and semester-specific evaluation counts
- **Teachers Evaluated**: Number of unique teachers evaluated
- **Students Participated**: Number of unique students who participated
- **Subjects Evaluated**: Number of unique subjects evaluated

### 3. Interactive Charts
- **Evaluation Types Chart**: Doughnut chart showing distribution of evaluator types
- **Monthly Activity Chart**: Line chart showing evaluation trends within selected semester
- **Real-time Updates**: Charts update based on semester selection

### 4. Top Performers Analysis
- **Top Evaluated Teachers**: Teachers with the most evaluations
- **Top Evaluated Subjects**: Subjects with the most evaluations
- **Semester Filtering**: Results filtered by selected semester

### 5. Recent Evaluations Table
- **Comprehensive View**: Shows evaluator, teacher, subject, type, and date
- **Semester Filtering**: Displays evaluations from selected semester
- **Status Indicators**: Color-coded evaluator types

### 6. Export Functionality
CSV export options for all report types:
- **Evaluation Reports**: Detailed evaluation data with semester filtering
- **Teacher Reports**: Teacher performance and evaluation statistics
- **Subject Reports**: Subject-wise evaluation analysis
- **Student Reports**: Student participation and activity
- **Monthly Reports**: Monthly evaluation activity trends

## Database Integration

### Tables Used
- `evaluation_sessions`: Core evaluation data
- `semesters`: Semester definitions and date ranges
- `subjects`: Subject information
- `users`: User information (evaluators and evaluatees)

### Actual Table Structure
The `evaluation_sessions` table has the following structure:
```sql
- id (int) - Primary key
- evaluator_id (int) - ID of the person doing the evaluation
- evaluator_type (enum) - Type of evaluator ('student', 'teacher', 'head')
- evaluatee_id (int) - ID of the person being evaluated
- evaluatee_type (enum) - Type of evaluatee ('teacher', 'student', 'head')
- main_category_id (int) - Evaluation category reference
- semester_id (int) - Semester reference
- subject_id (int) - Subject reference (nullable)
- evaluation_date (date) - Date of evaluation
- status (enum) - Status ('draft', 'completed', 'archived', 'cancelled')
- notes (text) - Additional notes
- created_at (timestamp) - Creation timestamp
- updated_at (timestamp) - Last update timestamp
```

### Key Queries
- Semester date range filtering using `BETWEEN` clauses on `evaluation_date`
- Prepared statements for security
- LEFT JOINs for related data
- GROUP BY for aggregated statistics
- DISTINCT counts for unique entities

## Usage

### Accessing Reports
1. Navigate to IntelliEVal Dashboard
2. Click on "Reports & Analytics"
3. Use the semester filter form to select desired period
4. View filtered statistics and charts
5. Export data as needed

### Filtering Options
- **No Filter**: View overall statistics
- **Semester Filter**: View data for specific semester
- **Academic Year**: Filter by academic year
- **Combined Filters**: Use both semester and year filters

### Export Options
- Click on export buttons to download CSV files
- Files include semester information in headers
- Data is filtered according to selected semester

## Report Types

### 1. Evaluation Reports
**File**: `export_evaluation_reports.php?type=evaluations`
**Content**:
- Evaluation ID
- Evaluator Type (Student/Teacher/Head)
- Evaluator Name
- Teacher Name
- Subject
- Semester
- Evaluation Date
- Status

### 2. Teacher Reports
**File**: `export_evaluation_reports.php?type=teachers`
**Content**:
- Teacher ID and Name
- Total Evaluations
- Breakdown by evaluator type (Student/Teacher/Head)
- Subjects Taught

### 3. Subject Reports
**File**: `export_evaluation_reports.php?type=subjects`
**Content**:
- Subject ID and Name
- Total Evaluations
- Breakdown by evaluator type
- Teachers Teaching the Subject

### 4. Student Reports
**File**: `export_evaluation_reports.php?type=students`
**Content**:
- Student ID and Name
- Total Evaluations
- Teachers Evaluated
- Subjects Evaluated
- Last Evaluation Date

### 5. Monthly Reports
**File**: `export_evaluation_reports.php?type=monthly`
**Content**:
- Month and Year
- Total Evaluations
- Breakdown by evaluator type

## Technical Implementation

### PHP Features
- Session-based authentication for guidance officers
- Prepared statements for SQL security
- Dynamic query building based on filters
- Error handling for missing data

### Frontend Features
- Responsive design with Tailwind CSS
- Interactive charts using Chart.js
- Real-time filter updates
- Mobile-friendly interface

### Security Features
- Guidance officer authentication required
- SQL injection prevention
- XSS protection with htmlspecialchars
- Input validation and sanitization

## File Structure
```
IntelliEVal/
├── reports.php                    # Main reports page with semester filtering
├── export_evaluation_reports.php  # CSV export functionality
└── README_REPORTS_SYSTEM.md      # This documentation
```

## Dependencies
- PHP 7.4+
- MySQL/MariaDB
- Chart.js (CDN)
- Tailwind CSS (CDN)
- Font Awesome (CDN)

## Browser Compatibility
- Chrome 80+
- Firefox 75+
- Safari 13+
- Edge 80+

## Data Visualization

### Chart Types
1. **Doughnut Chart**: Evaluator Types Distribution
   - Shows proportion of different evaluator types (Student/Teacher/Head)
   - Color-coded for easy identification
   - Legend at the bottom

2. **Line Chart**: Monthly Activity Trends
   - Shows evaluation activity over time
   - Only displayed when semester is selected
   - Smooth curves with fill area

### Color Scheme
- **Primary**: SEAIT Orange (#FF6B35)
- **Success**: Green (#4CAF50)
- **Info**: Blue (#2196F3)
- **Warning**: Orange (#FF9800)
- **Purple**: Purple (#9C27B0)

## Performance Considerations

### Database Optimization
- Indexed queries on frequently used columns
- Prepared statements for repeated queries
- Efficient JOIN operations
- Limited result sets (TOP 5, LIMIT 10)

### Frontend Optimization
- Lazy loading of charts
- Responsive image handling
- Minimal DOM manipulation
- Efficient event handling

## Error Handling

### Database Errors
- Graceful handling of missing tables
- Fallback queries for missing data
- User-friendly error messages
- Logging for debugging

### User Input Validation
- Semester ID validation
- Date range validation
- SQL injection prevention
- XSS protection

## Future Enhancements

### Planned Features
- PDF export functionality
- Email report scheduling
- Custom date range selection
- Advanced filtering options
- Real-time data updates
- Dashboard widgets
- Report templates
- Data visualization improvements

### Analytics Enhancements
- Trend analysis
- Predictive analytics
- Comparative reports
- Performance metrics
- Quality indicators

## Support and Maintenance

### Regular Tasks
- Database optimization
- Performance monitoring
- Security updates
- Feature enhancements
- Bug fixes

### Troubleshooting
- Check database connectivity
- Verify user permissions
- Review error logs
- Test export functionality
- Validate chart rendering

## Security Considerations

### Access Control
- Role-based authentication
- Session management
- Input sanitization
- Output encoding
- SQL injection prevention

### Data Protection
- Secure file downloads
- Temporary file cleanup
- Access logging
- Audit trails
- Privacy compliance

## Integration Points

### External Systems
- Student Information System
- Faculty Management System
- Academic Calendar System
- Learning Management System

### APIs and Services
- Authentication services
- Email services
- File storage services
- Analytics services 