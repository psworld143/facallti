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

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get form data
$college_name = sanitize_input($_POST['college_name'] ?? '');
$short_name = sanitize_input($_POST['short_name'] ?? '');
$description = sanitize_input($_POST['description'] ?? '');

// Validate required fields
if (empty($college_name) || empty($short_name)) {
    echo json_encode(['success' => false, 'message' => 'College name and short name are required']);
    exit();
}

// Check if college name already exists
$check_query = "SELECT id FROM colleges WHERE name = ?";
$check_stmt = mysqli_prepare($conn, $check_query);
mysqli_stmt_bind_param($check_stmt, "s", $college_name);
mysqli_stmt_execute($check_stmt);
$check_result = mysqli_stmt_get_result($check_stmt);

if (mysqli_num_rows($check_result) > 0) {
    echo json_encode(['success' => false, 'message' => 'College name already exists']);
    exit();
}

// Check if short name already exists
$check_short_query = "SELECT id FROM colleges WHERE short_name = ?";
$check_short_stmt = mysqli_prepare($conn, $check_short_query);
mysqli_stmt_bind_param($check_short_stmt, "s", $short_name);
mysqli_stmt_execute($check_short_stmt);
$check_short_result = mysqli_stmt_get_result($check_short_stmt);

if (mysqli_num_rows($check_short_result) > 0) {
    echo json_encode(['success' => false, 'message' => 'Short name already exists']);
    exit();
}

// Insert new college
$insert_query = "INSERT INTO colleges (name, short_name, description, created_by) VALUES (?, ?, ?, ?)";
$insert_stmt = mysqli_prepare($conn, $insert_query);
mysqli_stmt_bind_param($insert_stmt, "sssi", $college_name, $short_name, $description, $_SESSION['user_id']);

if (mysqli_stmt_execute($insert_stmt)) {
    echo json_encode(['success' => true, 'message' => 'College added successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error adding college: ' . mysqli_error($conn)]);
}
?>
