<?php
session_start();
require_once 'config/database.php';
require_once 'includes/pos-error-handler.php';

// Check if user is logged in to POS
if (!isset($_SESSION['pos_user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'includes/pos-functions.php';

// Get POS statistics
$pos_stats = getPOSStats();

// Get sample menu items for display (all categories)
$sample_menu_items = getMenuItems();

// Set page title
$page_title = 'POS Dashboard';

// Get user information for header
$user_role = $_SESSION['pos_user_role'] ?? 'pos_user';
$user_name = $_SESSION['pos_user_name'] ?? 'POS User';
$is_demo_mode = isset($_SESSION['pos_demo_mode']) && $_SESSION['pos_demo_mode'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Hotel POS System</title>
    <link rel="icon" type="image/png" href="../../../assets/images/seait-logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/pos-styles.css">
    <script src="assets/js/pos-sidebar.js"></script>
    <style>
        /* Sidebar mobile responsiveness */
        #sidebar {
            transition: transform 0.3s ease-in-out;
        }
        
        /* Mobile: sidebar starts hidden */
        @media (max-width: 1023px) {
            #sidebar {
                transform: translateX(-100%);
                z-index: 50;
            }
            #sidebar.sidebar-open {
                transform: translateX(0);
            }
        }
        
        /* Desktop: sidebar always visible */
        @media (min-width: 1024px) {
            #sidebar {
                transform: translateX(0) !important;
            }
        }
        
        #sidebar-overlay {
            transition: opacity 0.3s ease-in-out;
            z-index: 40;
        }
        
        /* Responsive layout fixes */
        .main-content {
            margin-left: 0;
            padding-top: 4rem;
        }
        
        @media (min-width: 1024px) {
            .main-content {
                margin-left: 16rem;
            }
        }

        /* Demo mode indicator */
        .demo-mode-indicator {
            background: linear-gradient(45deg, #ff6b6b, #feca57, #48dbfb, #ff9ff3);
            background-size: 400% 400%;
            animation: gradientShift 3s ease infinite;
        }
        
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#667eea',
                        secondary: '#764ba2',
                        success: '#28a745',
                        danger: '#dc3545',
                        warning: '#ffc107',
                        info: '#17a2b8'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50">
    <div class="flex min-h-screen">
        <!-- Sidebar Overlay for Mobile -->
        <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden lg:hidden" onclick="closeSidebar()"></div>
        
        <!-- Include POS-specific header and sidebar -->
        <?php include 'includes/pos-header.php'; ?>
        <?php include 'includes/pos-sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content p-4 lg:p-6 flex-1 transition-all duration-300">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 lg:mb-8 gap-4">
                <h2 class="text-2xl lg:text-3xl font-semibold text-gray-800">Point of Sale Dashboard</h2>
                <div class="text-right">
                    <div id="current-date" class="text-sm text-gray-600"></div>
                    <div id="current-time" class="text-sm text-gray-600"></div>
                </div>
            </div>

            <!-- POS Statistics Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6 mb-8">
                <div class="bg-white rounded-lg p-6 shadow-md hover:shadow-lg transition-shadow">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-gradient-to-r from-primary to-secondary rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-cash-register text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-3xl font-bold text-gray-800">₱<?php echo number_format($pos_stats['today_sales'], 2); ?></h3>
                            <p class="text-gray-600">Today's Sales</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg p-6 shadow-md hover:shadow-lg transition-shadow">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-gradient-to-r from-pink-400 to-red-400 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-receipt text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-3xl font-bold text-gray-800"><?php echo $pos_stats['today_transactions']; ?></h3>
                            <p class="text-gray-600">Transactions</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg p-6 shadow-md hover:shadow-lg transition-shadow">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-gradient-to-r from-blue-400 to-cyan-400 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-users text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-3xl font-bold text-gray-800"><?php echo $pos_stats['active_orders']; ?></h3>
                            <p class="text-gray-600">Active Orders</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg p-6 shadow-md hover:shadow-lg transition-shadow">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-gradient-to-r from-green-400 to-teal-400 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-chart-line text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-3xl font-bold text-gray-800">₱<?php echo number_format($pos_stats['monthly_revenue'], 2); ?></h3>
                            <p class="text-gray-600">Monthly Revenue</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- POS Service Categories -->
            <div class="bg-white rounded-lg p-6 shadow-md mb-8">
                <h3 class="text-xl font-semibold text-gray-800 mb-6">POS Services</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    
                    <!-- Restaurant POS -->
                    <a href="restaurant/" class="flex items-center p-4 bg-gray-50 border-2 border-gray-200 rounded-lg hover:bg-primary hover:border-primary hover:text-white transition-all duration-300">
                        <i class="fas fa-utensils text-xl mr-3"></i>
                        <span class="font-medium">Restaurant POS</span>
                    </a>

                    <!-- Room Service POS -->
                    <a href="room-service/" class="flex items-center p-4 bg-gray-50 border-2 border-gray-200 rounded-lg hover:bg-primary hover:border-primary hover:text-white transition-all duration-300">
                        <i class="fas fa-bed text-xl mr-3"></i>
                        <span class="font-medium">Room Service</span>
                    </a>

                    <!-- Spa & Wellness POS -->
                    <a href="spa/" class="flex items-center p-4 bg-gray-50 border-2 border-gray-200 rounded-lg hover:bg-primary hover:border-primary hover:text-white transition-all duration-300">
                        <i class="fas fa-spa text-xl mr-3"></i>
                        <span class="font-medium">Spa & Wellness</span>
                    </a>

                    <!-- Gift Shop POS -->
                    <a href="gift-shop/" class="flex items-center p-4 bg-gray-50 border-2 border-gray-200 rounded-lg hover:bg-primary hover:border-primary hover:text-white transition-all duration-300">
                        <i class="fas fa-gift text-xl mr-3"></i>
                        <span class="font-medium">Gift Shop</span>
                    </a>

                    <!-- Event Services POS -->
                    <a href="events/" class="flex items-center p-4 bg-gray-50 border-2 border-gray-200 rounded-lg hover:bg-primary hover:border-primary hover:text-white transition-all duration-300">
                        <i class="fas fa-calendar-alt text-xl mr-3"></i>
                        <span class="font-medium">Event Services</span>
                    </a>

                    <!-- Quick Sales POS -->
                    <a href="quick-sales/" class="flex items-center p-4 bg-gray-50 border-2 border-gray-200 rounded-lg hover:bg-primary hover:border-primary hover:text-white transition-all duration-300">
                        <i class="fas fa-bolt text-xl mr-3"></i>
                        <span class="font-medium">Quick Sales</span>
                    </a>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="bg-white rounded-lg p-6 shadow-md">
                <h3 class="text-xl font-semibold text-gray-800 mb-6">Recent Transactions</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transaction</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Service</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-gray-500">No recent transactions</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>

    <script>
        // POS Dashboard functionality
        function openPOSModule(module) {
            console.log('Opening POS module:', module);
        }
    </script>

    <?php include 'includes/pos-footer.php'; ?>
</div>
</body>
</html>
