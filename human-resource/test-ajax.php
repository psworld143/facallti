<?php
session_start();
header('Content-Type: application/json');

echo json_encode([
    'success' => true,
    'message' => 'AJAX test successful',
    'post_data' => $_POST,
    'session_data' => [
        'user_id' => $_SESSION['user_id'] ?? 'NOT SET',
        'role' => $_SESSION['role'] ?? 'NOT SET'
    ]
]);
?>
