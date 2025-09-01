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

// Get available rooms
$available_rooms = getAvailableRooms();
$room_types = getRoomTypes();

// Set page title
$page_title = 'New Reservation';
$page_subtitle = 'Create a new guest reservation';

// Include unified navigation (automatically selects based on user role)
include '../../includes/header-unified.php';
include '../../includes/sidebar-unified.php';
?>

        <!-- Main Content -->
        <main class="lg:ml-64 mt-16 p-4 lg:p-6 flex-1 transition-all duration-300">
            <div class="flex justify-between items-center mb-8">
                <h2 class="text-3xl font-semibold text-gray-800">New Reservation</h2>
                <div class="text-right">
                    <div id="current-date" class="text-sm text-gray-600"></div>
                    <div id="current-time" class="text-sm text-gray-600"></div>
                </div>
            </div>

            <div class="bg-white rounded-lg p-6 shadow-md">
                <h3 class="text-xl font-semibold text-gray-800 mb-6">Create New Reservation</h3>
                    
                    <form id="reservation-form" class="space-y-6">
                        <!-- Guest Information -->
                        <div class="border-b border-gray-200 pb-6">
                            <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                                <i class="fas fa-user mr-2 text-primary"></i>Guest Information
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <div>
                                    <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2">First Name *</label>
                                    <input type="text" id="first_name" name="first_name" required 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                </div>
                                <div>
                                    <label for="last_name" class="block text-sm font-medium text-gray-700 mb-2">Last Name *</label>
                                    <input type="text" id="last_name" name="last_name" required 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                </div>
                                <div>
                                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                                    <input type="email" id="email" name="email" 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                </div>
                                <div>
                                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">Phone *</label>
                                    <input type="tel" id="phone" name="phone" required 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                </div>
                                <div>
                                    <label for="id_type" class="block text-sm font-medium text-gray-700 mb-2">ID Type *</label>
                                    <select id="id_type" name="id_type" required 
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        <option value="">Select ID Type</option>
                                        <option value="passport">Passport</option>
                                        <option value="driver_license">Driver's License</option>
                                        <option value="national_id">National ID</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="id_number" class="block text-sm font-medium text-gray-700 mb-2">ID Number *</label>
                                    <input type="text" id="id_number" name="id_number" required 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                </div>
                                <div class="md:col-span-2 lg:col-span-3">
                                    <label for="address" class="block text-sm font-medium text-gray-700 mb-2">Address</label>
                                    <textarea id="address" name="address" rows="3" 
                                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                                </div>
                                <div class="flex items-center md:col-span-2 lg:col-span-3">
                                    <input type="checkbox" id="is_vip" name="is_vip" class="h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded">
                                    <label for="is_vip" class="ml-2 block text-sm text-gray-900">VIP Guest</label>
                                </div>
                            </div>
                        </div>

                        <!-- Reservation Details -->
                        <div class="border-b border-gray-200 pb-6">
                            <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                                <i class="fas fa-calendar-alt mr-2 text-primary"></i>Reservation Details
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <div>
                                    <label for="check_in_date" class="block text-sm font-medium text-gray-700 mb-2">Check-in Date *</label>
                                    <input type="date" id="check_in_date" name="check_in_date" required 
                                           min="<?php echo date('Y-m-d'); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                </div>
                                <div>
                                    <label for="check_out_date" class="block text-sm font-medium text-gray-700 mb-2">Check-out Date *</label>
                                    <input type="date" id="check_out_date" name="check_out_date" required 
                                           min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                </div>
                                <div>
                                    <label for="adults" class="block text-sm font-medium text-gray-700 mb-2">Number of Adults *</label>
                                    <input type="number" id="adults" name="adults" min="1" max="10" value="1" required 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                </div>
                                <div>
                                    <label for="children" class="block text-sm font-medium text-gray-700 mb-2">Number of Children</label>
                                    <input type="number" id="children" name="children" min="0" max="10" value="0" 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                </div>
                                <div>
                                    <label for="room_type" class="block text-sm font-medium text-gray-700 mb-2">Room Type *</label>
                                    <select id="room_type" name="room_type" required 
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        <option value="">Select Room Type</option>
                                        <?php foreach ($room_types as $type => $info): ?>
                                        <option value="<?php echo $type; ?>" data-rate="<?php echo $info['rate']; ?>">
                                            <?php echo $info['name']; ?> - $<?php echo $info['rate']; ?>/night
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="booking_source" class="block text-sm font-medium text-gray-700 mb-2">Booking Source *</label>
                                    <select id="booking_source" name="booking_source" required 
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        <option value="walk_in">Walk-in</option>
                                        <option value="online">Online</option>
                                        <option value="phone">Phone</option>
                                        <option value="travel_agent">Travel Agent</option>
                                    </select>
                                </div>
                                <div class="md:col-span-2 lg:col-span-3">
                                    <label for="special_requests" class="block text-sm font-medium text-gray-700 mb-2">Special Requests</label>
                                    <textarea id="special_requests" name="special_requests" rows="3" 
                                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                              placeholder="Any special requests or preferences..."></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Room Assignment -->
                        <div class="border-b border-gray-200 pb-6">
                            <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                                <i class="fas fa-bed mr-2 text-primary"></i>Room Assignment
                            </h4>
                            <div id="available-rooms" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                                <!-- Available rooms will be loaded here -->
                            </div>
                        </div>

                        <!-- Pricing Summary -->
                        <div class="border-b border-gray-200 pb-6">
                            <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                                <i class="fas fa-calculator mr-2 text-primary"></i>Pricing Summary
                            </h4>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-gray-600">Room Rate (per night):</span>
                                    <span id="room-rate" class="font-medium">₱0.00</span>
                                </div>
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-gray-600">Number of Nights:</span>
                                    <span id="nights" class="font-medium">0</span>
                                </div>
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-gray-600">Subtotal:</span>
                                    <span id="subtotal" class="font-medium">₱0.00</span>
                                </div>
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-gray-600">Tax (10%):</span>
                                    <span id="tax" class="font-medium">₱0.00</span>
                                </div>
                                <div class="border-t border-gray-300 pt-2">
                                    <div class="flex justify-between items-center">
                                        <span class="text-lg font-semibold text-gray-900">Total Amount:</span>
                                        <span id="total-amount" class="text-lg font-semibold text-primary">₱0.00</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex justify-end space-x-4">
                            <button type="button" onclick="window.history.back()" 
                                    class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                                Cancel
                            </button>
                            <button type="submit" 
                                    class="px-6 py-2 bg-primary text-white rounded-md hover:bg-primary-dark transition-colors">
                                <i class="fas fa-save mr-2"></i>Create Reservation
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>

<?php include '../../includes/footer.php'; ?>
