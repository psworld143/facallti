<?php
session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_type = '';

// Determine user type based on session role
if (isset($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'faculty':
            $user_type = 'employee';
            break;
        case 'head':
            $user_type = 'department_head';
            break;
        case 'hr':
            $user_type = 'hr';
            break;
    }
}

if ($user_type) {
    // Get unread notification count
    $query = "SELECT COUNT(*) as count FROM leave_notifications 
              WHERE recipient_id = ? AND recipient_type = ? AND is_read = 0";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'is', $user_id, $user_type);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    
    echo json_encode([
        'success' => true, 
        'count' => $row['count']
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid user type']);
}
?>
