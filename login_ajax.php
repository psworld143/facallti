<?php
// AJAX login handler
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
$user_role = null;

// Step 1: Check users table first
$query = "SELECT * FROM users WHERE username = ? OR email = ?";
$stmt = mysqli_prepare($conn, $query);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "ss", $username, $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result && $user = mysqli_fetch_assoc($result)) {
        if (password_verify($password, $user['password'])) {
            $user_found = true;
            $user_data = $user;
            $user_role = $user['role'];
        }
    }
    mysqli_stmt_close($stmt);
}

// Step 2: If not found in users table, check students table
if (!$user_found) {
    $query = "SELECT * FROM students WHERE email = ? OR student_id = ?";
    $stmt = mysqli_prepare($conn, $query);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ss", $username, $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($result && $student = mysqli_fetch_assoc($result)) {
            if (password_verify($password, $student['password_hash']) && $student['status'] == 'active') {
                $user_found = true;
                $user_data = $student;
                $user_role = 'student';
            }
        }
        mysqli_stmt_close($stmt);
    }
}

// Step 3: If not found in students table, check faculty table
if (!$user_found) {
    $query = "SELECT * FROM faculty WHERE email = ? AND is_active = 1";
    $stmt = mysqli_prepare($conn, $query);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($result && $faculty = mysqli_fetch_assoc($result)) {
            if (password_verify($password, $faculty['password'])) {
                $user_found = true;
                $user_data = $faculty;
                $user_role = 'teacher';
            }
        }
        mysqli_stmt_close($stmt);
    }
}

// Process login if user found
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
            // Script is at root level (e.g., http://home.seait-edu.ph/login_ajax.php)
            $base_url = $protocol . '://' . $host;
        } else {
            // Script is in a subdirectory (e.g., http://localhost/seait/login_ajax.php)
            $base_path = dirname($script_name);
            $base_url = $protocol . '://' . $host . $base_path;
        }
    } else {
        // Fallback: use the current directory
        $base_url = $protocol . '://' . $host;
    }

    // Debug logging
    error_log("Login AJAX - Protocol: $protocol, Host: $host, Script: $script_name, Base URL: $base_url");

    // Set session data based on the table the user was found in
    if ($user_role == 'student') {
        // Student from students table
        $_SESSION['user_id'] = (int)$user_data['id'];
        $_SESSION['username'] = (string)$user_data['student_id'];
        $_SESSION['email'] = (string)$user_data['email'];
        $_SESSION['role'] = 'student';
        $_SESSION['first_name'] = (string)$user_data['first_name'];
        $_SESSION['last_name'] = (string)$user_data['last_name'];
        $_SESSION['student_id'] = (string)$user_data['student_id'];

        $redirect_url = $base_url . '/students/dashboard.php';
        echo json_encode([
            'success' => true,
            'message' => 'Login successful! Redirecting to student dashboard...',
            'redirect_url' => $redirect_url
        ]);
        exit;
    } elseif ($user_role == 'teacher') {
        // Faculty from faculty table
        $_SESSION['user_id'] = (int)$user_data['id'];
        $_SESSION['username'] = (string)$user_data['email'];
        $_SESSION['email'] = (string)$user_data['email'];
        $_SESSION['role'] = 'teacher';
        $_SESSION['first_name'] = (string)$user_data['first_name'];
        $_SESSION['last_name'] = (string)$user_data['last_name'];
        $_SESSION['faculty_id'] = (int)$user_data['id'];

        $redirect_url = $base_url . '/faculty/dashboard.php';
        echo json_encode([
            'success' => true,
            'message' => 'Login successful! Redirecting to faculty dashboard...',
            'redirect_url' => $redirect_url
        ]);
        exit;
    } else {
        // User from users table
        $_SESSION['user_id'] = (int)$user_data['id'];
        $_SESSION['username'] = (string)$user_data['username'];
        $_SESSION['email'] = (string)$user_data['email'];
        $_SESSION['role'] = (string)$user_data['role'];
        $_SESSION['first_name'] = (string)$user_data['first_name'];
        $_SESSION['last_name'] = (string)$user_data['last_name'];

        // Redirect based on role using absolute URLs
        switch($user_data['role']) {
            case 'admin':
                $redirect_url = $base_url . '/admin/dashboard.php';
                echo json_encode([
                    'success' => true,
                    'message' => 'Login successful! Redirecting to admin dashboard...',
                    'redirect_url' => $redirect_url
                ]);
                exit;
                break;
            case 'social_media_manager':
                $redirect_url = $base_url . '/social-media/dashboard.php';
                echo json_encode([
                    'success' => true,
                    'message' => 'Login successful! Redirecting to social media dashboard...',
                    'redirect_url' => $redirect_url
                ]);
                exit;
                break;
            case 'content_creator':
                $redirect_url = $base_url . '/content-creator/dashboard.php';
                echo json_encode([
                    'success' => true,
                    'message' => 'Login successful! Redirecting to content creator dashboard...',
                    'redirect_url' => $redirect_url
                ]);
                exit;
                break;
            case 'guidance_officer':
                $redirect_url = $base_url . '/IntelliEVal/dashboard.php';
                echo json_encode([
                    'success' => true,
                    'message' => 'Login successful! Redirecting to guidance dashboard...',
                    'redirect_url' => $redirect_url
                ]);
                exit;
                break;
            case 'head':
                $redirect_url = $base_url . '/IntelliEVal/dashboard.php';
                echo json_encode([
                    'success' => true,
                    'message' => 'Login successful! Redirecting to head dashboard...',
                    'redirect_url' => $redirect_url
                ]);
                exit;
                break;
            case 'student':
                $redirect_url = $base_url . '/students/dashboard.php';
                echo json_encode([
                    'success' => true,
                    'message' => 'Login successful! Redirecting to student dashboard...',
                    'redirect_url' => $redirect_url
                ]);
                exit;
                break;
            default:
                $redirect_url = $base_url . '/index.php';
                echo json_encode([
                    'success' => true,
                    'message' => 'Login successful! Redirecting...',
                    'redirect_url' => $redirect_url
                ]);
                exit;
                break;
        }
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
    exit;
}

mysqli_close($conn);
?>
