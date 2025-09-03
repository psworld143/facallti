<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/id_encryption.php';

check_social_media_manager();

if (isset($_GET['id'])) {
    $encrypted_id = $_GET['id'];
    $post_id = safe_decrypt_id($encrypted_id, 0);

    // Update post status to rejected
    $query = "UPDATE posts SET status = 'rejected', approved_by = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ii", $_SESSION['user_id'], $post_id);

    if (mysqli_stmt_execute($stmt)) {
        header("Location: dashboard.php?message=Post rejected successfully!");
    } else {
        header("Location: dashboard.php?message=Error rejecting post.");
    }
} else {
    header("Location: dashboard.php?message=Invalid post ID.");
}
exit();
?>