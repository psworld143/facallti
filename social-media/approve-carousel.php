<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

check_social_media_manager();

if (isset($_GET['id'])) {
    $slide_id = (int)$_GET['id'];

    // Update carousel slide status to approved and set is_active to 1
    $query = "UPDATE carousel_slides SET status = 'approved', approved_by = ?, is_active = 1 WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ii", $_SESSION['user_id'], $slide_id);

    if (mysqli_stmt_execute($stmt)) {
        header("Location: pending-carousel.php?message=Carousel slide approved successfully!");
    } else {
        header("Location: pending-carousel.php?message=Error approving carousel slide.");
    }
} else {
    header("Location: pending-carousel.php?message=Invalid slide ID.");
}
exit();
?>