<?php
session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

if ($action === 'mark_all_read') {
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
        $query = "UPDATE leave_notifications SET is_read = 1, read_at = NOW() 
                  WHERE recipient_id = ? AND recipient_type = ? AND is_read = 0";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'is', $user_id, $user_type);
        
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true, 'message' => 'All notifications marked as read']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to mark notifications as read']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid user type']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>
