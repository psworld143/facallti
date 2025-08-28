<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a department head
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'head') {
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
$head_id = $_SESSION['user_id'];

// Validate action
if (!in_array($action, ['approve', 'reject'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
}

// Get department head information
$head_query = "SELECT h.*, u.first_name, u.last_name 
               FROM heads h 
               JOIN users u ON h.user_id = u.id 
               WHERE h.user_id = ? AND h.status = 'active'";
$head_stmt = mysqli_prepare($conn, $head_query);
mysqli_stmt_bind_param($head_stmt, 'i', $head_id);
mysqli_stmt_execute($head_stmt);
$head_result = mysqli_stmt_get_result($head_stmt);

if (mysqli_num_rows($head_result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Department head not found']);
    exit();
}

$head_info = mysqli_fetch_assoc($head_result);
$head_department = $head_info['department'];

// Get leave request details (only for the head's department)
$query = "SELECT lr.*, e.first_name, e.last_name, e.email, e.department, lt.name as leave_type_name
          FROM leave_requests lr 
          JOIN employees e ON lr.employee_id = e.id 
          JOIN leave_types lt ON lr.leave_type_id = lt.id
          WHERE lr.id = ? AND e.department = ? AND lr.status = 'pending'";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'is', $leave_id, $head_department);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Leave request not found or not ready for approval']);
    exit();
}

$leave = mysqli_fetch_assoc($result);

// Start transaction
mysqli_begin_transaction($conn);

try {
    // Update leave request
    $new_status = $action === 'approve' ? 'approved_by_head' : 'rejected';
    $department_head_approval = $action === 'approve' ? 'approved' : 'rejected';
    
    $update_query = "UPDATE leave_requests SET 
                     status = ?, 
                     department_head_approval = ?, 
                     department_head_id = ?, 
                     department_head_comment = ?, 
                     department_head_approved_at = NOW(),
                     updated_at = NOW()
                     WHERE id = ?";
    
    $update_stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($update_stmt, 'ssisi', $new_status, $department_head_approval, $head_id, $comment, $leave_id);
    
    if (!mysqli_stmt_execute($update_stmt)) {
        throw new Exception('Failed to update leave request');
    }

    // Create notification for employee
    $notification_title = $action === 'approve' ? 'Leave Request Approved by Department Head' : 'Leave Request Rejected by Department Head';
    $notification_message = $action === 'approve' 
        ? "Your leave request for {$leave['total_days']} days ({$leave['leave_type_name']}) has been approved by your department head and is now pending HR approval."
        : "Your leave request for {$leave['total_days']} days ({$leave['leave_type_name']}) has been rejected by your department head.";
    
    if ($comment) {
        $notification_message .= " Comment: " . $comment;
    }

    $employee_notification_query = "INSERT INTO leave_notifications 
                                   (recipient_id, recipient_type, title, message, type, related_leave_request_id) 
                                   VALUES (?, 'employee', ?, ?, ?, ?)";
    $employee_notification_stmt = mysqli_prepare($conn, $employee_notification_query);
    $notification_type = $action === 'approve' ? 'leave_approved' : 'leave_rejected';
    mysqli_stmt_bind_param($employee_notification_stmt, 'isssi', $leave['employee_id'], $notification_title, $notification_message, $notification_type, $leave_id);
    
    if (!mysqli_stmt_execute($employee_notification_stmt)) {
        throw new Exception('Failed to create employee notification');
    }

    // If approved, create notification for HR
    if ($action === 'approve') {
        // Get HR employees
        $hr_query = "SELECT id FROM employees WHERE employee_type = 'admin' AND is_active = 1";
        $hr_result = mysqli_query($conn, $hr_query);
        
        while ($hr_employee = mysqli_fetch_assoc($hr_result)) {
            $hr_notification_title = 'New Leave Request for HR Approval';
            $hr_notification_message = "Employee {$leave['first_name']} {$leave['last_name']} from {$leave['department']} has a leave request that has been approved by the department head and is now pending HR approval.";
            
            $hr_notification_query = "INSERT INTO leave_notifications 
                                     (recipient_id, recipient_type, title, message, type, related_leave_request_id) 
                                     VALUES (?, 'hr', ?, ?, 'leave_request', ?)";
            $hr_notification_stmt = mysqli_prepare($conn, $hr_notification_query);
            mysqli_stmt_bind_param($hr_notification_stmt, 'issi', $hr_employee['id'], $hr_notification_title, $hr_notification_message, $leave_id);
            
            if (!mysqli_stmt_execute($hr_notification_stmt)) {
                throw new Exception('Failed to create HR notification');
            }
        }
    }

    // Commit transaction
    mysqli_commit($conn);

    // Log the action
    $log_message = "Department Head " . ($action === 'approve' ? 'approved' : 'rejected') . " leave request #{$leave_id} for employee {$leave['first_name']} {$leave['last_name']}";
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
