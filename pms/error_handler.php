<?php
/**
 * Unified Error Handler for PMS System
 * This file handles all errors in the PMS folder and redirects to main error pages
 * Include this file at the top of all PMS pages after session_start()
 */

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set error handler to redirect to main error pages
function pmsErrorHandler($errno, $errstr, $errfile, $errline) {
    // Log the error
    error_log("PMS Error [$errno]: $errstr in $errfile on line $errline");
    
    // Only handle fatal errors, not warnings or notices
    if (!in_array($errno, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        return false; // Let PHP handle non-fatal errors normally
    }
    
    // Check if headers have already been sent
    if (headers_sent()) {
        // If headers already sent, we can't redirect, so just log the error
        error_log("Cannot redirect - headers already sent. Error: [$errno] $errstr in $errfile on line $errline");
        return false; // Let PHP handle the error normally
    }
    
    // Redirect to 505.php for fatal errors
    header("Location: /seait/505.php");
    exit();
}

// Set the custom error handler
set_error_handler("pmsErrorHandler");

// Set exception handler
function pmsExceptionHandler($exception) {
    // Log the exception
    error_log("PMS Uncaught Exception: " . $exception->getMessage());
    
    // Check if headers have already been sent
    if (headers_sent()) {
        // If headers already sent, we can't redirect, so just log the error
        error_log("Cannot redirect - headers already sent. Exception: " . $exception->getMessage());
        return; // Let PHP handle the exception normally
    }
    
    // Redirect to 505.php for exceptions (server errors)
    header("Location: /seait/505.php");
    exit();
}

set_exception_handler("pmsExceptionHandler");

// Register shutdown function to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        // Log the fatal error
        error_log("PMS Fatal Error: " . $error['message'] . " in " . $error['file'] . " on line " . $error['line']);
        
        // Check if headers have already been sent
        if (!headers_sent()) {
            // Redirect to 505.php
            header("Location: /seait/505.php");
            exit();
        } else {
            // If headers already sent, just log the error
            error_log("Cannot redirect - headers already sent. Fatal Error: " . $error['message']);
        }
    }
});

// Database connection check function
function pmsCheckDatabaseConnection($conn) {
    if (!$conn) {
        error_log("PMS Database connection failed");
        if (!headers_sent()) {
            header("Location: /seait/505.php");
            exit();
        } else {
            error_log("Cannot redirect - headers already sent. Database connection failed.");
            return false;
        }
    }
    return true;
}

// Database query error check function
function pmsCheckDatabaseQuery($result, $query_name = "Unknown") {
    if (!$result) {
        global $conn;
        $error_message = mysqli_error($conn);
        error_log("PMS Database error in $query_name query: $error_message");
        if (!headers_sent()) {
            header("Location: /seait/505.php");
            exit();
        } else {
            error_log("Cannot redirect - headers already sent. Database error in $query_name query: $error_message");
            return false;
        }
    }
    return true;
}

// Database statement execution check function
function pmsCheckDatabaseStatement($stmt, $query_name = "Unknown") {
    if (!mysqli_stmt_execute($stmt)) {
        global $conn;
        $error_message = mysqli_error($conn);
        error_log("PMS Database error in $query_name statement: $error_message");
        if (!headers_sent()) {
            header("Location: /seait/505.php");
            exit();
        } else {
            error_log("Cannot redirect - headers already sent. Database error in $query_name statement: $error_message");
            return false;
        }
    }
    return true;
}

// Function to safely include files with error handling
function pmsSafeInclude($file_path) {
    if (!file_exists($file_path)) {
        error_log("PMS Required file not found: $file_path");
        if (!headers_sent()) {
            header("Location: /seait/505.php");
            exit();
        } else {
            error_log("Cannot redirect - headers already sent. Required file not found: $file_path");
            return false;
        }
    }
    
    try {
        require_once $file_path;
    } catch (Exception $e) {
        error_log("PMS Error including file $file_path: " . $e->getMessage());
        if (!headers_sent()) {
            header("Location: /seait/505.php");
            exit();
        } else {
            error_log("Cannot redirect - headers already sent. Error including file $file_path: " . $e->getMessage());
            return false;
        }
    }
    return true;
}

// Function to validate session
function pmsValidateSession() {
    if (!isset($_SESSION['user_id'])) {
        if (!headers_sent()) {
            header("Location: /seait/404.php");
            exit();
        } else {
            error_log("Cannot redirect - headers already sent. Session validation failed.");
            return false;
        }
    }
    return true;
}

// Function to validate user role
function pmsValidateRole($required_role) {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $required_role) {
        if (!headers_sent()) {
            header("Location: /seait/404.php");
            exit();
        } else {
            error_log("Cannot redirect - headers already sent. Role validation failed for role: $required_role");
            return false;
        }
    }
    return true;
}

// Function to sanitize and validate input
function pmsSanitizeAndValidate($input, $type = 'string') {
    if (empty($input)) {
        return null;
    }
    
    switch ($type) {
        case 'email':
            if (!filter_var($input, FILTER_VALIDATE_EMAIL)) {
                if (!headers_sent()) {
                    header("Location: /seait/404.php");
                    exit();
                } else {
                    error_log("Cannot redirect - headers already sent. Email validation failed.");
                    return false;
                }
            }
            return filter_var($input, FILTER_SANITIZE_EMAIL);
            
        case 'int':
            if (!is_numeric($input)) {
                if (!headers_sent()) {
                    header("Location: /seait/404.php");
                    exit();
                } else {
                    error_log("Cannot redirect - headers already sent. Integer validation failed.");
                    return false;
                }
            }
            return (int)$input;
            
        case 'float':
            if (!is_numeric($input)) {
                if (!headers_sent()) {
                    header("Location: /seait/404.php");
                    exit();
                } else {
                    error_log("Cannot redirect - headers already sent. Float validation failed.");
                    return false;
                }
            }
            return (float)$input;
            
        case 'url':
            if (!filter_var($input, FILTER_VALIDATE_URL)) {
                if (!headers_sent()) {
                    header("Location: /seait/404.php");
                    exit();
                } else {
                    error_log("Cannot redirect - headers already sent. URL validation failed.");
                    return false;
                }
            }
            return filter_var($input, FILTER_SANITIZE_URL);
            
        default:
            return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}

// Function to handle AJAX errors
function pmsHandleAjaxError($message, $error_code = 500) {
    http_response_code($error_code);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $message]);
    exit();
}

// Function to log user activity
function pmsLogUserActivity($action, $details = '') {
    if (isset($_SESSION['user_id'])) {
        global $conn;
        
        if (!$conn) {
            return; // If no database connection, skip logging
        }
        
        // Check if user_activity_logs table exists first
        $table_check = "SHOW TABLES LIKE 'user_activity_logs'";
        $table_result = mysqli_query($conn, $table_check);
        
        if (!$table_result || mysqli_num_rows($table_result) === 0) {
            return; // Table doesn't exist, skip logging
        }
        
        $user_id = $_SESSION['user_id'];
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        $log_query = "INSERT INTO user_activity_logs (user_id, action, details, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
        $log_stmt = mysqli_prepare($conn, $log_query);
        
        if ($log_stmt) {
            mysqli_stmt_bind_param($log_stmt, 'issss', $user_id, $action, $details, $ip_address, $user_agent);
            mysqli_stmt_execute($log_stmt);
            mysqli_stmt_close($log_stmt);
        }
    }
}

// Function to check if user is logged in
function pmsIsLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Function to get current user ID
function pmsGetCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

// Function to get current user role
function pmsGetCurrentUserRole() {
    return $_SESSION['role'] ?? null;
}

// Function to check if user has specific permission
function pmsHasPermission($permission) {
    if (!isset($_SESSION['permissions'])) {
        return false;
    }
    
    return in_array($permission, $_SESSION['permissions']);
}

// Function to redirect with error
function pmsRedirectWithError($url, $error_message) {
    $_SESSION['error_message'] = $error_message;
    header("Location: $url");
    exit();
}

// Function to redirect with success
function pmsRedirectWithSuccess($url, $success_message) {
    $_SESSION['success_message'] = $success_message;
    header("Location: $url");
    exit();
}

// Function to display flash messages
function pmsDisplayFlashMessages() {
    if (isset($_SESSION['error_message'])) {
        echo '<div class="alert alert-danger">' . htmlspecialchars($_SESSION['error_message']) . '</div>';
        unset($_SESSION['error_message']);
    }
    
    if (isset($_SESSION['success_message'])) {
        echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
        unset($_SESSION['success_message']);
    }
}

// Function to generate CSRF token
function pmsGenerateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Function to validate CSRF token
function pmsValidateCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        if (!headers_sent()) {
            header("Location: /seait/404.php");
            exit();
        } else {
            error_log("Cannot redirect - headers already sent. CSRF token validation failed.");
            return false;
        }
    }
    return true;
}

// Function to check if request is AJAX
function pmsIsAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

// Function to handle rate limiting
function pmsCheckRateLimit($action, $max_attempts = 5, $time_window = 300) {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $current_time = time();
    
    global $conn;
    
    if (!$conn) {
        return true; // If no database connection, allow the request
    }
    
    // Check if rate_limiting table exists first
    $table_check = "SHOW TABLES LIKE 'rate_limiting'";
    $table_result = mysqli_query($conn, $table_check);
    
    if (!$table_result || mysqli_num_rows($table_result) === 0) {
        // Table doesn't exist, return true (no rate limiting)
        return true;
    }
    
    // Clean old attempts
    $clean_query = "DELETE FROM rate_limiting WHERE action = ? AND ip_address = ? AND attempt_time < ?";
    $clean_stmt = mysqli_prepare($conn, $clean_query);
    if ($clean_stmt) {
        $cutoff_time = $current_time - $time_window;
        mysqli_stmt_bind_param($clean_stmt, 'ssi', $action, $ip_address, $cutoff_time);
        mysqli_stmt_execute($clean_stmt);
        mysqli_stmt_close($clean_stmt);
    }
    
    // Count recent attempts
    $count_query = "SELECT COUNT(*) as attempts FROM rate_limiting WHERE action = ? AND ip_address = ?";
    $count_stmt = mysqli_prepare($conn, $count_query);
    if ($count_stmt) {
        mysqli_stmt_bind_param($count_stmt, 'ss', $action, $ip_address);
        mysqli_stmt_execute($count_stmt);
        $result = mysqli_stmt_get_result($count_stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($count_stmt);
        
        if ($row['attempts'] >= $max_attempts) {
            return false; // Rate limit exceeded
        }
    }
    
    // Log this attempt
    $log_query = "INSERT INTO rate_limiting (action, ip_address, attempt_time) VALUES (?, ?, ?)";
    $log_stmt = mysqli_prepare($conn, $log_query);
    if ($log_stmt) {
        mysqli_stmt_bind_param($log_stmt, 'ssi', $action, $ip_address, $current_time);
        mysqli_stmt_execute($log_stmt);
        mysqli_stmt_close($log_stmt);
    }
    
    return true; // Rate limit not exceeded
}

// Function to get user-friendly error message
function pmsGetUserFriendlyErrorMessage($error_code) {
    $messages = [
        400 => 'Bad Request - Please check your input and try again.',
        401 => 'Unauthorized - Please log in to access this page.',
        403 => 'Forbidden - You don\'t have permission to access this page.',
        404 => 'Page Not Found - The page you\'re looking for doesn\'t exist.',
        408 => 'Request Timeout - The request took too long to complete.',
        429 => 'Too Many Requests - Please wait a moment and try again.',
        500 => 'Internal Server Error - Something went wrong on our end.',
        502 => 'Bad Gateway - The server received an invalid response.',
        503 => 'Service Unavailable - The service is temporarily unavailable.',
        504 => 'Gateway Timeout - The request took too long to process.',
        505 => 'HTTP Version Not Supported - Please update your browser.'
    ];
    
    return $messages[$error_code] ?? 'An unexpected error occurred.';
}

// Function to log error with context
function pmsLogErrorWithContext($error_message, $context = []) {
    $log_data = [
        'error_message' => $error_message,
        'timestamp' => date('Y-m-d H:i:s'),
        'user_id' => $_SESSION['user_id'] ?? 'Not logged in',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'Unknown',
        'context' => json_encode($context)
    ];
    
    error_log("PMS Error with context: " . json_encode($log_data));
}

// Function to check if maintenance mode is enabled
function pmsIsMaintenanceMode() {
    global $conn;
    
    if (!$conn) {
        return false;
    }
    
    // Check if system_settings table exists first
    $table_check = "SHOW TABLES LIKE 'system_settings'";
    $table_result = mysqli_query($conn, $table_check);
    
    if (!$table_result || mysqli_num_rows($table_result) === 0) {
        // Table doesn't exist, return false (no maintenance mode)
        return false;
    }
    
    $query = "SELECT value FROM system_settings WHERE setting_key = 'maintenance_mode'";
    $result = mysqli_query($conn, $query);
    
    if ($result && $row = mysqli_fetch_assoc($result)) {
        return $row['value'] === '1';
    }
    
    return false;
}

// Function to check if user is admin (bypass maintenance mode)
function pmsIsAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Function to handle maintenance mode
function pmsHandleMaintenanceMode() {
    if (pmsIsMaintenanceMode() && !pmsIsAdmin()) {
        if (!headers_sent()) {
            header("Location: /seait/505.php");
            exit();
        } else {
            error_log("Cannot redirect - headers already sent. Maintenance mode check failed.");
            return false;
        }
    }
    return true;
}

// Function to handle 404 errors
function pmsHandle404Error() {
    if (!headers_sent()) {
        header("Location: /seait/404.php");
        exit();
    } else {
        error_log("Cannot redirect - headers already sent. 404 error occurred.");
        return false;
    }
}

// Function to handle 505 errors
function pmsHandle505Error() {
    if (!headers_sent()) {
        header("Location: /seait/505.php");
        exit();
    } else {
        error_log("Cannot redirect - headers already sent. 505 error occurred.");
        return false;
    }
}

// Don't automatically call maintenance mode check - let developers call it when needed
// pmsHandleMaintenanceMode();
?>
