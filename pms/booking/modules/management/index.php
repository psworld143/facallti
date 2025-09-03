<?php
session_start();
require_once '../../../includes/error_handler.php';
require_once '../includes/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has manager access
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
    header('Location: ../../login.php');
    exit();
}

// Redirect to reports dashboard
header('Location: reports-dashboard.php');
exit();
?>
