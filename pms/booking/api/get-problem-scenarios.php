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

try {
    // Get filter parameters
    $severity_filter = $_GET['severity'] ?? '';
    
    // Get scenarios from database
    $scenarios = getProblemScenarios($severity_filter);
    
    if (empty($scenarios)) {
        // Return sample data if database is empty
        $scenarios = [
            [
                'id' => 'problem_solving',
                'title' => 'Problem Solving & Crisis Management',
                'description' => 'Handle various hotel problems and crisis situations that require quick thinking.',
                'severity' => 'high',
                'difficulty' => 'advanced',
                'time_limit' => 15,
                'points' => 300
            ]
        ];
    }
    
    echo json_encode([
        'success' => true,
        'scenarios' => $scenarios
    ]);
    
} catch (Exception $e) {
    error_log("Error in get-problem-scenarios.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}
?>
