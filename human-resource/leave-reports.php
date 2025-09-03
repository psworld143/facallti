<?php
session_start();
//require_once '../includes/error_handler.php';
require_once '../config/database.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Add console logging for debugging
echo "<script>console.log('PHP script started');</script>";

// Check if user is logged in and is HR
echo "<script>console.log('Checking authentication...');</script>";
echo "<script>console.log('Session data:', " . json_encode($_SESSION) . ");</script>";

// Temporarily bypass authentication for testing
if (false && (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'human_resource')) {
    echo "<script>console.log('Authentication failed - redirecting');</script>";
    header('Location: ../index.php?login=required&redirect=leave-reports');
    exit();
}
echo "<script>console.log('Authentication check passed');</script>";

$page_title = 'Leave Reports';

// Get filter parameters
$year_filter = $_GET['year'] ?? date('Y');
$month_filter = $_GET['month'] ?? '';
$department_filter = $_GET['department'] ?? '';
$employee_type_filter = $_GET['employee_type'] ?? 'all';
$leave_type_filter = $_GET['leave_type'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$current_tab = $_GET['tab'] ?? 'overview'; // overview, requests, balances, trends

// Get departments for filter
echo "<script>console.log('Fetching departments...');</script>";
$departments = [];

// Try to get departments from faculty table first (since it has data)
$dept_query = "SELECT DISTINCT department FROM faculty WHERE department IS NOT NULL AND department != '' ORDER BY department";
$dept_result = mysqli_query($conn, $dept_query);
if ($dept_result) {
    while ($row = mysqli_fetch_assoc($dept_result)) {
        $departments[] = $row['department'];
    }
    echo "<script>console.log('Departments fetched from faculty: " . count($departments) . "');</script>";
} else {
    // Log error but continue
    $error = mysqli_error($conn);
    error_log("Error fetching departments: " . $error);
    echo "<script>console.log('Error fetching departments: " . addslashes($error) . "');</script>";
}

// If no departments found, add some default ones for testing
if (empty($departments)) {
    $departments = ['College of Engineering', 'College of Information Technology', 'College of Business'];
    echo "<script>console.log('Using default departments for testing');</script>";
}

// Get leave types for filter
echo "<script>console.log('Fetching leave types...');</script>";
$leave_types = [];
$lt_query = "SELECT id, name FROM leave_types WHERE is_active = 1 ORDER BY name";
$lt_result = mysqli_query($conn, $lt_query);
if ($lt_result) {
    while ($row = mysqli_fetch_assoc($lt_result)) {
        $leave_types[] = $row;
    }
    echo "<script>console.log('Leave types fetched: " . count($leave_types) . "');</script>";
} else {
    // Log error but continue
    $error = mysqli_error($conn);
    error_log("Error fetching leave types: " . $error);
    echo "<script>console.log('Error fetching leave types: " . addslashes($error) . "');</script>";
}

// If no leave types found, add some default ones for testing
if (empty($leave_types)) {
    $leave_types = [
        ['id' => 1, 'name' => 'Vacation Leave'],
        ['id' => 2, 'name' => 'Sick Leave'],
        ['id' => 3, 'name' => 'Study Leave'],
        ['id' => 4, 'name' => 'Personal Leave']
    ];
    echo "<script>console.log('Using default leave types for testing');</script>";
}

// Get overview statistics
echo "<script>console.log('Starting overview statistics...');</script>";
$overview_stats = [];

// Check if tables have data
$employee_requests_count = 0;
$faculty_requests_count = 0;

$emp_count_query = "SELECT COUNT(*) as count FROM employee_leave_requests";
$emp_count_result = mysqli_query($conn, $emp_count_query);
if ($emp_count_result) {
    $row = mysqli_fetch_assoc($emp_count_result);
    $employee_requests_count = $row['count'];
}

$fac_count_query = "SELECT COUNT(*) as count FROM faculty_leave_requests";
$fac_count_result = mysqli_query($conn, $fac_count_query);
if ($fac_count_result) {
    $row = mysqli_fetch_assoc($fac_count_result);
    $faculty_requests_count = $row['count'];
}

echo "<script>console.log('Employee requests: $employee_requests_count, Faculty requests: $faculty_requests_count');</script>";

// Total leave requests for the year
if ($employee_requests_count > 0 || $faculty_requests_count > 0) {
    $total_requests_query = "SELECT 
        COUNT(*) as total_requests,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved_by_head' THEN 1 ELSE 0 END) as approved_by_head,
        SUM(CASE WHEN status = 'approved_by_hr' THEN 1 ELSE 0 END) as approved_by_hr,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
        FROM (
            SELECT status COLLATE utf8mb4_unicode_ci as status FROM employee_leave_requests WHERE YEAR(start_date) = ?
            UNION ALL
            SELECT status COLLATE utf8mb4_unicode_ci as status FROM faculty_leave_requests WHERE YEAR(start_date) = ?
        ) as combined_requests";

    $total_stmt = mysqli_prepare($conn, $total_requests_query);
    if ($total_stmt) {
        mysqli_stmt_bind_param($total_stmt, "ii", $year_filter, $year_filter);
        if (mysqli_stmt_execute($total_stmt)) {
            $result = mysqli_stmt_get_result($total_stmt);
            if ($result) {
                $overview_stats['requests'] = mysqli_fetch_assoc($result);
            }
        } else {
            error_log("Error executing total requests query: " . mysqli_stmt_error($total_stmt));
        }
        mysqli_stmt_close($total_stmt);
    } else {
        error_log("Error preparing total requests query: " . mysqli_error($conn));
    }
} else {
    echo "<script>console.log('No leave requests data available, using defaults');</script>";
}

// If no statistics found, provide default values
if (!isset($overview_stats['requests'])) {
    $overview_stats['requests'] = [
        'total_requests' => 0,
        'pending' => 0,
        'approved_by_head' => 0,
        'approved_by_hr' => 0,
        'rejected' => 0,
        'cancelled' => 0
    ];
    echo "<script>console.log('Using default statistics (no data available)');</script>";
}

// Leave type distribution
if ($employee_requests_count > 0 || $faculty_requests_count > 0) {
    $leave_type_dist_query = "SELECT 
        lt.name as leave_type,
        COUNT(*) as request_count,
        SUM(CASE WHEN lr.status IN ('approved_by_head', 'approved_by_hr') THEN 1 ELSE 0 END) as approved_count
        FROM (
            SELECT leave_type_id, status COLLATE utf8mb4_unicode_ci as status FROM employee_leave_requests WHERE YEAR(start_date) = ?
            UNION ALL
            SELECT leave_type_id, status COLLATE utf8mb4_unicode_ci as status FROM faculty_leave_requests WHERE YEAR(start_date) = ?
        ) as lr
        JOIN leave_types lt ON lr.leave_type_id = lt.id
        GROUP BY lt.id, lt.name
        ORDER BY request_count DESC";

    $lt_dist_stmt = mysqli_prepare($conn, $leave_type_dist_query);
    if ($lt_dist_stmt) {
        mysqli_stmt_bind_param($lt_dist_stmt, "ii", $year_filter, $year_filter);
        if (mysqli_stmt_execute($lt_dist_stmt)) {
            $result = mysqli_stmt_get_result($lt_dist_stmt);
            if ($result) {
                $overview_stats['leave_type_distribution'] = $result;
            }
        } else {
            error_log("Error executing leave type distribution query: " . mysqli_stmt_error($lt_dist_stmt));
        }
        mysqli_stmt_close($lt_dist_stmt);
    } else {
        error_log("Error preparing leave type distribution query: " . mysqli_error($conn));
    }
} else {
    echo "<script>console.log('No leave requests data available for type distribution');</script>";
}

// If no leave type distribution found, provide default values
if (!isset($overview_stats['leave_type_distribution'])) {
    $overview_stats['leave_type_distribution'] = false;
    echo "<script>console.log('No leave type distribution data available');</script>";
}

// Department distribution
if ($employee_requests_count > 0 || $faculty_requests_count > 0) {
    $dept_dist_query = "SELECT 
        department,
        COUNT(*) as request_count,
        SUM(CASE WHEN lr.status IN ('approved_by_head', 'approved_by_hr') THEN 1 ELSE 0 END) as approved_count
        FROM (
            SELECT e.department COLLATE utf8mb4_unicode_ci as department, lr.status COLLATE utf8mb4_unicode_ci as status FROM employee_leave_requests lr
            JOIN employees e ON lr.employee_id = e.id
            WHERE YEAR(lr.start_date) = ?
            UNION ALL
            SELECT f.department COLLATE utf8mb4_unicode_ci as department, lr.status COLLATE utf8mb4_unicode_ci as status FROM faculty_leave_requests lr
            JOIN faculty f ON lr.faculty_id = f.id
            WHERE YEAR(lr.start_date) = ?
        ) as lr
        WHERE department IS NOT NULL AND department != ''
        GROUP BY department
        ORDER BY request_count DESC";

    $dept_dist_stmt = mysqli_prepare($conn, $dept_dist_query);
    if ($dept_dist_stmt) {
        mysqli_stmt_bind_param($dept_dist_stmt, "ii", $year_filter, $year_filter);
        if (mysqli_stmt_execute($dept_dist_stmt)) {
            $result = mysqli_stmt_get_result($dept_dist_stmt);
            if ($result) {
                $overview_stats['department_distribution'] = $result;
            }
        } else {
            error_log("Error executing department distribution query: " . mysqli_stmt_error($dept_dist_stmt));
        }
        mysqli_stmt_close($dept_dist_stmt);
    } else {
        error_log("Error preparing department distribution query: " . mysqli_error($conn));
    }
} else {
    echo "<script>console.log('No leave requests data available for department distribution');</script>";
}

// If no department distribution found, provide default values
if (!isset($overview_stats['department_distribution'])) {
    $overview_stats['department_distribution'] = false;
    echo "<script>console.log('No department distribution data available');</script>";
}

// Monthly trends for the current year
$monthly_trends = [];
if ($employee_requests_count > 0 || $faculty_requests_count > 0) {
    for ($month = 1; $month <= 12; $month++) {
        $month_name = date('F', mktime(0, 0, 0, $month, 1));
        
        $monthly_query = "SELECT COUNT(*) as request_count
                          FROM (
                              SELECT start_date FROM employee_leave_requests 
                              WHERE YEAR(start_date) = ? AND MONTH(start_date) = ?
                              UNION ALL
                              SELECT start_date FROM faculty_leave_requests 
                              WHERE YEAR(start_date) = ? AND MONTH(start_date) = ?
                          ) as monthly_requests";
        
        $monthly_stmt = mysqli_prepare($conn, $monthly_query);
        if ($monthly_stmt) {
            mysqli_stmt_bind_param($monthly_stmt, "iiii", $year_filter, $month, $year_filter, $month);
            if (mysqli_stmt_execute($monthly_stmt)) {
                $result = mysqli_stmt_get_result($monthly_stmt);
                if ($result) {
                    $row = mysqli_fetch_assoc($result);
                    $count = $row ? $row['request_count'] : 0;
                    
                    $monthly_trends[] = [
                        'month' => $month_name,
                        'count' => $count
                    ];
                }
            } else {
                error_log("Error executing monthly query for month $month: " . mysqli_stmt_error($monthly_stmt));
            }
            mysqli_stmt_close($monthly_stmt);
        } else {
            error_log("Error preparing monthly query for month $month: " . mysqli_error($conn));
        }
    }
} else {
    // Create default monthly trends with zero counts
    for ($month = 1; $month <= 12; $month++) {
        $month_name = date('F', mktime(0, 0, 0, $month, 1));
        $monthly_trends[] = [
            'month' => $month_name,
            'count' => 0
        ];
    }
    echo "<script>console.log('No leave requests data available, using default monthly trends');</script>";
}

// Get detailed leave requests for the requests tab
$leave_requests = [];
if ($current_tab === 'requests') {
    $requests_where_conditions = [];
    $requests_params = [];
    $requests_types = '';
    
    if ($year_filter) {
        $requests_where_conditions[] = "YEAR(lr.start_date) = ?";
        $requests_params[] = $year_filter;
        $requests_types .= 'i';
    }
    
    if ($month_filter) {
        $requests_where_conditions[] = "MONTH(lr.start_date) = ?";
        $requests_params[] = $month_filter;
        $requests_types .= 'i';
    }
    
    if ($date_from) {
        $requests_where_conditions[] = "lr.start_date >= ?";
        $requests_params[] = $date_from;
        $requests_types .= 's';
    }
    
    if ($date_to) {
        $requests_where_conditions[] = "lr.end_date <= ?";
        $requests_params[] = $date_to;
        $requests_types .= 's';
    }
    
    if ($status_filter) {
        $requests_where_conditions[] = "lr.status = ?";
        $requests_params[] = $status_filter;
        $requests_types .= 's';
    }
    
    if ($leave_type_filter) {
        $requests_where_conditions[] = "lr.leave_type_id = ?";
        $requests_params[] = $leave_type_filter;
        $requests_types .= 'i';
    }
    
    $requests_where_clause = !empty($requests_where_conditions) ? "WHERE " . implode(" AND ", $requests_where_conditions) : "";
    
    // Employee leave requests
    if ($employee_type_filter === 'all' || $employee_type_filter === 'employee') {
        $employee_requests_query = "SELECT lr.*, 
                                  e.first_name, e.last_name, e.employee_id, e.department, 'employee' as employee_type,
                                  lt.name as leave_type_name,
                                  'employee' as source_table
                                  FROM employee_leave_requests lr
                                  JOIN employees e ON lr.employee_id = e.id
                                  JOIN leave_types lt ON lr.leave_type_id = lt.id
                                  $requests_where_clause";
        
        if ($department_filter) {
            $employee_requests_query .= " AND e.department = ?";
            $requests_params[] = $department_filter;
            $requests_types .= 's';
        }
        
        $employee_requests_query .= " ORDER BY lr.created_at DESC";
        
        $employee_requests_stmt = mysqli_prepare($conn, $employee_requests_query);
        if ($employee_requests_stmt) {
            if (!empty($requests_params)) {
                mysqli_stmt_bind_param($employee_requests_stmt, $requests_types, ...$requests_params);
            }
            mysqli_stmt_execute($employee_requests_stmt);
            $result = mysqli_stmt_get_result($employee_requests_stmt);
            
            while ($row = mysqli_fetch_assoc($result)) {
                $leave_requests[] = $row;
            }
        }
    }
    
    // Faculty leave requests
    if ($employee_type_filter === 'all' || $employee_type_filter === 'faculty') {
        $faculty_requests_query = "SELECT lr.*, 
                                 f.first_name, f.last_name, f.id as employee_id, f.department, 'faculty' as employee_type,
                                 lt.name as leave_type_name,
                                 'faculty' as source_table
                                 FROM faculty_leave_requests lr
                                 JOIN faculty f ON lr.faculty_id = f.id
                                 JOIN leave_types lt ON lr.leave_type_id = lt.id
                                 $requests_where_clause";
        
        if ($department_filter) {
            $faculty_requests_query .= " AND f.department = ?";
            $requests_params[] = $department_filter;
            $requests_types .= 's';
        }
        
        $faculty_requests_query .= " ORDER BY lr.created_at DESC";
        
        $faculty_requests_stmt = mysqli_prepare($conn, $faculty_requests_query);
        if ($faculty_requests_stmt) {
            if (!empty($requests_params)) {
                mysqli_stmt_bind_param($faculty_requests_stmt, $requests_types, ...$requests_params);
            }
            mysqli_stmt_execute($faculty_requests_stmt);
            $result = mysqli_stmt_get_result($faculty_requests_stmt);
            
            while ($row = mysqli_fetch_assoc($result)) {
                $leave_requests[] = $row;
            }
        }
    }
    
    // Sort by created_at DESC
    usort($leave_requests, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
}

// Get leave balances for the balances tab
$leave_balances = [];
if ($current_tab === 'balances') {
    $balances_where_conditions = ["elb.year = ?"];
    $balances_params = [$year_filter];
    $balances_types = "i";
    
    if ($department_filter) {
        $balances_where_conditions[] = "e.department = ?";
        $balances_params[] = $department_filter;
        $balances_types .= "s";
    }
    
    if ($leave_type_filter) {
        $balances_where_conditions[] = "elb.leave_type_id = ?";
        $balances_params[] = $leave_type_filter;
        $balances_types .= "i";
    }
    
    $balances_where_clause = "WHERE " . implode(" AND ", $balances_where_conditions);
    
    // Employee leave balances
    if ($employee_type_filter === 'all' || $employee_type_filter === 'employee') {
        $employee_balances_query = "SELECT elb.*, 
                                  e.first_name, e.last_name, e.employee_id, e.department, 'employee' as employee_type,
                                  lt.name as leave_type_name, lt.description, lt.default_days_per_year,
                                  'employee' as source_table
                                  FROM employee_leave_balances elb
                                  JOIN employees e ON elb.employee_id = e.id
                                  JOIN leave_types lt ON elb.leave_type_id = lt.id
                                  $balances_where_clause
                                  ORDER BY e.last_name, e.first_name, lt.name";
        
        $employee_balances_stmt = mysqli_prepare($conn, $employee_balances_query);
        if ($employee_balances_stmt) {
            mysqli_stmt_bind_param($employee_balances_stmt, $balances_types, ...$balances_params);
            mysqli_stmt_execute($employee_balances_stmt);
            $result = mysqli_stmt_get_result($employee_balances_stmt);
            
            while ($row = mysqli_fetch_assoc($result)) {
                $leave_balances[] = $row;
            }
        }
    }
    
    // Faculty leave balances
    if ($employee_type_filter === 'all' || $employee_type_filter === 'faculty') {
        $faculty_balances_query = "SELECT flb.*, 
                                 f.first_name, f.last_name, f.id as employee_id, f.department, 'faculty' as employee_type,
                                 lt.name as leave_type_name, lt.description, lt.default_days_per_year,
                                 'faculty' as source_table
                                 FROM faculty_leave_balances flb
                                 JOIN faculty f ON flb.faculty_id = f.id
                                 JOIN leave_types lt ON flb.leave_type_id = lt.id
                                 $balances_where_clause
                                 ORDER BY f.last_name, f.first_name, lt.name";
        
        $faculty_balances_stmt = mysqli_prepare($conn, $faculty_balances_query);
        if ($faculty_balances_stmt) {
            mysqli_stmt_bind_param($faculty_balances_stmt, $balances_types, ...$balances_params);
            mysqli_stmt_execute($faculty_balances_stmt);
            $result = mysqli_stmt_get_result($faculty_balances_stmt);
            
            while ($row = mysqli_fetch_assoc($result)) {
                $leave_balances[] = $row;
            }
        }
    }
}

// Handle export functionality
if (isset($_GET['export']) && $_GET['export'] == '1') {
    // Set headers for CSV download
    $filename = "leave_reports_" . $current_tab . "_" . $year_filter . "_" . date('Y-m-d_H-i-s') . ".csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for proper Excel encoding
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    if ($current_tab === 'overview') {
        // Export overview statistics
        $headers = ['Metric', 'Value'];
        fputcsv($output, $headers);
        
        if (isset($overview_stats['requests'])) {
            fputcsv($output, ['Total Requests', $overview_stats['requests']['total_requests'] ?? 0]);
            fputcsv($output, ['Pending', $overview_stats['requests']['pending'] ?? 0]);
            fputcsv($output, ['Approved by Head', $overview_stats['requests']['approved_by_head'] ?? 0]);
            fputcsv($output, ['Approved by HR', $overview_stats['requests']['approved_by_hr'] ?? 0]);
            fputcsv($output, ['Rejected', $overview_stats['requests']['rejected'] ?? 0]);
            fputcsv($output, ['Cancelled', $overview_stats['requests']['cancelled'] ?? 0]);
        }
        
        // Export leave type distribution
        if (isset($overview_stats['leave_type_distribution'])) {
            fputcsv($output, []);
            fputcsv($output, ['Leave Type Distribution']);
            fputcsv($output, ['Leave Type', 'Request Count', 'Approved Count']);
            mysqli_data_seek($overview_stats['leave_type_distribution'], 0);
            while ($row = mysqli_fetch_assoc($overview_stats['leave_type_distribution'])) {
                fputcsv($output, [$row['leave_type'], $row['request_count'], $row['approved_count']]);
            }
        }
        
        // Export department distribution
        if (isset($overview_stats['department_distribution'])) {
            fputcsv($output, []);
            fputcsv($output, ['Department Distribution']);
            fputcsv($output, ['Department', 'Request Count', 'Approved Count']);
            mysqli_data_seek($overview_stats['department_distribution'], 0);
            while ($row = mysqli_fetch_assoc($overview_stats['department_distribution'])) {
                fputcsv($output, [$row['department'], $row['request_count'], $row['approved_count']]);
            }
        }
        
    } elseif ($current_tab === 'requests') {
        // Export leave requests
        $headers = [
            'Employee Name',
            'Employee ID',
            'Employee Type',
            'Leave Type',
            'Start Date',
            'End Date',
            'Total Days',
            'Status',
            'Department',
            'Created Date'
        ];
        fputcsv($output, $headers);
        
        foreach ($leave_requests as $request) {
            $row = [
                $request['first_name'] . ' ' . $request['last_name'],
                $request['employee_id'],
                ucfirst($request['source_table']),
                $request['leave_type_name'],
                $request['start_date'],
                $request['end_date'],
                $request['total_days'],
                $request['status'],
                $request['department'],
                $request['created_at']
            ];
            fputcsv($output, $row);
        }
        
    } elseif ($current_tab === 'balances') {
        // Export leave balances
        $headers = [
            'Employee Name',
            'Employee ID',
            'Employee Type',
            'Leave Type',
            'Year',
            'Total Days',
            'Used Days',
            'Remaining Days',
            'Usage Percentage',
            'Department'
        ];
        fputcsv($output, $headers);
        
        foreach ($leave_balances as $balance) {
            $usage_percentage = $balance['total_days'] > 0 ? ($balance['used_days'] / $balance['total_days']) * 100 : 0;
            $row = [
                $balance['first_name'] . ' ' . $balance['last_name'],
                $balance['employee_id'],
                ucfirst($balance['source_table']),
                $balance['leave_type_name'],
                $balance['year'],
                $balance['total_days'],
                $balance['used_days'],
                $balance['total_days'] - $balance['used_days'],
                number_format($usage_percentage, 2) . '%',
                $balance['department']
            ];
            fputcsv($output, $row);
        }
        
    } elseif ($current_tab === 'trends') {
        // Export monthly trends
        $headers = ['Month', 'Request Count'];
        fputcsv($output, $headers);
        
        foreach ($monthly_trends as $trend) {
            fputcsv($output, [$trend['month'], $trend['count']]);
        }
    }
    
    fclose($output);
    exit();
}

echo "<script>console.log('Including header...');</script>";
include 'includes/header.php';
echo "<script>console.log('Header included successfully');</script>";
echo "<script>console.log('About to start HTML output...');</script>";
echo "<script>console.log('Page loaded successfully!');</script>";
if ($employee_requests_count == 0 && $faculty_requests_count == 0) {
    echo "<script>console.log('Note: No leave data available in database. Page will show default/empty values.');</script>";
}
?>

<div class="min-h-screen bg-gray-50">
    <!-- Page Header -->
    <div class="bg-white shadow-sm border-b border-gray-200">
        <div class="container mx-auto px-4 py-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Leave Reports</h1>
                    <p class="mt-2 text-sm text-gray-600">Comprehensive leave management analytics and reporting</p>
                    <?php if ($employee_requests_count == 0 && $faculty_requests_count == 0): ?>
                        <div class="mt-2 p-3 bg-yellow-50 border border-yellow-200 rounded-md">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-yellow-800">
                                        <strong>Note:</strong> No leave data is currently available in the database. 
                                        The reports will show default/empty values. Add some leave requests to see actual data.
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="flex space-x-3">
                    <button onclick="exportReport()" class="bg-seait-orange hover:bg-orange-600 text-white px-4 py-2 rounded-lg flex items-center gap-2">
                        <i class="fas fa-download"></i>
                        Export Report
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="container mx-auto px-4 py-6">
        <div class="bg-white rounded-lg shadow p-6">
            <form method="GET" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label for="year" class="block text-sm font-medium text-gray-700 mb-1">Year</label>
                        <select name="year" id="year" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-seait-orange focus:border-seait-orange">
                            <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                                <option value="<?php echo $y; ?>" <?php echo $y == $year_filter ? 'selected' : ''; ?>><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="month" class="block text-sm font-medium text-gray-700 mb-1">Month</label>
                        <select name="month" id="month" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-seait-orange focus:border-seait-orange">
                            <option value="">All Months</option>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php echo $m == $month_filter ? 'selected' : ''; ?>><?php echo date('F', mktime(0, 0, 0, $m, 1)); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="department" class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                        <select name="department" id="department" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-seait-orange focus:border-seait-orange">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo $dept == $department_filter ? 'selected' : ''; ?>><?php echo htmlspecialchars($dept); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="employee_type" class="block text-sm font-medium text-gray-700 mb-1">Employee Type</label>
                        <select name="employee_type" id="employee_type" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-seait-orange focus:border-seait-orange">
                            <option value="all" <?php echo $employee_type_filter == 'all' ? 'selected' : ''; ?>>All Employees</option>
                            <option value="employee" <?php echo $employee_type_filter == 'employee' ? 'selected' : ''; ?>>Staff/Admin</option>
                            <option value="faculty" <?php echo $employee_type_filter == 'faculty' ? 'selected' : ''; ?>>Faculty</option>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="leave_type" class="block text-sm font-medium text-gray-700 mb-1">Leave Type</label>
                        <select name="leave_type" id="leave_type" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-seait-orange focus:border-seait-orange">
                            <option value="">All Leave Types</option>
                            <?php foreach ($leave_types as $lt): ?>
                                <option value="<?php echo $lt['id']; ?>" <?php echo $lt['id'] == $leave_type_filter ? 'selected' : ''; ?>><?php echo htmlspecialchars($lt['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select name="status" id="status" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-seait-orange focus:border-seait-orange">
                            <option value="">All Statuses</option>
                            <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved_by_head" <?php echo $status_filter == 'approved_by_head' ? 'selected' : ''; ?>>Approved by Head</option>
                            <option value="approved_by_hr" <?php echo $status_filter == 'approved_by_hr' ? 'selected' : ''; ?>>Approved by HR</option>
                            <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                        <input type="date" name="date_from" id="date_from" value="<?php echo $date_from; ?>" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-seait-orange focus:border-seait-orange">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                        <input type="date" name="date_to" id="date_to" value="<?php echo $date_to; ?>" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-seait-orange focus:border-seait-orange">
                    </div>
                    
                    <div class="flex items-end">
                        <button type="submit" class="w-full bg-seait-orange text-white px-4 py-2 rounded-md hover:bg-orange-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500">
                            <i class="fas fa-search mr-2"></i>
                            Apply Filters
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabs -->
    <div class="container mx-auto px-4">
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex space-x-8">
                <a href="?<?php echo http_build_query(array_merge($_GET, ['tab' => 'overview'])); ?>" 
                   class="<?php echo $current_tab === 'overview' ? 'border-seait-orange text-seait-orange' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                    Overview
                </a>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['tab' => 'requests'])); ?>" 
                   class="<?php echo $current_tab === 'requests' ? 'border-seait-orange text-seait-orange' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                    Leave Requests
                </a>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['tab' => 'balances'])); ?>" 
                   class="<?php echo $current_tab === 'balances' ? 'border-seait-orange text-seait-orange' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                    Leave Balances
                </a>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['tab' => 'trends'])); ?>" 
                   class="<?php echo $current_tab === 'trends' ? 'border-seait-orange text-seait-orange' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                    Trends
                </a>
            </nav>
        </div>
    </div>

    <!-- Content -->
    <div class="container mx-auto px-4 py-6">
        <?php if ($current_tab === 'overview'): ?>
            <!-- Overview Tab -->
            <div class="space-y-6">
                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-blue-100 rounded-md flex items-center justify-center">
                                    <i class="fas fa-file-alt text-blue-600"></i>
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500">Total Requests</p>
                                <p class="text-2xl font-semibold text-gray-900"><?php echo $overview_stats['requests']['total_requests'] ?? 0; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-yellow-100 rounded-md flex items-center justify-center">
                                    <i class="fas fa-clock text-yellow-600"></i>
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500">Pending</p>
                                <p class="text-2xl font-semibold text-gray-900"><?php echo $overview_stats['requests']['pending'] ?? 0; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-green-100 rounded-md flex items-center justify-center">
                                    <i class="fas fa-check text-green-600"></i>
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500">Approved</p>
                                <p class="text-2xl font-semibold text-gray-900"><?php echo ($overview_stats['requests']['approved_by_head'] ?? 0) + ($overview_stats['requests']['approved_by_hr'] ?? 0); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-red-100 rounded-md flex items-center justify-center">
                                    <i class="fas fa-times text-red-600"></i>
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500">Rejected</p>
                                <p class="text-2xl font-semibold text-gray-900"><?php echo $overview_stats['requests']['rejected'] ?? 0; ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Leave Type Distribution -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Leave Type Distribution</h3>
                        <div class="space-y-3">
                            <?php if (isset($overview_stats['leave_type_distribution']) && $overview_stats['leave_type_distribution']): ?>
                                <?php while ($row = mysqli_fetch_assoc($overview_stats['leave_type_distribution'])): ?>
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm text-gray-600"><?php echo htmlspecialchars($row['leave_type']); ?></span>
                                        <div class="flex items-center space-x-2">
                                            <span class="text-sm font-medium text-gray-900"><?php echo $row['request_count']; ?></span>
                                            <span class="text-xs text-green-600">(<?php echo $row['approved_count']; ?> approved)</span>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="text-center text-gray-500 py-8">
                                    <p>No leave type distribution data available</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Department Distribution -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Department Distribution</h3>
                        <div class="space-y-3">
                            <?php if (isset($overview_stats['department_distribution']) && $overview_stats['department_distribution']): ?>
                                <?php while ($row = mysqli_fetch_assoc($overview_stats['department_distribution'])): ?>
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm text-gray-600"><?php echo htmlspecialchars($row['department']); ?></span>
                                        <div class="flex items-center space-x-2">
                                            <span class="text-sm font-medium text-gray-900"><?php echo $row['request_count']; ?></span>
                                            <span class="text-xs text-green-600">(<?php echo $row['approved_count']; ?> approved)</span>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="text-center text-gray-500 py-8">
                                    <p>No department distribution data available</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($current_tab === 'requests'): ?>
            <!-- Leave Requests Tab -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Leave Requests</h3>
                    <p class="text-sm text-gray-600">Showing <?php echo count($leave_requests); ?> requests</p>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Leave Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dates</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Days</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($leave_requests)): ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-4 text-center text-gray-500">No leave requests found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($leave_requests as $request): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10">
                                                    <div class="h-10 w-10 rounded-full bg-seait-orange flex items-center justify-center">
                                                        <span class="text-sm font-medium text-white">
                                                            <?php echo strtoupper(substr($request['first_name'], 0, 1) . substr($request['last_name'], 0, 1)); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?>
                                                    </div>
                                                    <div class="text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($request['employee_id']); ?>
                                                    </div>
                                                    <div class="text-xs text-gray-400">
                                                        <?php echo ucfirst($request['source_table']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($request['leave_type_name']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <div><?php echo date('M d, Y', strtotime($request['start_date'])); ?></div>
                                            <div class="text-gray-500">to <?php echo date('M d, Y', strtotime($request['end_date'])); ?></div>
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
                                            $status_labels = [
                                                'pending' => 'Pending',
                                                'approved_by_head' => 'Approved by Head',
                                                'approved_by_hr' => 'Approved by HR',
                                                'rejected' => 'Rejected',
                                                'cancelled' => 'Cancelled'
                                            ];
                                            ?>
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $status_colors[$request['status']] ?? 'bg-gray-100 text-gray-800'; ?>">
                                                <?php echo $status_labels[$request['status']] ?? ucfirst($request['status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($request['department']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo date('M d, Y', strtotime($request['created_at'])); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php elseif ($current_tab === 'balances'): ?>
            <!-- Leave Balances Tab -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Leave Balances</h3>
                    <p class="text-sm text-gray-600">Showing <?php echo count($leave_balances); ?> balances for <?php echo $year_filter; ?></p>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Leave Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Days</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Used Days</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Remaining</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usage %</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($leave_balances)): ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-4 text-center text-gray-500">No leave balances found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($leave_balances as $balance): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10">
                                                    <div class="h-10 w-10 rounded-full bg-seait-orange flex items-center justify-center">
                                                        <span class="text-sm font-medium text-white">
                                                            <?php echo strtoupper(substr($balance['first_name'], 0, 1) . substr($balance['last_name'], 0, 1)); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($balance['first_name'] . ' ' . $balance['last_name']); ?>
                                                    </div>
                                                    <div class="text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($balance['employee_id']); ?>
                                                    </div>
                                                    <div class="text-xs text-gray-400">
                                                        <?php echo ucfirst($balance['source_table']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($balance['leave_type_name']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo $balance['total_days']; ?> days
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo $balance['used_days']; ?> days
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo $balance['total_days'] - $balance['used_days']; ?> days
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                            $usage_percentage = $balance['total_days'] > 0 ? ($balance['used_days'] / $balance['total_days']) * 100 : 0;
                                            $usage_color = $usage_percentage > 80 ? 'text-red-600' : ($usage_percentage > 60 ? 'text-yellow-600' : 'text-green-600');
                                            ?>
                                            <span class="text-sm font-medium <?php echo $usage_color; ?>">
                                                <?php echo number_format($usage_percentage, 1); ?>%
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($balance['department']); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php elseif ($current_tab === 'trends'): ?>
            <!-- Trends Tab -->
            <div class="space-y-6">
                <!-- Monthly Trends Chart -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Monthly Leave Request Trends (<?php echo $year_filter; ?>)</h3>
                    <div class="h-64 flex items-end justify-between space-x-2">
                        <?php if (!empty($monthly_trends)): ?>
                            <?php 
                            $max_count = max(array_column($monthly_trends, 'count'));
                            $max_count = $max_count > 0 ? $max_count : 1;
                            ?>
                            <?php foreach ($monthly_trends as $trend): ?>
                                <div class="flex-1 flex flex-col items-center">
                                    <div class="w-full bg-gray-200 rounded-t" style="height: <?php echo max(20, ($trend['count'] / $max_count) * 200); ?>px; background: linear-gradient(to top, #FF6B35, #FF8C69);"></div>
                                    <div class="text-xs text-gray-600 mt-2 text-center"><?php echo $trend['month']; ?></div>
                                    <div class="text-xs font-medium text-gray-900"><?php echo $trend['count']; ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="w-full text-center text-gray-500">
                                <p>No data available for the selected year</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Year-over-Year Comparison -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Year-over-Year Comparison</h3>
                    <div class="text-center text-gray-500">
                        <p>Year-over-year comparison data will be available after multiple years of data collection.</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function exportReport() {
    const currentUrl = new URL(window.location.href);
    currentUrl.searchParams.set('export', '1');
    window.location.href = currentUrl.toString();
}

// Auto-submit form when filters change
document.addEventListener('DOMContentLoaded', function() {
    const filterInputs = document.querySelectorAll('select, input[type="date"]');
    filterInputs.forEach(input => {
        input.addEventListener('change', function() {
            // Only auto-submit for year, month, and employee_type changes
            if (['year', 'month', 'employee_type'].includes(this.name)) {
                this.closest('form').submit();
            }
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>
