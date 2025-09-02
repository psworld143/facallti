<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get request data
$request_id = $_POST['request_id'] ?? '';

if (!$request_id) {
    echo json_encode(['error' => 'Request ID required']);
    exit;
}

try {
    // Simple update: change status from 'pending' to 'accepted'
    $update_query = "UPDATE consultation_requests 
                     SET status = 'accepted', 
                         response_time = NOW()
                     WHERE id = ? AND status = 'pending'";
    
    $update_stmt = mysqli_prepare($conn, $update_query);
    
    if (!$update_stmt) {
        throw new Exception('Failed to prepare update statement: ' . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($update_stmt, "i", $request_id);
    $update_result = mysqli_stmt_execute($update_stmt);
    
    if (!$update_result) {
        throw new Exception('Failed to update request: ' . mysqli_stmt_error($update_stmt));
    }
    
    $affected_rows = mysqli_stmt_affected_rows($update_stmt);
    
    if ($affected_rows > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Request status updated to accepted',
            'request_id' => $request_id,
            'affected_rows' => $affected_rows
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Request not found or already processed',
            'request_id' => $request_id
        ]);
    }
    
} catch (Exception $e) {
    error_log('Update request status error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Failed to update request status: ' . $e->getMessage()
    ]);
}
?>
