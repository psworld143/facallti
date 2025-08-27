<?php
session_start();
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

// Get additional reservation data
$guest_details = getGuestDetails($reservation['guest_id'] ?? null);
$room_details = getRoomDetails($reservation['room_id'] ?? null);
$billing_details = getBillingDetails($reservation_id);
$check_in_details = getCheckInDetails($reservation_id);
$additional_services = getAdditionalServicesForReservation($reservation_id);

// Set page title
$page_title = 'View Reservation';

// Include unified navigation (automatically selects based on user role)
include '../../includes/header-unified.php';
include '../../includes/sidebar-unified.php';
?>

        <!-- Main Content -->
        <main class="ml-64 mt-16 p-6 flex-1">
            <div class="flex justify-between items-center mb-8">
                <h2 class="text-3xl font-semibold text-gray-800">View Reservation</h2>
                <div class="text-right">
                    <div class="text-sm text-gray-600">Reservation #<?php echo htmlspecialchars($reservation['reservation_number']); ?></div>
                    <div class="text-sm text-gray-600">Status: <span class="font-medium <?php echo $reservation['status'] === 'confirmed' ? 'text-green-600' : ($reservation['status'] === 'checked_in' ? 'text-blue-600' : 'text-gray-600'); ?>"><?php echo ucfirst($reservation['status']); ?></span></div>
                </div>
            </div>

            <!-- Reservation Summary -->
            <div class="bg-white rounded-lg p-6 shadow-md mb-8">
                <h3 class="text-xl font-semibold text-gray-800 mb-6">Reservation Overview</h3>
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
                            <h4 class="text-lg font-bold text-gray-800">$<?php echo number_format($reservation['total_amount'], 2); ?></h4>
                            <p class="text-gray-600">Total Amount</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Guest Information -->
            <div class="bg-white rounded-lg p-6 shadow-md mb-8">
                <h3 class="text-xl font-semibold text-gray-800 mb-6">Guest Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                        <p class="text-gray-900 font-medium"><?php echo htmlspecialchars($reservation['first_name'] . ' ' . $reservation['last_name']); ?></p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <p class="text-gray-900"><?php echo htmlspecialchars($reservation['email'] ?? 'Not provided'); ?></p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                        <p class="text-gray-900"><?php echo htmlspecialchars($reservation['phone']); ?></p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Adults</label>
                        <p class="text-gray-900"><?php echo $reservation['adults']; ?></p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Children</label>
                        <p class="text-gray-900"><?php echo $reservation['children'] ?? 0; ?></p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Special Requests</label>
                        <p class="text-gray-900"><?php echo htmlspecialchars($reservation['special_requests'] ?? 'None'); ?></p>
                    </div>
                </div>
            </div>

            <!-- Room Information -->
            <div class="bg-white rounded-lg p-6 shadow-md mb-8">
                <h3 class="text-xl font-semibold text-gray-800 mb-6">Room Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Room Number</label>
                        <p class="text-gray-900 font-medium"><?php echo htmlspecialchars($reservation['room_number']); ?></p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Room Type</label>
                        <p class="text-gray-900"><?php echo ucfirst($reservation['room_type']); ?></p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Room Rate</label>
                        <p class="text-gray-900">$<?php echo number_format($reservation['room_rate'], 2); ?> per night</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Check-in Date</label>
                        <p class="text-gray-900"><?php echo date('F d, Y', strtotime($reservation['check_in_date'])); ?></p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Check-out Date</label>
                        <p class="text-gray-900"><?php echo date('F d, Y', strtotime($reservation['check_out_date'])); ?></p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nights</label>
                        <p class="text-gray-900"><?php echo $reservation['nights']; ?> night(s)</p>
                    </div>
                </div>
            </div>

            <!-- Billing Information -->
            <div class="bg-white rounded-lg p-6 shadow-md mb-8">
                <h3 class="text-xl font-semibold text-gray-800 mb-6">Billing Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Room Charges</label>
                        <p class="text-gray-900 font-medium">$<?php echo number_format($reservation['room_rate'] * $reservation['nights'], 2); ?></p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Additional Services</label>
                        <p class="text-gray-900">$<?php echo number_format($billing_details['services_total'] ?? 0, 2); ?></p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Taxes & Fees</label>
                        <p class="text-gray-900">$<?php echo number_format($billing_details['taxes'] ?? 0, 2); ?></p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Total Amount</label>
                        <p class="text-gray-900 font-bold text-lg">$<?php echo number_format($reservation['total_amount'], 2); ?></p>
                    </div>
                </div>
                
                <?php if (!empty($additional_services)): ?>
                <div class="mt-6">
                    <h4 class="font-medium text-gray-900 mb-3">Additional Services</h4>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="space-y-2">
                            <?php foreach ($additional_services as $service): ?>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-700"><?php echo htmlspecialchars($service['service_name']); ?></span>
                                <span class="font-medium">$<?php echo number_format($service['amount'], 2); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Check-in/Check-out Status -->
            <?php if ($check_in_details): ?>
            <div class="bg-white rounded-lg p-6 shadow-md mb-8">
                <h3 class="text-xl font-semibold text-gray-800 mb-6">Check-in/Check-out Status</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Check-in Date</label>
                        <p class="text-gray-900"><?php echo $check_in_details['checked_in_at'] ? date('F d, Y g:i A', strtotime($check_in_details['checked_in_at'])) : 'Not checked in'; ?></p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Check-out Date</label>
                        <p class="text-gray-900"><?php echo $check_in_details['checked_out_at'] ? date('F d, Y g:i A', strtotime($check_in_details['checked_out_at'])) : 'Not checked out'; ?></p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Checked in by</label>
                        <p class="text-gray-900"><?php echo htmlspecialchars($check_in_details['checked_in_by'] ?? 'N/A'); ?></p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Checked out by</label>
                        <p class="text-gray-900"><?php echo htmlspecialchars($check_in_details['checked_out_by'] ?? 'N/A'); ?></p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Reservation Timeline -->
            <div class="bg-white rounded-lg p-6 shadow-md mb-8">
                <h3 class="text-xl font-semibold text-gray-800 mb-6">Reservation Timeline</h3>
                <div class="space-y-4">
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-check text-white text-sm"></i>
                        </div>
                        <div>
                            <p class="font-medium text-gray-900">Reservation Created</p>
                            <p class="text-sm text-gray-600"><?php echo date('F d, Y g:i A', strtotime($reservation['created_at'])); ?></p>
                        </div>
                    </div>
                    
                    <?php if ($reservation['status'] === 'confirmed' || $reservation['status'] === 'checked_in' || $reservation['status'] === 'checked_out'): ?>
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-check text-white text-sm"></i>
                        </div>
                        <div>
                            <p class="font-medium text-gray-900">Reservation Confirmed</p>
                            <p class="text-sm text-gray-600"><?php echo date('F d, Y g:i A', strtotime($reservation['confirmed_at'] ?? $reservation['created_at'])); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($check_in_details && $check_in_details['checked_in_at']): ?>
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-check text-white text-sm"></i>
                        </div>
                        <div>
                            <p class="font-medium text-gray-900">Guest Checked In</p>
                            <p class="text-sm text-gray-600"><?php echo date('F d, Y g:i A', strtotime($check_in_details['checked_in_at'])); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($check_in_details && $check_in_details['checked_out_at']): ?>
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-red-500 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-check text-white text-sm"></i>
                        </div>
                        <div>
                            <p class="font-medium text-gray-900">Guest Checked Out</p>
                            <p class="text-sm text-gray-600"><?php echo date('F d, Y g:i A', strtotime($check_in_details['checked_out_at'])); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white rounded-lg p-6 shadow-md">
                <h3 class="text-xl font-semibold text-gray-800 mb-6">Quick Actions</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <a href="manage-reservations.php" class="flex items-center p-4 bg-gray-50 border-2 border-gray-200 rounded-lg hover:bg-gray-100 hover:border-gray-300 transition-all duration-300">
                        <i class="fas fa-arrow-left text-gray-600 text-xl mr-3"></i>
                        <span class="font-medium text-gray-800">Back to Reservations</span>
                    </a>
                    <a href="modify-reservation.php?id=<?php echo $reservation_id; ?>" class="flex items-center p-4 bg-blue-50 border-2 border-blue-200 rounded-lg hover:bg-blue-100 hover:border-blue-300 transition-all duration-300">
                        <i class="fas fa-edit text-blue-600 text-xl mr-3"></i>
                        <span class="font-medium text-blue-800">Modify Reservation</span>
                    </a>
                    <button onclick="printReservation()" class="flex items-center p-4 bg-green-50 border-2 border-green-200 rounded-lg hover:bg-green-100 hover:border-green-300 transition-all duration-300">
                        <i class="fas fa-print text-green-600 text-xl mr-3"></i>
                        <span class="font-medium text-green-800">Print Reservation</span>
                    </button>
                    <?php if ($reservation['status'] === 'confirmed'): ?>
                    <a href="check-in.php?id=<?php echo $reservation_id; ?>" class="flex items-center p-4 bg-purple-50 border-2 border-purple-200 rounded-lg hover:bg-purple-100 hover:border-purple-300 transition-all duration-300">
                        <i class="fas fa-sign-in-alt text-purple-600 text-xl mr-3"></i>
                        <span class="font-medium text-purple-800">Check In Guest</span>
                    </a>
                    <?php elseif ($reservation['status'] === 'checked_in'): ?>
                    <a href="check-out.php?id=<?php echo $reservation_id; ?>" class="flex items-center p-4 bg-red-50 border-2 border-red-200 rounded-lg hover:bg-red-100 hover:border-red-300 transition-all duration-300">
                        <i class="fas fa-sign-out-alt text-red-600 text-xl mr-3"></i>
                        <span class="font-medium text-red-800">Check Out Guest</span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="../../assets/js/main.js"></script>
    <script>
        function printReservation() {
            window.print();
        }
    </script>
    
    <?php include '../../includes/footer.php'; ?>
