<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Get request data
$request_id = $_POST['request_id'] ?? null;
$action = $_POST['action'] ?? ''; // 'accept' or 'decline'
$teacher_id = $_POST['teacher_id'] ?? null;

if (!$request_id || !$action || !$teacher_id) {
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

if (!in_array($action, ['accept', 'decline'])) {
    echo json_encode(['error' => 'Invalid action']);
    exit;
}

try {
    // Update the consultation request status
    $status = ($action === 'accept') ? 'accepted' : 'declined';
    $response_time = date('Y-m-d H:i:s');
    
    $query = "UPDATE consultation_requests 
              SET status = ?, response_time = ?, updated_at = NOW() 
              WHERE id = ? AND teacher_id = ? AND status = 'pending'";
              
    $stmt = mysqli_prepare($conn, $query);
    
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, "ssii", $status, $response_time, $request_id, $teacher_id);
    $result = mysqli_stmt_execute($stmt);
    
    if (!$result) {
        throw new Exception('Failed to update consultation request: ' . mysqli_stmt_error($stmt));
    }
    
    if (mysqli_affected_rows($conn) === 0) {
        throw new Exception('No consultation request found or already processed');
    }
    
    // Get the updated request details
    $get_query = "SELECT session_id, student_name, student_dept FROM consultation_requests WHERE id = ?";
    $get_stmt = mysqli_prepare($conn, $get_query);
    mysqli_stmt_bind_param($get_stmt, "i", $request_id);
    mysqli_stmt_execute($get_stmt);
    $get_result = mysqli_stmt_get_result($get_stmt);
    $request_data = mysqli_fetch_assoc($get_result);
    
    $response = [
        'success' => true,
        'message' => 'Consultation request ' . $action . 'ed successfully',
        'request_id' => $request_id,
        'session_id' => $request_data['session_id'],
        'status' => $status,
        'response_time' => $response_time,
        'student_name' => $request_data['student_name'],
        'student_dept' => $request_data['student_dept']
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    // Log error to console instead of showing alert
    error_log('Respond to consultation error: ' . $e->getMessage());
    echo json_encode(['error' => 'Failed to ' . $action . ' consultation request. Please try again.']);
}
?>
