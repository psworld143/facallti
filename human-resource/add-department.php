<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has human_resource role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'human_resource') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get form data
$department_name = sanitize_input($_POST['department_name'] ?? '');
$description = sanitize_input($_POST['description'] ?? '');

// Validate required fields
if (empty($department_name)) {
    echo json_encode(['success' => false, 'message' => 'Department name is required']);
    exit();
}

// Check if department name already exists
$check_query = "SELECT id FROM departments WHERE name = ?";
$check_stmt = mysqli_prepare($conn, $check_query);
mysqli_stmt_bind_param($check_stmt, "s", $department_name);
mysqli_stmt_execute($check_stmt);
$check_result = mysqli_stmt_get_result($check_stmt);

if (mysqli_num_rows($check_result) > 0) {
    echo json_encode(['success' => false, 'message' => 'Department name already exists']);
    exit();
}

// Insert new department
$insert_query = "INSERT INTO departments (name, description, is_active, created_at) VALUES (?, ?, 1, NOW())";
$insert_stmt = mysqli_prepare($conn, $insert_query);
mysqli_stmt_bind_param($insert_stmt, "ss", $department_name, $description);

if (mysqli_stmt_execute($insert_stmt)) {
    echo json_encode([
        'success' => true,
        'message' => 'Department added successfully',
        'department_id' => mysqli_insert_id($conn)
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Error adding department: ' . mysqli_error($conn)
    ]);
}
?>
