<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

try {
    // Get department parameter
    $selected_department = isset($_GET['department']) ? $_GET['department'] : '';
    
    // Build query based on whether department is selected
    if (!empty($selected_department)) {
        // Get all teachers from specific department (with grouped consultation hours)
        $query = "SELECT 
                    f.id,
                    f.first_name,
                    f.last_name,
                    f.department,
                    f.position,
                    f.email,
                    f.bio,
                    f.image_url,
                    f.is_active,
                    GROUP_CONCAT(
                        DISTINCT CONCAT(
                            TIME_FORMAT(ch.start_time, '%h:%i %p'), 
                            ' - ', 
                            TIME_FORMAT(ch.end_time, '%h:%i %p'),
                            IF(ch.room IS NOT NULL, CONCAT(' | ', ch.room), '')
                        ) 
                        ORDER BY ch.start_time 
                        SEPARATOR '; '
                    ) as consultation_times,
                    COUNT(DISTINCT ch.id) as consultation_count,
                    CASE WHEN COUNT(ch.id) > 0 THEN 1 ELSE 0 END as has_consultation
                   FROM faculty f 
                   LEFT JOIN consultation_hours ch ON f.id = ch.teacher_id 
                   AND ch.day_of_week = ? 
                   AND ch.is_active = 1
                   WHERE f.is_active = 1 
                   AND f.department = ?
                   AND f.id NOT IN (
                       SELECT teacher_id 
                       FROM consultation_leave 
                       WHERE leave_date = CURDATE()
                   )
                   GROUP BY f.id, f.first_name, f.last_name, f.department, f.position, f.email, f.bio, f.image_url, f.is_active
                   ORDER BY f.first_name, f.last_name";
        
        $stmt = mysqli_prepare($conn, $query);
        if ($stmt) {
            $current_day = date('l'); // Get current day name
            mysqli_stmt_bind_param($stmt, "ss", $current_day, $selected_department);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
        }
    } else {
        // Get all active teachers (with grouped consultation hours)
        $query = "SELECT 
                    f.id,
                    f.first_name,
                    f.last_name,
                    f.department,
                    f.position,
                    f.email,
                    f.bio,
                    f.image_url,
                    f.is_active,
                    GROUP_CONCAT(
                        DISTINCT CONCAT(
                            TIME_FORMAT(ch.start_time, '%h:%i %p'), 
                            ' - ', 
                            TIME_FORMAT(ch.end_time, '%h:%i %p'),
                            IF(ch.room IS NOT NULL, CONCAT(' | ', ch.room), '')
                        ) 
                        ORDER BY ch.start_time 
                        SEPARATOR '; '
                    ) as consultation_times,
                    COUNT(DISTINCT ch.id) as consultation_count,
                    CASE WHEN COUNT(ch.id) > 0 THEN 1 ELSE 0 END as has_consultation
                   FROM faculty f 
                   LEFT JOIN consultation_hours ch ON f.id = ch.teacher_id 
                   AND ch.day_of_week = ? 
                   AND ch.is_active = 1
                   WHERE f.is_active = 1 
                   AND f.id NOT IN (
                       SELECT teacher_id 
                       FROM consultation_leave 
                       WHERE leave_date = CURDATE()
                   )
                   GROUP BY f.id, f.first_name, f.last_name, f.department, f.position, f.email, f.bio, f.image_url, f.is_active
                   ORDER BY f.department, f.first_name, f.last_name";
        
        $stmt = mysqli_prepare($conn, $query);
        if ($stmt) {
            $current_day = date('l'); // Get current day name
            mysqli_stmt_bind_param($stmt, "s", $current_day);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
        }
    }
    
    $teachers = [];
    if (isset($result) && $result) {
        while ($row = mysqli_fetch_assoc($result)) {
            // Generate initials
            $first_initial = strtoupper(substr($row['first_name'], 0, 1));
            $last_initial = strtoupper(substr($row['last_name'], 0, 1));
            $initials = $first_initial . $last_initial;
            
            // Use the grouped consultation times from the query
            $consultation_time = $row['consultation_times'] ?: '';
            $has_consultation = (bool)$row['has_consultation'];
            
            $teachers[] = [
                'id' => $row['id'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'full_name' => $row['first_name'] . ' ' . $row['last_name'],
                'department' => $row['department'],
                'position' => $row['position'],
                'email' => $row['email'],
                'bio' => $row['bio'],
                'image_url' => $row['image_url'],
                'initials' => $initials,
                'consultation_time' => $consultation_time,
                'has_consultation' => $has_consultation,
                'consultation_count' => (int)$row['consultation_count'],
                'consultation_active' => $has_consultation
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'teachers' => $teachers,
        'department' => $selected_department,
        'total_count' => count($teachers)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'teachers' => [],
        'total_count' => 0
    ]);
}

mysqli_close($conn);
?>
