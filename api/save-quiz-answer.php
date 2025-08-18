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

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['submission_id']) || !isset($input['question_id']) || !isset($input['answer'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$submission_id = (int)$input['submission_id'];
$question_id = (int)$input['question_id'];
$answer = $input['answer'];
$student_id = $_SESSION['user_id'];

// Verify submission belongs to the student
$verify_query = "SELECT qs.id FROM quiz_submissions qs
                WHERE qs.id = ? AND qs.student_id = ? AND qs.status IN ('started', 'in_progress')";
$verify_stmt = mysqli_prepare($conn, $verify_query);
mysqli_stmt_bind_param($verify_stmt, "ii", $submission_id, $student_id);
mysqli_stmt_execute($verify_stmt);
$verify_result = mysqli_stmt_get_result($verify_stmt);

if (mysqli_num_rows($verify_result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid submission or access denied']);
    exit;
}

// Check if answer already exists
$check_query = "SELECT id FROM quiz_answers WHERE submission_id = ? AND question_id = ?";
$check_stmt = mysqli_prepare($conn, $check_query);
mysqli_stmt_bind_param($check_stmt, "ii", $submission_id, $question_id);
mysqli_stmt_execute($check_stmt);
$check_result = mysqli_stmt_get_result($check_stmt);

if (mysqli_num_rows($check_result) > 0) {
    // Update existing answer
    $update_query = "UPDATE quiz_answers SET answer = ?, updated_at = NOW() WHERE submission_id = ? AND question_id = ?";
    $update_stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($update_stmt, "sii", $answer, $submission_id, $question_id);

    if (mysqli_stmt_execute($update_stmt)) {
        echo json_encode(['success' => true, 'message' => 'Answer updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating answer']);
    }
} else {
    // Insert new answer
    $insert_query = "INSERT INTO quiz_answers (submission_id, question_id, answer, created_at, updated_at)
                    VALUES (?, ?, ?, NOW(), NOW())";
    $insert_stmt = mysqli_prepare($conn, $insert_query);
    mysqli_stmt_bind_param($insert_stmt, "iis", $submission_id, $question_id, $answer);

    if (mysqli_stmt_execute($insert_stmt)) {
        // Update submission status to in_progress
        $update_status_query = "UPDATE quiz_submissions SET status = 'in_progress', updated_at = NOW() WHERE id = ?";
        $update_status_stmt = mysqli_prepare($conn, $update_status_query);
        mysqli_stmt_bind_param($update_status_stmt, "i", $submission_id);
        mysqli_stmt_execute($update_status_stmt);

        echo json_encode(['success' => true, 'message' => 'Answer saved successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error saving answer']);
    }
}
?>