<?php
session_start();
require_once '../config/database.php';

// Set headers for Server-Sent Events
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Cache-Control');

// Prevent output buffering
if (ob_get_level()) ob_end_clean();

// Get student session ID from query parameters
$session_id = $_GET['session_id'] ?? '';

if (empty($session_id)) {
    echo "data: " . json_encode(['error' => 'Session ID required']) . "\n\n";
    exit;
}

// Function to send SSE data
function sendSSE($data) {
    echo "data: " . json_encode($data) . "\n\n";
    ob_flush();
    flush();
}

// Send initial connection message
sendSSE(['type' => 'connected', 'message' => 'Connected to notification stream']);

// Keep connection alive and check for status changes
$last_check_time = time();
$max_execution_time = 300; // 5 minutes max
$check_interval = 2; // Check every 2 seconds

while (true) {
    // Check if connection is still alive
    if (connection_aborted()) {
        break;
    }
    
    // Check if we've exceeded max execution time
    if (time() - $last_check_time > $max_execution_time) {
        sendSSE(['type' => 'timeout', 'message' => 'Connection timeout, please refresh']);
        break;
    }
    
    // Check for status changes in consultation requests
    $status_query = "SELECT 
                        cr.id,
                        cr.status,
                        cr.response_time,
                        cr.response_duration_seconds,
                        cr.decline_reason,
                        f.first_name,
                        f.last_name,
                        f.department,
                        f.position
                     FROM consultation_requests cr
                     INNER JOIN faculty f ON cr.teacher_id = f.id
                     WHERE cr.session_id = ? 
                     AND cr.status IN ('accepted', 'declined')
                     AND cr.response_time > DATE_SUB(NOW(), INTERVAL 10 SECOND)";
    
    $status_stmt = mysqli_prepare($conn, $status_query);
    if ($status_stmt) {
        mysqli_stmt_bind_param($status_stmt, "s", $session_id);
        mysqli_stmt_execute($status_stmt);
        $status_result = mysqli_stmt_get_result($status_stmt);
        
        if ($status_result && mysqli_num_rows($status_result) > 0) {
            $request_data = mysqli_fetch_assoc($status_result);
            
            // Send notification based on status
            if ($request_data['status'] === 'accepted') {
                $notification = [
                    'type' => 'consultation_accepted',
                    'message' => 'Your consultation request has been accepted!',
                    'data' => [
                        'request_id' => $request_data['id'],
                        'teacher_name' => $request_data['first_name'] . ' ' . $request_data['last_name'],
                        'teacher_position' => $request_data['position'],
                        'department' => $request_data['department'],
                        'response_time' => $request_data['response_time'],
                        'wait_duration' => $request_data['response_duration_seconds'],
                        'session_id' => $session_id
                    ]
                ];
            } else {
                $notification = [
                    'type' => 'consultation_declined',
                    'message' => 'Your consultation request has been declined.',
                    'data' => [
                        'request_id' => $request_data['id'],
                        'teacher_name' => $request_data['first_name'] . ' ' . $request_data['last_name'],
                        'teacher_position' => $request_data['position'],
                        'department' => $request_data['department'],
                        'response_time' => $request_data['response_time'],
                        'wait_duration' => $request_data['response_duration_seconds'],
                        'decline_reason' => $request_data['decline_reason'],
                        'session_id' => $session_id
                    ]
                ];
            }
            
            sendSSE($notification);
            
            // Close connection after sending notification
            break;
        }
        
        mysqli_stmt_close($status_stmt);
    }
    
    // Send keep-alive ping every 30 seconds
    if (time() - $last_check_time >= 30) {
        sendSSE(['type' => 'ping', 'timestamp' => time()]);
        $last_check_time = time();
    }
    
    // Wait before next check
    sleep($check_interval);
}

// Close connection
sendSSE(['type' => 'disconnected', 'message' => 'Connection closed']);
?>
