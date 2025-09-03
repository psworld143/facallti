<?php
session_start();

// Check if user is logged in to POS
if (!isset($_SESSION['pos_user_id'])) {
    header('Location: ../login.php');
    exit();
}

$page_title = 'Quick Items';
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
                    <h1 class="text-2xl font-bold text-gray-800 mb-2">Quick Items</h1>
                    <p class="text-gray-600">Manage quick sales operations and data</p>
                </div>

                <!-- Quick Actions -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-primary">
                        <div class="flex items-center">
                            <div class="p-2 bg-primary bg-opacity-10 rounded-lg">
                                <i class="fas fa-plus text-primary text-lg"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-gray-900">Add New</h3>
                                <p class="text-xs text-gray-500">Create new entry</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-success">
                        <div class="flex items-center">
                            <div class="p-2 bg-success bg-opacity-10 rounded-lg">
                                <i class="fas fa-search text-success text-lg"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-gray-900">Search</h3>
                                <p class="text-xs text-gray-500">Find records</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-warning">
                        <div class="flex items-center">
                            <div class="p-2 bg-warning bg-opacity-10 rounded-lg">
                                <i class="fas fa-chart-bar text-warning text-lg"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-gray-900">Analytics</h3>
                                <p class="text-xs text-gray-500">View statistics</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-info">
                        <div class="flex items-center">
                            <div class="p-2 bg-info bg-opacity-10 rounded-lg">
                                <i class="fas fa-download text-info text-lg"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-gray-900">Export</h3>
                                <p class="text-xs text-gray-500">Download data</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content Area -->
                <div class="bg-white rounded-lg shadow">
                    <div class="p-4 border-b border-gray-200 flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-gray-800">Quick Items Overview</h3>
                        <button class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary-dark transition-colors">
                            <i class="fas fa-plus mr-2"></i>Add New
                        </button>
                    </div>
                    <div class="p-6">
                        <div class="text-center text-gray-500 py-12">
                            <i class="fas fa-cog text-4xl mb-4"></i>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">Module Under Development</h3>
                            <p class="text-gray-600">This quick sales module is currently being developed.</p>
                            <p class="text-sm text-gray-500 mt-2">Check back soon for full functionality!</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Quick sales module functionality
        function addNew() {
            console.log('Adding new quick sales entry...');
        }

        function searchRecords() {
            console.log('Searching quick sales records...');
        }

        function viewAnalytics() {
            console.log('Viewing quick sales analytics...');
        }
    </script>
</body>
</html>