<?php
session_start();
require_once '../../includes/error_handler.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    // Get filter parameters
    $type_filter = $_GET['type'] ?? '';
    
    // Get scenarios from database
    $scenarios = getCustomerServiceScenarios($type_filter);
    
    if (empty($scenarios)) {
        // Return sample data if database is empty
        $scenarios = [
            [
                'id' => 'customer_service',
                'title' => 'Customer Service Excellence',
                'description' => 'Handle various customer service situations including complaints and special requests.',
                'type' => 'complaints',
                'difficulty' => 'intermediate',
                'estimated_time' => 25,
                'points' => 200
            ],
            [
                'id' => 'complaint_handling',
                'title' => 'Handling Guest Complaints',
                'description' => 'Practice responding to common guest complaints professionally.',
                'type' => 'complaints',
                'difficulty' => 'beginner',
                'estimated_time' => 20,
                'points' => 150
            ],
            [
                'id' => 'special_requests',
                'title' => 'Special Guest Requests',
                'description' => 'Handle unusual guest requests with professionalism and creativity.',
                'type' => 'requests',
                'difficulty' => 'advanced',
                'estimated_time' => 30,
                'points' => 250
            ]
        ];
    }
    
    echo json_encode([
        'success' => true,
        'scenarios' => $scenarios
    ]);
    
} catch (Exception $e) {
    error_log("Error in get-customer-service-scenarios.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}
?>
