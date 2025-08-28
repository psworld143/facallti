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

// Check if college ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid college ID']);
    exit();
}

// Decrypt the college ID
$college_id = safe_decrypt_id($_GET['id']);
if ($college_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid college ID']);
    exit();
}

// Get college details
$query = "SELECT * FROM colleges WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $college_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($college = mysqli_fetch_assoc($result)) {
    echo json_encode([
        'success' => true,
        'college' => $college
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'College not found']);
}
?>
