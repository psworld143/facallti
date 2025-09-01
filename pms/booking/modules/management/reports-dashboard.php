<?php
session_start();
require_once '../../../includes/error_handler.php';
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has manager access
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
    header('Location: ../../login.php');
    exit();
}

// Get management statistics
$management_stats = getManagementStatistics();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Management & Reports - Hotel PMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3B82F6',
                        'primary-dark': '#2563EB',
                        secondary: '#6B7280',
                        success: '#10B981',
                        warning: '#F59E0B',
                        danger: '#EF4444'
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <header class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center">
                    <a href="../index.php" class="text-gray-400 hover:text-gray-600 mr-4">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <h1 class="text-xl font-semibold text-gray-900">Management & Reports</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-500"><?php echo date('M d, Y H:i'); ?></span>
                    <a href="../../logout.php" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Key Performance Indicators -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-bed text-blue-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Occupancy Rate</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($management_stats['occupancy_rate'], 1); ?>%</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-dollar-sign text-green-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Monthly Revenue</p>
                        <p class="text-2xl font-semibold text-gray-900">₱<?php echo number_format($management_stats['monthly_revenue'], 2); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-users text-purple-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Guests</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($management_stats['total_guests']); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-yellow-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-boxes text-yellow-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Low Stock Items</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($management_stats['low_stock_items']); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white rounded-lg shadow-sm border p-6 mb-8">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Quick Actions</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <button onclick="generateReport('occupancy')" class="flex items-center p-4 bg-blue-50 border-2 border-blue-200 rounded-lg hover:bg-blue-100 hover:border-blue-300 transition-all duration-300">
                    <i class="fas fa-chart-line text-blue-600 text-xl mr-3"></i>
                    <span class="font-medium text-blue-800">Occupancy Report</span>
                </button>
                <button onclick="generateReport('revenue')" class="flex items-center p-4 bg-green-50 border-2 border-green-200 rounded-lg hover:bg-green-100 hover:border-green-300 transition-all duration-300">
                    <i class="fas fa-chart-bar text-green-600 text-xl mr-3"></i>
                    <span class="font-medium text-green-800">Revenue Report</span>
                </button>
                <button onclick="generateReport('demographics')" class="flex items-center p-4 bg-purple-50 border-2 border-purple-200 rounded-lg hover:bg-purple-100 hover:border-purple-300 transition-all duration-300">
                    <i class="fas fa-chart-pie text-purple-600 text-xl mr-3"></i>
                    <span class="font-medium text-purple-800">Guest Demographics</span>
                </button>
                <button onclick="openInventoryModal()" class="flex items-center p-4 bg-yellow-50 border-2 border-yellow-200 rounded-lg hover:bg-yellow-100 hover:border-yellow-300 transition-all duration-300">
                    <i class="fas fa-boxes text-yellow-600 text-xl mr-3"></i>
                    <span class="font-medium text-yellow-800">Inventory Management</span>
                </button>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Occupancy Chart -->
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Occupancy Rate Trend</h3>
                <canvas id="occupancyChart" width="400" height="200"></canvas>
            </div>
            
            <!-- Revenue Chart -->
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Revenue Trend</h3>
                <canvas id="revenueChart" width="400" height="200"></canvas>
            </div>
        </div>

        <!-- Reports Section -->
        <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex space-x-8 px-6">
                    <button onclick="switchReportTab('daily')" id="tab-daily" class="tab-button active py-4 px-1 border-b-2 border-primary font-medium text-sm text-primary">
                        <i class="fas fa-calendar-day mr-2"></i>Daily Reports
                    </button>
                    <button onclick="switchReportTab('weekly')" id="tab-weekly" class="tab-button py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        <i class="fas fa-calendar-week mr-2"></i>Weekly Reports
                    </button>
                    <button onclick="switchReportTab('monthly')" id="tab-monthly" class="tab-button py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        <i class="fas fa-calendar-alt mr-2"></i>Monthly Reports
                    </button>
                    <button onclick="switchReportTab('inventory')" id="tab-inventory" class="tab-button py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        <i class="fas fa-boxes mr-2"></i>Inventory Reports
                    </button>
                </nav>
            </div>
            
            <!-- Daily Reports Tab -->
            <div id="tab-content-daily" class="tab-content active p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-medium text-gray-900">Daily Reports</h3>
                    <div class="flex space-x-2">
                        <input type="date" id="daily-date-filter" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary">
                        <button onclick="exportReport('daily')" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">
                            <i class="fas fa-download mr-2"></i>Export
                        </button>
                    </div>
                </div>
                
                <div id="daily-reports-container">
                    <!-- Daily reports will be loaded here -->
                </div>
            </div>
            
            <!-- Weekly Reports Tab -->
            <div id="tab-content-weekly" class="tab-content hidden p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-medium text-gray-900">Weekly Reports</h3>
                    <div class="flex space-x-2">
                        <input type="week" id="weekly-date-filter" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary">
                        <button onclick="exportReport('weekly')" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">
                            <i class="fas fa-download mr-2"></i>Export
                        </button>
                    </div>
                </div>
                
                <div id="weekly-reports-container">
                    <!-- Weekly reports will be loaded here -->
                </div>
            </div>
            
            <!-- Monthly Reports Tab -->
            <div id="tab-content-monthly" class="tab-content hidden p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-medium text-gray-900">Monthly Reports</h3>
                    <div class="flex space-x-2">
                        <input type="month" id="monthly-date-filter" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary">
                        <button onclick="exportReport('monthly')" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">
                            <i class="fas fa-download mr-2"></i>Export
                        </button>
                    </div>
                </div>
                
                <div id="monthly-reports-container">
                    <!-- Monthly reports will be loaded here -->
                </div>
            </div>
            
            <!-- Inventory Reports Tab -->
            <div id="tab-content-inventory" class="tab-content hidden p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-medium text-gray-900">Inventory Reports</h3>
                    <div class="flex space-x-2">
                        <select id="inventory-category-filter" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary">
                            <option value="">All Categories</option>
                            <option value="minibar">Minibar</option>
                            <option value="housekeeping">Housekeeping</option>
                            <option value="amenities">Amenities</option>
                            <option value="linens">Linens</option>
                        </select>
                        <button onclick="exportReport('inventory')" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">
                            <i class="fas fa-download mr-2"></i>Export
                        </button>
                    </div>
                </div>
                
                <div id="inventory-reports-container">
                    <!-- Inventory reports will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Inventory Management Modal -->
    <div id="inventory-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-8 max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold text-gray-900">Inventory Management</h3>
                <button onclick="closeInventoryModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <!-- Inventory Tabs -->
            <div class="border-b border-gray-200 mb-6">
                <nav class="-mb-px flex space-x-8">
                    <button onclick="switchInventoryTab('items')" id="inventory-tab-items" class="inventory-tab-button active py-2 px-1 border-b-2 border-primary font-medium text-sm text-primary">
                        <i class="fas fa-box mr-2"></i>Inventory Items
                    </button>
                    <button onclick="switchInventoryTab('categories')" id="inventory-tab-categories" class="inventory-tab-button py-2 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        <i class="fas fa-tags mr-2"></i>Categories
                    </button>
                    <button onclick="switchInventoryTab('transactions')" id="inventory-tab-transactions" class="inventory-tab-button py-2 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        <i class="fas fa-exchange-alt mr-2"></i>Transactions
                    </button>
                </nav>
            </div>
            
            <!-- Inventory Items Tab -->
            <div id="inventory-content-items" class="inventory-content active">
                <div class="flex justify-between items-center mb-4">
                    <h4 class="text-md font-medium text-gray-900">Inventory Items</h4>
                    <button onclick="openAddItemModal()" class="px-4 py-2 bg-primary text-white rounded-md hover:bg-primary-dark transition-colors">
                        <i class="fas fa-plus mr-2"></i>Add Item
                    </button>
                </div>
                
                <div id="inventory-items-container">
                    <!-- Inventory items will be loaded here -->
                </div>
            </div>
            
            <!-- Categories Tab -->
            <div id="inventory-content-categories" class="inventory-content hidden">
                <div class="flex justify-between items-center mb-4">
                    <h4 class="text-md font-medium text-gray-900">Categories</h4>
                    <button onclick="openAddCategoryModal()" class="px-4 py-2 bg-primary text-white rounded-md hover:bg-primary-dark transition-colors">
                        <i class="fas fa-plus mr-2"></i>Add Category
                    </button>
                </div>
                
                <div id="inventory-categories-container">
                    <!-- Categories will be loaded here -->
                </div>
            </div>
            
            <!-- Transactions Tab -->
            <div id="inventory-content-transactions" class="inventory-content hidden">
                <div class="flex justify-between items-center mb-4">
                    <h4 class="text-md font-medium text-gray-900">Transactions</h4>
                    <button onclick="openAddTransactionModal()" class="px-4 py-2 bg-primary text-white rounded-md hover:bg-primary-dark transition-colors">
                        <i class="fas fa-plus mr-2"></i>Add Transaction
                    </button>
                </div>
                
                <div id="inventory-transactions-container">
                    <!-- Transactions will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Add Item Modal -->
    <div id="add-item-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-8 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold text-gray-900">Add Inventory Item</h3>
                <button onclick="closeAddItemModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="add-item-form" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Item Name *</label>
                        <input type="text" name="item_name" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Category *</label>
                        <select name="category_id" required 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                            <option value="">Select Category</option>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Current Stock *</label>
                        <input type="number" name="current_stock" min="0" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Minimum Stock *</label>
                        <input type="number" name="minimum_stock" min="0" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Unit Price</label>
                        <input type="number" name="unit_price" min="0" step="0.01" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="3" 
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary"></textarea>
                </div>

                <div class="flex justify-end space-x-4">
                    <button type="button" onclick="closeAddItemModal()" 
                            class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-6 py-2 bg-primary text-white rounded-md hover:bg-primary-dark transition-colors">
                        <i class="fas fa-save mr-2"></i>Add Item
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="../../assets/js/main.js"></script>
    <script src="../../assets/js/management-reports.js"></script>
</body>
</html>
