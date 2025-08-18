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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'stop_evaluation') {
    $category_id = (int)$_POST['category_id'];
    $evaluation_type = sanitize_input($_POST['evaluation_type']);

    // Start transaction
    mysqli_begin_transaction($conn);

    try {
        // Get the main category details
        $category_query = "SELECT * FROM main_evaluation_categories WHERE id = ? AND status = 'active'";
        $category_stmt = mysqli_prepare($conn, $category_query);
        mysqli_stmt_bind_param($category_stmt, "i", $category_id);
        mysqli_stmt_execute($category_stmt);
        $category_result = mysqli_stmt_get_result($category_stmt);
        $main_category = mysqli_fetch_assoc($category_result);

        if (!$main_category) {
            throw new Exception("Main evaluation category not found or inactive.");
        }

        // Find active evaluation schedules for this evaluation type
        $schedule_query = "SELECT * FROM evaluation_schedules
                          WHERE evaluation_type = ?
                          AND status = 'active'";
        $schedule_stmt = mysqli_prepare($conn, $schedule_query);
        mysqli_stmt_bind_param($schedule_stmt, "s", $evaluation_type);
        mysqli_stmt_execute($schedule_stmt);
        $schedule_result = mysqli_stmt_get_result($schedule_stmt);

        $schedules_updated = 0;
        while ($schedule = mysqli_fetch_assoc($schedule_result)) {
            // Update schedule status to completed
            $update_schedule = "UPDATE evaluation_schedules
                               SET status = 'completed', updated_at = NOW()
                               WHERE id = ?";
            $update_stmt = mysqli_prepare($conn, $update_schedule);
            mysqli_stmt_bind_param($update_stmt, "i", $schedule['id']);

            if (mysqli_stmt_execute($update_stmt)) {
                $schedules_updated++;
            }
        }

        // Update evaluation sessions based on whether they have responses
        // Only mark sessions as completed if they have actual evaluation responses
        $update_sessions_with_responses = "UPDATE evaluation_sessions es
                                         SET status = 'completed', updated_at = NOW()
                                         WHERE es.main_category_id = ?
                                         AND es.status = 'draft'
                                         AND EXISTS (
                                             SELECT 1 FROM evaluation_responses er
                                             WHERE er.evaluation_session_id = es.id
                                         )";
        $update_sessions_with_responses_stmt = mysqli_prepare($conn, $update_sessions_with_responses);
        mysqli_stmt_bind_param($update_sessions_with_responses_stmt, "i", $category_id);
        mysqli_stmt_execute($update_sessions_with_responses_stmt);
        $sessions_completed = mysqli_affected_rows($conn);

        // Delete sessions without responses (cancelled evaluations) and their related data
        // First, get the IDs of sessions to be deleted
        $get_sessions_to_delete = "SELECT es.id FROM evaluation_sessions es
                                  WHERE es.main_category_id = ?
                                  AND es.status = 'draft'
                                  AND NOT EXISTS (
                                      SELECT 1 FROM evaluation_responses er
                                      WHERE er.evaluation_session_id = es.id
                                  )";
        $get_sessions_stmt = mysqli_prepare($conn, $get_sessions_to_delete);
        mysqli_stmt_bind_param($get_sessions_stmt, "i", $category_id);
        mysqli_stmt_execute($get_sessions_stmt);
        $sessions_to_delete_result = mysqli_stmt_get_result($get_sessions_stmt);

        $sessions_cancelled = 0;
        $deleted_responses = 0;

        while ($session = mysqli_fetch_assoc($sessions_to_delete_result)) {
            $session_id = $session['id'];

            // Delete related evaluation responses first (due to foreign key constraints)
            $delete_responses = "DELETE FROM evaluation_responses WHERE evaluation_session_id = ?";
            $delete_responses_stmt = mysqli_prepare($conn, $delete_responses);
            mysqli_stmt_bind_param($delete_responses_stmt, "i", $session_id);
            mysqli_stmt_execute($delete_responses_stmt);
            $deleted_responses += mysqli_affected_rows($conn);

            // Delete the evaluation session
            $delete_session = "DELETE FROM evaluation_sessions WHERE id = ?";
            $delete_session_stmt = mysqli_prepare($conn, $delete_session);
            mysqli_stmt_bind_param($delete_session_stmt, "i", $session_id);
            mysqli_stmt_execute($delete_session_stmt);

            if (mysqli_affected_rows($conn) > 0) {
                $sessions_cancelled++;
            }
        }

        $sessions_updated = $sessions_completed + $sessions_cancelled;

        // Commit transaction
        mysqli_commit($conn);

        $message = "Evaluation stopped successfully! {$schedules_updated} schedule(s) updated. {$sessions_completed} session(s) completed, {$sessions_cancelled} cancelled session(s) deleted from database.";
        $message_type = "success";

    } catch (Exception $e) {
        // Rollback transaction on error
        mysqli_rollback($conn);
        $message = "Error stopping evaluation: " . $e->getMessage();
        $message_type = "error";
    }
}

// Redirect back to evaluations page with message
$_SESSION['message'] = $message;
$_SESSION['message_type'] = $message_type;
header('Location: evaluations.php');
exit();
?>