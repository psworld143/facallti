# IntelliEVal - Hierarchical Evaluation System

## Overview

The IntelliEVal system has been reconstructed to support a hierarchical evaluation structure with main categories, sub-categories, and standardized questionnaires using a 1-5 rating scale. This new structure provides better organization and more comprehensive evaluation capabilities.

## System Architecture

### Main Categories
The system supports three main evaluation categories:

1. **Student to Teacher Evaluation** - Students evaluate their teachers
2. **Peer to Peer Evaluation** - Teachers evaluate their colleagues  
3. **Head to Teacher Evaluation** - Department heads evaluate teachers

### Sub-Categories
Each main category contains multiple sub-categories:

#### Student to Teacher Evaluation Sub-Categories:
- Classroom Management
- Teaching Skills
- Subject Knowledge
- Communication Skills
- Student Engagement

#### Peer to Peer Evaluation Sub-Categories:
- Professional Competence
- Collaboration
- Innovation
- Mentoring

#### Head to Teacher Evaluation Sub-Categories:
- Leadership
- Administrative Skills
- Professional Development
- Compliance

### Questionnaires
Each sub-category contains multiple questionnaires with standardized question types:

- **Rating (1-5 Scale)** - Standardized rating scale with labels:
  - 1 - Poor
  - 2 - Good
  - 3 - Satisfactory
  - 4 - Very Satisfactory
  - 5 - Excellent

- **Text Response** - Open-ended text answers
- **Yes/No** - Binary choice questions
- **Multiple Choice** - Custom options for selection

## Database Schema

### New Tables

#### `main_evaluation_categories`
- Stores the three main evaluation categories
- Contains evaluation type (student_to_teacher, peer_to_peer, head_to_teacher)

#### `evaluation_sub_categories`
- Links to main categories via `main_category_id`
- Contains sub-category details and ordering

#### `evaluation_questionnaires`
- Links to sub-categories via `sub_category_id`
- Supports multiple question types with standardized rating scale
- Includes rating labels for 1-5 scale

#### `evaluation_sessions`
- Tracks individual evaluation sessions
- Supports different evaluator and evaluatee types
- Links to main categories and optional subjects/semesters

#### `evaluation_responses`
- Stores individual responses to questionnaires
- Supports different response types (rating, text, multiple choice, yes/no)
- Links to evaluation sessions and questionnaires

### Key Features

1. **Hierarchical Organization**: Clear structure from main categories → sub-categories → questionnaires
2. **Standardized Rating Scale**: Consistent 1-5 scale across all rating questions
3. **Flexible Question Types**: Support for rating, text, yes/no, and multiple choice questions
4. **Role-Based Evaluations**: Different evaluation types for different user roles
5. **Comprehensive Tracking**: Detailed session and response tracking
6. **Performance Optimization**: Indexed database for fast queries

## Implementation Files

### Database Files
- `database/evaluation_hierarchical_schema.sql` - Complete database schema
- `database/migrate_to_hierarchical.sql` - Migration script from old structure

### PHP Files
- `evaluations.php` - Main evaluation dashboard
- `sub-categories.php` - Sub-category management
- `questionnaires.php` - Questionnaire management

## Migration Process

### Step 1: Backup Existing Data
The migration script automatically creates backups of existing data:
- `backup_evaluation_categories`
- `backup_questionnaires`
- `backup_student_evaluations`
- `backup_evaluation_responses`

### Step 2: Create New Structure
Run the hierarchical schema script to create new tables:
```sql
SOURCE database/evaluation_hierarchical_schema.sql;
```

### Step 3: Migrate Data
Run the migration script to transfer existing data:
```sql
SOURCE database/migrate_to_hierarchical.sql;
```

### Step 4: Verify Migration
The migration script includes verification queries to ensure data integrity.

### Step 5: Cleanup (Optional)
After verifying the migration, you can remove backup tables:
```sql
DROP TABLE IF EXISTS backup_evaluation_categories;
DROP TABLE IF EXISTS backup_questionnaires;
DROP TABLE IF EXISTS backup_student_evaluations;
DROP TABLE IF EXISTS backup_evaluation_responses;
```

## Usage Guide

### For Guidance Officers

#### Managing Main Categories
1. Navigate to Evaluations dashboard
2. View the three main evaluation categories
3. Each category shows sub-category and questionnaire counts
4. Click "Start Evaluation" to begin a new evaluation session

#### Managing Sub-Categories
1. Click "View" on any main category
2. Add new sub-categories with descriptions
3. Set order numbers for proper sequencing
4. Edit or delete sub-categories as needed

#### Managing Questionnaires
1. Click "View Questionnaires" on any sub-category
2. Add new questionnaires with standardized question types
3. Use the 1-5 rating scale for rating questions
4. Set required/optional status for questions
5. Order questions appropriately

### For Evaluators

#### Conducting Evaluations
1. Select a main evaluation category
2. Choose specific sub-categories to evaluate
3. Answer questionnaires using the standardized rating scale
4. Provide text responses for open-ended questions
5. Submit completed evaluations

## Rating Scale Standards

### 1 - Poor
- Significantly below expectations
- Requires immediate improvement
- Not meeting basic requirements

### 2 - Good
- Below average performance
- Some areas need improvement
- Meeting minimum requirements

### 3 - Satisfactory
- Average performance
- Meeting expectations
- Adequate but could improve

### 4 - Very Satisfactory
- Above average performance
- Exceeding expectations
- Strong performance in most areas

### 5 - Excellent
- Outstanding performance
- Significantly exceeding expectations
- Exceptional quality and achievement

## Benefits of the New System

1. **Better Organization**: Clear hierarchical structure makes navigation intuitive
2. **Standardized Evaluation**: Consistent 1-5 rating scale across all evaluations
3. **Role-Based Access**: Different evaluation types for different user roles
4. **Comprehensive Tracking**: Detailed session and response tracking
5. **Scalable Design**: Easy to add new categories, sub-categories, and questions
6. **Performance Optimized**: Indexed database for fast queries and reporting
7. **Data Integrity**: Proper foreign key relationships and constraints

## Reporting and Analytics

The new system includes comprehensive views for reporting:

### `evaluation_summary_view`
- Complete evaluation session summaries
- Average ratings and response counts
- Breakdown by rating levels (Poor to Excellent)

### `sub_category_performance_view`
- Performance metrics by sub-category
- Average ratings and response distributions
- Comparative analysis across categories

### Stored Procedures
- `GetEvaluationStatsByMainCategory()` - Statistics by main category
- `GetSubCategoryPerformance()` - Performance by sub-category

## Technical Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- Modern web browser with JavaScript enabled

## Support and Maintenance

### Regular Maintenance
- Monitor database performance
- Review and update questionnaires as needed
- Backup evaluation data regularly
- Update user permissions and roles

### Troubleshooting
- Check database connections and permissions
- Verify foreign key relationships
- Monitor error logs for issues
- Test evaluation workflows regularly

## Future Enhancements

Potential improvements for future versions:
- Advanced analytics and reporting
- Automated evaluation scheduling
- Integration with learning management systems
- Mobile application support
- Real-time notifications
- Advanced data visualization

---

For technical support or questions about the hierarchical evaluation system, please contact the development team or refer to the system documentation. 