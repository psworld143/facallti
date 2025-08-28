-- Execute faculty deletion for those not associated with any college
-- This script will permanently delete faculty records

-- Start transaction for safety
START TRANSACTION;

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

-- Show remaining faculty details
SELECT 
    id,
    first_name,
    last_name,
    department,
    position
FROM faculty 
WHERE is_active = 1
ORDER BY last_name, first_name;

-- Commit the transaction
COMMIT;
