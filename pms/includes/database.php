<?php
/**
 * Unified Database Configuration for PMS System
 * This file provides database connection for all PMS modules:
 * - Booking System
 * - Inventory Management
 * - POS System
 * - Any future modules
 */

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'hotel_pms_clean');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application configuration
define('SITE_NAME', 'Hotel PMS Training System');
define('SITE_URL', 'http://localhost/seait/pms/');
define('TIMEZONE', 'Asia/Manila');

// Set timezone
date_default_timezone_set(TIMEZONE);

// Database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";port=3306;dbname=" . DB_NAME . ";unix_socket=/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Set charset to ensure proper encoding
    $pdo->exec("SET NAMES utf8mb4");
    
} catch(PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed. Please check your configuration.");
}

// Error reporting
error_reporting(E_ALL);

// Logs directory creation removed to avoid permission issues

// Function to get database connection (useful for modules that need to ensure connection)
function getDatabaseConnection() {
    global $pdo;
    return $pdo;
}

// Function to check if database is connected
function isDatabaseConnected() {
    global $pdo;
    try {
        $pdo->query('SELECT 1');
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// Function to close database connection
function closeDatabaseConnection() {
    global $pdo;
    $pdo = null;
}
?>
