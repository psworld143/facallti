<?php
// Content Creator index.php - Redirect to dashboard
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has content_creator role
check_content_creator();

// Redirect to dashboard
header('Location: dashboard.php');
exit();
?>
