# Teacher Availability System

## Overview

The Teacher Availability System allows teachers to confirm their availability for consultations by scanning their QR codes. This ensures that only teachers who have actively confirmed their availability are shown to students on the consultation screen.

## Features

### 1. QR Code Scanning
- Teachers can scan their unique QR codes to confirm availability
- Manual input option for cases where QR scanning is not available
- Real-time availability status updates

### 2. Database Tracking
- Tracks teacher availability status daily
- Records scan time and last activity
- Maintains availability history

### 3. Student Screen Integration
- Only shows teachers who have confirmed availability
- Real-time updates when teachers scan their QR codes
- Maintains existing consultation hour requirements

### 4. Admin Management
- Admin interface to manage teacher availability
- Ability to mark teachers as available/unavailable
- Generate QR codes for teachers
- View statistics and status reports

## Database Structure

### Teacher Availability Table
```sql
CREATE TABLE `teacher_availability` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `availability_date` date NOT NULL,
  `scan_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('available','unavailable') NOT NULL DEFAULT 'available',
  `last_activity` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_teacher_date` (`teacher_id`, `availability_date`)
);
```

## Files Created/Modified

### New Files
1. `database/teacher_availability_table.sql` - Database table creation script
2. `api/teacher-availability-handler.php` - API endpoint for handling availability
3. `generate-teacher-qr.php` - QR code generator for teacher IDs
4. `admin/manage-teacher-availability.php` - Admin interface
5. `test_teacher_availability.php` - Test script for verification

### Modified Files
1. `consultation/teacher-screen.php` - Added QR scanning functionality
2. `consultation/student-screen.php` - Updated to show only available teachers

## Setup Instructions

### 1. Database Setup
Run the database creation script:
```sql
-- Execute the contents of database/teacher_availability_table.sql
```

### 2. Test the System
1. Access `test_teacher_availability.php` to verify database setup
2. Generate QR codes for teachers using `generate-teacher-qr.php?id=TEACHER_ID`
3. Test QR scanning on the teacher screen

### 3. Admin Access
1. Access `admin/manage-teacher-availability.php` to manage teacher availability
2. Use the interface to mark teachers as available/unavailable
3. Generate QR codes for teachers

## Usage Workflow

### For Teachers
1. **Generate QR Code**: Use `generate-teacher-qr.php?id=YOUR_TEACHER_ID`
2. **Access Teacher Screen**: Go to `consultation/teacher-screen.php?dept=DEPARTMENT`
3. **Scan QR Code**: Click "Scan Teacher ID" and scan your QR code
4. **Confirm Availability**: Click "Confirm Availability"
5. **Mark Unavailable**: Use "Mark Unavailable" when leaving

### For Students
1. **Access Student Screen**: Go to `consultation/student-screen.php?dept=DEPARTMENT`
2. **Scan Student ID**: Scan your student ID to access the system
3. **View Available Teachers**: Only teachers who have scanned their QR codes will be shown
4. **Request Consultation**: Select an available teacher to request consultation

### For Admins
1. **Access Admin Panel**: Go to `admin/manage-teacher-availability.php`
2. **View Statistics**: See overview of teacher availability status
3. **Manage Availability**: Mark teachers as available/unavailable
4. **Generate QR Codes**: Create QR codes for teachers

## API Endpoints

### Mark Teacher Available
```
POST /api/teacher-availability-handler.php?action=mark_available
Parameters:
- teacher_id: Teacher ID
- notes: Optional notes
```

### Mark Teacher Unavailable
```
POST /api/teacher-availability-handler.php?action=mark_unavailable
Parameters:
- teacher_id: Teacher ID
- notes: Optional notes
```

### Get Teacher Status
```
GET /api/teacher-availability-handler.php?action=get_status&teacher_id=TEACHER_ID
```

### Get Available Teachers
```
GET /api/teacher-availability-handler.php?action=get_available_teachers&department=DEPARTMENT
```

## Security Considerations

1. **Input Validation**: All teacher IDs are validated for numeric format
2. **SQL Injection Protection**: Uses prepared statements for all database queries
3. **Access Control**: Admin interface requires admin role authentication
4. **Data Integrity**: Unique constraints prevent duplicate availability records

## Troubleshooting

### Common Issues

1. **QR Code Not Scanning**
   - Ensure camera permissions are granted
   - Check if HTML5 QR Code library is loaded
   - Try manual input as fallback

2. **Teacher Not Showing on Student Screen**
   - Verify teacher has consultation hours scheduled
   - Check if teacher has scanned their QR code
   - Ensure teacher is not on consultation leave

3. **Database Errors**
   - Run `test_teacher_availability.php` to check database setup
   - Verify all required tables exist
   - Check database connection settings

### Debug Information
- Check browser console for JavaScript errors
- Review server error logs for PHP issues
- Use `test_teacher_availability.php` for system verification

## Future Enhancements

1. **Automatic Timeout**: Auto-mark teachers as unavailable after inactivity
2. **Push Notifications**: Real-time notifications for availability changes
3. **Analytics Dashboard**: Detailed availability reports and trends
4. **Mobile App**: Native mobile application for QR scanning
5. **Integration**: Connect with existing faculty management systems

## Support

For technical support or questions about the Teacher Availability System, please contact the development team or refer to the system documentation.
