<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Get request ID from query parameters
$request_id = $_GET['request_id'] ?? '';

if (empty($request_id)) {
    echo json_encode(['error' => 'Request ID required']);
    exit;
}

try {
    // Get the session ID for the given request ID
    $query = "SELECT session_id, status FROM consultation_requests WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, "i", $request_id);
    $result = mysqli_stmt_execute($stmt);
    
    if (!$result) {
        throw new Exception('Failed to execute statement: ' . mysqli_stmt_error($stmt));
    }
    
    $stmt_result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($stmt_result) > 0) {
        $row = mysqli_fetch_assoc($stmt_result);
        
        echo json_encode([
            'success' => true,
            'session_id' => $row['session_id'],
            'status' => $row['status'],
            'request_id' => $request_id
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Request not found',
            'request_id' => $request_id
        ]);
    }
    
} catch (Exception $e) {
    error_log('Get request session error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Failed to get request session: ' . $e->getMessage()
    ]);
}
?>
