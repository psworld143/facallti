# Hotel PMS Training System

A comprehensive Hotel Property Management System designed for hospitality management students to learn and practice real hotel operations.

## Features

### 🏨 Core Modules
- **Front Desk Management**: Reservations, check-in/check-out, guest services
- **Room Management**: Room status, housekeeping, maintenance
- **Guest Management**: Guest profiles, preferences, VIP management
- **Billing & Payments**: Invoicing, payments, discounts, loyalty programs
- **Management & Reports**: Analytics, occupancy reports, revenue tracking
- **Training & Simulations**: Scenario-based learning, customer service practice

### 🎓 Training Features
- **Scenario-Based Simulations**: Realistic hotel operation scenarios
- **Customer Service Practice**: Guest interaction training
- **Problem Handling**: Crisis management and problem-solving scenarios
- **Progress Tracking**: Performance analytics and certificate system

## System Requirements

- **Web Server**: Apache/Nginx with PHP support
- **PHP**: Version 7.4 or higher
- **MySQL**: Version 5.7 or higher
- **Browser**: Modern web browser with JavaScript enabled

## Installation

### 1. Download and Extract
Download the Hotel PMS files and extract them to your web server directory (e.g., `htdocs` for XAMPP).

### 2. Database Setup
The system uses the `hotel_pms_clean` database which is already set up and configured. The database contains:
- 32 tables for complete hotel management
- Sample data for testing
- Default user accounts ready to use

### 3. Configuration
The system is pre-configured for local development. If you need to modify database settings, edit:
```
pms/booking/includes/config.php
```

### 4. Access the System
Navigate to:
```
http://localhost/seait/pms/booking/
```

## Default Login Credentials

| Role | Username | Password |
|------|----------|----------|
| **Manager** | admin | password |
| **Front Desk** | frontdesk | password |
| **Housekeeping** | housekeeping | password |

## User Roles and Permissions

### Manager
- Full system access
- Management reports and analytics
- User management
- System configuration

### Front Desk
- Guest check-in/check-out
- Reservation management
- Guest services
- Basic reporting

### Housekeeping
- Room status management
- Housekeeping tasks
- Maintenance requests
- Room inventory

## Training System

### Scenario-Based Learning
- **Front Desk Scenarios**: Check-in procedures, guest services
- **Customer Service**: Complaint handling, special requests
- **Problem Solving**: Crisis management, operational challenges

### Progress Tracking
- Completion rates
- Performance scores
- Training hours
- Certificate achievements

### Certificates
- **Scenario Excellence**: Complete scenarios with high scores
- **Customer Service Excellence**: Master guest interactions
- **Problem Solving Excellence**: Demonstrate problem-solving skills

## Sample Data

The system comes pre-loaded with:
- Sample rooms (Standard, Deluxe, Suite)
- Sample guests with profiles
- Sample inventory items
- Training scenarios
- Sample reservations and transactions

## File Structure

```
pms/booking/
├── assets/
│   ├── css/          # Stylesheets
│   ├── js/           # JavaScript files
│   └── images/       # Images and icons
├── modules/
│   ├── front-desk/   # Front desk operations
│   ├── housekeeping/ # Housekeeping management
│   ├── management/   # Management and reports
│   ├── training/     # Training and simulations
│   └── guests/       # Guest management
├── includes/
│   ├── config.php    # Database configuration
│   └── functions.php # Common functions
├── api/              # API endpoints
├── database/
│   └── schema.sql    # Database schema
├── setup_database.php # Database setup script
└── index.php         # Main dashboard
```

## Support

For technical support or questions about the Hotel PMS Training System, please contact your instructor or system administrator.

## Security Notes

- Change default passwords after first login
- Keep the system updated
- Regularly backup the database
- Use HTTPS in production environments

## License

This system is designed for educational purposes in hospitality management training programs.

---

**Hotel PMS Training System** - Preparing students for real hotel operations through comprehensive training and simulation.
