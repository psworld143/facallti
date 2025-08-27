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
    // Sample scenario data - in a real implementation, this would come from the database
    $scenarios = [
        'front_desk_basic' => [
            'id' => 'front_desk_basic',
            'title' => 'Front Desk Check-in Process',
            'description' => 'A guest arrives at the front desk to check in. They have a reservation but there seems to be an issue with their room assignment.',
            'instructions' => 'Follow the proper check-in procedure, handle the room assignment issue professionally, and ensure the guest is satisfied with their stay.',
            'difficulty' => 'beginner',
            'category' => 'front_desk',
            'estimated_time' => 15,
            'points' => 100,
            'questions' => [
                [
                    'question' => 'What is the first thing you should do when a guest approaches the front desk?',
                    'options' => [
                        ['value' => 'a', 'text' => 'Ask for their ID and credit card'],
                        ['value' => 'b', 'text' => 'Greet them warmly and ask how you can help'],
                        ['value' => 'c', 'text' => 'Start typing on the computer'],
                        ['value' => 'd', 'text' => 'Ask them to wait']
                    ],
                    'correct_answer' => 'b'
                ],
                [
                    'question' => 'The guest\'s room is not ready. What should you do?',
                    'options' => [
                        ['value' => 'a', 'text' => 'Tell them to come back later'],
                        ['value' => 'b', 'text' => 'Offer to store their luggage and provide a timeline'],
                        ['value' => 'c', 'text' => 'Give them a different room without checking'],
                        ['value' => 'd', 'text' => 'Ignore the issue']
                    ],
                    'correct_answer' => 'b'
                ],
                [
                    'question' => 'What information should you verify during check-in?',
                    'options' => [
                        ['value' => 'a', 'text' => 'Only their name'],
                        ['value' => 'b', 'text' => 'Name, ID, payment method, and room preferences'],
                        ['value' => 'c', 'text' => 'Just their credit card'],
                        ['value' => 'd', 'text' => 'Nothing, just give them the key']
                    ],
                    'correct_answer' => 'b'
                ]
            ]
        ],
        'customer_service_basic' => [
            'id' => 'customer_service_basic',
            'title' => 'Handling Guest Complaints',
            'description' => 'A guest is upset about noise from the room next door and wants immediate action.',
            'instructions' => 'Listen to the guest\'s concerns, show empathy, and take appropriate action to resolve the situation.',
            'difficulty' => 'beginner',
            'category' => 'front_desk',
            'estimated_time' => 20,
            'points' => 150,
            'questions' => [
                [
                    'question' => 'What is the first step when handling a guest complaint?',
                    'options' => [
                        ['value' => 'a', 'text' => 'Immediately offer a discount'],
                        ['value' => 'b', 'text' => 'Listen actively and acknowledge their concern'],
                        ['value' => 'c', 'text' => 'Tell them to call management'],
                        ['value' => 'd', 'text' => 'Ignore the complaint']
                    ],
                    'correct_answer' => 'b'
                ],
                [
                    'question' => 'The guest wants immediate action. What should you do?',
                    'options' => [
                        ['value' => 'a', 'text' => 'Tell them to wait until morning'],
                        ['value' => 'b', 'text' => 'Take immediate action and follow up'],
                        ['value' => 'c', 'text' => 'Ignore the request'],
                        ['value' => 'd', 'text' => 'Call the police']
                    ],
                    'correct_answer' => 'b'
                ]
            ]
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
    error_log("Error in get-scenario-details.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}
?>
