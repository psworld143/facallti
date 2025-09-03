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

// Get events data
$event_services = getEventServices();
$active_events = getActiveEvents();
$event_stats = getEventStats();

// Set page title
$page_title = 'Event Services POS';

// Include POS-specific header and sidebar
include '../includes/pos-header.php';
include '../includes/pos-sidebar.php';
?>

        <!-- Main Content -->
        <main class="main-content p-4 lg:p-6 flex-1 transition-all duration-300">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 lg:mb-8 gap-4">
                <h2 class="text-2xl lg:text-3xl font-semibold text-gray-800">Event Services POS System</h2>
                <div class="text-right">
                    <div id="current-date" class="text-sm text-gray-600"></div>
                    <div id="current-time" class="text-sm text-gray-600"></div>
                </div>
            </div>

            <!-- Event Statistics Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6 mb-8">
                <div class="bg-white rounded-lg p-6 shadow-md hover:shadow-lg transition-shadow">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-gradient-to-r from-primary to-secondary rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-calendar-alt text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-3xl font-bold text-gray-800">₱<?php echo number_format($event_stats['today_revenue'], 2); ?></h3>
                            <p class="text-gray-600">Today's Revenue</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg p-6 shadow-md hover:shadow-lg transition-shadow">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-gradient-to-r from-pink-400 to-red-400 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-users text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-3xl font-bold text-gray-800"><?php echo $event_stats['today_guests']; ?></h3>
                            <p class="text-gray-600">Today's Guests</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg p-6 shadow-md hover:shadow-lg transition-shadow">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-gradient-to-r from-blue-400 to-cyan-400 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-champagne-glasses text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-3xl font-bold text-gray-800"><?php echo $event_stats['active_events']; ?></h3>
                            <p class="text-gray-600">Active Events</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg p-6 shadow-md hover:shadow-lg transition-shadow">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-gradient-to-r from-green-400 to-teal-400 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-chart-line text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-3xl font-bold text-gray-800">₱<?php echo number_format($event_stats['monthly_revenue'], 2); ?></h3>
                            <p class="text-gray-600">Monthly Revenue</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white rounded-lg p-6 shadow-md mb-8">
                <h3 class="text-xl font-semibold text-gray-800 mb-6">Quick Actions</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <button onclick="openNewEvent()" class="flex items-center p-4 bg-gray-50 border-2 border-gray-200 rounded-lg hover:bg-primary hover:border-primary hover:text-white transition-all duration-300">
                        <i class="fas fa-plus text-xl mr-3"></i>
                        <span class="font-medium">New Event</span>
                    </button>
                    <button onclick="openCatering()" class="flex items-center p-4 bg-gray-50 border-2 border-gray-200 rounded-lg hover:bg-primary hover:border-primary hover:text-white transition-all duration-300">
                        <i class="fas fa-utensils text-xl mr-3"></i>
                        <span class="font-medium">Catering</span>
                    </button>
                    <button onclick="openAVServices()" class="flex items-center p-4 bg-gray-50 border-2 border-gray-200 rounded-lg hover:bg-primary hover:border-primary hover:text-white transition-all duration-300">
                        <i class="fas fa-video text-xl mr-3"></i>
                        <span class="font-medium">AV Services</span>
                    </button>
                    <button onclick="openEventReports()" class="flex items-center p-4 bg-gray-50 border-2 border-gray-200 rounded-lg hover:bg-primary hover:border-primary hover:text-white transition-all duration-300">
                        <i class="fas fa-chart-bar text-xl mr-3"></i>
                        <span class="font-medium">Event Reports</span>
                    </button>
                </div>
            </div>

            <!-- Active Events -->
            <div class="bg-white rounded-lg p-6 shadow-md mb-8">
                <h3 class="text-xl font-semibold text-gray-800 mb-6">Active Events</h3>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <?php if (empty($active_events)): ?>
                        <div class="col-span-2 text-center py-8 text-gray-500">
                            <i class="fas fa-calendar-times text-4xl mb-4"></i>
                            <p>No active events at the moment</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($active_events as $event): ?>
                            <div class="border border-gray-200 rounded-lg p-6 hover:shadow-md transition-shadow">
                                <div class="flex items-start justify-between mb-4">
                                    <div>
                                        <h4 class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($event['event_name']); ?></h4>
                                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($event['event_type']); ?></p>
                                    </div>
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                        <?php echo $event['status'] === 'in-progress' ? 'bg-green-100 text-green-800' : 
                                            ($event['status'] === 'setup' ? 'bg-blue-100 text-blue-800' : 'bg-yellow-100 text-yellow-800'); ?>">
                                        <?php echo ucfirst($event['status']); ?>
                                    </span>
                                </div>
                                
                                <div class="grid grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <p class="text-xs text-gray-500">Start Time</p>
                                        <p class="text-sm font-medium"><?php echo date('H:i', strtotime($event['start_time'])); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500">End Time</p>
                                        <p class="text-sm font-medium"><?php echo date('H:i', strtotime($event['end_time'])); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500">Guests</p>
                                        <p class="text-sm font-medium"><?php echo $event['expected_guests']; ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500">Revenue</p>
                                        <p class="text-sm font-medium text-primary">₱<?php echo number_format($event['total_revenue'], 2); ?></p>
                                    </div>
                                </div>
                                
                                <div class="flex space-x-2">
                                    <button onclick="manageEvent(<?php echo $event['id']; ?>)" class="flex-1 px-3 py-2 bg-primary text-white text-sm rounded hover:bg-primary-dark transition-colors">
                                        Manage Event
                                    </button>
                                    <button onclick="addServices(<?php echo $event['id']; ?>)" class="flex-1 px-3 py-2 bg-gray-100 text-gray-700 text-sm rounded hover:bg-gray-200 transition-colors">
                                        Add Services
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Event Services -->
            <div class="bg-white rounded-lg p-6 shadow-md">
                <h3 class="text-xl font-semibold text-gray-800 mb-6">Available Services</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($event_services as $service): ?>
                        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                            <div class="flex items-center mb-3">
                                <div class="w-10 h-10 bg-gradient-to-r from-primary to-secondary rounded-lg flex items-center justify-center mr-3">
                                    <i class="<?php echo $service['icon']; ?> text-white"></i>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-900"><?php echo htmlspecialchars($service['name']); ?></h4>
                                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($service['category']); ?></p>
                                </div>
                            </div>
                            
                            <p class="text-sm text-gray-600 mb-3"><?php echo htmlspecialchars($service['description']); ?></p>
                            
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="text-lg font-bold text-primary">₱<?php echo number_format($service['price'], 2); ?></span>
                                    <span class="text-xs text-gray-500">/<?php echo $service['unit']; ?></span>
                                </div>
                                <button onclick="addEventService(<?php echo $service['id']; ?>)" class="px-3 py-1 bg-primary text-white text-sm rounded hover:bg-primary-dark transition-colors">
                                    Add Service
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Event Services POS functionality
        function openNewEvent() {
            // Implementation for new event
            console.log('Opening new event form');
        }

        function openCatering() {
            // Implementation for catering services
            console.log('Opening catering services');
        }

        function openAVServices() {
            // Implementation for AV services
            console.log('Opening AV services');
        }

        function openEventReports() {
            // Implementation for event reports
            console.log('Opening event reports');
        }

        function manageEvent(eventId) {
            // Implementation for managing event
            console.log('Managing event:', eventId);
        }

        function addServices(eventId) {
            // Implementation for adding services to event
            console.log('Adding services to event:', eventId);
        }

        function addEventService(serviceId) {
            // Implementation for adding event service
            console.log('Adding event service:', serviceId);
        }
    </script>

<?php include '../includes/pos-footer.php'; ?>
