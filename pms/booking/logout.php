<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Log the logout activity if user is logged in
if (isset($_SESSION['user_id'])) {
    try {
        require_once '../includes/database.php';
        require_once 'includes/functions.php';
        
        // Log the logout activity
        if (function_exists('logActivity')) {
            logActivity($_SESSION['user_id'], 'logout', 'User logged out successfully');
        }
    } catch (Exception $e) {
        // If logging fails, continue with logout
        error_log("Logout logging failed: " . $e->getMessage());
    }
}

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Clear any output buffers
while (ob_get_level()) {
    ob_end_clean();
}

// Redirect to login page with a success message
header('Location: login.php?logout=success');
exit();
?>
