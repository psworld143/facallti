<?php
session_start();
require_once '../includes/error_handler.php';
require_once '../includes/database.php';

// Log the logout activity if user was logged in
if (isset($_SESSION['pos_user_id'])) {
    try {
        // Log POS logout
        $stmt = $pdo->prepare("INSERT INTO pos_activity_log (user_id, action, description, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([
            $_SESSION['pos_user_id'], 
            'logout', 
            'User logged out from POS system', 
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    } catch (PDOException $e) {
        error_log("Error logging POS logout: " . $e->getMessage());
    }
    
    // Clear all POS session variables
    unset($_SESSION['pos_user_id']);
    unset($_SESSION['pos_user_name']);
    unset($_SESSION['pos_user_role']);
    unset($_SESSION['pos_login_type']);
    unset($_SESSION['pos_demo_mode']);
}

// Destroy the session completely
session_destroy();

// Redirect to POS login
header('Location: login.php');
exit();
?>
