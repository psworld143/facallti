<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has housekeeping access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['housekeeping', 'manager'])) {
    header('Location: ../../login.php');
    exit();
}

// Get housekeeping statistics
$stats = getHousekeepingStats();

// Set page title
$page_title = 'Housekeeping Dashboard';

// Include unified navigation (automatically selects based on user role)
include '../../includes/header-unified.php';
include '../../includes/sidebar-unified.php';
?>

        <!-- Main Content -->
        <main class="ml-64 mt-16 p-6 flex-1">
            <div class="flex justify-between items-center mb-8">
                <h2 class="text-3xl font-semibold text-gray-800">Housekeeping Dashboard</h2>
                <div class="text-right">
                    <div id="current-date" class="text-sm text-gray-600"></div>
                    <div id="current-time" class="text-sm text-gray-600"></div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg p-6 shadow-md hover:shadow-lg transition-shadow">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-gradient-to-r from-green-400 to-green-600 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-check-circle text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-3xl font-bold text-gray-800"><?php echo $stats['clean_rooms']; ?></h3>
                            <p class="text-gray-600">Clean Rooms</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg p-6 shadow-md hover:shadow-lg transition-shadow">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-gradient-to-r from-red-400 to-red-600 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-times-circle text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-3xl font-bold text-gray-800"><?php echo $stats['dirty_rooms']; ?></h3>
                            <p class="text-gray-600">Dirty Rooms</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg p-6 shadow-md hover:shadow-lg transition-shadow">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-gradient-to-r from-yellow-400 to-yellow-600 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-clock text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-3xl font-bold text-gray-800"><?php echo $stats['pending_tasks']; ?></h3>
                            <p class="text-gray-600">Pending Tasks</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg p-6 shadow-md hover:shadow-lg transition-shadow">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-gradient-to-r from-blue-400 to-blue-600 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-tools text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-3xl font-bold text-gray-800"><?php echo $stats['maintenance_requests']; ?></h3>
                            <p class="text-gray-600">Maintenance Requests</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white rounded-lg p-6 shadow-md mb-8">
                <h3 class="text-xl font-semibold text-gray-800 mb-6">Quick Actions</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <a href="room-status.php" class="flex items-center p-4 bg-blue-50 border-2 border-blue-200 rounded-lg hover:bg-blue-100 hover:border-blue-300 transition-all duration-300">
                        <i class="fas fa-bed text-blue-600 text-xl mr-3"></i>
                        <span class="font-medium text-blue-800">Update Room Status</span>
                    </a>
                    <a href="tasks.php" class="flex items-center p-4 bg-green-50 border-2 border-green-200 rounded-lg hover:bg-green-100 hover:border-green-300 transition-all duration-300">
                        <i class="fas fa-tasks text-green-600 text-xl mr-3"></i>
                        <span class="font-medium text-green-800">View Tasks</span>
                    </a>
                    <a href="maintenance.php" class="flex items-center p-4 bg-orange-50 border-2 border-orange-200 rounded-lg hover:bg-orange-100 hover:border-orange-300 transition-all duration-300">
                        <i class="fas fa-tools text-orange-600 text-xl mr-3"></i>
                        <span class="font-medium text-orange-800">Maintenance</span>
                    </a>
                    <a href="inventory.php" class="flex items-center p-4 bg-purple-50 border-2 border-purple-200 rounded-lg hover:bg-purple-100 hover:border-purple-300 transition-all duration-300">
                        <i class="fas fa-boxes text-purple-600 text-xl mr-3"></i>
                        <span class="font-medium text-purple-800">Inventory</span>
                    </a>
                </div>
            </div>

            <!-- Room Status Overview -->
            <div class="bg-white rounded-lg p-6 shadow-md mb-8">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-semibold text-gray-800">Room Status Overview</h3>
                    <a href="room-status.php" class="text-primary hover:text-primary-dark">View All</a>
                </div>
                <div id="room-status-overview" class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Room status will be loaded here -->
                </div>
            </div>

            <!-- Recent Tasks -->
            <div class="bg-white rounded-lg p-6 shadow-md">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-semibold text-gray-800">Recent Tasks</h3>
                    <a href="tasks.php" class="text-primary hover:text-primary-dark">View All</a>
                </div>
                <div id="recent-tasks" class="overflow-x-auto">
                    <!-- Recent tasks will be loaded here -->
                </div>
            </div>
        </main>
    </div>

    <script src="../../assets/js/main.js"></script>
    <script src="../../assets/js/housekeeping.js"></script>
    
    <?php include '../../includes/footer.php'; ?>
