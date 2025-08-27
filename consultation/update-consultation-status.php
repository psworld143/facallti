<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Get request data
$session_id = $_POST['session_id'] ?? null;
$action = $_POST['action'] ?? ''; // 'complete', 'start', etc.
$teacher_id = $_POST['teacher_id'] ?? null;

if (!$session_id || !$action || !$teacher_id) {
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

try {
    $status = '';
    $update_fields = [];
    
    switch ($action) {
        case 'complete':
            $status = 'completed';
            $update_fields[] = "end_time = NOW()";
            $update_fields[] = "duration_minutes = TIMESTAMPDIFF(MINUTE, start_time, NOW())";
            break;
        case 'start':
            $status = 'in_progress';
            $update_fields[] = "start_time = NOW()";
            break;
        default:
            throw new Exception('Invalid action');
    }
    
    $update_fields[] = "status = '$status'";
    $update_fields[] = "updated_at = NOW()";
    
    $query = "UPDATE consultation_requests 
              SET " . implode(', ', $update_fields) . "
              WHERE session_id = ? AND teacher_id = ? AND status IN ('accepted', 'in_progress')";
              
    $stmt = mysqli_prepare($conn, $query);
    
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, "si", $session_id, $teacher_id);
    $result = mysqli_stmt_execute($stmt);
    
    if (!$result) {
        throw new Exception('Failed to update consultation status: ' . mysqli_stmt_error($stmt));
    }
    
    $response = [
        'success' => true,
        'message' => 'Consultation status updated successfully',
        'session_id' => $session_id,
        'status' => $status
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    // Log error to console instead of showing alert
    error_log('Update consultation status error: ' . $e->getMessage());
    echo json_encode(['error' => 'Failed to update consultation status']);
}
?>
