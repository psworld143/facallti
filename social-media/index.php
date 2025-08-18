<?php
// Social Media Manager index.php - Redirect to dashboard
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has social_media_manager role
check_social_media_manager();

// Redirect to dashboard
header('Location: dashboard.php');
exit();
?>
