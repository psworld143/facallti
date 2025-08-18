<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and has guidance_officer role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guidance_officer') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Check if main_category_id is provided
if (!isset($_GET['main_category_id']) || empty($_GET['main_category_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Main category ID is required']);
    exit();
}

$main_category_id = (int)$_GET['main_category_id'];

// Get sub-categories for the selected main category
$query = "SELECT id, name FROM evaluation_sub_categories
          WHERE main_category_id = ? AND status = 'active'
          ORDER BY order_number, name";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $main_category_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$sub_categories = [];
while ($row = mysqli_fetch_assoc($result)) {
    $sub_categories[] = [
        'id' => $row['id'],
        'name' => $row['name']
    ];
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($sub_categories);
?>