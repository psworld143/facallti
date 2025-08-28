<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is faculty
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['leave_id']) || !is_numeric($input['leave_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing or invalid leave ID']);
    exit();
}

$leave_id = (int)$input['leave_id'];
$faculty_id = $_SESSION['user_id'];

// Get faculty information
$faculty_query = "SELECT e.id as employee_id FROM faculty f 
                  LEFT JOIN employees e ON f.email = e.email 
                  WHERE f.id = ?";
$faculty_stmt = mysqli_prepare($conn, $faculty_query);
mysqli_stmt_bind_param($faculty_stmt, 'i', $faculty_id);
mysqli_stmt_execute($faculty_stmt);
$faculty_result = mysqli_stmt_get_result($faculty_stmt);

if (mysqli_num_rows($faculty_result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Faculty not found']);
    exit();
}

$faculty_info = mysqli_fetch_assoc($faculty_result);

// Get leave request details (only for the faculty's own pending requests)
$query = "SELECT lr.*, e.first_name, e.last_name, lt.name as leave_type_name
          FROM leave_requests lr 
          JOIN employees e ON lr.employee_id = e.id 
          JOIN leave_types lt ON lr.leave_type_id = lt.id
          WHERE lr.id = ? AND lr.employee_id = ? AND lr.status = 'pending'";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'ii', $leave_id, $faculty_info['employee_id']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Leave request not found or not eligible for cancellation']);
    exit();
}

$leave = mysqli_fetch_assoc($result);

// Start transaction
mysqli_begin_transaction($conn);

try {
    // Update leave request status to cancelled
    $update_query = "UPDATE leave_requests SET 
                     status = 'cancelled', 
                     updated_at = NOW()
                     WHERE id = ?";
    
    $update_stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($update_stmt, 'i', $leave_id);
    
    if (!mysqli_stmt_execute($update_stmt)) {
        throw new Exception('Failed to cancel leave request');
    }

    // Create notification for department head (if assigned)
    if ($leave['department_head_id']) {
        $head_notification_title = 'Leave Request Cancelled';
        $head_notification_message = "Employee {$leave['first_name']} {$leave['last_name']} has cancelled their leave request for {$leave['total_days']} days ({$leave['leave_type_name']}) from {$leave['start_date']} to {$leave['end_date']}.";
        
        $head_notification_query = "INSERT INTO leave_notifications 
                                   (recipient_id, recipient_type, title, message, type, related_leave_request_id) 
                                   VALUES (?, 'department_head', ?, ?, 'leave_cancelled', ?)";
        $head_notification_stmt = mysqli_prepare($conn, $head_notification_query);
        mysqli_stmt_bind_param($head_notification_stmt, 'issi', $leave['department_head_id'], $head_notification_title, $head_notification_message, $leave_id);
        
        if (!mysqli_stmt_execute($head_notification_stmt)) {
            throw new Exception('Failed to create department head notification');
        }
    }

    // Create notification for faculty
    $faculty_notification_title = 'Leave Request Cancelled';
    $faculty_notification_message = "Your leave request for {$leave['total_days']} days ({$leave['leave_type_name']}) from {$leave['start_date']} to {$leave['end_date']} has been cancelled successfully.";
    
    $faculty_notification_query = "INSERT INTO leave_notifications 
                                   (recipient_id, recipient_type, title, message, type, related_leave_request_id) 
                                   VALUES (?, 'employee', ?, ?, 'leave_cancelled', ?)";
    $faculty_notification_stmt = mysqli_prepare($conn, $faculty_notification_query);
    mysqli_stmt_bind_param($faculty_notification_stmt, 'issi', $faculty_info['employee_id'], $faculty_notification_title, $faculty_notification_message, $leave_id);
    
    if (!mysqli_stmt_execute($faculty_notification_stmt)) {
        throw new Exception('Failed to create faculty notification');
    }

    // Commit transaction
    mysqli_commit($conn);

    // Log the action
    $log_message = "Faculty {$leave['first_name']} {$leave['last_name']} cancelled leave request #{$leave_id}";
    error_log($log_message);

    echo json_encode([
        'success' => true, 
        'message' => 'Leave request cancelled successfully'
    ]);

} catch (Exception $e) {
    // Rollback transaction
    mysqli_rollback($conn);
    
    error_log('Leave cancellation error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while cancelling the leave request']);
}
