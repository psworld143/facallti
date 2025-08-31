<?php
session_start();
require_once '../includes/error_handler.php';
require_once '../config/database.php';

// Check if user is logged in and is faculty
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get form data
$faculty_id = $_POST['faculty_id'] ?? $_SESSION['user_id'];
$leave_type_id = $_POST['leave_type_id'] ?? '';
$start_date = $_POST['start_date'] ?? '';
$end_date = $_POST['end_date'] ?? '';
$reason = $_POST['reason'] ?? '';

// Validate required fields
if (empty($faculty_id) || empty($leave_type_id) || empty($start_date) || empty($end_date) || empty($reason)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit();
}

// Validate dates
if (strtotime($start_date) > strtotime($end_date)) {
    echo json_encode(['success' => false, 'message' => 'Start date cannot be after end date']);
    exit();
}

if (strtotime($start_date) < date('Y-m-d')) {
    echo json_encode(['success' => false, 'message' => 'Start date cannot be in the past']);
    exit();
}

// Calculate total days
$start = new DateTime($start_date);
$end = new DateTime($end_date);
$interval = $start->diff($end);
$total_days = $interval->days + 1; // Include both start and end dates

// Get faculty information
$faculty_query = "SELECT f.*, f.id as faculty_id, f.first_name, f.last_name, f.email, f.department 
                  FROM faculty f 
                  WHERE f.id = ? AND f.is_active = 1";
$faculty_stmt = mysqli_prepare($conn, $faculty_query);
mysqli_stmt_bind_param($faculty_stmt, 'i', $faculty_id);
mysqli_stmt_execute($faculty_stmt);
$faculty_result = mysqli_stmt_get_result($faculty_stmt);

if (mysqli_num_rows($faculty_result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Faculty not found or inactive']);
    exit();
}

$faculty_info = mysqli_fetch_assoc($faculty_result);

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

// Check leave balance from faculty_leave_balances table
$balance_query = "SELECT total_days, used_days, (total_days - used_days) as remaining_days 
                  FROM faculty_leave_balances 
                  WHERE faculty_id = ? AND leave_type_id = ? AND year = ?";
$balance_stmt = mysqli_prepare($conn, $balance_query);
$current_year = date('Y');
mysqli_stmt_bind_param($balance_stmt, 'iii', $faculty_id, $leave_type_id, $current_year);
mysqli_stmt_execute($balance_stmt);
$balance_result = mysqli_stmt_get_result($balance_stmt);

if (mysqli_num_rows($balance_result) === 0) {
    // Create leave balance if it doesn't exist
    $create_balance_query = "INSERT INTO faculty_leave_balances (faculty_id, leave_type_id, year, total_days, used_days) 
                             VALUES (?, ?, ?, ?, 0)";
    $create_balance_stmt = mysqli_prepare($conn, $create_balance_query);
    mysqli_stmt_bind_param($create_balance_stmt, 'iiii', $faculty_id, $leave_type_id, $current_year, $leave_type['default_days_per_year']);
    mysqli_stmt_execute($create_balance_stmt);
    
    $remaining_days = $leave_type['default_days_per_year'];
} else {
    $balance = mysqli_fetch_assoc($balance_result);
    $remaining_days = $balance['remaining_days'];
}

// Check if faculty has enough leave balance
if ($total_days > $remaining_days) {
    echo json_encode(['success' => false, 'message' => "Insufficient leave balance. Available: $remaining_days days, Requested: $total_days days"]);
    exit();
}

// Check for overlapping leave requests
$overlap_query = "SELECT id FROM faculty_leave_requests 
                  WHERE faculty_id = ? AND status NOT IN ('rejected', 'cancelled')
                  AND ((start_date BETWEEN ? AND ?) OR (end_date BETWEEN ? AND ?) OR (start_date <= ? AND end_date >= ?))";
$overlap_stmt = mysqli_prepare($conn, $overlap_query);
mysqli_stmt_bind_param($overlap_stmt, 'isssss', $faculty_id, $start_date, $end_date, $start_date, $end_date, $start_date, $end_date);
mysqli_stmt_execute($overlap_stmt);
$overlap_result = mysqli_stmt_get_result($overlap_stmt);

if (mysqli_num_rows($overlap_result) > 0) {
    echo json_encode(['success' => false, 'message' => 'Leave request overlaps with existing approved or pending leave']);
    exit();
}

// Get department head for this faculty's department
$department_head_query = "SELECT dh.id as department_head_id 
                          FROM department_heads dh 
                          WHERE dh.department = ?";
$department_head_stmt = mysqli_prepare($conn, $department_head_query);
mysqli_stmt_bind_param($department_head_stmt, 's', $faculty_info['department']);
mysqli_stmt_execute($department_head_stmt);
$department_head_result = mysqli_stmt_get_result($department_head_stmt);
$department_head_id = null;

if (mysqli_num_rows($department_head_result) > 0) {
    $department_head = mysqli_fetch_assoc($department_head_result);
    $department_head_id = $department_head['department_head_id'];
}

// Insert leave request into faculty_leave_requests table
$insert_query = "INSERT INTO faculty_leave_requests (
                    faculty_id, leave_type_id, start_date, end_date, total_days, reason, 
                    status, department_head_id, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, NOW())";

$insert_stmt = mysqli_prepare($conn, $insert_query);
mysqli_stmt_bind_param($insert_stmt, 'iissds', $faculty_id, $leave_type_id, $start_date, $end_date, $total_days, $reason, $department_head_id);

if (mysqli_stmt_execute($insert_stmt)) {
    $leave_request_id = mysqli_insert_id($conn);
    
    // Update leave balance
    $update_balance_query = "UPDATE faculty_leave_balances 
                             SET used_days = used_days + ? 
                             WHERE faculty_id = ? AND leave_type_id = ? AND year = ?";
    $update_balance_stmt = mysqli_prepare($conn, $update_balance_query);
    mysqli_stmt_bind_param($update_balance_stmt, 'diii', $total_days, $faculty_id, $leave_type_id, $current_year);
    mysqli_stmt_execute($update_balance_stmt);
    
    echo json_encode(['success' => true, 'message' => 'Leave request submitted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error submitting leave request: ' . mysqli_error($conn)]);
}

mysqli_close($conn);
?>
