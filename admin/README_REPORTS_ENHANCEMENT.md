# Enhanced Reports and Statistics with Semester Selection

## Overview
The admin reports system has been enhanced to include comprehensive semester-based filtering and statistics. This allows administrators to view detailed analytics for specific academic periods.

## Features

### 1. Semester Filtering
- **Semester Dropdown**: Select from available active semesters
- **Academic Year Filter**: Filter by specific academic years
- **Date Range Display**: Shows the exact date range for selected semester
- **All Semesters Option**: View overall statistics without semester filtering

### 2. Enhanced Statistics Cards
The main statistics cards now show:
- **Total counts** for all time
- **Semester-specific counts** when a semester is selected
- **Visual indicators** showing the difference between total and semester data

### 3. Semester-Specific Statistics
When a semester is selected, the system displays:
- Users registered during the semester
- Posts created during the semester
- Students active during the semester
- Inquiries received during the semester
- Evaluations conducted during the semester

### 4. Additional Semester Statistics
Extra statistics cards appear when a semester is selected:
- **Evaluations**: Number of evaluations conducted
- **Active Faculty**: Total active faculty members
- **Active Programs**: Number of active academic programs

### 5. Enhanced Charts
- **Posts by Type**: Filtered by selected semester
- **Users by Role**: Filtered by selected semester
- **Monthly Activity Chart**: Shows activity trends within the selected semester

### 6. Recent Activity Filtering
- **Recent Posts**: Shows posts from the selected semester
- **Recent Users**: Shows users registered during the selected semester
- **Clear Indicators**: Labels show when data is semester-filtered

### 7. Detailed Statistics Sections
Three detailed statistics panels:
- **Post Statistics**: Total, approved, pending, and semester-specific counts
- **User Statistics**: Total users, semester users, active students, and faculty
- **Inquiry Statistics**: Total inquiries, resolution rates, and semester-specific data

### 8. Export Functionality
CSV export options for all report types:
- **User Reports**: Export user data with semester filtering
- **Post Reports**: Export post data with semester filtering
- **Inquiry Reports**: Export inquiry data with semester filtering
- **Student Reports**: Export student data with semester filtering
- **Evaluation Reports**: Export evaluation data with semester filtering

## Database Integration

### Tables Used
- `semesters`: Semester definitions and date ranges
- `users`: User registration and activity data
- `posts`: Content creation data
- `students`: Student registration data
- `user_inquiries`: Contact form submissions
- `evaluation_sessions`: Teacher evaluation data (IntelliEVal system)
- `faculty`: Faculty member data
- `academic_programs`: Program information

### Key Queries
- Semester date range filtering using `BETWEEN` clauses
- Prepared statements for security
- LEFT JOINs for related data
- GROUP BY for aggregated statistics

## Usage

### Accessing Reports
1. Navigate to Admin Dashboard
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

## Technical Implementation

### PHP Features
- Session-based authentication
- Prepared statements for SQL security
- Dynamic query building based on filters
- Error handling for missing tables

### Frontend Features
- Responsive design with Tailwind CSS
- Interactive charts using Chart.js
- Real-time filter updates
- Mobile-friendly interface

### Security Features
- Admin authentication required
- SQL injection prevention
- XSS protection with htmlspecialchars
- Input validation and sanitization

## File Structure
```
admin/
├── reports.php              # Main reports page with semester filtering
├── export_reports.php       # CSV export functionality
└── README_REPORTS_ENHANCEMENT.md  # This documentation
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

## Future Enhancements
- PDF export functionality
- Email report scheduling
- Custom date range selection
- Advanced filtering options
- Real-time data updates
- Dashboard widgets
- Report templates
- Data visualization improvements 