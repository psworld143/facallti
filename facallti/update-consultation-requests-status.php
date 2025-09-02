<?php
session_start();
require_once '../config/database.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid request method. Only POST requests are allowed.'
    ]);
    exit;
}

// Get the action and department from POST data
$action = $_POST['action'] ?? '';
$department = $_POST['department'] ?? '';

// Validate required parameters
if (empty($action) || empty($department)) {
    echo json_encode([
        'success' => false,
        'error' => 'Missing required parameters: action and department'
    ]);
    exit;
}

// Check if action is valid
if ($action !== 'update_status') {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid action. Only "update_status" is allowed.'
    ]);
    exit;
}

try {
    // Update consultation requests status from 'pending' to 'accepted' for the selected department
    $update_query = "UPDATE consultation_requests 
                     SET status = 'accepted', 
                         updated_at = CURRENT_TIMESTAMP 
                     WHERE status = 'pending' 
                     AND department = ?";
    
    $update_stmt = mysqli_prepare($conn, $update_query);
    
    if (!$update_stmt) {
        throw new Exception('Failed to prepare update statement: ' . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($update_stmt, 's', $department);
    $update_result = mysqli_stmt_execute($update_stmt);
    
    if (!$update_result) {
        throw new Exception('Failed to execute update statement: ' . mysqli_stmt_error($update_stmt));
    }
    
    // Get the number of affected rows (updated requests)
    $updated_count = mysqli_stmt_affected_rows($update_stmt);
    
    // Close the statement
    mysqli_stmt_close($update_stmt);
    
    // Log the update for debugging
    error_log("Updated $updated_count consultation requests from 'pending' to 'accepted' for department: $department");
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => "Successfully updated $updated_count consultation requests for department: $department",
        'updated_count' => $updated_count,
        'department' => $department,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    // Log the error
    error_log("Error updating consultation requests status: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage(),
        'department' => $department,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

// Close database connection
mysqli_close($conn);
?>
