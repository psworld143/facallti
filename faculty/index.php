<?php
// Faculty index.php - Redirect to dashboard
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has teacher role
check_login();
if ($_SESSION['role'] !== 'teacher') {
    header('Location: ../index.php');
    exit();
}

// Redirect to dashboard
header('Location: dashboard.php');
exit();
?>
