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

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['response']) || !isset($input['scenario_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Response and scenario ID are required']);
    exit();
}

try {
    $response = trim($input['response']);
    $scenario_id = $input['scenario_id'];
    
    if (empty($response)) {
        echo json_encode([
            'success' => false,
            'message' => 'Please provide a response'
        ]);
        exit();
    }
    
    // Simple scoring based on response length and keywords
    $score = 0;
    $response_length = strlen($response);
    
    // Base score for providing a response
    if ($response_length > 10) {
        $score += 30;
    }
    
    // Additional points for longer, more detailed responses
    if ($response_length > 50) {
        $score += 20;
    }
    if ($response_length > 100) {
        $score += 20;
    }
    
    // Points for using customer service keywords
    $keywords = ['apologize', 'sorry', 'understand', 'help', 'assist', 'resolve', 'solution', 'immediately', 'compensate', 'discount'];
    $keyword_count = 0;
    foreach ($keywords as $keyword) {
        if (stripos($response, $keyword) !== false) {
            $keyword_count++;
        }
    }
    
    $score += ($keyword_count * 5);
    
    // Cap score at 100
    $score = min($score, 100);
    
    // In a real implementation, save the response to database
    // saveCustomerServiceResponse($_SESSION['user_id'], $scenario_id, $response, $score);
    
    // Return success response
    echo json_encode([
        'success' => true,
        'score' => $score,
        'message' => 'Response submitted successfully!',
        'feedback' => generateFeedback($score, $response_length, $keyword_count)
    ]);
    
} catch (Exception $e) {
    error_log("Error in submit-customer-service.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}

function generateFeedback($score, $length, $keywords) {
    $feedback = [];
    
    if ($score >= 80) {
        $feedback[] = "Excellent response! You demonstrated strong customer service skills.";
    } elseif ($score >= 60) {
        $feedback[] = "Good response! Consider adding more specific solutions.";
    } else {
        $feedback[] = "Try to be more detailed and empathetic in your response.";
    }
    
    if ($length < 50) {
        $feedback[] = "Consider providing more detailed responses.";
    }
    
    if ($keywords < 3) {
        $feedback[] = "Use more customer service keywords like 'apologize', 'help', 'resolve'.";
    }
    
    return implode(' ', $feedback);
}
?>
