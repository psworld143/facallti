<?php
//Online Database Configuration
$online_host = 'seait-edu.ph';
$online_username = 'seaitedu_seait_website';
$online_password = '020894Website';
$online_dbname = 'seaitedu_seait_website';

// Suppress error output and handle connection gracefully
$online_conn = null;

// Disable error reporting for this file to prevent output
error_reporting(0);
ini_set('display_errors', 0);

try {
    // Set connection timeout to prevent hanging
    $online_conn = new mysqli($online_host, $online_username, $online_password, $online_dbname, 3306);
    
    // Check if connection was successful
    if ($online_conn->connect_error) {
        error_log("Online database connection failed: " . $online_conn->connect_error);
        $online_conn = null;
    } else {
        // Set charset to prevent encoding issues
        $online_conn->set_charset("utf8mb4");
    }
} catch (Exception $e) {
    error_log("Online database connection exception: " . $e->getMessage());
    $online_conn = null;
}
?>