<?php
session_start();
require_once '../includes/error_handler.php';
require_once '../config/database.php';

echo "<h1>Faculty Session Test</h1>";
echo "<h2>Session Data:</h2>";
echo "<pre>" . print_r($_SESSION, true) . "</pre>";

echo "<h2>Login Status:</h2>";
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    echo "User ID: " . $_SESSION['user_id'] . "<br>";
    echo "Role: " . $_SESSION['role'] . "<br>";
    echo "Username: " . ($_SESSION['username'] ?? 'NOT SET') . "<br>";
    echo "Email: " . ($_SESSION['email'] ?? 'NOT SET') . "<br>";
    
    if ($_SESSION['role'] === 'teacher') {
        echo "<p style='color: green;'>✅ Faculty user is logged in!</p>";
        
        // Test database connection
        if (checkDatabaseConnection($conn)) {
            echo "<p style='color: green;'>✅ Database connection successful!</p>";
            
            // Test faculty query
            $faculty_id = $_SESSION['user_id'];
            $query = "SELECT id, first_name, last_name, email FROM faculty WHERE id = ?";
            $stmt = mysqli_prepare($conn, $query);
            
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'i', $faculty_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                if ($faculty = mysqli_fetch_assoc($result)) {
                    echo "<p style='color: green;'>✅ Faculty found in database!</p>";
                    echo "<pre>" . print_r($faculty, true) . "</pre>";
                } else {
                    echo "<p style='color: red;'>❌ Faculty not found in database!</p>";
                }
            } else {
                echo "<p style='color: red;'>❌ Database query failed!</p>";
            }
        } else {
            echo "<p style='color: red;'>❌ Database connection failed!</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ User is not a faculty member (role: " . $_SESSION['role'] . ")</p>";
    }
} else {
    echo "<p style='color: red;'>❌ User is not logged in!</p>";
    echo "<p><a href='../index.php?login=required'>Click here to login</a></p>";
}

echo "<h2>Available Faculty Users:</h2>";
$query = "SELECT id, first_name, last_name, email, password IS NOT NULL as has_password FROM faculty WHERE password IS NOT NULL LIMIT 5";
$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        echo "ID: " . $row['id'] . " - " . $row['first_name'] . " " . $row['last_name'] . " (" . $row['email'] . ") - Has Password: " . ($row['has_password'] ? 'Yes' : 'No') . "<br>";
    }
} else {
    echo "Failed to query faculty table";
}
?>
