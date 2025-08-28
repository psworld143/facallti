<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is HR
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hr') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Validate required fields
$required_fields = ['employee_id', 'leave_type_id', 'start_date', 'end_date', 'reason'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty($_POST[$field])) {
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit();
    }
}

$employee_id = (int)$_POST['employee_id'];
$leave_type_id = (int)$_POST['leave_type_id'];
$start_date = $_POST['start_date'];
$end_date = $_POST['end_date'];
$reason = trim($_POST['reason']);
$hr_id = $_SESSION['user_id'];

// Validate dates
if (strtotime($start_date) > strtotime($end_date)) {
    echo json_encode(['success' => false, 'message' => 'Start date cannot be after end date']);
    exit();
}

if (strtotime($start_date) < strtotime(date('Y-m-d'))) {
    echo json_encode(['success' => false, 'message' => 'Start date cannot be in the past']);
    exit();
}

// Calculate total days
$start = new DateTime($start_date);
$end = new DateTime($end_date);
$interval = $start->diff($end);
$total_days = $interval->days + 1; // Include both start and end dates

// Get employee and leave type details
$employee_query = "SELECT e.*, dh.id as department_head_id 
                   FROM employees e 
                   LEFT JOIN department_heads dh ON e.department = dh.department 
                   WHERE e.id = ? AND e.is_active = 1";
$employee_stmt = mysqli_prepare($conn, $employee_query);
mysqli_stmt_bind_param($employee_stmt, 'i', $employee_id);
mysqli_stmt_execute($employee_stmt);
$employee_result = mysqli_stmt_get_result($employee_stmt);

if (mysqli_num_rows($employee_result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Employee not found or inactive']);
    exit();
}

$employee = mysqli_fetch_assoc($employee_result);

// Get leave type details
$leave_type_query = "SELECT * FROM leave_types WHERE id = ? AND is_active = 1";
$leave_type_stmt = mysqli_prepare($conn, $leave_type_query);
mysqli_stmt_bind_param($leave_type_stmt, 'i', $leave_type_id);
mysqli_stmt_execute($leave_type_stmt);
$leave_type_result = mysqli_stmt_get_result($leave_type_stmt);

if (mysqli_num_rows($leave_type_result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Leave type not found or inactive']);
    exit();
}

$leave_type = mysqli_fetch_assoc($leave_type_result);

// Check leave balance
$balance_query = "SELECT total_days, used_days, (total_days - used_days) as remaining_days 
                  FROM leave_balances 
                  WHERE employee_id = ? AND leave_type_id = ? AND year = ?";
$balance_stmt = mysqli_prepare($conn, $balance_query);
$current_year = date('Y');
mysqli_stmt_bind_param($balance_stmt, 'iii', $employee_id, $leave_type_id, $current_year);
mysqli_stmt_execute($balance_stmt);
$balance_result = mysqli_stmt_get_result($balance_stmt);

if (mysqli_num_rows($balance_result) === 0) {
    // Create leave balance if it doesn't exist
    $create_balance_query = "INSERT INTO leave_balances (employee_id, leave_type_id, year, total_days, used_days) 
                             VALUES (?, ?, ?, ?, 0)";
    $create_balance_stmt = mysqli_prepare($conn, $create_balance_query);
    mysqli_stmt_bind_param($create_balance_stmt, 'iiii', $employee_id, $leave_type_id, $current_year, $leave_type['default_days_per_year']);
    mysqli_stmt_execute($create_balance_stmt);
    
    $remaining_days = $leave_type['default_days_per_year'];
} else {
    $balance = mysqli_fetch_assoc($balance_result);
    $remaining_days = $balance['remaining_days'];
}

// Check if employee has enough leave balance
if ($total_days > $remaining_days) {
    echo json_encode(['success' => false, 'message' => "Insufficient leave balance. Available: $remaining_days days, Requested: $total_days days"]);
    exit();
}

// Check for overlapping leave requests
$overlap_query = "SELECT id FROM leave_requests 
                  WHERE employee_id = ? AND status NOT IN ('rejected', 'cancelled')
                  AND ((start_date BETWEEN ? AND ?) OR (end_date BETWEEN ? AND ?) OR (start_date <= ? AND end_date >= ?))";
$overlap_stmt = mysqli_prepare($conn, $overlap_query);
mysqli_stmt_bind_param($overlap_stmt, 'isssss', $employee_id, $start_date, $end_date, $start_date, $end_date, $start_date, $end_date);
mysqli_stmt_execute($overlap_stmt);
$overlap_result = mysqli_stmt_get_result($overlap_stmt);

if (mysqli_num_rows($overlap_result) > 0) {
    echo json_encode(['success' => false, 'message' => 'Leave request overlaps with existing approved or pending leave']);
    exit();
}

// Start transaction
mysqli_begin_transaction($conn);

try {
    // Insert leave request
    $insert_query = "INSERT INTO leave_requests 
                     (employee_id, leave_type_id, start_date, end_date, total_days, reason, 
                      department_head_id, status, department_head_approval, hr_approval, created_at) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', 'pending', NOW())";
    
    $insert_stmt = mysqli_prepare($conn, $insert_query);
    mysqli_stmt_bind_param($insert_stmt, 'iissdsi', $employee_id, $leave_type_id, $start_date, $end_date, $total_days, $reason, $employee['department_head_id']);
    
    if (!mysqli_stmt_execute($insert_stmt)) {
        throw new Exception('Failed to create leave request');
    }
    
    $leave_request_id = mysqli_insert_id($conn);

    // Create notification for department head
    if ($employee['department_head_id']) {
        $head_notification_title = 'New Leave Request for Approval';
        $head_notification_message = "Employee {$employee['first_name']} {$employee['last_name']} has submitted a leave request for {$total_days} days ({$leave_type['name']}) from {$start_date} to {$end_date}.";
        
        $head_notification_query = "INSERT INTO leave_notifications 
                                   (recipient_id, recipient_type, title, message, type, related_leave_request_id) 
                                   VALUES (?, 'department_head', ?, ?, 'leave_request', ?)";
        $head_notification_stmt = mysqli_prepare($conn, $head_notification_query);
        mysqli_stmt_bind_param($head_notification_stmt, 'issi', $employee['department_head_id'], $head_notification_title, $head_notification_message, $leave_request_id);
        
        if (!mysqli_stmt_execute($head_notification_stmt)) {
            throw new Exception('Failed to create department head notification');
        }
    }

    // Create notification for employee
    $employee_notification_title = 'Leave Request Submitted';
    $employee_notification_message = "Your leave request for {$total_days} days ({$leave_type['name']}) from {$start_date} to {$end_date} has been submitted successfully and is pending department head approval.";
    
    $employee_notification_query = "INSERT INTO leave_notifications 
                                   (recipient_id, recipient_type, title, message, type, related_leave_request_id) 
                                   VALUES (?, 'employee', ?, ?, 'leave_request', ?)";
    $employee_notification_stmt = mysqli_prepare($conn, $employee_notification_query);
    mysqli_stmt_bind_param($employee_notification_stmt, 'issi', $employee_id, $employee_notification_title, $employee_notification_message, $leave_request_id);
    
    if (!mysqli_stmt_execute($employee_notification_stmt)) {
        throw new Exception('Failed to create employee notification');
    }

    // Commit transaction
    mysqli_commit($conn);

    // Log the action
    $log_message = "HR created leave request #{$leave_request_id} for employee {$employee['first_name']} {$employee['last_name']} - {$total_days} days from {$start_date} to {$end_date}";
    error_log($log_message);

    echo json_encode([
        'success' => true, 
        'message' => 'Leave request created successfully',
        'leave_request_id' => $leave_request_id
    ]);

} catch (Exception $e) {
    // Rollback transaction
    mysqli_rollback($conn);
    
    error_log('Leave request creation error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while creating the leave request']);
}
