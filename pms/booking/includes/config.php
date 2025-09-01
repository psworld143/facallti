<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'hotel_pms_clean');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application configuration
define('SITE_NAME', 'Hotel PMS Training System');
define('SITE_URL', 'http://localhost/seait/pms/booking/');
define('TIMEZONE', 'Asia/Manila');

// Set timezone
date_default_timezone_set(TIMEZONE);

// Database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";port=3306;dbname=" . DB_NAME . ";unix_socket=/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed. Please check your configuration.");
}

// Session configuration moved to session-config.php

// Error reporting
error_reporting(E_ALL);
// Note: ini_set calls moved to session-config.php to avoid session warnings

// Logs directory creation removed to avoid permission issues
?>
