<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<script>console.log('Test page started');</script>";

// Test database connection
require_once '../config/database.php';
echo "<script>console.log('Database config loaded');</script>";

if ($conn) {
    echo "<script>console.log('Database connection successful');</script>";
} else {
    echo "<script>console.log('Database connection failed: " . mysqli_connect_error() . "');</script>";
}

// Test basic query
$test_query = "SELECT COUNT(*) as count FROM employees";
$result = mysqli_query($conn, $test_query);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<script>console.log('Test query successful: " . $row['count'] . " employees found');</script>";
} else {
    echo "<script>console.log('Test query failed: " . mysqli_error($conn) . "');</script>";
}

// Test session
echo "<script>console.log('Session ID: " . session_id() . "');</script>";
echo "<script>console.log('User ID: " . ($_SESSION['user_id'] ?? 'NOT SET') . "');</script>";
echo "<script>console.log('User Role: " . ($_SESSION['role'] ?? 'NOT SET') . "');</script>";

// Test header include
echo "<script>console.log('About to include header...');</script>";
try {
    include 'includes/header.php';
    echo "<script>console.log('Header included successfully');</script>";
} catch (Exception $e) {
    echo "<script>console.log('Header include failed: " . addslashes($e->getMessage()) . "');</script>";
} catch (Error $e) {
    echo "<script>console.log('Header include failed: " . addslashes($e->getMessage()) . "');</script>";
}

echo "<h1>Test Leave Reports Page</h1>";
echo "<p>If you can see this, the basic page is working.</p>";
echo "<p>Check the browser console for debugging information.</p>";
?>
