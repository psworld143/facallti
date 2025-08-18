-- IntelliEVal System - Run Text Questionnaire Seeding
-- This script runs the complete text questionnaire seeding and verifies results

-- =====================================================
-- STEP 1: RUN THE COMPLETE TEXT QUESTIONNAIRE SEEDING
-- =====================================================

-- Source the complete text questionnaires script
SOURCE complete_text_questionnaires.sql;

-- =====================================================
-- STEP 2: VERIFY THE RESULTS
-- =====================================================

-- Show summary of text questions per sub-category
SELECT 
    esc.name as 'Sub-Category',
    COUNT(CASE WHEN eq.question_type = 'text' THEN 1 END) as 'Text Questions',
    COUNT(*) as 'Total Questions',
    ROUND((COUNT(CASE WHEN eq.question_type = 'text' THEN 1 END) / COUNT(*)) * 100, 1) as 'Text %'
FROM evaluation_sub_categories esc
LEFT JOIN evaluation_questionnaires eq ON esc.id = eq.sub_category_id
WHERE esc.main_category_id = 1  -- Student to Teacher evaluation
GROUP BY esc.id, esc.name
ORDER BY esc.order_number;

-- =====================================================
-- STEP 3: SHOW SAMPLE TEXT QUESTIONS FOR EACH SUB-CATEGORY
-- =====================================================

SELECT 
    esc.name as 'Sub-Category',
    eq.question_text as 'Text Question',
    eq.order_number as 'Order'
FROM evaluation_sub_categories esc
JOIN evaluation_questionnaires eq ON esc.id = eq.sub_category_id
WHERE esc.main_category_id = 1 
    AND eq.question_type = 'text'
ORDER BY esc.order_number, eq.order_number;

-- =====================================================
-- STEP 4: VERIFY NO DUPLICATE QUESTIONS
-- =====================================================

SELECT 
    sub_category_id,
    question_text,
    COUNT(*) as duplicate_count
FROM evaluation_questionnaires 
WHERE question_type = 'text'
GROUP BY sub_category_id, question_text
HAVING COUNT(*) > 1;

-- If no results above, then no duplicates exist 