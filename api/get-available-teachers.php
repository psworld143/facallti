<?php
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Set timezone
if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set('Asia/Manila');
}

require_once '../config/database.php';

try {
    // Get parameters
    $selected_department = $_GET['dept'] ?? '';
    $last_update = $_GET['last_update'] ?? '';
    
    // Get current day of week and time
    $current_day = date('l'); // Returns Monday, Tuesday, etc.
    $current_time = date('H:i:s'); // Current time in HH:MM:SS format
    
    // If PHP time seems wrong, use system time as fallback
    if (function_exists('shell_exec')) {
        $system_day = trim(shell_exec('date +%A'));
        $system_time = trim(shell_exec('date +%H:%M:%S'));
        $system_date = trim(shell_exec('date +%Y-%m-%d'));
        
        // Check if there's a significant time difference (more than 1 hour)
        $php_timestamp = strtotime(date('Y-m-d H:i:s'));
        $system_timestamp = strtotime($system_date . ' ' . $system_time);
        $time_diff = abs($php_timestamp - $system_timestamp);
        
        if ($time_diff > 3600) { // More than 1 hour difference
            $current_day = $system_day;
            $current_time = $system_time;
        }
    }
    
    // Get active semester
    $semester_query = "SELECT name, academic_year FROM semesters WHERE status = 'active' LIMIT 1";
    $semester_result = mysqli_query($conn, $semester_query);
    $active_semester = null;
    $active_academic_year = null;
    
    if ($semester_result && mysqli_num_rows($semester_result) > 0) {
        $semester_row = mysqli_fetch_assoc($semester_result);
        $active_semester = $semester_row['name'];
        $active_academic_year = $semester_row['academic_year'];
    }
    
    // Build the main query - Teachers are available regardless of scheduled hours once they scan
    $teachers_query = "SELECT 
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
                        ta.scan_time,
                        ta.last_activity,
                        ta.created_at as availability_created
                       FROM faculty f 
                       INNER JOIN teacher_availability ta ON f.id = ta.teacher_id
                       LEFT JOIN consultation_hours ch ON f.id = ch.teacher_id 
                           AND ch.day_of_week = ? 
                           AND ch.is_active = 1
                           " . ($active_semester ? "AND ch.semester = ?" : "") . "
                           " . ($active_academic_year ? "AND ch.academic_year = ?" : "") . "
                       WHERE f.is_active = 1 
                       AND f.id NOT IN (
                           SELECT teacher_id 
                           FROM consultation_leave 
                           WHERE leave_date = CURDATE()
                       )
                       AND ta.availability_date = CURDATE()
                       AND ta.status = 'available'";
    
    // Add department filter if specified
    if (!empty($selected_department)) {
        $teachers_query .= " AND f.department = ?";
    }
    
    $teachers_query .= " GROUP BY f.id, f.first_name, f.last_name, f.department, f.position, f.email, f.bio, f.image_url, f.is_active, ta.scan_time, ta.last_activity, ta.created_at
                        ORDER BY ta.scan_time DESC, f.first_name, f.last_name";
    
    $teachers_stmt = mysqli_prepare($conn, $teachers_query);
    
    if ($teachers_stmt) {
        // Build parameter types and values dynamically
        $param_types = "s";
        $param_values = [$current_day];
        
        if ($active_semester) {
            $param_types .= "s";
            $param_values[] = $active_semester;
        }
        if ($active_academic_year) {
            $param_types .= "s";
            $param_values[] = $active_academic_year;
        }
        if (!empty($selected_department)) {
            $param_types .= "s";
            $param_values[] = $selected_department;
        }
        
        mysqli_stmt_bind_param($teachers_stmt, $param_types, ...$param_values);
        mysqli_stmt_execute($teachers_stmt);
        $teachers_result = mysqli_stmt_get_result($teachers_stmt);
        
        $teachers = [];
        while ($row = mysqli_fetch_assoc($teachers_result)) {
            $teachers[] = $row;
        }
        
        // If no teachers found and department is specified, try fallback approaches
        if (empty($teachers) && !empty($selected_department)) {
            // First try partial matching for the department
            $partial_query = "SELECT 
                                f.id,
                                f.first_name,
                                f.last_name,
                                f.department,
                                f.position,
                                f.email,
                                f.bio,
                                f.image_url,
                                f.is_active,
                                MIN(ch.start_time) as start_time,
                                MAX(ch.end_time) as end_time,
                                GROUP_CONCAT(DISTINCT ch.room ORDER BY ch.room SEPARATOR ', ') as room,
                                GROUP_CONCAT(DISTINCT ch.notes ORDER BY ch.notes SEPARATOR '; ') as notes,
                                ta.scan_time,
                                ta.last_activity,
                                ta.created_at as availability_created
                               FROM faculty f 
                               INNER JOIN consultation_hours ch ON f.id = ch.teacher_id
                               INNER JOIN teacher_availability ta ON f.id = ta.teacher_id
                               WHERE f.is_active = 1 
                               AND f.department LIKE ?
                               AND ch.day_of_week = ?
                               AND ch.is_active = 1
                               AND ch.start_time <= ?
                               AND ch.end_time >= ?
                               " . ($active_semester ? "AND ch.semester = ?" : "") . "
                               " . ($active_academic_year ? "AND ch.academic_year = ?" : "") . "
                               AND f.id NOT IN (
                                   SELECT teacher_id 
                                   FROM consultation_leave 
                                   WHERE leave_date = CURDATE()
                               )
                               AND ta.availability_date = CURDATE()
                               AND ta.status = 'available'
                               GROUP BY f.id, f.first_name, f.last_name, f.department, f.position, f.email, f.bio, f.image_url, f.is_active, ta.scan_time, ta.last_activity, ta.created_at
                               ORDER BY ta.scan_time DESC, f.first_name, f.last_name";
            
            $partial_stmt = mysqli_prepare($conn, $partial_query);
            if ($partial_stmt) {
                $search_term = '%' . $selected_department . '%';
                
                $param_types = "ssss";
                $param_values = [$search_term, $current_day, $current_time, $current_time];
                
                if ($active_semester) {
                    $param_types .= "s";
                    $param_values[] = $active_semester;
                }
                if ($active_academic_year) {
                    $param_types .= "s";
                    $param_values[] = $active_academic_year;
                }
                
                mysqli_stmt_bind_param($partial_stmt, $param_types, ...$param_values);
                mysqli_stmt_execute($partial_stmt);
                $partial_result = mysqli_stmt_get_result($partial_stmt);
                
                while ($row = mysqli_fetch_assoc($partial_result)) {
                    $teachers[] = $row;
                }
            }
            
            // If still no teachers found after partial matching, show all available teachers
            if (empty($teachers)) {
                $all_teachers_query = "SELECT 
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
                                        ta.scan_time,
                                        ta.last_activity,
                                        ta.created_at as availability_created
                                       FROM faculty f 
                                       INNER JOIN teacher_availability ta ON f.id = ta.teacher_id
                                       LEFT JOIN consultation_hours ch ON f.id = ch.teacher_id 
                                           AND ch.day_of_week = ? 
                                           AND ch.is_active = 1
                                           " . ($active_semester ? "AND ch.semester = ?" : "") . "
                                           " . ($active_academic_year ? "AND ch.academic_year = ?" : "") . "
                                       WHERE f.is_active = 1 
                                       AND f.id NOT IN (
                                           SELECT teacher_id 
                                           FROM consultation_leave 
                                           WHERE leave_date = CURDATE()
                                       )
                                       AND ta.availability_date = CURDATE()
                                       AND ta.status = 'available'
                                       GROUP BY f.id, f.first_name, f.last_name, f.department, f.position, f.email, f.bio, f.image_url, f.is_active, ta.scan_time, ta.last_activity, ta.created_at
                                       ORDER BY ta.scan_time DESC, f.first_name, f.last_name";
                
                $all_teachers_stmt = mysqli_prepare($conn, $all_teachers_query);
                if ($all_teachers_stmt) {
                    // Build parameter types and values dynamically
                    $param_types = "s";
                    $param_values = [$current_day];
                    
                    if ($active_semester) {
                        $param_types .= "s";
                        $param_values[] = $active_semester;
                    }
                    if ($active_academic_year) {
                        $param_types .= "s";
                        $param_values[] = $active_academic_year;
                    }
                    
                    mysqli_stmt_bind_param($all_teachers_stmt, $param_types, ...$param_values);
                    mysqli_stmt_execute($all_teachers_stmt);
                    $all_teachers_result = mysqli_stmt_get_result($all_teachers_stmt);
                    
                    while ($row = mysqli_fetch_assoc($all_teachers_result)) {
                        $teachers[] = $row;
                    }
                }
            }
        }
        
        // If still no teachers and no department specified, get all available
        if (empty($teachers) && empty($selected_department)) {
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
                                COALESCE(MIN(ch.start_time), '08:00:00') as start_time,
                                COALESCE(MAX(ch.end_time), '17:00:00') as end_time,
                                COALESCE(GROUP_CONCAT(DISTINCT ch.room ORDER BY ch.room SEPARATOR ', '), 'Available') as room,
                                COALESCE(GROUP_CONCAT(DISTINCT ch.notes ORDER BY ch.notes SEPARATOR '; '), 'Available for consultation') as notes,
                                ta.scan_time,
                                ta.last_activity,
                                ta.created_at as availability_created
                               FROM faculty f 
                               INNER JOIN teacher_availability ta ON f.id = ta.teacher_id
                               LEFT JOIN consultation_hours ch ON f.id = ch.teacher_id 
                                   AND ch.day_of_week = ? 
                                   AND ch.is_active = 1
                                   " . ($active_semester ? "AND ch.semester = ?" : "") . "
                                   " . ($active_academic_year ? "AND ch.academic_year = ?" : "") . "
                               WHERE f.is_active = 1 
                               AND f.id NOT IN (
                                   SELECT teacher_id 
                                   FROM consultation_leave 
                                   WHERE leave_date = CURDATE()
                               )
                               AND ta.availability_date = CURDATE()
                               AND ta.status = 'available'
                               GROUP BY f.id, f.first_name, f.last_name, f.department, f.position, f.email, f.bio, f.image_url, f.is_active, ta.scan_time, ta.last_activity, ta.created_at
                               ORDER BY ta.scan_time DESC, f.department, f.first_name, f.last_name";
            
            $fallback_stmt = mysqli_prepare($conn, $fallback_query);
            if ($fallback_stmt) {
                $param_types = "s";
                $param_values = [$current_day];
                
                if ($active_semester) {
                    $param_types .= "s";
                    $param_values[] = $active_semester;
                }
                if ($active_academic_year) {
                    $param_types .= "s";
                    $param_values[] = $active_academic_year;
                }
                
                mysqli_stmt_bind_param($fallback_stmt, $param_types, ...$param_values);
                mysqli_stmt_execute($fallback_stmt);
                $fallback_result = mysqli_stmt_get_result($fallback_stmt);
                
                while ($row = mysqli_fetch_assoc($fallback_result)) {
                    $teachers[] = $row;
                }
            }
        }
        
        // Return successful response
        echo json_encode([
            'success' => true,
            'teachers' => $teachers,
            'count' => count($teachers),
            'current_time' => date('Y-m-d H:i:s'),
            'current_day' => $current_day,
            'department' => $selected_department,
            'last_update' => date('Y-m-d H:i:s')
        ]);
        
    } else {
        throw new Exception('Failed to prepare database query');
    }
    
} catch (Exception $e) {
    error_log('Error in get-available-teachers.php: ' . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch available teachers',
        'debug' => $e->getMessage()
    ]);
}
?>
