<?php
session_start();
require_once '../../includes/error_handler.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and has housekeeping access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['housekeeping', 'manager'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid input data');
    }
    
    // Validate required fields
    if (empty($input['room_id'])) {
        throw new Exception('Room ID is required');
    }
    
    if (empty($input['status'])) {
        throw new Exception('Status is required');
    }
    
    // Validate status
    $valid_statuses = ['clean', 'dirty', 'maintenance'];
    if (!in_array($input['status'], $valid_statuses)) {
        throw new Exception('Invalid status');
    }
    
    // Update room status
    $result = updateRoomHousekeepingStatus($input['room_id'], $input['status'], $input['notes'] ?? '');
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("Error updating room status: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
