-- IntelliEVal - Remove Unlinked Categories SQL Script
-- This script removes categories from evaluation_categories that are not linked to main categories
-- Run this script directly in your database

-- =====================================================
-- SAFETY CHECK: Create backup first
-- =====================================================

-- Create backup of evaluation_categories table
CREATE TABLE IF NOT EXISTS evaluation_categories_backup AS 
SELECT * FROM evaluation_categories;

-- Create backup of questionnaires table
CREATE TABLE IF NOT EXISTS questionnaires_backup AS 
SELECT * FROM questionnaires;

-- Create backup of student_evaluations table
CREATE TABLE IF NOT EXISTS student_evaluations_backup AS 
SELECT * FROM student_evaluations;

-- =====================================================
-- STEP 1: Identify unlinked categories
-- =====================================================

-- Show unlinked categories before removal
SELECT 
    ec.id,
    ec.name,
    ec.description,
    ec.status,
    ec.created_at,
    (SELECT COUNT(*) FROM questionnaires q WHERE q.category_id = ec.id AND q.status = 'active') as questionnaire_count,
    (SELECT COUNT(*) FROM student_evaluations se WHERE se.category_id = ec.id) as evaluation_count
FROM evaluation_categories ec
LEFT JOIN evaluation_sub_categories esc ON ec.name = esc.name
WHERE esc.id IS NULL
ORDER BY ec.created_at DESC;

-- =====================================================
-- STEP 2: Remove unlinked categories (SAFE VERSION)
-- =====================================================

-- Only remove categories that have NO questionnaires and NO evaluations
DELETE ec FROM evaluation_categories ec
LEFT JOIN evaluation_sub_categories esc ON ec.name = esc.name
LEFT JOIN questionnaires q ON ec.id = q.category_id AND q.status = 'active'
LEFT JOIN student_evaluations se ON ec.id = se.category_id
WHERE esc.id IS NULL 
  AND q.id IS NULL 
  AND se.id IS NULL;

-- =====================================================
-- STEP 3: Verify results
-- =====================================================

-- Show remaining categories
SELECT 
    'Remaining Categories' as status,
    COUNT(*) as count
FROM evaluation_categories;

-- Show categories that are now properly linked
SELECT 
    'Linked Categories' as status,
    COUNT(*) as count
FROM evaluation_categories ec
JOIN evaluation_sub_categories esc ON ec.name = esc.name;

-- Show any remaining unlinked categories (should be 0 if all were removed)
SELECT 
    'Still Unlinked Categories' as status,
    COUNT(*) as count
FROM evaluation_categories ec
LEFT JOIN evaluation_sub_categories esc ON ec.name = esc.name
WHERE esc.id IS NULL;

-- =====================================================
-- STEP 4: Show detailed results
-- =====================================================

-- Show all remaining categories with their link status
SELECT 
    ec.id,
    ec.name,
    ec.description,
    ec.status,
    CASE 
        WHEN esc.id IS NOT NULL THEN 'Linked to Main Category'
        ELSE 'Unlinked (has data)'
    END as link_status,
    (SELECT COUNT(*) FROM questionnaires q WHERE q.category_id = ec.id AND q.status = 'active') as questionnaire_count,
    (SELECT COUNT(*) FROM student_evaluations se WHERE se.category_id = ec.id) as evaluation_count
FROM evaluation_categories ec
LEFT JOIN evaluation_sub_categories esc ON ec.name = esc.name
ORDER BY 
    CASE WHEN esc.id IS NULL THEN 1 ELSE 0 END DESC,
    ec.name ASC;

-- =====================================================
-- OPTIONAL: Force remove all unlinked categories (DANGEROUS)
-- =====================================================
-- WARNING: This will remove ALL unlinked categories, even those with data
-- Uncomment the lines below ONLY if you want to force remove everything

/*
-- Force remove all unlinked categories (DANGEROUS - removes even categories with data)
DELETE ec FROM evaluation_categories ec
LEFT JOIN evaluation_sub_categories esc ON ec.name = esc.name
WHERE esc.id IS NULL;

-- Verify all unlinked categories are gone
SELECT 
    'Force Removed - Remaining Unlinked' as status,
    COUNT(*) as count
FROM evaluation_categories ec
LEFT JOIN evaluation_sub_categories esc ON ec.name = esc.name
WHERE esc.id IS NULL;
*/

-- =====================================================
-- CLEANUP: Remove backup tables (optional)
-- =====================================================
-- Uncomment the lines below if you want to remove the backup tables
-- Only do this after confirming everything is working correctly

/*
DROP TABLE IF EXISTS evaluation_categories_backup;
DROP TABLE IF EXISTS questionnaires_backup;
DROP TABLE IF EXISTS student_evaluations_backup;
*/ 