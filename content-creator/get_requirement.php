<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a content creator
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'content_creator') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $requirement_id = (int)$_GET['id'];

    $query = "SELECT * FROM course_requirements WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $requirement_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($requirement = mysqli_fetch_assoc($result)) {
        echo json_encode(['success' => true, 'requirement' => $requirement]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Requirement not found']);
    }

    mysqli_stmt_close($stmt);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>