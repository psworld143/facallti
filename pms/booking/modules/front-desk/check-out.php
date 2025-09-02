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

// Get checked-in guests
$checked_in_guests = getCheckedInGuests();

// Set page title
$page_title = 'Check Out';

// Include unified header (automatically selects appropriate navbar)
include '../../includes/header-unified.php';
// Include unified sidebar (automatically selects appropriate sidebar)
include '../../includes/sidebar-unified.php';
?>

        <!-- Main Content -->
        <main class="lg:ml-64 mt-16 p-4 lg:p-6 flex-1 transition-all duration-300">
                <!-- Search Section -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Search Checked-in Guests</h2>
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
                            <label for="search_room" class="block text-sm font-medium text-gray-700 mb-2">Room Number</label>
                            <input type="text" id="search_room" placeholder="Enter room number" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                        <div class="flex items-end">
                            <button onclick="searchCheckedInGuests()" 
                                    class="w-full px-4 py-2 bg-primary text-white rounded-md hover:bg-primary-dark transition-colors">
                                <i class="fas fa-search mr-2"></i>Search
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Checked-in Guests -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Currently Checked-in Guests</h2>
                    <div id="checked-in-guests" class="overflow-x-auto">
                        <!-- Checked-in guests will be loaded here -->
                    </div>
                </div>

                <!-- Check-out Form -->
                <div id="checkout-form-container" class="bg-white rounded-lg shadow-md p-6 hidden">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Check-out Details</h2>
                    <form id="checkout-form" class="space-y-6">
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
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Check-out Date</label>
                                    <input type="text" id="checkout_date" readonly 
                                           class="w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md">
                                </div>
                            </div>
                        </div>

                        <!-- Billing Summary -->
                        <div class="border-b border-gray-200 pb-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Billing Summary</h3>
                            <div id="billing-summary" class="bg-gray-50 p-4 rounded-lg">
                                <!-- Billing details will be loaded here -->
                            </div>
                        </div>

                        <!-- Check-out Details -->
                        <div class="border-b border-gray-200 pb-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Check-out Details</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="room_key_returned" class="block text-sm font-medium text-gray-700 mb-2">Room Key Returned</label>
                                    <select id="room_key_returned" name="room_key_returned" required 
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        <option value="">Select</option>
                                        <option value="1">Yes</option>
                                        <option value="0">No</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="payment_status" class="block text-sm font-medium text-gray-700 mb-2">Payment Status</label>
                                    <select id="payment_status" name="payment_status" required 
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        <option value="">Select</option>
                                        <option value="paid">Paid</option>
                                        <option value="pending">Pending</option>
                                        <option value="partial">Partial</option>
                                    </select>
                                </div>
                                <div class="md:col-span-2">
                                    <label for="checkout_notes" class="block text-sm font-medium text-gray-700 mb-2">Check-out Notes</label>
                                    <textarea id="checkout_notes" name="checkout_notes" rows="3" 
                                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                              placeholder="Any notes about the check-out..."></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex justify-end space-x-4">
                            <button type="button" onclick="cancelCheckout()" 
                                    class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                                Cancel
                            </button>
                            <button type="submit" 
                                    class="px-6 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors">
                                <i class="fas fa-sign-out-alt mr-2"></i>Complete Check-out
                            </button>
                        </div>
                    </form>
                </div>
        </main>
    </div>

    <script src="../../assets/js/main.js"></script>
    <script src="../../assets/js/checkout.js"></script>
    
    <?php include '../../includes/footer.php'; ?>
