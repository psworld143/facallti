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

// Get service requests data
$service_requests = getServiceRequests();
$request_stats = getServiceRequestStats();

// Set page title
$page_title = 'Service Requests';

// Include unified navigation (automatically selects based on user role)
include '../../includes/header-unified.php';
include '../../includes/sidebar-unified.php';
?>

        <!-- Main Content -->
        <main class="lg:ml-64 mt-16 p-4 lg:p-6 flex-1 transition-all duration-300">
            <!-- Search Section -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Search Service Requests</h2>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label for="search_request" class="block text-sm font-medium text-gray-700 mb-2">Request ID</label>
                        <input type="text" id="search_request" placeholder="Enter request ID" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <div>
                        <label for="search_guest" class="block text-sm font-medium text-gray-700 mb-2">Guest Name</label>
                        <input type="text" id="search_guest" placeholder="Enter guest name" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <div>
                        <label for="search_status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select id="search_status" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button onclick="searchRequests()" 
                                class="w-full px-4 py-2 bg-primary text-white rounded-md hover:bg-primary-dark transition-colors">
                            <i class="fas fa-search mr-2"></i>Search
                        </button>
                    </div>
                </div>
            </div>

            <!-- Service Requests Statistics -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Request Statistics</h2>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                    <div class="flex items-center">
                            <div class="p-3 bg-blue-100 rounded-lg">
                            <i class="fas fa-clock text-blue-600 text-xl"></i>
                        </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Pending Requests</p>
                                <p class="text-2xl font-bold text-gray-900"><?php echo $request_stats['pending']; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-green-50 rounded-lg p-4 border border-green-200">
                    <div class="flex items-center">
                            <div class="p-3 bg-green-100 rounded-lg">
                            <i class="fas fa-check-circle text-green-600 text-xl"></i>
                        </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Completed Today</p>
                                <p class="text-2xl font-bold text-gray-900"><?php echo $request_stats['completed']; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-yellow-50 rounded-lg p-4 border border-yellow-200">
                    <div class="flex items-center">
                            <div class="p-3 bg-yellow-100 rounded-lg">
                            <i class="fas fa-exclamation-triangle text-yellow-600 text-xl"></i>
                        </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Urgent Requests</p>
                                <p class="text-2xl font-bold text-gray-900"><?php echo $request_stats['urgent']; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-purple-50 rounded-lg p-4 border border-purple-200">
                    <div class="flex items-center">
                            <div class="p-3 bg-purple-100 rounded-lg">
                            <i class="fas fa-star text-purple-600 text-xl"></i>
                        </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Avg Response Time</p>
                                <p class="text-2xl font-bold text-gray-900"><?php echo $request_stats['avg_response_time']; ?>m</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Service Requests List -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Service Requests</h2>
                <div id="service-requests" class="overflow-x-auto">
                    <!-- Service requests will be loaded here -->
                </div>
            </div>

            <!-- New Request Form -->
            <div id="request-form-container" class="bg-white rounded-lg shadow-md p-6 hidden">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Create New Service Request</h2>
                <form id="request-form" class="space-y-6">
                    <input type="hidden" id="request_id" name="request_id">
                    
                    <!-- Guest Information -->
                    <div class="border-b border-gray-200 pb-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Guest Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                                <label for="guest_id" class="block text-sm font-medium text-gray-700 mb-2">Guest *</label>
                                <select id="guest_id" name="guest_id" required 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="">Select Guest</option>
                            <?php foreach (getGuests() as $guest): ?>
                                <option value="<?php echo $guest['id']; ?>">
                                    <?php echo htmlspecialchars($guest['name']); ?> - Room <?php echo htmlspecialchars($guest['room_number']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                                <label for="room_number" class="block text-sm font-medium text-gray-700 mb-2">Room Number</label>
                                <input type="text" id="room_number" readonly 
                                       class="w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md">
                            </div>
                        </div>
                    </div>

                    <!-- Request Details -->
                    <div class="border-b border-gray-200 pb-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Request Details</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="request_type" class="block text-sm font-medium text-gray-700 mb-2">Request Type *</label>
                                <select id="request_type" name="request_type" required 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="">Select Type</option>
                            <option value="room_service">Room Service</option>
                            <option value="housekeeping">Housekeeping</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="concierge">Concierge</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div>
                                <label for="priority" class="block text-sm font-medium text-gray-700 mb-2">Priority *</label>
                                <select id="priority" name="priority" required 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="">Select Priority</option>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    <div>
                                <label for="assigned_to" class="block text-sm font-medium text-gray-700 mb-2">Assigned To</label>
                                <select id="assigned_to" name="assigned_to" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="">Unassigned</option>
                            <?php foreach (getStaff() as $staff): ?>
                                <option value="<?php echo $staff['id']; ?>">
                                    <?php echo htmlspecialchars($staff['name']); ?> (<?php echo htmlspecialchars($staff['role']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                            <div class="md:col-span-2">
                                <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Description *</label>
                                <textarea id="description" name="description" rows="4" required
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                              placeholder="Describe the service request..."></textarea>
                </div>
                    </div>
                </div>
                
                    <!-- Action Buttons -->
                    <div class="flex justify-end space-x-4">
                        <button type="button" onclick="cancelRequest()" 
                                class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                                class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                            <i class="fas fa-plus mr-2"></i>Create Request
                    </button>
                </div>
            </form>
        </div>
        </main>
    </div>

    <script src="../../assets/js/main.js"></script>
    <script src="../../assets/js/requests.js"></script>

<?php include '../../includes/footer.php'; ?>