<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Set page information
$page_title = 'Example Template Page';
$page_subtitle = 'This page demonstrates how to use the module template';
$required_roles = ['front_desk', 'manager']; // Only front desk and managers can access

// Include the module template
include '../../includes/module-template.php';
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
                    This page uses the module template which automatically includes the appropriate navigation based on your user role.
                </p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div class="bg-white border border-gray-200 rounded-lg p-6">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-gradient-to-r from-blue-400 to-blue-600 rounded-full flex items-center justify-center mr-4">
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
                <div class="w-12 h-12 bg-gradient-to-r from-green-400 to-green-600 rounded-full flex items-center justify-center mr-4">
                    <i class="fas fa-check text-white text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">Access Level</h3>
                    <p class="text-gray-600">Front Desk Operations</p>
                </div>
            </div>
        </div>

        <div class="bg-white border border-gray-200 rounded-lg p-6">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-gradient-to-r from-purple-400 to-purple-600 rounded-full flex items-center justify-center mr-4">
                    <i class="fas fa-mobile-alt text-white text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">Navigation</h3>
                    <p class="text-gray-600">Role-specific menu</p>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white border border-gray-200 rounded-lg p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Template Features</h3>
        <ul class="space-y-2 text-gray-600">
            <li class="flex items-center">
                <i class="fas fa-check text-green-500 mr-2"></i>
                Automatic role-based navigation selection
            </li>
            <li class="flex items-center">
                <i class="fas fa-check text-green-500 mr-2"></i>
                Role-based access control
            </li>
            <li class="flex items-center">
                <i class="fas fa-check text-green-500 mr-2"></i>
                Consistent page layout and styling
            </li>
            <li class="flex items-center">
                <i class="fas fa-check text-green-500 mr-2"></i>
                Mobile-responsive design
            </li>
            <li class="flex items-center">
                <i class="fas fa-check text-green-500 mr-2"></i>
                Real-time clock and date display
            </li>
        </ul>
    </div>
</div>
