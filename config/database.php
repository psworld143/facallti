<?php
// Database configuration
$host = 'seait-edu.ph';
$dbname = 'seaitedu_seait_website';
$username = 'seaitedu_seait_website';
$password = '020894Website';

// Create connection
$conn = mysqli_connect($host, $username, $password, $dbname, 3306, '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock');

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set charset to utf8
mysqli_set_charset($conn, "utf8");