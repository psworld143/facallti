<?php
session_start();

// Check if user is logged in to POS
if (!isset($_SESSION['pos_user_id'])) {
    header('Location: ../login.php');
    exit();
}

$page_title = 'Restaurant Reports';
$user_role = $_SESSION['pos_user_role'];
$user_name = $_SESSION['pos_user_name'];
$is_demo_mode = isset($_SESSION['pos_demo_mode']) && $_SESSION['pos_demo_mode'];

// Include POS functions
require_once '../includes/pos-functions.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Hotel POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="../assets/js/pos-sidebar.js"></script>
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
        <?php include '../includes/pos-header.php'; ?>
        <?php include '../includes/pos-sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="flex-1 lg:ml-64">
            <div class="main-content p-6">
                <!-- Page Header -->
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-gray-800 mb-2">Restaurant Reports</h1>
                    <p class="text-gray-600">Comprehensive analytics and reporting for restaurant operations</p>
                </div>

                <!-- Report Filters -->
                <div class="bg-white rounded-lg shadow p-4 mb-6">
                    <div class="flex flex-wrap items-center gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Date Range</label>
                            <select class="border border-gray-300 rounded-lg px-3 py-2">
                                <option>Today</option>
                                <option>Yesterday</option>
                                <option>Last 7 Days</option>
                                <option>Last 30 Days</option>
                                <option>This Month</option>
                                <option>Custom Range</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                            <select class="border border-gray-300 rounded-lg px-3 py-2">
                                <option>All Categories</option>
                                <option>Appetizers</option>
                                <option>Main Courses</option>
                                <option>Desserts</option>
                                <option>Beverages</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Server</label>
                            <select class="border border-gray-300 rounded-lg px-3 py-2">
                                <option>All Servers</option>
                                <option>John Smith</option>
                                <option>Sarah Johnson</option>
                                <option>Mike Davis</option>
                            </select>
                        </div>
                        <div class="mt-6">
                            <button class="bg-primary text-white px-6 py-2 rounded-lg hover:bg-primary-dark transition-colors">
                                <i class="fas fa-search mr-2"></i>Generate Report
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Key Metrics -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-primary">
                        <div class="flex items-center">
                            <div class="p-2 bg-primary bg-opacity-10 rounded-lg">
                                <i class="fas fa-dollar-sign text-primary text-lg"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-gray-900">Total Revenue</h3>
                                <p class="text-2xl font-bold text-primary">$12,450</p>
                                <p class="text-xs text-green-600">+15% vs last period</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-success">
                        <div class="flex items-center">
                            <div class="p-2 bg-success bg-opacity-10 rounded-lg">
                                <i class="fas fa-utensils text-success text-lg"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-gray-900">Orders Served</h3>
                                <p class="text-2xl font-bold text-success">156</p>
                                <p class="text-xs text-green-600">+8% vs last period</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-warning">
                        <div class="flex items-center">
                            <div class="p-2 bg-warning bg-opacity-10 rounded-lg">
                                <i class="fas fa-users text-warning text-lg"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-gray-900">Total Guests</h3>
                                <p class="text-2xl font-bold text-warning">342</p>
                                <p class="text-xs text-green-600">+12% vs last period</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-info">
                        <div class="flex items-center">
                            <div class="p-2 bg-info bg-opacity-10 rounded-lg">
                                <i class="fas fa-chart-line text-info text-lg"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-gray-900">Avg. Order Value</h3>
                                <p class="text-2xl font-bold text-info">$79.81</p>
                                <p class="text-xs text-green-600">+5% vs last period</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detailed Reports -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Top Selling Items -->
                    <div class="bg-white rounded-lg shadow">
                        <div class="p-4 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-800">Top Selling Items</h3>
                        </div>
                        <div class="p-4">
                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <span class="w-6 h-6 bg-primary text-white text-xs rounded-full flex items-center justify-center mr-3">1</span>
                                        <span class="text-sm font-medium text-gray-900">Grilled Salmon</span>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-sm font-medium text-gray-900">45 orders</div>
                                        <div class="text-xs text-gray-500">$1,124.55</div>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <span class="w-6 h-6 bg-secondary text-white text-xs rounded-full flex items-center justify-center mr-3">2</span>
                                        <span class="text-sm font-medium text-gray-900">Caesar Salad</span>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-sm font-medium text-gray-900">38 orders</div>
                                        <div class="text-xs text-gray-500">$493.62</div>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <span class="w-6 h-6 bg-warning text-white text-xs rounded-full flex items-center justify-center mr-3">3</span>
                                        <span class="text-sm font-medium text-gray-900">Chicken Pasta</span>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-sm font-medium text-gray-900">32 orders</div>
                                        <div class="text-xs text-gray-500">$912.00</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Revenue by Category -->
                    <div class="bg-white rounded-lg shadow">
                        <div class="p-4 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-800">Revenue by Category</h3>
                        </div>
                        <div class="p-4">
                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium text-gray-900">Main Courses</span>
                                    <div class="flex items-center">
                                        <div class="w-32 bg-gray-200 rounded-full h-2 mr-3">
                                            <div class="bg-primary h-2 rounded-full" style="width: 65%"></div>
                                        </div>
                                        <span class="text-sm text-gray-900">$8,092.50</span>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium text-gray-900">Appetizers</span>
                                    <div class="flex items-center">
                                        <div class="w-32 bg-gray-200 rounded-full h-2 mr-3">
                                            <div class="bg-success h-2 rounded-full" style="width: 45%"></div>
                                        </div>
                                        <span class="text-sm text-gray-900">$5,602.50</span>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium text-gray-900">Beverages</span>
                                    <div class="flex items-center">
                                        <div class="w-32 bg-gray-200 rounded-full h-2 mr-3">
                                            <div class="bg-warning h-2 rounded-full" style="width: 30%"></div>
                                        </div>
                                        <span class="text-sm text-gray-900">$3,735.00</span>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium text-gray-900">Desserts</span>
                                    <div class="flex items-center">
                                        <div class="w-32 bg-gray-200 rounded-full h-2 mr-3">
                                            <div class="bg-info h-2 rounded-full" style="width: 20%"></div>
                                        </div>
                                        <span class="text-sm text-gray-900">$2,490.00</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Export Options -->
                <div class="bg-white rounded-lg shadow mt-6">
                    <div class="p-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800">Export Reports</h3>
                    </div>
                    <div class="p-4">
                        <div class="flex flex-wrap gap-3">
                            <button class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors">
                                <i class="fas fa-file-excel mr-2"></i>Export to Excel
                            </button>
                            <button class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors">
                                <i class="fas fa-file-pdf mr-2"></i>Export to PDF
                            </button>
                            <button class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                                <i class="fas fa-envelope mr-2"></i>Email Report
                            </button>
                            <button class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition-colors">
                                <i class="fas fa-print mr-2"></i>Print Report
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Report functionality
        function generateReport() {
            console.log('Generating report...');
        }

        function exportReport(format) {
            console.log('Exporting report to:', format);
        }
    </script>
</body>
</html>
