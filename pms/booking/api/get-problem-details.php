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
    // Sample problem scenarios
    $scenarios = [
        'problem_solving' => [
            'id' => 'problem_solving',
            'title' => 'System Failure Response',
            'description' => 'The hotel management system has crashed during peak check-in hours. Multiple guests are waiting and becoming impatient.',
            'resources' => 'You have access to a backup paper system, a supervisor, and can contact IT support.',
            'severity' => 'high',
            'time_limit' => 10
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
    error_log("Error in get-problem-details.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}
?>
