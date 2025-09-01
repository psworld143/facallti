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

// Get reservation ID from URL
$reservation_id = $_GET['id'] ?? null;
if (!$reservation_id) {
    header('Location: manage-reservations.php');
    exit();
}

// Get reservation details
$reservation = getReservationDetails($reservation_id);
if (!$reservation) {
    header('Location: manage-reservations.php');
    exit();
}

// Get available rooms for transfer/upgrade
$available_rooms = getAvailableRooms();
$room_types = getRoomTypes();

// Set page title
$page_title = 'Modify Reservation';

// Include unified navigation (automatically selects based on user role)
include '../../includes/header-unified.php';
include '../../includes/sidebar-unified.php';
?>

        <!-- Main Content -->
        <main class="ml-64 mt-16 p-6 flex-1">
            <div class="flex justify-between items-center mb-8">
                <h2 class="text-3xl font-semibold text-gray-800">Modify Reservation</h2>
                <div class="text-right">
                    <div class="text-sm text-gray-600">Reservation #<?php echo htmlspecialchars($reservation['reservation_number']); ?></div>
                    <div class="text-sm text-gray-600">Status: <span class="font-medium <?php echo $reservation['status'] === 'confirmed' ? 'text-green-600' : 'text-blue-600'; ?>"><?php echo ucfirst($reservation['status']); ?></span></div>
                </div>
            </div>

            <!-- Reservation Summary -->
            <div class="bg-white rounded-lg p-6 shadow-md mb-8">
            <h3 class="text-xl font-semibold text-gray-800 mb-6">Reservation Details</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-gradient-to-r from-blue-400 to-blue-600 rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-user text-white text-xl"></i>
                    </div>
                    <div>
                        <h4 class="text-lg font-bold text-gray-800"><?php echo htmlspecialchars($reservation['first_name'] . ' ' . $reservation['last_name']); ?></h4>
                        <p class="text-gray-600">Guest</p>
                    </div>
                </div>
                
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-gradient-to-r from-green-400 to-green-600 rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-bed text-white text-xl"></i>
                    </div>
                    <div>
                        <h4 class="text-lg font-bold text-gray-800"><?php echo htmlspecialchars($reservation['room_number']); ?></h4>
                        <p class="text-gray-600"><?php echo ucfirst($reservation['room_type']); ?></p>
                    </div>
                </div>
                
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-gradient-to-r from-purple-400 to-purple-600 rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-calendar-alt text-white text-xl"></i>
                    </div>
                    <div>
                        <h4 class="text-lg font-bold text-gray-800"><?php echo date('M d', strtotime($reservation['check_in_date'])); ?> - <?php echo date('M d', strtotime($reservation['check_out_date'])); ?></h4>
                        <p class="text-gray-600">Stay Duration</p>
                    </div>
                </div>
                
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-gradient-to-r from-orange-400 to-orange-600 rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-dollar-sign text-white text-xl"></i>
                    </div>
                    <div>
                        <h4 class="text-lg font-bold text-gray-800">₱<?php echo number_format($reservation['total_amount'], 2); ?></h4>
                        <p class="text-gray-600">Total Amount</p>
                    </div>
                </div>
            </div>
        </div>

            <!-- Modification Options -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Basic Information Modification -->
                <div class="bg-white rounded-lg p-6 shadow-md">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Basic Information</h3>
                <form id="basic-info-form" class="space-y-4">
                    <input type="hidden" name="reservation_id" value="<?php echo $reservation_id; ?>">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                            <input type="text" name="first_name" value="<?php echo htmlspecialchars($reservation['first_name']); ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                            <input type="text" name="last_name" value="<?php echo htmlspecialchars($reservation['last_name']); ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($reservation['email'] ?? ''); ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($reservation['phone']); ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Check-in Date</label>
                            <input type="date" name="check_in_date" value="<?php echo $reservation['check_in_date']; ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Check-out Date</label>
                            <input type="date" name="check_out_date" value="<?php echo $reservation['check_out_date']; ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Adults</label>
                            <input type="number" name="adults" value="<?php echo $reservation['adults']; ?>" min="1" max="10" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Children</label>
                            <input type="number" name="children" value="<?php echo $reservation['children'] ?? 0; ?>" min="0" max="10" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Special Requests</label>
                        <textarea name="special_requests" rows="3" 
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary"><?php echo htmlspecialchars($reservation['special_requests'] ?? ''); ?></textarea>
                    </div>
                    
                    <button type="submit" class="w-full bg-primary text-white py-2 px-4 rounded-md hover:bg-primary-dark transition-colors">
                        <i class="fas fa-save mr-2"></i>Update Basic Information
                    </button>
                </form>
            </div>

            <!-- Room Management -->
            <div class="bg-white rounded-lg p-6 shadow-md">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Room Management</h3>
                
                <!-- Current Room Info -->
                <div class="bg-gray-50 rounded-lg p-4 mb-4">
                    <h4 class="font-medium text-gray-900 mb-2">Current Room</h4>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-gray-500">Room Number:</span>
                            <span class="font-medium"><?php echo htmlspecialchars($reservation['room_number']); ?></span>
                        </div>
                        <div>
                            <span class="text-gray-500">Type:</span>
                            <span class="font-medium"><?php echo ucfirst($reservation['room_type']); ?></span>
                        </div>
                        <div>
                            <span class="text-gray-500">Rate:</span>
                            <span class="font-medium">₱<?php echo number_format($room_types[$reservation['room_type']]['rate'], 2); ?>/night</span>
                        </div>
                        <div>
                            <span class="text-gray-500">Status:</span>
                            <span class="font-medium text-green-600"><?php echo ucfirst($reservation['room_status']); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Room Transfer -->
                <div class="border-t pt-4 mb-4">
                    <h4 class="font-medium text-gray-900 mb-3">Room Transfer</h4>
                    <form id="room-transfer-form" class="space-y-3">
                        <input type="hidden" name="reservation_id" value="<?php echo $reservation_id; ?>">
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">New Room Type</label>
                            <select name="new_room_type" id="new-room-type" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                                <option value="">Select Room Type</option>
                                <?php foreach ($room_types as $type => $info): ?>
                                <option value="<?php echo $type; ?>" <?php echo $type === $reservation['room_type'] ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($type); ?> - ₱<?php echo number_format($info['rate'], 2); ?>/night
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Specific Room (Optional)</label>
                            <select name="new_room_id" id="new-room-select" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                                <option value="">Auto-assign best available</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Transfer Reason</label>
                            <textarea name="transfer_reason" rows="2" placeholder="Reason for room transfer..." 
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary"></textarea>
                        </div>
                        
                        <button type="submit" class="w-full bg-warning text-white py-2 px-4 rounded-md hover:bg-yellow-600 transition-colors">
                            <i class="fas fa-exchange-alt mr-2"></i>Transfer Room
                        </button>
                    </form>
                </div>

                <!-- Room Upgrade -->
                <div class="border-t pt-4">
                    <h4 class="font-medium text-gray-900 mb-3">Room Upgrade</h4>
                    <form id="room-upgrade-form" class="space-y-3">
                        <input type="hidden" name="reservation_id" value="<?php echo $reservation_id; ?>">
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Upgrade To</label>
                            <select name="upgrade_room_type" id="upgrade-room-type" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                                <option value="">Select Upgrade</option>
                                <?php 
                                $current_rate = $room_types[$reservation['room_type']]['rate'];
                                foreach ($room_types as $type => $info): 
                                    if ($info['rate'] > $current_rate):
                                ?>
                                <option value="<?php echo $type; ?>">
                                    <?php echo ucfirst($type); ?> - ₱<?php echo number_format($info['rate'], 2); ?>/night 
                                    (+₱<?php echo number_format($info['rate'] - $current_rate, 2); ?>)
                                </option>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Upgrade Reason</label>
                            <textarea name="upgrade_reason" rows="2" placeholder="Reason for upgrade..." 
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary"></textarea>
                        </div>
                        
                        <div class="flex items-center">
                            <input type="checkbox" name="charge_upgrade" id="charge-upgrade" class="mr-2">
                            <label for="charge-upgrade" class="text-sm text-gray-700">Charge guest for upgrade</label>
                        </div>
                        
                        <button type="submit" class="w-full bg-success text-white py-2 px-4 rounded-md hover:bg-green-600 transition-colors">
                            <i class="fas fa-arrow-up mr-2"></i>Upgrade Room
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Overbooking Management -->
        <div class="bg-white rounded-lg p-6 shadow-md mb-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Overbooking Management</h3>
            
            <div id="overbooking-status" class="mb-4">
                <!-- Overbooking status will be loaded here -->
            </div>
            
            <div id="overbooking-actions" class="hidden">
                <h4 class="font-medium text-gray-900 mb-3">Resolution Options</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <button onclick="handleOverbooking('walk')" class="p-4 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                        <i class="fas fa-walking text-2xl text-gray-400 mb-2"></i>
                        <h5 class="font-medium text-gray-900">Walk Guest</h5>
                        <p class="text-sm text-gray-500">Find alternative accommodation</p>
                    </button>
                    <button onclick="handleOverbooking('upgrade')" class="p-4 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                        <i class="fas fa-arrow-up text-2xl text-gray-400 mb-2"></i>
                        <h5 class="font-medium text-gray-900">Upgrade Guest</h5>
                        <p class="text-sm text-gray-500">Move to higher category room</p>
                    </button>
                    <button onclick="handleOverbooking('compensation')" class="p-4 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                        <i class="fas fa-gift text-2xl text-gray-400 mb-2"></i>
                        <h5 class="font-medium text-gray-900">Offer Compensation</h5>
                        <p class="text-sm text-gray-500">Provide discounts or amenities</p>
                    </button>
                </div>
            </div>
        </div>

        <!-- Group Booking Management -->
        <div class="bg-white rounded-lg p-6 shadow-md mb-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Group Booking Management</h3>
            
            <div class="mb-4">
                <button onclick="showGroupBookingModal()" class="bg-primary text-white py-2 px-4 rounded-md hover:bg-primary-dark transition-colors">
                    <i class="fas fa-users mr-2"></i>Add to Group Booking
                </button>
            </div>
            
            <div id="group-booking-info" class="hidden">
                <h4 class="font-medium text-gray-900 mb-2">Group Information</h4>
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                        <div>
                            <span class="text-gray-500">Group Name:</span>
                            <span class="font-medium" id="group-name"></span>
                        </div>
                        <div>
                            <span class="text-gray-500">Group Size:</span>
                            <span class="font-medium" id="group-size"></span>
                        </div>
                        <div>
                            <span class="text-gray-500">Group Discount:</span>
                            <span class="font-medium" id="group-discount"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="bg-white rounded-lg p-6 shadow-md">
            <h3 class="text-xl font-semibold text-gray-800 mb-6">Quick Actions</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <a href="manage-reservations.php" class="flex items-center p-4 bg-gray-50 border-2 border-gray-200 rounded-lg hover:bg-gray-100 hover:border-gray-300 transition-all duration-300">
                    <i class="fas fa-arrow-left text-gray-600 text-xl mr-3"></i>
                    <span class="font-medium text-gray-800">Back to Reservations</span>
                </a>
                <button onclick="showCancelModal()" class="flex items-center p-4 bg-red-50 border-2 border-red-200 rounded-lg hover:bg-red-100 hover:border-red-300 transition-all duration-300">
                    <i class="fas fa-times text-red-600 text-xl mr-3"></i>
                    <span class="font-medium text-red-800">Cancel Reservation</span>
                </button>
                <button onclick="printReservation()" class="flex items-center p-4 bg-blue-50 border-2 border-blue-200 rounded-lg hover:bg-blue-100 hover:border-blue-300 transition-all duration-300">
                    <i class="fas fa-print text-blue-600 text-xl mr-3"></i>
                    <span class="font-medium text-blue-800">Print Reservation</span>
                </button>
            </div>
        </div>
    </main>

    <!-- Group Booking Modal -->
    <div id="group-booking-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-8 max-w-md w-full mx-4">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold text-gray-900">Add to Group Booking</h3>
                <button onclick="closeGroupBookingModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <form id="group-booking-form" class="space-y-4">
                <input type="hidden" name="reservation_id" value="<?php echo $reservation_id; ?>">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Group Name</label>
                    <input type="text" name="group_name" required 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Group Size</label>
                    <input type="number" name="group_size" min="2" required 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Group Discount (%)</label>
                    <input type="number" name="group_discount" min="0" max="50" value="10" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
                
                <div class="flex justify-end space-x-4">
                    <button type="button" onclick="closeGroupBookingModal()" 
                            class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-primary text-white rounded-md hover:bg-primary-dark transition-colors">
                        Add to Group
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Cancel Confirmation Modal -->
    <div id="cancel-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-8 max-w-md w-full mx-4">
            <div class="text-center">
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Cancel Reservation</h3>
                <p class="text-gray-600 mb-4">Are you sure you want to cancel this reservation? This action cannot be undone.</p>
                <div class="space-y-2 text-sm text-gray-600 mb-6">
                    <p><strong>Reservation:</strong> <?php echo htmlspecialchars($reservation['reservation_number']); ?></p>
                    <p><strong>Guest:</strong> <?php echo htmlspecialchars($reservation['first_name'] . ' ' . $reservation['last_name']); ?></p>
                </div>
                <div class="flex justify-center space-x-4">
                    <button onclick="closeCancelModal()" 
                            class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                        Keep Reservation
                    </button>
                    <button onclick="confirmCancelReservation()" 
                            class="px-6 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors">
                        <i class="fas fa-times mr-2"></i>Cancel Reservation
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../../assets/js/main.js"></script>
    <script src="../../assets/js/modify-reservation.js"></script>
    
    <?php include '../../includes/footer.php'; ?>
