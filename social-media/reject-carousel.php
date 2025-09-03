<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/id_encryption.php';

check_social_media_manager();

if (isset($_GET['id'])) {
    $encrypted_id = $_GET['id'];
    $slide_id = safe_decrypt_id($encrypted_id, 0);
    $rejection_reason = isset($_GET['reason']) ? $_GET['reason'] : '';

    // Update carousel slide status to rejected and set is_active to 0
    $query = "UPDATE carousel_slides SET status = 'rejected', rejected_by = ?, rejected_at = NOW(), rejection_reason = ?, is_active = 0 WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "isi", $_SESSION['user_id'], $rejection_reason, $slide_id);

    if (mysqli_stmt_execute($stmt)) {
        header("Location: pending-carousel.php?message=Carousel slide rejected successfully!");
    } else {
        header("Location: pending-carousel.php?message=Error rejecting carousel slide.");
    }
} else {
    header("Location: pending-carousel.php?message=Invalid slide ID.");
}
exit();
?>