<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/id_encryption.php';

// Check if user is logged in and has human_resource role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'human_resource') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if department ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid department ID']);
    exit();
}

// Decrypt the department ID
$department_id = safe_decrypt_id($_GET['id']);
if ($department_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid department ID']);
    exit();
}

// Get department details
$query = "SELECT * FROM departments WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $department_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($department = mysqli_fetch_assoc($result)) {
    echo json_encode([
        'success' => true,
        'department' => $department
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Department not found'
    ]);
}
?>
