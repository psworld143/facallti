# IntelliEVal Training & Seminar Management Module

## Overview

The Training & Seminar Management Module allows guidance officers to create, manage, and suggest professional development programs for teachers based on their evaluation results. This module integrates with the existing evaluation system to provide data-driven training recommendations.

## Features

### 🎯 **Core Functionality**
- **Training Management**: Create and manage trainings, seminars, workshops, and conferences
- **Category Alignment**: Link trainings to evaluation categories (e.g., Classroom Management, Teaching Skills)
- **AI Suggestions**: Automatically suggest trainings based on teacher evaluation scores
- **Registration Tracking**: Monitor participant registrations and attendance
- **Progress Monitoring**: Track training completion and feedback

### 📊 **Smart Recommendations**
- **4.0 Threshold**: Automatically suggests trainings for teachers with average scores below 4.0
- **Priority Levels**: Critical (< 2.5), High (2.5-3.0), Medium (3.0-3.5), Low (3.5-4.0)
- **Category-Specific**: Trainings aligned with weak areas identified in evaluations
- **Evidence-Based**: Suggestions include evaluation scores and reasoning
- **Automatic Generation**: AI-powered suggestion system based on performance gaps

### 🎨 **User Interface**
- **Modern Design**: Clean, responsive interface using Tailwind CSS
- **Separate CSS**: Modular styling with dedicated CSS file
- **Mobile-Friendly**: Responsive design for all devices
- **Intuitive Navigation**: Easy-to-use interface with clear actions

## Database Schema

### Tables Created
1. **`training_categories`** - Training category management
2. **`trainings_seminars`** - Main training/seminar data
3. **`training_registrations`** - Participant registration tracking
4. **`training_suggestions`** - AI-based training recommendations
5. **`training_materials`** - Training resource management

### Key Relationships
- Trainings linked to evaluation main categories and sub-categories
- Suggestions based on teacher evaluation scores
- Registrations tracked per training and participant

## Setup Instructions

### 1. Database Setup
```bash
# Run the database schema
mysql -u root -p seait_website < database/training_seminar_schema.sql
```

### 2. Initial Configuration
1. **Add Training Categories**: Create categories to organize your trainings
2. **Create First Training**: Use the "Add Training" feature
3. **Link to Evaluations**: Connect trainings to relevant evaluation categories
4. **Generate Suggestions**: Use the AI suggestion system

## Pages Created

### Main Pages
- **`trainings.php`** - Main training management dashboard
- **`add-training.php`** - Create new trainings/seminars
- **`view-training.php`** - Detailed training view
- **`training-suggestions.php`** - AI-powered training recommendations

### Supporting Files
- **`get_sub_categories.php`** - AJAX endpoint for dynamic category loading
- **`assets/css/training-module.css`** - Dedicated CSS styling
- **`database/training_seminar_schema.sql`** - Database schema

## Usage Guide

### Creating a Training
1. Navigate to **Trainings & Seminars**
2. Click **"Add Training"**
3. Fill in basic information (title, description, type)
4. Select categories and link to evaluation categories
5. Set schedule and details
6. Configure options (mandatory, certificates, materials)
7. Save as draft or publish

### Linking to Evaluation Categories
1. Select **Main Category** (e.g., "Student to Teacher Evaluation")
2. Choose **Sub-Category** (e.g., "Classroom Management")
3. This enables AI suggestions for teachers with low scores in that area

### Generating Training Suggestions
1. Navigate to **Training Suggestions**
2. Click **"Generate Suggestions"**
3. System analyzes teacher evaluation scores and identifies those below 4.0 threshold
4. Creates priority-based recommendations based on score ranges:
   - **Critical**: Scores below 2.5
   - **High**: Scores between 2.5-3.0
   - **Medium**: Scores between 3.0-3.5
   - **Low**: Scores between 3.5-4.0
5. Teachers can view and respond to suggestions

### Managing Participants
1. View training details
2. See registered participants
3. Track attendance and completion
4. Manage certificates and feedback

## CSS Classes

### Badge Classes
- `.priority-critical` - Red badge for critical priority
- `.priority-high` - Orange badge for high priority
- `.priority-medium` - Yellow badge for medium priority
- `.priority-low` - Green badge for low priority

### Status Classes
- `.status-published` - Green badge for published trainings
- `.status-draft` - Yellow badge for draft trainings
- `.status-ongoing` - Blue badge for ongoing trainings
- `.status-completed` - Gray badge for completed trainings

### Button Classes
- `.btn-primary` - Primary action buttons
- `.btn-secondary` - Secondary action buttons
- `.btn-success` - Success action buttons
- `.btn-info` - Information action buttons

### Card Classes
- `.training-card` - Main card styling
- `.stats-card` - Statistics card styling
- `.overview-card` - Overview section styling

## Integration Points

### With Evaluation System
- **Main Categories**: Links to `main_evaluation_categories`
- **Sub-Categories**: Links to `evaluation_sub_categories`
- **Teacher Scores**: Uses evaluation results for suggestions
- **Performance Tracking**: Monitors improvement through evaluations

### With User Management
- **Teacher Profiles**: Links to teacher user accounts
- **Guidance Officers**: Restricted access for guidance officers only
- **Role-Based Access**: Secure access control

## Future Enhancements

### Planned Features
- **Email Notifications**: Automatic notifications for training suggestions
- **Calendar Integration**: Sync with school calendar
- **Certificate Generation**: Automated certificate creation
- **Advanced Analytics**: Detailed training effectiveness reports
- **Mobile App**: Mobile-friendly participant interface

### Technical Improvements
- **API Endpoints**: RESTful API for external integrations
- **Real-time Updates**: WebSocket notifications
- **Advanced Filtering**: More sophisticated search and filter options
- **Bulk Operations**: Mass training management features

## Troubleshooting

### Common Issues
1. **"Table doesn't exist"**: Run the database schema to create missing tables
2. **"No categories found"**: Create training categories through the add training form
3. **"AJAX errors"**: Check file permissions and database connectivity
4. **"CSS not loading"**: Verify the CSS file path in header.php

### Support
- Verify all required files are present
- Ensure proper database permissions
- Check browser console for JavaScript errors
- Run the database schema if tables are missing

## Security Considerations

- **Input Validation**: All user inputs are validated and sanitized
- **SQL Injection Protection**: Prepared statements used throughout
- **Access Control**: Role-based access restrictions
- **Session Management**: Secure session handling
- **XSS Protection**: Output escaping for all user data

---

**Version**: 1.0  
**Last Updated**: December 2024  
**Compatibility**: IntelliEVal System v2.0+ 