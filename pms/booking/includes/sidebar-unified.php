<?php
// Unified sidebar component that automatically selects the appropriate sidebar
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$user_role = $_SESSION['user_role'];

// Include the appropriate sidebar based on user role
switch ($user_role) {
    case 'manager':
        include 'sidebar-manager.php';
        break;
    case 'front_desk':
        include 'sidebar-frontdesk.php';
        break;
    case 'housekeeping':
        include 'sidebar-housekeeping.php';
        break;
    default:
        // Fallback to generic sidebar
        include 'sidebar.php';
        break;
}
?>
