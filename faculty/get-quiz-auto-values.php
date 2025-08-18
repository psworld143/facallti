<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has teacher role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

$quiz_id = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : 0;

if ($quiz_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid quiz ID']);
    exit();
}

// Verify quiz ownership
$quiz_check_query = "SELECT id FROM quizzes WHERE id = ? AND teacher_id = ?";
$quiz_check_stmt = mysqli_prepare($conn, $quiz_check_query);
mysqli_stmt_bind_param($quiz_check_stmt, "ii", $quiz_id, $_SESSION['user_id']);
mysqli_stmt_execute($quiz_check_stmt);
$quiz_check_result = mysqli_stmt_get_result($quiz_check_stmt);

if (mysqli_num_rows($quiz_check_result) == 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Quiz not found or access denied']);
    exit();
}

// Get automatic values
$auto_values_query = "SELECT
                      COALESCE(MAX(order_number), 0) + 1 as next_order,
                      COALESCE(AVG(points), 1) as avg_points
                      FROM quiz_questions
                      WHERE quiz_id = ?";
$auto_values_stmt = mysqli_prepare($conn, $auto_values_query);
mysqli_stmt_bind_param($auto_values_stmt, "i", $quiz_id);
mysqli_stmt_execute($auto_values_stmt);
$auto_values = mysqli_fetch_assoc(mysqli_stmt_get_result($auto_values_stmt));

$response = [
    'success' => true,
    'next_order' => (int)$auto_values['next_order'],
    'avg_points' => round($auto_values['avg_points'])
];

header('Content-Type: application/json');
echo json_encode($response);
?>