<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has teacher role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

// Set page title
$page_title = 'My Leave Requests';

// Get faculty information
$faculty_id = $_SESSION['user_id'];

// Check database connection
if (!$conn) {
    echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">Database connection failed. Please try again later.</div>';
    exit();
}

// Get faculty information
$faculty_query = "SELECT id, first_name, last_name, email, department FROM faculty WHERE id = ? AND is_active = 1";
$faculty_stmt = mysqli_prepare($conn, $faculty_query);

if (!$faculty_stmt) {
    echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">Database query preparation failed.</div>';
    exit();
}

mysqli_stmt_bind_param($faculty_stmt, 'i', $faculty_id);
mysqli_stmt_execute($faculty_stmt);
$faculty_result = mysqli_stmt_get_result($faculty_stmt);
$faculty_info = mysqli_fetch_assoc($faculty_result);

if (!$faculty_info) {
    echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">Faculty information not found. Please contact administrator.</div>';
    exit();
}

// Get leave requests for this faculty
$leave_query = "SELECT flr.*, lt.name as leave_type_name
                FROM faculty_leave_requests flr
                JOIN leave_types lt ON flr.leave_type_id = lt.id
                WHERE flr.faculty_id = ?
                ORDER BY flr.created_at DESC";

$leave_stmt = mysqli_prepare($conn, $leave_query);
if ($leave_stmt) {
    mysqli_stmt_bind_param($leave_stmt, 'i', $faculty_id);
    mysqli_stmt_execute($leave_stmt);
    $leave_result = mysqli_stmt_get_result($leave_stmt);
} else {
    $leave_result = false;
}

// Get leave types for the form
$leave_types_query = "SELECT id, name FROM leave_types WHERE is_active = 1 ORDER BY name";
$leave_types_result = mysqli_query($conn, $leave_types_query);

// Get leave balance
$current_year = date('Y');
$balance_query = "SELECT flb.*, lt.name as leave_type_name 
                  FROM faculty_leave_balances flb
                  JOIN leave_types lt ON flb.leave_type_id = lt.id
                  WHERE flb.faculty_id = ? AND flb.year = ?
                  ORDER BY lt.name";

$balance_stmt = mysqli_prepare($conn, $balance_query);
if ($balance_stmt) {
    mysqli_stmt_bind_param($balance_stmt, 'ii', $faculty_id, $current_year);
    mysqli_stmt_execute($balance_stmt);
    $balance_result = mysqli_stmt_get_result($balance_stmt);
} else {
    $balance_result = false;
}

// Count leave requests by status
$pending_count = 0;
$approved_count = 0;
$rejected_count = 0;

if ($leave_result) {
    mysqli_data_seek($leave_result, 0);
    while ($leave = mysqli_fetch_assoc($leave_result)) {
        switch ($leave['status']) {
            case 'pending':
                $pending_count++;
                break;
            case 'approved_by_head':
            case 'approved_by_hr':
                $approved_count++;
                break;
            case 'rejected':
                $rejected_count++;
                break;
        }
    }
    mysqli_data_seek($leave_result, 0); // Reset pointer for later use
}

// Include the shared header
$sidebar_context = 'main';
include 'includes/unified-header.php';
?>

<div class="mb-6 sm:mb-8">
    <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">My Leave Requests</h1>
    <p class="text-sm sm:text-base text-gray-600">Submit and manage your leave requests</p>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-6 sm:mb-8">
    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-4 sm:p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-yellow-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-clock text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Pending Requests</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($pending_count); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-4 sm:p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-check text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Approved Requests</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($approved_count); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-4 sm:p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-red-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-times text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Rejected Requests</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($rejected_count); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-4 sm:p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-calendar-alt text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Current Year</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo $current_year; ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="mb-6 sm:mb-8">
    <h2 class="text-xl sm:text-2xl font-bold text-seait-dark mb-4">Quick Actions</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <button onclick="openNewLeaveModal()" class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow text-left">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                    <i class="fas fa-plus text-green-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Submit Leave Request</h3>
                    <p class="text-sm text-gray-600">Create a new leave request</p>
                </div>
            </div>
        </button>

        <a href="#leave-balance" class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                    <i class="fas fa-chart-pie text-blue-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">View Leave Balance</h3>
                    <p class="text-sm text-gray-600">Check your remaining leave days</p>
                </div>
            </div>
        </a>

        <a href="#leave-history" class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                    <i class="fas fa-history text-purple-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Leave History</h3>
                    <p class="text-sm text-gray-600">View all your leave requests</p>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- Leave Balance Summary -->
<div id="leave-balance" class="mb-6 sm:mb-8">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl sm:text-2xl font-bold text-seait-dark">Leave Balance Summary (<?php echo $current_year; ?>)</h2>
    </div>
    
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <?php if ($balance_result && mysqli_num_rows($balance_result) > 0): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Leave Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Days</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Used Days</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Remaining</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php while ($balance = mysqli_fetch_assoc($balance_result)): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($balance['leave_type_name']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $balance['total_days']; ?> days</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $balance['used_days']; ?> days</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo ($balance['total_days'] - $balance['used_days']); ?> days</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php 
                                    $remaining = $balance['total_days'] - $balance['used_days'];
                                    $percentage = ($remaining / $balance['total_days']) * 100;
                                    if ($percentage >= 75) {
                                        $status_class = 'bg-green-100 text-green-800';
                                        $status_text = 'Good';
                                    } elseif ($percentage >= 50) {
                                        $status_class = 'bg-yellow-100 text-yellow-800';
                                        $status_text = 'Moderate';
                                    } else {
                                        $status_class = 'bg-red-100 text-red-800';
                                        $status_text = 'Low';
                                    }
                                    ?>
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $status_class; ?>">
                                        <?php echo $status_text; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="p-6 text-center">
                <i class="fas fa-calendar-times text-gray-300 text-4xl mb-4"></i>
                <p class="text-gray-500">No leave balance information available</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Leave Requests Table -->
<div id="leave-history" class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-medium text-gray-900">My Leave Requests</h2>
            <button onclick="openNewLeaveModal()" class="bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition-colors duration-200 flex items-center">
                <i class="fas fa-plus mr-2"></i>
                New Request
            </button>
        </div>
    </div>
    
    <div class="overflow-x-auto">
        <?php if ($leave_result && mysqli_num_rows($leave_result) > 0): ?>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Leave Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Range</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Days</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php while ($leave = mysqli_fetch_assoc($leave_result)): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($leave['leave_type_name']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?php echo date('M d, Y', strtotime($leave['start_date'])); ?></div>
                                <div class="text-sm text-gray-500">to <?php echo date('M d, Y', strtotime($leave['end_date'])); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $leave['total_days']; ?> days</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php
                                $status_colors = [
                                    'pending' => 'bg-yellow-100 text-yellow-800',
                                    'approved_by_head' => 'bg-blue-100 text-blue-800',
                                    'approved_by_hr' => 'bg-green-100 text-green-800',
                                    'rejected' => 'bg-red-100 text-red-800',
                                    'cancelled' => 'bg-gray-100 text-gray-800'
                                ];
                                $status_text = [
                                    'pending' => 'Pending',
                                    'approved_by_head' => 'Approved by Head',
                                    'approved_by_hr' => 'Approved by HR',
                                    'rejected' => 'Rejected',
                                    'cancelled' => 'Cancelled'
                                ];
                                ?>
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $status_colors[$leave['status']] ?? 'bg-gray-100 text-gray-800'; ?>">
                                    <?php echo $status_text[$leave['status']] ?? ucfirst($leave['status']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button onclick="viewLeaveDetails(<?php echo $leave['id']; ?>)" class="text-seait-orange hover:text-orange-600 mr-3">
                                    <i class="fas fa-eye"></i> View
                                </button>
                                <?php if ($leave['status'] === 'pending'): ?>
                                <button onclick="cancelLeaveRequest(<?php echo $leave['id']; ?>)" class="text-red-600 hover:text-red-800">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="p-6 text-center">
                <i class="fas fa-calendar-plus text-gray-300 text-4xl mb-4"></i>
                <p class="text-gray-500">No leave requests found.</p>
                <button onclick="openNewLeaveModal()" class="mt-4 bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition">
                    Submit Your First Request
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- New Leave Request Modal -->
<div id="newLeaveModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Submit New Leave Request</h3>
                <button onclick="closeNewLeaveModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="newLeaveForm" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Leave Type</label>
                        <select name="leave_type_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                            <option value="">Select Leave Type</option>
                            <?php if ($leave_types_result): ?>
                                <?php while ($leave_type = mysqli_fetch_assoc($leave_types_result)): ?>
                                    <option value="<?php echo $leave_type['id']; ?>">
                                        <?php echo htmlspecialchars($leave_type['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                        <input type="date" name="start_date" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                        <input type="date" name="end_date" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reason</label>
                    <textarea name="reason" required rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent" placeholder="Enter the reason for leave request"></textarea>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeNewLeaveModal()" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition-colors duration-200">
                        Cancel
                    </button>
                    <button type="submit" class="bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition-colors duration-200">
                        Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Leave Details Modal -->
<div id="leaveDetailsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-10 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Leave Request Details</h3>
                <button onclick="closeLeaveDetailsModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div id="leaveDetailsContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script>
function openNewLeaveModal() {
    document.getElementById('newLeaveModal').classList.remove('hidden');
}

function closeNewLeaveModal() {
    document.getElementById('newLeaveModal').classList.add('hidden');
    document.getElementById('newLeaveForm').reset();
}

function viewLeaveDetails(leaveId) {
    fetch(`get-leave-details.php?id=${leaveId}&table=faculty`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('leaveDetailsContent').innerHTML = html;
            document.getElementById('leaveDetailsModal').classList.remove('hidden');
        })
        .catch(error => {
            console.error('Error loading leave details:', error);
            alert('Error loading leave details.');
        });
}

function closeLeaveDetailsModal() {
    document.getElementById('leaveDetailsModal').classList.add('hidden');
}

function cancelLeaveRequest(leaveId) {
    if (confirm('Are you sure you want to cancel this leave request?')) {
        fetch('cancel-leave-request.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                leave_id: leaveId,
                table: 'faculty'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Leave request cancelled successfully!');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while cancelling the leave request.');
        });
    }
}

// Handle new leave form submission
document.getElementById('newLeaveForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('faculty_id', '<?php echo $faculty_id; ?>');
    
    fetch('submit-leave-request.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Leave request submitted successfully!');
            closeNewLeaveModal();
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error submitting leave request');
    });
});
</script>

<?php
// Include the unified footer
include 'includes/footer.php';
?>