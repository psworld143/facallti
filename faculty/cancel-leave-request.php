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

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$leave_id = $input['leave_id'] ?? '';
$table = $input['table'] ?? '';
$faculty_id = $_SESSION['user_id'];

if (empty($leave_id) || empty($table)) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

// Get leave request details
$leave = null;

if ($table === 'faculty') {
    $leave_query = "SELECT flr.*, f.first_name, f.last_name 
                    FROM faculty_leave_requests flr 
                    JOIN faculty f ON flr.faculty_id = f.id 
                    WHERE flr.id = ? AND flr.faculty_id = ? AND flr.status = 'pending'";
    $leave_stmt = mysqli_prepare($conn, $leave_query);
    mysqli_stmt_bind_param($leave_stmt, 'ii', $leave_id, $faculty_id);
    mysqli_stmt_execute($leave_stmt);
    $leave_result = mysqli_stmt_get_result($leave_stmt);
    
    if (mysqli_num_rows($leave_result) > 0) {
        $leave = mysqli_fetch_assoc($leave_result);
    }
}

if (!$leave) {
    echo json_encode(['success' => false, 'message' => 'Leave request not found or cannot be cancelled']);
    exit();
}

// Update leave request status to cancelled
if ($table === 'faculty') {
    $update_query = "UPDATE faculty_leave_requests 
                     SET status = 'cancelled', updated_at = NOW() 
                     WHERE id = ? AND faculty_id = ?";
    $update_stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($update_stmt, 'ii', $leave_id, $faculty_id);
}

if (mysqli_stmt_execute($update_stmt)) {
    // Restore leave balance
    if ($table === 'faculty') {
        $balance_query = "UPDATE faculty_leave_balances 
                          SET used_days = used_days - ? 
                          WHERE faculty_id = ? AND leave_type_id = ? AND year = ?";
        $balance_stmt = mysqli_prepare($conn, $balance_query);
        $current_year = date('Y');
        mysqli_stmt_bind_param($balance_stmt, 'diii', $leave['total_days'], $faculty_id, $leave['leave_type_id'], $current_year);
        mysqli_stmt_execute($balance_stmt);
    }
    
    echo json_encode(['success' => true, 'message' => 'Leave request cancelled successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error cancelling leave request: ' . mysqli_error($conn)]);
}

mysqli_close($conn);
?>
