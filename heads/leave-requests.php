<?php
session_start();
require_once '../includes/error_handler.php';
require_once '../config/database.php';

// Check if user is logged in and is a department head
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'head') {
    header('Location: ../index.php?login=required&redirect=heads-leave');
    exit();
}

$page_title = 'Faculty Leave Requests';

// Get department head information
$head_id = $_SESSION['user_id'];
$head_query = "SELECT h.*, u.first_name, u.last_name, u.email 
               FROM heads h 
               JOIN users u ON h.user_id = u.id 
               WHERE h.user_id = ? AND h.status = 'active'";
$head_stmt = mysqli_prepare($conn, $head_query);
mysqli_stmt_bind_param($head_stmt, 'i', $head_id);
mysqli_stmt_execute($head_stmt);
$head_result = mysqli_stmt_get_result($head_stmt);

if (mysqli_num_rows($head_result) === 0) {
    header('Location: ../index.php');
    exit();
}

$head_info = mysqli_fetch_assoc($head_result);
$head_department = $head_info['department'];

// Check if faculty table exists and has data
$check_faculty_query = "SELECT COUNT(*) as count FROM faculty WHERE department = ? AND is_active = 1";
$check_faculty_stmt = mysqli_prepare($conn, $check_faculty_query);
mysqli_stmt_bind_param($check_faculty_stmt, 's', $head_department);
mysqli_stmt_execute($check_faculty_stmt);
$faculty_count = mysqli_fetch_assoc(mysqli_stmt_get_result($check_faculty_stmt))['count'];

if ($faculty_count == 0) {
    // No faculty in this department, show a message
    include 'includes/header.php';
    ?>
    <div class="space-y-6">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="text-center">
                <i class="fas fa-users text-blue-500 text-4xl mb-4"></i>
                <h1 class="text-2xl font-bold text-gray-900 mb-2">No Faculty Found</h1>
                <p class="text-gray-600 mb-4">There are no faculty members assigned to the <?php echo htmlspecialchars($head_department); ?> department.</p>
                <p class="text-sm text-gray-500">Leave requests will appear here once faculty members are added to your department.</p>
                <a href="dashboard.php" class="inline-block mt-4 bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition-colors duration-200">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </div>
    <?php
    include 'includes/footer.php';
    exit();
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

// Build query for faculty leave requests in the department
$faculty_leave_query = "SELECT 
    flr.id,
    flr.faculty_id,
    flr.leave_type_id,
    flr.start_date,
    flr.end_date,
    flr.total_days,
    flr.reason,
    flr.status,
    flr.department_head_approval,
    flr.hr_approval,
    flr.created_at,
    f.first_name, 
    f.last_name, 
    f.email,
    f.department,
    lt.name as leave_type_name,
    dh.first_name as head_first_name,
    dh.last_name as head_last_name,
    hr.first_name as hr_first_name,
    hr.last_name as hr_last_name
    FROM faculty_leave_requests flr
    JOIN faculty f ON flr.faculty_id = f.id
    JOIN leave_types lt ON flr.leave_type_id = lt.id
    LEFT JOIN faculty dh ON flr.department_head_id = dh.id
    LEFT JOIN faculty hr ON flr.hr_approver_id = hr.id
    WHERE f.department = ?";

$params = [$head_department];
$types = 's';

// Add filters
if ($status_filter) {
    $faculty_leave_query .= " AND flr.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if ($date_from) {
    $faculty_leave_query .= " AND flr.start_date >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if ($date_to) {
    $faculty_leave_query .= " AND flr.end_date <= ?";
    $params[] = $date_to;
    $types .= 's';
}

if ($search) {
    $faculty_leave_query .= " AND (f.first_name LIKE ? OR f.last_name LIKE ? OR f.email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

$faculty_leave_query .= " ORDER BY flr.created_at DESC";

$faculty_leave_stmt = mysqli_prepare($conn, $faculty_leave_query);
mysqli_stmt_bind_param($faculty_leave_stmt, $types, ...$params);
mysqli_stmt_execute($faculty_leave_stmt);
$faculty_leave_result = mysqli_stmt_get_result($faculty_leave_stmt);

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total_requests,
    SUM(CASE WHEN flr.status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN flr.status = 'approved_by_head' THEN 1 ELSE 0 END) as approved_by_head,
    SUM(CASE WHEN flr.status = 'approved_by_hr' THEN 1 ELSE 0 END) as approved_by_hr,
    SUM(CASE WHEN flr.status = 'rejected' THEN 1 ELSE 0 END) as rejected,
    SUM(CASE WHEN flr.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM faculty_leave_requests flr
    JOIN faculty f ON flr.faculty_id = f.id
    WHERE f.department = ?";

$stats_stmt = mysqli_prepare($conn, $stats_query);
mysqli_stmt_bind_param($stats_stmt, 's', $head_department);
mysqli_stmt_execute($stats_stmt);
$stats_result = mysqli_stmt_get_result($stats_stmt);
$stats = mysqli_fetch_assoc($stats_result);

include 'includes/header.php';
?>

<div class="space-y-6">
    <!-- Page Header -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-seait-dark">Faculty Leave Requests</h1>
                <p class="text-gray-600 mt-1">Review and approve leave requests from faculty in <?php echo htmlspecialchars($head_department); ?> department</p>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <div class="flex items-center">
                <div class="p-2 bg-blue-100 rounded-lg">
                    <i class="fas fa-calendar text-blue-600"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-gray-600">Total Requests</p>
                    <p class="text-lg font-semibold text-gray-900"><?php echo $stats['total_requests']; ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <div class="flex items-center">
                <div class="p-2 bg-yellow-100 rounded-lg">
                    <i class="fas fa-clock text-yellow-600"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-gray-600">Pending</p>
                    <p class="text-lg font-semibold text-gray-900"><?php echo $stats['pending']; ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <div class="flex items-center">
                <div class="p-2 bg-orange-100 rounded-lg">
                    <i class="fas fa-user-check text-orange-600"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-gray-600">Approved by Head</p>
                    <p class="text-lg font-semibold text-gray-900"><?php echo $stats['approved_by_head']; ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <div class="flex items-center">
                <div class="p-2 bg-green-100 rounded-lg">
                    <i class="fas fa-check-circle text-green-600"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-gray-600">Approved by HR</p>
                    <p class="text-lg font-semibold text-gray-900"><?php echo $stats['approved_by_hr']; ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <div class="flex items-center">
                <div class="p-2 bg-red-100 rounded-lg">
                    <i class="fas fa-times-circle text-red-600"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-gray-600">Rejected</p>
                    <p class="text-lg font-semibold text-gray-900"><?php echo $stats['rejected']; ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <div class="flex items-center">
                <div class="p-2 bg-gray-100 rounded-lg">
                    <i class="fas fa-ban text-gray-600"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-gray-600">Cancelled</p>
                    <p class="text-lg font-semibold text-gray-900"><?php echo $stats['cancelled']; ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                    <option value="">All Status</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved_by_head" <?php echo $status_filter === 'approved_by_head' ? 'selected' : ''; ?>>Approved by Head</option>
                    <option value="approved_by_hr" <?php echo $status_filter === 'approved_by_hr' ? 'selected' : ''; ?>>Approved by HR</option>
                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
                <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from ?? ''); ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to ?? ''); ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search ?? ''); ?>" placeholder="Faculty name or email" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent">
            </div>

            <div class="flex items-end">
                <button type="submit" class="bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition-colors duration-200 mr-2">
                    <i class="fas fa-search mr-1"></i> Filter
                </button>
                <a href="leave-requests.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition-colors duration-200">
                    <i class="fas fa-times mr-1"></i> Clear
                </a>
            </div>
        </form>
    </div>

    <!-- Leave Requests Table -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Faculty Leave Requests</h2>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Faculty</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Leave Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Range</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Days</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (mysqli_num_rows($faculty_leave_result) > 0): ?>
                        <?php while ($leave = mysqli_fetch_assoc($faculty_leave_result)): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div class="h-10 w-10 rounded-full bg-seait-orange flex items-center justify-center">
                                                <span class="text-white font-medium text-sm">
                                                    <?php echo strtoupper(substr($leave['first_name'], 0, 1) . substr($leave['last_name'], 0, 1)); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name']); ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <?php echo htmlspecialchars($leave['email']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-900"><?php echo htmlspecialchars($leave['leave_type_name']); ?></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?php echo date('M d, Y', strtotime($leave['start_date'])); ?>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        to <?php echo date('M d, Y', strtotime($leave['end_date'])); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-900"><?php echo $leave['total_days']; ?> days</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $status_colors = [
                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                        'approved_by_head' => 'bg-orange-100 text-orange-800',
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
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $status_colors[$leave['status']]; ?>">
                                        <?php echo $status_text[$leave['status']]; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <button onclick="viewLeaveDetails(<?php echo $leave['id']; ?>)" class="text-seait-orange hover:text-orange-600">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($leave['status'] === 'pending'): ?>
                                            <button onclick="approveLeave(<?php echo $leave['id']; ?>)" class="text-green-600 hover:text-green-800">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button onclick="rejectLeave(<?php echo $leave['id']; ?>)" class="text-red-600 hover:text-red-800">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                No leave requests found for this department.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Leave Details Modal -->
<div id="leaveDetailsModal" class="fixed inset-0 bg-black bg-opacity-50 overflow-y-auto h-full w-full hidden z-50 transition-all duration-300 ease-in-out backdrop-blur-sm">
    <div class="relative top-10 mx-auto p-0 border-0 w-11/12 max-w-4xl shadow-2xl rounded-xl bg-white transform scale-95 opacity-0 transition-all duration-300 ease-out" id="leaveDetailsModalContent">
        <!-- Header -->
        <div class="bg-gradient-to-r from-seait-orange to-orange-500 rounded-t-xl p-6 text-white">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-eye text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold">Leave Request Details</h3>
                        <p class="text-orange-100 text-sm">View detailed information about the leave request</p>
                    </div>
                </div>
                <button onclick="closeLeaveDetailsModal()" class="text-white hover:text-orange-200 transition-colors duration-200 p-2 rounded-full hover:bg-white hover:bg-opacity-20">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        
        <!-- Content -->
        <div class="p-6">
            <div id="leaveDetailsContent">
                <!-- Content will be loaded here -->
            </div>
        </div>

        <!-- Footer -->
        <div class="bg-gray-50 rounded-b-xl p-4 border-t border-gray-200">
            <div class="flex justify-end">
                <button onclick="closeLeaveDetailsModal()" class="bg-seait-orange hover:bg-orange-600 text-white px-6 py-2 rounded-lg transition-all duration-200 font-medium shadow-md hover:shadow-lg transform hover:scale-105">
                    <i class="fas fa-check mr-2"></i>
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Approve Leave Modal -->
<div id="approveLeaveModal" class="fixed inset-0 bg-black bg-opacity-50 overflow-y-auto h-full w-full hidden z-50 transition-all duration-300 ease-in-out backdrop-blur-sm">
    <div class="relative top-20 mx-auto p-0 border-0 w-full max-w-md shadow-2xl rounded-xl bg-white transform scale-95 opacity-0 transition-all duration-300 ease-out" id="approveLeaveModalContent">
        <!-- Header -->
        <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-t-xl p-6 text-white">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-check text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold">Approve Leave Request</h3>
                        <p class="text-green-100 text-sm">Confirm approval of this leave request</p>
                    </div>
                </div>
                <button onclick="closeApproveLeaveModal()" class="text-white hover:text-green-200 transition-colors duration-200 p-2 rounded-full hover:bg-white hover:bg-opacity-20">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        
        <!-- Content -->
        <div class="p-6">
            <div class="flex items-start">
                <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center mr-4 mt-1">
                    <i class="fas fa-user-check text-green-600"></i>
                </div>
                <div class="flex-1">
                    <h4 class="text-lg font-semibold text-gray-900 mb-2">Confirm Leave Approval</h4>
                    <p class="text-gray-600 leading-relaxed">Are you sure you want to approve this leave request? This action will forward the request to HR for final approval.</p>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="bg-gray-50 rounded-b-xl p-4 border-t border-gray-200">
            <form id="approveLeaveForm">
                <input type="hidden" name="leave_id" id="approveLeaveId" value="">
                <input type="hidden" name="table" value="faculty">
                <input type="hidden" name="action" value="approve">
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeApproveLeaveModal()" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg transition-all duration-200 font-medium shadow-md hover:shadow-lg transform hover:scale-105">
                        <i class="fas fa-times mr-2"></i>
                        Cancel
                    </button>
                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg transition-all duration-200 font-medium shadow-md hover:shadow-lg transform hover:scale-105">
                        <i class="fas fa-check mr-2"></i>
                        Approve Leave
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Leave Modal -->
<div id="rejectLeaveModal" class="fixed inset-0 bg-black bg-opacity-50 overflow-y-auto h-full w-full hidden z-50 transition-all duration-300 ease-in-out backdrop-blur-sm">
    <div class="relative top-20 mx-auto p-0 border-0 w-full max-w-md shadow-2xl rounded-xl bg-white transform scale-95 opacity-0 transition-all duration-300 ease-out" id="rejectLeaveModalContent">
        <!-- Header -->
        <div class="bg-gradient-to-r from-red-500 to-red-600 rounded-t-xl p-6 text-white">
        <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-times text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold">Reject Leave Request</h3>
                        <p class="text-red-100 text-sm">Provide reason for rejection</p>
                    </div>
                </div>
                <button onclick="closeRejectLeaveModal()" class="text-white hover:text-red-200 transition-colors duration-200 p-2 rounded-full hover:bg-white hover:bg-opacity-20">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        
        <!-- Content -->
        <div class="p-6">
            <div class="flex items-start">
                <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center mr-4 mt-1">
                    <i class="fas fa-exclamation-triangle text-red-600"></i>
                </div>
                <div class="flex-1">
                    <h4 class="text-lg font-semibold text-gray-900 mb-2">Reject Leave Request</h4>
                    <p class="text-gray-600 leading-relaxed mb-4">Please provide a reason for rejecting this leave request. This will be communicated to the faculty member.</p>
                    
                    <div>
                        <label for="rejectReason" class="block text-sm font-medium text-gray-700 mb-2">Rejection Reason *</label>
                        <textarea id="rejectReason" name="reason" rows="4" required 
                                  placeholder="Enter the reason for rejection..."
                                  class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent resize-none"></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="bg-gray-50 rounded-b-xl p-4 border-t border-gray-200">
            <form id="rejectLeaveForm">
                <input type="hidden" name="leave_id" id="rejectLeaveId" value="">
                <input type="hidden" name="table" value="faculty">
                <input type="hidden" name="action" value="reject">
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeRejectLeaveModal()" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg transition-all duration-200 font-medium shadow-md hover:shadow-lg transform hover:scale-105">
                        <i class="fas fa-times mr-2"></i>
                        Cancel
                    </button>
                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-6 py-2 rounded-lg transition-all duration-200 font-medium shadow-md hover:shadow-lg transform hover:scale-105">
                        <i class="fas fa-times mr-2"></i>
                        Reject Leave
            </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function viewLeaveDetails(leaveId) {
    console.log('Opening modal for leave ID:', leaveId);
    
    const modal = document.getElementById('leaveDetailsModal');
    const modalContent = document.getElementById('leaveDetailsModalContent');
    
    // Show loading state
    document.getElementById('leaveDetailsContent').innerHTML = '<div class="text-center py-8"><i class="fas fa-spinner fa-spin text-2xl text-seait-orange"></i><p class="mt-2 text-gray-600">Loading details...</p></div>';
    
    // Show modal with animation
    modal.classList.remove('hidden');
    setTimeout(() => {
        modalContent.classList.remove('scale-95', 'opacity-0');
        modalContent.classList.add('scale-100', 'opacity-100');
    }, 10);
    
    fetch(`get-leave-details.php?id=${leaveId}&table=faculty`)
        .then(response => {
            console.log('Response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text();
        })
        .then(html => {
            console.log('Received HTML length:', html.length);
            document.getElementById('leaveDetailsContent').innerHTML = html;
        })
        .catch(error => {
            console.error('Error loading leave details:', error);
            document.getElementById('leaveDetailsContent').innerHTML = '<div class="text-center py-8 text-red-600"><i class="fas fa-exclamation-triangle text-2xl"></i><p class="mt-2">Error loading leave details: ' + error.message + '</p></div>';
        });
}

function closeLeaveDetailsModal() {
    const modal = document.getElementById('leaveDetailsModal');
    const modalContent = document.getElementById('leaveDetailsModalContent');
    
    // Start closing animation
    modalContent.classList.remove('scale-100', 'opacity-100');
    modalContent.classList.add('scale-95', 'opacity-0');
    
    // Hide modal after animation completes
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}

function approveLeave(leaveId) {
    const modal = document.getElementById('approveLeaveModal');
    const modalContent = document.getElementById('approveLeaveModalContent');
    
    // Set the leave ID
    document.getElementById('approveLeaveId').value = leaveId;
    
    // Show modal with animation
    modal.classList.remove('hidden');
    setTimeout(() => {
        modalContent.classList.remove('scale-95', 'opacity-0');
        modalContent.classList.add('scale-100', 'opacity-100');
    }, 10);
}

function closeApproveLeaveModal() {
    const modal = document.getElementById('approveLeaveModal');
    const modalContent = document.getElementById('approveLeaveModalContent');
    
    // Start closing animation
    modalContent.classList.remove('scale-100', 'opacity-100');
    modalContent.classList.add('scale-95', 'opacity-0');
    
    // Hide modal after animation completes
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}

function rejectLeave(leaveId) {
    const modal = document.getElementById('rejectLeaveModal');
    const modalContent = document.getElementById('rejectLeaveModalContent');
    
    // Set the leave ID and clear previous reason
    document.getElementById('rejectLeaveId').value = leaveId;
    document.getElementById('rejectReason').value = '';
    
    // Show modal with animation
    modal.classList.remove('hidden');
    setTimeout(() => {
        modalContent.classList.remove('scale-95', 'opacity-0');
        modalContent.classList.add('scale-100', 'opacity-100');
    }, 10);
}

function closeRejectLeaveModal() {
    const modal = document.getElementById('rejectLeaveModal');
    const modalContent = document.getElementById('rejectLeaveModalContent');
    
    // Start closing animation
    modalContent.classList.remove('scale-100', 'opacity-100');
    modalContent.classList.add('scale-95', 'opacity-0');
    
    // Hide modal after animation completes
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}

// Form submission handlers
document.getElementById('approveLeaveForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const data = {
        leave_id: formData.get('leave_id'),
        table: formData.get('table'),
        action: formData.get('action')
    };
    
    // Show loading state
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
    submitBtn.disabled = true;
    
    fetch('approve-leave.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
        body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
            // Show success message
            showNotification('Leave request approved successfully!', 'success');
            closeApproveLeaveModal();
                location.reload();
            } else {
            showNotification('Error: ' + data.message, 'error');
            }
        })
        .catch(error => {
        console.error('Error:', error);
        showNotification('An error occurred while approving the leave request.', 'error');
    })
    .finally(() => {
        // Restore button state
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
});

document.getElementById('rejectLeaveForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const reason = document.getElementById('rejectReason').value.trim();
    
    if (!reason) {
        showNotification('Please provide a reason for rejection.', 'error');
        return;
    }
    
    const data = {
        leave_id: formData.get('leave_id'),
        table: formData.get('table'),
        action: formData.get('action'),
        reason: reason
    };
    
    // Show loading state
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
    submitBtn.disabled = true;
    
    fetch('approve-leave.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
        body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
            // Show success message
            showNotification('Leave request rejected successfully!', 'success');
            closeRejectLeaveModal();
                location.reload();
            } else {
            showNotification('Error: ' + data.message, 'error');
            }
        })
        .catch(error => {
        console.error('Error:', error);
        showNotification('An error occurred while rejecting the leave request.', 'error');
    })
    .finally(() => {
        // Restore button state
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
});

// Close modals when clicking outside
window.onclick = function(event) {
    const leaveDetailsModal = document.getElementById('leaveDetailsModal');
    const approveLeaveModal = document.getElementById('approveLeaveModal');
    const rejectLeaveModal = document.getElementById('rejectLeaveModal');
    
    if (event.target === leaveDetailsModal) {
        closeLeaveDetailsModal();
    }
    if (event.target === approveLeaveModal) {
        closeApproveLeaveModal();
    }
    if (event.target === rejectLeaveModal) {
        closeRejectLeaveModal();
    }
}

// Notification function
function showNotification(message, type) {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg transform transition-all duration-300 translate-x-full`;
    
    if (type === 'success') {
        notification.classList.add('bg-green-500', 'text-white');
        notification.innerHTML = `
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-2"></i>
                <span>${message}</span>
            </div>
        `;
    } else {
        notification.classList.add('bg-red-500', 'text-white');
        notification.innerHTML = `
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <span>${message}</span>
            </div>
        `;
    }
    
    // Add to page
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
        notification.classList.remove('translate-x-full');
    }, 100);
    
    // Remove after 5 seconds
    setTimeout(() => {
        notification.classList.add('translate-x-full');
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 300);
    }, 5000);
}
</script>
