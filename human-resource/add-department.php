<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has human_resource role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'human_resource') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get form data
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$description = isset($_POST['description']) ? trim($_POST['description']) : '';
$icon = isset($_POST['icon']) ? trim($_POST['icon']) : '';
$color_theme = isset($_POST['color_theme']) ? trim($_POST['color_theme']) : '#FF6B35';
$sort_order = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;
$is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

// Validate required fields
if (empty($name)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Department name is required']);
    exit();
}

if (empty($icon)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Icon class is required']);
    exit();
}

// Sanitize inputs
$name = sanitize_input($name);
$description = sanitize_input($description);
$icon = sanitize_input($icon);
$color_theme = sanitize_input($color_theme);

// Validate color theme format
if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color_theme)) {
    $color_theme = '#FF6B35'; // Default color if invalid
}

// Check if department name already exists
$check_query = "SELECT id FROM departments WHERE name = ? AND is_active = 1";
$check_stmt = mysqli_prepare($conn, $check_query);
mysqli_stmt_bind_param($check_stmt, 's', $name);
mysqli_stmt_execute($check_stmt);
$check_result = mysqli_stmt_get_result($check_stmt);

if (mysqli_num_rows($check_result) > 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'A department with this name already exists']);
    exit();
}

// Insert new department
$insert_query = "INSERT INTO departments (name, description, icon, color_theme, sort_order, is_active, created_by, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

$insert_stmt = mysqli_prepare($conn, $insert_query);
mysqli_stmt_bind_param($insert_stmt, 'ssssiii', 
    $name, 
    $description, 
    $icon, 
    $color_theme, 
    $sort_order, 
    $is_active, 
    $_SESSION['user_id']
);

if (mysqli_stmt_execute($insert_stmt)) {
    $new_department_id = mysqli_insert_id($conn);
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'message' => 'Department added successfully',
        'department_id' => $new_department_id
    ]);
} else {
    // Return error response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'Error adding department: ' . mysqli_error($conn)
    ]);
}

mysqli_stmt_close($insert_stmt);
mysqli_close($conn);
?>
