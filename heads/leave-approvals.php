<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a department head
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'head') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get department head details
$query = "SELECT * FROM department_heads WHERE employee_id = ? AND is_active = 1";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$department_head = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$department_head) {
    header('Location: ../login.php');
    exit;
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$year_filter = $_GET['year'] ?? date('Y');
$leave_type_filter = $_GET['leave_type'] ?? '';
$search = $_GET['search'] ?? '';

// Build query with filters
$where_conditions = ["lr.department_head_id = ?"];
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
    $where_conditions[] = "(e.first_name LIKE ? OR e.last_name LIKE ? OR lt.name LIKE ? OR lr.reason LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'ssss';
}

$where_clause = implode(' AND ', $where_conditions);

// Get leave requests that this head has approved/rejected
$query = "SELECT lr.*, lt.name as leave_type_name, lt.description,
          e.first_name, e.last_name, e.email, e.position,
          hr.first_name as hr_first_name, hr.last_name as hr_last_name
          FROM leave_requests lr
          JOIN leave_types lt ON lr.leave_type_id = lt.id
          JOIN employees e ON lr.employee_id = e.employee_id
          LEFT JOIN employees hr ON lr.hr_approver_id = hr.employee_id
          WHERE $where_clause
          ORDER BY lr.department_head_approved_at DESC";

$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
}
mysqli_stmt_execute($stmt);
$leave_requests = mysqli_stmt_get_result($stmt);

// Get available years for filter
$query = "SELECT DISTINCT YEAR(start_date) as year FROM leave_requests WHERE department_head_id = ? ORDER BY year DESC";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$available_years = mysqli_stmt_get_result($stmt);

// Get available leave types for filter
$query = "SELECT DISTINCT lt.id, lt.name FROM leave_types lt
          JOIN leave_requests lr ON lt.id = lr.leave_type_id
          WHERE lr.department_head_id = ?
          ORDER BY lt.name";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$available_leave_types = mysqli_stmt_get_result($stmt);

// Calculate statistics
$total_approvals = 0;
$approved_count = 0;
$rejected_count = 0;
$pending_hr_count = 0;
$total_days_approved = 0;

$requests_array = [];
while ($request = mysqli_fetch_assoc($leave_requests)) {
    $requests_array[] = $request;
    $total_approvals++;
    
    switch ($request['status']) {
        case 'approved_by_head':
            $pending_hr_count++;
            break;
        case 'approved_by_hr':
            $approved_count++;
            $total_days_approved += $request['total_days'];
            break;
        case 'rejected':
            $rejected_count++;
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Approvals - Department Head Portal</title>
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
    <?php include 'includes/header.php'; ?>
    
    <div class="flex">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="flex-1 ml-64 p-8">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">My Approvals</h1>
                <p class="text-gray-600">View all leave requests you have approved or rejected</p>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow-md p-6 animate-fadeInUp">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                            <i class="fas fa-list text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Decisions</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $total_approvals; ?></p>
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
                            <p class="text-2xl font-bold text-gray-900"><?php echo $approved_count; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6 animate-fadeInUp" style="animation-delay: 0.2s;">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                            <i class="fas fa-clock text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Pending HR</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $pending_hr_count; ?></p>
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
                            <p class="text-2xl font-bold text-gray-900"><?php echo $rejected_count; ?></p>
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
                            <option value="approved_by_head" <?php echo $status_filter === 'approved_by_head' ? 'selected' : ''; ?>>Pending HR</option>
                            <option value="approved_by_hr" <?php echo $status_filter === 'approved_by_hr' ? 'selected' : ''; ?>>Approved by HR</option>
                            <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
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
                               placeholder="Search employee or reason..." 
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
                        <a href="leave-approvals.php" class="text-sm text-seait-orange hover:text-orange-600">
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
                            <h2 class="text-xl font-semibold text-gray-900">My Approval Decisions</h2>
                            <p class="text-sm text-gray-600 mt-1">
                                Showing <?php echo count($requests_array); ?> decision(s)
                                <?php if ($total_days_approved > 0): ?>
                                    • Total days approved: <?php echo $total_days_approved; ?> days
                                <?php endif; ?>
                            </p>
                        </div>
                        <a href="leave-requests.php" class="bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition-colors duration-200">
                            <i class="fas fa-list mr-2"></i>View All Requests
                        </a>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <?php if (count($requests_array) > 0): ?>
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Leave Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Range</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Days</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Your Decision</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">HR Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Decision Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($requests_array as $request): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div>
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?>
                                                </div>
                                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($request['position']); ?></div>
                                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($request['email']); ?></div>
                                            </div>
                                        </td>
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
                                            $decision_colors = [
                                                'approved' => 'bg-green-100 text-green-800',
                                                'rejected' => 'bg-red-100 text-red-800'
                                            ];
                                            $decision_text = [
                                                'approved' => 'Approved',
                                                'rejected' => 'Rejected'
                                            ];
                                            $decision = $request['department_head_approval'];
                                            ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $decision_colors[$decision] ?? 'bg-gray-100 text-gray-800'; ?>">
                                                <?php echo $decision_text[$decision] ?? ucfirst($decision); ?>
                                            </span>
                                            <?php if ($request['department_head_comment']): ?>
                                                <div class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($request['department_head_comment']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                            $hr_status_colors = [
                                                'pending' => 'bg-yellow-100 text-yellow-800',
                                                'approved_by_hr' => 'bg-green-100 text-green-800',
                                                'rejected' => 'bg-red-100 text-red-800'
                                            ];
                                            $hr_status_text = [
                                                'pending' => 'Pending',
                                                'approved_by_head' => 'Pending',
                                                'approved_by_hr' => 'Approved',
                                                'rejected' => 'Rejected'
                                            ];
                                            $hr_status = $request['status'];
                                            ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $hr_status_colors[$hr_status] ?? 'bg-gray-100 text-gray-800'; ?>">
                                                <?php echo $hr_status_text[$hr_status] ?? ucfirst($hr_status); ?>
                                            </span>
                                            <?php if ($request['hr_comment']): ?>
                                                <div class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($request['hr_comment']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo $request['department_head_approved_at'] ? date('M d, Y g:i A', strtotime($request['department_head_approved_at'])) : 'N/A'; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <button onclick="viewLeaveDetails(<?php echo $request['id']; ?>)" 
                                                    class="text-seait-orange hover:text-orange-600">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="p-8 text-center">
                            <i class="fas fa-clipboard-check text-4xl text-gray-400 mb-4"></i>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">No approval decisions found</h3>
                            <p class="text-gray-500 mb-4">
                                <?php if ($status_filter || $year_filter || $leave_type_filter || $search): ?>
                                    No decisions match your current filters.
                                <?php else: ?>
                                    You haven't made any approval decisions yet.
                                <?php endif; ?>
                            </p>
                            <a href="leave-requests.php" class="bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition-colors duration-200">
                                <i class="fas fa-list mr-2"></i>View Pending Requests
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

        // Close modal when clicking outside
        document.getElementById('leaveDetailsModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeLeaveDetailsModal();
            }
        });
    </script>
</body>
</html>
