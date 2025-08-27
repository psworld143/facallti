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

// Get filter parameters
$difficulty_filter = $_GET['difficulty'] ?? '';
$category_filter = $_GET['category'] ?? '';

try {
    // Get scenarios from database
    $scenarios = [];
    try {
        $scenarios = getTrainingScenarios($difficulty_filter, $category_filter);
    } catch (Exception $db_error) {
        error_log("Database error in get-training-scenarios.php: " . $db_error->getMessage());
        // Continue with sample data if database fails
    }
    
    // If no scenarios found in database, return sample data
    if (empty($scenarios)) {
        $scenarios = [
            [
                'id' => 'front_desk_basic',
                'title' => 'Front Desk Check-in Process',
                'description' => 'Learn the proper procedure for checking in guests at the front desk.',
                'difficulty' => 'beginner',
                'category' => 'front_desk',
                'estimated_time' => 15,
                'points' => 100,
                'status' => 'available'
            ],
            [
                'id' => 'customer_service_basic',
                'title' => 'Handling Guest Complaints',
                'description' => 'Practice responding to common guest complaints professionally.',
                'difficulty' => 'beginner',
                'category' => 'front_desk',
                'estimated_time' => 20,
                'points' => 150,
                'status' => 'available'
            ],
            [
                'id' => 'housekeeping_inspection',
                'title' => 'Room Inspection Process',
                'description' => 'Learn how to properly inspect rooms for cleanliness and maintenance.',
                'difficulty' => 'intermediate',
                'category' => 'housekeeping',
                'estimated_time' => 25,
                'points' => 200,
                'status' => 'available'
            ],
            [
                'id' => 'emergency_response',
                'title' => 'Emergency Situation Response',
                'description' => 'Practice responding to emergency situations in the hotel.',
                'difficulty' => 'advanced',
                'category' => 'management',
                'estimated_time' => 30,
                'points' => 300,
                'status' => 'available'
            ],
            [
                'id' => 'billing_dispute',
                'title' => 'Billing Dispute Resolution',
                'description' => 'Handle billing disputes and explain charges to guests.',
                'difficulty' => 'intermediate',
                'category' => 'front_desk',
                'estimated_time' => 20,
                'points' => 175,
                'status' => 'available'
            ],
            [
                'id' => 'overbooking_management',
                'title' => 'Overbooking Management',
                'description' => 'Learn how to handle overbooking situations professionally.',
                'difficulty' => 'advanced',
                'category' => 'management',
                'estimated_time' => 35,
                'points' => 250,
                'status' => 'available'
            ]
        ];
        
        // Apply filters to sample data
        if (!empty($difficulty_filter)) {
            $scenarios = array_filter($scenarios, function($scenario) use ($difficulty_filter) {
                return $scenario['difficulty'] === $difficulty_filter;
            });
        }
        
        if (!empty($category_filter)) {
            $scenarios = array_filter($scenarios, function($scenario) use ($category_filter) {
                return $scenario['category'] === $category_filter;
            });
        }
        
        // Re-index array
        $scenarios = array_values($scenarios);
    }
    
    echo json_encode([
        'success' => true,
        'scenarios' => $scenarios
    ]);
    
} catch (Exception $e) {
    error_log("Error in get-training-scenarios.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}
?>
