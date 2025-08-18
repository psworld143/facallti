# Text Type Questionnaires for Student to Teacher Evaluation

## Overview

This directory contains SQL scripts to seed comprehensive text type questionnaires for each evaluation sub-category in the Student to Teacher evaluation system. The scripts ensure that each sub-category has multiple text-based questions to gather detailed qualitative feedback from students.

## Files

### 1. `seed_text_questionnaires.sql`
- **Purpose**: Adds additional text type questions to existing questionnaires
- **Content**: 31 new text questions across 5 sub-categories
- **Usage**: Run after the main `seed_questionnaires.sql`

### 2. `complete_text_questionnaires.sql`
- **Purpose**: Comprehensive seeding with duplicate prevention
- **Content**: Complete set of text questions with EXISTS checks
- **Usage**: Safe to run multiple times (won't create duplicates)

### 3. `run_text_seeding.sql`
- **Purpose**: Executes seeding and verifies results
- **Content**: Runs seeding + verification queries
- **Usage**: Main execution script

## Evaluation Sub-Categories

### 1. Classroom Management (Sub-category ID: 1)
**Text Questions Added:**
- What suggestions do you have for improving classroom management?
- Describe a specific situation where the teacher handled classroom discipline effectively
- What specific classroom management strategies have you observed from this teacher?
- How does the teacher create a positive learning environment? Please provide examples
- What improvements would you suggest for the teacher's classroom management approach?
- Describe how the teacher handles transitions between different activities

**Total Questions:** 12 (7 original + 5 new text questions)

### 2. Teaching Skills (Sub-category ID: 2)
**Text Questions Added:**
- What teaching methods do you find most effective?
- Describe a lesson where the teacher's explanation was particularly clear and helpful
- What specific teaching methods or strategies do you find most effective in this class?
- How does the teacher adapt their teaching to accommodate different learning styles?
- Describe an example of how the teacher uses technology to enhance learning
- What suggestions do you have for improving the teacher's instructional methods?
- How does the teacher check for student understanding during lessons?

**Total Questions:** 14 (8 original + 6 new text questions)

### 3. Subject Knowledge (Sub-category ID: 3)
**Text Questions Added:**
- What topics would you like the teacher to explain better?
- Describe a time when the teacher demonstrated deep knowledge of the subject matter
- How does the teacher connect classroom concepts to real-world applications?
- What specific examples or analogies does the teacher use to explain complex topics?
- Describe how the teacher answers difficult questions from students
- What areas of the subject would you like the teacher to explain in more detail?
- How does the teacher stay current with developments in their field?

**Total Questions:** 13 (7 original + 6 new text questions)

### 4. Communication Skills (Sub-category ID: 4)
**Text Questions Added:**
- What communication improvements would you suggest?
- Describe how the teacher communicates complex ideas in simple terms
- What specific communication techniques does the teacher use effectively?
- How does the teacher encourage students to ask questions and participate?
- Describe how the teacher provides feedback on student work
- What communication barriers, if any, have you observed in this class?
- How does the teacher handle communication with students outside of class time?
- What specific improvements would you suggest for the teacher's communication style?

**Total Questions:** 15 (8 original + 7 new text questions)

### 5. Student Engagement (Sub-category ID: 5)
**Text Questions Added:**
- What activities do you find most engaging in this class?
- Describe a specific activity or lesson that was particularly engaging
- How does the teacher motivate students who seem disinterested?
- What types of interactive activities does the teacher use most effectively?
- Describe how the teacher encourages critical thinking and discussion
- What specific strategies does the teacher use to maintain student attention?
- How does the teacher recognize and respond to student interests and questions?
- What additional activities or approaches would make this class more engaging?

**Total Questions:** 15 (8 original + 7 new text questions)

## Usage Instructions

### Option 1: Quick Setup
```sql
-- Run the complete seeding script
SOURCE complete_text_questionnaires.sql;
```

### Option 2: Step-by-Step Setup
```sql
-- 1. Run the main questionnaires first (if not already done)
SOURCE seed_questionnaires.sql;

-- 2. Add additional text questions
SOURCE seed_text_questionnaires.sql;

-- 3. Verify results
SOURCE run_text_seeding.sql;
```

### Option 3: Full Verification
```sql
-- Run the complete verification script
SOURCE run_text_seeding.sql;
```

## Database Schema Requirements

The scripts require the following tables to exist:
- `main_evaluation_categories`
- `evaluation_sub_categories`
- `evaluation_questionnaires`

Make sure the hierarchical evaluation schema is set up before running these scripts.

## Question Characteristics

### Text Question Features:
- **Type**: `text` (open-ended responses)
- **Required**: `0` (optional responses)
- **Order**: Sequential numbering continuing from existing questions
- **Created By**: User ID `1` (assumes admin user exists)

### Question Design Principles:
1. **Specific**: Ask for concrete examples and situations
2. **Actionable**: Focus on improvements and suggestions
3. **Descriptive**: Encourage detailed responses
4. **Balanced**: Cover both positive feedback and areas for improvement
5. **Student-Centered**: Written from student perspective

## Verification Queries

After running the seeding scripts, you can verify the results with these queries:

### Count Questions by Type
```sql
SELECT 
    esc.name as 'Sub-Category',
    COUNT(CASE WHEN eq.question_type = 'text' THEN 1 END) as 'Text Questions',
    COUNT(*) as 'Total Questions'
FROM evaluation_sub_categories esc
LEFT JOIN evaluation_questionnaires eq ON esc.id = eq.sub_category_id
WHERE esc.main_category_id = 1
GROUP BY esc.id, esc.name
ORDER BY esc.order_number;
```

### Check for Duplicates
```sql
SELECT 
    sub_category_id,
    question_text,
    COUNT(*) as duplicate_count
FROM evaluation_questionnaires 
WHERE question_type = 'text'
GROUP BY sub_category_id, question_text
HAVING COUNT(*) > 1;
```

## Expected Results

After successful seeding, you should have:
- **Classroom Management**: 12 total questions (6 text questions)
- **Teaching Skills**: 14 total questions (7 text questions)
- **Subject Knowledge**: 13 total questions (7 text questions)
- **Communication Skills**: 15 total questions (8 text questions)
- **Student Engagement**: 15 total questions (8 text questions)

**Total Text Questions**: 36 across all sub-categories

## Notes

- All text questions are marked as optional (`required = 0`)
- Questions are designed to complement existing rating and yes/no questions
- The seeding scripts include duplicate prevention
- Questions follow a consistent format and tone
- All questions are student-focused and encourage detailed responses 