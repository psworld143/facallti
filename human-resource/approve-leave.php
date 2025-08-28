<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is HR
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hr') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['leave_id']) || !isset($input['action'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$leave_id = (int)$input['leave_id'];
$action = $input['action'];
$comment = $input['comment'] ?? '';
$hr_id = $_SESSION['user_id'];

// Validate action
if (!in_array($action, ['approve', 'reject'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
}

// Get leave request details
$query = "SELECT lr.*, e.department, e.first_name, e.last_name, e.email 
          FROM leave_requests lr 
          JOIN employees e ON lr.employee_id = e.id 
          WHERE lr.id = ? AND lr.status = 'approved_by_head'";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'i', $leave_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Leave request not found or not ready for HR approval']);
    exit();
}

$leave = mysqli_fetch_assoc($result);

// Start transaction
mysqli_begin_transaction($conn);

try {
    // Update leave request
    $new_status = $action === 'approve' ? 'approved_by_hr' : 'rejected';
    $hr_approval = $action === 'approve' ? 'approved' : 'rejected';
    
    $update_query = "UPDATE leave_requests SET 
                     status = ?, 
                     hr_approval = ?, 
                     hr_approver_id = ?, 
                     hr_comment = ?, 
                     hr_approved_at = NOW(),
                     updated_at = NOW()
                     WHERE id = ?";
    
    $update_stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($update_stmt, 'ssisi', $new_status, $hr_approval, $hr_id, $comment, $leave_id);
    
    if (!mysqli_stmt_execute($update_stmt)) {
        throw new Exception('Failed to update leave request');
    }

    // If approved, update leave balance
    if ($action === 'approve') {
        $balance_query = "UPDATE leave_balances SET 
                         used_days = used_days + ? 
                         WHERE employee_id = ? AND leave_type_id = ? AND year = ?";
        $balance_stmt = mysqli_prepare($conn, $balance_query);
        mysqli_stmt_bind_param($balance_stmt, 'diii', $leave['total_days'], $leave['employee_id'], $leave['leave_type_id'], date('Y'));
        
        if (!mysqli_stmt_execute($balance_stmt)) {
            throw new Exception('Failed to update leave balance');
        }
    }

    // Create notification for employee
    $notification_title = $action === 'approve' ? 'Leave Request Approved' : 'Leave Request Rejected';
    $notification_message = $action === 'approve' 
        ? "Your leave request for {$leave['total_days']} days has been approved by HR."
        : "Your leave request for {$leave['total_days']} days has been rejected by HR.";
    
    if ($comment) {
        $notification_message .= " Comment: " . $comment;
    }

    $notification_query = "INSERT INTO leave_notifications 
                          (recipient_id, recipient_type, title, message, type, related_leave_request_id) 
                          VALUES (?, 'employee', ?, ?, ?, ?)";
    $notification_stmt = mysqli_prepare($conn, $notification_query);
    $notification_type = $action === 'approve' ? 'leave_approved' : 'leave_rejected';
    mysqli_stmt_bind_param($notification_stmt, 'isssi', $leave['employee_id'], $notification_title, $notification_message, $notification_type, $leave_id);
    
    if (!mysqli_stmt_execute($notification_stmt)) {
        throw new Exception('Failed to create notification');
    }

    // Commit transaction
    mysqli_commit($conn);

    // Log the action
    $log_message = "HR " . ($action === 'approve' ? 'approved' : 'rejected') . " leave request #{$leave_id} for employee {$leave['first_name']} {$leave['last_name']}";
    error_log($log_message);

    echo json_encode([
        'success' => true, 
        'message' => 'Leave request ' . ($action === 'approve' ? 'approved' : 'rejected') . ' successfully'
    ]);

} catch (Exception $e) {
    // Rollback transaction
    mysqli_rollback($conn);
    
    error_log('Leave approval error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while processing the request']);
}
