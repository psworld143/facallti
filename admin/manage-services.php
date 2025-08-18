<?php
session_start();
require_once '../config/database.php';
require_once '../includes/services_crud.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_service':
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);
                $icon = trim($_POST['icon']);
                $color_theme = trim($_POST['color_theme']);
                $category_id = (int)$_POST['category_id'];
                $sort_order = (int)$_POST['sort_order'];

                $errors = validate_service_data($name, $description, $category_id);

                if (empty($errors)) {
                    $service_id = add_service($conn, $name, $description, $icon, $color_theme, $category_id, $sort_order);
                    if ($service_id) {
                        $message = "Service added successfully!";
                    } else {
                        $error = "Failed to add service.";
                    }
                } else {
                    $error = implode(", ", $errors);
                }
                break;

            case 'update_service':
                $service_id = (int)$_POST['service_id'];
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);
                $icon = trim($_POST['icon']);
                $color_theme = trim($_POST['color_theme']);
                $category_id = (int)$_POST['category_id'];
                $sort_order = (int)$_POST['sort_order'];

                $errors = validate_service_data($name, $description, $category_id);

                if (empty($errors)) {
                    if (update_service($conn, $service_id, $name, $description, $icon, $color_theme, $category_id, $sort_order)) {
                        $message = "Service updated successfully!";
                    } else {
                        $error = "Failed to update service.";
                    }
                } else {
                    $error = implode(", ", $errors);
                }
                break;

            case 'delete_service':
                $service_id = (int)$_POST['service_id'];
                if (delete_service($conn, $service_id)) {
                    $message = "Service deleted successfully!";
                } else {
                    $error = "Failed to delete service.";
                }
                break;

            case 'add_category':
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);
                $icon = trim($_POST['icon']);
                $color_theme = trim($_POST['color_theme']);
                $sort_order = (int)$_POST['sort_order'];

                $errors = validate_service_category_data($name, $description);

                if (empty($errors)) {
                    $category_id = add_service_category($conn, $name, $description, $icon, $color_theme, $sort_order);
                    if ($category_id) {
                        $message = "Category added successfully!";
                    } else {
                        $error = "Failed to add category.";
                    }
                } else {
                    $error = implode(", ", $errors);
                }
                break;

            case 'update_category':
                $category_id = (int)$_POST['category_id'];
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);
                $icon = trim($_POST['icon']);
                $color_theme = trim($_POST['color_theme']);
                $sort_order = (int)$_POST['sort_order'];

                $errors = validate_service_category_data($name, $description);

                if (empty($errors)) {
                    if (update_service_category($conn, $category_id, $name, $description, $icon, $color_theme, $sort_order)) {
                        $message = "Category updated successfully!";
                    } else {
                        $error = "Failed to update category.";
                    }
                } else {
                    $error = implode(", ", $errors);
                }
                break;

            case 'delete_category':
                $category_id = (int)$_POST['category_id'];
                if (delete_service_category($conn, $category_id)) {
                    $message = "Category deleted successfully!";
                } else {
                    $error = "Failed to delete category.";
                }
                break;
        }
    }
}

// Get data for display
$services = get_services($conn);
$service_categories = get_service_categories($conn);
$stats = get_services_statistics($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Services - SEAIT Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        'seait-orange': '#FF6B35',
                        'seait-dark': '#2C3E50',
                        'seait-light': '#FFF8F0'
                    },
                    fontFamily: {
                        'poppins': ['Poppins', 'sans-serif']
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @keyframes bounce-in {
            0% {
                opacity: 0;
                transform: scale(0.3);
            }
            50% {
                opacity: 1;
                transform: scale(1.05);
            }
            70% {
                transform: scale(0.9);
            }
            100% {
                opacity: 1;
                transform: scale(1);
            }
        }

        .animate-bounce-in {
            animation: bounce-in 0.6s ease-out;
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'includes/admin-header.php'; ?>

    <div class="flex pt-16">
        <?php include 'includes/admin-sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 ml-64 p-8 overflow-y-auto h-screen">
            <!-- Header -->
            <div class="mb-6 sm:mb-8">
                <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Manage Services</h1>
                <p class="text-gray-600">Add, edit, and manage services and categories</p>
            </div>

            <!-- Messages -->
            <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="text-3xl font-bold text-seait-orange"><?php echo $stats['total_services']; ?></div>
                    <div class="text-gray-600">Total Services</div>
                </div>
                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="text-3xl font-bold text-seait-orange"><?php echo $stats['total_categories']; ?></div>
                    <div class="text-gray-600">Total Categories</div>
                </div>
                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="text-3xl font-bold text-seait-orange">Active</div>
                    <div class="text-gray-600">System Status</div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="mb-8">
                <div class="border-b border-gray-200">
                    <nav class="-mb-px flex space-x-8">
                        <button onclick="showTab('services')" class="tab-button py-2 px-1 border-b-2 border-seait-orange text-seait-orange font-medium">
                            Services
                        </button>
                        <button onclick="showTab('categories')" class="tab-button py-2 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium">
                            Categories
                        </button>
                    </nav>
                </div>
            </div>

            <!-- Services Tab -->
            <div id="services-tab" class="tab-content">
                <!-- Add Service Form -->
                <div class="bg-white p-6 rounded-lg shadow mb-8">
                    <h3 class="text-xl font-semibold text-seait-dark mb-4">Add New Service</h3>
                    <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <input type="hidden" name="action" value="add_service">

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Service Name</label>
                            <input type="text" name="name" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                            <select name="category_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                                <option value="">Select Category</option>
                                <?php foreach ($service_categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                            <textarea name="description" required rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Icon (FontAwesome)</label>
                            <input type="text" name="icon" value="fas fa-cog" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Color Theme</label>
                            <input type="color" name="color_theme" value="#FF6B35" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Sort Order</label>
                            <input type="number" name="sort_order" value="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>

                        <div class="md:col-span-2">
                            <button type="submit" class="bg-seait-orange text-white px-6 py-2 rounded-lg hover:bg-orange-600 transition">
                                Add Service
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Services List -->
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-xl font-semibold text-seait-dark">All Services</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Service</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Icon</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sort Order</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($services as $service): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($service['name']); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars(substr($service['description'], 0, 50)); ?>...</div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo htmlspecialchars($service['category_name'] ?? 'Uncategorized'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <i class="<?php echo htmlspecialchars($service['icon']); ?> text-lg" style="color: <?php echo $service['color_theme']; ?>"></i>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo $service['sort_order']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button onclick="editService(<?php echo htmlspecialchars(json_encode($service)); ?>)" class="text-seait-orange hover:text-orange-600 mr-3">Edit</button>
                                        <button onclick="deleteService(<?php echo $service['id']; ?>)" class="text-red-600 hover:text-red-900">Delete</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Categories Tab -->
            <div id="categories-tab" class="tab-content hidden">
                <!-- Add Category Form -->
                <div class="bg-white p-6 rounded-lg shadow mb-8">
                    <h3 class="text-xl font-semibold text-seait-dark mb-4">Add New Category</h3>
                    <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <input type="hidden" name="action" value="add_category">

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Category Name</label>
                            <input type="text" name="name" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Icon (FontAwesome)</label>
                            <input type="text" name="icon" value="fas fa-folder" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                            <textarea name="description" required rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Color Theme</label>
                            <input type="color" name="color_theme" value="#FF6B35" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Sort Order</label>
                            <input type="number" name="sort_order" value="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>

                        <div class="md:col-span-2">
                            <button type="submit" class="bg-seait-orange text-white px-6 py-2 rounded-lg hover:bg-orange-600 transition">
                                Add Category
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Categories List -->
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-xl font-semibold text-seait-dark">All Categories</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Icon</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Services Count</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sort Order</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($service_categories as $category): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($category['name']); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars(substr($category['description'], 0, 50)); ?>...</div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <i class="<?php echo htmlspecialchars($category['icon']); ?> text-lg" style="color: <?php echo $category['color_theme']; ?>"></i>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php
                                        $category_services = get_services_by_category($conn, $category['id']);
                                        echo count($category_services);
                                        ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo $category['sort_order']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button onclick="editCategory(<?php echo htmlspecialchars(json_encode($category)); ?>)" class="text-seait-orange hover:text-orange-600 mr-3">Edit</button>
                                        <button onclick="deleteCategory(<?php echo $category['id']; ?>)" class="text-red-600 hover:text-red-900">Delete</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Service Confirmation Modal -->
    <div id="deleteServiceModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full animate-bounce-in">
                <div class="p-6 text-center">
                    <div class="mb-4">
                        <div class="p-4 rounded-full bg-red-100 text-red-600 inline-block mb-4">
                            <i class="fas fa-exclamation-triangle text-3xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Delete Service</h3>
                        <p class="text-gray-600 mb-4">Are you sure you want to delete this service? This action cannot be undone.</p>
                        <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                            <div class="flex items-center text-red-800">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                <span class="text-sm font-medium">Warning:</span>
                            </div>
                            <ul class="text-sm text-red-700 mt-2 text-left space-y-1">
                                <li class="flex items-center">
                                    <i class="fas fa-trash mr-2 text-red-500"></i>
                                    Service will be permanently removed
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-eye-slash mr-2 text-red-500"></i>
                                    No longer visible to visitors
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-undo mr-2 text-red-500"></i>
                                    Cannot be recovered
                                </li>
                            </ul>
                        </div>
                    </div>
                    <form method="POST" class="space-y-3">
                        <input type="hidden" name="action" value="delete_service">
                        <input type="hidden" name="service_id" id="deleteServiceId">
                        <div class="flex justify-center space-x-3">
                            <button type="button" onclick="closeDeleteServiceModal()"
                                    class="px-6 py-3 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200">
                                <i class="fas fa-times mr-2"></i>Cancel
                            </button>
                            <button type="submit"
                                    class="px-6 py-3 bg-gradient-to-r from-red-500 to-red-600 text-white rounded-lg hover:from-red-600 hover:to-red-700 transition-all duration-200 font-semibold">
                                <i class="fas fa-trash mr-2"></i>Delete Permanently
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Category Confirmation Modal -->
    <div id="deleteCategoryModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full animate-bounce-in">
                <div class="p-6 text-center">
                    <div class="mb-4">
                        <div class="p-4 rounded-full bg-red-100 text-red-600 inline-block mb-4">
                            <i class="fas fa-exclamation-triangle text-3xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Delete Category</h3>
                        <p class="text-gray-600 mb-4">Are you sure you want to delete this category? This action cannot be undone.</p>
                        <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                            <div class="flex items-center text-red-800">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                <span class="text-sm font-medium">Warning:</span>
                            </div>
                            <ul class="text-sm text-red-700 mt-2 text-left space-y-1">
                                <li class="flex items-center">
                                    <i class="fas fa-trash mr-2 text-red-500"></i>
                                    Category will be permanently removed
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-eye-slash mr-2 text-red-500"></i>
                                    All services in this category will be affected
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-undo mr-2 text-red-500"></i>
                                    Cannot be recovered
                                </li>
                            </ul>
                        </div>
                    </div>
                    <form method="POST" class="space-y-3">
                        <input type="hidden" name="action" value="delete_category">
                        <input type="hidden" name="category_id" id="deleteCategoryId">
                        <div class="flex justify-center space-x-3">
                            <button type="button" onclick="closeDeleteCategoryModal()"
                                    class="px-6 py-3 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200">
                                <i class="fas fa-times mr-2"></i>Cancel
                            </button>
                            <button type="submit"
                                    class="px-6 py-3 bg-gradient-to-r from-red-500 to-red-600 text-white rounded-lg hover:from-red-600 hover:to-red-700 transition-all duration-200 font-semibold">
                                <i class="fas fa-trash mr-2"></i>Delete Permanently
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Sidebar functionality
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuButton = document.getElementById('mobile-menu-button');
            const sidebar = document.getElementById('sidebar');
            const closeSidebarButton = document.getElementById('close-sidebar');
            const mobileOverlay = document.getElementById('mobile-overlay');

            // Open sidebar
            mobileMenuButton.addEventListener('click', function() {
                sidebar.classList.remove('-translate-x-full');
                mobileOverlay.classList.remove('hidden');
            });

            // Close sidebar
            function closeSidebar() {
                sidebar.classList.add('-translate-x-full');
                mobileOverlay.classList.add('hidden');
            }

            closeSidebarButton.addEventListener('click', closeSidebar);
            mobileOverlay.addEventListener('click', closeSidebar);

            // Close sidebar on window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 1024) {
                    sidebar.classList.remove('-translate-x-full');
                    mobileOverlay.classList.add('hidden');
                } else {
                    sidebar.classList.add('-translate-x-full');
                }
            });
        });

        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });

            // Remove active class from all tab buttons
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('border-seait-orange', 'text-seait-orange');
                button.classList.add('border-transparent', 'text-gray-500');
            });

            // Show selected tab content
            document.getElementById(tabName + '-tab').classList.remove('hidden');

            // Add active class to clicked button
            event.target.classList.remove('border-transparent', 'text-gray-500');
            event.target.classList.add('border-seait-orange', 'text-seait-orange');
        }

        function deleteService(serviceId) {
            document.getElementById('deleteServiceId').value = serviceId;
            document.getElementById('deleteServiceModal').classList.remove('hidden');
        }

        function deleteCategory(categoryId) {
            document.getElementById('deleteCategoryId').value = categoryId;
            document.getElementById('deleteCategoryModal').classList.remove('hidden');
        }

        function closeDeleteServiceModal() {
            document.getElementById('deleteServiceModal').classList.add('hidden');
        }

        function closeDeleteCategoryModal() {
            document.getElementById('deleteCategoryModal').classList.add('hidden');
        }

        // Close delete modals when clicking outside
        const deleteServiceModal = document.getElementById('deleteServiceModal');
        if (deleteServiceModal) {
            deleteServiceModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeDeleteServiceModal();
                }
            });
        }

        const deleteCategoryModal = document.getElementById('deleteCategoryModal');
        if (deleteCategoryModal) {
            deleteCategoryModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeDeleteCategoryModal();
                }
            });
        }

        function editService(service) {
            // Populate form with service data
            document.querySelector('input[name="name"]').value = service.name;
            document.querySelector('textarea[name="description"]').value = service.description;
            document.querySelector('input[name="icon"]').value = service.icon;
            document.querySelector('input[name="color_theme"]').value = service.color_theme;
            document.querySelector('select[name="category_id"]').value = service.category_id;
            document.querySelector('input[name="sort_order"]').value = service.sort_order;

            // Change form action
            document.querySelector('input[name="action"]').value = 'update_service';
            document.querySelector('button[type="submit"]').textContent = 'Update Service';

            // Add service ID
            let serviceIdInput = document.querySelector('input[name="service_id"]');
            if (!serviceIdInput) {
                serviceIdInput = document.createElement('input');
                serviceIdInput.type = 'hidden';
                serviceIdInput.name = 'service_id';
                document.querySelector('form').appendChild(serviceIdInput);
            }
            serviceIdInput.value = service.id;
        }

        function editCategory(category) {
            // Switch to categories tab first
            showTab('categories');

            // Populate form with category data
            document.querySelector('#categories-tab input[name="name"]').value = category.name;
            document.querySelector('#categories-tab textarea[name="description"]').value = category.description;
            document.querySelector('#categories-tab input[name="icon"]').value = category.icon;
            document.querySelector('#categories-tab input[name="color_theme"]').value = category.color_theme;
            document.querySelector('#categories-tab input[name="sort_order"]').value = category.sort_order;

            // Change form action
            document.querySelector('#categories-tab input[name="action"]').value = 'update_category';
            document.querySelector('#categories-tab button[type="submit"]').textContent = 'Update Category';

            // Add category ID
            let categoryIdInput = document.querySelector('#categories-tab input[name="category_id"]');
            if (!categoryIdInput) {
                categoryIdInput = document.createElement('input');
                categoryIdInput.type = 'hidden';
                categoryIdInput.name = 'category_id';
                document.querySelector('#categories-tab form').appendChild(categoryIdInput);
            }
            categoryIdInput.value = category.id;
        }
    </script>
</body>
</html>