# FACALLTI - Faculty Consultation Time Interface

A comprehensive web-based system for managing faculty consultation hours and student-teacher interactions at SEAIT (Southeast Asian Institute of Technology).

## 🎯 System Overview

FACALLTI (Faculty Consultation Time Interface) is designed to streamline and manage faculty consultation hours, student requests, and real-time communication between teachers and students. The system provides a modern, efficient way to handle consultation scheduling and management.

## ✨ Key Features

### For Students
- **QR Code Scanning**: Quick access to faculty consultation through QR codes
- **Real-time Consultation Requests**: Submit consultation requests to available faculty
- **Live Notifications**: Receive instant updates on consultation status
- **Department-based Faculty Discovery**: Browse faculty by department/college

### For Faculty
- **Consultation Hours Management**: Set and manage consultation schedules
- **Real-time Request Handling**: Accept/decline consultation requests instantly
- **Availability Status**: Toggle availability status in real-time
- **Consultation History**: Track and manage consultation sessions

### For Department Heads
- **Faculty Management**: Add, edit, and manage faculty profiles
- **Consultation Monitoring**: Monitor consultation activities and response times
- **Reports and Analytics**: Generate consultation reports and analytics
- **Department Oversight**: Manage faculty consultation hours and schedules

## 🏗️ System Architecture

### Core Components
- **`/facallti/`** - Main consultation system interface
- **`/heads/`** - Department head management system
- **`/api/`** - RESTful APIs for consultation functionality
- **`/config/`** - System configuration files
- **`/database/`** - Database schema and setup files

### Database Schema
The system uses a MySQL database with the following core tables:
- `colleges` - College/department information
- `faculty` - Faculty member profiles
- `students` - Student information
- `heads` - Department head profiles
- `consultation_hours` - Faculty consultation schedules
- `consultation_requests` - Student consultation requests
- `consultation_leave` - Faculty leave management
- `teacher_availability` - Real-time availability status

## 🚀 Installation

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- Composer (for dependency management)

### Setup Instructions

1. **Clone the repository**
   ```bash
   git clone https://github.com/psworld143/facallti.git
   cd facallti
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Database setup**
   - Create a MySQL database named `facallti`
   - Import the database schema:
     ```bash
     mysql -u root -p facallti < database/facallti_core.sql
     ```

4. **Configuration**
   - Update `config/database.php` with your database credentials
   - Configure your web server to point to the project directory

5. **Permissions**
   ```bash
   chmod 755 uploads/
   chmod 755 uploads/faculty_photos/
   chmod 755 uploads/student-photos/
   ```

## 📱 Usage

### For Students
1. Navigate to the main consultation interface
2. Scan QR codes or browse faculty by department
3. Submit consultation requests
4. Receive real-time notifications about request status

### For Faculty
1. Access the teacher dashboard
2. Set consultation hours and availability
3. Respond to student consultation requests
4. Manage consultation sessions

### For Department Heads
1. Access the head dashboard
2. Manage faculty profiles and schedules
3. Monitor consultation activities
4. Generate reports and analytics

## 🔧 Configuration

### Database Configuration
Edit `config/database.php` to match your database settings:
```php
$host = 'localhost';
$dbname = 'facallti';
$username = 'your_username';
$password = 'your_password';
```

### System Settings
- Timezone: Set to 'Asia/Manila' (configurable in database.php)
- Character Set: UTF-8 (supports emojis and special characters)

## 📊 Features in Detail

### QR Code Integration
- Faculty can generate QR codes for easy student access
- Students can scan QR codes to quickly request consultations
- Real-time availability checking through QR code scanning

### Real-time Notifications
- WebSocket-based notifications for instant updates
- Audio notifications for consultation requests
- Status updates for both students and faculty

### Consultation Management
- Flexible consultation hour scheduling
- Leave management for faculty
- Session tracking and duration monitoring
- Notes and feedback system

## 🛠️ Development

### Project Structure
```
facallti/
├── api/                    # RESTful API endpoints
├── assets/                 # CSS, JS, and image assets
├── config/                 # Configuration files
├── database/               # Database schema and migrations
├── facallti/              # Main consultation system
├── heads/                 # Department head management
├── includes/              # Shared PHP includes
├── uploads/               # File uploads
└── vendor/                # Composer dependencies
```

### API Endpoints
- `GET /api/get-available-teachers.php` - Get available faculty
- `POST /api/process-qr-scan.php` - Process QR code scans
- `POST /api/confirm-teacher-availability.php` - Update availability
- `POST /api/update-teacher-availability.php` - Toggle availability

## 📝 License

This project is proprietary software developed for SEAIT (Southeast Asian Institute of Technology).

## 🤝 Support

For technical support or questions about the FACALLTI system, please contact the development team at SEAIT.

## 📈 Version History

- **v1.0** - Initial release with core consultation functionality
- **v1.1** - Added QR code integration and real-time notifications
- **v1.2** - Enhanced reporting and analytics features

---

**FACALLTI** - Streamlining faculty consultation management for modern education.
