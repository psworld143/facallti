<?php
// Utility functions for SEAIT website

/**
 * Get current academic year and semester
 * @return array Array with 'academic_year' and 'semester' keys
 */
function getCurrentAcademicYearAndSemester() {
    $current_year = date('Y');
    $current_month = date('n');
    
    // Determine academic year based on current month
    if ($current_month >= 6 && $current_month <= 12) {
        $academic_year = $current_year . '-' . ($current_year + 1);
    } else {
        $academic_year = ($current_year - 1) . '-' . $current_year;
    }
    
    // Determine semester based on current month
    if ($current_month >= 6 && $current_month <= 12) {
        $semester = 'First Semester';
    } else {
        $semester = 'Second Semester';
    }
    
    return [
        'academic_year' => $academic_year,
        'semester' => $semester
    ];
}

function sanitize_input($data) {
    // Handle NULL values - preserve them as NULL
    if ($data === null) {
        return null;
    }
    
    // Handle non-string values - convert to string first
    if (!is_string($data)) {
        if (is_array($data)) {
            // Arrays should not be converted to strings, return NULL instead
            return null;
        }
        $data = (string)$data;
    }
    
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    
    // Return NULL if the result is empty (to preserve NULL semantics)
    return empty($data) ? null : $data;
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
    // Since evaluation_schedules table was removed during FaCallTi cleanup,
    // evaluation functionality is no longer available
    return false;
}

/**
 * Get school logo from database settings
 * @param mysqli $conn Database connection
 * @return string School logo URL or empty string if not set
 */
function get_school_logo($conn) {
    $query = "SELECT setting_value FROM settings WHERE setting_key = 'school_logo'";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        return $row['setting_value'] ?? '';
    }
    return '';
}

/**
 * Display school logo HTML
 * @param mysqli $conn Database connection
 * @param array $options Display options (size, class, alt, etc.)
 * @return string HTML for school logo
 */
function display_school_logo($conn, $options = []) {
    $logo_url = get_school_logo($conn);
    $school_abbreviation = get_school_abbreviation($conn);
    
    // Default options
    $defaults = [
        'size' => 'w-16 h-16',
        'class' => 'object-contain',
        'alt' => 'School Logo',
        'fallback_icon' => 'fas fa-university',
        'fallback_text' => $school_abbreviation
    ];
    
    $options = array_merge($defaults, $options);
    
    if (!empty($logo_url)) {
        return '<img src="' . htmlspecialchars($logo_url) . '" 
                     alt="' . htmlspecialchars($options['alt']) . '" 
                     class="' . htmlspecialchars($options['size'] . ' ' . $options['class']) . '">';
    } else {
        // Fallback to icon or text
        if (!empty($options['fallback_icon'])) {
            return '<div class="' . htmlspecialchars($options['size']) . ' bg-seait-orange rounded-full flex items-center justify-center">
                        <i class="' . htmlspecialchars($options['fallback_icon']) . ' text-white text-xl"></i>
                    </div>';
        } else {
            return '<div class="' . htmlspecialchars($options['size']) . ' bg-seait-orange rounded-full flex items-center justify-center">
                        <span class="text-white font-bold text-sm">' . htmlspecialchars($options['fallback_text']) . '</span>
                    </div>';
        }
    }
}

/**
 * Get school setting value
 * @param mysqli $conn Database connection
 * @param string $key Setting key
 * @param string $default Default value if setting not found
 * @return string Setting value
 */
function get_school_setting($conn, $key, $default = '') {
    $query = "SELECT setting_value FROM settings WHERE setting_key = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $key);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($row = mysqli_fetch_assoc($result)) {
        return $row['setting_value'] ?? $default;
    }
    return $default;
}

/**
 * Get school abbreviation from database settings
 * @param mysqli $conn Database connection
 * @return string School abbreviation or 'SEAIT' as fallback
 */
function get_school_abbreviation($conn) {
    $query = "SELECT setting_value FROM settings WHERE setting_key = 'site_description'";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        return $row['setting_value'] ?? 'SEAIT';
    }
    return 'SEAIT';
}

/**
 * Generate favicon HTML tags from school logo
 * @param mysqli $conn Database connection
 * @param string $base_path Base path for relative URLs (e.g., '../' for subdirectories)
 * @return string HTML favicon tagsChange th
 */
function generate_favicon_tags($conn, $base_path = '') {
    $school_logo = get_school_logo($conn);
    
    if (!empty($school_logo)) {
        $logo_path = $base_path . $school_logo;
        return '
    <link rel="icon" type="image/png" href="' . htmlspecialchars($logo_path) . '">
    <link rel="shortcut icon" type="image/png" href="' . htmlspecialchars($logo_path) . '">
    <link rel="apple-touch-icon" type="image/png" href="' . htmlspecialchars($logo_path) . '">
    <link rel="apple-touch-icon-precomposed" type="image/png" href="' . htmlspecialchars($logo_path) . '">
    <meta name="msapplication-TileImage" content="' . htmlspecialchars($logo_path) . '">';
    } else {
        $default_path = $base_path . 'assets/images/';
        return '
    <link rel="icon" type="image/x-icon" href="' . $default_path . 'favicon.ico">
    <link rel="icon" type="image/png" href="' . $default_path . 'seait-logo.png">
    <link rel="shortcut icon" type="image/x-icon" href="' . $default_path . 'favicon.ico">
    <link rel="shortcut icon" type="image/png" href="' . $default_path . 'seait-logo.png">
    <link rel="apple-touch-icon" type="image/png" href="' . $default_path . 'seait-logo.png">
    <link rel="apple-touch-icon-precomposed" type="image/png" href="' . $default_path . 'seait-logo.png">
    <meta name="msapplication-TileImage" content="' . $default_path . 'seait-logo.png">';
    }
}