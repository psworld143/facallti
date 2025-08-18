<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Check if user is logged in and has student role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if (!isset($_GET['submission_id'])) {
    echo json_encode(['success' => false, 'message' => 'Submission ID is required']);
    exit;
}

$submission_id = (int)$_GET['submission_id'];
$student_id = $_SESSION['user_id'];

// Verify submission belongs to the student
$verify_query = "SELECT id FROM quiz_submissions WHERE id = ? AND student_id = ?";
$verify_stmt = mysqli_prepare($conn, $verify_query);
mysqli_stmt_bind_param($verify_stmt, "ii", $submission_id, $student_id);
mysqli_stmt_execute($verify_stmt);
$verify_result = mysqli_stmt_get_result($verify_stmt);

if (mysqli_num_rows($verify_result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid submission or access denied']);
    exit;
}

// Get saved answers
$answers_query = "SELECT question_id, answer FROM quiz_answers WHERE submission_id = ?";
$answers_stmt = mysqli_prepare($conn, $answers_query);
mysqli_stmt_bind_param($answers_stmt, "i", $submission_id);
mysqli_stmt_execute($answers_stmt);
$answers_result = mysqli_stmt_get_result($answers_stmt);

$answers = [];
while ($answer = mysqli_fetch_assoc($answers_result)) {
    $answers[$answer['question_id']] = $answer['answer'];
}

echo json_encode([
    'success' => true,
    'answers' => $answers
]);
?>