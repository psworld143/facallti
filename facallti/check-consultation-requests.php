<?php
session_start();
require_once '../config/database.php';

// Set headers for AJAX response
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Get department from query parameter
$department = $_GET['dept'] ?? '';

if (empty($department)) {
    echo json_encode([
        'success' => false,
        'error' => 'Department parameter is required'
    ]);
    exit;
}

try {
    // Check for pending consultation requests for teachers in the specified department
    $query = "
        SELECT 
            cr.id as request_id,
            cr.teacher_id,
            cr.student_name,
            cr.student_dept,
            cr.student_id,
            cr.status,
            cr.request_time,
            cr.session_id,
            f.first_name as teacher_first_name,
            f.last_name as teacher_last_name,
            f.image_url as teacher_image_url,
            TIMESTAMPDIFF(MINUTE, cr.request_time, NOW()) as minutes_ago
        FROM consultation_requests cr
        INNER JOIN faculty f ON cr.teacher_id = f.id
        WHERE f.department = ?
        AND cr.status = 'pending'
        AND cr.request_time > NOW() - INTERVAL 60 MINUTE
        ORDER BY cr.request_time ASC
    ";
    
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, 's', $department);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (!$result) {
        throw new Exception('Failed to execute query: ' . mysqli_error($conn));
    }
    
    $requests = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $requests[] = [
            'request_id' => $row['request_id'],
            'teacher_id' => $row['teacher_id'],
            'teacher_name' => $row['teacher_first_name'] . ' ' . $row['teacher_last_name'],
            'teacher_image_url' => $row['teacher_image_url'],
            'student_name' => $row['student_name'],
            'student_dept' => $row['student_dept'],
            'student_id' => $row['student_id'],
            'status' => $row['status'],
            'request_time' => $row['request_time'],
            'session_id' => $row['session_id'],
            'minutes_ago' => $row['minutes_ago']
        ];
    }
    
    mysqli_stmt_close($stmt);
    
    // Return response
    if (count($requests) > 0) {
        echo json_encode([
            'success' => true,
            'has_request' => true,
            'total_requests' => count($requests),
            'requests' => $requests
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'has_request' => false,
            'total_requests' => 0,
            'requests' => []
        ]);
    }
    
} catch (Exception $e) {
    // Log error for debugging
    error_log('Error in check-consultation-requests.php: ' . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred',
        'debug_message' => $e->getMessage()
    ]);
}

mysqli_close($conn);
?>
