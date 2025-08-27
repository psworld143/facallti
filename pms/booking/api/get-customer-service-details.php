<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get scenario ID
$scenario_id = $_GET['id'] ?? '';

if (empty($scenario_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Scenario ID is required']);
    exit();
}

try {
    // Sample customer service scenarios
    $scenarios = [
        'customer_service' => [
            'id' => 'customer_service',
            'title' => 'Handling Guest Complaints',
            'situation' => 'A guest approaches the front desk visibly upset about their room condition.',
            'guest_request' => 'The guest says their room is not clean and they want a refund or a new room immediately.',
            'difficulty' => 'beginner',
            'points' => 150
        ]
    ];
    
    if (isset($scenarios[$scenario_id])) {
        echo json_encode([
            'success' => true,
            'scenario' => $scenarios[$scenario_id]
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Scenario not found'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error in get-customer-service-details.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}
?>
