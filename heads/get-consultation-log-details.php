<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in and has head role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'head') {
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

$log_id = $_GET['id'] ?? null;

if (!$log_id) {
    echo json_encode(['error' => 'Log ID is required']);
    exit();
}

try {
    // Get head information to ensure they can only view logs from their department
    $head_query = "SELECT h.* FROM heads h WHERE h.user_id = ?";
    $head_stmt = mysqli_prepare($conn, $head_query);
    mysqli_stmt_bind_param($head_stmt, "i", $_SESSION['user_id']);
    mysqli_stmt_execute($head_stmt);
    $head_result = mysqli_stmt_get_result($head_stmt);
    $head_info = mysqli_fetch_assoc($head_result);

    if (!$head_info) {
        echo json_encode(['error' => 'Head information not found']);
        exit();
    }

    // Get consultation log details with teacher information
    $query = "SELECT 
                cr.id,
                cr.student_name,
                cr.student_dept,
                cr.request_time,
                cr.response_time,
                cr.response_duration_seconds,
                cr.status,
                cr.decline_reason,
                f.first_name,
                f.last_name,
                f.email,
                f.department
              FROM consultation_requests cr
              JOIN faculty f ON cr.teacher_id = f.id
              WHERE cr.id = ? AND f.department = ?";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "is", $log_id, $head_info['department']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $log = mysqli_fetch_assoc($result);

    if (!$log) {
        echo json_encode(['error' => 'Consultation log not found or access denied']);
        exit();
    }

    // Format response duration
    $response_duration_formatted = '';
    if ($log['response_duration_seconds'] && $log['response_duration_seconds'] > 0) {
        // Duration is stored in seconds
        $duration_seconds = $log['response_duration_seconds'];
        
        if ($duration_seconds < 60) {
            $response_duration_formatted = $duration_seconds . ' seconds';
        } elseif ($duration_seconds < 3600) {
            $minutes = floor($duration_seconds / 60);
            $seconds = $duration_seconds % 60;
            $response_duration_formatted = $minutes . ' min ' . $seconds . ' sec';
        } else {
            $hours = floor($duration_seconds / 3600);
            $minutes = floor(($duration_seconds % 3600) / 60);
            $response_duration_formatted = $hours . 'h ' . $minutes . 'm';
        }
    }

    // Format dates
    $request_time_formatted = $log['request_time'] ? date('M j, Y g:i A', strtotime($log['request_time'])) : 'N/A';
    $response_time_formatted = $log['response_time'] ? date('M j, Y g:i A', strtotime($log['response_time'])) : 'N/A';

    $response = [
        'success' => true,
        'teacher_name' => $log['first_name'] . ' ' . $log['last_name'],
        'teacher_email' => $log['email'],
        'teacher_department' => $log['department'],
        'student_name' => $log['student_name'],
        'student_dept' => $log['student_dept'],
        'request_time' => $request_time_formatted,
        'response_time' => $response_time_formatted,
        'response_duration_formatted' => $response_duration_formatted,
        'status' => $log['status'],
        'decline_reason' => $log['decline_reason']
    ];

    echo json_encode($response);

} catch (Exception $e) {
    error_log('Error in get-consultation-log-details.php: ' . $e->getMessage());
    echo json_encode(['error' => 'Failed to retrieve consultation log details']);
}
?>
