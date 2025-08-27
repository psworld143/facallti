<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
require_once '../config/database.php';

// Get session ID from query parameter
$session_id = $_GET['session_id'] ?? '';

if (empty($session_id)) {
    echo json_encode([
        'success' => false,
        'error' => 'Session ID is required'
    ]);
    exit();
}

try {
    // Query the consultation_requests table for the specific session
    $query = "SELECT 
                cr.status,
                cr.teacher_id,
                cr.student_name,
                cr.student_dept,
                cr.request_time,
                cr.response_time,
                f.first_name,
                f.last_name
              FROM consultation_requests cr
              LEFT JOIN faculty f ON cr.teacher_id = f.id
              WHERE cr.session_id = ?
              ORDER BY cr.request_time DESC
              LIMIT 1";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $session_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $request = mysqli_fetch_assoc($result);
        
        $response = [
            'success' => true,
            'status' => $request['status'],
            'teacher_name' => $request['first_name'] . ' ' . $request['last_name'],
            'teacher_id' => $request['teacher_id'],
            'student_name' => $request['student_name'],
            'student_dept' => $request['student_dept'],
            'request_time' => $request['request_time'],
            'response_time' => $request['response_time']
        ];
        
        echo json_encode($response);
    } else {
        // No request found with this session ID
        echo json_encode([
            'success' => false,
            'error' => 'No consultation request found with this session ID',
            'status' => 'not_found'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}

mysqli_close($conn);
?>
