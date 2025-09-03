<?php
session_start();
require_once '../../includes/error_handler.php';
require_once '../../includes/database.php';
require_once '../includes/pos-functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../booking/login.php');
    exit();
}

// Get spa data
$spa_services = getSpaServices();
$active_appointments = getActiveAppointments('spa');
$spa_stats = getSpaStats();

// Set page title
$page_title = 'Spa & Wellness POS';

// Include POS-specific header and sidebar
include '../includes/pos-header.php';
include '../includes/pos-sidebar.php';
?>

        <!-- Main Content -->
        <main class="main-content p-4 lg:p-6 flex-1 transition-all duration-300">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 lg:mb-8 gap-4">
                <h2 class="text-2xl lg:text-3xl font-semibold text-gray-800">Spa & Wellness POS System</h2>
                <div class="text-right">
                    <div id="current-date" class="text-sm text-gray-600"></div>
                    <div id="current-time" class="text-sm text-gray-600"></div>
                </div>
            </div>

            <!-- Spa Statistics Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6 mb-8">
                <div class="bg-white rounded-lg p-6 shadow-md hover:shadow-lg transition-shadow">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-gradient-to-r from-primary to-secondary rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-spa text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-3xl font-bold text-gray-800">₱<?php echo number_format($spa_stats['today_revenue'], 2); ?></h3>
                            <p class="text-gray-600">Today's Revenue</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg p-6 shadow-md hover:shadow-lg transition-shadow">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-gradient-to-r from-pink-400 to-red-400 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-calendar-check text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-3xl font-bold text-gray-800"><?php echo $spa_stats['today_appointments']; ?></h3>
                            <p class="text-gray-600">Today's Appointments</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg p-6 shadow-md hover:shadow-lg transition-shadow">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-gradient-to-r from-blue-400 to-cyan-400 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-clock text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-3xl font-bold text-gray-800"><?php echo $spa_stats['active_appointments']; ?></h3>
                            <p class="text-gray-600">Active Sessions</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg p-6 shadow-md hover:shadow-lg transition-shadow">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-gradient-to-r from-green-400 to-teal-400 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-chart-line text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-3xl font-bold text-gray-800">₱<?php echo number_format($spa_stats['monthly_revenue'], 2); ?></h3>
                            <p class="text-gray-600">Monthly Revenue</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white rounded-lg p-6 shadow-md mb-8">
                <h3 class="text-xl font-semibold text-gray-800 mb-6">Quick Actions</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <button onclick="openNewAppointment()" class="flex items-center p-4 bg-gray-50 border-2 border-gray-200 rounded-lg hover:bg-primary hover:border-primary hover:text-white transition-all duration-300">
                        <i class="fas fa-plus text-xl mr-3"></i>
                        <span class="font-medium">New Appointment</span>
                    </button>
                    <button onclick="openServiceBooking()" class="flex items-center p-4 bg-gray-50 border-2 border-gray-200 rounded-lg hover:bg-primary hover:border-primary hover:text-white transition-all duration-300">
                        <i class="fas fa-calendar-plus text-xl mr-3"></i>
                        <span class="font-medium">Book Service</span>
                    </button>
                    <button onclick="openWalkInService()" class="flex items-center p-4 bg-gray-50 border-2 border-gray-200 rounded-lg hover:bg-primary hover:border-primary hover:text-white transition-all duration-300">
                        <i class="fas fa-user-clock text-xl mr-3"></i>
                        <span class="font-medium">Walk-in Service</span>
                    </button>
                    <button onclick="openSpaReports()" class="flex items-center p-4 bg-gray-50 border-2 border-gray-200 rounded-lg hover:bg-primary hover:border-primary hover:text-white transition-all duration-300">
                        <i class="fas fa-chart-bar text-xl mr-3"></i>
                        <span class="font-medium">Spa Reports</span>
                    </button>
                </div>
            </div>

            <!-- Active Appointments -->
            <div class="bg-white rounded-lg p-6 shadow-md mb-8">
                <h3 class="text-xl font-semibold text-gray-800 mb-6">Active Appointments</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Guest</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Service</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($active_appointments)): ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-4 text-center text-gray-500">No active appointments</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($active_appointments as $appointment): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="w-8 h-8 bg-gradient-to-r from-primary to-secondary rounded-full flex items-center justify-center mr-3">
                                                    <i class="fas fa-user text-white text-xs"></i>
                                                </div>
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($appointment['guest_name']); ?></div>
                                                    <div class="text-sm text-gray-500">Room <?php echo htmlspecialchars($appointment['room_number']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($appointment['service_name']); ?></div>
                                            <div class="text-sm text-gray-500">₱<?php echo number_format($appointment['price'], 2); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo date('H:i', strtotime($appointment['appointment_time'])); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo date('M j', strtotime($appointment['appointment_time'])); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                                <?php echo $appointment['status'] === 'confirmed' ? 'bg-green-100 text-green-800' : 
                                                    ($appointment['status'] === 'in-progress' ? 'bg-blue-100 text-blue-800' : 'bg-yellow-100 text-yellow-800'); ?>">
                                                <?php echo ucfirst($appointment['status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <button onclick="startService(<?php echo $appointment['id']; ?>)" class="text-primary hover:text-primary-dark mr-3">Start</button>
                                            <button onclick="completeService(<?php echo $appointment['id']; ?>)" class="text-success hover:text-success-dark">Complete</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Spa Services -->
            <div class="bg-white rounded-lg p-6 shadow-md">
                <h3 class="text-xl font-semibold text-gray-800 mb-6">Available Services</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($spa_services as $service): ?>
                        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="font-medium text-gray-900"><?php echo htmlspecialchars($service['name']); ?></h4>
                                <span class="text-lg font-bold text-primary">₱<?php echo number_format($service['price'], 2); ?></span>
                            </div>
                            <p class="text-sm text-gray-600 mb-3"><?php echo htmlspecialchars($service['description']); ?></p>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-500"><?php echo $service['duration']; ?> min</span>
                                <button onclick="bookService(<?php echo $service['id']; ?>)" class="px-3 py-1 bg-primary text-white text-sm rounded hover:bg-primary-dark transition-colors">
                                    Book Now
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Spa POS functionality
        function openNewAppointment() {
            // Implementation for new appointment modal
            console.log('Opening new appointment form');
        }

        function openServiceBooking() {
            // Implementation for service booking
            console.log('Opening service booking');
        }

        function openWalkInService() {
            // Implementation for walk-in service
            console.log('Opening walk-in service');
        }

        function openSpaReports() {
            // Implementation for spa reports
            console.log('Opening spa reports');
        }

        function startService(appointmentId) {
            // Implementation for starting service
            console.log('Starting service for appointment:', appointmentId);
        }

        function completeService(appointmentId) {
            // Implementation for completing service
            console.log('Completing service for appointment:', appointmentId);
        }

        function bookService(serviceId) {
            // Implementation for booking service
            console.log('Booking service:', serviceId);
        }
    </script>

<?php include '../includes/pos-footer.php'; ?>
