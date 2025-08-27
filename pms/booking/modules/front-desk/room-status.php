<?php
require_once '../../includes/session-config.php';
session_start();
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has front desk access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['front_desk', 'manager'])) {
    header('Location: ../../login.php');
    exit();
}

// Get room status data
$room_status_overview = getRoomStatusOverview();
$all_rooms = getAllRoomsWithStatus();
$room_status_options = getRoomStatusOptions();

// Set page title
$page_title = 'Room Status';

// Include unified header and sidebar
include '../../includes/header-unified.php';
include '../../includes/sidebar-unified.php';
?>

        <!-- Main Content -->
        <main class="lg:ml-64 mt-16 p-4 lg:p-6 flex-1 transition-all duration-300">
            <div class="flex justify-between items-center mb-8">
                <h2 class="text-3xl font-semibold text-gray-800">Room Status Overview</h2>
                <div class="text-right">
                    <div id="current-date" class="text-sm text-gray-600"></div>
                    <div id="current-time" class="text-sm text-gray-600"></div>
                </div>
            </div>

            <!-- Room Status Overview -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <h3 class="text-xl font-semibold text-gray-800 mb-6">Room Status Summary</h3>
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
                                <?php foreach ($room_status_options as $key => $value): ?>
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
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current Guest</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Check-in</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Check-out</th>
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
                                        <div class="text-sm text-gray-900" id="guest-<?php echo $room['id']; ?>">
                                            <?php echo getCurrentGuest($room['id']); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" id="checkin-<?php echo $room['id']; ?>">
                                        <?php echo getCheckInDate($room['id']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" id="checkout-<?php echo $room['id']; ?>">
                                        <?php echo getCheckOutDate($room['id']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        $<?php echo number_format($room['rate'], 2); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <button onclick="viewRoomDetails(<?php echo $room['id']; ?>)" 
                                                    class="text-blue-600 hover:text-blue-900">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if ($room['status'] === 'available'): ?>
                                                <button onclick="assignRoom(<?php echo $room['id']; ?>)" 
                                                        class="text-green-600 hover:text-green-900">
                                                    <i class="fas fa-user-plus"></i>
                                                </button>
                                            <?php endif; ?>
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

    <script src="../../assets/js/main.js"></script>
    <script src="../../assets/js/front-desk-room-status.js"></script>
    
    <?php include '../../includes/footer.php'; ?>

<?php
// Helper functions are now in functions.php
?>
