<?php
// Unified Header for Content Creator Portal
// This file provides a consistent header across all content creator pages
// Usage: include this file and set $page_title before including

// Default context if not set
if (!isset($sidebar_context)) {
    $sidebar_context = 'main'; // 'main' for regular content creator pages
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - Content Creator Portal' : 'Content Creator Portal'; ?></title>
    <!-- Favicon Configuration -->
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
    <link rel="icon" type="image/png" href="../assets/images/seait-logo.png">
    <link rel="shortcut icon" type="image/x-icon" href="../assets/images/favicon.ico">
    <link rel="shortcut icon" type="image/png" href="../assets/images/seait-logo.png">
    <link rel="apple-touch-icon" type="image/png" href="../assets/images/seait-logo.png">
    <link rel="apple-touch-icon-precomposed" type="image/png" href="../assets/images/seait-logo.png">
    <meta name="msapplication-TileImage" content="../assets/images/seait-logo.png">
    <meta name="msapplication-TileColor" content="#FF6B35">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'seait-orange': '#FF6B35',
                        'seait-dark': '#2C3E50',
                        'seait-light': '#FFF8F0'
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .sidebar {
            transform: translateX(-100%);
            transition: transform 0.3s ease-in-out;
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
            background: linear-gradient(180deg, #2C3E50 0%, #34495E 100%);
        }
        .sidebar.open {
            transform: translateX(0);
        }
        .sidebar-overlay {
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease-in-out, visibility 0.3s ease-in-out;
        }
        .sidebar-overlay.open {
            opacity: 1;
            visibility: visible;
        }
        @media (min-width: 1024px) {
            .sidebar {
                transform: translateX(0);
            }
        }

        /* Sidebar scrollable content */
        .sidebar-content {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 1rem;
        }

        .sidebar-content::-webkit-scrollbar {
            width: 4px;
        }

        .sidebar-content::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 2px;
        }

        .sidebar-content::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 2px;
        }

        .sidebar-content::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.5);
        }

        /* Sidebar header and footer fixed */
        .sidebar-header,
        .sidebar-footer {
            flex-shrink: 0;
        }

        /* Prevent horizontal overflow */
        body, html {
            overflow-x: hidden;
            max-width: 100vw;
        }

        /* Ensure main content doesn't overflow */
        .flex-1 {
            min-width: 0;
        }

        /* Mobile responsiveness improvements */
        @media (max-width: 768px) {
            .px-4 {
                padding-left: 1rem;
                padding-right: 1rem;
            }

            .px-6 {
                padding-left: 1.5rem;
                padding-right: 1.5rem;
            }

            .px-8 {
                padding-left: 2rem;
                padding-right: 2rem;
            }
        }

        @media (max-width: 640px) {
            .px-4 {
                padding-left: 0.75rem;
                padding-right: 0.75rem;
            }

            .px-6 {
                padding-left: 1rem;
                padding-right: 1rem;
            }

            .px-8 {
                padding-left: 1.5rem;
                padding-right: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .px-4 {
                padding-left: 0.5rem;
                padding-right: 0.5rem;
            }

            .px-6 {
                padding-left: 0.75rem;
                padding-right: 0.75rem;
            }

            .px-8 {
                padding-left: 1rem;
                padding-right: 1rem;
            }
        }

        /* Animation keyframes */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fadeInUp {
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes activePulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
        }

        .animate-active-pulse {
            animation: activePulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.5;
            }
        }

        .animate-pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen">
        <?php include 'sidebar.php'; ?>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col lg:ml-0">
            <!-- Top Header -->
            <header class="bg-white shadow-sm border-b border-gray-200">
                <div class="flex justify-between items-center py-4 px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center">
                        <!-- Mobile Sidebar Toggle -->
                        <button onclick="toggleSidebar()" class="lg:hidden mr-3 text-gray-600 hover:text-gray-900">
                            <i class="fas fa-bars text-xl"></i>
                        </button>

                        <div>
                            <h1 class="text-lg sm:text-xl font-bold text-seait-dark">
                                <?php echo isset($page_title) ? $page_title : 'Content Creator Portal'; ?>
                            </h1>
                            <p class="text-xs sm:text-sm text-gray-600">
                                Content Management System
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center space-x-4">
                        <a href="../index.php" class="text-seait-dark hover:text-seait-orange transition hidden sm:block">
                            <i class="fas fa-home mr-2"></i>View Site
                        </a>
                        <div class="hidden sm:block text-right">
                            <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></p>
                            <p class="text-sm text-gray-500">Content Creator</p>
                        </div>
                        <div class="h-8 w-8 sm:h-10 sm:w-10 rounded-full bg-seait-orange flex items-center justify-center overflow-hidden">
                            <?php 
                            // Debug: Log session data
                            error_log("Content Creator Header Debug - Session profile_photo: " . ($_SESSION['profile_photo'] ?? 'NOT SET'));
                            error_log("Content Creator Header Debug - Session user_id: " . ($_SESSION['user_id'] ?? 'NOT SET'));
                            
                            if (!empty($_SESSION['profile_photo'])): ?>
                                <img src="../<?php echo htmlspecialchars($_SESSION['profile_photo']); ?>" 
                                     alt="Profile Photo" 
                                     class="w-full h-full rounded-full object-cover">
                            <?php else: ?>
                                <span class="text-white text-sm sm:text-base font-medium"><?php echo strtoupper(substr($_SESSION['first_name'], 0, 1) . substr($_SESSION['last_name'], 0, 1)); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content Container -->
            <main class="flex-1 py-4 sm:py-6 px-4 sm:px-6 lg:px-8 overflow-auto">
                <div class="px-0 sm:px-0">