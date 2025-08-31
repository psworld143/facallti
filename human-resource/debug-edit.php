<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/id_encryption.php';
require_once 'includes/employee_id_generator.php';

echo "<h1>Debug Edit Faculty</h1>";

// Check session
echo "<h2>Session Check:</h2>";
echo "Session user_id: " . ($_SESSION['user_id'] ?? 'NOT SET') . "<br>";
echo "Session role: " . ($_SESSION['role'] ?? 'NOT SET') . "<br>";

// Check if faculty ID is provided
echo "<h2>URL Parameters:</h2>";
echo "ID parameter: " . ($_GET['id'] ?? 'NOT SET') . "<br>";

if (isset($_GET['id']) && !empty($_GET['id'])) {
    // Decrypt the faculty ID
    $faculty_id = safe_decrypt_id($_GET['id']);
    echo "Decrypted faculty ID: " . $faculty_id . "<br>";
    
    if ($faculty_id > 0) {
        // Test database connection
        echo "<h2>Database Test:</h2>";
        $test_query = "SELECT id, first_name, last_name FROM faculty WHERE id = ?";
        $test_stmt = mysqli_prepare($conn, $test_query);
        if ($test_stmt) {
            mysqli_stmt_bind_param($test_stmt, "i", $faculty_id);
            mysqli_stmt_execute($test_stmt);
            $test_result = mysqli_stmt_get_result($test_stmt);
            
            if ($faculty = mysqli_fetch_assoc($test_result)) {
                echo "Faculty found: " . $faculty['first_name'] . " " . $faculty['last_name'] . "<br>";
            } else {
                echo "Faculty not found in database<br>";
            }
        } else {
            echo "Database query failed: " . mysqli_error($conn) . "<br>";
        }
    } else {
        echo "Invalid faculty ID (decrypted to 0 or negative)<br>";
    }
} else {
    echo "No ID parameter provided<br>";
}

echo "<h2>Database Connection:</h2>";
if ($conn) {
    echo "Database connected successfully<br>";
    echo "Database name: " . mysqli_get_dbname($conn) . "<br>";
} else {
    echo "Database connection failed<br>";
}
?>
