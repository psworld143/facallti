<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/id_encryption.php';

// Check if user is logged in and has human_resource role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'human_resource') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['faculty_id']) || empty($input['faculty_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Faculty ID is required']);
    exit();
}

$faculty_id = (int)$input['faculty_id'];

// Validate faculty ID
if ($faculty_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid faculty ID']);
    exit();
}

// Check if faculty exists
$check_query = "SELECT id, first_name, last_name, email FROM faculty WHERE id = ?";
$check_stmt = mysqli_prepare($conn, $check_query);
mysqli_stmt_bind_param($check_stmt, "i", $faculty_id);
mysqli_stmt_execute($check_stmt);
$check_result = mysqli_stmt_get_result($check_stmt);

if (mysqli_num_rows($check_result) === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Faculty member not found']);
    exit();
}

$faculty = mysqli_fetch_assoc($check_result);
$faculty_name = $faculty['first_name'] . ' ' . $faculty['last_name'];

// Start transaction
mysqli_begin_transaction($conn);

try {
    // Delete from faculty_details first (due to foreign key constraint)
    $delete_details_query = "DELETE FROM faculty_details WHERE faculty_id = ?";
    $delete_details_stmt = mysqli_prepare($conn, $delete_details_query);
    if (!$delete_details_stmt) {
        throw new Exception('Error preparing faculty details delete: ' . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($delete_details_stmt, "i", $faculty_id);
    
    if (!mysqli_stmt_execute($delete_details_stmt)) {
        throw new Exception('Error deleting faculty details: ' . mysqli_stmt_error($delete_details_stmt));
    }
    
    // Delete from faculty table
    $delete_faculty_query = "DELETE FROM faculty WHERE id = ?";
    $delete_faculty_stmt = mysqli_prepare($conn, $delete_faculty_query);
    if (!$delete_faculty_stmt) {
        throw new Exception('Error preparing faculty delete: ' . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($delete_faculty_stmt, "i", $faculty_id);
    
    if (!mysqli_stmt_execute($delete_faculty_stmt)) {
        throw new Exception('Error deleting faculty: ' . mysqli_stmt_error($delete_faculty_stmt));
    }
    
    // Log the action (optional - don't fail deletion if logging fails)
    try {
        $admin_id = $_SESSION['user_id'];
        
        // Check if the user exists in the users table
        $check_user_query = "SELECT id FROM users WHERE id = ?";
        $check_user_stmt = mysqli_prepare($conn, $check_user_query);
        mysqli_stmt_bind_param($check_user_stmt, "i", $admin_id);
        mysqli_stmt_execute($check_user_stmt);
        $check_user_result = mysqli_stmt_get_result($check_user_stmt);
        
        if (mysqli_num_rows($check_user_result) > 0) {
            // User exists, proceed with logging
            $action = "Deleted faculty member: $faculty_name";
            $log_query = "INSERT INTO admin_activity_logs (admin_id, action, created_at) VALUES (?, ?, NOW())";
            $log_stmt = mysqli_prepare($conn, $log_query);
            if ($log_stmt) {
                mysqli_stmt_bind_param($log_stmt, "is", $admin_id, $action);
                mysqli_stmt_execute($log_stmt);
            }
        } else {
            // User doesn't exist, skip logging
            error_log("Activity logging skipped: User ID $admin_id not found in users table");
        }
    } catch (Exception $e) {
        // Log error but don't fail the deletion
        error_log("Activity logging failed: " . $e->getMessage());
    }
    
    // Commit transaction
    mysqli_commit($conn);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'message' => "Faculty member '$faculty_name' deleted successfully"
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    mysqli_rollback($conn);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error deleting faculty member: ' . $e->getMessage()]);
}

// Close statements
if (isset($delete_details_stmt)) mysqli_stmt_close($delete_details_stmt);
if (isset($delete_faculty_stmt)) mysqli_stmt_close($delete_faculty_stmt);

mysqli_close($conn);
?>
