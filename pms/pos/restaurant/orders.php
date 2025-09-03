<?php
session_start();

// Check if user is logged in to POS
if (!isset($_SESSION['pos_user_id'])) {
    header('Location: ../login.php');
    exit();
}

$page_title = 'Restaurant Orders';
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
                    <h1 class="text-2xl font-bold text-gray-800 mb-2">Restaurant Orders</h1>
                    <p class="text-gray-600">Manage active restaurant orders and table status</p>
                </div>

                <!-- Order Statistics -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-primary">
                        <div class="flex items-center">
                            <div class="p-2 bg-primary bg-opacity-10 rounded-lg">
                                <i class="fas fa-clock text-primary text-lg"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-gray-900">Pending</h3>
                                <p class="text-2xl font-bold text-primary">8</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-warning">
                        <div class="flex items-center">
                            <div class="p-2 bg-warning bg-opacity-10 rounded-lg">
                                <i class="fas fa-fire text-warning text-lg"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-gray-900">In Progress</h3>
                                <p class="text-2xl font-bold text-warning">12</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-success">
                        <div class="flex items-center">
                            <div class="p-2 bg-success bg-opacity-10 rounded-lg">
                                <i class="fas fa-check text-success text-lg"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-gray-900">Ready</h3>
                                <p class="text-2xl font-bold text-success">5</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-info">
                        <div class="flex items-center">
                            <div class="p-2 bg-info bg-opacity-10 rounded-lg">
                                <i class="fas fa-utensils text-info text-lg"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-gray-900">Total Today</h3>
                                <p class="text-2xl font-bold text-info">25</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Active Orders Table -->
                <div class="bg-white rounded-lg shadow">
                    <div class="p-4 border-b border-gray-200 flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-gray-800">Active Orders</h3>
                        <button class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary-dark transition-colors">
                            <i class="fas fa-plus mr-2"></i>New Order
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order #</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Table</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Items</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">#R001</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Table 5</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Grilled Salmon, Caesar Salad</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">$37.98</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-warning bg-opacity-20 text-warning-800">
                                            In Progress
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">15 min ago</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button class="text-blue-600 hover:text-blue-900 mr-3">View</button>
                                        <button class="text-green-600 hover:text-green-900 mr-3">Ready</button>
                                        <button class="text-red-600 hover:text-red-900">Cancel</button>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">#R002</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Table 8</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Chicken Pasta, Garlic Bread</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">$28.50</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-primary bg-opacity-20 text-primary-800">
                                            Pending
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">8 min ago</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button class="text-blue-600 hover:text-blue-900 mr-3">View</button>
                                        <button class="text-yellow-600 hover:text-yellow-900 mr-3">Start</button>
                                        <button class="text-red-600 hover:text-red-900">Cancel</button>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">#R003</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Table 3</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Beef Steak, Mashed Potatoes</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">$42.99</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-success bg-opacity-20 text-success-800">
                                            Ready
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">22 min ago</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button class="text-blue-600 hover:text-blue-900 mr-3">View</button>
                                        <button class="text-green-600 hover:text-green-900">Serve</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Order management functionality
        function viewOrder(orderId) {
            console.log('Viewing order:', orderId);
        }

        function updateOrderStatus(orderId, status) {
            console.log('Updating order status:', orderId, status);
        }

        function cancelOrder(orderId) {
            console.log('Cancelling order:', orderId);
        }
    </script>
</body>
</html>
