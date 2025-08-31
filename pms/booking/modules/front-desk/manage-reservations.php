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

// Set page title
$page_title = 'Manage Reservations';
$page_subtitle = 'View and manage guest reservations';

// Include unified navigation (automatically selects based on user role)
include '../../includes/header-unified.php';
include '../../includes/sidebar-unified.php';
?>

        <!-- Main Content -->
        <main class="lg:ml-64 mt-16 p-4 lg:p-6 flex-1 transition-all duration-300">
            <div class="flex justify-between items-center mb-8">
                <h2 class="text-3xl font-semibold text-gray-800">Manage Reservations</h2>
                <div class="text-right">
                    <div id="current-date" class="text-sm text-gray-600"></div>
                    <div id="current-time" class="text-sm text-gray-600"></div>
                </div>
            </div>

            <!-- Search and Filters -->
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
                            <label for="search_status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                            <select id="search_status" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                <option value="">All Statuses</option>
                                <option value="confirmed">Confirmed</option>
                                <option value="checked_in">Checked In</option>
                                <option value="checked_out">Checked Out</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button onclick="searchReservations()" 
                                    class="w-full px-4 py-2 bg-primary text-white rounded-md hover:bg-primary-dark transition-colors">
                                <i class="fas fa-search mr-2"></i>Search
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Reservations List -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-semibold text-gray-800">Reservations</h2>
                        <div class="flex space-x-2">
                            <button onclick="loadReservations()" 
                                    class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 transition-colors">
                                <i class="fas fa-refresh mr-2"></i>Refresh
                            </button>
                            <a href="new-reservation.php" 
                               class="px-4 py-2 bg-primary text-white rounded-md hover:bg-primary-dark transition-colors">
                                <i class="fas fa-plus mr-2"></i>New Reservation
                            </a>
                        </div>
                    </div>
                    <div id="reservations-list" class="overflow-x-auto">
                        <!-- Reservations will be loaded here -->
                    </div>
                </div>
            </div>
        </main>

    <!-- Edit Reservation Modal -->
    <div id="edit-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-8 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold text-gray-900">Edit Reservation</h3>
                <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <form id="edit-reservation-form" class="space-y-6">
                <input type="hidden" id="edit_reservation_id" name="reservation_id">
                
                <!-- Guest Information -->
                <div class="border-b border-gray-200 pb-6">
                    <h4 class="text-md font-medium text-gray-900 mb-4">Guest Information</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="edit_first_name" class="block text-sm font-medium text-gray-700 mb-2">First Name *</label>
                            <input type="text" id="edit_first_name" name="first_name" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                        <div>
                            <label for="edit_last_name" class="block text-sm font-medium text-gray-700 mb-2">Last Name *</label>
                            <input type="text" id="edit_last_name" name="last_name" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                        <div>
                            <label for="edit_email" class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                            <input type="email" id="edit_email" name="email" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                        <div>
                            <label for="edit_phone" class="block text-sm font-medium text-gray-700 mb-2">Phone *</label>
                            <input type="tel" id="edit_phone" name="phone" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                    </div>
                </div>

                <!-- Reservation Details -->
                <div class="border-b border-gray-200 pb-6">
                    <h4 class="text-md font-medium text-gray-900 mb-4">Reservation Details</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="edit_check_in_date" class="block text-sm font-medium text-gray-700 mb-2">Check-in Date *</label>
                            <input type="date" id="edit_check_in_date" name="check_in_date" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                        <div>
                            <label for="edit_check_out_date" class="block text-sm font-medium text-gray-700 mb-2">Check-out Date *</label>
                            <input type="date" id="edit_check_out_date" name="check_out_date" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                        <div>
                            <label for="edit_adults" class="block text-sm font-medium text-gray-700 mb-2">Number of Adults *</label>
                            <input type="number" id="edit_adults" name="adults" min="1" max="10" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                        <div>
                            <label for="edit_children" class="block text-sm font-medium text-gray-700 mb-2">Number of Children</label>
                            <input type="number" id="edit_children" name="children" min="0" max="10" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                        <div>
                            <label for="edit_room_type" class="block text-sm font-medium text-gray-700 mb-2">Room Type *</label>
                            <select id="edit_room_type" name="room_type" required 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                <option value="">Select Room Type</option>
                                <option value="standard">Standard Room</option>
                                <option value="deluxe">Deluxe Room</option>
                                <option value="suite">Suite</option>
                                <option value="presidential">Presidential Suite</option>
                            </select>
                        </div>
                        <div>
                            <label for="edit_special_requests" class="block text-sm font-medium text-gray-700 mb-2">Special Requests</label>
                            <textarea id="edit_special_requests" name="special_requests" rows="3" 
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-end space-x-4">
                    <button type="button" onclick="closeEditModal()" 
                            class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-6 py-2 bg-primary text-white rounded-md hover:bg-primary-dark transition-colors">
                        <i class="fas fa-save mr-2"></i>Update Reservation
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
                    <p><strong>Reservation:</strong> <span id="cancel_reservation_number"></span></p>
                    <p><strong>Guest:</strong> <span id="cancel_guest_name"></span></p>
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

<?php include '../../includes/footer.php'; ?>
