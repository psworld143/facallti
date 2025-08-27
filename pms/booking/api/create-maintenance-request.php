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
    $issue_type = $_POST['issue_type'] ?? '';
    $priority = $_POST['priority'] ?? '';
    $description = $_POST['description'] ?? '';
    
    // Validate inputs
    if (empty($room_id) || empty($issue_type) || empty($priority) || empty($description)) {
        throw new Exception('All fields are required');
    }
    
    // Validate issue type
    $valid_issue_types = ['plumbing', 'electrical', 'hvac', 'furniture', 'appliances', 'structural', 'other'];
    if (!in_array($issue_type, $valid_issue_types)) {
        throw new Exception('Invalid issue type');
    }
    
    // Validate priority
    $valid_priorities = ['low', 'medium', 'high', 'urgent'];
    if (!in_array($priority, $valid_priorities)) {
        throw new Exception('Invalid priority level');
    }
    
    // Create maintenance request
    $result = createMaintenanceRequest($room_id, $issue_type, $description, $priority);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Maintenance request created successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $result['message']
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error in create-maintenance-request.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error creating maintenance request: ' . $e->getMessage()
    ]);
}
?>
