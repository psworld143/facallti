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

if (!$input || !isset($input['solution'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Solution is required']);
    exit();
}

try {
    // For now, return a sample response
    // In a real implementation, this would analyze the solution quality
    $score = rand(70, 100); // Random score for demonstration
    
    // Log the training attempt
    try {
        logActivity($_SESSION['user_id'], 'completed_problem_solving_training', 'Score: ' . $score . '%');
    } catch (Exception $log_error) {
        error_log("Error logging activity: " . $log_error->getMessage());
        // Continue even if logging fails
    }
    
    echo json_encode([
        'success' => true,
        'score' => $score,
        'message' => 'Problem solution submitted successfully!'
    ]);
    
} catch (Exception $e) {
    error_log("Error in submit-problem.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}
?>
