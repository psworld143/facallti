<?php
session_start();
require_once '../includes/error_handler.php';
require_once '../config/database.php';

// Check if user is logged in and is a department head
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'head') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['leave_id']) || !isset($input['action']) || !isset($input['table'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$leave_id = (int)$input['leave_id'];
$action = $input['action'];
$table = $input['table'];
$comment = $input['comment'] ?? '';
$head_id = $_SESSION['user_id'];

// Validate action and table
if (!in_array($action, ['approve', 'reject'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
}

if (!in_array($table, ['faculty'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid table']);
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

// Get leave request details based on table
$leave = null;

if ($table === 'faculty') {
    $query = "SELECT flr.*, f.first_name, f.last_name, f.email, f.department, lt.name as leave_type_name
              FROM faculty_leave_requests flr 
              JOIN faculty f ON flr.faculty_id = f.id 
              JOIN leave_types lt ON flr.leave_type_id = lt.id
              WHERE flr.id = ? AND f.department = ? AND flr.status = 'pending'";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'is', $leave_id, $head_department);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        $leave = mysqli_fetch_assoc($result);
    }
}

if (!$leave) {
    echo json_encode(['success' => false, 'message' => 'Leave request not found or not ready for approval']);
    exit();
}

// Start transaction
mysqli_begin_transaction($conn);

try {
    // Update leave request based on table
    $new_status = $action === 'approve' ? 'approved_by_head' : 'rejected';
    $department_head_approval = $action === 'approve' ? 'approved' : 'rejected';
    
    if ($table === 'faculty') {
        $update_query = "UPDATE faculty_leave_requests SET 
                         status = ?, 
                         department_head_approval = ?, 
                         department_head_id = ?, 
                         department_head_comment = ?, 
                         department_head_approved_at = NOW(),
                         updated_at = NOW()
                         WHERE id = ?";
    }
    
    $update_stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($update_stmt, 'ssisi', $new_status, $department_head_approval, $head_id, $comment, $leave_id);
    
    if (!mysqli_stmt_execute($update_stmt)) {
        throw new Exception('Failed to update leave request');
    }

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

    // Commit transaction
    mysqli_commit($conn);

    // Log the action
    $log_message = "Department Head " . ($action === 'approve' ? 'approved' : 'rejected') . " leave request #{$leave_id} for faculty {$leave['first_name']} {$leave['last_name']}";
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
