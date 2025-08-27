<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has front desk access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['front_desk', 'manager'])) {
    header('Location: ../../login.php');
    exit();
}

// Get pending check-ins
$pending_checkins = getPendingCheckins();

// Set page title
$page_title = 'Check In';

// Include unified navigation (automatically selects based on user role)
include '../../includes/header-unified.php';
include '../../includes/sidebar-unified.php';
?>

        <!-- Main Content -->
        <main class="lg:ml-64 mt-16 p-4 lg:p-6 flex-1 transition-all duration-300">
            <!-- Search Section -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Search Reservations</h2>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label for="search_reservation" class="block text-sm font-medium text-gray-700 mb-2">Reservation Number</label>
                            <input type="text" id="search_reservation" placeholder="Enter reservation number" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                        <div>
                            <label for="search_guest" class="block text-sm font-medium text-gray-700 mb-2">Guest Name</label>
                            <input type="text" id="search_guest" placeholder="Enter guest name" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                        <div>
                            <label for="search_date" class="block text-sm font-medium text-gray-700 mb-2">Check-in Date</label>
                            <input type="date" id="search_date" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                        <div class="flex items-end">
                            <button onclick="searchReservations()" 
                                    class="w-full px-4 py-2 bg-primary text-white rounded-md hover:bg-primary-dark transition-colors">
                                <i class="fas fa-search mr-2"></i>Search
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Pending Check-ins -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Pending Check-ins</h2>
                    <div id="pending-checkins" class="overflow-x-auto">
                        <!-- Pending check-ins will be loaded here -->
                    </div>
                </div>

                <!-- Check-in Form -->
                <div id="checkin-form-container" class="bg-white rounded-lg shadow-md p-6 hidden">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Check-in Details</h2>
                    <form id="checkin-form" class="space-y-6">
                        <input type="hidden" id="reservation_id" name="reservation_id">
                        
                        <!-- Guest Information -->
                        <div class="border-b border-gray-200 pb-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Guest Information</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Guest Name</label>
                                    <input type="text" id="guest_name" readonly 
                                           class="w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Reservation Number</label>
                                    <input type="text" id="reservation_number" readonly 
                                           class="w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Room Number</label>
                                    <input type="text" id="room_number" readonly 
                                           class="w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Check-in Date</label>
                                    <input type="text" id="checkin_date" readonly 
                                           class="w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md">
                                </div>
                            </div>
                        </div>

                        <!-- Check-in Details -->
                        <div class="border-b border-gray-200 pb-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Check-in Details</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="room_key_issued" class="block text-sm font-medium text-gray-700 mb-2">Room Key Issued</label>
                                    <select id="room_key_issued" name="room_key_issued" required 
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        <option value="">Select</option>
                                        <option value="1">Yes</option>
                                        <option value="0">No</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="welcome_amenities" class="block text-sm font-medium text-gray-700 mb-2">Welcome Amenities Provided</label>
                                    <select id="welcome_amenities" name="welcome_amenities" required 
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        <option value="">Select</option>
                                        <option value="1">Yes</option>
                                        <option value="0">No</option>
                                    </select>
                                </div>
                                <div class="md:col-span-2">
                                    <label for="special_instructions" class="block text-sm font-medium text-gray-700 mb-2">Special Instructions</label>
                                    <textarea id="special_instructions" name="special_instructions" rows="3" 
                                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                              placeholder="Any special instructions for the guest..."></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex justify-end space-x-4">
                            <button type="button" onclick="cancelCheckin()" 
                                    class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                                Cancel
                            </button>
                            <button type="submit" 
                                    class="px-6 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">
                                <i class="fas fa-check mr-2"></i>Complete Check-in
                            </button>
                        </div>
                    </form>
                </div>
        </main>
    </div>

    <script src="../../assets/js/main.js"></script>
    <script src="../../assets/js/checkin.js"></script>
    
    <?php include '../../includes/footer.php'; ?>
