# Leave Reports - Human Resource Module

## Overview
The Leave Reports page provides comprehensive analytics and reporting for leave management across both employees (staff/admin) and faculty members. It offers multiple views and export capabilities to help HR personnel analyze leave patterns and trends.

## Features

### 1. Overview Tab
- **Statistics Cards**: Display total requests, pending, approved, and rejected counts
- **Leave Type Distribution**: Shows request counts and approval rates by leave type
- **Department Distribution**: Displays request counts and approval rates by department

### 2. Leave Requests Tab
- **Detailed Table**: Lists all leave requests with employee information, dates, status, and department
- **Filtering**: Apply filters by year, month, department, employee type, leave type, status, and date range
- **Sorting**: Automatically sorted by creation date (newest first)

### 3. Leave Balances Tab
- **Balance Overview**: Shows current leave balances for all employees and faculty
- **Usage Tracking**: Displays total days, used days, remaining days, and usage percentage
- **Visual Indicators**: Color-coded usage percentages (green: <60%, yellow: 60-80%, red: >80%)

### 4. Trends Tab
- **Monthly Trends**: Bar chart showing leave request patterns throughout the year
- **Year-over-Year**: Placeholder for future comparison functionality

## Filtering Options

### Basic Filters
- **Year**: Select from current year and previous 5 years
- **Month**: Filter by specific month or view all months
- **Department**: Filter by specific department or view all departments
- **Employee Type**: Choose between all employees, staff/admin only, or faculty only

### Advanced Filters
- **Leave Type**: Filter by specific leave type (vacation, sick, study, etc.)
- **Status**: Filter by request status (pending, approved, rejected, cancelled)
- **Date Range**: Set custom start and end dates for requests

## Export Functionality

### CSV Export
- **Format**: UTF-8 encoded CSV with BOM for Excel compatibility
- **Naming**: Files are named with tab name, year, and timestamp
- **Content**: Exports data based on current tab and applied filters

### Export by Tab
- **Overview**: Statistics, leave type distribution, and department distribution
- **Requests**: Detailed leave request information
- **Balances**: Leave balance data with usage calculations
- **Trends**: Monthly trend data

## Technical Details

### Database Tables Used
- `employee_leave_requests` - Staff/admin leave requests
- `faculty_leave_requests` - Faculty leave requests
- `employee_leave_balances` - Staff/admin leave balances
- `faculty_leave_balances` - Faculty leave balances
- `leave_types` - Leave type definitions
- `employees` - Staff/admin information
- `faculty` - Faculty information

### Security Features
- **Authentication**: Requires HR role authentication
- **Input Validation**: All user inputs are properly sanitized
- **SQL Injection Protection**: Uses prepared statements for all database queries
- **Error Handling**: Comprehensive error logging without exposing sensitive information

### Performance Considerations
- **Efficient Queries**: Optimized SQL queries with proper JOINs
- **Prepared Statements**: Reusable query templates for better performance
- **Result Caching**: Results are processed once and reused across tabs

## Usage Instructions

### Accessing the Page
1. Navigate to the Human Resource module
2. Click on "Leave Reports" in the sidebar navigation
3. Ensure you have HR role permissions

### Applying Filters
1. Select desired filter options from the dropdown menus
2. Set date ranges if needed
3. Click "Apply Filters" or let auto-submit handle year/month changes
4. Switch between tabs to view different data perspectives

### Exporting Data
1. Apply desired filters to narrow down the data
2. Select the appropriate tab for the data you want to export
3. Click the "Export Report" button
4. Choose a location to save the CSV file

### Interpreting Results
- **High Usage Percentages**: May indicate employees taking maximum leave or potential leave abuse
- **Department Patterns**: Can reveal workload distribution or policy compliance issues
- **Monthly Trends**: Help identify peak leave periods for resource planning
- **Status Distribution**: Shows approval workflow efficiency

## Troubleshooting

### Common Issues
- **No Data Displayed**: Check if the selected year has leave data
- **Export Fails**: Ensure sufficient memory and disk space
- **Slow Loading**: Large datasets may take time; consider narrowing filters

### Error Logging
- All database errors are logged to the system error log
- Check error logs for detailed troubleshooting information
- Contact system administrator for persistent issues

## Future Enhancements

### Planned Features
- **Interactive Charts**: JavaScript-based charts for better visualization
- **Advanced Analytics**: Statistical analysis and predictive modeling
- **Custom Report Builder**: User-defined report templates
- **Email Reports**: Automated report delivery via email
- **Real-time Updates**: Live data refresh capabilities

### Integration Opportunities
- **HRIS Systems**: Connect with external HR information systems
- **Payroll Integration**: Link leave data with payroll calculations
- **Workflow Automation**: Trigger actions based on leave patterns
- **Mobile Access**: Responsive design for mobile devices

## Support

For technical support or feature requests, contact the HR module development team or refer to the system documentation.
