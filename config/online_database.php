<?php
// Database configuration
$host = 'seait-edu.ph';
$dbname = 'seaitedu_seait_website';
$username = 'seaitedu_seait_website';
$password = '020894Website';

// Create connection
$conn = mysqli_connect($host, $username, $password, $dbname);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set charset to utf8mb4 to support 4-byte UTF-8 characters (emojis)
mysqli_set_charset($conn, "utf8mb4");

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');