<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has guidance_officer role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guidance_officer') {
    header('Location: ../login.php');
    exit();
}

// Check if file parameter is provided
if (!isset($_GET['file']) || empty($_GET['file'])) {
    $_SESSION['message'] = "No file specified for deletion.";
    $_SESSION['message_type'] = 'error';
    header('Location: settings.php');
    exit();
}

$filename = $_GET['file'];
$backup_dir = 'backups/';
$file_path = $backup_dir . $filename;

// Security check: ensure the file is within the backups directory
$real_file_path = realpath($file_path);
$real_backup_dir = realpath($backup_dir);

if ($real_file_path === false || strpos($real_file_path, $real_backup_dir) !== 0) {
    $_SESSION['message'] = "Invalid file path.";
    $_SESSION['message_type'] = 'error';
    header('Location: settings.php');
    exit();
}

// Check if file exists and is a .sql file
if (!file_exists($file_path) || pathinfo($filename, PATHINFO_EXTENSION) !== 'sql') {
    $_SESSION['message'] = "Backup file not found or invalid file type.";
    $_SESSION['message_type'] = 'error';
    header('Location: settings.php');
    exit();
}

// Attempt to delete the file
if (unlink($file_path)) {
    $_SESSION['message'] = "Backup file '$filename' deleted successfully.";
    $_SESSION['message_type'] = 'success';
} else {
    $_SESSION['message'] = "Failed to delete backup file '$filename'.";
    $_SESSION['message_type'] = 'error';
}

header('Location: settings.php');
exit();
?>
