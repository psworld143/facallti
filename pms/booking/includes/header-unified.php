<?php
// Unified header component that automatically selects the appropriate navbar
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$user_role = $_SESSION['user_role'];
$user_name = $_SESSION['user_name'];
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Hotel PMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        #sidebar {
            transition: transform 0.3s ease-in-out;
            transform: translateX(-100%);
        }
        #sidebar-overlay {
            transition: opacity 0.3s ease-in-out;
        }
        @media (min-width: 1024px) {
            #sidebar {
                transform: translateX(0) !important;
            }
        }
        
        /* Responsive layout fixes */
        .main-content {
            margin-left: 0;
            padding-top: 4rem; /* 64px for navbar */
        }
        
        @media (min-width: 1024px) {
            .main-content {
                margin-left: 16rem; /* 256px for sidebar */
            }
        }
        
        /* Mobile sidebar improvements */
        @media (max-width: 1023px) {
            #sidebar {
                z-index: 50;
            }
            #sidebar-overlay {
                z-index: 40;
            }
        }
    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#667eea',
                        secondary: '#764ba2',
                        success: '#28a745',
                        danger: '#dc3545',
                        warning: '#ffc107',
                        info: '#17a2b8'
                    }
                }
            }
        }
    </script>
    <?php if (isset($additional_css)): ?>
        <?php echo $additional_css; ?>
    <?php endif; ?>
</head>
<body class="bg-gray-50">
    <div class="flex min-h-screen">
        <!-- Sidebar Overlay for Mobile -->
        <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 hidden lg:hidden" onclick="closeSidebar()"></div>
        <?php
        // Include the appropriate navbar based on user role
        switch ($user_role) {
            case 'manager':
                include 'navbar-manager.php';
                break;
            case 'front_desk':
                include 'navbar-frontdesk.php';
                break;
            case 'housekeeping':
                include 'navbar-housekeeping.php';
                break;
            default:
                // Fallback to generic navbar
                include 'header.php';
                break;
        }
        ?>
