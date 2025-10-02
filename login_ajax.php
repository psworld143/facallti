<?php
// AJAX login handler - Head/Dean only
header('Content-Type: application/json');

// Start session with error handling
try {
    session_start();
} catch (Exception $e) {
    error_log("Session start error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Session error occurred']);
    exit;
}

require_once 'config/database.php';
require_once 'includes/functions.php';

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$username = isset($_POST['username']) ? sanitize_input($_POST['username']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

// Validate inputs
if (empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Please enter both username and password']);
    exit;
}

$user_found = false;
$user_data = null;

// Check users table for head role only
$query = "SELECT * FROM users WHERE (username = ? OR email = ?) AND role = 'head' AND is_active = 1";
$stmt = mysqli_prepare($conn, $query);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "ss", $username, $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result && $user = mysqli_fetch_assoc($result)) {
        if (password_verify($password, $user['password'])) {
            $user_found = true;
            $user_data = $user;
        }
    }
    mysqli_stmt_close($stmt);
}

// Process login if head user found
if ($user_found && $user_data) {
    // Clear any existing session data
    session_unset();
    session_destroy();
    session_start();

    // Get the base URL for absolute redirects
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $script_name = $_SERVER['SCRIPT_NAME'];

    // Determine the base URL based on the script location
    if (basename($script_name) === 'login_ajax.php') {
        if (dirname($script_name) === '/') {
            // Script is at root level
            $base_url = $protocol . '://' . $host;
        } else {
            // Script is in a subdirectory
            $base_path = dirname($script_name);
            $base_url = $protocol . '://' . $host . $base_path;
        }
    } else {
        // Fallback: use the current directory
        $base_url = $protocol . '://' . $host;
    }

    // Set session data for head user
    $_SESSION['user_id'] = (int)$user_data['id'];
    $_SESSION['username'] = (string)$user_data['username'];
    $_SESSION['email'] = (string)$user_data['email'];
    $_SESSION['role'] = 'head';
    $_SESSION['first_name'] = (string)$user_data['first_name'];
    $_SESSION['last_name'] = (string)$user_data['last_name'];
    $_SESSION['profile_photo'] = isset($user_data['profile_photo']) ? (string)$user_data['profile_photo'] : '';

    // Redirect to head dashboard
    $redirect_url = $base_url . '/heads/dashboard.php';
    echo json_encode([
        'success' => true,
        'message' => 'Login successful! Redirecting to head dashboard...',
        'redirect_url' => $redirect_url
    ]);
    exit;
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid username or password. Only head/dean accounts are allowed.']);
    exit;
}

mysqli_close($conn);
?>