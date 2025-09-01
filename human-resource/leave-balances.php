<?php
session_start();
// Don't include error handler to avoid redirects
require_once '../config/database.php';

// Check if user is logged in and is HR
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'human_resource') {
    header('Location: ../index.php?login=required&redirect=leave-balances');
    exit();
}

$page_title = 'Leave Balances';

// Get filter parameters
$year_filter = $_GET['year'] ?? date('Y');
$department_filter = $_GET['department'] ?? '';
$employee_type_filter = $_GET['employee_type'] ?? 'all';
$search = $_GET['search'] ?? '';
$current_tab = $_GET['tab'] ?? 'employees'; // Default to employees tab

// Get current year for default
$current_year = date('Y');

// Initialize variables
$all_balances = [];
$departments = [];
$total_employees = 0;
$total_faculty = 0;
$total_leave_days = 0;
$total_used_days = 0;
$total_remaining_days = 0;
$leave_type_stats = [];

// Get unique departments for filter
try {
    $departments_query = "SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND department != '' 
                         UNION 
                         SELECT DISTINCT department FROM faculty WHERE department IS NOT NULL AND department != '' 
                         ORDER BY department";
    $departments_result = mysqli_query($conn, $departments_query);
    if ($departments_result) {
        while ($row = mysqli_fetch_assoc($departments_result)) {
            $departments[] = $row['department'];
        }
    }
} catch (Exception $e) {
    // Silently handle error
}

// Get leave balances for employees
$employee_balances = [];
if (($employee_type_filter === 'all' || $employee_type_filter === 'employee') && ($current_tab === 'employees' || $current_tab === 'all')) {
    try {
        $employee_where_conditions = ["elb.year = ?"];
        $employee_params = [$year_filter];
        $employee_types = "i";
        
        if ($department_filter) {
            $employee_where_conditions[] = "e.department = ?";
            $employee_params[] = $department_filter;
            $employee_types .= "s";
        }
        
        if ($search) {
            $employee_where_conditions[] = "(e.first_name LIKE ? OR e.last_name LIKE ? OR e.employee_id LIKE ?)";
            $search_param = "%$search%";
            $employee_params[] = $search_param;
            $employee_params[] = $search_param;
            $employee_params[] = $search_param;
            $employee_types .= "sss";
        }
        
        $employee_where_clause = "WHERE " . implode(" AND ", $employee_where_conditions);
        
        $employee_query = "SELECT elb.*, 
                          e.first_name, e.last_name, e.employee_id, e.department, e.employee_type,
                          lt.name as leave_type_name, lt.description, lt.default_days_per_year,
                          'employee' as source_table
                          FROM employee_leave_balances elb
                          JOIN employees e ON elb.employee_id = e.id
                          JOIN leave_types lt ON elb.leave_type_id = lt.id
                          $employee_where_clause
                          ORDER BY e.last_name, e.first_name, lt.name";
        
        $employee_stmt = mysqli_prepare($conn, $employee_query);
        if ($employee_stmt) {
            mysqli_stmt_bind_param($employee_stmt, $employee_types, ...$employee_params);
            mysqli_stmt_execute($employee_stmt);
            $employee_result = mysqli_stmt_get_result($employee_stmt);
            
            while ($row = mysqli_fetch_assoc($employee_result)) {
                $employee_balances[] = $row;
            }
        }
    } catch (Exception $e) {
        // Silently handle error
    }
}

// Get leave balances for faculty
$faculty_balances = [];
if (($employee_type_filter === 'all' || $employee_type_filter === 'faculty') && ($current_tab === 'faculty' || $current_tab === 'all')) {
    try {
        $faculty_where_conditions = ["flb.year = ?"];
        $faculty_params = [$year_filter];
        $faculty_types = "i";
        
        if ($department_filter) {
            $faculty_where_conditions[] = "f.department = ?";
            $faculty_params[] = $department_filter;
            $faculty_types .= "s";
        }
        
        if ($search) {
            $faculty_where_conditions[] = "(f.first_name LIKE ? OR f.last_name LIKE ? OR f.id LIKE ?)";
            $search_param = "%$search%";
            $faculty_params[] = $search_param;
            $faculty_params[] = $search_param;
            $faculty_params[] = $search_param;
            $faculty_types .= "sss";
        }
        
        $faculty_where_clause = "WHERE " . implode(" AND ", $faculty_where_conditions);
        
        $faculty_query = "SELECT flb.*, 
                         f.first_name, f.last_name, f.id as employee_id, f.department, 'faculty' as employee_type,
                         lt.name as leave_type_name, lt.description, lt.default_days_per_year,
                         'faculty' as source_table
                         FROM faculty_leave_balances flb
                         JOIN faculty f ON flb.faculty_id = f.id
                         JOIN leave_types lt ON flb.leave_type_id = lt.id
                         $faculty_where_clause
                         ORDER BY f.last_name, f.first_name, lt.name";
        
        $faculty_stmt = mysqli_prepare($conn, $faculty_query);
        if ($faculty_stmt) {
            mysqli_stmt_bind_param($faculty_stmt, $faculty_types, ...$faculty_params);
            mysqli_stmt_execute($faculty_stmt);
            $faculty_result = mysqli_stmt_get_result($faculty_stmt);
            
            while ($row = mysqli_fetch_assoc($faculty_result)) {
                $faculty_balances[] = $row;
            }
        }
    } catch (Exception $e) {
        // Silently handle error
    }
}

// Combine all balances
$all_balances = array_merge($employee_balances, $faculty_balances);

// Calculate statistics based on current tab
$unique_employees = [];
$unique_faculty = [];
$total_leave_days = 0;
$total_used_days = 0;
$total_remaining_days = 0;
$leave_type_stats = [];

// Get all balances for statistics (regardless of tab)
$all_balances_for_stats = array_merge($employee_balances, $faculty_balances);

foreach ($all_balances_for_stats as $balance) {
    // Create unique key for each person
    $person_key = $balance['employee_id'] . '_' . $balance['source_table'];
    
    if ($balance['source_table'] === 'employee') {
        $unique_employees[$person_key] = [
            'id' => $balance['employee_id'],
            'name' => $balance['first_name'] . ' ' . $balance['last_name'],
            'department' => $balance['department']
        ];
    } else {
        $unique_faculty[$person_key] = [
            'id' => $balance['employee_id'],
            'name' => $balance['first_name'] . ' ' . $balance['last_name'],
            'department' => $balance['department']
        ];
    }
    
    $total_leave_days += $balance['total_days'];
    $total_used_days += $balance['used_days'];
    $total_remaining_days += ($balance['total_days'] - $balance['used_days']);
    
    // Group by leave type
    $leave_type = $balance['leave_type_name'];
    if (!isset($leave_type_stats[$leave_type])) {
        $leave_type_stats[$leave_type] = [
            'total_days' => 0,
            'used_days' => 0,
            'remaining_days' => 0,
            'employee_count' => 0,
            'faculty_count' => 0
        ];
    }
    
    $leave_type_stats[$leave_type]['total_days'] += $balance['total_days'];
    $leave_type_stats[$leave_type]['used_days'] += $balance['used_days'];
    $leave_type_stats[$leave_type]['remaining_days'] += ($balance['total_days'] - $balance['used_days']);
    
    if ($balance['source_table'] === 'employee') {
        $leave_type_stats[$leave_type]['employee_count']++;
    } else {
        $leave_type_stats[$leave_type]['faculty_count']++;
    }
}

// Get accurate counts
$total_employees = count($unique_employees);
$total_faculty = count($unique_faculty);

// Calculate current tab statistics
$current_tab_balances = $current_tab === 'employees' ? $employee_balances : ($current_tab === 'faculty' ? $faculty_balances : $all_balances);
$current_tab_leave_days = 0;
$current_tab_used_days = 0;
$current_tab_remaining_days = 0;

foreach ($current_tab_balances as $balance) {
    $current_tab_leave_days += $balance['total_days'];
    $current_tab_used_days += $balance['used_days'];
    $current_tab_remaining_days += ($balance['total_days'] - $balance['used_days']);
}

// For display, use only the current tab's data
$display_balances = $current_tab_balances;

// Include the header
include 'includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Leave Balances</h1>
            <p class="text-gray-600">View and manage employee and faculty leave balances</p>
        </div>
        <div class="flex space-x-3">
            <a href="leave-management.php" class="bg-seait-dark text-white px-4 py-2 rounded-lg hover:bg-gray-800 transform transition-all hover:scale-105 font-medium">
                <i class="fas fa-calendar-alt mr-2"></i>Leave Management
            </a>
            <button onclick="exportLeaveBalances()" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transform transition-all hover:scale-105 font-medium">
                <i class="fas fa-download mr-2"></i>Export
            </button>
        </div>
    </div>
</div>

<!-- Tabs -->
<div class="mb-6">
    <div class="border-b border-gray-200">
        <nav class="-mb-px flex space-x-8" aria-label="Tabs">
            <a href="?tab=employees<?php echo $year_filter ? '&year=' . $year_filter : ''; ?><?php echo $department_filter ? '&department=' . urlencode($department_filter) : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
               class="<?php echo (!isset($_GET['tab']) || $_GET['tab'] === 'employees') ? 'border-seait-orange text-seait-orange' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                <i class="fas fa-users mr-2"></i>Employees
                <span class="ml-2 bg-gray-100 text-gray-900 py-0.5 px-2.5 rounded-full text-xs font-medium"><?php echo $total_employees; ?></span>
            </a>
            <a href="?tab=faculty<?php echo $year_filter ? '&year=' . $year_filter : ''; ?><?php echo $department_filter ? '&department=' . urlencode($department_filter) : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
               class="<?php echo (isset($_GET['tab']) && $_GET['tab'] === 'faculty') ? 'border-seait-orange text-seait-orange' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                <i class="fas fa-chalkboard-teacher mr-2"></i>Faculty
                <span class="ml-2 bg-gray-100 text-gray-900 py-0.5 px-2.5 rounded-full text-xs font-medium"><?php echo $total_faculty; ?></span>
            </a>
        </nav>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6 border border-gray-200">
    <form method="GET" id="leave-balance-filters" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <input type="hidden" name="tab" value="<?php echo htmlspecialchars($current_tab); ?>">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Year</label>
            <select name="year" class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all">
                <?php for ($year = $current_year + 2; $year >= $current_year - 2; $year--): ?>
                    <option value="<?php echo $year; ?>" <?php echo $year_filter == $year ? 'selected' : ''; ?>>
                        <?php echo $year; ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Department</label>
            <select name="department" class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all">
                <option value="">All Departments</option>
                <?php foreach ($departments as $department): ?>
                    <option value="<?php echo htmlspecialchars($department); ?>" <?php echo $department_filter === $department ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($department); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="<?php echo ($current_tab === 'employees' || $current_tab === 'faculty') ? 'hidden' : ''; ?>">
            <label class="block text-sm font-medium text-gray-700 mb-2">Employee Type</label>
            <select name="employee_type" class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all">
                <option value="all" <?php echo $employee_type_filter === 'all' ? 'selected' : ''; ?>>All Types</option>
                <option value="employee" <?php echo $employee_type_filter === 'employee' ? 'selected' : ''; ?>>Employees</option>
                <option value="faculty" <?php echo $employee_type_filter === 'faculty' ? 'selected' : ''; ?>>Faculty</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                   placeholder="Search by name or ID..."
                   class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all">
        </div>
        <div class="flex items-end">
            <button type="submit" class="w-full bg-gradient-to-r from-seait-orange to-orange-500 text-white px-4 py-2 rounded-lg hover:from-orange-500 hover:to-seait-orange transform transition-all hover:scale-105 hover:shadow-lg font-medium">
                <i class="fas fa-search mr-2"></i>Filter
            </button>
        </div>
    </form>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
    <!-- Total Employees -->
    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                <i class="fas fa-users text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Total Employees</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $total_employees; ?></p>
            </div>
        </div>
    </div>
    
    <!-- Total Faculty -->
    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                <i class="fas fa-chalkboard-teacher text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Total Faculty</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $total_faculty; ?></p>
            </div>
        </div>
    </div>
    
    <!-- Total Leave Days -->
    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-green-100 text-green-600">
                <i class="fas fa-calendar-check text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Total Leave Days</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo number_format($current_tab_leave_days); ?></p>
            </div>
        </div>
    </div>
    
    <!-- Remaining Days -->
    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-orange-100 text-orange-600">
                <i class="fas fa-clock text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Remaining Days</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo number_format($current_tab_remaining_days); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Leave Type Statistics -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6 border border-gray-200">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Leave Type Statistics (<?php echo $year_filter; ?>)</h3>
    
    <?php if (!empty($leave_type_stats)): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Leave Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Days</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Used Days</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Remaining</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usage %</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employees</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Faculty</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($leave_type_stats as $leave_type => $stats): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo htmlspecialchars($leave_type); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo number_format($stats['total_days']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo number_format($stats['used_days']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo number_format($stats['remaining_days']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php 
                                $usage_percentage = $stats['total_days'] > 0 ? ($stats['used_days'] / $stats['total_days']) * 100 : 0;
                                $color_class = $usage_percentage > 80 ? 'text-red-600' : ($usage_percentage > 60 ? 'text-yellow-600' : 'text-green-600');
                                ?>
                                <span class="<?php echo $color_class; ?> font-medium">
                                    <?php echo number_format($usage_percentage, 1); ?>%
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo $stats['employee_count']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo $stats['faculty_count']; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-gray-500 text-center py-8">No leave balance data available for <?php echo $year_filter; ?></p>
    <?php endif; ?>
</div>

<!-- Detailed Leave Balances -->
<div class="bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
        <h3 class="text-lg font-medium text-gray-900">Detailed Leave Balances - <?php echo ucfirst($current_tab); ?> (<?php echo count($display_balances); ?> records)</h3>
    </div>
    
    <?php if (!empty($display_balances)): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-hr-secondary">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Employee</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Department</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Leave Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Total Days</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Used Days</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Remaining</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Usage %</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($display_balances as $balance): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-blue-600 rounded-full flex items-center justify-center text-white font-bold text-sm">
                                            <?php echo strtoupper(substr($balance['first_name'], 0, 1) . substr($balance['last_name'], 0, 1)); ?>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($balance['first_name'] . ' ' . $balance['last_name']); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <?php echo htmlspecialchars($balance['employee_id']); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-3 py-1 text-xs rounded-full font-semibold <?php echo $balance['source_table'] === 'employee' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800'; ?>">
                                    <?php echo ucfirst($balance['source_table']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo htmlspecialchars($balance['department']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo htmlspecialchars($balance['leave_type_name']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo number_format($balance['total_days']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo number_format($balance['used_days']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo number_format($balance['total_days'] - $balance['used_days']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <?php 
                                $usage_percentage = $balance['total_days'] > 0 ? ($balance['used_days'] / $balance['total_days']) * 100 : 0;
                                $color_class = $usage_percentage > 80 ? 'text-red-600' : ($usage_percentage > 60 ? 'text-yellow-600' : 'text-green-600');
                                ?>
                                <span class="<?php echo $color_class; ?> font-medium">
                                    <?php echo number_format($usage_percentage, 1); ?>%
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="px-6 py-8 text-center text-gray-500">
            <i class="fas fa-inbox text-4xl mb-4 text-gray-300"></i>
            <p>No leave balance data found for the selected filters.</p>
            <p class="text-sm mt-2">This might be because:</p>
            <ul class="text-sm mt-1">
                <li>• No leave balances have been created yet</li>
                <li>• The selected year has no data</li>
                <li>• The filters are too restrictive</li>
            </ul>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>

<script>
// Export functionality
function exportLeaveBalances() {
    const currentUrl = new URL(window.location.href);
    const params = new URLSearchParams(currentUrl.search);
    params.set('export', '1');
    params.set('tab', '<?php echo $current_tab; ?>');
    const downloadUrl = 'export-leave-balances.php?' + params.toString();
    window.open(downloadUrl, '_blank');
}

// Auto-submit form on filter change
document.addEventListener('DOMContentLoaded', function() {
    const filterForm = document.getElementById('leave-balance-filters');
    const filterInputs = filterForm.querySelectorAll('select, input[type="text"]');
    
    filterInputs.forEach(input => {
        input.addEventListener('change', function() {
            filterForm.submit();
        });
    });
    
    // Don't auto-submit on search input to allow typing
    const searchInput = filterForm.querySelector('input[name="search"]');
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            filterForm.submit();
        }
    });
});
</script>
