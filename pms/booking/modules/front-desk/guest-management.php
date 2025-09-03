<?php
session_start();
require_once '../../../includes/error_handler.php';
require_once '../includes/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has front desk access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['front_desk', 'manager'])) {
    header('Location: ../../login.php');
    exit();
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$vip_filter = $_GET['vip'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Get guest statistics
$guest_stats = getGuestStatistics();

// Set page title
$page_title = 'Guest Management';

// Include unified header (automatically selects appropriate navbar)
include '../../includes/header-unified.php';
// Include unified sidebar (automatically selects appropriate sidebar)
include '../../includes/sidebar-unified.php';
?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guest Management - Hotel PMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
        <!-- Main Content -->
        <main class="lg:ml-64 mt-16 p-4 lg:p-6 flex-1 transition-all duration-300">
        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-users text-blue-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Guests</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($guest_stats['total_guests'] ?? 0); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-yellow-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-crown text-yellow-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">VIP Guests</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($guest_stats['vip_guests'] ?? 0); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-user-check text-green-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Active Guests</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($guest_stats['active_guests'] ?? 0); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-red-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-exclamation-triangle text-red-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Pending Feedback</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($guest_stats['pending_feedback'] ?? 0); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="bg-white rounded-lg shadow-sm border p-6 mb-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
                <div class="flex-1 max-w-lg">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400"></i>
                        </div>
                        <input type="text" id="search-input" placeholder="Search guests by name, email, or phone..." 
                               value="<?php echo htmlspecialchars($search); ?>"
                               class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-primary focus:border-primary">
                    </div>
                </div>
                
                <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-4">
                    <select id="vip-filter" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary">
                        <option value="">All VIP Status</option>
                        <option value="1" <?php echo $vip_filter === '1' ? 'selected' : ''; ?>>VIP Only</option>
                        <option value="0" <?php echo $vip_filter === '0' ? 'selected' : ''; ?>>Non-VIP Only</option>
                    </select>
                    
                    <select id="status-filter" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Currently Staying</option>
                        <option value="recent" <?php echo $status_filter === 'recent' ? 'selected' : ''; ?>>Recent Guests</option>
                        <option value="frequent" <?php echo $status_filter === 'frequent' ? 'selected' : ''; ?>>Frequent Guests</option>
                    </select>
                    
                    <button onclick="addNewGuest()" class="px-4 py-2 bg-primary text-white rounded-md hover:bg-primary-dark transition-colors">
                        <i class="fas fa-plus mr-2"></i>Add Guest
                    </button>
                </div>
            </div>
        </div>

        <!-- Guests Table -->
        <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Guest Directory</h3>
            </div>
            
            <div id="guests-table-container">
                <!-- Guests table will be loaded here -->
            </div>
        </div>

        <!-- Pagination -->
        <div id="pagination-container" class="mt-6">
            <!-- Pagination will be loaded here -->
        </div>
    </div>

    <!-- Add/Edit Guest Modal -->
    <div id="guest-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-8 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold text-gray-900" id="modal-title">Add New Guest</h3>
                <button onclick="closeGuestModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="guest-form" class="space-y-6">
                <input type="hidden" id="guest_id" name="guest_id">
                
                <!-- Basic Information -->
                <div class="border-b border-gray-200 pb-6">
                    <h4 class="text-md font-medium text-gray-900 mb-4">Basic Information</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">First Name *</label>
                            <input type="text" name="first_name" id="first_name" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Last Name *</label>
                            <input type="text" name="last_name" id="last_name" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input type="email" name="email" id="email" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Phone *</label>
                            <input type="tel" name="phone" id="phone" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Date of Birth</label>
                            <input type="date" name="date_of_birth" id="date_of_birth" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nationality</label>
                            <input type="text" name="nationality" id="nationality" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                        <textarea name="address" id="address" rows="3" 
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary"></textarea>
                    </div>
                </div>

                <!-- VIP and Preferences -->
                <div class="border-b border-gray-200 pb-6">
                    <h4 class="text-md font-medium text-gray-900 mb-4">VIP Status & Preferences</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ID Type</label>
                            <select name="id_type" id="id_type" required 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                                <option value="">Select ID Type</option>
                                <option value="passport">Passport</option>
                                <option value="driver_license">Driver's License</option>
                                <option value="national_id">National ID</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ID Number *</label>
                            <input type="text" name="id_number" id="id_number" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <div class="flex items-center">
                            <input type="checkbox" name="is_vip" id="is_vip" class="mr-2">
                            <label for="is_vip" class="text-sm font-medium text-gray-700">VIP Guest</label>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Guest Preferences</label>
                        <textarea name="preferences" id="preferences" rows="3" 
                                  placeholder="Room preferences, dietary restrictions, special requests..."
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary"></textarea>
                    </div>
                </div>

                <!-- Service Notes -->
                <div class="border-b border-gray-200 pb-6">
                    <h4 class="text-md font-medium text-gray-900 mb-4">Service Notes</h4>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Personalized Service Notes</label>
                        <textarea name="service_notes" id="service_notes" rows="4" 
                                  placeholder="Add personalized service notes, special instructions, or important information about this guest..."
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary"></textarea>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-end space-x-4">
                    <button type="button" onclick="closeGuestModal()" 
                            class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-6 py-2 bg-primary text-white rounded-md hover:bg-primary-dark transition-colors">
                        <i class="fas fa-save mr-2"></i>Save Guest
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Guest Details Modal -->
    <div id="guest-details-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-8 max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold text-gray-900">Guest Details</h3>
                <button onclick="closeGuestDetailsModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div id="guest-details-content">
                <!-- Guest details will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Feedback Modal -->
    <div id="feedback-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-8 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold text-gray-900">Add Feedback/Complaint</h3>
                <button onclick="closeFeedbackModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="feedback-form" class="space-y-6">
                <input type="hidden" id="feedback_guest_id" name="guest_id">
                <input type="hidden" id="feedback_reservation_id" name="reservation_id">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Feedback Type *</label>
                        <select name="feedback_type" id="feedback_type" required 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                            <option value="">Select Type</option>
                            <option value="compliment">Compliment</option>
                            <option value="complaint">Complaint</option>
                            <option value="suggestion">Suggestion</option>
                            <option value="general">General</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Category *</label>
                        <select name="category" id="category" required 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                            <option value="">Select Category</option>
                            <option value="service">Service</option>
                            <option value="cleanliness">Cleanliness</option>
                            <option value="facilities">Facilities</option>
                            <option value="staff">Staff</option>
                            <option value="food">Food</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Rating</label>
                    <div class="flex space-x-2">
                        <input type="radio" name="rating" value="1" id="rating-1" class="mr-1">
                        <label for="rating-1" class="text-sm text-gray-700">1</label>
                        <input type="radio" name="rating" value="2" id="rating-2" class="mr-1">
                        <label for="rating-2" class="text-sm text-gray-700">2</label>
                        <input type="radio" name="rating" value="3" id="rating-3" class="mr-1">
                        <label for="rating-3" class="text-sm text-gray-700">3</label>
                        <input type="radio" name="rating" value="4" id="rating-4" class="mr-1">
                        <label for="rating-4" class="text-sm text-gray-700">4</label>
                        <input type="radio" name="rating" value="5" id="rating-5" class="mr-1">
                        <label for="rating-5" class="text-sm text-gray-700">5</label>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Comments *</label>
                    <textarea name="comments" id="comments" rows="4" required 
                              placeholder="Please provide detailed feedback..."
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary"></textarea>
                </div>

                <div class="flex justify-end space-x-4">
                    <button type="button" onclick="closeFeedbackModal()" 
                            class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-6 py-2 bg-primary text-white rounded-md hover:bg-primary-dark transition-colors">
                        <i class="fas fa-save mr-2"></i>Submit Feedback
                    </button>
                </div>
            </form>
        </div>
    </div>

        </main>
    </div>

    <script src="../../assets/js/main.js"></script>
    <script src="../../assets/js/guest-management.js"></script>
    
    <?php include '../../includes/footer.php'; ?>
