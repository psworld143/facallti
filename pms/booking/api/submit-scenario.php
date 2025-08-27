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

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit();
}

try {
    // For now, return a sample response
    // In a real implementation, this would validate answers against the database
    $score = rand(70, 100); // Random score for demonstration
    
    // Log the training attempt
    try {
        logActivity($_SESSION['user_id'], 'completed_training_scenario', 'Score: ' . $score . '%');
    } catch (Exception $log_error) {
        error_log("Error logging activity: " . $log_error->getMessage());
        // Continue even if logging fails
    }
    
    echo json_encode([
        'success' => true,
        'score' => $score,
        'message' => 'Scenario completed successfully!'
    ]);
    
} catch (Exception $e) {
    error_log("Error in submit-scenario.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}
?>
