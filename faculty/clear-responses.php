<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has teacher role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Get session ID from GET parameter
$session_id = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;

if (!$session_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid session ID']);
    exit();
}

// Verify the evaluation session belongs to the current user
$session_query = "SELECT id FROM evaluation_sessions WHERE id = ? AND evaluator_id = ?";
$session_stmt = mysqli_prepare($conn, $session_query);
mysqli_stmt_bind_param($session_stmt, "ii", $session_id, $_SESSION['user_id']);
mysqli_stmt_execute($session_stmt);
$session_result = mysqli_stmt_get_result($session_stmt);

if (mysqli_num_rows($session_result) === 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Evaluation session not found or access denied']);
    exit();
}

// Clear all responses for this evaluation session
$clear_query = "DELETE FROM evaluation_responses WHERE evaluation_session_id = ?";
$clear_stmt = mysqli_prepare($conn, $clear_query);
mysqli_stmt_bind_param($clear_stmt, "i", $session_id);

if (mysqli_stmt_execute($clear_stmt)) {
    // Update the evaluation session status back to draft if it was completed
    $update_query = "UPDATE evaluation_sessions SET status = 'draft', updated_at = NOW() WHERE id = ?";
    $update_stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($update_stmt, "i", $session_id);
    mysqli_stmt_execute($update_stmt);

    echo json_encode(['success' => true, 'message' => 'All responses cleared successfully']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to clear responses: ' . mysqli_error($conn)]);
}

mysqli_close($conn);
?>