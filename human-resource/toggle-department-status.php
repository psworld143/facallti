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

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['department_id']) || empty($input['department_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid department ID']);
    exit();
}

if (!isset($input['status']) || !in_array($input['status'], [0, 1])) {
    echo json_encode(['success' => false, 'message' => 'Invalid status value']);
    exit();
}

// Decrypt the department ID
$department_id = safe_decrypt_id($input['department_id']);
if ($department_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid department ID']);
    exit();
}

$status = (int)$input['status'];

// Check if department exists
$check_query = "SELECT id, name FROM departments WHERE id = ?";
$check_stmt = mysqli_prepare($conn, $check_query);
mysqli_stmt_bind_param($check_stmt, "i", $department_id);
mysqli_stmt_execute($check_stmt);
$check_result = mysqli_stmt_get_result($check_stmt);

if (mysqli_num_rows($check_result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Department not found']);
    exit();
}

$department = mysqli_fetch_assoc($check_result);

// Update department status
$update_query = "UPDATE departments SET is_active = ?, updated_at = NOW() WHERE id = ?";
$update_stmt = mysqli_prepare($conn, $update_query);
mysqli_stmt_bind_param($update_stmt, "ii", $status, $department_id);

if (mysqli_stmt_execute($update_stmt)) {
    $action = $status ? 'activated' : 'deactivated';
    echo json_encode([
        'success' => true,
        'message' => "Department '{$department['name']}' has been {$action} successfully"
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Error updating department status: ' . mysqli_error($conn)
    ]);
}
?>
