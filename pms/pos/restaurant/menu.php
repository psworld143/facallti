<?php
session_start();

// Check if user is logged in to POS
if (!isset($_SESSION['pos_user_id'])) {
    header('Location: ../login.php');
    exit();
}

$page_title = 'Restaurant Menu Management';
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
                    <h1 class="text-2xl font-bold text-gray-800 mb-2">Restaurant Menu Management</h1>
                    <p class="text-gray-600">Manage restaurant menu items, categories, and pricing</p>
                </div>

                <!-- Quick Actions -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-primary">
                        <div class="flex items-center">
                            <div class="p-2 bg-primary bg-opacity-10 rounded-lg">
                                <i class="fas fa-plus text-primary text-lg"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-gray-900">Add New Item</h3>
                                <p class="text-xs text-gray-500">Create menu item</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-success">
                        <div class="flex items-center">
                            <div class="p-2 bg-success bg-opacity-10 rounded-lg">
                                <i class="fas fa-tags text-success text-lg"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-gray-900">Categories</h3>
                                <p class="text-xs text-gray-500">Manage categories</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-warning">
                        <div class="flex items-center">
                            <div class="p-2 bg-warning bg-opacity-10 rounded-lg">
                                <i class="fas fa-chart-line text-warning text-lg"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-gray-900">Popular Items</h3>
                                <p class="text-xs text-gray-500">View top sellers</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-info">
                        <div class="flex items-center">
                            <div class="p-2 bg-info bg-opacity-10 rounded-lg">
                                <i class="fas fa-print text-info text-lg"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-gray-900">Print Menu</h3>
                                <p class="text-xs text-gray-500">Generate menu PDF</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Menu Categories and Items -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Categories Panel -->
                    <div class="lg:col-span-1">
                        <div class="bg-white rounded-lg shadow">
                            <div class="p-4 border-b border-gray-200">
                                <h3 class="text-lg font-semibold text-gray-800">Menu Categories</h3>
                            </div>
                            <div class="p-4">
                                <div class="space-y-2">
                                    <button class="w-full text-left px-3 py-2 rounded-lg bg-primary text-white font-medium">
                                        <i class="fas fa-utensils mr-2"></i>All Items
                                    </button>
                                    <button class="w-full text-left px-3 py-2 rounded-lg text-gray-700 hover:bg-gray-100">
                                        <i class="fas fa-apple-alt mr-2"></i>Appetizers
                                    </button>
                                    <button class="w-full text-left px-3 py-2 rounded-lg text-gray-700 hover:bg-gray-100">
                                        <i class="fas fa-drumstick-bite mr-2"></i>Main Courses
                                    </button>
                                    <button class="w-full text-left px-3 py-2 rounded-lg text-gray-700 hover:bg-gray-100">
                                        <i class="fas fa-ice-cream mr-2"></i>Desserts
                                    </button>
                                    <button class="w-full text-left px-3 py-2 rounded-lg text-gray-700 hover:bg-gray-100">
                                        <i class="fas fa-coffee mr-2"></i>Beverages
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Menu Items Panel -->
                    <div class="lg:col-span-2">
                        <div class="bg-white rounded-lg shadow">
                            <div class="p-4 border-b border-gray-200 flex justify-between items-center">
                                <h3 class="text-lg font-semibold text-gray-800">Menu Items</h3>
                                <button class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary-dark transition-colors">
                                    <i class="fas fa-plus mr-2"></i>Add Item
                                </button>
                            </div>
                            <div class="p-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <!-- Sample Menu Items -->
                                    <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                                        <div class="flex justify-between items-start mb-2">
                                            <h4 class="font-medium text-gray-800">Grilled Salmon</h4>
                                            <span class="text-primary font-semibold">$24.99</span>
                                        </div>
                                        <p class="text-sm text-gray-600 mb-3">Fresh Atlantic salmon with herbs and lemon</p>
                                        <div class="flex justify-between items-center">
                                            <span class="text-xs text-gray-500">Main Course</span>
                                            <div class="flex space-x-2">
                                                <button class="text-blue-600 hover:text-blue-800">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="text-red-600 hover:text-red-800">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                                        <div class="flex justify-between items-start mb-2">
                                            <h4 class="font-medium text-gray-800">Caesar Salad</h4>
                                            <span class="text-primary font-semibold">$12.99</span>
                                        </div>
                                        <p class="text-sm text-gray-600 mb-3">Fresh romaine lettuce with Caesar dressing</p>
                                        <div class="flex justify-between items-center">
                                            <span class="text-xs text-gray-500">Appetizer</span>
                                            <div class="flex space-x-2">
                                                <button class="text-blue-600 hover:text-blue-800">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="text-red-600 hover:text-red-800">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                                        <div class="flex justify-between items-start mb-2">
                                            <h4 class="font-medium text-gray-800">Chocolate Cake</h4>
                                            <span class="text-primary font-semibold">$8.99</span>
                                        </div>
                                        <p class="text-sm text-gray-600 mb-3">Rich chocolate cake with ganache</p>
                                        <div class="flex justify-between items-center">
                                            <span class="text-xs text-gray-500">Dessert</span>
                                            <div class="flex space-x-2">
                                                <button class="text-blue-600 hover:text-blue-800">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="text-red-600 hover:text-red-800">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                                        <div class="flex justify-between items-start mb-2">
                                            <h4 class="font-medium text-gray-800">Fresh Juice</h4>
                                            <span class="text-primary font-semibold">$4.99</span>
                                        </div>
                                        <p class="text-sm text-gray-600 mb-3">Freshly squeezed orange juice</p>
                                        <div class="flex justify-between items-center">
                                            <span class="text-xs text-gray-500">Beverage</span>
                                            <div class="flex space-x-2">
                                                <button class="text-blue-600 hover:text-blue-800">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="text-red-600 hover:text-red-800">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Menu management functionality
        function addMenuItem() {
            console.log('Adding new menu item...');
        }

        function editMenuItem(id) {
            console.log('Editing menu item:', id);
        }

        function deleteMenuItem(id) {
            console.log('Deleting menu item:', id);
        }
    </script>
</body>
</html>
