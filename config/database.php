<?php
// Database configuration
$host = 'seait-edu.ph';
$dbname = 'seaitedu_facallti';
$username = 'seaitedu_facallti';
$password = '020894FaCallTi';

// Create connection with socket path
$conn = mysqli_connect($host, $username, $password, $dbname, 3306, '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock');

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set charset to utf8mb4 to support 4-byte UTF-8 characters (emojis)
mysqli_set_charset($conn, "utf8mb4");

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');