<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has housekeeping access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['housekeeping', 'manager'])) {
    header('Location: ../../login.php');
    exit();
}

// Get room status data
$room_status_overview = getRoomStatusOverview();
$all_rooms = getAllRoomsWithStatus();
$housekeeping_status_options = getHousekeepingStatusOptions();

// Set page title
$page_title = 'Room Status';

// Include unified header and sidebar
include '../../includes/header-unified.php';
include '../../includes/sidebar-unified.php';
?>

        <!-- Main Content -->
        <main class="lg:ml-64 mt-16 p-4 lg:p-6 flex-1 transition-all duration-300">
            <div class="flex justify-between items-center mb-8">
                <h2 class="text-3xl font-semibold text-gray-800">Room Status Management</h2>
                <div class="text-right">
                    <div id="current-date" class="text-sm text-gray-600"></div>
                    <div id="current-time" class="text-sm text-gray-600"></div>
                </div>
            </div>

            <!-- Room Status Overview -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <h3 class="text-xl font-semibold text-gray-800 mb-6">Room Status Overview</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <?php foreach ($room_status_overview as $overview): ?>
                        <div class="bg-gradient-to-r from-blue-50 to-blue-100 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="text-lg font-semibold text-blue-800"><?php echo htmlspecialchars($overview['room_type']); ?></h4>
                                <span class="text-2xl font-bold text-blue-600"><?php echo $overview['total']; ?></span>
                            </div>
                            <div class="space-y-1 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-green-600">Available:</span>
                                    <span class="font-medium"><?php echo $overview['available']; ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-red-600">Occupied:</span>
                                    <span class="font-medium"><?php echo $overview['occupied']; ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-yellow-600">Reserved:</span>
                                    <span class="font-medium"><?php echo $overview['reserved']; ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-orange-600">Maintenance:</span>
                                    <span class="font-medium"><?php echo $overview['maintenance']; ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Room List -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <h3 class="text-xl font-semibold text-gray-800">All Rooms</h3>
                        <div class="flex space-x-2">
                            <select id="status-filter" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">All Status</option>
                                <option value="available">Available</option>
                                <option value="occupied">Occupied</option>
                                <option value="reserved">Reserved</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="out_of_service">Out of Service</option>
                            </select>
                            <select id="housekeeping-filter" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">All Housekeeping Status</option>
                                <?php foreach ($housekeeping_status_options as $key => $value): ?>
                                    <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" id="search-room" placeholder="Search rooms..." 
                                   class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Room</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Housekeeping</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rate</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200" id="rooms-table-body">
                            <?php foreach ($all_rooms as $room): ?>
                                <tr class="hover:bg-gray-50" data-room-id="<?php echo $room['id']; ?>">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($room['room_number']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($room['room_type']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo getStatusBadgeClass($room['status']); ?>">
                                            <?php echo getStatusLabel($room['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo getHousekeepingStatusBadgeClass($room['housekeeping_status']); ?>">
                                            <?php echo getHousekeepingStatusLabel($room['housekeeping_status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        $<?php echo number_format($room['rate'], 2); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <button onclick="updateHousekeepingStatus(<?php echo $room['id']; ?>)" 
                                                    class="text-blue-600 hover:text-blue-900">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="viewRoomDetails(<?php echo $room['id']; ?>)" 
                                                    class="text-green-600 hover:text-green-900">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button onclick="createMaintenanceRequest(<?php echo $room['id']; ?>)" 
                                                    class="text-orange-600 hover:text-orange-900">
                                                <i class="fas fa-tools"></i>
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

    <!-- Update Housekeeping Status Modal -->
    <div id="update-status-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-8 max-w-md w-full mx-4">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold text-gray-900">Update Housekeeping Status</h3>
                <button onclick="closeUpdateStatusModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="update-status-form" class="space-y-6">
                <input type="hidden" id="room_id" name="room_id">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Room</label>
                    <input type="text" id="room_number_display" readonly 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Housekeeping Status *</label>
                    <select name="housekeeping_status" id="housekeeping_status" required 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Select Status</option>
                        <?php foreach ($housekeeping_status_options as $key => $value): ?>
                            <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                    <textarea name="notes" id="status_notes" rows="3" 
                              placeholder="Additional notes about the room status..."
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                </div>

                <div class="flex justify-end space-x-4">
                    <button type="button" onclick="closeUpdateStatusModal()" 
                            class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                        <i class="fas fa-save mr-2"></i>Update Status
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Maintenance Request Modal -->
    <div id="maintenance-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-8 max-w-md w-full mx-4">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold text-gray-900">Create Maintenance Request</h3>
                <button onclick="closeMaintenanceModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="maintenance-form" class="space-y-6">
                <input type="hidden" id="maintenance_room_id" name="room_id">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Room</label>
                    <input type="text" id="maintenance_room_number" readonly 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Issue Type *</label>
                    <select name="issue_type" id="issue_type" required 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Select Issue Type</option>
                        <option value="plumbing">Plumbing</option>
                        <option value="electrical">Electrical</option>
                        <option value="hvac">HVAC</option>
                        <option value="furniture">Furniture</option>
                        <option value="appliances">Appliances</option>
                        <option value="structural">Structural</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Priority *</label>
                    <select name="priority" id="priority" required 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Select Priority</option>
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Description *</label>
                    <textarea name="description" id="maintenance_description" rows="4" required 
                              placeholder="Describe the maintenance issue..."
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                </div>

                <div class="flex justify-end space-x-4">
                    <button type="button" onclick="closeMaintenanceModal()" 
                            class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-6 py-2 bg-orange-600 text-white rounded-md hover:bg-orange-700 transition-colors">
                        <i class="fas fa-tools mr-2"></i>Create Request
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="../../assets/js/main.js"></script>
    <script src="../../assets/js/room-status.js"></script>
    
    <?php include '../../includes/footer.php'; ?>

<?php
// Helper functions for status badges and labels
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'available': return 'bg-green-100 text-green-800';
        case 'occupied': return 'bg-red-100 text-red-800';
        case 'reserved': return 'bg-yellow-100 text-yellow-800';
        case 'maintenance': return 'bg-blue-100 text-blue-800';
        case 'out_of_service': return 'bg-gray-100 text-gray-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

function getStatusLabel($status) {
    switch ($status) {
        case 'available': return 'Available';
        case 'occupied': return 'Occupied';
        case 'reserved': return 'Reserved';
        case 'maintenance': return 'Maintenance';
        case 'out_of_service': return 'Out of Service';
        default: return ucfirst($status);
    }
}

function getHousekeepingStatusBadgeClass($status) {
    switch ($status) {
        case 'clean': return 'bg-green-100 text-green-800';
        case 'dirty': return 'bg-red-100 text-red-800';
        case 'cleaning': return 'bg-yellow-100 text-yellow-800';
        case 'maintenance': return 'bg-blue-100 text-blue-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

function getHousekeepingStatusLabel($status) {
    switch ($status) {
        case 'clean': return 'Clean';
        case 'dirty': return 'Dirty';
        case 'cleaning': return 'Cleaning';
        case 'maintenance': return 'Maintenance';
        default: return 'Unknown';
    }
}
?>
