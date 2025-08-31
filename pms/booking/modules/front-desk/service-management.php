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

$user_role = $_SESSION['user_role'];
$user_name = $_SESSION['user_name'];

// Get service statistics
$service_stats = getServiceStatistics();

// Set page title
$page_title = 'Service Management';
$page_subtitle = 'Manage hotel services and requests';

// Include unified navigation (automatically selects based on user role)
include '../../includes/header-unified.php';
include '../../includes/sidebar-unified.php';
?>

        <!-- Main Content -->
        <main class="lg:ml-64 mt-16 p-4 lg:p-6 flex-1 transition-all duration-300">
            <div class="flex justify-between items-center mb-8">
                <h2 class="text-3xl font-semibold text-gray-800">Service Management</h2>
                <div class="text-right">
                    <div id="current-date" class="text-sm text-gray-600"></div>
                    <div id="current-time" class="text-sm text-gray-600"></div>
                </div>
            </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-tools text-blue-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Active Service Requests</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($service_stats['active_requests']); ?></p>
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
                        <p class="text-sm font-medium text-gray-500">Today's Service Revenue</p>
                        <p class="text-2xl font-semibold text-gray-900">$<?php echo number_format($service_stats['today_revenue'], 2); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-yellow-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-concierge-bell text-yellow-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Pending Services</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($service_stats['pending_services']); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-check-circle text-purple-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Completed Today</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo number_format($service_stats['completed_today']); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white rounded-lg shadow-sm border p-6 mb-8">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Quick Actions</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <button onclick="openServiceRequestModal()" class="flex items-center p-4 bg-blue-50 border-2 border-blue-200 rounded-lg hover:bg-blue-100 hover:border-blue-300 transition-all duration-300">
                    <i class="fas fa-plus text-blue-600 text-xl mr-3"></i>
                    <span class="font-medium text-blue-800">New Service Request</span>
                </button>
                <button onclick="openAdditionalServiceModal()" class="flex items-center p-4 bg-green-50 border-2 border-green-200 rounded-lg hover:bg-green-100 hover:border-green-300 transition-all duration-300">
                    <i class="fas fa-concierge-bell text-green-600 text-xl mr-3"></i>
                    <span class="font-medium text-green-800">Add Service Charge</span>
                </button>
                <button onclick="openMinibarModal()" class="flex items-center p-4 bg-yellow-50 border-2 border-yellow-200 rounded-lg hover:bg-yellow-100 hover:border-yellow-300 transition-all duration-300">
                    <i class="fas fa-wine-bottle text-yellow-600 text-xl mr-3"></i>
                    <span class="font-medium text-yellow-800">Minibar Charges</span>
                </button>
                <button onclick="openLaundryModal()" class="flex items-center p-4 bg-purple-50 border-2 border-purple-200 rounded-lg hover:bg-purple-100 hover:border-purple-300 transition-all duration-300">
                    <i class="fas fa-tshirt text-purple-600 text-xl mr-3"></i>
                    <span class="font-medium text-purple-800">Laundry Services</span>
                </button>
            </div>
        </div>

        <!-- Tabs -->
        <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex space-x-8 px-6">
                    <button onclick="switchTab('service-requests')" id="tab-service-requests" class="tab-button active py-4 px-1 border-b-2 border-primary font-medium text-sm text-primary">
                        <i class="fas fa-tools mr-2"></i>Service Requests
                    </button>
                    <button onclick="switchTab('additional-services')" id="tab-additional-services" class="tab-button py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        <i class="fas fa-concierge-bell mr-2"></i>Additional Services
                    </button>
                    <button onclick="switchTab('service-charges')" id="tab-service-charges" class="tab-button py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        <i class="fas fa-dollar-sign mr-2"></i>Service Charges
                    </button>
                </nav>
            </div>
            
            <!-- Service Requests Tab -->
            <div id="tab-content-service-requests" class="tab-content active p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-medium text-gray-900">Service Requests</h3>
                    <div class="flex space-x-2">
                        <select id="request-status-filter" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary">
                            <option value="">All Status</option>
                            <option value="reported">Reported</option>
                            <option value="assigned">Assigned</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                            <option value="verified">Verified</option>
                        </select>
                        <select id="request-type-filter" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary">
                            <option value="">All Types</option>
                            <option value="plumbing">Plumbing</option>
                            <option value="electrical">Electrical</option>
                            <option value="hvac">HVAC</option>
                            <option value="furniture">Furniture</option>
                            <option value="appliance">Appliance</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                
                <div id="service-requests-container">
                    <!-- Service requests will be loaded here -->
                </div>
            </div>
            
            <!-- Additional Services Tab -->
            <div id="tab-content-additional-services" class="tab-content hidden p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-medium text-gray-900">Additional Services</h3>
                    <div class="flex space-x-2">
                        <select id="service-category-filter" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary">
                            <option value="">All Categories</option>
                            <option value="food_beverage">Food & Beverage</option>
                            <option value="laundry">Laundry</option>
                            <option value="spa">Spa</option>
                            <option value="transportation">Transportation</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                
                <div id="additional-services-container">
                    <!-- Additional services will be loaded here -->
                </div>
            </div>
            
            <!-- Service Charges Tab -->
            <div id="tab-content-service-charges" class="tab-content hidden p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-medium text-gray-900">Service Charges</h3>
                    <div class="flex space-x-2">
                        <input type="date" id="charges-date-filter" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary">
                        <select id="charges-status-filter" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary">
                            <option value="">All Services</option>
                            <option value="today">Today Only</option>
                            <option value="week">This Week</option>
                            <option value="month">This Month</option>
                        </select>
                    </div>
                </div>
                
                <div id="service-charges-container">
                    <!-- Service charges will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Service Request Modal -->
    <div id="service-request-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-8 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold text-gray-900">New Service Request</h3>
                <button onclick="closeServiceRequestModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="service-request-form" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Room Number *</label>
                        <select name="room_id" id="request_room_id" required 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                            <option value="">Select Room</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Issue Type *</label>
                        <select name="issue_type" id="issue_type" required 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                            <option value="">Select Type</option>
                            <option value="plumbing">Plumbing</option>
                            <option value="electrical">Electrical</option>
                            <option value="hvac">HVAC</option>
                            <option value="furniture">Furniture</option>
                            <option value="appliance">Appliance</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Priority *</label>
                    <select name="priority" id="request_priority" required 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                        <option value="">Select Priority</option>
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description *</label>
                    <textarea name="description" id="request_description" rows="4" required 
                              placeholder="Describe the service request in detail..."
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary"></textarea>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Special Instructions</label>
                    <textarea name="special_instructions" id="request_instructions" rows="3" 
                              placeholder="Any special instructions or notes..."
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary"></textarea>
                </div>

                <div class="flex justify-end space-x-4">
                    <button type="button" onclick="closeServiceRequestModal()" 
                            class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-6 py-2 bg-primary text-white rounded-md hover:bg-primary-dark transition-colors">
                        <i class="fas fa-save mr-2"></i>Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Additional Service Modal -->
    <div id="additional-service-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-8 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold text-gray-900">Add Service Charge</h3>
                <button onclick="closeAdditionalServiceModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="additional-service-form" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Reservation *</label>
                        <select name="reservation_id" id="service_reservation_id" required 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                            <option value="">Select Reservation</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Service Category *</label>
                        <select name="service_category" id="service_category" required 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                            <option value="">Select Category</option>
                            <option value="minibar">Minibar</option>
                            <option value="laundry">Laundry</option>
                            <option value="spa">Spa</option>
                            <option value="restaurant">Restaurant</option>
                            <option value="transportation">Transportation</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Service Name *</label>
                        <input type="text" name="service_name" id="service_name" required 
                               placeholder="e.g., Room Service, Spa Treatment, etc."
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Quantity</label>
                        <input type="number" name="quantity" id="service_quantity" min="1" value="1"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Unit Price *</label>
                        <input type="number" name="unit_price" id="service_unit_price" step="0.01" min="0" required 
                               placeholder="0.00"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Total Amount</label>
                        <input type="text" id="service_total_amount" readonly 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" id="service_description" rows="3" 
                              placeholder="Additional details about the service..."
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary"></textarea>
                </div>

                <div class="flex justify-end space-x-4">
                    <button type="button" onclick="closeAdditionalServiceModal()" 
                            class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-6 py-2 bg-primary text-white rounded-md hover:bg-primary-dark transition-colors">
                        <i class="fas fa-save mr-2"></i>Add Service
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Minibar Modal -->
    <div id="minibar-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-8 max-w-3xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold text-gray-900">Minibar Charges</h3>
                <button onclick="closeMinibarModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="minibar-form" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Room *</label>
                        <select name="minibar_room_id" id="minibar_room_id" required 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                            <option value="">Select Room</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                        <input type="date" name="minibar_date" id="minibar_date" 
                               value="<?php echo date('Y-m-d'); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-4">Minibar Items</label>
                    <div id="minibar-items-container" class="space-y-3">
                        <!-- Minibar items will be loaded here -->
                    </div>
                </div>
                
                <div class="border-t pt-4">
                    <div class="flex justify-between items-center">
                        <span class="text-lg font-medium text-gray-900">Total Amount:</span>
                        <span id="minibar-total" class="text-2xl font-bold text-primary">$0.00</span>
                    </div>
                </div>

                <div class="flex justify-end space-x-4">
                    <button type="button" onclick="closeMinibarModal()" 
                            class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-6 py-2 bg-primary text-white rounded-md hover:bg-primary-dark transition-colors">
                        <i class="fas fa-save mr-2"></i>Save Minibar Charges
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Laundry Modal -->
    <div id="laundry-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-8 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold text-gray-900">Laundry Services</h3>
                <button onclick="closeLaundryModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="laundry-form" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Reservation *</label>
                        <select name="laundry_reservation_id" id="laundry_reservation_id" required 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                            <option value="">Select Reservation</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Service Type *</label>
                        <select name="laundry_service_type" id="laundry_service_type" required 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                            <option value="">Select Service</option>
                            <option value="dry_clean">Dry Clean</option>
                            <option value="wash_and_press">Wash & Press</option>
                            <option value="press_only">Press Only</option>
                            <option value="express">Express Service</option>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Number of Items</label>
                        <input type="number" name="laundry_quantity" id="laundry_quantity" min="1" value="1"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Unit Price</label>
                        <input type="number" name="laundry_unit_price" id="laundry_unit_price" step="0.01" min="0"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Special Instructions</label>
                    <textarea name="laundry_instructions" id="laundry_instructions" rows="3" 
                              placeholder="Any special instructions for laundry..."
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary"></textarea>
                </div>

                <div class="flex justify-end space-x-4">
                    <button type="button" onclick="closeLaundryModal()" 
                            class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-6 py-2 bg-primary text-white rounded-md hover:bg-primary-dark transition-colors">
                        <i class="fas fa-save mr-2"></i>Add Laundry Service
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Service Request Details Modal -->
    <div id="service-request-details-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-8 max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold text-gray-900">Service Request Details</h3>
                <button onclick="closeServiceRequestDetailsModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div id="service-request-details-content" class="space-y-6">
                <!-- Content will be loaded dynamically -->
            </div>

            <div class="flex justify-end space-x-4 mt-6">
                <button onclick="closeServiceRequestDetailsModal()" 
                        class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- Service Details Modal -->
    <div id="service-details-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-8 max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold text-gray-900">Service Details</h3>
                <button onclick="closeServiceDetailsModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div id="service-details-content" class="space-y-6">
                <!-- Content will be loaded dynamically -->
            </div>

            <div class="flex justify-end space-x-4 mt-6">
                <button onclick="closeServiceDetailsModal()" 
                        class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- Charge Details Modal -->
    <div id="charge-details-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-8 max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold text-gray-900">Charge Details</h3>
                <button onclick="closeChargeDetailsModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div id="charge-details-content" class="space-y-6">
                <!-- Content will be loaded dynamically -->
            </div>

            <div class="flex justify-end space-x-4 mt-6">
                <button onclick="closeChargeDetailsModal()" 
                        class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                    Close
                </button>
            </div>
        </div>
    </div>

        </main>

<?php include '../../includes/footer.php'; ?>
