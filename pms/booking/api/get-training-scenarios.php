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
    $difficulty_filter = $_GET['difficulty'] ?? '';
    $category_filter = $_GET['category'] ?? '';
    
    // Get scenarios from database
    $scenarios = getTrainingScenarios($difficulty_filter, $category_filter);
    
    if (empty($scenarios)) {
        // Return sample data if database is empty
        $scenarios = [
            [
                'id' => 'front_desk_basic',
                'title' => 'Front Desk Check-in Process',
                'description' => 'Learn essential check-in and check-out procedures with real scenarios.',
                'category' => 'front_desk',
                'difficulty' => 'beginner',
                'estimated_time' => 15,
                'points' => 100
            ],
            [
                'id' => 'customer_service',
                'title' => 'Customer Service Excellence',
                'description' => 'Handle various customer service situations including complaints and special requests.',
                'category' => 'customer_service',
                'difficulty' => 'intermediate',
                'estimated_time' => 25,
                'points' => 200
            ],
            [
                'id' => 'problem_solving',
                'title' => 'Problem Solving & Crisis Management',
                'description' => 'Handle various hotel problems and crisis situations that require quick thinking.',
                'category' => 'problem_solving',
                'difficulty' => 'advanced',
                'estimated_time' => 30,
                'points' => 300
            ]
        ];
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
