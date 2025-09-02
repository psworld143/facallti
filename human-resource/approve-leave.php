<?php
session_start();
require_once '../includes/error_handler.php';
require_once '../config/database.php';

// Check if user is logged in and is HR
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'human_resource') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$leave_id = $input['leave_id'] ?? '';
$table = $input['table'] ?? '';
$action = $input['action'] ?? '';
$reason = $input['reason'] ?? '';

if (empty($leave_id) || empty($table) || empty($action)) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

// Get leave request details based on table
$leave = null;

if ($table === 'employee') {
    // Try employee leave requests table
    $employee_query = "SELECT elr.*, e.department, e.first_name, e.last_name, e.email 
                       FROM employee_leave_requests elr 
                       JOIN employees e ON elr.employee_id = e.id 
                       WHERE elr.id = ? AND elr.status = 'pending'";
    $employee_stmt = mysqli_prepare($conn, $employee_query);
    mysqli_stmt_bind_param($employee_stmt, 'i', $leave_id);
    mysqli_stmt_execute($employee_stmt);
    $employee_result = mysqli_stmt_get_result($employee_stmt);
    
    if (mysqli_num_rows($employee_result) > 0) {
        $leave = mysqli_fetch_assoc($employee_result);
    }
} else if ($table === 'faculty') {
    // Try faculty leave requests table - only allow approval if already approved by head
    $faculty_query = "SELECT flr.*, f.department, f.first_name, f.last_name, f.email 
                      FROM faculty_leave_requests flr 
                      JOIN faculty f ON flr.faculty_id = f.id 
                      WHERE flr.id = ? AND flr.status = 'approved_by_head'";
    $faculty_stmt = mysqli_prepare($conn, $faculty_query);
    mysqli_stmt_bind_param($faculty_stmt, 'i', $leave_id);
    mysqli_stmt_execute($faculty_stmt);
    $faculty_result = mysqli_stmt_get_result($faculty_stmt);
    
    if (mysqli_num_rows($faculty_result) > 0) {
        $leave = mysqli_fetch_assoc($faculty_result);
    }
}

if (!$leave) {
    echo json_encode(['success' => false, 'message' => 'Leave request not found or not ready for approval']);
    exit();
}

$hr_id = $_SESSION['user_id'];
$current_time = date('Y-m-d H:i:s');

// Update leave request based on action and table
if ($action === 'approve') {
    $new_status = 'approved_by_hr';
    $hr_approval = 'approved';
    $hr_comment = 'Approved by HR';
} else if ($action === 'reject') {
    $new_status = 'rejected';
    $hr_approval = 'rejected';
    $hr_comment = $reason ?: 'Rejected by HR';
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
}

// Update the appropriate table
if ($table === 'employee') {
    $update_query = "UPDATE employee_leave_requests 
                     SET status = ?, hr_approval = ?, hr_comment = ?, hr_approver_id = ?, hr_approved_at = ?, updated_at = NOW() 
                     WHERE id = ?";
} else {
    $update_query = "UPDATE faculty_leave_requests 
                     SET status = ?, hr_approval = ?, hr_comment = ?, hr_approver_id = ?, hr_approved_at = ?, updated_at = NOW() 
                     WHERE id = ?";
}

$update_stmt = mysqli_prepare($conn, $update_query);
mysqli_stmt_bind_param($update_stmt, 'sssisi', $new_status, $hr_approval, $hr_comment, $hr_id, $current_time, $leave_id);

if (mysqli_stmt_execute($update_stmt)) {
    // If rejected, restore leave balance
    if ($action === 'reject') {
        // Get leave request details to restore balance
        if ($table === 'employee') {
            $balance_query = "UPDATE employee_leave_balances 
                             SET used_days = used_days - (SELECT total_days FROM employee_leave_requests WHERE id = ?) 
                             WHERE employee_id = (SELECT employee_id FROM employee_leave_requests WHERE id = ?) 
                             AND leave_type_id = (SELECT leave_type_id FROM employee_leave_requests WHERE id = ?) 
                             AND year = ?";
        } else {
            $balance_query = "UPDATE faculty_leave_balances 
                             SET used_days = used_days - (SELECT total_days FROM faculty_leave_requests WHERE id = ?) 
                             WHERE faculty_id = (SELECT faculty_id FROM faculty_leave_requests WHERE id = ?) 
                             AND leave_type_id = (SELECT leave_type_id FROM faculty_leave_requests WHERE id = ?) 
                             AND year = ?";
        }
        
        $current_year = date('Y');
        $balance_stmt = mysqli_prepare($conn, $balance_query);
        mysqli_stmt_bind_param($balance_stmt, 'iiii', $leave_id, $leave_id, $leave_id, $current_year);
        mysqli_stmt_execute($balance_stmt);
    }
    
    echo json_encode(['success' => true, 'message' => 'Leave request ' . $action . 'd successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error updating leave request: ' . mysqli_error($conn)]);
}

mysqli_close($conn);
?>
