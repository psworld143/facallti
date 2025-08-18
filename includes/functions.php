<?php
// Utility functions for SEAIT website

function sanitize_input($data) {
    if (!is_string($data)) {
        return '';
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function generate_unique_join_code($conn) {
    do {
        // Generate a random 8-character alphanumeric code
        $code = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8));

        // Check if this code already exists in the database
        $check_query = "SELECT COUNT(*) as count FROM teacher_classes WHERE join_code = ?";
        $check_stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($check_stmt, "s", $code);
        mysqli_stmt_execute($check_stmt);
        $result = mysqli_stmt_get_result($check_stmt);
        $row = mysqli_fetch_assoc($result);

    } while ($row['count'] > 0); // Keep generating until we get a unique code

    return $code;
}

function get_login_path() {
    // Determine the correct path to index.php (main page with login modal) based on current directory
    $current_dir = dirname($_SERVER['SCRIPT_NAME']);
    $depth = substr_count($current_dir, '/') - 1; // -1 because we start from root

    if ($depth > 0) {
        return str_repeat('../', $depth) . 'index.php';
    } else {
        return 'index.php';
    }
}

function check_login() {
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        // Use output buffering to prevent header errors
        if (!headers_sent()) {
            header("Location: " . get_login_path());
            exit();
        } else {
            // If headers already sent, use JavaScript redirect
            echo '<script>window.location.href = "' . get_login_path() . '";</script>';
            exit();
        }
    }
}

function check_admin() {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        // Use output buffering to prevent header errors
        if (!headers_sent()) {
            header("Location: " . get_login_path());
            exit();
        } else {
            // If headers already sent, use JavaScript redirect
            echo '<script>window.location.href = "' . get_login_path() . '";</script>';
            exit();
        }
    }
}

function check_social_media_manager() {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'social_media_manager') {
        // Use output buffering to prevent header errors
        if (!headers_sent()) {
            header("Location: " . get_login_path());
            exit();
        } else {
            // If headers already sent, use JavaScript redirect
            echo '<script>window.location.href = "' . get_login_path() . '";</script>';
            exit();
        }
    }
}

function check_content_creator() {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'content_creator') {
        // Use output buffering to prevent header errors
        if (!headers_sent()) {
            header("Location: " . get_login_path());
            exit();
        } else {
            // If headers already sent, use JavaScript redirect
            echo '<script>window.location.href = "' . get_login_path() . '";</script>';
            exit();
        }
    }
}

function get_user_role() {
    return isset($_SESSION['role']) && is_string($_SESSION['role']) ? $_SESSION['role'] : '';
}

function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function redirect($url) {
    // Use output buffering to prevent header errors
    if (!headers_sent()) {
        header("Location: $url");
        exit();
    } else {
        // If headers already sent, use JavaScript redirect
        echo '<script>window.location.href = "' . $url . '";</script>';
        exit();
    }
}

function display_message($message, $type = 'info') {
    $alert_class = '';
    switch($type) {
        case 'success':
            $alert_class = 'bg-green-100 border-green-400 text-green-700';
            break;
        case 'error':
            $alert_class = 'bg-red-100 border-red-400 text-red-700';
            break;
        case 'warning':
            $alert_class = 'bg-yellow-100 border-yellow-400 text-yellow-700';
            break;
        default:
            $alert_class = 'bg-blue-100 border-blue-400 text-blue-700';
    }

    return "<div class='$alert_class border px-4 py-3 rounded mb-4'>$message</div>";
}

function get_student_id($conn, $user_email) {
    // Get the student_id from students table based on user email
    $query = "SELECT s.id as student_id FROM students s WHERE s.email = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $user_email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $student_data = mysqli_fetch_assoc($result);

    return $student_data ? $student_data['student_id'] : null;
}

function is_head_evaluation_active() {
    global $conn;
    $query = "SELECT 1 FROM evaluation_schedules WHERE evaluation_type = 'head_to_teacher' AND status = 'active' AND NOW() BETWEEN start_date AND end_date LIMIT 1";
    $result = mysqli_query($conn, $query);
    return $result && mysqli_num_rows($result) > 0;
}