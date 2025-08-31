<?php
// This file contains the shared responsive header for all Human Resource pages
// Include this file at the top of each page after session_start() and database connection
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - Human Resource' : 'Human Resource'; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'seait-orange': '#FF6B35',
                        'seait-dark': '#2C3E50',
                        'seait-light': '#FFF8F0',
                        'hr-secondary': '#374151'
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- jQuery and jGrowl -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jgrowl/1.4.8/jquery.jgrowl.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jgrowl/1.4.8/jquery.jgrowl.min.css">
    <style>
        .sidebar {
            transform: translateX(-100%);
            transition: transform 0.3s ease-in-out;
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
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
            max-width: 100%;
        }

        /* Custom jGrowl Success Theme */
        .jGrowl-success {
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #16a34a;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        .jGrowl-success .jGrowl-header {
            color: #16a34a;
            font-weight: 600;
            font-size: 14px;
        }
        
        .jGrowl-success .jGrowl-message {
            color: #16a34a;
            font-size: 13px;
        }
        
        .jGrowl-success .jGrowl-close {
            color: #16a34a;
        }
        
        .jGrowl-success .jGrowl-close:hover {
            color: #15803d;
        }
        
        /* Custom jGrowl Error Theme */
        .jGrowl-error {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        .jGrowl-error .jGrowl-header {
            color: #dc2626;
            font-weight: 600;
            font-size: 14px;
        }
        
        .jGrowl-error .jGrowl-message {
            color: #dc2626;
            font-size: 13px;
        }
        
        .jGrowl-error .jGrowl-close {
            color: #dc2626;
        }
        
        .jGrowl-error .jGrowl-close:hover {
            color: #b91c1c;
        }
        
        /* jGrowl Container Styling */
        #jGrowl {
            z-index: 9999;
        }
        
        .jGrowl-notification {
            font-family: 'Poppins', sans-serif;
        }

        /* Custom animations */
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
            animation: fadeInUp 0.6s ease-out forwards;
            opacity: 0;
        }

        /* Sidebar open/close animations */
        .sidebar.open {
            transform: translateX(0);
        }

        .sidebar-overlay.open {
            opacity: 1;
            pointer-events: auto;
        }

        /* Smooth transitions for all interactive elements */
        .sidebar a {
            position: relative;
            overflow: hidden;
        }

        .sidebar a::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            transition: left 0.5s;
        }

        .sidebar a:hover::before {
            left: 100%;
        }

        /* Active state animations */
        .sidebar a.bg-seait-orange {
            animation: activePulse 2s infinite;
        }

        @keyframes activePulse {
            0%, 100% {
                box-shadow: 0 0 0 0 rgba(249, 115, 22, 0.7);
            }
            50% {
                box-shadow: 0 0 0 10px rgba(249, 115, 22, 0);
            }
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

        /* Table Responsive Styles */
        .hr-table {
            width: 100%;
            min-width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .hr-table th,
        .hr-table td {
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        /* Responsive table container */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            max-width: 100%;
        }

        /* Mobile table improvements */
        @media (max-width: 1024px) {
            .hr-table th,
            .hr-table td {
                padding: 0.5rem 0.25rem;
                font-size: 0.75rem;
            }
        }

        @media (max-width: 768px) {
            .hr-table th,
            .hr-table td {
                padding: 0.375rem 0.125rem;
                font-size: 0.7rem;
            }

            .table-responsive {
                border-radius: 0.5rem;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            }
        }

        @media (max-width: 640px) {
            .hr-table th,
            .hr-table td {
                padding: 0.25rem 0.125rem;
                font-size: 0.65rem;
            }
        }

        @media (max-width: 480px) {
            .hr-table th,
            .hr-table td {
                padding: 0.125rem 0.0625rem;
                font-size: 0.6rem;
            }
        }

        /* Ensure table doesn't break layout */
        .overflow-x-auto {
            max-width: 100vw;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        /* Table cell content wrapping */
        .table-cell-content {
            white-space: normal;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        /* Responsive text truncation */
        .responsive-text {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        @media (max-width: 768px) {
            .responsive-text {
                max-width: 120px;
            }
        }

        @media (max-width: 640px) {
            .responsive-text {
                max-width: 80px;
            }
        }

        @media (max-width: 480px) {
            .responsive-text {
                max-width: 60px;
            }
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen">
        <!-- Sidebar Overlay -->
        <div id="sidebarOverlay" class="sidebar-overlay fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden transition-opacity duration-300 ease-in-out opacity-0 pointer-events-none" onclick="toggleSidebar()"></div>

        <!-- Sidebar -->
        <div id="sidebar" class="sidebar fixed top-0 left-0 h-full w-64 bg-seait-dark z-50 lg:relative lg:translate-x-0 lg:z-auto transform transition-transform duration-300 ease-in-out -translate-x-full lg:translate-x-0">
            <!-- Sidebar Header -->
            <div class="sidebar-header flex items-center justify-center p-4 border-b border-gray-700 bg-gradient-to-r from-gray-800 to-gray-700">
                <div class="flex items-center transform transition-transform duration-200 hover:scale-105">
                    <img src="../assets/images/seait-logo.png" alt="SEAIT Logo" class="h-8 w-auto mr-2 transition-all duration-200 hover:rotate-12">
                    <span class="text-white font-semibold">Human Resource</span>
                </div>
            </div>

            <!-- Navigation Menu - Scrollable Content -->
            <div class="sidebar-content">
                <!-- User Profile Section -->
                <div class="mb-6 p-4 bg-gray-800 rounded-lg mx-3 transform transition-all duration-300 hover:bg-gray-700 hover:scale-105 hover:shadow-lg">
                    <div class="flex items-center">
                        <div class="h-12 w-12 rounded-full bg-seait-orange flex items-center justify-center mr-3 transition-all duration-300 hover:bg-orange-500 hover:scale-110 hover:shadow-md">
                            <span class="text-white font-semibold text-lg"><?php echo strtoupper(substr($_SESSION['first_name'], 0, 1) . substr($_SESSION['last_name'], 0, 1)); ?></span>
                        </div>
                        <div class="flex-1">
                            <p class="text-white font-semibold text-sm"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></p>
                            <p class="text-gray-400 text-xs">HR Manager</p>
                            <div class="flex items-center mt-1">
                                <div class="w-2 h-2 bg-green-400 rounded-full mr-2 animate-pulse"></div>
                                <span class="text-green-400 text-xs">Online</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <!-- Dashboard Section -->
                    <div class="animate-fadeInUp" style="animation-delay: 0.1s;">
                        <a href="index.php" class="flex items-center <?php echo basename($_SERVER['PHP_SELF']) === 'index.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                            <i class="fas fa-tachometer-alt mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Dashboard
                        </a>
                    </div>

                    <!-- Employee Management Section -->
                    <div class="animate-fadeInUp" style="animation-delay: 0.2s;">
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3 px-3">Employee Management</h3>
                        <div class="space-y-1">
                            

                            <a href="manage-colleges.php" class="flex items-center <?php echo basename($_SERVER['PHP_SELF']) === 'manage-colleges.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                                <i class="fas fa-university mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>College Management
                            </a>

                            <a href="manage-departments.php" class="flex items-center <?php echo basename($_SERVER['PHP_SELF']) === 'manage-departments.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                                <i class="fas fa-building mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Department Management
                            </a>

                            <a href="admin-employee.php" class="flex items-center <?php echo basename($_SERVER['PHP_SELF']) === 'admin-employee.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                                <i class="fas fa-user-plus mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Admin Employee
                            </a>
                            <a href="manage-faculty.php" class="flex items-center <?php echo basename($_SERVER['PHP_SELF']) === 'manage-faculty.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                                <i class="fas fa-chalkboard-teacher mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Faculty Management
                            </a>
                        </div>
                    </div>

                    <!-- Leave Management Section -->
                    <div class="animate-fadeInUp" style="animation-delay: 0.25s;">
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3 px-3">Leave Management</h3>
                        <div class="space-y-1">
                            <a href="leave-management.php" class="flex items-center <?php echo basename($_SERVER['PHP_SELF']) === 'leave-management.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                                <i class="fas fa-calendar-alt mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Leave Requests
                            </a>

                            <a href="leave-balances.php" class="flex items-center <?php echo basename($_SERVER['PHP_SELF']) === 'leave-balances.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                                <i class="fas fa-chart-pie mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Leave Balances
                            </a>

                            <a href="leave-reports.php" class="flex items-center <?php echo basename($_SERVER['PHP_SELF']) === 'leave-reports.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                                <i class="fas fa-file-alt mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Leave Reports
                            </a>
                        </div>
                    </div>

                    <!-- Regularization Management Section -->
                    <div class="animate-fadeInUp" style="animation-delay: 0.25s;">
                                                    <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3 px-3">Regularization</h3>
                            <div class="space-y-1">
                                <a href="manage-regularization.php" class="flex items-center <?php echo basename($_SERVER['PHP_SELF']) === 'manage-regularization.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                                    <i class="fas fa-clipboard-check mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Regularization Management
                                </a>

                                <a href="add-regularization.php" class="flex items-center <?php echo basename($_SERVER['PHP_SELF']) === 'add-regularization.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                                    <i class="fas fa-plus mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Add Regularization
                                </a>
                            </div>

                            <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3 px-3">Education</h3>
                            <div class="space-y-1">
                                <a href="manage-degrees.php" class="flex items-center <?php echo basename($_SERVER['PHP_SELF']) === 'manage-degrees.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                                    <i class="fas fa-graduation-cap mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Manage Degrees
                                </a>
                            </div>
                    </div>

                    <!-- Recruitment Section -->
                    <div class="animate-fadeInUp" style="animation-delay: 0.3s;">
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3 px-3">Recruitment</h3>
                        <div class="space-y-1">
                            <a href="job-postings.php" class="flex items-center <?php echo basename($_SERVER['PHP_SELF']) === 'job-postings.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                                <i class="fas fa-briefcase mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Job Postings
                            </a>

                            <a href="applications.php" class="flex items-center <?php echo basename($_SERVER['PHP_SELF']) === 'applications.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                                <i class="fas fa-file-alt mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Applications
                            </a>

                            <a href="interviews.php" class="flex items-center <?php echo basename($_SERVER['PHP_SELF']) === 'interviews.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                                <i class="fas fa-handshake mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Interviews
                            </a>
                        </div>
                    </div>

                    <!-- Performance Management Section -->
                    <div class="animate-fadeInUp" style="animation-delay: 0.4s;">
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3 px-3">Performance</h3>
                        <div class="space-y-1">
                            <a href="performance-reviews.php" class="flex items-center <?php echo basename($_SERVER['PHP_SELF']) === 'performance-reviews.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                                <i class="fas fa-star mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Performance Reviews
                            </a>

                            <a href="training-programs.php" class="flex items-center <?php echo basename($_SERVER['PHP_SELF']) === 'training-programs.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                                <i class="fas fa-graduation-cap mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Training Programs
                            </a>
                        </div>
                    </div>

                    <!-- Reports & Analytics Section -->
                    <div class="animate-fadeInUp" style="animation-delay: 0.5s;">
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3 px-3">Reports & Analytics</h3>
                        <div class="space-y-1">
                            <a href="hr-reports.php" class="flex items-center <?php echo basename($_SERVER['PHP_SELF']) === 'hr-reports.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                                <i class="fas fa-chart-bar mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>HR Reports
                            </a>
                            
                            <a href="analytics.php" class="flex items-center <?php echo basename($_SERVER['PHP_SELF']) === 'analytics.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                                <i class="fas fa-chart-line mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Analytics
                            </a>
                        </div>
                    </div>

                    <!-- System Settings Section -->
                    <div class="animate-fadeInUp" style="animation-delay: 0.6s;">
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3 px-3">System</h3>
                        <div class="space-y-1">
                            <a href="settings.php" class="flex items-center <?php echo basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                                <i class="fas fa-cog mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Settings
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar Footer -->
            <div class="sidebar-footer p-4 border-t border-gray-700 bg-gradient-to-r from-gray-800 to-gray-700">
                <a href="logout.php" class="flex items-center bg-red-600 text-white hover:bg-red-700 px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-lg">
                    <i class="fas fa-sign-out-alt mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Logout
                </a>
            </div>
        </div>

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
                            <h1 class="text-lg sm:text-xl font-bold text-seait-dark">Human Resource Management</h1>
                            <p class="text-xs sm:text-sm text-gray-600">Employee & Recruitment Management System</p>
                        </div>
                    </div>

                    <div class="flex items-center space-x-4">
                        <div class="h-8 w-8 sm:h-10 sm:w-10 rounded-full bg-seait-orange flex items-center justify-center">
                            <span class="text-white text-sm sm:text-base font-medium"><?php echo strtoupper(substr($_SESSION['first_name'], 0, 1) . substr($_SESSION['last_name'], 0, 1)); ?></span>
                        </div>
                        <a href="logout.php" class="text-gray-600 hover:text-red-600 p-2 rounded-lg hover:bg-gray-100 transition-colors" title="Logout">
                            <i class="fas fa-sign-out-alt text-xl"></i>
                        </a>
                    </div>
                </div>
            </header>

            <!-- Main Content Container -->
            <main class="flex-1 py-4 sm:py-6 px-4 sm:px-6 lg:px-8 overflow-auto">
                <div class="px-0 sm:px-0">

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (sidebar && overlay) {
        if (sidebar.classList.contains('open')) {
            sidebar.classList.remove('open');
            overlay.classList.remove('open');
        } else {
            sidebar.classList.add('open');
            overlay.classList.add('open');
        }
    }
}

// Initialize sidebar behavior
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const sidebarLinks = document.querySelectorAll('#sidebar a:not([href^="#"])'); // Exclude anchor links
    const sidebarContent = document.querySelector('.sidebar-content');

    // Store scroll position in sessionStorage
    function saveScrollPosition() {
        if (sidebarContent) {
            sessionStorage.setItem('hrSidebarScrollPosition', sidebarContent.scrollTop);
        }
    }

    // Restore scroll position from sessionStorage
    function restoreScrollPosition() {
        if (sidebarContent) {
            const savedPosition = sessionStorage.getItem('hrSidebarScrollPosition');
            if (savedPosition !== null) {
                setTimeout(() => {
                    sidebarContent.scrollTop = parseInt(savedPosition);
                }, 100);
            }
        }
    }

    // Save scroll position when scrolling
    if (sidebarContent) {
        sidebarContent.addEventListener('scroll', function() {
            saveScrollPosition();
        });
    }

    // Restore scroll position on page load
    restoreScrollPosition();

    // Ensure sidebar is in correct state on load
    if (sidebar) {
        if (window.innerWidth >= 1024) {
            sidebar.classList.remove('open');
            if (overlay) overlay.classList.remove('open');
        }
    }

    // Handle link clicks - NO MOVEMENT
    sidebarLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            // Save current scroll position before navigation
            saveScrollPosition();

            // Prevent any default behavior that might cause movement
            e.preventDefault();

            // Get the href and navigate immediately without any delays or animations
            const href = link.getAttribute('href');
            if (href) {
                // Navigate immediately - no delays, no animations, no sidebar movement
                window.location.href = href;
            }
        });
    });

    // Handle overlay clicks
    if (overlay) {
        overlay.addEventListener('click', (e) => {
            e.preventDefault();
            if (window.innerWidth < 1024) {
                toggleSidebar();
            }
        });
    }
});

// Handle window resize
window.addEventListener('resize', () => {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (window.innerWidth >= 1024) {
        if (sidebar) sidebar.classList.remove('open');
        if (overlay) overlay.classList.remove('open');
    }
});

// Prevent unwanted sidebar interactions on desktop
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    if (sidebar) {
        sidebar.addEventListener('click', (e) => {
            // Prevent event bubbling on desktop
            if (window.innerWidth >= 1024) {
                e.stopPropagation();
            }
        });
    }
});

// Prevent any zooming or scaling issues
document.addEventListener('DOMContentLoaded', function() {
    // Ensure viewport meta tag is properly set
    const viewport = document.querySelector('meta[name="viewport"]');
    if (!viewport) {
        const meta = document.createElement('meta');
        meta.name = 'viewport';
        meta.content = 'width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no';
        document.head.appendChild(meta);
    } else {
        viewport.content = 'width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no';
    }
});
</script>
