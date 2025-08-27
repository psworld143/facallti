<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/id_encryption.php';

check_admin();

if (isset($_GET['id'])) {
    $post_id = safe_decrypt_id($_GET['id']);

    $query = "SELECT p.*, u.first_name, u.last_name, u.username
              FROM posts p
              LEFT JOIN users u ON p.author_id = u.id
              WHERE p.id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $post_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($post = mysqli_fetch_assoc($result)) {
        header('Content-Type: application/json');
        echo json_encode($post);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Post not found']);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Post ID required']);
}
?>