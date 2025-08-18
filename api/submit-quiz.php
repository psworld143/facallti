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

if (!isset($input['submission_id']) || !isset($input['answers']) || !isset($input['time_taken'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$submission_id = (int)$input['submission_id'];
$answers = $input['answers'];
$time_taken = (int)$input['time_taken'];
$student_id = $_SESSION['user_id'];

// Verify submission belongs to the student
$verify_query = "SELECT qs.*, q.total_points, q.time_limit
                FROM quiz_submissions qs
                JOIN quizzes q ON qs.quiz_id = q.id
                WHERE qs.id = ? AND qs.student_id = ? AND qs.status IN ('started', 'in_progress')";
$verify_stmt = mysqli_prepare($conn, $verify_query);
mysqli_stmt_bind_param($verify_stmt, "ii", $submission_id, $student_id);
mysqli_stmt_execute($verify_stmt);
$verify_result = mysqli_stmt_get_result($verify_stmt);

if (mysqli_num_rows($verify_result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid submission or access denied']);
    exit;
}

$submission = mysqli_fetch_assoc($verify_result);

// Calculate score
$score = 0;
$correct_answers = 0;
$total_questions = 0;

// Get all questions for this quiz
$questions_query = "SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY question_order ASC";
$questions_stmt = mysqli_prepare($conn, $questions_query);
mysqli_stmt_bind_param($questions_stmt, "i", $submission['quiz_id']);
mysqli_stmt_execute($questions_stmt);
$questions_result = mysqli_stmt_get_result($questions_stmt);

$questions = [];
while ($question = mysqli_fetch_assoc($questions_result)) {
    $questions[] = $question;
    $total_questions++;
}

// Check each answer
foreach ($questions as $question) {
    $student_answer = isset($answers[$question['id']]) ? $answers[$question['id']] : null;

    if ($student_answer !== null) {
        // Save the answer if not already saved
        $save_answer_query = "INSERT IGNORE INTO quiz_answers (submission_id, question_id, answer, created_at, updated_at)
                            VALUES (?, ?, ?, NOW(), NOW())";
        $save_answer_stmt = mysqli_prepare($conn, $save_answer_query);
        mysqli_stmt_bind_param($save_answer_stmt, "iis", $submission_id, $question['id'], $student_answer);
        mysqli_stmt_execute($save_answer_stmt);

        // Check if answer is correct
        if ($question['question_type'] === 'multiple_choice') {
            $correct_option_query = "SELECT id FROM quiz_question_options WHERE question_id = ? AND is_correct = 1";
            $correct_option_stmt = mysqli_prepare($conn, $correct_option_query);
            mysqli_stmt_bind_param($correct_option_stmt, "i", $question['id']);
            mysqli_stmt_execute($correct_option_stmt);
            $correct_option_result = mysqli_stmt_get_result($correct_option_stmt);
            $correct_option = mysqli_fetch_assoc($correct_option_result);

            if ($correct_option && $student_answer == $correct_option['id']) {
                $score += $question['points'];
                $correct_answers++;
            }
        } elseif ($question['question_type'] === 'true_false') {
            if ($student_answer === $question['correct_answer']) {
                $score += $question['points'];
                $correct_answers++;
            }
        }
    }
}

// Calculate percentage
$percentage = $total_questions > 0 ? round(($correct_answers / $total_questions) * 100, 2) : 0;

// Update submission with results
$update_query = "UPDATE quiz_submissions SET
                status = 'completed',
                score = ?,
                total_questions = ?,
                correct_answers = ?,
                time_taken = ?,
                completed_at = NOW(),
                updated_at = NOW()
                WHERE id = ?";
$update_stmt = mysqli_prepare($conn, $update_query);
mysqli_stmt_bind_param($update_stmt, "iiiii", $score, $total_questions, $correct_answers, $time_taken, $submission_id);

if (mysqli_stmt_execute($update_stmt)) {
    // Get updated leaderboard position
    $position_query = "SELECT COUNT(*) + 1 as position
                      FROM quiz_submissions
                      WHERE quiz_id = ? AND status = 'completed' AND score > ?";
    $position_stmt = mysqli_prepare($conn, $position_query);
    mysqli_stmt_bind_param($position_stmt, "ii", $submission['quiz_id'], $score);
    mysqli_stmt_execute($position_stmt);
    $position_result = mysqli_stmt_get_result($position_stmt);
    $position = mysqli_fetch_assoc($position_result);

    // Get total participants
    $participants_query = "SELECT COUNT(*) as total FROM quiz_submissions WHERE quiz_id = ? AND status = 'completed'";
    $participants_stmt = mysqli_prepare($conn, $participants_query);
    mysqli_stmt_bind_param($participants_stmt, "i", $submission['quiz_id']);
    mysqli_stmt_execute($participants_stmt);
    $participants_result = mysqli_stmt_get_result($participants_stmt);
    $total_participants = mysqli_fetch_assoc($participants_result)['total'];

    $results = [
        'score' => $score,
        'total_points' => $submission['total_points'],
        'correct_answers' => $correct_answers,
        'total_questions' => $total_questions,
        'percentage' => $percentage,
        'time_taken' => $time_taken,
        'position' => $position['position'],
        'total_participants' => $total_participants,
        'is_top_three' => $position['position'] <= 3
    ];

    echo json_encode([
        'success' => true,
        'message' => 'Quiz submitted successfully',
        'results' => $results
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error submitting quiz']);
}
?>