<?php
session_start();
require_once '../../../includes/error_handler.php';
require_once '../includes/database.php';

// Check if user is logged in and has front desk role
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'front_desk') {
    header('Location: ../../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Fetch VIP guests data from database
try {
    // Get VIP guests with their current reservation status
    $stmt = $pdo->prepare("
        SELECT 
            g.id,
            g.first_name,
            g.last_name,
            g.email,
            g.phone,
            g.is_vip,
            g.preferences,
            g.service_notes,
            r.id as reservation_id,
            r.reservation_number,
            r.check_in_date,
            r.check_out_date,
            r.status as reservation_status,
            rm.room_number,
            rm.room_type,
            CASE 
                WHEN r.status = 'checked_in' THEN 'staying'
                WHEN r.status = 'confirmed' AND r.check_in_date = CURDATE() THEN 'arriving'
                WHEN r.status = 'checked_out' THEN 'checked_out'
                ELSE 'other'
            END as current_status
        FROM guests g
        LEFT JOIN reservations r ON g.id = r.guest_id AND r.status IN ('confirmed', 'checked_in', 'checked_out')
        LEFT JOIN rooms rm ON r.room_id = rm.id
        WHERE g.is_vip = 1
        ORDER BY g.first_name, g.last_name
    ");
    $stmt->execute();
    $vip_guests = $stmt->fetchAll();

    // Get statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_vip,
            SUM(CASE WHEN r.status = 'checked_in' THEN 1 ELSE 0 END) as currently_staying,
            SUM(CASE WHEN r.status = 'confirmed' AND r.check_in_date = CURDATE() THEN 1 ELSE 0 END) as arriving_today,
            SUM(CASE WHEN g.preferences IS NOT NULL AND g.preferences != '' THEN 1 ELSE 0 END) as special_requests
        FROM guests g
        LEFT JOIN reservations r ON g.id = r.guest_id AND r.status IN ('confirmed', 'checked_in')
        WHERE g.is_vip = 1
    ");
    $stmt->execute();
    $stats = $stmt->fetch();

} catch (PDOException $e) {
    error_log("Error fetching VIP guests: " . $e->getMessage());
    $vip_guests = [];
    $stats = [
        'total_vip' => 0,
        'currently_staying' => 0,
        'arriving_today' => 0,
        'special_requests' => 0
    ];
}

// Set page title for unified header
$page_title = 'VIP Guests Management';

// Include unified navigation (automatically selects based on user role)
include '../../includes/header-unified.php';
include '../../includes/sidebar-unified.php';
?>

        <!-- Main Content -->
        <main class="lg:ml-64 mt-16 p-4 lg:p-6 flex-1 transition-all duration-300">
            <div class="flex justify-between items-center mb-8">
                <h2 class="text-3xl font-semibold text-gray-800">VIP Guests Management</h2>
                <div class="text-right">
                    <div id="current-date" class="text-sm text-gray-600"></div>
                    <div id="current-time" class="text-sm text-gray-600"></div>
                </div>
            </div>

            <!-- Page Header -->
            <div class="mb-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">VIP Guests</h2>
                        <p class="text-gray-600 mt-1">Manage VIP guest information and special requirements</p>
                    </div>
                    <div class="flex space-x-3">
                        <button class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-plus mr-2"></i>Add VIP Guest
                        </button>
                        <button class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors">
                            <i class="fas fa-download mr-2"></i>Export
                        </button>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
                    <div class="flex items-center">
                        <div class="p-3 bg-blue-100 rounded-lg">
                            <i class="fas fa-crown text-blue-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total VIP Guests</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total_vip']; ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
                    <div class="flex items-center">
                        <div class="p-3 bg-green-100 rounded-lg">
                            <i class="fas fa-check-circle text-green-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Currently Staying</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $stats['currently_staying']; ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
                    <div class="flex items-center">
                        <div class="p-3 bg-yellow-100 rounded-lg">
                            <i class="fas fa-clock text-yellow-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Arriving Today</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $stats['arriving_today']; ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
                    <div class="flex items-center">
                        <div class="p-3 bg-purple-100 rounded-lg">
                            <i class="fas fa-star text-purple-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Special Requests</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $stats['special_requests']; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- VIP Guests Table -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-800">VIP Guest List</h3>
                        <div class="flex space-x-2">
                            <div class="relative">
                                <input type="text" id="search-vip" placeholder="Search VIP guests..." class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                            </div>
                            <select id="status-filter" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">All Status</option>
                                <option value="staying">Staying</option>
                                <option value="arriving">Arriving</option>
                                <option value="checked_out">Checked Out</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Guest</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Room</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">VIP Level</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Special Requests</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($vip_guests)): ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                        <i class="fas fa-crown text-2xl mb-2"></i>
                                        <p>No VIP guests found</p>
                                        <p class="text-sm">VIP guests will appear here when they are added to the system</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($vip_guests as $guest): ?>
                                    <tr class="hover:bg-gray-50 vip-guest-row" data-status="<?php echo $guest['current_status']; ?>">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="w-10 h-10 bg-gradient-to-r from-purple-500 to-pink-500 rounded-full flex items-center justify-center">
                                                    <span class="text-white font-medium"><?php echo strtoupper(substr($guest['first_name'], 0, 1) . substr($guest['last_name'], 0, 1)); ?></span>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($guest['first_name'] . ' ' . $guest['last_name']); ?></div>
                                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($guest['email']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="text-sm text-gray-900">
                                                <?php echo $guest['room_number'] ? htmlspecialchars($guest['room_number']) : 'Not assigned'; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                            $status_class = '';
                                            $status_text = '';
                                            switch ($guest['current_status']) {
                                                case 'staying':
                                                    $status_class = 'bg-green-100 text-green-800';
                                                    $status_text = 'Staying';
                                                    break;
                                                case 'arriving':
                                                    $status_class = 'bg-yellow-100 text-yellow-800';
                                                    $status_text = 'Arriving';
                                                    break;
                                                case 'checked_out':
                                                    $status_class = 'bg-gray-100 text-gray-800';
                                                    $status_text = 'Checked Out';
                                                    break;
                                                default:
                                                    $status_class = 'bg-blue-100 text-blue-800';
                                                    $status_text = 'Other';
                                            }
                                            ?>
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $status_class; ?>">
                                                <?php echo $status_text; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <i class="fas fa-crown text-yellow-500 mr-1"></i>
                                                <span class="text-sm text-gray-900">VIP</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="text-sm text-gray-900">
                                                <?php echo $guest['preferences'] ? htmlspecialchars(substr($guest['preferences'], 0, 50) . (strlen($guest['preferences']) > 50 ? '...' : '')) : 'No special requests'; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex space-x-2">
                                                <button class="text-blue-600 hover:text-blue-900" onclick="editGuest(<?php echo $guest['id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="text-green-600 hover:text-green-900" onclick="viewGuest(<?php echo $guest['id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="text-red-600 hover:text-red-900" onclick="deleteGuest(<?php echo $guest['id']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="px-6 py-4 border-t border-gray-200">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-700">
                            Showing <span class="font-medium"><?php echo count($vip_guests); ?></span> VIP guests
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <?php include '../../includes/footer.php'; ?>

    <script>
        // Search functionality
        $('#search-vip').on('input', function() {
            const searchTerm = $(this).val().toLowerCase();
            $('.vip-guest-row').each(function() {
                const guestName = $(this).find('td:first').text().toLowerCase();
                const guestEmail = $(this).find('td:first .text-gray-500').text().toLowerCase();
                
                if (guestName.includes(searchTerm) || guestEmail.includes(searchTerm)) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        });

        // Status filter
        $('#status-filter').on('change', function() {
            const selectedStatus = $(this).val();
            $('.vip-guest-row').each(function() {
                const guestStatus = $(this).data('status');
                
                if (selectedStatus === '' || guestStatus === selectedStatus) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        });

        // Guest action functions
        function editGuest(guestId) {
            // Redirect to guest edit page
            window.location.href = `guest-management.php?action=edit&id=${guestId}`;
        }

        function viewGuest(guestId) {
            // Redirect to guest details page
            window.location.href = `guest-management.php?action=view&id=${guestId}`;
        }

        function deleteGuest(guestId) {
            if (confirm('Are you sure you want to delete this VIP guest?')) {
                // AJAX call to delete guest
                $.post('../../api/delete-guest.php', {guest_id: guestId}, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error deleting guest: ' + response.message);
                    }
                });
            }
        }
    </script>
</body>
</html>
