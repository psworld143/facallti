<?php
// Housekeeping-specific navbar component
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'housekeeping') {
    header('Location: ../login.php');
    exit();
}

$user_role = $_SESSION['user_role'];
$user_name = $_SESSION['user_name'];
?>
<!-- Housekeeping Navbar -->
<header class="fixed top-0 left-0 right-0 h-16 bg-gradient-to-r from-green-600 to-emerald-600 text-white flex justify-between items-center px-6 z-50 shadow-lg">
    <div class="flex items-center">
        <button id="mobile-sidebar-toggle" class="lg:hidden mr-4 text-white hover:text-gray-200 transition-colors p-2 rounded-lg hover:bg-white hover:bg-opacity-20" title="Toggle Sidebar">
            <i class="fas fa-bars text-xl"></i>
        </button>
        <i class="fas fa-hotel text-yellow-400 mr-3 text-xl"></i>
        <h1 class="text-xl font-semibold">Hotel PMS</h1>
        <span class="ml-4 text-sm bg-white bg-opacity-20 px-2 py-1 rounded-full">
            Housekeeping Console
        </span>
    </div>
    
    <div class="flex items-center space-x-4">
        <!-- Task Status -->
        <div class="hidden md:flex items-center space-x-2 bg-white bg-opacity-10 px-3 py-1 rounded-full">
            <div class="w-2 h-2 bg-green-400 rounded-full"></div>
            <span class="text-sm" id="task-status">Tasks: 0</span>
        </div>
        
        <!-- Notifications -->
        <div class="relative">
            <button id="notifications-toggle" class="relative p-2 text-white hover:text-gray-200 transition-colors">
                <i class="fas fa-bell text-lg"></i>
                <span id="notification-badge" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center hidden">0</span>
            </button>
            <!-- Notifications dropdown -->
            <div id="notifications-dropdown" class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-xl border border-gray-200 hidden z-50">
                <div class="p-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800">Housekeeping Alerts</h3>
                </div>
                <div id="notifications-list" class="max-h-64 overflow-y-auto">
                    <!-- Notifications will be loaded here -->
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="relative">
            <button id="quick-actions-toggle" class="relative p-2 text-white hover:text-gray-200 transition-colors">
                <i class="fas fa-bolt text-lg"></i>
            </button>
            <!-- Quick actions dropdown -->
            <div id="quick-actions-dropdown" class="absolute right-0 mt-2 w-64 bg-white rounded-lg shadow-xl border border-gray-200 hidden z-50">
                <div class="p-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800">Quick Actions</h3>
                </div>
                <div class="py-2">
                    <a href="../modules/housekeeping/room-status.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-clipboard-list mr-3"></i>
                        Room Status
                    </a>
                    <a href="../modules/housekeeping/tasks.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-tasks mr-3"></i>
                        My Tasks
                    </a>
                    <a href="../modules/housekeeping/maintenance.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-tools mr-3"></i>
                        Maintenance
                    </a>
                    <a href="../modules/housekeeping/inventory.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-boxes mr-3"></i>
                        Inventory
                    </a>
                </div>
            </div>
        </div>
        
        <!-- User Menu -->
        <div class="relative">
            <button id="user-menu-toggle" class="flex items-center space-x-2 text-white hover:text-gray-200 transition-colors">
                <div class="w-8 h-8 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                    <i class="fas fa-user text-sm"></i>
                </div>
                <span class="hidden md:block"><?php echo htmlspecialchars($user_name); ?></span>
                <i class="fas fa-chevron-down text-sm"></i>
            </button>
            
            <!-- User dropdown menu -->
            <div id="user-dropdown" class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-xl border border-gray-200 hidden z-50">
                <div class="p-4 border-b border-gray-200">
                    <div class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($user_name); ?></div>
                    <div class="text-sm text-gray-500">Housekeeping Staff</div>
                </div>
                <div class="py-2">
                    <a href="../../profile.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-user-circle mr-3"></i>
                        Profile
                    </a>
                    <a href="../modules/housekeeping/settings.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-cog mr-3"></i>
                        Settings
                    </a>
                    <a href="../modules/housekeeping/activity-log.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-history mr-3"></i>
                        Activity Log
                    </a>
                    <hr class="my-2">
                    <a href="logout.php" class="flex items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                        <i class="fas fa-sign-out-alt mr-3"></i>
                        Logout
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Role Badge -->
        <span class="bg-white bg-opacity-20 px-3 py-1 rounded-full text-sm font-medium hidden lg:block">
            <i class="fas fa-broom mr-1"></i>Housekeeping
        </span>
    </div>
</header>
