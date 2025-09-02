<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/id_encryption.php';
require_once '../includes/error_handler.php';

// Check database connection
if (!checkDatabaseConnection($conn)) {
    // If we can't redirect due to headers already sent, show a user-friendly error
    if (headers_sent()) {
        echo '<div style="background: #fee; border: 1px solid #fcc; padding: 20px; margin: 20px; border-radius: 5px; color: #c33;">
                <h2>Database Connection Error</h2>
                <p>Unable to connect to the database. Please try refreshing the page or contact support if the problem persists.</p>
              </div>';
        exit();
    }
}

// Check if user is logged in and has human_resource role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'human_resource') {
    header('Location: ../index.php');
    exit();
}

// Set page title
$page_title = 'Admin Employee';

// Pagination settings
$records_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

// Get filter parameters
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$department_filter = isset($_GET['department']) ? $_GET['department'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Build query conditions
$where_conditions = ["e.is_active = 1"];
$params = [];
$param_types = "";

if ($search) {
    $where_conditions[] = "(e.first_name LIKE ? OR e.last_name LIKE ? OR e.email LIKE ? OR e.department LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $param_types .= "ssss";
}

if ($department_filter) {
    $where_conditions[] = "e.department = ?";
    $params[] = $department_filter;
    $param_types .= "s";
}

if ($status_filter) {
    $where_conditions[] = "e.is_active = ?";
    $params[] = $status_filter === 'active' ? 1 : 0;
    $param_types .= "i";
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM employees e $where_clause";
$count_stmt = mysqli_prepare($conn, $count_query);
if ($count_stmt && !empty($params)) {
    mysqli_stmt_bind_param($count_stmt, $param_types, ...$params);
    if (!checkDatabaseStatement($count_stmt, "count")) {
        // If we can't redirect due to headers already sent, show a user-friendly error
        if (headers_sent()) {
            echo '<div style="background: #fee; border: 1px solid #fcc; padding: 20px; margin: 20px; border-radius: 5px; color: #c33;">
                    <h2>Database Error</h2>
                    <p>Unable to retrieve employee count. Please try refreshing the page or contact support if the problem persists.</p>
                  </div>';
            exit();
        }
    }
    $count_result = mysqli_stmt_get_result($count_stmt);
} else {
    $count_result = mysqli_query($conn, $count_query);
    if (!checkDatabaseQuery($count_result, "count")) {
        // If we can't redirect due to headers already sent, show a user-friendly error
        if (headers_sent()) {
            echo '<div style="background: #fee; border: 1px solid #fcc; padding: 20px; margin: 20px; border-radius: 5px; color: #c33;">
                    <h2>Database Error</h2>
                    <p>Unable to retrieve employee count. Please try refreshing the page or contact support if the problem persists.</p>
                  </div>';
            exit();
        }
    }
}
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $records_per_page);

// Get employees with pagination
$employees_query = "SELECT e.*, d.name as department_name, d.icon as department_icon, d.color_theme as department_color 
                   FROM employees e 
                   LEFT JOIN departments d ON e.department = d.name 
                   $where_clause 
                   ORDER BY e.last_name, e.first_name 
                   LIMIT ? OFFSET ?";

// Add pagination parameters
$pagination_params = array_merge($params, [$records_per_page, $offset]);
$pagination_param_types = $param_types . "ii";

$employees_stmt = mysqli_prepare($conn, $employees_query);
if ($employees_stmt && !empty($pagination_params)) {
    mysqli_stmt_bind_param($employees_stmt, $pagination_param_types, ...$pagination_params);
    if (!checkDatabaseStatement($employees_stmt, "employees")) {
        // If we can't redirect due to headers already sent, show a user-friendly error
        if (headers_sent()) {
            echo '<div style="background: #fee; border: 1px solid #fcc; padding: 20px; margin: 20px; border-radius: 5px; color: #c33;">
                    <h2>Database Error</h2>
                    <p>Unable to retrieve employee list. Please try refreshing the page or contact support if the problem persists.</p>
                  </div>';
            exit();
        }
    }
    $employees_result = mysqli_stmt_get_result($employees_stmt);
} else {
    $employees_result = mysqli_query($conn, $employees_query);
    if (!checkDatabaseQuery($employees_result, "employees")) {
        // If we can't redirect due to headers already sent, show a user-friendly error
        if (headers_sent()) {
            echo '<div style="background: #fee; border: 1px solid #fcc; padding: 20px; margin: 20px; border-radius: 5px; color: #c33;">
                    <h2>Database Error</h2>
                    <p>Unable to retrieve employee list. Please try refreshing the page or contact support if the problem persists.</p>
                  </div>';
            exit();
        }
    }
}

$employees = [];
while ($row = mysqli_fetch_assoc($employees_result)) {
    $employees[] = $row;
}

// Get unique departments for filter
$departments_query = "SELECT name FROM departments WHERE is_active = 1 ORDER BY sort_order, name";
$departments_result = mysqli_query($conn, $departments_query);
if (!checkDatabaseQuery($departments_result, "departments")) {
    // If we can't redirect due to headers already sent, show a user-friendly error
    if (headers_sent()) {
        echo '<div style="background: #fee; border: 1px solid #fcc; padding: 20px; margin: 20px; border-radius: 5px; color: #c33;">
                <h2>Database Error</h2>
                <p>Unable to retrieve department list. Please try refreshing the page or contact support if the problem persists.</p>
              </div>';
        exit();
    }
}
$departments = [];
while ($row = mysqli_fetch_assoc($departments_result)) {
    $departments[] = $row['name'];
}

// Include the header
include 'includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Admin Employee</h1>
            <p class="text-gray-600">Add new employees to the system</p>
        </div>
        <div class="flex space-x-3">
            <button onclick="openAddEmployeeModal()" class="bg-gradient-to-r from-seait-orange to-orange-500 text-white px-4 py-2 rounded-lg hover:from-orange-500 hover:to-seait-orange transform transition-all hover:scale-105 hover:shadow-lg font-medium">
                <i class="fas fa-plus mr-2"></i>Add Employee
            </button>
            <a href="manage-degrees.php" class="bg-seait-dark text-white px-4 py-2 rounded-lg hover:bg-gray-800 transform transition-all hover:scale-105 font-medium">
                <i class="fas fa-graduation-cap mr-2"></i>Manage Degrees
            </a>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6 border border-gray-200">
    <form method="GET" id="employee-filters" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
            <input type="text" name="search" id="employee-search" value="<?php echo htmlspecialchars($search ?? ''); ?>" 
                   placeholder="Search by name, email, or department..."
                   class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Department</label>
            <select name="department" class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all">
                <option value="">All Departments</option>
                <?php foreach ($departments as $department): ?>
                    <option value="<?php echo htmlspecialchars($department ?? ''); ?>" <?php echo $department_filter === $department ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($department ?? ''); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
            <select name="status" class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all">
                <option value="">All Status</option>
                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
            </select>
        </div>
        <div class="flex items-end">
            <button type="submit" class="w-full bg-gradient-to-r from-seait-orange to-orange-500 text-white px-4 py-2 rounded-lg hover:from-orange-500 hover:to-seait-orange transform transition-all hover:scale-105 hover:shadow-lg font-medium">
                <i class="fas fa-search mr-2"></i>Search
            </button>
        </div>
    </form>
</div>

<!-- Search Results -->
<div id="search-results" class="mb-6" style="display: none;"></div>

<!-- Employee Table -->
<div class="bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
        <h3 class="text-lg font-medium text-gray-900">Employees (<?php echo $total_records; ?> found)</h3>
    </div>
    
    <!-- Desktop Table View -->
    <div class="hidden lg:block overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-hr-secondary">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Employee</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Position</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Department</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Email</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($employees)): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">No employees found</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($employees as $employee): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-blue-600 rounded-full flex items-center justify-center text-white font-bold text-sm">
                                            <?php echo strtoupper(substr($employee['first_name'] ?? '', 0, 1) . substr($employee['last_name'] ?? '', 0, 1)); ?>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo ($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? ''); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo $employee['position'] ?? ''; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <div class="flex items-center">
                                    <?php if ($employee['department_icon'] && $employee['department_color']): ?>
                                        <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white text-xs mr-2" 
                                             style="background-color: <?php echo $employee['department_color']; ?>">
                                            <i class="<?php echo $employee['department_icon']; ?>"></i>
                                        </div>
                                    <?php endif; ?>
                                    <span><?php echo ($employee['department_name'] ?: $employee['department']) ?? ''; ?></span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo $employee['email'] ?? ''; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-3 py-1 text-xs rounded-full font-semibold <?php echo $employee['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo $employee['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="view-employee.php?id=<?php echo encrypt_id($employee['id']); ?>" 
                                   class="text-seait-orange hover:text-seait-dark font-medium mr-3 transition-colors">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <a href="edit-employee.php?id=<?php echo encrypt_id($employee['id']); ?>" 
                                   class="text-seait-dark hover:text-gray-700 font-medium transition-colors">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Mobile/Tablet Card View -->
    <div class="lg:hidden">
        <?php if (empty($employees)): ?>
            <div class="px-6 py-4 text-center text-gray-500">No employees found</div>
        <?php else: ?>
            <div class="divide-y divide-gray-200">
                <?php foreach ($employees as $employee): ?>
                    <div class="p-4 hover:bg-gray-50 transition-colors">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="flex-shrink-0">
                                                                            <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-blue-600 rounded-full flex items-center justify-center text-white font-bold text-sm">
                                            <?php echo strtoupper(substr($employee['first_name'] ?? '', 0, 1) . substr($employee['last_name'] ?? '', 0, 1)); ?>
                                        </div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-medium text-gray-900 truncate">
                                        <?php echo ($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? ''); ?>
                                    </div>
                                    <div class="text-sm text-gray-500 truncate">
                                        <?php echo $employee['position'] ?? ''; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="flex flex-col items-end space-y-2">
                                <span class="px-3 py-1 text-xs rounded-full font-semibold <?php echo $employee['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo $employee['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                                <div class="flex space-x-2">
                                    <a href="view-employee.php?id=<?php echo encrypt_id($employee['id']); ?>" 
                                       class="text-seait-orange hover:text-seait-dark font-medium text-sm transition-colors">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="edit-employee.php?id=<?php echo encrypt_id($employee['id']); ?>" 
                                       class="text-seait-dark hover:text-gray-700 font-medium text-sm transition-colors">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3 space-y-1">
                            <div class="text-sm text-gray-500">
                                <span class="font-medium">Department:</span> 
                                <div class="flex items-center mt-1">
                                    <?php if ($employee['department_icon'] && $employee['department_color']): ?>
                                        <div class="w-6 h-6 rounded-lg flex items-center justify-center text-white text-xs mr-2" 
                                             style="background-color: <?php echo $employee['department_color']; ?>">
                                            <i class="<?php echo $employee['department_icon']; ?>"></i>
                                        </div>
                                    <?php endif; ?>
                                    <span><?php echo ($employee['department_name'] ?: $employee['department']) ?? ''; ?></span>
                                </div>
                            </div>
                            <div class="text-sm text-gray-500 truncate">
                                <span class="font-medium">Email:</span> <?php echo $employee['email'] ?? ''; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
        <!-- Mobile Pagination -->
        <div class="flex-1 flex justify-between lg:hidden">
            <?php if ($current_page > 1): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page - 1])); ?>" 
                   class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    Previous
                </a>
            <?php endif; ?>
            <div class="text-sm text-gray-700">
                Page <?php echo $current_page; ?> of <?php echo $total_pages; ?>
            </div>
            <?php if ($current_page < $total_pages): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page + 1])); ?>" 
                   class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    Next
                </a>
            <?php endif; ?>
        </div>
        
        <!-- Desktop Pagination -->
        <div class="hidden lg:flex lg:flex-1 lg:items-center lg:justify-between">
            <div>
                <p class="text-sm text-gray-700">
                    Showing <span class="font-medium"><?php echo $offset + 1; ?></span> to 
                    <span class="font-medium"><?php echo min($offset + $records_per_page, $total_records); ?></span> of 
                    <span class="font-medium"><?php echo $total_records; ?></span> results
                </p>
            </div>
            <div>
                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                    <?php if ($current_page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page - 1])); ?>" 
                           class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                            <span class="sr-only">Previous</span>
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $current_page + 2);
                    
                    if ($start_page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" 
                           class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                            1
                        </a>
                        <?php if ($start_page > 2): ?>
                            <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                                ...
                            </span>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                           class="relative inline-flex items-center px-4 py-2 border text-sm font-medium <?php echo $i === $current_page ? 'z-10 bg-seait-orange border-seait-orange text-white' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($end_page < $total_pages): ?>
                        <?php if ($end_page < $total_pages - 1): ?>
                            <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                                ...
                            </span>
                        <?php endif; ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>" 
                           class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                            <?php echo $total_pages; ?>
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($current_page < $total_pages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page + 1])); ?>" 
                           class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                            <span class="sr-only">Next</span>
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </nav>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Add Employee Modal -->
<div id="addEmployeeModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-4 mx-auto p-6 border w-full max-w-7xl shadow-2xl rounded-xl bg-white max-h-[95vh] overflow-y-auto">
        <div class="mt-2">
            <div class="flex items-center justify-between mb-8 pb-4 border-b border-gray-200">
                <div>
                    <h3 class="text-2xl font-bold text-gray-900">Add New Employee</h3>
                    <p class="text-gray-600 mt-1">Complete all required information to register a new employee</p>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="text-sm text-gray-600">
                        <span id="formProgress">0%</span> Complete
                    </div>
                    <button onclick="closeAddEmployeeModal()" class="text-gray-400 hover:text-gray-600 p-2 hover:bg-gray-100 rounded-lg transition-colors">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
            </div>
            <form id="addEmployeeForm" class="space-y-8">
                <!-- Personal Information Section -->
                <div class="bg-gray-50 rounded-xl p-6 border border-gray-200">
                    <div class="flex items-center mb-6">
                        <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-user text-blue-600 text-lg"></i>
                        </div>
                        <div>
                            <h4 class="text-xl font-bold text-gray-900">Personal Information</h4>
                            <p class="text-gray-600 text-sm">Basic personal details of the employee</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                First Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="first_name" required
                                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all bg-white shadow-sm"
                                   placeholder="Enter first name">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Last Name *</label>
                            <input type="text" name="last_name" required
                                   class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                                   placeholder="Enter last name">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Email Address *</label>
                            <input type="email" name="email" required
                                   class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                                   placeholder="Enter email address">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number *</label>
                            <input type="tel" name="phone" required
                                   class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                                   placeholder="Enter phone number">
                        </div>
                    </div>

                    <div class="mt-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Complete Address *</label>
                        <textarea name="address" required rows="3"
                                  class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                                  placeholder="Enter complete address"></textarea>
                    </div>
                </div>

                <!-- Employment Information Section -->
                <div class="bg-gray-50 rounded-xl p-6 border border-gray-200">
                    <div class="flex items-center mb-6">
                        <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-briefcase text-purple-600 text-lg"></i>
                        </div>
                        <div>
                            <h4 class="text-xl font-bold text-gray-900">Employment Information</h4>
                            <p class="text-gray-600 text-sm">Job details and employment status</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Employee ID *</label>
                            <div class="flex space-x-2">
                                <input type="text" name="employee_id" id="employee_id_input"
                                       class="flex-1 px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                                       placeholder="YYYY-XXXX (e.g., 2025-0001)" pattern="\d{4}-\d{4}">
                                <button type="button" onclick="generateEmployeeID()" 
                                        class="px-4 py-2 bg-seait-dark text-white rounded-lg hover:bg-gray-800 transition-colors">
                                    <i class="fas fa-magic mr-1"></i>Auto
                                </button>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Format: YYYY-XXXX (Year-Series)</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Date of Hire *</label>
                            <input type="date" name="hire_date" required
                                   class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Position/Title *</label>
                            <input type="text" name="position" required
                                   class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                                   placeholder="Enter position/title">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Department *</label>
                            <select name="department" required class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all">
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $department): ?>
                                    <option value="<?php echo htmlspecialchars($department ?? ''); ?>">
                                        <?php echo htmlspecialchars($department ?? ''); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Employee Type *</label>
                            <select name="employee_type" required class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all">
                                <option value="">Select Employee Type</option>
                                <option value="faculty">Faculty</option>
                                <option value="staff">Staff</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Employment Status</label>
                            <select name="is_active" class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Account Information Section -->
                <div class="bg-gray-50 rounded-xl p-6 border border-gray-200">
                    <div class="flex items-center mb-6">
                        <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-key text-green-600 text-lg"></i>
                        </div>
                        <div>
                            <h4 class="text-xl font-bold text-gray-900">Account Information</h4>
                            <p class="text-gray-600 text-sm">Login credentials for the employee</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Password *</label>
                            <input type="password" name="password" required
                                   class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                                   placeholder="Enter password">
                            <p class="text-xs text-gray-500 mt-1">Minimum 8 characters</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Confirm Password *</label>
                            <input type="password" name="confirm_password" required
                                   class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                                   placeholder="Confirm password">
                        </div>
                    </div>
                </div>

                <div class="bg-white border-t border-gray-200 mt-8 pt-6">
                    <div class="flex justify-between items-center">
                        <div class="text-sm text-gray-600">
                            <i class="fas fa-info-circle mr-2"></i>
                            All fields marked with * are required
                        </div>
                        <div class="flex space-x-4">
                            <button type="button" onclick="closeAddEmployeeModal()" 
                                    class="px-8 py-3 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors font-medium border border-gray-300">
                                <i class="fas fa-times mr-2"></i>Cancel
                            </button>
                            <button type="submit" 
                                    class="px-8 py-3 bg-gradient-to-r from-seait-orange to-orange-500 text-white rounded-lg hover:from-orange-500 hover:to-seait-orange transform transition-all hover:scale-105 font-medium shadow-lg">
                                <i class="fas fa-plus mr-2"></i>Add Employee
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<!-- Custom JavaScript for HR Dashboard -->
<script src="assets/js/hr-dashboard.js"></script>

<script>


// Modal functions
function openAddEmployeeModal() {
    document.getElementById('addEmployeeModal').classList.remove('hidden');
}

function closeAddEmployeeModal() {
    document.getElementById('addEmployeeModal').classList.add('hidden');
    
    // Reset form
    const form = document.getElementById('addEmployeeForm');
    if (form) {
        form.reset();
        
        // Remove validation styling
        const fields = form.querySelectorAll('input, select, textarea');
        fields.forEach(field => {
            field.classList.remove('border-red-500');
            field.disabled = false;
        });
        
        // Reset progress
        document.getElementById('formProgress').textContent = '0%';
        document.getElementById('formProgress').className = 'text-red-500 font-semibold';
    }
}

// Employee ID generation
function generateEmployeeID() {
    fetch('get-next-employee-id.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('employee_id_input').value = data.employee_id;
                showToast('Employee ID generated: ' + data.employee_id, 'success');
            } else {
                showToast('Error generating employee ID: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Network error. Please try again.', 'error');
        });
}

// Form progress tracking
function updateFormProgress() {
    const form = document.getElementById('addEmployeeForm');
    const requiredFields = form.querySelectorAll('input[required], select[required], textarea[required]');
    const filledFields = Array.from(requiredFields).filter(field => field.value.trim() !== '');
    const progress = Math.round((filledFields.length / requiredFields.length) * 100);
    
    document.getElementById('formProgress').textContent = progress + '%';
    
    // Update progress bar color
    const progressElement = document.getElementById('formProgress');
    if (progress < 50) {
        progressElement.className = 'text-red-500 font-semibold';
    } else if (progress < 100) {
        progressElement.className = 'text-yellow-500 font-semibold';
    } else {
        progressElement.className = 'text-green-500 font-semibold';
    }
}

// Add event listeners to all form fields
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('addEmployeeForm');
    if (form) {
        const fields = form.querySelectorAll('input, select, textarea');
        fields.forEach(field => {
            field.addEventListener('input', updateFormProgress);
            field.addEventListener('change', updateFormProgress);
        });
    }
});

// Form submission with enhanced validation and error handling
document.getElementById('addEmployeeForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Client-side validation
    const requiredFields = this.querySelectorAll('input[required], select[required], textarea[required]');
    const missingFields = [];
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            missingFields.push(field.name.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()));
            field.classList.add('border-red-500');
        } else {
            field.classList.remove('border-red-500');
        }
    });
    
    if (missingFields.length > 0) {
        showToast('Please fill in all required fields: ' + missingFields.join(', '), 'error');
        return;
    }
    
    // Email validation
    const emailField = this.querySelector('input[name="email"]');
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(emailField.value)) {
        showToast('Please enter a valid email address', 'error');
        emailField.classList.add('border-red-500');
        return;
    } else {
        emailField.classList.remove('border-red-500');
    }
    
    // Password validation
    const passwordField = this.querySelector('input[name="password"]');
    const confirmPasswordField = this.querySelector('input[name="confirm_password"]');
    
    if (passwordField.value.length < 8) {
        showToast('Password must be at least 8 characters long', 'error');
        passwordField.classList.add('border-red-500');
        return;
    } else {
        passwordField.classList.remove('border-red-500');
    }
    
    if (passwordField.value !== confirmPasswordField.value) {
        showToast('Passwords do not match', 'error');
        confirmPasswordField.classList.add('border-red-500');
        return;
    } else {
        confirmPasswordField.classList.remove('border-red-500');
    }
    
    const formData = new FormData(this);
    
    // Show loading state
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding Employee...';
    submitBtn.disabled = true;
    
    // Disable all form fields during submission
    const formFields = this.querySelectorAll('input, select, textarea');
    formFields.forEach(field => field.disabled = true);
    
    fetch('add-employee.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showToast('Employee added successfully!', 'success');
            closeAddEmployeeModal();
            
            // Show success animation
            const successIcon = document.createElement('div');
            successIcon.className = 'fixed inset-0 bg-green-500 bg-opacity-20 flex items-center justify-center z-50';
            successIcon.innerHTML = '<div class="bg-white p-8 rounded-lg shadow-lg text-center"><i class="fas fa-check-circle text-6xl text-green-500 mb-4"></i><h3 class="text-xl font-bold text-gray-900">Employee Added Successfully!</h3></div>';
            document.body.appendChild(successIcon);
            
            setTimeout(() => {
                document.body.removeChild(successIcon);
                // Reload page to show new employee
                window.location.reload();
            }, 2000);
        } else {
            showToast(data.message || 'Error adding employee', 'error');
            
            // Re-enable form fields on error
            formFields.forEach(field => field.disabled = false);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Network error. Please try again.', 'error');
        
        // Re-enable form fields on error
        formFields.forEach(field => field.disabled = false);
    })
    .finally(() => {
        // Reset button state
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
});

// Enhanced toast notification function
function showToast(message, type = 'info') {
    // Remove existing toasts
    const existingToasts = document.querySelectorAll('.toast-notification');
    existingToasts.forEach(toast => document.body.removeChild(toast));
    
    const toast = document.createElement('div');
    toast.className = `toast-notification fixed top-4 right-4 z-50 p-4 rounded-lg shadow-xl transform transition-all duration-300 translate-x-full max-w-md`;
    
    // Set background and icon based on type
    let bgColor, icon, iconColor;
    switch(type) {
        case 'success':
            bgColor = 'bg-gradient-to-r from-green-500 to-green-600';
            icon = 'fas fa-check-circle';
            iconColor = 'text-green-100';
            break;
        case 'error':
            bgColor = 'bg-gradient-to-r from-red-500 to-red-600';
            icon = 'fas fa-exclamation-circle';
            iconColor = 'text-red-100';
            break;
        case 'warning':
            bgColor = 'bg-gradient-to-r from-yellow-500 to-yellow-600';
            icon = 'fas fa-exclamation-triangle';
            iconColor = 'text-yellow-100';
            break;
        default:
            bgColor = 'bg-gradient-to-r from-blue-500 to-blue-600';
            icon = 'fas fa-info-circle';
            iconColor = 'text-blue-100';
    }
    
    toast.className += ` ${bgColor} text-white`;
    
    toast.innerHTML = `
        <div class="flex items-center space-x-3">
            <i class="${icon} ${iconColor} text-xl"></i>
            <div class="flex-1">
                <p class="font-medium">${message}</p>
            </div>
            <button onclick="this.parentElement.parentElement.remove()" class="text-white hover:text-gray-200 transition-colors">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    document.body.appendChild(toast);
    
    // Animate in
    setTimeout(() => {
        toast.classList.remove('translate-x-full');
    }, 100);
    
    // Auto remove after 4 seconds
    setTimeout(() => {
        toast.classList.add('translate-x-full');
        setTimeout(() => {
            if (document.body.contains(toast)) {
                document.body.removeChild(toast);
            }
        }, 300);
    }, 4000);
}

// Close modal when clicking outside
window.addEventListener('click', function(e) {
    const modal = document.getElementById('addEmployeeModal');
    if (e.target === modal) {
        closeAddEmployeeModal();
    }
});
</script>
