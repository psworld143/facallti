<?php
session_start();
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

// Include unified header and sidebar
include '../../includes/header-unified.php';
include '../../includes/sidebar-unified.php';
?>

        <!-- Main Content -->
        <main class="lg:ml-64 mt-16 p-4 lg:p-6 flex-1 transition-all duration-300">
            <div class="flex justify-between items-center mb-8">
                <h2 class="text-3xl font-semibold text-gray-800">Service Requests Management</h2>
                <div class="text-right">
                    <div id="current-date" class="text-sm text-gray-600"></div>
                    <div id="current-time" class="text-sm text-gray-600"></div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-clock text-blue-600 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-3xl font-bold text-gray-800"><?php echo $request_stats['pending']; ?></h3>
                            <p class="text-gray-600">Pending Requests</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-check-circle text-green-600 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-3xl font-bold text-gray-800"><?php echo $request_stats['completed']; ?></h3>
                            <p class="text-gray-600">Completed Today</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-exclamation-triangle text-yellow-600 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-3xl font-bold text-gray-800"><?php echo $request_stats['urgent']; ?></h3>
                            <p class="text-gray-600">Urgent Requests</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-star text-purple-600 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-3xl font-bold text-gray-800"><?php echo $request_stats['avg_response_time']; ?>m</h3>
                            <p class="text-gray-600">Avg Response Time</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <h3 class="text-xl font-semibold text-gray-800 mb-6">Quick Actions</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <button onclick="openNewRequestModal()" class="flex items-center p-4 bg-blue-50 border-2 border-blue-200 rounded-lg hover:bg-blue-100 hover:border-blue-300 transition-all duration-300">
                        <i class="fas fa-plus text-blue-600 text-xl mr-3"></i>
                        <span class="font-medium text-blue-800">New Request</span>
                    </button>
                    <button onclick="openBulkAssignModal()" class="flex items-center p-4 bg-green-50 border-2 border-green-200 rounded-lg hover:bg-green-100 hover:border-green-300 transition-all duration-300">
                        <i class="fas fa-users text-green-600 text-xl mr-3"></i>
                        <span class="font-medium text-green-800">Bulk Assign</span>
                    </button>
                    <button onclick="exportRequests()" class="flex items-center p-4 bg-purple-50 border-2 border-purple-200 rounded-lg hover:bg-purple-100 hover:border-purple-300 transition-all duration-300">
                        <i class="fas fa-download text-purple-600 text-xl mr-3"></i>
                        <span class="font-medium text-purple-800">Export Data</span>
                    </button>
                    <button onclick="openSettingsModal()" class="flex items-center p-4 bg-gray-50 border-2 border-gray-200 rounded-lg hover:bg-gray-100 hover:border-gray-300 transition-all duration-300">
                        <i class="fas fa-cog text-gray-600 text-xl mr-3"></i>
                        <span class="font-medium text-gray-800">Settings</span>
                    </button>
                </div>
            </div>

            <!-- Service Requests Table -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <h3 class="text-xl font-semibold text-gray-800">Service Requests</h3>
                        <div class="flex space-x-2">
                            <select id="status-filter" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">All Status</option>
                                <option value="pending">Pending</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                            <select id="priority-filter" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">All Priorities</option>
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                            <select id="type-filter" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">All Types</option>
                                <option value="room_service">Room Service</option>
                                <option value="housekeeping">Housekeeping</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="concierge">Concierge</option>
                                <option value="other">Other</option>
                            </select>
                            <input type="text" id="search-requests" placeholder="Search requests..." 
                                   class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <input type="checkbox" id="select-all" class="rounded border-gray-300">
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Request ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Guest</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Room</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Priority</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assigned To</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200" id="requests-table-body">
                            <?php foreach ($service_requests as $request): ?>
                                <tr class="hover:bg-gray-50" data-request-id="<?php echo $request['id']; ?>">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <input type="checkbox" class="request-checkbox rounded border-gray-300" value="<?php echo $request['id']; ?>">
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">#<?php echo str_pad($request['id'], 4, '0', STR_PAD_LEFT); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($request['guest_name']); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($request['guest_phone']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($request['room_number']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo getRequestTypeBadgeClass($request['request_type']); ?>">
                                            <?php echo getRequestTypeLabel($request['request_type']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo getPriorityBadgeClass($request['priority']); ?>">
                                            <?php echo ucfirst($request['priority']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo getStatusBadgeClass($request['status']); ?>">
                                            <?php echo getStatusLabel($request['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo date('M d, H:i', strtotime($request['created_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo $request['assigned_to_name'] ? htmlspecialchars($request['assigned_to_name']) : 'Unassigned'; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <button onclick="viewRequestDetails(<?php echo $request['id']; ?>)" 
                                                    class="text-blue-600 hover:text-blue-900">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button onclick="editRequest(<?php echo $request['id']; ?>)" 
                                                    class="text-green-600 hover:text-green-900">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="assignRequest(<?php echo $request['id']; ?>)" 
                                                    class="text-purple-600 hover:text-purple-900">
                                                <i class="fas fa-user-plus"></i>
                                            </button>
                                            <button onclick="updateRequestStatus(<?php echo $request['id']; ?>)" 
                                                    class="text-orange-600 hover:text-orange-900">
                                                <i class="fas fa-flag"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- New Request Modal -->
    <div id="new-request-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-8 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold text-gray-900">Create New Service Request</h3>
                <button onclick="closeNewRequestModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="new-request-form" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Guest *</label>
                        <select name="guest_id" id="request_guest_id" required 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md 
                                focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Guest</option>
                            <?php foreach (getGuests() as $guest): ?>
                                <option value="<?php echo $guest['id']; ?>">
                                    <?php echo htmlspecialchars($guest['name']); ?> - Room <?php echo htmlspecialchars($guest['room_number']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Request Type *</label>
                        <select name="request_type" id="request_type" required 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md 
                                focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Type</option>
                            <option value="room_service">Room Service</option>
                            <option value="housekeeping">Housekeeping</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="concierge">Concierge</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Priority *</label>
                        <select name="priority" id="request_priority" required 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md 
                                focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Priority</option>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Assigned To</label>
                        <select name="assigned_to" id="request_assigned_to" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md 
                                focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Unassigned</option>
                            <?php foreach (getStaff() as $staff): ?>
                                <option value="<?php echo $staff['id']; ?>">
                                    <?php echo htmlspecialchars($staff['name']); ?> (<?php echo htmlspecialchars($staff['role']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Description *</label>
                    <textarea name="description" id="request_description" rows="4" required
                              class="w-full px-3 py-2 border border-gray-300 rounded-md 
                              focus:outline-none focus:ring-2 focus:ring-blue-500"
                              placeholder="Describe the service request..."></textarea>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeNewRequestModal()" 
                            class="px-4 py-2 text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                        Create Request
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bulk Assign Modal -->
    <div id="bulk-assign-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-8 max-w-md w-full mx-4">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold text-gray-900">Bulk Assign Requests</h3>
                <button onclick="closeBulkAssignModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="bulk-assign-form" class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Assign To *</label>
                    <select name="bulk_assigned_to" id="bulk_assigned_to" required 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md 
                            focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Select Staff Member</option>
                        <?php foreach (getStaff() as $staff): ?>
                            <option value="<?php echo $staff['id']; ?>">
                                <?php echo htmlspecialchars($staff['name']); ?> (<?php echo htmlspecialchars($staff['role']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Selected Requests</label>
                    <div id="selected-requests-count" class="text-sm text-gray-600">
                        No requests selected
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeBulkAssignModal()" 
                            class="px-4 py-2 text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">
                        Assign Requests
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Settings Modal -->
    <div id="settings-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-8 max-w-lg w-full mx-4">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold text-gray-900">Settings</h3>
                <button onclick="closeSettingsModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="settings-form" class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Auto-refresh Interval (seconds)</label>
                    <input type="number" name="refresh_interval" id="refresh_interval" min="30" max="300" value="60"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md 
                           focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="flex items-center">
                        <input type="checkbox" name="auto_refresh" id="auto_refresh" class="rounded border-gray-300 mr-2">
                        <span class="text-sm font-medium text-gray-700">Enable Auto-refresh</span>
                    </label>
                </div>
                
                <div>
                    <label class="flex items-center">
                        <input type="checkbox" name="notifications" id="notifications" class="rounded border-gray-300 mr-2">
                        <span class="text-sm font-medium text-gray-700">Enable Browser Notifications</span>
                    </label>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeSettingsModal()" 
                            class="px-4 py-2 text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                        Save Settings
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Request Details Modal -->
    <div id="request-details-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-8 max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold text-gray-900">Request Details</h3>
                <button onclick="closeRequestDetailsModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div id="request-details-content" class="space-y-6">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>

    <!-- Status Update Modal -->
    <div id="status-update-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-8 max-w-md w-full mx-4">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold text-gray-900">Update Request Status</h3>
                <button onclick="closeStatusUpdateModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="status-update-form" class="space-y-6">
                <input type="hidden" id="status_request_id" name="request_id">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">New Status *</label>
                    <select name="new_status" id="new_status" required 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md 
                            focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Select Status</option>
                        <option value="pending">Pending</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                    <textarea name="status_notes" id="status_notes" rows="3"
                              class="w-full px-3 py-2 border border-gray-300 rounded-md 
                              focus:outline-none focus:ring-2 focus:ring-blue-500"
                              placeholder="Add any notes about this status change..."></textarea>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeStatusUpdateModal()" 
                            class="px-4 py-2 text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-orange-600 text-white rounded-md hover:bg-orange-700 transition-colors">
                        Update Status
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Success/Error Toast -->
    <div id="toast" class="fixed top-4 right-4 z-50 hidden">
        <div class="bg-white rounded-lg shadow-lg p-4 max-w-sm">
            <div class="flex items-center">
                <div id="toast-icon" class="mr-3"></div>
                <div>
                    <div id="toast-title" class="font-medium"></div>
                    <div id="toast-message" class="text-sm text-gray-600"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let refreshInterval;
        let selectedRequests = new Set();

        // Initialize page functionality
        document.addEventListener('DOMContentLoaded', function() {
            updateDateTime();
            setInterval(updateDateTime, 1000);
            setupEventListeners();
        });

        // Update date and time
        function updateDateTime() {
            const now = new Date();
            document.getElementById('current-date').textContent = now.toLocaleDateString();
            document.getElementById('current-time').textContent = now.toLocaleTimeString();
        }

        // Setup event listeners
        function setupEventListeners() {
            // Search and filter functionality
            document.getElementById('search-requests').addEventListener('input', filterRequests);
            document.getElementById('status-filter').addEventListener('change', filterRequests);
            document.getElementById('priority-filter').addEventListener('change', filterRequests);
            document.getElementById('type-filter').addEventListener('change', filterRequests);

            // Select all checkbox
            document.getElementById('select-all').addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('.request-checkbox');
                checkboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                    if (this.checked) {
                        selectedRequests.add(checkbox.value);
                    } else {
                        selectedRequests.delete(checkbox.value);
                    }
                });
                updateSelectedCount();
            });

            // Individual checkboxes
            document.querySelectorAll('.request-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    if (this.checked) {
                        selectedRequests.add(this.value);
                    } else {
                        selectedRequests.delete(this.value);
                    }
                    updateSelectedCount();
                });
            });

            // Form submissions
            document.getElementById('new-request-form').addEventListener('submit', handleNewRequest);
            document.getElementById('bulk-assign-form').addEventListener('submit', handleBulkAssign);
            document.getElementById('settings-form').addEventListener('submit', handleSettings);
            document.getElementById('status-update-form').addEventListener('submit', handleStatusUpdate);
        }

        // Filter requests
        function filterRequests() {
            const searchTerm = document.getElementById('search-requests').value.toLowerCase();
            const statusFilter = document.getElementById('status-filter').value;
            const priorityFilter = document.getElementById('priority-filter').value;
            const typeFilter = document.getElementById('type-filter').value;

            const rows = document.querySelectorAll('#requests-table-body tr');

            rows.forEach(row => {
                const requestId = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
                const guestName = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
                const status = row.querySelector('td:nth-child(7) span').textContent.toLowerCase();
                const priority = row.querySelector('td:nth-child(6) span').textContent.toLowerCase();
                const type = row.querySelector('td:nth-child(5) span').textContent.toLowerCase();

                const matchesSearch = requestId.includes(searchTerm) || guestName.includes(searchTerm);
                const matchesStatus = !statusFilter || status.includes(statusFilter);
                const matchesPriority = !priorityFilter || priority.includes(priorityFilter);
                const matchesType = !typeFilter || type.includes(typeFilter);

                if (matchesSearch && matchesStatus && matchesPriority && matchesType) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // Update selected count
        function updateSelectedCount() {
            const count = selectedRequests.size;
            const countElement = document.getElementById('selected-requests-count');
            if (count === 0) {
                countElement.textContent = 'No requests selected';
            } else {
                countElement.textContent = `${count} request(s) selected`;
            }
        }

        // Modal functions
        function openNewRequestModal() {
            document.getElementById('new-request-modal').classList.remove('hidden');
        }

        function closeNewRequestModal() {
            document.getElementById('new-request-modal').classList.add('hidden');
            document.getElementById('new-request-form').reset();
        }

        function openBulkAssignModal() {
            alert('Bulk assign functionality coming soon!');
        }

        function closeBulkAssignModal() {
            document.getElementById('bulk-assign-modal').classList.add('hidden');
            document.getElementById('bulk-assign-form').reset();
        }

        function openSettingsModal() {
            alert('Settings functionality coming soon!');
        }

        function closeSettingsModal() {
            document.getElementById('settings-modal').classList.add('hidden');
        }

        function closeRequestDetailsModal() {
            document.getElementById('request-details-modal').classList.add('hidden');
        }

        function closeStatusUpdateModal() {
            document.getElementById('status-update-modal').classList.add('hidden');
            document.getElementById('status-update-form').reset();
        }

        // Request management functions
        function viewRequestDetails(requestId) {
            alert('View details for request #' + requestId);
        }

        function editRequest(requestId) {
            alert('Edit request #' + requestId);
        }

        function assignRequest(requestId) {
            alert('Assign request #' + requestId);
        }

        function updateRequestStatus(requestId) {
            alert('Update status for request #' + requestId);
        }

        function exportRequests() {
            alert('Export functionality coming soon!');
        }

        // Form handlers
        function handleNewRequest(e) {
            e.preventDefault();
            const formData = new FormData(e.target);

            fetch('../../api/create-request.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('success', 'Success', 'Service request created successfully.');
                    closeNewRequestModal();
                    location.reload();
                } else {
                    showToast('error', 'Error', data.message || 'Failed to create request.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('error', 'Error', 'Failed to create request.');
            });
        }

        function handleBulkAssign(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            formData.append('request_ids', Array.from(selectedRequests).join(','));

            fetch('../../api/bulk-assign-requests.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('success', 'Success', 'Requests assigned successfully.');
                    closeBulkAssignModal();
                    selectedRequests.clear();
                    updateSelectedCount();
                    location.reload();
                } else {
                    showToast('error', 'Error', data.message || 'Failed to assign requests.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('error', 'Error', 'Failed to assign requests.');
            });
        }

        function handleStatusUpdate(e) {
            e.preventDefault();
            const formData = new FormData(e.target);

            fetch('../../api/update-request-status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('success', 'Success', 'Request status updated successfully.');
                    closeStatusUpdateModal();
                    location.reload();
                } else {
                    showToast('error', 'Error', data.message || 'Failed to update status.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('error', 'Error', 'Failed to update status.');
            });
        }

        function handleSettings(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            const settings = {
                refresh_interval: formData.get('refresh_interval'),
                auto_refresh: formData.get('auto_refresh') === 'on',
                notifications: formData.get('notifications') === 'on'
            };

            localStorage.setItem('requestSettings', JSON.stringify(settings));
            setupAutoRefresh();
            showToast('success', 'Success', 'Settings saved successfully.');
            closeSettingsModal();
        }

        // Settings management
        function loadSettings() {
            const settings = JSON.parse(localStorage.getItem('requestSettings') || '{}');
            
            if (settings.refresh_interval) {
                document.getElementById('refresh_interval').value = settings.refresh_interval;
            }
            if (settings.auto_refresh !== undefined) {
                document.getElementById('auto_refresh').checked = settings.auto_refresh;
            }
            if (settings.notifications !== undefined) {
                document.getElementById('notifications').checked = settings.notifications;
            }
        }

        function setupAutoRefresh() {
            const settings = JSON.parse(localStorage.getItem('requestSettings') || '{}');
            
            if (refreshInterval) {
                clearInterval(refreshInterval);
            }

            if (settings.auto_refresh && settings.refresh_interval) {
                refreshInterval = setInterval(() => {
                    location.reload();
                }, settings.refresh_interval * 1000);
            }
        }

        // Toast notification
        function showToast(type, title, message) {
            const toast = document.getElementById('toast');
            const toastIcon = document.getElementById('toast-icon');
            const toastTitle = document.getElementById('toast-title');
            const toastMessage = document.getElementById('toast-message');

            // Set icon and colors based on type
            let iconClass, bgColor;
            switch (type) {
                case 'success':
                    iconClass = 'fas fa-check-circle text-green-500';
                    bgColor = 'border-l-4 border-green-500';
                    break;
                case 'error':
                    iconClass = 'fas fa-exclamation-circle text-red-500';
                    bgColor = 'border-l-4 border-red-500';
                    break;
                case 'warning':
                    iconClass = 'fas fa-exclamation-triangle text-yellow-500';
                    bgColor = 'border-l-4 border-yellow-500';
                    break;
                default:
                    iconClass = 'fas fa-info-circle text-blue-500';
                    bgColor = 'border-l-4 border-blue-500';
            }

            toastIcon.className = iconClass;
            toast.className = `fixed top-4 right-4 z-50 ${bgColor}`;
            toastTitle.textContent = title;
            toastMessage.textContent = message;

            toast.classList.remove('hidden');

            // Auto-hide after 5 seconds
            setTimeout(() => {
                toast.classList.add('hidden');
            }, 5000);
        }

        // Request browser notifications permission
        if ('Notification' in window) {
            Notification.requestPermission();
        }
    </script>

<?php include '../../includes/footer.php'; ?>