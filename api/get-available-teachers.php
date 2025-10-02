<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

require_once '../config/database.php';

// Get parameters
$department = $_GET['dept'] ?? '';
$last_update = $_GET['last_update'] ?? '';

try {
    // Get current day and time
    $current_day = date('l'); // Monday, Tuesday, etc.
    $current_time = date('H:i:s'); // Current time in HH:MM:SS format
    
    // Since semesters table was removed during FaCallTi cleanup,
    // set default semester values
    $active_semester = 'First Semester';
    $active_academic_year = date('Y') . '-' . (date('Y') + 1);
    
    // Build the query for available teachers
    // Teachers are available regardless of their scheduled consultation hours once they scan
    $teachers_query = "SELECT DISTINCT 
                    f.id,
                    f.first_name,
                    f.last_name,
                    f.department,
                    f.position,
                    f.email,
                    f.bio,
                    f.image_url,
                    f.is_active,
                    COALESCE(MIN(ch.start_time), '08:00:00') as start_time,
                    COALESCE(MAX(ch.end_time), '17:00:00') as end_time,
                    COALESCE(GROUP_CONCAT(DISTINCT ch.room ORDER BY ch.room SEPARATOR ', '), 'Available') as room,
                    COALESCE(GROUP_CONCAT(DISTINCT ch.notes ORDER BY ch.notes SEPARATOR '; '), 'Available for consultation') as notes,
                    NOW() as scan_time,
                    COALESCE(ta.is_available, 0) as availability_status,
                    NOW() as last_activity
                   FROM faculty f 
                   LEFT JOIN consultation_hours ch ON f.id = ch.teacher_id 
                       AND ch.day_of_week = ? 
                       AND ch.is_active = 1
                   LEFT JOIN teacher_availability ta ON f.id = ta.teacher_id AND ta.availability_date = CURDATE()
                   WHERE f.is_active = 1 
                   AND f.department = ?
                   AND f.id NOT IN (
                       SELECT teacher_id 
                       FROM consultation_leave 
                       WHERE leave_date = CURDATE()
                   )
                   AND ta.is_available = 1
                   GROUP BY f.id, f.first_name, f.last_name, f.department, f.position, f.email, f.bio, f.image_url, f.is_active
                   ORDER BY f.first_name, f.last_name";
    
    $teachers_stmt = mysqli_prepare($conn, $teachers_query);
    mysqli_stmt_bind_param($teachers_stmt, "ss", $current_day, $department);
    mysqli_stmt_execute($teachers_stmt);
    $teachers_result = mysqli_stmt_get_result($teachers_stmt);
    
    $teachers = [];
    while ($row = mysqli_fetch_assoc($teachers_result)) {
        $teachers[] = [
            'id' => $row['id'],
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'department' => $row['department'],
            'position' => $row['position'],
            'email' => $row['email'],
            'bio' => $row['bio'],
            'image_url' => $row['image_url'],
            'start_time' => $row['start_time'],
            'end_time' => $row['end_time'],
            'room' => $row['room'],
            'notes' => $row['notes'],
            'availability_status' => $row['availability_status'],
            'scan_time' => $row['scan_time'],
            'last_activity' => $row['last_activity']
        ];
    }
    
    // If no teachers found for the department, try partial matching
    if (empty($teachers)) {
        $partial_query = "SELECT DISTINCT 
                        f.id,
                        f.first_name,
                        f.last_name,
                        f.department,
                        f.position,
                        f.email,
                        f.bio,
                        f.image_url,
                        f.is_active,
                        COALESCE(MIN(ch.start_time), '08:00:00') as start_time,
                        COALESCE(MAX(ch.end_time), '17:00:00') as end_time,
                        COALESCE(GROUP_CONCAT(DISTINCT ch.room ORDER BY ch.room SEPARATOR ', '), 'Available') as room,
                        COALESCE(GROUP_CONCAT(DISTINCT ch.notes ORDER BY ch.notes SEPARATOR '; '), 'Available for consultation') as notes,
                        NOW() as scan_time,
                        COALESCE(ta.is_available, 0) as availability_status,
                        NOW() as last_activity
                       FROM faculty f 
                       LEFT JOIN consultation_hours ch ON f.id = ch.teacher_id 
                           AND ch.day_of_week = ? 
                           AND ch.is_active = 1
                       LEFT JOIN teacher_availability ta ON f.id = ta.teacher_id AND ta.availability_date = CURDATE()
                       WHERE f.is_active = 1 
                       AND f.department LIKE ?
                       AND f.id NOT IN (
                           SELECT teacher_id 
                           FROM consultation_leave 
                           WHERE leave_date = CURDATE()
                       )
                       AND ta.is_available = 1
                       GROUP BY f.id, f.first_name, f.last_name, f.department, f.position, f.email, f.bio, f.image_url, f.is_active
                       ORDER BY f.first_name, f.last_name";
        
        $partial_stmt = mysqli_prepare($conn, $partial_query);
        $search_term = '%' . $department . '%';
        mysqli_stmt_bind_param($partial_stmt, "ss", $current_day, $search_term);
        mysqli_stmt_execute($partial_stmt);
        $partial_result = mysqli_stmt_get_result($partial_stmt);
        
        while ($row = mysqli_fetch_assoc($partial_result)) {
            $teachers[] = [
                'id' => $row['id'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'department' => $row['department'],
                'position' => $row['position'],
                'email' => $row['email'],
                'bio' => $row['bio'],
                'image_url' => $row['image_url'],
                'start_time' => $row['start_time'],
                'end_time' => $row['end_time'],
                'room' => $row['room'],
                'notes' => $row['notes'],
                'availability_status' => $row['availability_status'],
                'scan_time' => $row['scan_time'],
                'last_activity' => $row['last_activity']
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'teachers' => $teachers,
        'current_time' => $current_time,
        'current_day' => $current_day,
        'active_semester' => $active_semester,
        'active_academic_year' => $active_academic_year,
        'last_update' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("Get available teachers error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch teachers: ' . $e->getMessage()
    ]);
}
?>
