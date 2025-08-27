<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and has housekeeping access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['housekeeping', 'manager'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    $room_id = $_POST['room_id'] ?? '';
    $housekeeping_status = $_POST['housekeeping_status'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    // Validate inputs
    if (empty($room_id) || empty($housekeeping_status)) {
        throw new Exception('Room ID and housekeeping status are required');
    }
    
    // Validate housekeeping status
    $valid_statuses = ['clean', 'dirty', 'cleaning', 'maintenance'];
    if (!in_array($housekeeping_status, $valid_statuses)) {
        throw new Exception('Invalid housekeeping status');
    }
    
    // Update room housekeeping status
    $result = updateRoomHousekeepingStatus($room_id, $housekeeping_status);
    
    if ($result['success']) {
        // Log the activity with notes if provided
        if (!empty($notes)) {
            logActivity($_SESSION['user_id'], 'housekeeping_notes', "Updated room {$room_id} with notes: {$notes}");
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Room housekeeping status updated successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $result['message']
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error in update-room-housekeeping-status.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error updating room status: ' . $e->getMessage()
    ]);
}
?>
