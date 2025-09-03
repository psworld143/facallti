<?php
session_start();

// Check if user is logged in to POS
if (!isset($_SESSION['pos_user_id'])) {
    header('Location: ../login.php');
    exit();
}

$page_title = 'Table Management';
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
                    <h1 class="text-2xl font-bold text-gray-800 mb-2">Table Management</h1>
                    <p class="text-gray-600">Monitor table status and manage reservations</p>
                </div>

                <!-- Table Status Overview -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-success">
                        <div class="flex items-center">
                            <div class="p-2 bg-success bg-opacity-10 rounded-lg">
                                <i class="fas fa-check text-success text-lg"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-gray-900">Available</h3>
                                <p class="text-2xl font-bold text-success">12</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-warning">
                        <div class="flex items-center">
                            <div class="p-2 bg-warning bg-opacity-10 rounded-lg">
                                <i class="fas fa-users text-warning text-lg"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-gray-900">Occupied</h3>
                                <p class="text-2xl font-bold text-warning">8</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-primary">
                        <div class="flex items-center">
                            <div class="p-2 bg-primary bg-opacity-10 rounded-lg">
                                <i class="fas fa-clock text-primary text-lg"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-gray-900">Reserved</h3>
                                <p class="text-2xl font-bold text-primary">3</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-danger">
                        <div class="flex items-center">
                            <div class="p-2 bg-danger bg-opacity-10 rounded-lg">
                                <i class="fas fa-times text-danger text-lg"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-gray-900">Maintenance</h3>
                                <p class="text-2xl font-bold text-danger">2</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Table Grid -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-semibold text-gray-800">Restaurant Floor Plan</h3>
                        <button class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary-dark transition-colors">
                            <i class="fas fa-plus mr-2"></i>Add Table
                        </button>
                    </div>
                    
                    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                        <!-- Table 1 - Available -->
                        <div class="bg-green-100 border-2 border-green-300 rounded-lg p-4 text-center hover:shadow-md transition-shadow cursor-pointer">
                            <div class="text-2xl font-bold text-green-800 mb-2">1</div>
                            <div class="text-sm text-green-700 mb-2">Available</div>
                            <div class="text-xs text-green-600">4 Seats</div>
                            <div class="mt-2">
                                <button class="bg-green-600 text-white px-3 py-1 rounded text-xs hover:bg-green-700">Reserve</button>
                            </div>
                        </div>

                        <!-- Table 2 - Occupied -->
                        <div class="bg-yellow-100 border-2 border-yellow-300 rounded-lg p-4 text-center hover:shadow-md transition-shadow cursor-pointer">
                            <div class="text-2xl font-bold text-yellow-800 mb-2">2</div>
                            <div class="text-sm text-yellow-700 mb-2">Occupied</div>
                            <div class="text-xs text-yellow-600">2 Guests</div>
                            <div class="mt-2">
                                <button class="bg-blue-600 text-white px-3 py-1 rounded text-xs hover:bg-blue-700">View</button>
                            </div>
                        </div>

                        <!-- Table 3 - Reserved -->
                        <div class="bg-blue-100 border-2 border-blue-300 rounded-lg p-4 text-center hover:shadow-md transition-shadow cursor-pointer">
                            <div class="text-2xl font-bold text-blue-800 mb-2">3</div>
                            <div class="text-sm text-blue-700 mb-2">Reserved</div>
                            <div class="text-xs text-blue-600">7:00 PM</div>
                            <div class="mt-2">
                                <button class="bg-blue-600 text-white px-3 py-1 rounded text-xs hover:bg-blue-700">Details</button>
                            </div>
                        </div>

                        <!-- Table 4 - Available -->
                        <div class="bg-green-100 border-2 border-green-300 rounded-lg p-4 text-center hover:shadow-md transition-shadow cursor-pointer">
                            <div class="text-2xl font-bold text-green-800 mb-2">4</div>
                            <div class="text-sm text-green-700 mb-2">Available</div>
                            <div class="text-xs text-green-600">6 Seats</div>
                            <div class="mt-2">
                                <button class="bg-green-600 text-white px-3 py-1 rounded text-xs hover:bg-green-700">Reserve</button>
                            </div>
                        </div>

                        <!-- Table 5 - Occupied -->
                        <div class="bg-yellow-100 border-2 border-yellow-300 rounded-lg p-4 text-center hover:shadow-md transition-shadow cursor-pointer">
                            <div class="text-2xl font-bold text-yellow-800 mb-2">5</div>
                            <div class="text-sm text-yellow-700 mb-2">Occupied</div>
                            <div class="text-xs text-yellow-600">4 Guests</div>
                            <div class="mt-2">
                                <button class="bg-blue-600 text-white px-3 py-1 rounded text-xs hover:bg-blue-700">View</button>
                            </div>
                        </div>

                        <!-- Table 6 - Maintenance -->
                        <div class="bg-red-100 border-2 border-red-300 rounded-lg p-4 text-center hover:shadow-md transition-shadow cursor-pointer">
                            <div class="text-2xl font-bold text-red-800 mb-2">6</div>
                            <div class="text-sm text-red-700 mb-2">Maintenance</div>
                            <div class="text-xs text-red-600">Out of Service</div>
                            <div class="mt-2">
                                <button class="bg-gray-600 text-white px-3 py-1 rounded text-xs hover:bg-gray-700">Repair</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Reservations -->
                <div class="bg-white rounded-lg shadow mt-6">
                    <div class="p-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800">Recent Reservations</h3>
                    </div>
                    <div class="p-4">
                        <div class="space-y-3">
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div>
                                    <div class="font-medium text-gray-900">Table 3 - 7:00 PM</div>
                                    <div class="text-sm text-gray-600">John Smith - 4 guests</div>
                                </div>
                                <div class="flex space-x-2">
                                    <button class="text-blue-600 hover:text-blue-800 text-sm">Edit</button>
                                    <button class="text-red-600 hover:text-red-800 text-sm">Cancel</button>
                                </div>
                            </div>
                            
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div>
                                    <div class="font-medium text-gray-900">Table 8 - 8:30 PM</div>
                                    <div class="text-sm text-gray-600">Sarah Johnson - 2 guests</div>
                                </div>
                                <div class="flex space-x-2">
                                    <button class="text-blue-600 hover:text-blue-800 text-sm">Edit</button>
                                    <button class="text-red-600 hover:text-red-800 text-sm">Cancel</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Table management functionality
        function reserveTable(tableId) {
            console.log('Reserving table:', tableId);
        }

        function viewTable(tableId) {
            console.log('Viewing table:', tableId);
        }

        function editReservation(reservationId) {
            console.log('Editing reservation:', reservationId);
        }
    </script>
</body>
</html>
