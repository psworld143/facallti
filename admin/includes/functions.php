<?php
/**
 * Admin Functions
 * Contains utility functions for the admin panel
 */

/**
 * Check if user is logged in and has admin role
 */
function checkAdminAuth() {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        header('Location: ../login.php');
        exit();
    }
}

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    global $conn;
    return mysqli_real_escape_string($conn, trim($data));
}

/**
 * Validate email format
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Generate random string
 */
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

/**
 * Format date for display
 */
function formatDate($date, $format = 'M d, Y') {
    return date($format, strtotime($date));
}

/**
 * Get user by ID
 */
function getUserById($conn, $id) {
    $query = "SELECT * FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    return mysqli_fetch_assoc($result);
}

/**
 * Get user by email
 */
function getUserByEmail($conn, $email) {
    $query = "SELECT * FROM users WHERE email = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    return mysqli_fetch_assoc($result);
}

/**
 * Update user last login
 */
function updateUserLastLogin($conn, $user_id) {
    $query = "UPDATE users SET last_login = NOW() WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    return mysqli_stmt_execute($stmt);
}

/**
 * Log admin activity
 */
function logAdminActivity($conn, $admin_id, $action, $details = '') {
    $query = "INSERT INTO admin_activity_logs (admin_id, action, details, created_at) VALUES (?, ?, ?, NOW())";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "iss", $admin_id, $action, $details);
    return mysqli_stmt_execute($stmt);
}

/**
 * Get admin statistics
 */
function getAdminStatistics($conn) {
    $stats = [];

    // Count users
    $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM users");
    $stats['users'] = mysqli_fetch_assoc($result)['total'];

    // Count posts
    $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM posts");
    $stats['posts'] = mysqli_fetch_assoc($result)['total'];

    // Count inquiries
    $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM user_inquiries WHERE is_resolved = 0");
    $stats['pending_inquiries'] = mysqli_fetch_assoc($result)['total'];

    // Count students
    $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM students WHERE status = 'active'");
    $stats['active_students'] = mysqli_fetch_assoc($result)['total'];

    return $stats;
}

/**
 * Check if table exists
 */
function tableExists($conn, $table_name) {
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$table_name'");
    return mysqli_num_rows($result) > 0;
}

/**
 * Create admin activity logs table if it doesn't exist
 */
function createAdminActivityLogsTable($conn) {
    if (!tableExists($conn, 'admin_activity_logs')) {
        $sql = "CREATE TABLE `admin_activity_logs` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `admin_id` int(11) NOT NULL,
            `action` varchar(255) NOT NULL,
            `details` text DEFAULT NULL,
            `ip_address` varchar(45) DEFAULT NULL,
            `user_agent` text DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `admin_id` (`admin_id`),
            KEY `action` (`action`),
            KEY `created_at` (`created_at`),
            CONSTRAINT `fk_activity_logs_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        return mysqli_query($conn, $sql);
    }
    return true;
}

/**
 * Initialize admin system
 */
function initializeAdminSystem($conn) {
    // Create admin activity logs table
    createAdminActivityLogsTable($conn);

    // Log admin login
    if (isset($_SESSION['user_id'])) {
        logAdminActivity($conn, $_SESSION['user_id'], 'login', 'Admin logged in');
    }
}

/**
 * Display success message
 */
function showSuccessMessage($message) {
    return '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">' . htmlspecialchars($message) . '</div>';
}

/**
 * Display error message
 */
function showErrorMessage($message) {
    return '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">' . htmlspecialchars($message) . '</div>';
}

/**
 * Display warning message
 */
function showWarningMessage($message) {
    return '<div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4">' . htmlspecialchars($message) . '</div>';
}

/**
 * Display info message
 */
function showInfoMessage($message) {
    return '<div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded mb-4">' . htmlspecialchars($message) . '</div>';
}

/**
 * Pagination helper
 */
function getPagination($total_records, $records_per_page, $current_page, $base_url) {
    $total_pages = ceil($total_records / $records_per_page);

    if ($total_pages <= 1) {
        return '';
    }

    $pagination = '<nav class="flex items-center justify-between border-t border-gray-200 px-4 py-3 sm:px-6">';
    $pagination .= '<div class="flex flex-1 justify-between sm:hidden">';

    // Previous button
    if ($current_page > 1) {
        $pagination .= '<a href="' . $base_url . '?page=' . ($current_page - 1) . '" class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Previous</a>';
    } else {
        $pagination .= '<span class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-300 cursor-not-allowed">Previous</span>';
    }

    // Next button
    if ($current_page < $total_pages) {
        $pagination .= '<a href="' . $base_url . '?page=' . ($current_page + 1) . '" class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Next</a>';
    } else {
        $pagination .= '<span class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-300 cursor-not-allowed">Next</span>';
    }

    $pagination .= '</div>';
    $pagination .= '</nav>';

    return $pagination;
}

/**
 * Format file size
 */
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

/**
 * Get file extension
 */
function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Validate file upload
 */
function validateFileUpload($file, $allowed_extensions = [], $max_size = 5242880) {
    $errors = [];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'File upload failed';
        return $errors;
    }

    if ($file['size'] > $max_size) {
        $errors[] = 'File size exceeds maximum allowed size';
    }

    if (!empty($allowed_extensions)) {
        $extension = getFileExtension($file['name']);
        if (!in_array($extension, $allowed_extensions)) {
            $errors[] = 'File type not allowed';
        }
    }

    return $errors;
}

/**
 * Initialize the admin system when this file is included
 */
if (isset($conn)) {
    initializeAdminSystem($conn);
}
?>