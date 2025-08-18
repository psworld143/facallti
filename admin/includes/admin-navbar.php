<?php
// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}
?>

<!-- Admin Header -->
<header class="bg-white shadow-sm border-b fixed top-0 left-0 right-0 z-30">
    <div class="flex items-center justify-between px-4 py-3">
        <!-- Left side - Logo and Title -->
        <div class="flex items-center space-x-4">
            <div class="flex items-center space-x-3">
                <div class="w-8 h-8 bg-seait-orange rounded-lg flex items-center justify-center">
                    <i class="fas fa-shield-alt text-white text-sm"></i>
                </div>
                <div>
                    <h1 class="text-lg font-bold text-seait-dark">SEAIT Admin</h1>
                    <p class="text-xs text-gray-500">Administrative Panel</p>
                </div>
            </div>
        </div>

        <!-- Right side - User menu and notifications -->
        <div class="flex items-center space-x-4">
            <!-- Notifications -->
            <div class="relative">
                <button class="p-2 text-gray-600 hover:text-seait-orange transition-colors duration-200">
                    <i class="fas fa-bell text-lg"></i>
                    <span class="absolute -top-1 -right-1 w-3 h-3 bg-red-500 rounded-full text-xs text-white flex items-center justify-center">3</span>
                </button>
            </div>

            <!-- User Menu -->
            <div class="relative">
                <button id="user-menu-button" class="flex items-center space-x-2 p-2 rounded-lg hover:bg-gray-100 transition-colors duration-200">
                    <div class="w-8 h-8 bg-seait-orange rounded-full flex items-center justify-center">
                        <i class="fas fa-user text-white text-sm"></i>
                    </div>
                    <div class="hidden md:block text-left">
                        <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></p>
                        <p class="text-xs text-gray-500">Administrator</p>
                    </div>
                    <i class="fas fa-chevron-down text-gray-400 text-xs"></i>
                </button>

                <!-- Dropdown menu -->
                <div id="user-menu-dropdown" class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 hidden z-50">
                    <div class="py-1">
                        <a href="profile.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                            <i class="fas fa-user-circle mr-3"></i>
                            Profile
                        </a>
                        <a href="settings.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                            <i class="fas fa-cog mr-3"></i>
                            Settings
                        </a>
                        <hr class="my-1">
                        <a href="logout.php" class="flex items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                            <i class="fas fa-sign-out-alt mr-3"></i>
                            Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- Include the sidebar -->
<?php include 'admin-sidebar.php'; ?>

<!-- Main content wrapper -->
<div class="lg:ml-64 pt-16">
    <!-- Content will be inserted here by individual pages -->
</div>

<script>
// User menu dropdown functionality
document.addEventListener('DOMContentLoaded', function() {
    const userMenuButton = document.getElementById('user-menu-button');
    const userMenuDropdown = document.getElementById('user-menu-dropdown');

    if (userMenuButton && userMenuDropdown) {
        userMenuButton.addEventListener('click', function(e) {
            e.stopPropagation();
            userMenuDropdown.classList.toggle('hidden');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!userMenuButton.contains(e.target) && !userMenuDropdown.contains(e.target)) {
                userMenuDropdown.classList.add('hidden');
            }
        });
    }
});
</script>