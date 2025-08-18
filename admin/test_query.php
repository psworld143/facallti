<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Simple admin check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Access denied. Please log in as admin.");
}

echo "<h1>Posts Query Test</h1>";

// Test the exact same query as posts.php
$query = "SELECT p.*, u.first_name, u.last_name, u.username
          FROM posts p
          LEFT JOIN users u ON p.author_id = u.id
          ORDER BY p.created_at DESC";

echo "<p><strong>Query:</strong> $query</p>";

$result = mysqli_query($conn, $query);
if (!$result) {
    die("Query failed: " . mysqli_error($conn));
}

$total_posts = mysqli_num_rows($result);
echo "<p><strong>Total posts found:</strong> $total_posts</p>";

if ($total_posts > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Title</th><th>Type</th><th>Status</th><th>Author</th><th>Created</th></tr>";

    while ($post = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . $post['id'] . "</td>";
        echo "<td>" . htmlspecialchars($post['title']) . "</td>";
        echo "<td>" . $post['type'] . "</td>";
        echo "<td>" . $post['status'] . "</td>";
        echo "<td>" . htmlspecialchars($post['first_name'] . ' ' . $post['last_name']) . "</td>";
        echo "<td>" . $post['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No posts found.</p>";
}
?>