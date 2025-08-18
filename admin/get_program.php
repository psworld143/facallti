<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

check_admin();

if (isset($_GET['id'])) {
    $program_id = (int)$_GET['id'];

    $query = "SELECT * FROM academic_programs WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $program_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($program = mysqli_fetch_assoc($result)) {
        header('Content-Type: application/json');
        echo json_encode($program);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Program not found']);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Program ID required']);
}
?>