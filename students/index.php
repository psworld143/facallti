<?php
// Students index.php - Redirect to dashboard
session_start();
require_once '../includes/error_handler.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has student role
check_login();
if ($_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

// Redirect to dashboard
header('Location: dashboard.php');
exit();
?>
