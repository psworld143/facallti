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
    $curriculum_id = (int)$_GET['id'];

    $query = "SELECT cc.*, prereq.subject_code as prerequisite_code, prereq.subject_title as prerequisite_title
              FROM course_curriculum cc
              LEFT JOIN course_curriculum prereq ON cc.prerequisite_id = prereq.id
              WHERE cc.id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $curriculum_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($curriculum = mysqli_fetch_assoc($result)) {
        echo json_encode(['success' => true, 'curriculum' => $curriculum]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Curriculum not found']);
    }

    mysqli_stmt_close($stmt);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>