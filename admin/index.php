<?php
// Admin index.php - Redirect to dashboard
session_start();
require_once '../includes/error_handler.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has admin role
check_admin();

// Redirect to dashboard
header('Location: dashboard.php');
exit();
?>
