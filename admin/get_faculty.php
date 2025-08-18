<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

check_admin();

if (isset($_GET['id'])) {
    $faculty_id = (int)$_GET['id'];

    $query = "SELECT id, first_name, last_name, position, department, email, bio, image_url, is_active, created_at FROM faculty WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $faculty_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($faculty = mysqli_fetch_assoc($result)) {
        header('Content-Type: application/json');
        echo json_encode($faculty);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Faculty member not found']);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Faculty ID required']);
}
?>