<?php
// Simple test script to check if the teachers page works
session_start();

// Simulate a logged-in head user
$_SESSION['user_id'] = 7; // Using user_id 7 which corresponds to a head in the database
$_SESSION['role'] = 'head';
$_SESSION['username'] = 'test_head';
$_SESSION['first_name'] = 'Test';
$_SESSION['last_name'] = 'Head';

// Include the database connection
require_once 'config/database.php';

// Test database connection
if (!$conn) {
    die("Database connection failed");
}

echo "Database connection successful<br>";

// Test head query
$head_query = "SELECT h.* FROM heads h WHERE h.user_id = ?";
$head_stmt = mysqli_prepare($conn, $head_query);
if (!$head_stmt) {
    die("Error preparing head statement: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($head_stmt, "i", $_SESSION['user_id']);
if (!mysqli_stmt_execute($head_stmt)) {
    die("Error executing head statement: " . mysqli_stmt_error($head_stmt));
}

$head_result = mysqli_stmt_get_result($head_stmt);
if (!$head_result) {
    die("Error getting head result: " . mysqli_stmt_error($head_stmt));
}

$head_info = mysqli_fetch_assoc($head_result);
if (!$head_info) {
    die("No head info found for user_id: " . $_SESSION['user_id']);
}

echo "Head info found: " . $head_info['department'] . "<br>";

// Test faculty query
$faculty_query = "SELECT COUNT(*) as count FROM faculty f WHERE f.department = ? AND f.is_active = 1";
$faculty_stmt = mysqli_prepare($conn, $faculty_query);
if (!$faculty_stmt) {
    die("Error preparing faculty statement: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($faculty_stmt, "s", $head_info['department']);
if (!mysqli_stmt_execute($faculty_stmt)) {
    die("Error executing faculty statement: " . mysqli_stmt_error($faculty_stmt));
}

$faculty_result = mysqli_stmt_get_result($faculty_stmt);
if (!$faculty_result) {
    die("Error getting faculty result: " . mysqli_stmt_error($faculty_stmt));
}

$faculty_count = mysqli_fetch_assoc($faculty_result);
echo "Faculty count in department: " . $faculty_count['count'] . "<br>";

echo "All tests passed! The teachers page should work correctly.";
?>
