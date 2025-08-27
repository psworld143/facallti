<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Debug Information</h1>";

// Check session
echo "<h2>Session Information:</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<p style='color: red;'>User not logged in - no user_id in session</p>";
} else {
    echo "<p style='color: green;'>User logged in - user_id: " . $_SESSION['user_id'] . "</p>";
}

// Check user role
if (!isset($_SESSION['user_role'])) {
    echo "<p style='color: red;'>No user_role in session</p>";
} else {
    echo "<p style='color: green;'>User role: " . $_SESSION['user_role'] . "</p>";
}

// Check if role is front_desk
if ($_SESSION['user_role'] !== 'front_desk') {
    echo "<p style='color: red;'>User role is not 'front_desk'</p>";
} else {
    echo "<p style='color: green;'>User role is correct (front_desk)</p>";
}

// Check database connection
echo "<h2>Database Connection Test:</h2>";
try {
    require_once '../../includes/config.php';
    echo "<p style='color: green;'>Database connection successful</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>Database connection failed: " . $e->getMessage() . "</p>";
}

// Check current URL
echo "<h2>Current URL Information:</h2>";
echo "<p>Current URL: " . $_SERVER['REQUEST_URI'] . "</p>";
echo "<p>Script Name: " . $_SERVER['SCRIPT_NAME'] . "</p>";
echo "<p>PHP Self: " . $_SERVER['PHP_SELF'] . "</p>";

// Test sidebar include
echo "<h2>Sidebar Include Test:</h2>";
$sidebar_path = '../../includes/sidebar-frontdesk.php';
if (file_exists($sidebar_path)) {
    echo "<p style='color: green;'>Sidebar file exists at: " . $sidebar_path . "</p>";
} else {
    echo "<p style='color: red;'>Sidebar file not found at: " . $sidebar_path . "</p>";
}

// Test footer include
echo "<h2>Footer Include Test:</h2>";
$footer_path = '../../includes/footer.php';
if (file_exists($footer_path)) {
    echo "<p style='color: green;'>Footer file exists at: " . $footer_path . "</p>";
} else {
    echo "<p style='color: red;'>Footer file not found at: " . $footer_path . "</p>";
}

echo "<h2>Navigation Test:</h2>";
$base_path = '/seait/pms/booking/';
echo "<p>Base path: " . $base_path . "</p>";
echo "<p>VIP Guests URL: " . $base_path . 'modules/front-desk/vip-guests.php' . "</p>";
echo "<p>Feedback URL: " . $base_path . 'modules/front-desk/feedback.php' . "</p>";

echo "<h2>Quick Navigation:</h2>";
echo "<a href='vip-guests.php'>Go to VIP Guests Page</a><br>";
echo "<a href='feedback.php'>Go to Feedback Page</a><br>";
echo "<a href='../../includes/sidebar-frontdesk.php'>View Sidebar File</a><br>";
?>
