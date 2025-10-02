<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has head role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'head') {
    header('Location: ../index.php');
    exit();
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: teachers.php');
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];

// Get head information
$head_query = "SELECT h.* FROM heads h WHERE h.user_id = ?";
$head_stmt = mysqli_prepare($conn, $head_query);
mysqli_stmt_bind_param($head_stmt, "i", $user_id);
mysqli_stmt_execute($head_stmt);
$head_result = mysqli_stmt_get_result($head_stmt);
$head_info = mysqli_fetch_assoc($head_result);

if (!$head_info) {
    header('Location: teachers.php?error=unauthorized');
    exit();
}

// Sanitize input data
$faculty_id = (int)$_POST['faculty_id'];
$action = sanitize_input($_POST['action']);
$faculty_name = sanitize_input($_POST['faculty_name']);

// Validate required fields
if (empty($faculty_id) || empty($action)) {
    header('Location: teachers.php?error=missing_fields');
    exit();
}

// Validate action
if (!in_array($action, ['deactivate', 'reactivate'])) {
    header('Location: teachers.php?error=invalid_action');
    exit();
}

try {
    // First, verify the faculty member exists and belongs to this head's department
    $verify_query = "SELECT id, first_name, last_name, department, is_active FROM faculty WHERE id = ?";
    $verify_stmt = mysqli_prepare($conn, $verify_query);
    
    if (!$verify_stmt) {
        throw new Exception("Error preparing verify statement: " . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($verify_stmt, "i", $faculty_id);
    
    if (!mysqli_stmt_execute($verify_stmt)) {
        throw new Exception("Error executing verify statement: " . mysqli_stmt_error($verify_stmt));
    }
    
    $verify_result = mysqli_stmt_get_result($verify_stmt);
    $faculty = mysqli_fetch_assoc($verify_result);
    
    if (!$faculty) {
        header('Location: teachers.php?error=faculty_not_found');
        exit();
    }
    
    // Verify department matches head's department
    if ($faculty['department'] !== $head_info['department']) {
        header('Location: teachers.php?error=unauthorized_department');
        exit();
    }
    
    if ($action === 'deactivate') {
        // Delete the teacher from the database
        $delete_query = "DELETE FROM faculty WHERE id = ?";
        $delete_stmt = mysqli_prepare($conn, $delete_query);
        
        if (!$delete_stmt) {
            throw new Exception("Error preparing delete statement: " . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($delete_stmt, "i", $faculty_id);
        
        if (!mysqli_stmt_execute($delete_stmt)) {
            throw new Exception("Error executing delete statement: " . mysqli_stmt_error($delete_stmt));
        }
        
        // Check if deletion was successful
        if (mysqli_affected_rows($conn) > 0) {
            // Success - redirect with success message
            header('Location: teachers.php?success=faculty_deleted&name=' . urlencode($faculty_name));
            exit();
        } else {
            throw new Exception("No rows affected during deletion");
        }
    } else {
        // Reactivate functionality (set is_active to 1)
        $new_status = 1;
        
        // Check if already active
        if ($faculty['is_active'] == $new_status) {
            header('Location: teachers.php?error=already_active&name=' . urlencode($faculty_name));
            exit();
        }
        
        // Update faculty status
        $update_query = "UPDATE faculty SET is_active = ? WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update_query);
        
        if (!$update_stmt) {
            throw new Exception("Error preparing update statement: " . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($update_stmt, "ii", $new_status, $faculty_id);
        
        if (!mysqli_stmt_execute($update_stmt)) {
            throw new Exception("Error executing update statement: " . mysqli_stmt_error($update_stmt));
        }
        
        // Check if update was successful
        if (mysqli_affected_rows($conn) > 0) {
            // Success - redirect with success message
            header('Location: teachers.php?success=faculty_reactivated&name=' . urlencode($faculty_name));
            exit();
        } else {
            throw new Exception("No rows affected during update");
        }
    }
    
} catch (Exception $e) {
    // Log error for debugging
    error_log("Update Faculty Status Error: " . $e->getMessage());
    
    // Redirect with error
    header('Location: teachers.php?error=status_update_error');
    exit();
}
?>
