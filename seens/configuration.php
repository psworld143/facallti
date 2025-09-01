<?php
$host = 'localhost';
$username = 'root';
$password = '';
$dbname = 'seait_seens';

// Detect operating system and set appropriate socket configuration
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    // Windows - no socket needed, use TCP/IP
    $socket = null;
} else {
    // macOS/Linux - use socket for XAMPP
    $socket = '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock';
}

// Initialize connection variable but don't connect yet
$conn = null;

// Function to establish database connection
if (!function_exists('establishConnection')) {
    function establishConnection() {
        global $host, $username, $password, $dbname, $socket, $conn;
        
        try {
            if ($socket) {
                $conn = new mysqli($host, $username, $password, $dbname, 3306, $socket);
            } else {
                $conn = new mysqli($host, $username, $password, $dbname, 3306);
            }
            return $conn;
        } catch (Exception $e) {
            // Database doesn't exist or connection failed
            return null;
        }
    }
}

?>