<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

echo "<h1>Posts Test</h1>";

// Check database connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}
echo "<p>Database connected successfully</p>";

// Check if we're logged in
if (!isset($_SESSION['user_id'])) {
    die("Not logged in");
}
echo "<p>Logged in as user ID: " . $_SESSION['user_id'] . "</p>";
echo "<p>User role: " . ($_SESSION['role'] ?? 'not set') . "</p>";

// Test simple query
$test_query = "SELECT COUNT(*) as total FROM posts";
$result = mysqli_query($conn, $test_query);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<p>Total posts in database: " . $row['total'] . "</p>";
} else {
    echo "<p>Error querying posts: " . mysqli_error($conn) . "</p>";
}

// Test the actual query
$query = "SELECT p.*, u.first_name, u.last_name, u.username
          FROM posts p
          LEFT JOIN users u ON p.author_id = u.id
          ORDER BY p.created_at DESC
          LIMIT 5";

$result = mysqli_query($conn, $query);
if ($result) {
    echo "<p>Query executed successfully</p>";
    echo "<p>Number of rows returned: " . mysqli_num_rows($result) . "</p>";

    echo "<h2>First 5 posts:</h2>";
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
    echo "<p>Error with main query: " . mysqli_error($conn) . "</p>";
}
?>