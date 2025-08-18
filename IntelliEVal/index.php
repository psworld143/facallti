<?php
// IntelliEVal index.php - Redirect to dashboard
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has appropriate role
check_login();
if (!in_array($_SESSION['role'], ['guidance_officer', 'head', 'admin'])) {
    header('Location: ../index.php');
    exit();
}

// Redirect to dashboard
header('Location: dashboard.php');
exit();
?>
