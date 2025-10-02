<?php
/**
 * FACALLTI Database Configuration
 * Faculty Consultation Time Interface
 */

// Database configuration
$host = 'localhost';
$dbname = 'cons_facallti';
$username = 'cons_facallti';
$password = '020894Facallti';

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