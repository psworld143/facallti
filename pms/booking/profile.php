<?php
session_start();
require_once '../../includes/error_handler.php';
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];

// Set page title
$page_title = 'User Profile';

// Include unified navigation (automatically selects based on user role)
include 'includes/header-unified.php';
include 'includes/sidebar-unified.php';
?>

<!-- Main Content -->
<main class="ml-0 lg:ml-64 mt-16 p-6 flex-1">
    <!-- Page Header -->
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-semibold text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>
            <p class="text-gray-600 mt-2">Manage your account settings and preferences</p>
        </div>
        <div class="text-right">
            <div id="current-date" class="text-sm text-gray-600"></div>
            <div id="current-time" class="text-sm text-gray-600"></div>
        </div>
    </div>

    <!-- Profile Content -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Profile Information -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-6">Profile Information</h2>
                
                <div class="space-y-6">
                    <div class="flex items-center">
                        <div class="w-16 h-16 bg-gradient-to-r from-blue-600 to-purple-600 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-user text-white text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-medium text-gray-800"><?php echo htmlspecialchars($user_name); ?></h3>
                            <p class="text-gray-600"><?php echo ucfirst(str_replace('_', ' ', $user_role)); ?></p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
                            <input type="text" value="<?php echo htmlspecialchars($user_name); ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50" readonly>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">User Role</label>
                            <input type="text" value="<?php echo ucfirst(str_replace('_', ' ', $user_role)); ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50" readonly>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">User ID</label>
                            <input type="text" value="<?php echo $user_id; ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50" readonly>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Account Status</label>
                            <input type="text" value="Active" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md bg-green-50 text-green-700" readonly>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="space-y-6">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Quick Actions</h3>
                <div class="space-y-3">
                    <a href="index.php" class="flex items-center p-3 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition-colors">
                        <i class="fas fa-tachometer-alt text-blue-600 mr-3"></i>
                        <span class="text-blue-800 font-medium">Dashboard</span>
                    </a>
                    <a href="role-demo.php" class="flex items-center p-3 bg-green-50 border border-green-200 rounded-lg hover:bg-green-100 transition-colors">
                        <i class="fas fa-eye text-green-600 mr-3"></i>
                        <span class="text-green-800 font-medium">View Role Demo</span>
                    </a>
                    <a href="test-logout.php" class="flex items-center p-3 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition-colors">
                        <i class="fas fa-sign-out-alt text-red-600 mr-3"></i>
                        <span class="text-red-800 font-medium">Test Logout</span>
                    </a>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">System Information</h3>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">PHP Version:</span>
                        <span class="text-gray-800"><?php echo PHP_VERSION; ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Database:</span>
                        <span class="text-gray-800">MySQL</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">System:</span>
                        <span class="text-gray-800">Hotel PMS Training</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Last Login:</span>
                        <span class="text-gray-800"><?php echo date('M d, Y H:i'); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
