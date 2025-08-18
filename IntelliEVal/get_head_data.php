<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has guidance_officer role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guidance_officer') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if it's an AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid head ID']);
    exit();
}

$head_id = (int)$_GET['id'];

// Fetch head data
$query = "SELECT u.*, h.department, h.position, h.phone, h.status as head_status
          FROM users u
          LEFT JOIN heads h ON u.id = h.user_id
          WHERE u.id = ? AND u.role = 'head'";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $head_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($result && mysqli_num_rows($result) > 0) {
    $head = mysqli_fetch_assoc($result);

    // Prepare response data
    $response_data = [
        'id' => $head['id'],
        'first_name' => $head['first_name'],
        'last_name' => $head['last_name'],
        'email' => $head['email'],
        'phone' => $head['phone'],
        'department' => $head['department'],
        'position' => $head['position'],
        'status' => $head['status'],
        'username' => $head['username']
    ];

    echo json_encode(['success' => true, 'head' => $response_data]);
} else {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Head not found']);
}

mysqli_close($conn);
?>