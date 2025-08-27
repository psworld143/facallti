<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Get request data
$session_id = $_GET['session_id'] ?? null;

if (!$session_id) {
    echo json_encode(['error' => 'Session ID required']);
    exit;
}

try {
    // Check consultation request status
    $query = "SELECT id, teacher_id, student_name, student_dept, status, request_time, response_time, start_time, end_time, duration_minutes
              FROM consultation_requests 
              WHERE session_id = ?
              ORDER BY request_time DESC 
              LIMIT 1";
              
    $stmt = mysqli_prepare($conn, $query);
    
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, "s", $session_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $request = mysqli_fetch_assoc($result);
    
    if (!$request) {
        echo json_encode(['error' => 'Consultation request not found']);
        exit;
    }
    
    // Get teacher information
    $teacher_query = "SELECT first_name, last_name, department, position FROM faculty WHERE id = ?";
    $teacher_stmt = mysqli_prepare($conn, $teacher_query);
    mysqli_stmt_bind_param($teacher_stmt, "i", $request['teacher_id']);
    mysqli_stmt_execute($teacher_stmt);
    $teacher_result = mysqli_stmt_get_result($teacher_stmt);
    $teacher = mysqli_fetch_assoc($teacher_result);
    
    $response = [
        'success' => true,
        'request' => $request,
        'teacher' => $teacher,
        'status_message' => getStatusMessage($request['status']),
        'can_proceed' => in_array($request['status'], ['accepted', 'in_progress', 'completed'])
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    // Log error to console instead of showing alert
    error_log('Check request status error: ' . $e->getMessage());
    echo json_encode(['error' => 'Failed to check request status']);
}

function getStatusMessage($status) {
    switch ($status) {
        case 'pending':
            return 'Waiting for teacher to accept your request...';
        case 'accepted':
            return 'Your request has been accepted! You can now join the consultation.';
        case 'in_progress':
            return 'Consultation is currently in progress.';
        case 'completed':
            return 'Consultation has been completed.';
        case 'declined':
            return 'Your request was declined by the teacher.';
        case 'cancelled':
            return 'Consultation was cancelled.';
        default:
            return 'Unknown status.';
    }
}
?>
