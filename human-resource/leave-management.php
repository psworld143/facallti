<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is HR
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hr') {
    header('Location: ../login.php');
    exit();
}

$page_title = 'Leave Management';

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$department_filter = $_GET['department'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

// Build query for leave requests
$query = "SELECT lr.*, 
          e.first_name, e.last_name, e.employee_id, e.department,
          lt.name as leave_type_name,
          dh.first_name as head_first_name, dh.last_name as head_last_name,
          hr.first_name as hr_first_name, hr.last_name as hr_last_name
          FROM leave_requests lr
          JOIN employees e ON lr.employee_id = e.id
          JOIN leave_types lt ON lr.leave_type_id = lt.id
          LEFT JOIN employees dh ON lr.department_head_id = dh.id
          LEFT JOIN employees hr ON lr.hr_approver_id = hr.id
          WHERE 1=1";

$params = [];
$types = '';

if ($status_filter) {
    $query .= " AND lr.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if ($department_filter) {
    $query .= " AND e.department = ?";
    $params[] = $department_filter;
    $types .= 's';
}

if ($date_from) {
    $query .= " AND lr.start_date >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if ($date_to) {
    $query .= " AND lr.end_date <= ?";
    $params[] = $date_to;
    $types .= 's';
}

if ($search) {
    $query .= " AND (e.first_name LIKE ? OR e.last_name LIKE ? OR e.employee_id LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

$query .= " ORDER BY lr.created_at DESC";

// Prepare and execute query
$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Get departments for filter
$departments_query = "SELECT DISTINCT department FROM employees WHERE department IS NOT NULL ORDER BY department";
$departments_result = mysqli_query($conn, $departments_query);

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total_requests,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
    SUM(CASE WHEN status = 'approved_by_head' THEN 1 ELSE 0 END) as approved_by_head,
    SUM(CASE WHEN status = 'approved_by_hr' THEN 1 ELSE 0 END) as approved_by_hr,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_requests
    FROM leave_requests";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

include 'includes/header.php';
?>

<div class="space-y-6">
    <!-- Page Header -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-seait-dark">Leave Management</h1>
                <p class="text-gray-600 mt-1">Manage and approve employee leave requests</p>
            </div>
            <div class="mt-4 sm:mt-0">
                <button onclick="openNewLeaveModal()" class="bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition-colors duration-200 flex items-center">
                    <i class="fas fa-plus mr-2"></i>
                    New Leave Request
                </button>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
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
                    <p class="text-lg font-semibold text-gray-900"><?php echo $stats['pending_requests']; ?></p>
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
                    <p class="text-lg font-semibold text-gray-900"><?php echo $stats['rejected_requests']; ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
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
                <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                <select name="department" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                    <option value="">All Departments</option>
                    <?php while ($dept = mysqli_fetch_assoc($departments_result)): ?>
                        <option value="<?php echo htmlspecialchars($dept['department']); ?>" <?php echo $department_filter === $dept['department'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dept['department']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
                <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Employee name or ID" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent">
            </div>

            <div class="flex items-end">
                <button type="submit" class="bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition-colors duration-200 mr-2">
                    <i class="fas fa-search mr-1"></i> Filter
                </button>
                <a href="leave-management.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition-colors duration-200">
                    <i class="fas fa-times mr-1"></i> Clear
                </a>
            </div>
        </form>
    </div>

    <!-- Leave Requests Table -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Leave Requests</h2>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Leave Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Range</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Days</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department Head</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (mysqli_num_rows($result) > 0): ?>
                        <?php while ($leave = mysqli_fetch_assoc($result)): ?>
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
                                                <?php echo htmlspecialchars($leave['employee_id']); ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <?php echo htmlspecialchars($leave['department']); ?>
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
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($leave['head_first_name']): ?>
                                        <div class="text-sm text-gray-900">
                                            <?php echo htmlspecialchars($leave['head_first_name'] . ' ' . $leave['head_last_name']); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <?php echo $leave['department_head_approval'] === 'approved' ? 'Approved' : ($leave['department_head_approval'] === 'rejected' ? 'Rejected' : 'Pending'); ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-sm text-gray-500">Not assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <button onclick="viewLeaveDetails(<?php echo $leave['id']; ?>)" class="text-seait-orange hover:text-orange-600">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($leave['status'] === 'approved_by_head'): ?>
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
                            <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                                No leave requests found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
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
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="leaveDetailsContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<!-- New Leave Request Modal -->
<div id="newLeaveModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Create New Leave Request</h3>
                <button onclick="closeNewLeaveModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="newLeaveForm" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Employee</label>
                        <select name="employee_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                            <option value="">Select Employee</option>
                            <?php
                            $employees_query = "SELECT id, first_name, last_name, employee_id, department FROM employees WHERE is_active = 1 ORDER BY first_name, last_name";
                            $employees_result = mysqli_query($conn, $employees_query);
                            while ($employee = mysqli_fetch_assoc($employees_result)):
                            ?>
                                <option value="<?php echo $employee['id']; ?>">
                                    <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name'] . ' (' . $employee['employee_id'] . ') - ' . $employee['department']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Leave Type</label>
                        <select name="leave_type_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                            <option value="">Select Leave Type</option>
                            <?php
                            $leave_types_query = "SELECT id, name FROM leave_types WHERE is_active = 1 ORDER BY name";
                            $leave_types_result = mysqli_query($conn, $leave_types_query);
                            while ($leave_type = mysqli_fetch_assoc($leave_types_result)):
                            ?>
                                <option value="<?php echo $leave_type['id']; ?>">
                                    <?php echo htmlspecialchars($leave_type['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

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
                        Create Request
                    </button>
                </div>
            </form>
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
    // Load leave details via AJAX
    fetch(`get-leave-details.php?id=${leaveId}`)
        .then(response => response.text())
        .then(data => {
            document.getElementById('leaveDetailsContent').innerHTML = data;
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

function approveLeave(leaveId) {
    if (confirm('Are you sure you want to approve this leave request?')) {
        fetch('approve-leave.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                leave_id: leaveId,
                action: 'approve'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Leave request approved successfully');
                location.reload();
            } else {
                alert(data.message || 'Error approving leave request');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error approving leave request');
        });
    }
}

function rejectLeave(leaveId) {
    const comment = prompt('Please provide a reason for rejection:');
    if (comment !== null) {
        fetch('approve-leave.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                leave_id: leaveId,
                action: 'reject',
                comment: comment
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Leave request rejected successfully');
                location.reload();
            } else {
                alert(data.message || 'Error rejecting leave request');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error rejecting leave request');
        });
    }
}

// Handle new leave form submission
document.getElementById('newLeaveForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('create-leave-request.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Leave request created successfully');
            closeNewLeaveModal();
            location.reload();
        } else {
            alert(data.message || 'Error creating leave request');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error creating leave request');
    });
});
</script>

<?php include 'includes/footer.php'; ?>
