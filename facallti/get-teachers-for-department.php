<?php
session_start();
require_once '../config/database.php';

// Set content type to JSON
header('Content-Type: application/json');

// Get selected department from URL parameter
$selected_department = $_GET['dept'] ?? '';

if (empty($selected_department)) {
    echo json_encode(['success' => false, 'error' => 'Department parameter is required']);
    exit();
}

// Get current day and time
$current_day = date('l'); // Monday, Tuesday, etc.
$current_time = date('H:i:s'); // Current time in HH:MM:SS format

// Get active semester and academic year
$semester_query = "SELECT name, academic_year FROM semesters WHERE status = 'active' LIMIT 1";
$semester_result = mysqli_query($conn, $semester_query);
$active_semester = null;
$active_academic_year = null;

if ($semester_result && mysqli_num_rows($semester_result) > 0) {
    $semester_row = mysqli_fetch_assoc($semester_result);
    $active_semester = $semester_row['name'];
    $active_academic_year = $semester_row['academic_year'];
}

// Get department information and all teachers with consultation hours today
$department_query = "SELECT DISTINCT 
                    f.id,
                    f.first_name,
                    f.last_name,
                    f.department,
                    f.position,
                    f.email,
                    f.bio,
                    f.image_url,
                    f.is_active,
                    ch.start_time,
                    ch.end_time,
                    ch.room,
                    ch.notes,
                    ta.scan_time,
                    ta.is_available as availability_status,
                    ta.last_activity
                   FROM faculty f 
                   LEFT JOIN consultation_hours ch ON f.id = ch.teacher_id AND ch.day_of_week = ? AND ch.is_active = 1
                   LEFT JOIN teacher_availability ta ON f.id = ta.teacher_id AND ta.availability_date = CURDATE()
                   WHERE f.department = ? 
                   AND f.is_active = 1
                   AND f.id NOT IN (
                       SELECT teacher_id 
                       FROM consultation_leave 
                       WHERE leave_date = CURDATE()
                   )
                   GROUP BY f.id, f.first_name, f.last_name, f.department, f.position, f.email, f.bio, f.image_url, f.is_active, ta.scan_time, ta.is_available, ta.last_activity
                   ORDER BY ta.is_available DESC, f.first_name, f.last_name";



$department_stmt = mysqli_prepare($conn, $department_query);
if ($department_stmt) {
    // Simplified parameter binding for the new query
    mysqli_stmt_bind_param($department_stmt, "ss", $current_day, $selected_department);
    mysqli_stmt_execute($department_stmt);
    $department_result = mysqli_stmt_get_result($department_stmt);
} else {
    // Fallback to simple query if prepared statement fails
    $fallback_query = "SELECT 
                        f.id,
                        f.first_name,
                        f.last_name,
                        f.department,
                        f.position,
                        f.email,
                        f.bio,
                        f.image_url,
                        f.is_active,
                        NULL as start_time,
                        NULL as end_time,
                        NULL as room,
                        NULL as notes,
                        ta.scan_time,
                        ta.is_available as availability_status,
                        ta.last_activity
                       FROM faculty f 
                       LEFT JOIN teacher_availability ta ON f.id = ta.teacher_id AND ta.availability_date = CURDATE()
                       WHERE f.department = ? AND f.is_active = 1
                       ORDER BY ta.is_available DESC, f.first_name, f.last_name";
    
    $fallback_stmt = mysqli_prepare($conn, $fallback_query);
    mysqli_stmt_bind_param($fallback_stmt, "s", $selected_department);
    mysqli_stmt_execute($fallback_stmt);
    $department_result = mysqli_stmt_get_result($fallback_stmt);
    

}

$department_teachers = [];
while ($row = mysqli_fetch_assoc($department_result)) {
    $department_teachers[] = $row;
}

// Return the data as JSON
echo json_encode([
    'success' => true,
    'teachers' => $department_teachers,
    'total_count' => count($department_teachers),
    'department' => $selected_department,
    'current_time' => $current_time,
    'current_day' => $current_day
]);

mysqli_close($conn);
?>
