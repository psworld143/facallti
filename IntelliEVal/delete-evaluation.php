<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has guidance_officer role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guidance_officer') {
    header('Location: ../login.php');
    exit();
}

$message = '';
$message_type = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_evaluation') {
    $evaluation_id = (int)$_POST['evaluation_id'];

    // Start transaction
    mysqli_begin_transaction($conn);

    try {
        // First, check if the evaluation exists and get its details
        $check_query = "SELECT es.*, mec.name as category_name,
                       CASE
                           WHEN es.evaluator_type = 'student' THEN evaluator_s.first_name
                           WHEN es.evaluator_type = 'teacher' THEN evaluator_f.first_name
                           WHEN es.evaluator_type = 'head' THEN evaluator_u.first_name
                           ELSE 'Unknown'
                       END as evaluator_first_name,
                       CASE
                           WHEN es.evaluator_type = 'student' THEN evaluator_s.last_name
                           WHEN es.evaluator_type = 'teacher' THEN evaluator_f.last_name
                           WHEN es.evaluator_type = 'head' THEN evaluator_u.last_name
                           ELSE 'Unknown'
                       END as evaluator_last_name
                       FROM evaluation_sessions es
                       JOIN main_evaluation_categories mec ON es.main_category_id = mec.id
                       LEFT JOIN students evaluator_s ON es.evaluator_id = evaluator_s.id AND es.evaluator_type = 'student'
                       LEFT JOIN faculty evaluator_f ON es.evaluator_id = evaluator_f.id AND es.evaluator_type = 'teacher'
                       LEFT JOIN users evaluator_u ON es.evaluator_id = evaluator_u.id AND es.evaluator_type = 'head'
                       WHERE es.id = ?";
        $check_stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($check_stmt, "i", $evaluation_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        $evaluation = mysqli_fetch_assoc($check_result);

        if (!$evaluation) {
            throw new Exception("Evaluation not found.");
        }

        // Check if evaluation has responses
        $responses_query = "SELECT COUNT(*) as count FROM evaluation_responses WHERE evaluation_session_id = ?";
        $responses_stmt = mysqli_prepare($conn, $responses_query);
        mysqli_stmt_bind_param($responses_stmt, "i", $evaluation_id);
        mysqli_stmt_execute($responses_stmt);
        $responses_result = mysqli_stmt_get_result($responses_stmt);
        $responses_count = mysqli_fetch_assoc($responses_result)['count'];

        // Delete related evaluation responses first (due to foreign key constraints)
        $delete_responses = "DELETE FROM evaluation_responses WHERE evaluation_session_id = ?";
        $delete_responses_stmt = mysqli_prepare($conn, $delete_responses);
        mysqli_stmt_bind_param($delete_responses_stmt, "i", $evaluation_id);
        mysqli_stmt_execute($delete_responses_stmt);

        // Delete the evaluation session
        $delete_evaluation = "DELETE FROM evaluation_sessions WHERE id = ?";
        $delete_evaluation_stmt = mysqli_prepare($conn, $delete_evaluation);
        mysqli_stmt_bind_param($delete_evaluation_stmt, "i", $evaluation_id);
        mysqli_stmt_execute($delete_evaluation_stmt);

        if (mysqli_affected_rows($conn) > 0) {
            // Commit transaction
            mysqli_commit($conn);

            $message = "Evaluation deleted successfully!";
            if ($responses_count > 0) {
                $message .= " {$responses_count} response(s) were also deleted.";
            }
            $message_type = "success";
        } else {
            throw new Exception("Failed to delete evaluation.");
        }

    } catch (Exception $e) {
        // Rollback transaction on error
        mysqli_rollback($conn);
        $message = "Error deleting evaluation: " . $e->getMessage();
        $message_type = "error";
    }
}

// Redirect back to all-evaluations page with message
$_SESSION['message'] = $message;
$_SESSION['message_type'] = $message_type;
header('Location: all-evaluations.php');
exit();
?>