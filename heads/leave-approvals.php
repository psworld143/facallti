<?php
session_start();
require_once '../includes/error_handler.php';
require_once '../config/database.php';

// Check if user is logged in and is a department head
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'head') {
    header('Location: ../index.php?login=required&redirect=heads-leave-approvals');
    exit;
}

$head_id = $_SESSION['user_id'];

// Get department head details
$query = "SELECT h.*, u.first_name, u.last_name 
          FROM heads h 
          JOIN users u ON h.user_id = u.id 
          WHERE h.user_id = ? AND h.status = 'active'";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'i', $head_id);
mysqli_stmt_execute($stmt);
$department_head = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$department_head) {
    header('Location: ../index.php?login=required&redirect=heads-leave-approvals');
    exit;
}

$head_department = $department_head['department'];

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$year_filter = $_GET['year'] ?? date('Y');
$leave_type_filter = $_GET['leave_type'] ?? '';
$search = $_GET['search'] ?? '';

// Build query with filters for faculty_leave_requests table
$where_conditions = ["flr.department_head_id = ?"];
$params = [$head_id];
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
    $where_conditions[] = "(f.first_name LIKE ? OR f.last_name LIKE ? OR lt.name LIKE ? OR flr.reason LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'ssss';
}

$where_clause = implode(' AND ', $where_conditions);

// Get leave requests that this head has approved/rejected from faculty_leave_requests table
$query = "SELECT flr.*, lt.name as leave_type_name, lt.description,
          f.first_name, f.last_name, f.email, f.department,
          hr.first_name as hr_first_name, hr.last_name as hr_last_name
          FROM faculty_leave_requests flr
          JOIN leave_types lt ON flr.leave_type_id = lt.id
          JOIN faculty f ON flr.faculty_id = f.id
          LEFT JOIN faculty hr ON flr.hr_approver_id = hr.id
          WHERE $where_clause
          ORDER BY flr.department_head_approved_at DESC";

$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
}
mysqli_stmt_execute($stmt);
$leave_requests = mysqli_stmt_get_result($stmt);

// Get available years for filter from faculty_leave_requests table
$query = "SELECT DISTINCT YEAR(start_date) as year FROM faculty_leave_requests WHERE department_head_id = ? ORDER BY year DESC";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'i', $head_id);
mysqli_stmt_execute($stmt);
$available_years = mysqli_stmt_get_result($stmt);

// Get available leave types for filter from faculty_leave_requests table
$query = "SELECT DISTINCT lt.id, lt.name FROM leave_types lt
          JOIN faculty_leave_requests flr ON lt.id = flr.leave_type_id
          WHERE flr.department_head_id = ?
          ORDER BY lt.name";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'i', $head_id);
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
    <link rel="icon" type="image/png" href="../../assets/images/seait-logo.png">
    <link rel="shortcut icon" type="image/png" href="../../assets/images/seait-logo.png">
    <link rel="apple-touch-icon" type="image/png" href="../../assets/images/seait-logo.png">
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
    
    <div class="space-y-6">
            <!-- Page Header -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-seait-dark">My Approvals</h1>
                        <p class="text-gray-600 mt-1">View all faculty leave requests you have approved or rejected</p>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                    <div class="flex items-center">
                        <div class="p-2 bg-blue-100 rounded-lg">
                            <i class="fas fa-list text-blue-600"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-gray-600">Total Decisions</p>
                            <p class="text-lg font-semibold text-gray-900"><?php echo $total_approvals; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                    <div class="flex items-center">
                        <div class="p-2 bg-green-100 rounded-lg">
                            <i class="fas fa-check-circle text-green-600"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-gray-600">Approved</p>
                            <p class="text-lg font-semibold text-gray-900"><?php echo $approved_count; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                    <div class="flex items-center">
                        <div class="p-2 bg-yellow-100 rounded-lg">
                            <i class="fas fa-clock text-yellow-600"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-gray-600">Pending HR</p>
                            <p class="text-lg font-semibold text-gray-900"><?php echo $pending_hr_count; ?></p>
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
                            <p class="text-lg font-semibold text-gray-900"><?php echo $rejected_count; ?></p>
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
                            <option value="approved_by_head" <?php echo $status_filter === 'approved_by_head' ? 'selected' : ''; ?>>Pending HR</option>
                            <option value="approved_by_hr" <?php echo $status_filter === 'approved_by_hr' ? 'selected' : ''; ?>>Approved by HR</option>
                            <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Year</label>
                        <select name="year" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                            <?php while ($year = mysqli_fetch_assoc($available_years)): ?>
                                <option value="<?php echo $year['year']; ?>" <?php echo $year_filter == $year['year'] ? 'selected' : ''; ?>>
                                    <?php echo $year['year']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Leave Type</label>
                        <select name="leave_type" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                            <option value="">All Types</option>
                            <?php while ($type = mysqli_fetch_assoc($available_leave_types)): ?>
                                <option value="<?php echo $type['id']; ?>" <?php echo $leave_type_filter == $type['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search ?? ''); ?>" 
                               placeholder="Search faculty or reason..." 
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                    </div>

                    <div class="flex items-end">
                        <button type="submit" class="bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition-colors duration-200 mr-2">
                            <i class="fas fa-search mr-1"></i> Filter
                        </button>
                        <a href="leave-approvals.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-times mr-1"></i> Clear
                        </a>
                    </div>
                </form>
            </div>

            <!-- Leave Requests Table -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">My Approval Decisions</h2>
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
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Faculty</th>
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
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10">
                                                    <div class="h-10 w-10 rounded-full bg-seait-orange flex items-center justify-center">
                                                        <span class="text-white font-medium text-sm">
                                                            <?php echo strtoupper(substr($request['first_name'], 0, 1) . substr($request['last_name'], 0, 1)); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?>
                                                    </div>
                                                    <div class="text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($request['email']); ?>
                                                    </div>
                                                </div>
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
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $decision_colors[$decision] ?? 'bg-gray-100 text-gray-800'; ?>">
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
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $hr_status_colors[$hr_status] ?? 'bg-gray-100 text-gray-800'; ?>">
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
                                                <i class="fas fa-eye"></i>
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

        // Close modals when clicking outside
        window.onclick = function(event) {
            const leaveDetailsModal = document.getElementById('leaveDetailsModal');
            
            if (event.target === leaveDetailsModal) {
                closeLeaveDetailsModal();
            }
        }
    </script>
</body>
</html>
