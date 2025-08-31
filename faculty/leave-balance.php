<?php
session_start();
require_once '../includes/error_handler.php';
require_once '../config/database.php';

// Check if user is logged in and is faculty
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../index.php?login=required&redirect=faculty-leave-balance');
    exit;
}

$faculty_id = $_SESSION['user_id'];
$current_year = date('Y');

// Get faculty details
$query = "SELECT * FROM faculty WHERE id = ? AND is_active = 1";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'i', $faculty_id);
mysqli_stmt_execute($stmt);
$faculty = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$faculty) {
    header('Location: ../index.php?login=required&redirect=faculty-leave-balance');
    exit;
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Balance - Faculty Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
    </style>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-4">
                    <div class="flex items-center">
                        <h1 class="text-2xl font-bold text-gray-900">Leave Balance</h1>
                    </div>
                    <div class="flex items-center space-x-4">
                        <a href="leave-requests.php" class="bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition-colors duration-200">
                            <i class="fas fa-calendar-plus mr-2"></i>My Leave Requests
                        </a>
                        <a href="dashboard.php" class="text-gray-600 hover:text-gray-900">
                            <i class="fas fa-home mr-2"></i>Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Faculty Info Card -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-8 animate-fadeInUp">
                <div class="flex items-center">
                    <div class="h-16 w-16 rounded-full bg-seait-orange flex items-center justify-center">
                        <span class="text-white font-bold text-xl">
                            <?php echo strtoupper(substr($faculty['first_name'], 0, 1) . substr($faculty['last_name'], 0, 1)); ?>
                        </span>
                    </div>
                    <div class="ml-6">
                        <h2 class="text-xl font-semibold text-gray-900">
                            <?php echo htmlspecialchars($faculty['first_name'] . ' ' . $faculty['last_name']); ?>
                        </h2>
                        <p class="text-gray-600"><?php echo htmlspecialchars($faculty['department']); ?> Department</p>
                        <p class="text-sm text-gray-500">Faculty ID: <?php echo htmlspecialchars($faculty['id']); ?></p>
                    </div>
                </div>
            </div>

            <!-- Overall Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 animate-fadeInUp">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100">
                            <i class="fas fa-calendar text-blue-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Leave Days</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $total_leave_days; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 animate-fadeInUp">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100">
                            <i class="fas fa-check-circle text-green-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Used Days</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $total_used_days; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 animate-fadeInUp">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-orange-100">
                            <i class="fas fa-clock text-orange-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Remaining Days</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $total_remaining_days; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Leave Balance Details -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Leave Balance by Type -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 animate-fadeInUp">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Leave Balance by Type (<?php echo $current_year; ?>)</h3>
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
                            <i class="fas fa-calendar-times text-gray-400 text-4xl mb-4"></i>
                            <p class="text-gray-500">No leave balance information available for <?php echo $current_year; ?></p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Leave Requests -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 animate-fadeInUp">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Recent Leave Requests</h3>
                    <?php if (mysqli_num_rows($recent_requests) > 0): ?>
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
                        <div class="mt-4 text-center">
                            <a href="leave-requests.php" class="text-seait-orange hover:text-orange-600 text-sm font-medium">
                                View All Requests <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="fas fa-calendar-plus text-gray-400 text-4xl mb-4"></i>
                            <p class="text-gray-500">No leave requests found</p>
                            <a href="leave-requests.php" class="inline-block mt-4 bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition-colors duration-200">
                                Submit Leave Request
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Usage Statistics -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mt-8 animate-fadeInUp">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Leave Usage Statistics (<?php echo $current_year; ?>)</h3>
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
                                    <tr>
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
                        <i class="fas fa-chart-bar text-gray-400 text-4xl mb-4"></i>
                        <p class="text-gray-500">No leave usage statistics available for <?php echo $current_year; ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        // Add any additional JavaScript functionality here
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize any charts or interactive elements
            console.log('Leave Balance page loaded successfully');
        });
    </script>
</body>
</html>
