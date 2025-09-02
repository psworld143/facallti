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
$page_title = 'Leave Balance';

$faculty_id = $_SESSION['user_id'];
$current_year = date('Y');

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

// Get leave balances for current year from faculty_leave_balances table
$query = "SELECT flb.*, lt.name as leave_type_name, lt.description, lt.default_days_per_year
          FROM faculty_leave_balances flb
          JOIN leave_types lt ON flb.leave_type_id = lt.id
          WHERE flb.faculty_id = ? AND flb.year = ?
          ORDER BY lt.name";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'ii', $faculty_id, $current_year);
mysqli_stmt_execute($stmt);
$leave_balances = mysqli_stmt_get_result($stmt);

// Get leave usage statistics from faculty_leave_requests table
$query = "SELECT 
            lt.name as leave_type_name,
            COUNT(*) as total_requests,
            SUM(CASE WHEN flr.status = 'approved_by_hr' THEN 1 ELSE 0 END) as approved_requests,
            SUM(CASE WHEN flr.status = 'rejected' THEN 1 ELSE 0 END) as rejected_requests,
            SUM(CASE WHEN flr.status = 'pending' OR flr.status = 'approved_by_head' THEN 1 ELSE 0 END) as pending_requests,
            SUM(CASE WHEN flr.status = 'approved_by_hr' THEN flr.total_days ELSE 0 END) as total_days_used
          FROM faculty_leave_requests flr
          JOIN leave_types lt ON flr.leave_type_id = lt.id
          WHERE flr.faculty_id = ? AND YEAR(flr.start_date) = ?
          GROUP BY lt.id, lt.name
          ORDER BY lt.name";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'ii', $faculty_id, $current_year);
mysqli_stmt_execute($stmt);
$usage_stats = mysqli_stmt_get_result($stmt);

// Get recent leave requests from faculty_leave_requests table
$query = "SELECT flr.*, lt.name as leave_type_name
          FROM faculty_leave_requests flr
          JOIN leave_types lt ON flr.leave_type_id = lt.id
          WHERE flr.faculty_id = ?
          ORDER BY flr.created_at DESC
          LIMIT 5";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'i', $faculty_id);
mysqli_stmt_execute($stmt);
$recent_requests = mysqli_stmt_get_result($stmt);

// Calculate overall statistics
$total_leave_days = 0;
$total_used_days = 0;
$total_remaining_days = 0;

$balances_array = [];
while ($balance = mysqli_fetch_assoc($leave_balances)) {
    $balances_array[] = $balance;
    $total_leave_days += $balance['total_days'];
    $total_used_days += $balance['used_days'];
    $total_remaining_days += ($balance['total_days'] - $balance['used_days']);
}

// Count leave types
$leave_types_count = count($balances_array);

// Count recent requests
$recent_requests_count = mysqli_num_rows($recent_requests);

// Include the shared header
$sidebar_context = 'main';
include 'includes/unified-header.php';
?>

<div class="mb-6 sm:mb-8">
    <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Leave Balance</h1>
    <p class="text-sm sm:text-base text-gray-600">View your leave balance and usage statistics for <?php echo $current_year; ?></p>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-6 sm:mb-8">
    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-4 sm:p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-calendar text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Total Leave Days</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($total_leave_days); ?></dd>
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
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Used Days</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($total_used_days); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-4 sm:p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-orange-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-clock text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Remaining Days</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($total_remaining_days); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-4 sm:p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-purple-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-list text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Leave Types</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($leave_types_count); ?></dd>
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
                    <h3 class="text-lg font-semibold text-gray-900">Submit Leave Request</h3>
                    <p class="text-sm text-gray-600">Create a new leave request</p>
                </div>
            </div>
        </a>

        <a href="leave-requests.php" class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                    <i class="fas fa-history text-blue-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">View All Requests</h3>
                    <p class="text-sm text-gray-600">Check your leave request history</p>
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

<!-- Leave Balance Details -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 sm:gap-8 mb-6 sm:mb-8">
    <!-- Leave Balance by Type -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-medium text-gray-900">Leave Balance by Type (<?php echo $current_year; ?>)</h2>
        </div>
        <div class="p-6">
            <?php if (!empty($balances_array)): ?>
                <div class="space-y-4">
                    <?php foreach ($balances_array as $balance): ?>
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex justify-between items-center mb-2">
                                <h4 class="font-medium text-gray-900"><?php echo htmlspecialchars($balance['leave_type_name']); ?></h4>
                                <span class="text-sm text-gray-500"><?php echo ($balance['total_days'] - $balance['used_days']); ?> days remaining</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <?php 
                                $percentage = $balance['total_days'] > 0 ? ($balance['used_days'] / $balance['total_days']) * 100 : 0;
                                $color_class = $percentage > 80 ? 'bg-red-500' : ($percentage > 60 ? 'bg-yellow-500' : 'bg-green-500');
                                ?>
                                <div class="<?php echo $color_class; ?> h-2 rounded-full" style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                            <div class="flex justify-between text-sm text-gray-600 mt-2">
                                <span>Used: <?php echo $balance['used_days']; ?> days</span>
                                <span>Total: <?php echo $balance['total_days']; ?> days</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-8">
                    <i class="fas fa-calendar-times text-gray-300 text-4xl mb-4"></i>
                    <p class="text-gray-500">No leave balance information available for <?php echo $current_year; ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Leave Requests -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-medium text-gray-900">Recent Leave Requests</h2>
                <a href="leave-requests.php" class="text-seait-orange hover:text-orange-600 text-sm font-medium">
                    View all <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
        </div>
        <div class="p-6">
            <?php if ($recent_requests_count > 0): ?>
                <div class="space-y-3">
                    <?php while ($request = mysqli_fetch_assoc($recent_requests)): ?>
                        <div class="border border-gray-200 rounded-lg p-3">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="font-medium text-gray-900"><?php echo htmlspecialchars($request['leave_type_name']); ?></p>
                                    <p class="text-sm text-gray-600">
                                        <?php echo date('M d, Y', strtotime($request['start_date'])); ?> - 
                                        <?php echo date('M d, Y', strtotime($request['end_date'])); ?>
                                    </p>
                                    <p class="text-sm text-gray-500"><?php echo $request['total_days']; ?> days</p>
                                </div>
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                    <?php 
                                    switch($request['status']) {
                                        case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                        case 'approved_by_head': echo 'bg-blue-100 text-blue-800'; break;
                                        case 'approved_by_hr': echo 'bg-green-100 text-green-800'; break;
                                        case 'rejected': echo 'bg-red-100 text-red-800'; break;
                                        case 'cancelled': echo 'bg-gray-100 text-gray-800'; break;
                                        default: echo 'bg-gray-100 text-gray-800';
                                    }
                                    ?>">
                                    <?php 
                                    switch($request['status']) {
                                        case 'pending': echo 'Pending'; break;
                                        case 'approved_by_head': echo 'Approved by Head'; break;
                                        case 'approved_by_hr': echo 'Approved by HR'; break;
                                        case 'rejected': echo 'Rejected'; break;
                                        case 'cancelled': echo 'Cancelled'; break;
                                        default: echo ucfirst($request['status']);
                                    }
                                    ?>
                                </span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-8">
                    <i class="fas fa-calendar-plus text-gray-300 text-4xl mb-4"></i>
                    <p class="text-gray-500">No leave requests found</p>
                    <a href="leave-requests.php" class="inline-block mt-4 bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition">
                        Submit Leave Request
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Usage Statistics -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200">
        <h2 class="text-lg font-medium text-gray-900">Leave Usage Statistics (<?php echo $current_year; ?>)</h2>
    </div>
    <div class="p-6">
        <?php if (mysqli_num_rows($usage_stats) > 0): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Leave Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Requests</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Approved</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pending</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rejected</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Days Used</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php while ($stat = mysqli_fetch_assoc($usage_stats)): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($stat['leave_type_name']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo $stat['total_requests']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                        <?php echo $stat['approved_requests']; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                        <?php echo $stat['pending_requests']; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                        <?php echo $stat['rejected_requests']; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo $stat['total_days_used']; ?> days
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-8">
                <i class="fas fa-chart-bar text-gray-300 text-4xl mb-4"></i>
                <p class="text-gray-500">No leave usage statistics available for <?php echo $current_year; ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Include the unified footer
include 'includes/footer.php';
?>
