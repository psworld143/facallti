<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is faculty
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$current_year = date('Y');

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

// Get leave balances for current year
$query = "SELECT lb.*, lt.name as leave_type_name, lt.description, lt.default_days_per_year
          FROM leave_balances lb
          JOIN leave_types lt ON lb.leave_type_id = lt.id
          WHERE lb.employee_id = ? AND lb.year = ?
          ORDER BY lt.name";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'ii', $user_id, $current_year);
mysqli_stmt_execute($stmt);
$leave_balances = mysqli_stmt_get_result($stmt);

// Get leave usage statistics
$query = "SELECT 
            lt.name as leave_type_name,
            COUNT(*) as total_requests,
            SUM(CASE WHEN lr.status = 'approved_by_hr' THEN 1 ELSE 0 END) as approved_requests,
            SUM(CASE WHEN lr.status = 'rejected' THEN 1 ELSE 0 END) as rejected_requests,
            SUM(CASE WHEN lr.status = 'pending' OR lr.status = 'approved_by_head' THEN 1 ELSE 0 END) as pending_requests,
            SUM(CASE WHEN lr.status = 'approved_by_hr' THEN lr.total_days ELSE 0 END) as total_days_used
          FROM leave_requests lr
          JOIN leave_types lt ON lr.leave_type_id = lt.id
          WHERE lr.employee_id = ? AND YEAR(lr.start_date) = ?
          GROUP BY lt.id, lt.name
          ORDER BY lt.name";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'ii', $user_id, $current_year);
mysqli_stmt_execute($stmt);
$usage_stats = mysqli_stmt_get_result($stmt);

// Get recent leave requests
$query = "SELECT lr.*, lt.name as leave_type_name
          FROM leave_requests lr
          JOIN leave_types lt ON lr.leave_type_id = lt.id
          WHERE lr.employee_id = ?
          ORDER BY lr.created_at DESC
          LIMIT 5";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'i', $user_id);
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
    $total_remaining_days += $balance['remaining_days'];
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
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Leave Balance</h1>
                <p class="text-gray-600">View your leave balances and usage statistics for <?php echo $current_year; ?></p>
            </div>

            <!-- Overall Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow-md p-6 animate-fadeInUp">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                            <i class="fas fa-calendar-plus text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Leave Days</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $total_leave_days; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6 animate-fadeInUp" style="animation-delay: 0.1s;">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 text-green-600">
                            <i class="fas fa-calendar-check text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Used Days</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $total_used_days; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6 animate-fadeInUp" style="animation-delay: 0.2s;">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-orange-100 text-orange-600">
                            <i class="fas fa-calendar-minus text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Remaining Days</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $total_remaining_days; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Leave Balance Details -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Leave Balances Table -->
                <div class="bg-white rounded-lg shadow-md animate-fadeInUp" style="animation-delay: 0.3s;">
                    <div class="p-6 border-b border-gray-200">
                        <h2 class="text-xl font-semibold text-gray-900">Leave Balances</h2>
                        <p class="text-sm text-gray-600 mt-1">Your current leave balances for <?php echo $current_year; ?></p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Leave Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Used</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Remaining</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Progress</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (count($balances_array) > 0): ?>
                                    <?php foreach ($balances_array as $balance): ?>
                                        <?php 
                                        $percentage = $balance['total_days'] > 0 ? ($balance['used_days'] / $balance['total_days']) * 100 : 0;
                                        $progress_color = $percentage > 80 ? 'bg-red-500' : ($percentage > 60 ? 'bg-yellow-500' : 'bg-green-500');
                                        ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($balance['leave_type_name']); ?></div>
                                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($balance['description']); ?></div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo $balance['total_days']; ?> days
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php echo $balance['used_days']; ?> days
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <span class="font-medium <?php echo $balance['remaining_days'] < 5 ? 'text-red-600' : 'text-green-600'; ?>">
                                                    <?php echo $balance['remaining_days']; ?> days
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="w-full bg-gray-200 rounded-full h-2">
                                                    <div class="<?php echo $progress_color; ?> h-2 rounded-full" style="width: <?php echo min(100, $percentage); ?>%"></div>
                                                </div>
                                                <div class="text-xs text-gray-500 mt-1"><?php echo number_format($percentage, 1); ?>% used</div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                            No leave balances found for <?php echo $current_year; ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Usage Chart -->
                <div class="bg-white rounded-lg shadow-md animate-fadeInUp" style="animation-delay: 0.4s;">
                    <div class="p-6 border-b border-gray-200">
                        <h2 class="text-xl font-semibold text-gray-900">Leave Usage Chart</h2>
                        <p class="text-sm text-gray-600 mt-1">Visual representation of your leave usage</p>
                    </div>
                    <div class="p-6">
                        <canvas id="leaveChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>

            <!-- Usage Statistics -->
            <div class="mt-8 bg-white rounded-lg shadow-md animate-fadeInUp" style="animation-delay: 0.5s;">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-900">Usage Statistics</h2>
                    <p class="text-sm text-gray-600 mt-1">Detailed breakdown of your leave requests</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
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
                            <?php 
                            mysqli_data_seek($usage_stats, 0);
                            if (mysqli_num_rows($usage_stats) > 0): 
                            ?>
                                <?php while ($stat = mysqli_fetch_assoc($usage_stats)): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($stat['leave_type_name']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo $stat['total_requests']; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                <?php echo $stat['approved_requests']; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                <?php echo $stat['pending_requests']; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                <?php echo $stat['rejected_requests']; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo $stat['total_days_used']; ?> days
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                        No usage statistics found for <?php echo $current_year; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Leave Requests -->
            <div class="mt-8 bg-white rounded-lg shadow-md animate-fadeInUp" style="animation-delay: 0.6s;">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-xl font-semibold text-gray-900">Recent Leave Requests</h2>
                            <p class="text-sm text-gray-600 mt-1">Your latest leave requests</p>
                        </div>
                        <a href="leave-requests.php" class="bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition-colors duration-200">
                            View All Requests
                        </a>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Leave Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Range</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Days</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Submitted</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (mysqli_num_rows($recent_requests) > 0): ?>
                                <?php while ($request = mysqli_fetch_assoc($recent_requests)): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($request['leave_type_name']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo date('M d', strtotime($request['start_date'])); ?> - 
                                            <?php echo date('M d, Y', strtotime($request['end_date'])); ?>
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
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo date('M d, Y', strtotime($request['created_at'])); ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                        No recent leave requests found
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Chart.js configuration
        const ctx = document.getElementById('leaveChart').getContext('2d');
        
        const chartData = {
            labels: <?php echo json_encode(array_column($balances_array, 'leave_type_name')); ?>,
            datasets: [{
                label: 'Used Days',
                data: <?php echo json_encode(array_column($balances_array, 'used_days')); ?>,
                backgroundColor: 'rgba(255, 107, 53, 0.8)',
                borderColor: 'rgba(255, 107, 53, 1)',
                borderWidth: 1
            }, {
                label: 'Remaining Days',
                data: <?php echo json_encode(array_column($balances_array, 'remaining_days')); ?>,
                backgroundColor: 'rgba(34, 197, 94, 0.8)',
                borderColor: 'rgba(34, 197, 94, 1)',
                borderWidth: 1
            }]
        };

        new Chart(ctx, {
            type: 'bar',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        stacked: true,
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                    }
                }
            }
        });
    </script>
</body>
</html>
