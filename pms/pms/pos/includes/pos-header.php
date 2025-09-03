<?php
// POS-specific header component that matches PMS design
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../booking/login.php');
    exit();
}

$user_role = $_SESSION['user_role'] ?? 'pos_user';
$user_name = $_SESSION['user_name'] ?? 'POS User';
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Hotel POS System</title>
    <link rel="icon" type="image/png" href="../../../assets/images/seait-logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Sidebar mobile responsiveness */
        #sidebar {
            transition: transform 0.3s ease-in-out;
        }
        
        /* Mobile: sidebar starts hidden */
        @media (max-width: 1023px) {
            #sidebar {
                transform: translateX(-100%);
                z-index: 50;
            }
            #sidebar.sidebar-open {
                transform: translateX(0);
            }
        }
        
        /* Desktop: sidebar always visible */
        @media (min-width: 1024px) {
            #sidebar {
                transform: translateX(0) !important;
            }
        }
        
        #sidebar-overlay {
            transition: opacity 0.3s ease-in-out;
            z-index: 40;
        }
        
        /* Responsive layout fixes */
        .main-content {
            margin-left: 0;
            padding-top: 4rem;
        }
        
        @media (min-width: 1024px) {
            .main-content {
                margin-left: 16rem;
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
</head>
<body class="bg-gray-50">
    <div class="flex min-h-screen">
        <!-- Sidebar Overlay for Mobile -->
        <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden lg:hidden" onclick="closeSidebar()"></div>
        
        <!-- Top Navigation Bar -->
        <nav class="fixed top-0 left-0 right-0 bg-white shadow-md z-20 lg:ml-64">
            <div class="flex items-center justify-between px-4 py-3">
                <!-- Mobile Menu Button -->
                <button onclick="toggleSidebar()" class="lg:hidden p-2 rounded-lg hover:bg-gray-100">
                    <i class="fas fa-bars text-gray-600"></i>
                </button>
                
                <!-- Page Title -->
                <h1 class="text-lg font-semibold text-gray-800"><?php echo isset($page_title) ? $page_title : 'POS System'; ?></h1>
                
                <!-- User Menu -->
                <div class="flex items-center space-x-4">
                    <div class="text-right hidden sm:block">
                        <div id="current-date" class="text-sm text-gray-600"></div>
                        <div id="current-time" class="text-sm text-gray-600"></div>
                    </div>
                    <div class="relative">
                        <button onclick="toggleUserDropdown()" class="flex items-center space-x-2 p-2 rounded-lg hover:bg-gray-100">
                            <div class="w-8 h-8 bg-gradient-to-r from-primary to-secondary rounded-full flex items-center justify-center">
                                <i class="fas fa-user text-white text-sm"></i>
                            </div>
                            <span class="hidden sm:block text-sm font-medium text-gray-700"><?php echo $user_name; ?></span>
                            <i class="fas fa-chevron-down text-xs text-gray-500"></i>
                        </button>
                        <div id="user-dropdown" class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 hidden">
                            <div class="py-2">
                                <a href="../../booking/profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-user mr-2"></i>Profile
                                </a>
                                <a href="../../booking/" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-tachometer-alt mr-2"></i>PMS Dashboard
                                </a>
                                <hr class="my-2">
                                <a href="../../booking/logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                    <i class="fas fa-sign-out-alt mr-2"></i>Logout
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </nav>
