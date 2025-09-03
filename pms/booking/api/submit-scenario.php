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

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit();
}

try {
    // For now, we'll use sample data to calculate scores
    // In a real implementation, this would fetch the scenario from database
    $scenarios = [
        'front_desk_basic' => [
            'questions' => [
                ['correct_answer' => 'b'],
                ['correct_answer' => 'b'],
                ['correct_answer' => 'b']
            ]
        ],
        'customer_service' => [
            'questions' => [
                ['correct_answer' => 'b'],
                ['correct_answer' => 'b'],
                ['correct_answer' => 'b']
            ]
        ],
        'problem_solving' => [
            'questions' => [
                ['correct_answer' => 'b'],
                ['correct_answer' => 'b'],
                ['correct_answer' => 'b']
            ]
        ]
    ];
    
    // Calculate score based on answers
    $totalQuestions = 0;
    $correctAnswers = 0;
    $scenarioId = null;
    
    foreach ($input as $questionKey => $answer) {
        if (strpos($questionKey, 'q') === 0) {
            $totalQuestions++;
            
            // Determine which scenario this belongs to based on the number of questions
            if (!$scenarioId) {
                if ($totalQuestions <= 3) {
                    $scenarioId = 'front_desk_basic';
                } elseif ($totalQuestions <= 6) {
                    $scenarioId = 'customer_service';
                } else {
                    $scenarioId = 'problem_solving';
                }
            }
            
            // Check if answer is correct
            $questionIndex = intval(substr($questionKey, 1));
            if (isset($scenarios[$scenarioId]['questions'][$questionIndex])) {
                if ($answer === $scenarios[$scenarioId]['questions'][$questionIndex]['correct_answer']) {
                    $correctAnswers++;
                }
            }
        }
    }
    
    $score = $totalQuestions > 0 ? round(($correctAnswers / $totalQuestions) * 100) : 0;
    
    // In a real implementation, save the attempt to database
    // saveTrainingAttempt($_SESSION['user_id'], $scenarioId, $score, $input);
    
    // Return success response
    echo json_encode([
        'success' => true,
        'score' => $score,
        'correct_answers' => $correctAnswers,
        'total_questions' => $totalQuestions,
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
