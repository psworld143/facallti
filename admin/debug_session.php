<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

echo "<h1>Session and Database Debug</h1>";

// Check session
echo "<h2>Session Information:</h2>";
echo "<p>Session ID: " . session_id() . "</p>";
echo "<p>User ID: " . ($_SESSION['user_id'] ?? 'Not set') . "</p>";
echo "<p>User Role: " . ($_SESSION['role'] ?? 'Not set') . "</p>";
echo "<p>First Name: " . ($_SESSION['first_name'] ?? 'Not set') . "</p>";
echo "<p>Last Name: " . ($_SESSION['last_name'] ?? 'Not set') . "</p>";

// Check database
echo "<h2>Database Information:</h2>";
if ($conn) {
    echo "<p>Database connected successfully</p>";

    // Test simple query
    $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM posts");
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        echo "<p>Total posts: " . $row['total'] . "</p>";
    } else {
        echo "<p>Error querying posts: " . mysqli_error($conn) . "</p>";
    }

    // Test users query
    $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM users");
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        echo "<p>Total users: " . $row['total'] . "</p>";
    } else {
        echo "<p>Error querying users: " . mysqli_error($conn) . "</p>";
    }
} else {
    echo "<p>Database connection failed: " . mysqli_connect_error() . "</p>";
}

// Test admin check function
echo "<h2>Admin Check:</h2>";
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin') {
    echo "<p>User is admin ✓</p>";
} else {
    echo "<p>User is NOT admin ✗</p>";
}

// Test the actual posts query
echo "<h2>Posts Query Test:</h2>";
$query = "SELECT p.*, u.first_name, u.last_name, u.username
          FROM posts p
          LEFT JOIN users u ON p.author_id = u.id
          ORDER BY p.created_at DESC
          LIMIT 3";

$result = mysqli_query($conn, $query);
if ($result) {
    echo "<p>Query executed successfully</p>";
    echo "<p>Number of rows: " . mysqli_num_rows($result) . "</p>";

    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Title</th><th>Type</th><th>Status</th><th>Author</th></tr>";

    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['title']) . "</td>";
        echo "<td>" . $row['type'] . "</td>";
        echo "<td>" . $row['status'] . "</td>";
        echo "<td>" . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>Error with posts query: " . mysqli_error($conn) . "</p>";
}
?>