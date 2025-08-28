<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Get request data
$request_id = $_POST['request_id'] ?? null;
$action = $_POST['action'] ?? ''; // 'accept' or 'decline'
$teacher_id = $_POST['teacher_id'] ?? null;
$decline_reason = $_POST['decline_reason'] ?? null;
$duration_seconds = (int)($_POST['duration_seconds'] ?? 0);

if (!$request_id || !$action || !$teacher_id) {
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

// Validate decline reason if action is decline
if ($action === 'decline' && empty($decline_reason)) {
    echo json_encode(['error' => 'Decline reason is required when declining a request']);
    exit;
}

if (!in_array($action, ['accept', 'decline'])) {
    echo json_encode(['error' => 'Invalid action']);
    exit;
}

try {
    // Get the original request time for response_time
    $get_original_query = "SELECT request_time FROM consultation_requests WHERE id = ? AND teacher_id = ? AND status = 'pending'";
    $get_original_stmt = mysqli_prepare($conn, $get_original_query);
    mysqli_stmt_bind_param($get_original_stmt, "ii", $request_id, $teacher_id);
    mysqli_stmt_execute($get_original_stmt);
    $get_original_result = mysqli_stmt_get_result($get_original_stmt);
    $original_request = mysqli_fetch_assoc($get_original_result);
    
    if (!$original_request) {
        throw new Exception('No pending consultation request found');
    }
    
    // Use the duration sent from the client (waitTimeCounter)
    $response_duration = $duration_seconds;
    
    // Ensure duration is reasonable (max 24 hours)
    if ($response_duration > 86400) { // More than 24 hours
        $response_duration = 0; // Set to 0 if unreasonably long
    }
    
    // Update the consultation request status
    $status = ($action === 'accept') ? 'accepted' : 'declined';
    $response_time = date('Y-m-d H:i:s');
    
    if ($action === 'decline' && $decline_reason) {
        $query = "UPDATE consultation_requests 
                  SET status = ?, decline_reason = ?, response_time = ?, response_duration_seconds = ?, updated_at = NOW() 
                  WHERE id = ? AND teacher_id = ? AND status = 'pending'";
        $stmt = mysqli_prepare($conn, $query);
        
        if (!$stmt) {
            throw new Exception('Failed to prepare statement: ' . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($stmt, "sssiii", $status, $decline_reason, $response_time, $response_duration, $request_id, $teacher_id);
    } else {
        $query = "UPDATE consultation_requests 
                  SET status = ?, response_time = ?, response_duration_seconds = ?, updated_at = NOW() 
                  WHERE id = ? AND teacher_id = ? AND status = 'pending'";
        $stmt = mysqli_prepare($conn, $query);
        
        if (!$stmt) {
            throw new Exception('Failed to prepare statement: ' . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($stmt, "ssiii", $status, $response_time, $response_duration, $request_id, $teacher_id);
    }
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
    
    // Format response duration for human readability
    $response_duration_formatted = '';
    if ($response_duration < 60) {
        $response_duration_formatted = $response_duration . ' seconds';
    } elseif ($response_duration < 3600) {
        $minutes = floor($response_duration / 60);
        $seconds = $response_duration % 60;
        $response_duration_formatted = $minutes . 'm ' . $seconds . 's';
    } else {
        $hours = floor($response_duration / 3600);
        $minutes = floor(($response_duration % 3600) / 60);
        $response_duration_formatted = $hours . 'h ' . $minutes . 'm';
    }
    
    $response = [
        'success' => true,
        'message' => 'Consultation request ' . $action . 'ed successfully',
        'request_id' => $request_id,
        'session_id' => $request_data['session_id'],
        'status' => $status,
        'response_time' => $response_time,
        'response_duration_seconds' => $response_duration,
        'response_duration_formatted' => $response_duration_formatted,
        'student_name' => $request_data['student_name'],
        'student_dept' => $request_data['student_dept'],
        'action' => $action,
        'timestamp' => time()
    ];
    
    // Add decline reason to response if applicable
    if ($action === 'decline' && $decline_reason) {
        $response['decline_reason'] = $decline_reason;
    }
    
    echo json_encode($response);
    
    // Enhanced logging for teacher responses
    $log_data = [
        'timestamp' => date('Y-m-d H:i:s'),
        'request_id' => $request_id,
        'teacher_id' => $teacher_id,
        'action' => $action,
        'status' => $status,
        'response_time' => $response_time,
        'response_duration_seconds' => $response_duration,
        'response_duration_formatted' => $response_duration_formatted,
        'decline_reason' => $decline_reason ?? 'N/A',
        'student_name' => $request_data['student_name'],
        'student_dept' => $request_data['student_dept']
    ];
    
    error_log("TEACHER RESPONSE LOG: " . json_encode($log_data));
    
} catch (Exception $e) {
    // Log error to console instead of showing alert
    error_log('Respond to consultation error: ' . $e->getMessage());
    echo json_encode(['error' => 'Failed to ' . $action . ' consultation request. Please try again.']);
}
?>
