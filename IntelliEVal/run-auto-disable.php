<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has guidance_officer role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guidance_officer') {
    header('Location: ../login.php');
    exit();
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Capture output from the auto-disable script
    ob_start();
    include 'auto-disable-expired-evaluations.php';
    $output = ob_get_clean();

    // Parse the output to extract information
    if (strpos($output, 'No expired evaluations found') !== false) {
        $message = "No expired evaluations found. All evaluations are current.";
        $message_type = "success";
    } elseif (strpos($output, 'Successfully disabled') !== false) {
        $message = "Auto-disable script completed successfully. Check the output for details.";
        $message_type = "success";
    } else {
        $message = "Auto-disable script completed. Check the output for details.";
        $message_type = "info";
    }

    // Store the detailed output in session for display
    $_SESSION['auto_disable_output'] = $output;
}

// Redirect back to evaluations page
$_SESSION['message'] = $message;
$_SESSION['message_type'] = $message_type;
header('Location: evaluations.php');
exit();
?>