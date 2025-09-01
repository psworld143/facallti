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
$page_title = 'Leave History';

$faculty_id = $_SESSION['user_id'];

// Get faculty details
$query = "SELECT * FROM faculty WHERE id = ? AND is_active = 1";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'i', $faculty_id);
mysqli_stmt_execute($stmt);
$faculty = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$faculty) {
    header('Location: ../login.php');
    exit();
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$year_filter = $_GET['year'] ?? date('Y');
$leave_type_filter = $_GET['leave_type'] ?? '';
$search = $_GET['search'] ?? '';

// Build query with filters
$where_conditions = ["flr.faculty_id = ?"];
$params = [$faculty_id];
$param_types = 'i';

if ($status_filter) {
    $where_conditions[] = "flr.status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

if ($year_filter) {
    $where_conditions[] = "YEAR(flr.start_date) = ?";
    $params[] = $year_filter;
    $param_types .= 'i';
}

if ($leave_type_filter) {
    $where_conditions[] = "flr.leave_type_id = ?";
    $params[] = $leave_type_filter;
    $param_types .= 'i';
}

if ($search) {
    $where_conditions[] = "(lt.name LIKE ? OR flr.reason LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'ss';
}

$where_clause = implode(' AND ', $where_conditions);

// Get leave requests with filters from faculty_leave_requests table
$query = "SELECT flr.*, lt.name as leave_type_name, lt.description,
          dh.first_name as dh_first_name, dh.last_name as dh_last_name,
          hr.first_name as hr_first_name, hr.last_name as hr_last_name
          FROM faculty_leave_requests flr
          JOIN leave_types lt ON flr.leave_type_id = lt.id
          LEFT JOIN faculty dh ON flr.department_head_id = dh.id
          LEFT JOIN faculty hr ON flr.hr_approver_id = hr.id
          WHERE $where_clause
          ORDER BY flr.created_at DESC";

$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
}
mysqli_stmt_execute($stmt);
$leave_requests = mysqli_stmt_get_result($stmt);

// Get available years for filter from faculty_leave_requests table
$query = "SELECT DISTINCT YEAR(start_date) as year FROM faculty_leave_requests WHERE faculty_id = ? ORDER BY year DESC";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'i', $faculty_id);
mysqli_stmt_execute($stmt);
$available_years = mysqli_stmt_get_result($stmt);

// Get available leave types for filter from faculty_leave_requests table
$query = "SELECT DISTINCT lt.id, lt.name FROM leave_types lt
          JOIN faculty_leave_requests flr ON lt.id = flr.leave_type_id
          WHERE flr.faculty_id = ?
          ORDER BY lt.name";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'i', $faculty_id);
mysqli_stmt_execute($stmt);
$available_leave_types = mysqli_stmt_get_result($stmt);

// Calculate statistics
$total_requests = 0;
$approved_requests = 0;
$rejected_requests = 0;
$pending_requests = 0;
$total_days_used = 0;

$requests_array = [];
while ($request = mysqli_fetch_assoc($leave_requests)) {
    $requests_array[] = $request;
    $total_requests++;
    
    switch ($request['status']) {
        case 'approved_by_hr':
            $approved_requests++;
            $total_days_used += $request['total_days'];
            break;
        case 'rejected':
            $rejected_requests++;
            break;
        case 'pending':
        case 'approved_by_head':
            $pending_requests++;
            break;
    }
}

// Include the shared header
$sidebar_context = 'main';
include 'includes/unified-header.php';
?>

<div class="mb-6 sm:mb-8">
    <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Leave History</h1>
    <p class="text-sm sm:text-base text-gray-600">View and manage your leave request history</p>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-6 sm:mb-8">
    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-4 sm:p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-list text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Total Requests</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($total_requests); ?></dd>
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
                        <i class="fas fa-check-circle text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Approved</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($approved_requests); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

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
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Pending</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($pending_requests); ?></dd>
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
                        <i class="fas fa-times-circle text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Rejected</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($rejected_requests); ?></dd>
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
        <a href="leave-requests.php" class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                    <i class="fas fa-calendar-plus text-green-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Submit New Request</h3>
                    <p class="text-sm text-gray-600">Create a new leave request</p>
                </div>
            </div>
        </a>

        <a href="leave-balance.php" class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow">
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

        <a href="dashboard.php" class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                    <i class="fas fa-home text-purple-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Back to Dashboard</h3>
                    <p class="text-sm text-gray-600">Return to main dashboard</p>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow-md overflow-hidden mb-6 sm:mb-8">
    <div class="px-6 py-4 border-b border-gray-200">
        <h2 class="text-lg font-medium text-gray-900">Filters</h2>
    </div>
    <div class="p-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    <option value="">All Status</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved_by_head" <?php echo $status_filter === 'approved_by_head' ? 'selected' : ''; ?>>Approved by Head</option>
                    <option value="approved_by_hr" <?php echo $status_filter === 'approved_by_hr' ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Year</label>
                <select name="year" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    <?php while ($year = mysqli_fetch_assoc($available_years)): ?>
                        <option value="<?php echo $year['year']; ?>" <?php echo $year_filter == $year['year'] ? 'selected' : ''; ?>>
                            <?php echo $year['year']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Leave Type</label>
                <select name="leave_type" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    <option value="">All Types</option>
                    <?php while ($type = mysqli_fetch_assoc($available_leave_types)): ?>
                        <option value="<?php echo $type['id']; ?>" <?php echo $leave_type_filter == $type['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($type['name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Search reason or type..." 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
            </div>

            <div class="flex items-end">
                <button type="submit" class="w-full bg-seait-orange text-white px-4 py-2 rounded-md hover:bg-orange-600 transition-colors duration-200">
                    <i class="fas fa-search mr-2"></i>Filter
                </button>
            </div>
        </form>

        <?php if ($status_filter || $year_filter || $leave_type_filter || $search): ?>
            <div class="mt-4">
                <a href="leave-history.php" class="text-sm text-seait-orange hover:text-orange-600">
                    <i class="fas fa-times mr-1"></i>Clear all filters
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Leave Requests Table -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-medium text-gray-900">Leave Requests</h2>
                <p class="text-sm text-gray-600 mt-1">
                    Showing <?php echo count($requests_array); ?> request(s)
                    <?php if ($total_days_used > 0): ?>
                        • Total days used: <?php echo $total_days_used; ?> days
                    <?php endif; ?>
                </p>
            </div>
            <a href="leave-requests.php" class="bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition-colors duration-200">
                <i class="fas fa-plus mr-2"></i>New Request
            </a>
        </div>
    </div>

    <div class="overflow-x-auto">
        <?php if (count($requests_array) > 0): ?>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Leave Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Range</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Days</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Approvers</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Submitted</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($requests_array as $request): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div>
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($request['leave_type_name']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($request['description']); ?></div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    <?php echo date('M d', strtotime($request['start_date'])); ?> - 
                                    <?php echo date('M d, Y', strtotime($request['end_date'])); ?>
                                </div>
                                <div class="text-sm text-gray-500">
                                    <?php echo date('Y', strtotime($request['start_date'])); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo $request['total_days']; ?> days
                            </td>
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
                                    'approved_by_hr' => 'Approved',
                                    'rejected' => 'Rejected',
                                    'cancelled' => 'Cancelled'
                                ];
                                ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $status_colors[$request['status']] ?? 'bg-gray-100 text-gray-800'; ?>">
                                    <?php echo $status_text[$request['status']] ?? ucfirst($request['status']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php if ($request['dh_first_name']): ?>
                                    <div>Head: <?php echo htmlspecialchars($request['dh_first_name'] . ' ' . $request['dh_last_name']); ?></div>
                                <?php endif; ?>
                                <?php if ($request['hr_first_name']): ?>
                                    <div>HR: <?php echo htmlspecialchars($request['hr_first_name'] . ' ' . $request['hr_last_name']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo date('M d, Y', strtotime($request['created_at'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button onclick="viewLeaveDetails(<?php echo $request['id']; ?>)" 
                                        class="text-seait-orange hover:text-orange-600 mr-3">
                                    <i class="fas fa-eye"></i> View
                                </button>
                                <?php if ($request['status'] === 'pending'): ?>
                                    <button onclick="cancelLeave(<?php echo $request['id']; ?>)" 
                                            class="text-red-600 hover:text-red-800">
                                        <i class="fas fa-times"></i> Cancel
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="p-8 text-center">
                <i class="fas fa-calendar-times text-4xl text-gray-300 mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No leave requests found</h3>
                <p class="text-gray-500 mb-4">
                    <?php if ($status_filter || $year_filter || $leave_type_filter || $search): ?>
                        No requests match your current filters.
                    <?php else: ?>
                        You haven't submitted any leave requests yet.
                    <?php endif; ?>
                </p>
                <a href="leave-requests.php" class="bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition-colors duration-200">
                    <i class="fas fa-plus mr-2"></i>Submit Your First Request
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Leave Details Modal -->
<div id="leaveDetailsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
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
function viewLeaveDetails(leaveId) {
    fetch(`get-leave-details.php?leave_id=${leaveId}&table=faculty`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('leaveDetailsContent').innerHTML = html;
            document.getElementById('leaveDetailsModal').classList.remove('hidden');
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading leave details');
        });
}

function closeLeaveDetailsModal() {
    document.getElementById('leaveDetailsModal').classList.add('hidden');
}

function cancelLeave(leaveId) {
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
                alert('Leave request cancelled successfully');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error cancelling leave request');
        });
    }
}

// Close modal when clicking outside
document.getElementById('leaveDetailsModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeLeaveDetailsModal();
    }
});
</script>

<?php
// Include the unified footer
include 'includes/footer.php';
?>
