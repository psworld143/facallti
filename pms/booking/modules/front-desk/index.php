<?php
session_start();
require_once '../../../includes/error_handler.php';
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has front desk access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['front_desk', 'manager'])) {
    header('Location: ../../login.php');
    exit();
}

// Get front desk statistics
$stats = getFrontDeskStats();

// Set page title
$page_title = 'Front Desk Dashboard';

// Include unified navigation (automatically selects based on user role)
include '../../includes/header-unified.php';
include '../../includes/sidebar-unified.php';
?>

        <!-- Main Content -->
        <main class="ml-64 mt-16 p-6 flex-1">
            <div class="flex justify-between items-center mb-8">
                <h2 class="text-3xl font-semibold text-gray-800">Front Desk Dashboard</h2>
                <div class="text-right">
                    <div id="current-date" class="text-sm text-gray-600"></div>
                    <div id="current-time" class="text-sm text-gray-600"></div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg p-6 shadow-md hover:shadow-lg transition-shadow">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-gradient-to-r from-blue-400 to-blue-600 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-calendar-check text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-3xl font-bold text-gray-800"><?php echo $stats['today_checkins']; ?></h3>
                            <p class="text-gray-600">Today's Check-ins</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg p-6 shadow-md hover:shadow-lg transition-shadow">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-gradient-to-r from-green-400 to-green-600 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-sign-out-alt text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-3xl font-bold text-gray-800"><?php echo $stats['today_checkouts']; ?></h3>
                            <p class="text-gray-600">Today's Check-outs</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg p-6 shadow-md hover:shadow-lg transition-shadow">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-gradient-to-r from-purple-400 to-purple-600 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-user-clock text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-3xl font-bold text-gray-800"><?php echo $stats['pending_reservations']; ?></h3>
                            <p class="text-gray-600">Pending Reservations</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg p-6 shadow-md hover:shadow-lg transition-shadow">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-gradient-to-r from-orange-400 to-orange-600 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-exclamation-triangle text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-3xl font-bold text-gray-800"><?php echo $stats['overbookings']; ?></h3>
                            <p class="text-gray-600">Overbookings</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white rounded-lg p-6 shadow-md mb-8">
                <h3 class="text-xl font-semibold text-gray-800 mb-6">Quick Actions</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <a href="new-reservation.php" class="flex items-center p-4 bg-blue-50 border-2 border-blue-200 rounded-lg hover:bg-blue-100 hover:border-blue-300 transition-all duration-300">
                        <i class="fas fa-plus text-blue-600 text-xl mr-3"></i>
                        <span class="font-medium text-blue-800">New Reservation</span>
                    </a>
                    <a href="manage-reservations.php" class="flex items-center p-4 bg-indigo-50 border-2 border-indigo-200 rounded-lg hover:bg-indigo-100 hover:border-indigo-300 transition-all duration-300">
                        <i class="fas fa-calendar-alt text-indigo-600 text-xl mr-3"></i>
                        <span class="font-medium text-indigo-800">Manage Reservations</span>
                    </a>
                    <a href="check-in.php" class="flex items-center p-4 bg-green-50 border-2 border-green-200 rounded-lg hover:bg-green-100 hover:border-green-300 transition-all duration-300">
                        <i class="fas fa-sign-in-alt text-green-600 text-xl mr-3"></i>
                        <span class="font-medium text-green-800">Check In Guest</span>
                    </a>
                    <a href="check-out.php" class="flex items-center p-4 bg-red-50 border-2 border-red-200 rounded-lg hover:bg-red-100 hover:border-red-300 transition-all duration-300">
                        <i class="fas fa-sign-out-alt text-red-600 text-xl mr-3"></i>
                        <span class="font-medium text-red-800">Check Out Guest</span>
                    </a>
                    <a href="room-status.php" class="flex items-center p-4 bg-purple-50 border-2 border-purple-200 rounded-lg hover:bg-purple-100 hover:border-purple-300 transition-all duration-300">
                        <i class="fas fa-bed text-purple-600 text-xl mr-3"></i>
                        <span class="font-medium text-purple-800">View Room Status</span>
                    </a>
                    <a href="guest-management.php" class="flex items-center p-4 bg-yellow-50 border-2 border-yellow-200 rounded-lg hover:bg-yellow-100 hover:border-yellow-300 transition-all duration-300">
                        <i class="fas fa-users text-yellow-600 text-xl mr-3"></i>
                        <span class="font-medium text-yellow-800">Guest Management</span>
                    </a>
                    <a href="service-management.php" class="flex items-center p-4 bg-indigo-50 border-2 border-indigo-200 rounded-lg hover:bg-indigo-100 hover:border-indigo-300 transition-all duration-300">
                        <i class="fas fa-concierge-bell text-indigo-600 text-xl mr-3"></i>
                        <span class="font-medium text-indigo-800">Service Management</span>
                    </a>
                    <a href="billing-payment.php" class="flex items-center p-4 bg-emerald-50 border-2 border-emerald-200 rounded-lg hover:bg-emerald-100 hover:border-emerald-300 transition-all duration-300">
                        <i class="fas fa-file-invoice-dollar text-emerald-600 text-xl mr-3"></i>
                        <span class="font-medium text-emerald-800">Billing & Payment</span>
                    </a>
                </div>
            </div>

            <!-- Recent Reservations -->
            <div class="bg-white rounded-lg p-6 shadow-md mb-8">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-semibold text-gray-800">Recent Reservations</h3>
                    <a href="reservations.php" class="text-primary hover:text-primary-dark">View All</a>
                </div>
                <div id="recent-reservations" class="overflow-x-auto">
                    <!-- Recent reservations will be loaded via AJAX -->
                </div>
            </div>

            <!-- Today's Schedule -->
            <div class="bg-white rounded-lg p-6 shadow-md">
                <h3 class="text-xl font-semibold text-gray-800 mb-6">Today's Schedule</h3>
                <div id="today-schedule" class="space-y-4">
                    <!-- Today's schedule will be loaded via AJAX -->
                </div>
            </div>
        </main>
    </div>

    <script src="../../assets/js/main.js"></script>
    <script src="../../assets/js/front-desk.js"></script>
    
    <?php include '../../includes/footer.php'; ?>
