<?php
// Database configuration
$host = 'localhost';
$dbname = 'seait_website';
$username = 'root';
$password = '';

// Create connection
$conn = mysqli_connect($host, $username, $password, $dbname, 3306, '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock');

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set charset to utf8
mysqli_set_charset($conn, "utf8");