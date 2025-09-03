<?php
session_start();
require_once '../../includes/error_handler.php';
require_once '../includes/database.php';
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
            'title' => 'Customer Service Excellence',
            'situation' => 'A guest approaches the front desk looking very upset. They have been waiting for room service for over an hour and their food is cold.',
            'guest_request' => 'The guest demands immediate action and wants compensation for the poor service.',
            'difficulty' => 'intermediate',
            'points' => 200
        ],
        'complaint_handling' => [
            'id' => 'complaint_handling',
            'title' => 'Handling Guest Complaints',
            'situation' => 'A guest calls the front desk complaining about noise from the room next door. They cannot sleep and are very frustrated.',
            'guest_request' => 'The guest wants the noise to stop immediately and threatens to leave a negative review.',
            'difficulty' => 'beginner',
            'points' => 150
        ],
        'special_requests' => [
            'id' => 'special_requests',
            'title' => 'Special Guest Requests',
            'situation' => 'A guest with a severe allergy requests a special meal preparation and room cleaning.',
            'guest_request' => 'The guest needs assurance that their allergy will be taken seriously and wants special accommodations.',
            'difficulty' => 'advanced',
            'points' => 250
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
            'message' => 'Customer service scenario not found'
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
