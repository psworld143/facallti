<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Get department from request
$department = $_GET['dept'] ?? '';

if (!$department) {
    echo json_encode(['error' => 'Department required']);
    exit;
}

try {
    // Check for all pending consultation requests for this department in the last 10 minutes
    $query = "SELECT cr.id, cr.teacher_id, cr.student_name, cr.student_dept, cr.session_id, cr.request_time,
                     f.first_name, f.last_name, f.department,
                     TIMESTAMPDIFF(MINUTE, cr.request_time, NOW()) as minutes_ago
              FROM consultation_requests cr
              JOIN faculty f ON cr.teacher_id = f.id
              WHERE f.department = ? AND cr.status = 'pending' 
              AND cr.request_time > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
              ORDER BY cr.request_time ASC";
              
    $stmt = mysqli_prepare($conn, $query);
    
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, "s", $department);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $requests = [];
    $has_request = false;
    
    while ($request = mysqli_fetch_assoc($result)) {
        $requests[] = [
            'request_id' => $request['id'],
            'session_id' => $request['session_id'],
            'student_name' => $request['student_name'],
            'student_dept' => $request['student_dept'],
            'teacher_name' => $request['first_name'] . ' ' . $request['last_name'],
            'teacher_id' => $request['teacher_id'],
            'request_time' => $request['request_time'],
            'minutes_ago' => $request['minutes_ago']
        ];
        $has_request = true;
    }
    
    $response = [
        'has_request' => $has_request,
        'requests' => $requests,
        'total_requests' => count($requests),
        'timestamp' => date('Y-m-d H:i:s'),
        'department' => $department
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    // Log error to console instead of showing alert
    error_log('Check consultation requests error: ' . $e->getMessage());
    echo json_encode(['error' => 'Failed to check consultation requests']);
}
?>
