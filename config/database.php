<?php
// Database configuration for online server
$host = 'localhost';
$dbname = 'seait_website';
$username = 'root';
$password = '';

// Create connection
$conn = mysqli_connect($host, $username, $password, $dbname);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set charset to utf8
mysqli_set_charset($conn, "utf8");

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');