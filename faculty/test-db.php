<?php
session_start();
require_once '../config/database.php';

echo "<h1>Database Test</h1>";

// Check if mysqli is available
if (!extension_loaded('mysqli')) {
    echo "❌ mysqli extension not loaded<br>";
    exit();
}

// Check database connection
if (!$conn) {
    echo "❌ Database connection failed: " . mysqli_connect_error() . "<br>";
    exit();
}

echo "✅ Database connected successfully<br>";

// Test simple query
$test_query = "SELECT 1 as test";
$result = mysqli_query($conn, $test_query);
if ($result) {
    echo "✅ Simple query works<br>";
} else {
    echo "❌ Simple query failed: " . mysqli_error($conn) . "<br>";
}

// Test faculty table
$faculty_query = "SELECT COUNT(*) as count FROM faculty";
$result = mysqli_query($conn, $faculty_query);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "✅ Faculty table accessible: " . $row['count'] . " records<br>";
} else {
    echo "❌ Faculty table query failed: " . mysqli_error($conn) . "<br>";
}

// Test prepared statement
$prep_query = "SELECT id, first_name FROM faculty WHERE id = ?";
$stmt = mysqli_prepare($conn, $prep_query);
if ($stmt) {
    echo "✅ Prepared statement created<br>";
    
    // Test with parameter
    $test_id = 2;
    mysqli_stmt_bind_param($stmt, 'i', $test_id);
    $execute_result = mysqli_stmt_execute($stmt);
    
    if ($execute_result) {
        echo "✅ Prepared statement executed<br>";
        $result = mysqli_stmt_get_result($stmt);
        $faculty = mysqli_fetch_assoc($result);
        if ($faculty) {
            echo "✅ Faculty found: " . $faculty['first_name'] . "<br>";
        } else {
            echo "❌ Faculty not found<br>";
        }
    } else {
        echo "❌ Prepared statement execution failed: " . mysqli_stmt_error($stmt) . "<br>";
    }
} else {
    echo "❌ Prepared statement creation failed: " . mysqli_error($conn) . "<br>";
}

// Test session
echo "<h2>Session Data:</h2>";
echo "<pre>" . print_r($_SESSION, true) . "</pre>";
?>
