<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is faculty
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get employee details
$query = "SELECT * FROM employees WHERE employee_id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$employee = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$employee) {
    header('Location: ../login.php');
    exit;
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$year_filter = $_GET['year'] ?? date('Y');
$leave_type_filter = $_GET['leave_type'] ?? '';
$search = $_GET['search'] ?? '';

// Build query with filters
$where_conditions = ["lr.employee_id = ?"];
$params = [$user_id];
$param_types = 'i';

if ($status_filter) {
    $where_conditions[] = "lr.status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

if ($year_filter) {
    $where_conditions[] = "YEAR(lr.start_date) = ?";
    $params[] = $year_filter;
    $param_types .= 'i';
}

if ($leave_type_filter) {
    $where_conditions[] = "lr.leave_type_id = ?";
    $params[] = $leave_type_filter;
    $param_types .= 'i';
}

if ($search) {
    $where_conditions[] = "(lt.name LIKE ? OR lr.reason LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'ss';
}

$where_clause = implode(' AND ', $where_conditions);

// Get leave requests with filters
$query = "SELECT lr.*, lt.name as leave_type_name, lt.description,
          dh.first_name as dh_first_name, dh.last_name as dh_last_name,
          hr.first_name as hr_first_name, hr.last_name as hr_last_name
          FROM leave_requests lr
          JOIN leave_types lt ON lr.leave_type_id = lt.id
          LEFT JOIN employees dh ON lr.department_head_id = dh.employee_id
          LEFT JOIN employees hr ON lr.hr_approver_id = hr.employee_id
          WHERE $where_clause
          ORDER BY lr.created_at DESC";

$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
}
mysqli_stmt_execute($stmt);
$leave_requests = mysqli_stmt_get_result($stmt);

// Get available years for filter
$query = "SELECT DISTINCT YEAR(start_date) as year FROM leave_requests WHERE employee_id = ? ORDER BY year DESC";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$available_years = mysqli_stmt_get_result($stmt);

// Get available leave types for filter
$query = "SELECT DISTINCT lt.id, lt.name FROM leave_types lt
          JOIN leave_requests lr ON lt.id = lr.leave_type_id
          WHERE lr.employee_id = ?
          ORDER BY lt.name";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'i', $user_id);
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave History - Faculty Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .animate-fadeInUp {
            animation: fadeInUp 0.6s ease-out;
        }
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .seait-orange {
            background-color: #FF6B35;
        }
        .text-seait-orange {
            color: #FF6B35;
        }
        .border-seait-orange {
            border-color: #FF6B35;
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'includes/unified-header.php'; ?>
    
    <div class="flex">
        <?php include 'includes/unified-sidebar.php'; ?>
        
        <div class="flex-1 ml-64 p-8">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Leave History</h1>
                <p class="text-gray-600">View and manage your leave request history</p>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow-md p-6 animate-fadeInUp">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                            <i class="fas fa-list text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Requests</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $total_requests; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6 animate-fadeInUp" style="animation-delay: 0.1s;">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 text-green-600">
                            <i class="fas fa-check-circle text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Approved</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $approved_requests; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6 animate-fadeInUp" style="animation-delay: 0.2s;">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                            <i class="fas fa-clock text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Pending</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $pending_requests; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6 animate-fadeInUp" style="animation-delay: 0.3s;">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-red-100 text-red-600">
                            <i class="fas fa-times-circle text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Rejected</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $rejected_requests; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-8 animate-fadeInUp" style="animation-delay: 0.4s;">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Filters</h2>
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

            <!-- Leave Requests Table -->
            <div class="bg-white rounded-lg shadow-md animate-fadeInUp" style="animation-delay: 0.5s;">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-xl font-semibold text-gray-900">Leave Requests</h2>
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
                        <table class="w-full">
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
                            <i class="fas fa-calendar-times text-4xl text-gray-400 mb-4"></i>
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
            fetch(`get-leave-details.php?leave_id=${leaveId}`)
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
                        leave_id: leaveId
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
</body>
</html>
