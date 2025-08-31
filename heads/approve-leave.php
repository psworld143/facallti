<?php
session_start();
require_once '../includes/error_handler.php';
require_once '../config/database.php';

// Check if user is logged in and is a department head
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'head') {
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
$head_id = $_SESSION['user_id'];

if (empty($leave_id) || empty($table) || empty($action)) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

// Get department head information
$head_query = "SELECT h.*, u.first_name, u.last_name, u.email 
               FROM heads h 
               JOIN users u ON h.user_id = u.id 
               WHERE h.user_id = ? AND h.status = 'active'";
$head_stmt = mysqli_prepare($conn, $head_query);
mysqli_stmt_bind_param($head_stmt, 'i', $head_id);
mysqli_stmt_execute($head_stmt);
$head_result = mysqli_stmt_get_result($head_stmt);

if (mysqli_num_rows($head_result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Department head not found or inactive']);
    exit();
}

$head_info = mysqli_fetch_assoc($head_result);
$head_department = $head_info['department'];

// Get leave request details based on table
$leave = null;

if ($table === 'faculty') {
    $leave_query = "SELECT flr.*, f.first_name, f.last_name, f.department 
                    FROM faculty_leave_requests flr 
                    JOIN faculty f ON flr.faculty_id = f.id 
                    WHERE flr.id = ? AND f.department = ? AND flr.status = 'pending'";
    $leave_stmt = mysqli_prepare($conn, $leave_query);
    mysqli_stmt_bind_param($leave_stmt, 'is', $leave_id, $head_department);
    mysqli_stmt_execute($leave_stmt);
    $leave_result = mysqli_stmt_get_result($leave_stmt);
    
    if (mysqli_num_rows($leave_result) > 0) {
        $leave = mysqli_fetch_assoc($leave_result);
    }
}

if (!$leave) {
    echo json_encode(['success' => false, 'message' => 'Leave request not found or not eligible for approval']);
    exit();
}

$current_time = date('Y-m-d H:i:s');

// Update leave request based on action and table
if ($action === 'approve') {
    $new_status = 'approved_by_head';
    $department_head_approval = 'approved';
    $department_head_comment = 'Approved by department head';
} else if ($action === 'reject') {
    $new_status = 'rejected';
    $department_head_approval = 'rejected';
    $department_head_comment = $reason ?: 'Rejected by department head';
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
}

// Update the appropriate table
if ($table === 'faculty') {
    $update_query = "UPDATE faculty_leave_requests 
                     SET status = ?, department_head_approval = ?, department_head_comment = ?, department_head_id = ?, department_head_approved_at = ?, updated_at = NOW() 
                     WHERE id = ?";
}

$update_stmt = mysqli_prepare($conn, $update_query);
mysqli_stmt_bind_param($update_stmt, 'sssisi', $new_status, $department_head_approval, $department_head_comment, $head_id, $current_time, $leave_id);

if (mysqli_stmt_execute($update_stmt)) {
    // If rejected, restore leave balance
    if ($action === 'reject') {
        if ($table === 'faculty') {
            $balance_query = "UPDATE faculty_leave_balances 
                              SET used_days = used_days - (SELECT total_days FROM faculty_leave_requests WHERE id = ?) 
                              WHERE faculty_id = (SELECT faculty_id FROM faculty_leave_requests WHERE id = ?) 
                              AND leave_type_id = (SELECT leave_type_id FROM faculty_leave_requests WHERE id = ?) 
                              AND year = ?";
            $current_year = date('Y');
            $balance_stmt = mysqli_prepare($conn, $balance_query);
            mysqli_stmt_bind_param($balance_stmt, 'iiii', $leave_id, $leave_id, $leave_id, $current_year);
            mysqli_stmt_execute($balance_stmt);
        }
    }
    
    echo json_encode(['success' => true, 'message' => 'Leave request ' . $action . 'd successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error updating leave request: ' . mysqli_error($conn)]);
}

mysqli_close($conn);
?>
