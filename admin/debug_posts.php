<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Simple admin check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Access denied. Please log in as admin.");
}

echo "<h1>Posts Debug</h1>";

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
    echo "<h2>First 3 posts (raw data):</h2>";

    for ($i = 0; $i < min(3, $total_posts); $i++) {
        $post = mysqli_fetch_assoc($result);
        echo "<h3>Post " . ($i + 1) . ":</h3>";
        echo "<pre>";
        print_r($post);
        echo "</pre>";
        echo "<hr>";
    }
} else {
    echo "<p>No posts found.</p>";
}

// Test simple posts query
echo "<h2>Simple Posts Query Test:</h2>";
$simple_query = "SELECT * FROM posts LIMIT 3";
$simple_result = mysqli_query($conn, $simple_query);

if ($simple_result) {
    echo "<p>Simple query successful</p>";
    while ($post = mysqli_fetch_assoc($simple_result)) {
        echo "<p>Post ID: " . $post['id'] . ", Title: " . $post['title'] . "</p>";
    }
} else {
    echo "<p>Simple query failed: " . mysqli_error($conn) . "</p>";
}
?>