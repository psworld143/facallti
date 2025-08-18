<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

check_social_media_manager();

if (isset($_GET['id'])) {
    $post_id = (int)$_GET['id'];

    // Update post status to approved
    $query = "UPDATE posts SET status = 'approved', approved_by = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ii", $_SESSION['user_id'], $post_id);

    if (mysqli_stmt_execute($stmt)) {
        header("Location: dashboard.php?message=Post approved successfully!");
    } else {
        header("Location: dashboard.php?message=Error approving post.");
    }
} else {
    header("Location: dashboard.php?message=Invalid post ID.");
}
exit();
?>