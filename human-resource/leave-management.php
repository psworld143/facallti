<?php
session_start();
require_once '../includes/error_handler.php';
require_once '../config/database.php';

// Check if user is logged in and is HR
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'human_resource') {
    header('Location: ../index.php?login=required&redirect=leave-management');
    exit();
}

$page_title = 'Leave Management';

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$department_filter = $_GET['department'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';
$tab_filter = $_GET['tab'] ?? 'all'; // all, employee, faculty

// Build queries for leave requests based on tab filter
$all_results = [];

// Query for employees (admin/staff)
if ($tab_filter === 'all' || $tab_filter === 'employee') {
    $employee_where_conditions = [];
    $employee_params = [];
    $employee_types = '';
    
    if ($status_filter) {
        $employee_where_conditions[] = "elr.status = ?";
        $employee_params[] = $status_filter;
        $employee_types .= 's';
    }
    
    if ($date_from) {
        $employee_where_conditions[] = "elr.start_date >= ?";
        $employee_params[] = $date_from;
        $employee_types .= 's';
    }
    
    if ($date_to) {
        $employee_where_conditions[] = "elr.end_date <= ?";
        $employee_params[] = $date_to;
        $employee_types .= 's';
    }
    
    $employee_where_clause = !empty($employee_where_conditions) ? "WHERE " . implode(" AND ", $employee_where_conditions) : "";
    
    $employee_query = "SELECT elr.*, 
                      e.first_name, e.last_name, e.employee_id, e.department, e.employee_type,
                      lt.name as leave_type_name,
                      dh.first_name as head_first_name, dh.last_name as head_last_name,
                      hr.first_name as hr_first_name, hr.last_name as hr_last_name,
                      'employee' as source_table
                      FROM employee_leave_requests elr
                      JOIN employees e ON elr.employee_id = e.id
                      JOIN leave_types lt ON elr.leave_type_id = lt.id
                      LEFT JOIN employees dh ON elr.department_head_id = dh.id
                      LEFT JOIN employees hr ON elr.hr_approver_id = hr.id
                      $employee_where_clause";
    
    if ($department_filter) {
        $employee_query .= " AND e.department = ?";
        $employee_params[] = $department_filter;
        $employee_types .= 's';
    }
    
    if ($search) {
        $employee_query .= " AND (e.first_name LIKE ? OR e.last_name LIKE ? OR e.employee_id LIKE ?)";
        $search_param = "%$search%";
        $employee_params[] = $search_param;
        $employee_params[] = $search_param;
        $employee_params[] = $search_param;
        $employee_types .= 'sss';
    }
    
    $employee_query .= " ORDER BY elr.created_at DESC";
    
    // Execute employee query
    $employee_stmt = mysqli_prepare($conn, $employee_query);
    if ($employee_stmt) {
        if (!empty($employee_params)) {
            mysqli_stmt_bind_param($employee_stmt, $employee_types, ...$employee_params);
        }
        mysqli_stmt_execute($employee_stmt);
        $employee_result = mysqli_stmt_get_result($employee_stmt);
        
        while ($row = mysqli_fetch_assoc($employee_result)) {
            $all_results[] = $row;
        }
    }
}

// Query for faculty
if ($tab_filter === 'all' || $tab_filter === 'faculty') {
    $faculty_where_conditions = [];
    $faculty_params = [];
    $faculty_types = '';
    
    if ($status_filter) {
        $faculty_where_conditions[] = "flr.status = ?";
        $faculty_params[] = $status_filter;
        $faculty_types .= 's';
    }
    
    if ($date_from) {
        $faculty_where_conditions[] = "flr.start_date >= ?";
        $faculty_params[] = $date_from;
        $faculty_types .= 's';
    }
    
    if ($date_to) {
        $faculty_where_conditions[] = "flr.end_date <= ?";
        $faculty_params[] = $date_to;
        $faculty_types .= 's';
    }
    
    if ($department_filter) {
        $faculty_where_conditions[] = "f.department = ?";
        $faculty_params[] = $department_filter;
        $faculty_types .= 's';
    }
    
    if ($search) {
        $faculty_where_conditions[] = "(f.first_name LIKE ? OR f.last_name LIKE ? OR f.id LIKE ?)";
        $search_param = "%$search%";
        $faculty_params[] = $search_param;
        $faculty_params[] = $search_param;
        $faculty_params[] = $search_param;
        $faculty_types .= 'sss';
    }
    
    $faculty_where_clause = !empty($faculty_where_conditions) ? "WHERE " . implode(" AND ", $faculty_where_conditions) : "";
    
    $faculty_query = "SELECT flr.*, 
                     f.first_name, f.last_name, f.id as employee_id, f.department, 'faculty' as employee_type,
                     lt.name as leave_type_name,
                     dh.first_name as head_first_name, dh.last_name as head_last_name,
                     hr.first_name as hr_first_name, hr.last_name as hr_last_name,
                     'faculty' as source_table
                     FROM faculty_leave_requests flr
                     JOIN faculty f ON flr.faculty_id = f.id
                     JOIN leave_types lt ON flr.leave_type_id = lt.id
                     LEFT JOIN faculty dh ON flr.department_head_id = dh.id
                     LEFT JOIN faculty hr ON flr.hr_approver_id = hr.id
                     $faculty_where_clause";
    
    $faculty_query .= " ORDER BY flr.created_at DESC";
    
    // Execute faculty query
    $faculty_stmt = mysqli_prepare($conn, $faculty_query);
    if ($faculty_stmt) {
        if (!empty($faculty_params)) {
            mysqli_stmt_bind_param($faculty_stmt, $faculty_types, ...$faculty_params);
        }
        mysqli_stmt_execute($faculty_stmt);
        $faculty_result = mysqli_stmt_get_result($faculty_stmt);
        
        while ($row = mysqli_fetch_assoc($faculty_result)) {
            $all_results[] = $row;
        }
    }
}

// Sort all results by created_at DESC
usort($all_results, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// Calculate statistics for each tab
$stats = [];

// Employee statistics
if ($tab_filter === 'all' || $tab_filter === 'employee') {
    $employee_stats_query = "SELECT 
        COUNT(*) as total_requests,
        SUM(CASE WHEN elr.status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN elr.status = 'approved_by_head' THEN 1 ELSE 0 END) as approved_by_head,
        SUM(CASE WHEN elr.status = 'approved_by_hr' THEN 1 ELSE 0 END) as approved_by_hr,
        SUM(CASE WHEN elr.status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN elr.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
        FROM employee_leave_requests elr
        JOIN employees e ON elr.employee_id = e.id";
    
    $employee_stats_result = mysqli_query($conn, $employee_stats_query);
    if ($employee_stats_result) {
        $stats['employee'] = mysqli_fetch_assoc($employee_stats_result);
    }
}

// Faculty statistics
if ($tab_filter === 'all' || $tab_filter === 'faculty') {
    $faculty_stats_query = "SELECT 
        COUNT(*) as total_requests,
        SUM(CASE WHEN flr.status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN flr.status = 'approved_by_head' THEN 1 ELSE 0 END) as approved_by_head,
        SUM(CASE WHEN flr.status = 'approved_by_hr' THEN 1 ELSE 0 END) as approved_by_hr,
        SUM(CASE WHEN flr.status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN flr.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
        FROM faculty_leave_requests flr
        JOIN faculty f ON flr.faculty_id = f.id";
    
    $faculty_stats_result = mysqli_query($conn, $faculty_stats_query);
    if ($faculty_stats_result) {
        $stats['faculty'] = mysqli_fetch_assoc($faculty_stats_result);
    }
}

// Get departments for filter from both tables
$departments = [];
$employee_depts_query = "SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND department != ''";
$employee_depts_result = mysqli_query($conn, $employee_depts_query);
while ($row = mysqli_fetch_assoc($employee_depts_result)) {
    $departments[] = $row['department'];
}

$faculty_depts_query = "SELECT DISTINCT department FROM faculty WHERE department IS NOT NULL AND department != ''";
$faculty_depts_result = mysqli_query($conn, $faculty_depts_query);
while ($row = mysqli_fetch_assoc($faculty_depts_result)) {
    $departments[] = $row['department'];
}

$departments = array_unique($departments);
sort($departments);

include 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-900">Leave Management</h1>
        <button onclick="openCreateLeaveModal()" class="bg-seait-orange hover:bg-orange-600 text-white px-4 py-2 rounded-lg flex items-center gap-2">
            <i class="fas fa-plus"></i>
            Create Leave Request
        </button>
    </div>

    <!-- Tab Navigation -->
    <div class="mb-6">
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex space-x-8">
                <a href="?tab=all<?php echo $status_filter ? '&status=' . htmlspecialchars($status_filter) : ''; ?><?php echo $department_filter ? '&department=' . htmlspecialchars($department_filter) : ''; ?><?php echo $date_from ? '&date_from=' . htmlspecialchars($date_from) : ''; ?><?php echo $date_to ? '&date_to=' . htmlspecialchars($date_to) : ''; ?><?php echo $search ? '&search=' . htmlspecialchars($search) : ''; ?>" 
                   class="py-2 px-1 border-b-2 font-medium text-sm <?php echo $tab_filter === 'all' ? 'border-seait-orange text-seait-orange' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>">
                    <i class="fas fa-list mr-2"></i>All Leave Requests
                </a>
                <a href="?tab=employee<?php echo $status_filter ? '&status=' . htmlspecialchars($status_filter) : ''; ?><?php echo $department_filter ? '&department=' . htmlspecialchars($department_filter) : ''; ?><?php echo $date_from ? '&date_from=' . htmlspecialchars($date_from) : ''; ?><?php echo $date_to ? '&date_to=' . htmlspecialchars($date_to) : ''; ?><?php echo $search ? '&search=' . htmlspecialchars($search) : ''; ?>" 
                   class="py-2 px-1 border-b-2 font-medium text-sm <?php echo $tab_filter === 'employee' ? 'border-seait-orange text-seait-orange' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>">
                    <i class="fas fa-users mr-2"></i>Employee Leave Requests
                </a>
                <a href="?tab=faculty<?php echo $status_filter ? '&status=' . htmlspecialchars($status_filter) : ''; ?><?php echo $department_filter ? '&department=' . htmlspecialchars($department_filter) : ''; ?><?php echo $date_from ? '&date_from=' . htmlspecialchars($date_from) : ''; ?><?php echo $date_to ? '&date_to=' . htmlspecialchars($date_to) : ''; ?><?php echo $search ? '&search=' . htmlspecialchars($search) : ''; ?>" 
                   class="py-2 px-1 border-b-2 font-medium text-sm <?php echo $tab_filter === 'faculty' ? 'border-seait-orange text-seait-orange' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>">
                    <i class="fas fa-chalkboard-teacher mr-2"></i>Faculty Leave Requests
                </a>
            </nav>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <?php if ($tab_filter === 'all' || $tab_filter === 'employee'): ?>
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                    <i class="fas fa-users text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Employee Requests</p>
                    <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['employee']['total_requests'] ?? 0; ?></p>
                </div>
            </div>
            <div class="mt-4 grid grid-cols-2 gap-2 text-xs">
                <div class="text-green-600">Approved: <?php echo ($stats['employee']['approved_by_head'] ?? 0) + ($stats['employee']['approved_by_hr'] ?? 0); ?></div>
                <div class="text-yellow-600">Pending: <?php echo $stats['employee']['pending'] ?? 0; ?></div>
                <div class="text-red-600">Rejected: <?php echo $stats['employee']['rejected'] ?? 0; ?></div>
                <div class="text-gray-600">Cancelled: <?php echo $stats['employee']['cancelled'] ?? 0; ?></div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($tab_filter === 'all' || $tab_filter === 'faculty'): ?>
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                    <i class="fas fa-chalkboard-teacher text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Faculty Requests</p>
                    <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['faculty']['total_requests'] ?? 0; ?></p>
                </div>
            </div>
            <div class="mt-4 grid grid-cols-2 gap-2 text-xs">
                <div class="text-green-600">Approved: <?php echo ($stats['faculty']['approved_by_head'] ?? 0) + ($stats['faculty']['approved_by_hr'] ?? 0); ?></div>
                <div class="text-yellow-600">Pending: <?php echo $stats['faculty']['pending'] ?? 0; ?></div>
                <div class="text-red-600">Rejected: <?php echo $stats['faculty']['rejected'] ?? 0; ?></div>
                <div class="text-gray-600">Cancelled: <?php echo $stats['faculty']['cancelled'] ?? 0; ?></div>
            </div>
            <?php if ($tab_filter === 'faculty'): ?>
            <div class="mt-3 p-2 bg-blue-50 border border-blue-200 rounded text-xs text-blue-700">
                <i class="fas fa-info-circle mr-1"></i>
                <strong>Note:</strong> Faculty leave requests must be approved by their department head first before HR can approve them.
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Filters</h3>
        </div>
        <div class="p-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
                <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab_filter); ?>">
                
                <div>
                    <label for="status-filter" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" id="status-filter" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved_by_head" <?php echo $status_filter === 'approved_by_head' ? 'selected' : ''; ?>>Approved by Head</option>
                        <option value="approved_by_hr" <?php echo $status_filter === 'approved_by_hr' ? 'selected' : ''; ?>>Approved by HR</option>
                        <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                
                <div>
                    <label for="department-filter" class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                    <select name="department" id="department-filter" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo $department_filter === $dept ? 'selected' : ''; ?>><?php echo htmlspecialchars($dept); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="date-from" class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
                    <input type="date" name="date_from" id="date-from" value="<?php echo htmlspecialchars($date_from ?? ''); ?>" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                </div>
                
                <div>
                    <label for="date-to" class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                    <input type="date" name="date_to" id="date-to" value="<?php echo htmlspecialchars($date_to ?? ''); ?>" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                </div>
                
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search ?? ''); ?>" placeholder="Name, ID..." class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                </div>
                
                <div class="flex items-end space-x-2">
                    <button type="submit" class="bg-seait-orange hover:bg-orange-600 text-white px-4 py-2 rounded-md flex-1">
                        <i class="fas fa-search mr-2"></i>Filter
                    </button>
                    <a href="leave-management.php?tab=<?php echo htmlspecialchars($tab_filter); ?>" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Leave Requests Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Leave Requests</h3>
        </div>
        
        <!-- Desktop Table View -->
        <div class="hidden lg:block overflow-x-auto">
            <table class="w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/4">Employee</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/12">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/6">Leave Type</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/6">Dates</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/12">Days</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/6">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/6">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($all_results)): ?>
                    <tr>
                        <td colspan="7" class="px-4 py-4 text-center text-gray-500">No leave requests found.</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($all_results as $leave): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-4">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-8 w-8">
                                        <div class="h-8 w-8 rounded-full bg-seait-orange flex items-center justify-center text-white font-semibold text-sm">
                                            <?php echo substr($leave['first_name'] ?? '', 0, 1) . substr($leave['last_name'] ?? '', 0, 1); ?>
                                        </div>
                                    </div>
                                    <div class="ml-3">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars(($leave['first_name'] ?? '') . ' ' . ($leave['last_name'] ?? '')); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($leave['department'] ?? ''); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php echo $leave['source_table'] === 'faculty' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'; ?>">
                                    <?php echo $leave['source_table'] === 'faculty' ? 'Faculty' : 'Employee'; ?>
                                </span>
                            </td>
                            <td class="px-4 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($leave['leave_type_name'] ?? ''); ?></td>
                            <td class="px-4 py-4 text-sm text-gray-900">
                                <div><?php echo date('M j, Y', strtotime($leave['start_date'])); ?></div>
                                <div class="text-gray-500 text-xs">to <?php echo date('M j, Y', strtotime($leave['end_date'])); ?></div>
                            </td>
                            <td class="px-4 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($leave['total_days'] ?? ''); ?> days</td>
                            <td class="px-4 py-4">
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
                                $status = $leave['status'] ?? 'pending';
                                ?>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php echo $status_colors[$status]; ?>">
                                    <?php echo $status_text[$status]; ?>
                                </span>
                            </td>
                            <td class="px-4 py-4 text-sm font-medium">
                                <button onclick="viewLeaveDetails(<?php echo $leave['id']; ?>, '<?php echo $leave['source_table']; ?>')" class="text-seait-orange hover:text-orange-600 mr-2">
                                    <i class="fas fa-eye"></i> View
                                </button>
                                <?php 
                                // Show approve/reject buttons for:
                                // - Employee leave requests that are pending (direct HR approval)
                                // - Faculty leave requests that are approved by head (ready for HR approval)
                                $can_approve = false;
                                if ($leave['source_table'] === 'employee' && $leave['status'] === 'pending') {
                                    $can_approve = true;
                                } elseif ($leave['source_table'] === 'faculty' && $leave['status'] === 'approved_by_head') {
                                    $can_approve = true;
                                }
                                
                                if ($can_approve): 
                                ?>
                                <button onclick="approveLeave(<?php echo $leave['id']; ?>, '<?php echo $leave['source_table']; ?>')" class="text-green-600 hover:text-green-800 mr-2">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                                <button onclick="rejectLeave(<?php echo $leave['id']; ?>, '<?php echo $leave['source_table']; ?>')" class="text-red-600 hover:text-red-800">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile Card View -->
        <div class="lg:hidden">
            <?php if (empty($all_results)): ?>
            <div class="p-6 text-center text-gray-500">No leave requests found.</div>
            <?php else: ?>
                <?php foreach ($all_results as $leave): ?>
                <div class="p-4 border-b border-gray-200 hover:bg-gray-50">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 h-10 w-10">
                                <div class="h-10 w-10 rounded-full bg-seait-orange flex items-center justify-center text-white font-semibold">
                                    <?php echo substr($leave['first_name'] ?? '', 0, 1) . substr($leave['last_name'] ?? '', 0, 1); ?>
                                </div>
                            </div>
                            <div class="ml-3">
                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars(($leave['first_name'] ?? '') . ' ' . ($leave['last_name'] ?? '')); ?></div>
                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($leave['department'] ?? ''); ?></div>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php echo $leave['source_table'] === 'faculty' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'; ?>">
                                <?php echo $leave['source_table'] === 'faculty' ? 'Faculty' : 'Employee'; ?>
                            </span>
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
                            $status = $leave['status'] ?? 'pending';
                            ?>
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php echo $status_colors[$status]; ?>">
                                <?php echo $status_text[$status]; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 mb-3 text-sm">
                        <div>
                            <span class="text-gray-500">Leave Type:</span>
                            <div class="font-medium"><?php echo htmlspecialchars($leave['leave_type_name'] ?? ''); ?></div>
                        </div>
                        <div>
                            <span class="text-gray-500">Days:</span>
                            <div class="font-medium"><?php echo htmlspecialchars($leave['total_days'] ?? ''); ?> days</div>
                        </div>
                        <div class="col-span-2">
                            <span class="text-gray-500">Dates:</span>
                            <div class="font-medium"><?php echo date('M j, Y', strtotime($leave['start_date'])); ?> to <?php echo date('M j, Y', strtotime($leave['end_date'])); ?></div>
                        </div>
                    </div>
                    
                    <div class="flex space-x-2">
                        <button onclick="viewLeaveDetails(<?php echo $leave['id']; ?>, '<?php echo $leave['source_table']; ?>')" class="text-seait-orange hover:text-orange-600 text-sm">
                            <i class="fas fa-eye"></i> View
                        </button>
                        <?php 
                        // Show approve/reject buttons for:
                        // - Employee leave requests that are pending
                        // - Faculty leave requests that are approved by head (ready for HR approval)
                        $can_approve = false;
                        if ($leave['source_table'] === 'employee' && $leave['status'] === 'pending') {
                            $can_approve = true;
                        } elseif ($leave['source_table'] === 'faculty' && $leave['status'] === 'approved_by_head') {
                            $can_approve = true;
                        }
                        
                        if ($can_approve): 
                        ?>
                        <button onclick="approveLeave(<?php echo $leave['id']; ?>, '<?php echo $leave['source_table']; ?>')" class="text-green-600 hover:text-green-800 text-sm">
                            <i class="fas fa-check"></i> Approve
                        </button>
                        <button onclick="rejectLeave(<?php echo $leave['id']; ?>, '<?php echo $leave['source_table']; ?>')" class="text-red-600 hover:text-red-800 text-sm">
                            <i class="fas fa-times"></i> Reject
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Create Leave Request Modal -->
<div id="createLeaveModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Create Leave Request</h3>
            <form id="createLeaveForm">
                <div class="mb-4">
                    <label for="employee_id" class="block text-sm font-medium text-gray-700 mb-1">Employee</label>
                    <select name="employee_id" id="employee_id" required class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                        <option value="">Select Employee</option>
                        <!-- Will be populated via AJAX -->
                    </select>
                </div>
                
                <div class="mb-4">
                    <label for="leave_type_id" class="block text-sm font-medium text-gray-700 mb-1">Leave Type</label>
                    <select name="leave_type_id" id="leave_type_id" required class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                        <option value="">Select Leave Type</option>
                        <!-- Will be populated via AJAX -->
                    </select>
                </div>
                
                <div class="mb-4">
                    <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                    <input type="date" name="start_date" id="start_date" required class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                </div>
                
                <div class="mb-4">
                    <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                    <input type="date" name="end_date" id="end_date" required class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                </div>
                
                <div class="mb-4">
                    <label for="reason" class="block text-sm font-medium text-gray-700 mb-1">Reason</label>
                    <textarea name="reason" id="reason" rows="3" required class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent"></textarea>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeCreateLeaveModal()" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md">
                        Cancel
                    </button>
                    <button type="submit" class="bg-seait-orange hover:bg-orange-600 text-white px-4 py-2 rounded-md">
                        Create Request
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
function openCreateLeaveModal() {
    document.getElementById('createLeaveModal').classList.remove('hidden');
    loadEmployees();
    loadLeaveTypes();
}

function closeCreateLeaveModal() {
    document.getElementById('createLeaveModal').classList.add('hidden');
    document.getElementById('createLeaveForm').reset();
}

function loadEmployees() {
    fetch('get-employees.php')
        .then(response => response.json())
        .then(data => {
            const select = document.getElementById('employee_id');
            select.innerHTML = '<option value="">Select Employee</option>';
            
            data.forEach(employee => {
                const option = document.createElement('option');
                option.value = employee.id;
                option.textContent = `${employee.first_name} ${employee.last_name} (${employee.employee_id}) - ${employee.department}`;
                select.appendChild(option);
            });
        })
        .catch(error => console.error('Error loading employees:', error));
}

function loadLeaveTypes() {
    fetch('get-leave-types.php')
        .then(response => response.json())
        .then(data => {
            const select = document.getElementById('leave_type_id');
            select.innerHTML = '<option value="">Select Leave Type</option>';
            
            data.forEach(type => {
                const option = document.createElement('option');
                option.value = type.id;
                option.textContent = type.name;
                select.appendChild(option);
            });
        })
        .catch(error => console.error('Error loading leave types:', error));
}

document.getElementById('createLeaveForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('create-leave-request.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Leave request created successfully!');
            closeCreateLeaveModal();
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while creating the leave request.');
    });
});

function viewLeaveDetails(leaveId, sourceTable) {
    fetch(`get-leave-details.php?leave_id=${leaveId}&table=${sourceTable}`)
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

function approveLeave(leaveId, sourceTable) {
    if (confirm('Are you sure you want to approve this leave request?')) {
        fetch('approve-leave.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                leave_id: leaveId,
                table: sourceTable,
                action: 'approve'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Leave request approved successfully!');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while approving the leave request.');
        });
    }
}

function rejectLeave(leaveId, sourceTable) {
    const reason = prompt('Please provide a reason for rejection:');
    if (reason !== null) {
        fetch('approve-leave.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                leave_id: leaveId,
                table: sourceTable,
                action: 'reject',
                reason: reason
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Leave request rejected successfully!');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while rejecting the leave request.');
        });
    }
}
</script>

<?php include 'includes/footer.php'; ?>
