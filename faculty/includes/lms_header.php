<?php
// This file contains the shared LMS header for faculty class pages
// Include this file at the top of each class page after session_start() and database connection
// Requires $class_id and $class_data to be set before including

// Check if user is logged in and has teacher role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

// Verify class_id is provided
if (!isset($class_id) || !$class_data) {
    header('Location: class-management.php');
    exit();
}

// Set sidebar context for LMS class pages
$sidebar_context = 'lms';

// Include the unified header
include 'unified-header.php';
?>