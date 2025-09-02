<?php
/**
 * Unified Error Handler for SEAIT System
 * Works for both admin and regular users
 * Include this file at the top of all pages after session_start()
 */

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors on screen, only log them

// Determine if current user is admin
function isCurrentUserAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Determine if we're in admin area
function isAdminArea() {
    $current_path = $_SERVER['REQUEST_URI'] ?? '';
    return strpos($current_path, '/admin/') !== false || 
           strpos($_SERVER['PHP_SELF'] ?? '', '/admin/') !== false;
}

// Get appropriate redirect path based on context
function getErrorRedirectPath($error_code) {
    $base_path = '/seait/';
    
    // Always redirect to root error pages for consistency
    switch ($error_code) {
        case 404:
            return $base_path . '404.php';
        case 505:
        default:
            return $base_path . '505.php';
    }
}

// Unified error handler
function unifiedErrorHandler($errno, $errstr, $errfile, $errline) {
    $context = isAdminArea() ? 'Admin' : 'User';
    
    // Log the error with context
    error_log("$context PHP Error [$errno]: $errstr in $errfile on line $errline");
    
    // Only redirect for fatal errors, let non-fatal errors be handled normally
    if (in_array($errno, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        // Check if headers have already been sent
        if (headers_sent()) {
            error_log("Cannot redirect - headers already sent. $context Fatal Error: [$errno] $errstr in $errfile on line $errline");
            return false; // Let PHP handle the error normally
        }
        
        header("Location: " . getErrorRedirectPath(505));
        exit();
    }
    
    // For non-fatal errors, just log them and continue
    return false; // Let PHP handle the error normally
}

// Set the unified error handler
set_error_handler("unifiedErrorHandler");

// Unified exception handler
function unifiedExceptionHandler($exception) {
    $context = isAdminArea() ? 'Admin' : 'User';
    
    // Log the exception with context
    error_log("$context Uncaught Exception: " . $exception->getMessage());
    
    // Check if headers have already been sent
    if (headers_sent()) {
        error_log("Cannot redirect - headers already sent. $context Exception: " . $exception->getMessage());
        return;
    }
    
    // Redirect to 505 error page for exceptions
    header("Location: " . getErrorRedirectPath(505));
    exit();
}

set_exception_handler("unifiedExceptionHandler");

// Register shutdown function to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        $context = isAdminArea() ? 'Admin' : 'User';
        
        // Log the fatal error with context
        error_log("$context Fatal Error: " . $error['message'] . " in " . $error['file'] . " on line " . $error['line']);
        
        // Only redirect if headers haven't been sent and it's not an activity logging error
        if (!headers_sent() && strpos($error['message'], 'activity_logs') === false) {
            header("Location: " . getErrorRedirectPath(505));
            exit();
        } else {
            error_log("Cannot redirect - headers already sent or activity logging error. $context Fatal Error: " . $error['message']);
        }
    }
});

// Unified database connection check function
function checkDatabaseConnection($conn) {
    if (!$conn) {
        $context = isAdminArea() ? 'Admin' : 'User';
        error_log("$context Database connection failed");
        
        if (!headers_sent()) {
            header("Location: " . getErrorRedirectPath(505));
            exit();
        } else {
            error_log("Cannot redirect - headers already sent. $context Database connection failed.");
            return false;
        }
    }
    return true;
}

// Unified database query error check function
function checkDatabaseQuery($result, $query_name = "Unknown") {
    if (!$result) {
        global $conn;
        $context = isAdminArea() ? 'Admin' : 'User';
        $error_message = mysqli_error($conn);
        
        error_log("$context Database error in $query_name query: $error_message");
        
        if (!headers_sent()) {
            header("Location: " . getErrorRedirectPath(505));
            exit();
        } else {
            error_log("Cannot redirect - headers already sent. $context Database error in $query_name query: $error_message");
            return false;
        }
    }
    return true;
}

// Unified database statement execution check function
function checkDatabaseStatement($stmt, $query_name = "Unknown") {
    if (!mysqli_stmt_execute($stmt)) {
        global $conn;
        $context = isAdminArea() ? 'Admin' : 'User';
        $error_message = mysqli_error($conn);
        
        error_log("$context Database error in $query_name statement: $error_message");
        
        if (!headers_sent()) {
            header("Location: " . getErrorRedirectPath(505));
            exit();
        } else {
            error_log("Cannot redirect - headers already sent. $context Database error in $query_name statement: $error_message");
            return false;
        }
    }
    return true;
}

// Function to safely include files with error handling
function safeInclude($file_path) {
    if (!file_exists($file_path)) {
        $context = isAdminArea() ? 'Admin' : 'User';
        error_log("$context Required file not found: $file_path");
        
        if (!headers_sent()) {
            header("Location: " . getErrorRedirectPath(505));
            exit();
        } else {
            error_log("Cannot redirect - headers already sent. $context Required file not found: $file_path");
            return false;
        }
    }
    
    try {
        require_once $file_path;
    } catch (Exception $e) {
        $context = isAdminArea() ? 'Admin' : 'User';
        error_log("$context Error including file $file_path: " . $e->getMessage());
        
        if (!headers_sent()) {
            header("Location: " . getErrorRedirectPath(505));
            exit();
        } else {
            error_log("Cannot redirect - headers already sent. $context Error including file $file_path: " . $e->getMessage());
            return false;
        }
    }
    return true;
}

// Function to validate session
function validateSession() {
    if (!isset($_SESSION['user_id'])) {
        if (!headers_sent()) {
            header("Location: " . getErrorRedirectPath(404));
            exit();
        } else {
            error_log("Cannot redirect - headers already sent. Session validation failed.");
            return false;
        }
    }
    return true;
}

// Function to validate user role
function validateRole($required_role) {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $required_role) {
        if (!headers_sent()) {
            header("Location: " . getErrorRedirectPath(404));
            exit();
        } else {
            error_log("Cannot redirect - headers already sent. Role validation failed for role: $required_role");
            return false;
        }
    }
    return true;
}

// Function to validate admin access
function validateAdminAccess() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        if (!headers_sent()) {
            header("Location: " . getErrorRedirectPath(404));
            exit();
        } else {
            error_log("Cannot redirect - headers already sent. Admin access validation failed.");
            return false;
        }
    }
    return true;
}

// Function to sanitize and validate input
function sanitizeAndValidate($input, $type = 'string') {
    if (empty($input)) {
        return null;
    }
    
    switch ($type) {
        case 'email':
            if (!filter_var($input, FILTER_VALIDATE_EMAIL)) {
                if (!headers_sent()) {
                    header("Location: " . getErrorRedirectPath(404));
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
                    header("Location: " . getErrorRedirectPath(404));
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
                    header("Location: " . getErrorRedirectPath(404));
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
                    header("Location: " . getErrorRedirectPath(404));
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
function handleAjaxError($message, $error_code = 500) {
    $context = isAdminArea() ? 'Admin' : 'User';
    
    http_response_code($error_code);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => $message,
        'error_code' => $error_code,
        'context' => $context,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}

// Function to log user activity
function logUserActivity($action, $details = '') {
    if (isset($_SESSION['user_id'])) {
        global $conn;
        
        if (!$conn) {
            return; // If no database connection, skip logging
        }
        
        $context = isAdminArea() ? 'admin' : 'user';
        $table_name = $context . '_activity_logs';
        
        // Check if activity logs table exists first
        $table_check = "SHOW TABLES LIKE '$table_name'";
        $table_result = mysqli_query($conn, $table_check);
        
        if (!$table_result || mysqli_num_rows($table_result) === 0) {
            return; // Table doesn't exist, skip logging
        }
        
        // Check table structure to see what columns exist
        $structure_check = "DESCRIBE $table_name";
        $structure_result = mysqli_query($conn, $structure_check);
        
        if (!$structure_result) {
            return; // Can't check structure, skip logging
        }
        
        $columns = [];
        while ($row = mysqli_fetch_assoc($structure_result)) {
            $columns[] = $row['Field'];
        }
        
        // Only proceed if required columns exist
        if (!in_array('user_id', $columns) || !in_array('action', $columns)) {
            return; // Required columns don't exist, skip logging
        }
        
        try {
            $user_id = $_SESSION['user_id'];
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            $page = $_SERVER['REQUEST_URI'] ?? 'Unknown';
            
            // Build query based on available columns
            $fields = ['user_id', 'action'];
            $values = [$user_id, $action];
            $types = 'is';
            
            if (in_array('details', $columns)) {
                $fields[] = 'details';
                $values[] = $details;
                $types .= 's';
            }
            
            if (in_array('page', $columns)) {
                $fields[] = 'page';
                $values[] = $page;
                $types .= 's';
            }
            
            if (in_array('ip_address', $columns)) {
                $fields[] = 'ip_address';
                $values[] = $ip_address;
                $types .= 's';
            }
            
            if (in_array('user_agent', $columns)) {
                $fields[] = 'user_agent';
                $values[] = $user_agent;
                $types .= 's';
            }
            
            if (in_array('created_at', $columns)) {
                $fields[] = 'created_at';
                $values[] = date('Y-m-d H:i:s');
                $types .= 's';
            }
            
            $placeholders = str_repeat('?,', count($fields));
            $placeholders = rtrim($placeholders, ',');
            
            $log_query = "INSERT INTO $table_name (" . implode(', ', $fields) . ") VALUES ($placeholders)";
            $log_stmt = mysqli_prepare($conn, $log_query);
            
            if ($log_stmt) {
                mysqli_stmt_bind_param($log_stmt, $types, ...$values);
                mysqli_stmt_execute($log_stmt);
                mysqli_stmt_close($log_stmt);
            }
        } catch (Exception $e) {
            // Silently fail if logging doesn't work
            error_log("Activity logging failed: " . $e->getMessage());
        }
    }
}

// Function to display flash messages (context-aware styling)
function displayFlashMessages() {
    $isAdmin = isAdminArea();
    
    if (isset($_SESSION['error_message'])) {
        if ($isAdmin) {
            echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4" role="alert">
                    <div class="flex">
                        <div class="py-1">
                            <svg class="fill-current h-6 w-6 text-red-500 mr-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                <path d="M2.93 17.07A10 10 0 1 1 17.07 2.93 10 10 0 0 1 2.93 17.07zm12.73-1.41A8 8 0 1 0 4.34 4.34a8 8 0 0 0 11.32 11.32zM9 11V9h2v6H9v-4zm0-6h2v2H9V5z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="font-bold">Error!</p>
                            <p class="text-sm">' . htmlspecialchars($_SESSION['error_message']) . '</p>
                        </div>
                    </div>
                  </div>';
        } else {
            echo '<div class="alert alert-danger">' . htmlspecialchars($_SESSION['error_message']) . '</div>';
        }
        unset($_SESSION['error_message']);
    }
    
    if (isset($_SESSION['success_message'])) {
        if ($isAdmin) {
            echo '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4" role="alert">
                    <div class="flex">
                        <div class="py-1">
                            <svg class="fill-current h-6 w-6 text-green-500 mr-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                <path d="M2.93 17.07A10 10 0 1 1 17.07 2.93 10 10 0 0 1 2.93 17.07zm12.73-1.41A8 8 0 1 0 4.34 4.34a8 8 0 0 0 11.32 11.32zM6.7 9.29L9 11.6l4.3-4.3 1.4 1.42L9 14.4l-3.7-3.7 1.4-1.41z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="font-bold">Success!</p>
                            <p class="text-sm">' . htmlspecialchars($_SESSION['success_message']) . '</p>
                        </div>
                    </div>
                  </div>';
        } else {
            echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
        }
        unset($_SESSION['success_message']);
    }
    
    if (isset($_SESSION['warning_message'])) {
        if ($isAdmin) {
            echo '<div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4" role="alert">
                    <div class="flex">
                        <div class="py-1">
                            <svg class="fill-current h-6 w-6 text-yellow-500 mr-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                <path d="M2.93 17.07A10 10 0 1 1 17.07 2.93 10 10 0 0 1 2.93 17.07zm12.73-1.41A8 8 0 1 0 4.34 4.34a8 8 0 0 0 11.32 11.32zM9 5h2v6H9V5zm0 8h2v2H9v-2z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="font-bold">Warning!</p>
                            <p class="text-sm">' . htmlspecialchars($_SESSION['warning_message']) . '</p>
                        </div>
                    </div>
                  </div>';
        } else {
            echo '<div class="alert alert-warning">' . htmlspecialchars($_SESSION['warning_message']) . '</div>';
        }
        unset($_SESSION['warning_message']);
    }
}

// Function to redirect with error
function redirectWithError($url, $error_message) {
    $_SESSION['error_message'] = $error_message;
    header("Location: $url");
    exit();
}

// Function to redirect with success
function redirectWithSuccess($url, $success_message) {
    $_SESSION['success_message'] = $success_message;
    header("Location: $url");
    exit();
}

// Function to redirect with warning
function redirectWithWarning($url, $warning_message) {
    $_SESSION['warning_message'] = $warning_message;
    header("Location: $url");
    exit();
}

// Function to generate CSRF token
function generateCSRFToken() {
    $token_key = isAdminArea() ? 'admin_csrf_token' : 'csrf_token';
    
    if (!isset($_SESSION[$token_key])) {
        $_SESSION[$token_key] = bin2hex(random_bytes(32));
    }
    return $_SESSION[$token_key];
}

// Function to validate CSRF token
function validateCSRFToken($token) {
    $token_key = isAdminArea() ? 'admin_csrf_token' : 'csrf_token';
    
    if (!isset($_SESSION[$token_key]) || $token !== $_SESSION[$token_key]) {
        if (!headers_sent()) {
            header("Location: " . getErrorRedirectPath(404));
            exit();
        } else {
            error_log("Cannot redirect - headers already sent. CSRF token validation failed.");
            return false;
        }
    }
    return true;
}

// Function to check if request is AJAX
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

// Function to handle rate limiting
function checkRateLimit($action, $max_attempts = 5, $time_window = 300) {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $current_time = time();
    $context = isAdminArea() ? 'admin' : 'user';
    
    global $conn;
    
    if (!$conn) {
        return true; // If no database connection, allow the request
    }
    
    $table_name = $context . '_rate_limiting';
    
    // Check if rate limiting table exists first
    $table_check = "SHOW TABLES LIKE '$table_name'";
    $table_result = mysqli_query($conn, $table_check);
    
    if (!$table_result || mysqli_num_rows($table_result) === 0) {
        return true; // Table doesn't exist, return true (no rate limiting)
    }
    
    // Clean old attempts
    $clean_query = "DELETE FROM $table_name WHERE action = ? AND ip_address = ? AND attempt_time < ?";
    $clean_stmt = mysqli_prepare($conn, $clean_query);
    if ($clean_stmt) {
        $cutoff_time = $current_time - $time_window;
        mysqli_stmt_bind_param($clean_stmt, 'ssi', $action, $ip_address, $cutoff_time);
        mysqli_stmt_execute($clean_stmt);
        mysqli_stmt_close($clean_stmt);
    }
    
    // Count recent attempts
    $count_query = "SELECT COUNT(*) as attempts FROM $table_name WHERE action = ? AND ip_address = ?";
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
    $log_query = "INSERT INTO $table_name (action, ip_address, attempt_time) VALUES (?, ?, ?)";
    $log_stmt = mysqli_prepare($conn, $log_query);
    if ($log_stmt) {
        mysqli_stmt_bind_param($log_stmt, 'ssi', $action, $ip_address, $current_time);
        mysqli_stmt_execute($log_stmt);
        mysqli_stmt_close($log_stmt);
    }
    
    return true; // Rate limit not exceeded
}

// Function to get user-friendly error message
function getUserFriendlyErrorMessage($error_code) {
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
function logErrorWithContext($error_message, $context = []) {
    $area_context = isAdminArea() ? 'Admin' : 'User';
    
    $log_data = [
        'error_message' => $error_message,
        'timestamp' => date('Y-m-d H:i:s'),
        'area' => $area_context,
        'user_id' => $_SESSION['user_id'] ?? 'Not logged in',
        'user_role' => $_SESSION['role'] ?? 'Unknown',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'Unknown',
        'context' => json_encode($context)
    ];
    
    error_log("Unified Error with context: " . json_encode($log_data));
}

// Function to check if maintenance mode is enabled
function isMaintenanceMode() {
    global $conn;
    
    if (!$conn) {
        return false;
    }
    
    // Check if system_settings table exists first
    $table_check = "SHOW TABLES LIKE 'system_settings'";
    $table_result = mysqli_query($conn, $table_check);
    
    if (!$table_result || mysqli_num_rows($table_result) === 0) {
        return false; // Table doesn't exist, return false (no maintenance mode)
    }
    
    $query = "SELECT value FROM system_settings WHERE setting_key = 'maintenance_mode'";
    $result = mysqli_query($conn, $query);
    
    if ($result && $row = mysqli_fetch_assoc($result)) {
        return $row['value'] === '1';
    }
    
    return false;
}

// Function to handle maintenance mode
function handleMaintenanceMode() {
    if (isMaintenanceMode() && !isCurrentUserAdmin()) {
        if (!headers_sent()) {
            header("Location: /seait/maintenance.php");
            exit();
        } else {
            error_log("Cannot redirect - headers already sent. Maintenance mode check failed.");
            return false;
        }
    }
    return true;
}

// Helper functions for common checks
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getCurrentUserRole() {
    return $_SESSION['role'] ?? null;
}

function hasPermission($permission) {
    if (!isset($_SESSION['permissions'])) {
        return false;
    }
    return in_array($permission, $_SESSION['permissions']);
}

// Log admin page access if in admin area and user is logged in (temporarily disabled)
// if (isAdminArea() && isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
//     $page_name = basename($_SERVER['PHP_SELF']);
//     logUserActivity("Admin Page Access", "Accessed admin page: $page_name");
// }

// Initialize maintenance mode check
handleMaintenanceMode();
?>
