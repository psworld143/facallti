-- Delete faculty members who are not associated with any college
-- This script will identify and remove faculty whose department doesn't match any active college

-- First, let's see which faculty will be affected (DRY RUN)
SELECT 
    f.id,
    f.first_name,
    f.last_name,
    f.email,
    f.department,
    f.position,
    'Will be deleted' as action
FROM faculty f 
WHERE f.is_active = 1 
AND f.department NOT IN (
    SELECT name FROM colleges WHERE is_active = 1
);

-- Show the count of faculty that will be deleted
SELECT 
    COUNT(*) as faculty_to_delete
FROM faculty f 
WHERE f.is_active = 1 
AND f.department NOT IN (
    SELECT name FROM colleges WHERE is_active = 1
);

-- Show existing colleges for reference
SELECT 
    id,
    name as college_name
FROM colleges 
WHERE is_active = 1 
ORDER BY name;

-- ACTUAL DELETION (uncomment the lines below to execute)
-- WARNING: This will permanently delete faculty records!

/*
-- Delete faculty_details records first (due to foreign key constraint)
DELETE fd FROM faculty_details fd
INNER JOIN faculty f ON fd.faculty_id = f.id
WHERE f.is_active = 1 
AND f.department NOT IN (
    SELECT name FROM colleges WHERE is_active = 1
);

-- Delete faculty records
DELETE FROM faculty 
WHERE is_active = 1 
AND department NOT IN (
    SELECT name FROM colleges WHERE is_active = 1
);

-- Show remaining faculty after deletion
SELECT 
    COUNT(*) as remaining_faculty
FROM faculty 
WHERE is_active = 1;
*/
