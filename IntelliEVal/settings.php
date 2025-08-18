<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has guidance_officer role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guidance_officer') {
    header('Location: ../login.php');
    exit();
}

// Set page title
$page_title = 'System Settings';

// Include the shared header
include 'includes/header.php';
?>

<div class="mb-6 sm:mb-8">
    <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">System Settings</h1>
    <p class="text-sm sm:text-base text-gray-600">Configure system preferences and user settings</p>
</div>

<!-- Coming Soon -->
<div class="bg-white rounded-lg shadow-md p-6 sm:p-8 text-center">
    <div class="w-16 h-16 sm:w-24 sm:h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4 sm:mb-6">
        <i class="fas fa-cog text-gray-600 text-2xl sm:text-3xl"></i>
    </div>
    <h2 class="text-xl sm:text-2xl font-bold text-gray-900 mb-3 sm:mb-4">Settings Module</h2>
    <p class="text-sm sm:text-base text-gray-600 mb-4 sm:mb-6">The settings system is currently under development. This module will allow you to:</p>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6 max-w-4xl mx-auto">
        <div class="text-center">
            <div class="w-10 h-10 sm:w-12 sm:h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-2 sm:mb-3">
                <i class="fas fa-user-cog text-blue-600 text-sm sm:text-base"></i>
            </div>
            <h3 class="font-medium text-gray-900 mb-1 sm:mb-2 text-sm sm:text-base">Profile Settings</h3>
            <p class="text-xs sm:text-sm text-gray-500">Update personal information</p>
        </div>
        <div class="text-center">
            <div class="w-10 h-10 sm:w-12 sm:h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-2 sm:mb-3">
                <i class="fas fa-bell text-green-600 text-sm sm:text-base"></i>
            </div>
            <h3 class="font-medium text-gray-900 mb-1 sm:mb-2 text-sm sm:text-base">Notifications</h3>
            <p class="text-xs sm:text-sm text-gray-500">Configure alert preferences</p>
        </div>
        <div class="text-center">
            <div class="w-10 h-10 sm:w-12 sm:h-12 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-2 sm:mb-3">
                <i class="fas fa-shield-alt text-purple-600 text-sm sm:text-base"></i>
            </div>
            <h3 class="font-medium text-gray-900 mb-1 sm:mb-2 text-sm sm:text-base">Security</h3>
            <p class="text-xs sm:text-sm text-gray-500">Password and security settings</p>
        </div>
    </div>
</div>

<?php
// Include the shared footer
include 'includes/footer.php';
?>