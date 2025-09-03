<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Profile Test Page</h1>";

// Check if session is working
echo "<h2>Session Check:</h2>";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
echo "Session ID: " . session_id() . "<br>";
echo "Session Status: " . session_status() . "<br>";

// Check session variables
echo "<h2>Session Variables:</h2>";
echo "user_id: " . ($_SESSION['user_id'] ?? 'NOT SET') . "<br>";
echo "role: " . ($_SESSION['role'] ?? 'NOT SET') . "<br>";
echo "username: " . ($_SESSION['username'] ?? 'NOT SET') . "<br>";
echo "first_name: " . ($_SESSION['first_name'] ?? 'NOT SET') . "<br>";
echo "last_name: " . ($_SESSION['last_name'] ?? 'NOT SET') . "<br>";

// Check if user is logged in
echo "<h2>Authentication Check:</h2>";
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    echo "User is logged in<br>";
    if ($_SESSION['role'] === 'social_media_manager') {
        echo "User has correct role (social_media_manager)<br>";
        echo "<a href='profile.php'>Go to Profile Page</a>";
    } else {
        echo "User has wrong role: " . $_SESSION['role'] . "<br>";
        echo "Expected: social_media_manager<br>";
    }
} else {
    echo "User is NOT logged in<br>";
    echo "<a href='../index.php'>Go to Main Site to Login</a><br>";
    echo "<br>Available roles in the system:<br>";
    echo "- admin<br>";
    echo "- social_media_manager<br>";
    echo "- content_creator<br>";
    echo "- student<br>";
    echo "- teacher<br>";
}

// Check database connection
echo "<h2>Database Connection Check:</h2>";
try {
    require_once '../config/database.php';
    echo "Database connection: SUCCESS<br>";
    
    // Check if users table exists and has social_media_manager users
    $query = "SELECT COUNT(*) as count FROM users WHERE role = 'social_media_manager'";
    $result = mysqli_query($conn, $query);
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        echo "Social media managers in database: " . $row['count'] . "<br>";
    } else {
        echo "Error querying users table: " . mysqli_error($conn) . "<br>";
    }
} catch (Exception $e) {
    echo "Database connection: FAILED - " . $e->getMessage() . "<br>";
}

echo "<h2>Debug Information:</h2>";
echo "Current URL: " . $_SERVER['REQUEST_URI'] . "<br>";
echo "Script Path: " . __FILE__ . "<br>";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "PHP Version: " . PHP_VERSION . "<br>";
?>
