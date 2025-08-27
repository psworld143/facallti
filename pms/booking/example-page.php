<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Set page information
$page_title = 'Example Page';
$page_subtitle = 'This is an example of how to use the unified navigation system';

// Include the template
include 'includes/template.php';
?>

<!-- Custom page content goes here -->
<div class="space-y-6">
    <div class="bg-blue-50 border-l-4 border-blue-400 p-4">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-info-circle text-blue-400"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm text-blue-700">
                    This page demonstrates the unified navigation system. The sidebar and navbar are automatically included and adapt to your user role.
                </p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div class="bg-white border border-gray-200 rounded-lg p-6">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-gradient-to-r from-primary to-secondary rounded-full flex items-center justify-center mr-4">
                    <i class="fas fa-user text-white text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">User Role</h3>
                    <p class="text-gray-600"><?php echo ucfirst(str_replace('_', ' ', $_SESSION['user_role'])); ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white border border-gray-200 rounded-lg p-6">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-gradient-to-r from-green-400 to-teal-400 rounded-full flex items-center justify-center mr-4">
                    <i class="fas fa-check text-white text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">Navigation</h3>
                    <p class="text-gray-600">Role-based menu items</p>
                </div>
            </div>
        </div>

        <div class="bg-white border border-gray-200 rounded-lg p-6">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-gradient-to-r from-purple-400 to-pink-400 rounded-full flex items-center justify-center mr-4">
                    <i class="fas fa-mobile-alt text-white text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">Responsive</h3>
                    <p class="text-gray-600">Mobile-friendly design</p>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white border border-gray-200 rounded-lg p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Features</h3>
        <ul class="space-y-2 text-gray-600">
            <li class="flex items-center">
                <i class="fas fa-check text-green-500 mr-2"></i>
                Role-based navigation that adapts to user permissions
            </li>
            <li class="flex items-center">
                <i class="fas fa-check text-green-500 mr-2"></i>
                Collapsible submenus for better organization
            </li>
            <li class="flex items-center">
                <i class="fas fa-check text-green-500 mr-2"></i>
                User dropdown menu with profile and logout options
            </li>
            <li class="flex items-center">
                <i class="fas fa-check text-green-500 mr-2"></i>
                Notifications system with real-time updates
            </li>
            <li class="flex items-center">
                <i class="fas fa-check text-green-500 mr-2"></i>
                Mobile-responsive design with hamburger menu
            </li>
            <li class="flex items-center">
                <i class="fas fa-check text-green-500 mr-2"></i>
                Quick actions section for common tasks
            </li>
        </ul>
    </div>
</div>
