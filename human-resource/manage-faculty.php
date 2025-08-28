<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/id_encryption.php';
require_once 'includes/employee_id_generator.php';

// Check if user is logged in and has human_resource role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'human_resource') {
    header('Location: ../index.php');
    exit();
}

// Set page title
$page_title = 'Manage Faculty';

// Pagination settings
$records_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

// Get filter parameters
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$department_filter = isset($_GET['department']) ? $_GET['department'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Build query conditions
$where_conditions = ["f.is_active = 1"];
$params = [];
$param_types = "";

if ($search) {
    $where_conditions[] = "(f.first_name LIKE ? OR f.last_name LIKE ? OR f.email LIKE ? OR f.department LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $param_types .= "ssss";
}

if ($department_filter) {
    $where_conditions[] = "f.department = ?";
    $params[] = $department_filter;
    $param_types .= "s";
}

if ($status_filter) {
    $where_conditions[] = "f.is_active = ?";
    $params[] = $status_filter === 'active' ? 1 : 0;
    $param_types .= "i";
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM faculty f $where_clause";
$count_stmt = mysqli_prepare($conn, $count_query);
if ($count_stmt && !empty($params)) {
    mysqli_stmt_bind_param($count_stmt, $param_types, ...$params);
    mysqli_stmt_execute($count_stmt);
    $count_result = mysqli_stmt_get_result($count_stmt);
} else {
    $count_result = mysqli_query($conn, $count_query);
}
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $records_per_page);

// Get faculty members with pagination
$faculty_query = "SELECT f.* FROM faculty f $where_clause ORDER BY f.last_name, f.first_name LIMIT ? OFFSET ?";

// Add pagination parameters
$pagination_params = array_merge($params, [$records_per_page, $offset]);
$pagination_param_types = $param_types . "ii";

$faculty_stmt = mysqli_prepare($conn, $faculty_query);
if ($faculty_stmt && !empty($pagination_params)) {
    mysqli_stmt_bind_param($faculty_stmt, $pagination_param_types, ...$pagination_params);
    mysqli_stmt_execute($faculty_stmt);
    $faculty_result = mysqli_stmt_get_result($faculty_stmt);
} else {
    $faculty_result = mysqli_query($conn, $faculty_query);
}

$faculty_members = [];
while ($row = mysqli_fetch_assoc($faculty_result)) {
    $faculty_members[] = $row;
}

// Get unique departments for filter
$colleges_query = "SELECT name FROM colleges WHERE is_active = 1 ORDER BY name";
$colleges_result = mysqli_query($conn, $colleges_query);
$colleges = [];
while ($row = mysqli_fetch_assoc($colleges_result)) {
    $colleges[] = $row['name'];
}

// Include the header
include 'includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Manage Faculty</h1>
            <p class="text-gray-600">View and manage faculty members</p>
        </div>
        <div class="flex space-x-3">
            <button onclick="openAddFacultyModal()" class="bg-gradient-to-r from-seait-orange to-orange-500 text-white px-4 py-2 rounded-lg hover:from-orange-500 hover:to-seait-orange transform transition-all hover:scale-105 hover:shadow-lg font-medium">
                <i class="fas fa-plus mr-2"></i>Add Faculty
            </button>
            <a href="manage-degrees.php" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transform transition-all hover:scale-105 font-medium">
                <i class="fas fa-graduation-cap mr-2"></i>Manage Degrees
            </a>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6 border border-gray-200">
    <form method="GET" id="faculty-filters" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
            <input type="text" name="search" id="faculty-search" value="<?php echo htmlspecialchars($search); ?>" 
                   placeholder="Search by name, email, or department..."
                   class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Department</label>
            <select name="department" class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all">
                <option value="">All Colleges</option>
                <?php foreach ($colleges as $college): ?>
                    <option value="<?php echo htmlspecialchars($college); ?>" <?php echo $department_filter === $college ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($college); ?>
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

<!-- Faculty Table -->
<div class="bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
        <h3 class="text-lg font-medium text-gray-900">Faculty Members (<?php echo $total_records; ?> found)</h3>
    </div>
    
    <!-- Desktop Table View -->
    <div class="hidden lg:block overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-hr-secondary">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Faculty Member</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Position</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Department</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Email</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($faculty_members)): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">No faculty members found</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($faculty_members as $faculty): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-blue-600 rounded-full flex items-center justify-center text-white font-bold text-sm">
                                            <?php echo strtoupper(substr($faculty['first_name'], 0, 1) . substr($faculty['last_name'], 0, 1)); ?>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo $faculty['first_name'] . ' ' . $faculty['last_name']; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo $faculty['position']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo $faculty['department']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo $faculty['email']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-3 py-1 text-xs rounded-full font-semibold <?php echo $faculty['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo $faculty['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="view-faculty.php?id=<?php echo encrypt_id($faculty['id']); ?>" 
                                   class="text-seait-orange hover:text-seait-dark font-medium mr-3 transition-colors">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <a href="edit-faculty.php?id=<?php echo encrypt_id($faculty['id']); ?>" 
                                   class="text-blue-500 hover:text-blue-700 font-medium transition-colors">
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
        <?php if (empty($faculty_members)): ?>
            <div class="px-6 py-4 text-center text-gray-500">No faculty members found</div>
        <?php else: ?>
            <div class="divide-y divide-gray-200">
                <?php foreach ($faculty_members as $faculty): ?>
                    <div class="p-4 hover:bg-gray-50 transition-colors">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="flex-shrink-0">
                                    <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-blue-600 rounded-full flex items-center justify-center text-white font-bold text-sm">
                                        <?php echo strtoupper(substr($faculty['first_name'], 0, 1) . substr($faculty['last_name'], 0, 1)); ?>
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-medium text-gray-900 truncate">
                                        <?php echo $faculty['first_name'] . ' ' . $faculty['last_name']; ?>
                                    </div>
                                    <div class="text-sm text-gray-500 truncate">
                                        <?php echo $faculty['position']; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="flex flex-col items-end space-y-2">
                                <span class="px-3 py-1 text-xs rounded-full font-semibold <?php echo $faculty['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo $faculty['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                                <div class="flex space-x-2">
                                    <a href="view-faculty.php?id=<?php echo encrypt_id($faculty['id']); ?>" 
                                       class="text-seait-orange hover:text-seait-dark font-medium text-sm transition-colors">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="edit-faculty.php?id=<?php echo encrypt_id($faculty['id']); ?>" 
                                       class="text-blue-500 hover:text-blue-700 font-medium text-sm transition-colors">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3 space-y-1">
                            <div class="text-sm text-gray-500">
                                <span class="font-medium">Department:</span> <?php echo $faculty['department']; ?>
                            </div>
                            <div class="text-sm text-gray-500 truncate">
                                <span class="font-medium">Email:</span> <?php echo $faculty['email']; ?>
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

<!-- Add Faculty Modal -->
<div id="addFacultyModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-4 mx-auto p-6 border w-full max-w-7xl shadow-2xl rounded-xl bg-white max-h-[95vh] overflow-y-auto">
        <div class="mt-2">
            <div class="flex items-center justify-between mb-8 pb-4 border-b border-gray-200">
                <div>
                    <h3 class="text-2xl font-bold text-gray-900">Add New Faculty Member</h3>
                    <p class="text-gray-600 mt-1">Complete all required information to register a new faculty member</p>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="text-sm text-gray-600">
                        <span id="formProgress">0%</span> Complete
                    </div>
                    <button onclick="closeAddFacultyModal()" class="text-gray-400 hover:text-gray-600 p-2 hover:bg-gray-100 rounded-lg transition-colors">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
            </div>
            <form id="addFacultyForm" class="space-y-8">
                <!-- Personal Information Section -->
                <div class="bg-gray-50 rounded-xl p-6 border border-gray-200">
                    <div class="flex items-center mb-6">
                        <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-user text-blue-600 text-lg"></i>
                        </div>
                        <div>
                            <h4 class="text-xl font-bold text-gray-900">Personal Information</h4>
                            <p class="text-gray-600 text-sm">Basic personal details of the faculty member</p>
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
                            <label class="block text-sm font-medium text-gray-700 mb-2">Middle Name</label>
                            <input type="text" name="middle_name"
                                   class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                                   placeholder="Enter middle name">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Date of Birth *</label>
                            <input type="date" name="date_of_birth" required
                                   class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Gender *</label>
                            <select name="gender" required class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all">
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Civil Status *</label>
                            <select name="civil_status" required class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all">
                                <option value="">Select Civil Status</option>
                                <option value="Single">Single</option>
                                <option value="Married">Married</option>
                                <option value="Widowed">Widowed</option>
                                <option value="Divorced">Divorced</option>
                                <option value="Separated">Separated</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nationality *</label>
                            <input type="text" name="nationality" required
                                   class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                                   placeholder="Enter nationality">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Religion</label>
                            <input type="text" name="religion"
                                   class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                                   placeholder="Enter religion">
                        </div>
                    </div>
                </div>

                <!-- Contact Information Section -->
                <div class="bg-gray-50 rounded-xl p-6 border border-gray-200">
                    <div class="flex items-center mb-6">
                        <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-phone text-green-600 text-lg"></i>
                        </div>
                        <div>
                            <h4 class="text-xl font-bold text-gray-900">Contact Information</h4>
                            <p class="text-gray-600 text-sm">Contact details and emergency information</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
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

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Emergency Contact Name *</label>
                            <input type="text" name="emergency_contact_name" required
                                   class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                                   placeholder="Enter emergency contact name">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Emergency Contact Number *</label>
                            <input type="tel" name="emergency_contact_number" required
                                   class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                                   placeholder="Enter emergency contact number">
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
                                       placeholder="YYYY-XXXX (e.g., 2024-0001)" pattern="\d{4}-\d{4}">
                                <button type="button" onclick="generateEmployeeID()" 
                                        class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                                    <i class="fas fa-magic mr-1"></i>Auto
                                </button>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Format: YYYY-XXXX (Year-Series)</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Date of Hire *</label>
                            <input type="date" name="date_of_hire" required
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
                            <label class="block text-sm font-medium text-gray-700 mb-2">Department/College *</label>
                            <select name="department" required class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all">
                                <option value="">Select Department/College</option>
                                <?php foreach ($colleges as $college): ?>
                                    <option value="<?php echo htmlspecialchars($college); ?>">
                                        <?php echo htmlspecialchars($college); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Employment Type *</label>
                            <select name="employment_type" required class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all">
                                <option value="">Select Employment Type</option>
                                <option value="Full-time">Full-time</option>
                                <option value="Part-time">Part-time</option>
                                <option value="Contract">Contract</option>
                                <option value="Temporary">Temporary</option>
                                <option value="Probationary">Probationary</option>
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

                <!-- Salary Information Section -->
                <div class="bg-gray-50 rounded-xl p-6 border border-gray-200">
                    <div class="flex items-center mb-6">
                        <div class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-money-bill-wave text-yellow-600 text-lg"></i>
                        </div>
                        <div>
                            <h4 class="text-xl font-bold text-gray-900">Salary Information</h4>
                            <p class="text-gray-600 text-sm">Compensation and benefits details</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Basic Salary *</label>
                            <input type="number" name="basic_salary" required step="0.01" min="0"
                                   class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                                   placeholder="Enter basic salary">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Salary Grade</label>
                            <input type="text" name="salary_grade"
                                   class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                                   placeholder="Enter salary grade">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Allowances</label>
                            <input type="number" name="allowances" step="0.01" min="0"
                                   class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                                   placeholder="Enter allowances">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Pay Schedule</label>
                            <select name="pay_schedule" class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all">
                                <option value="">Select Pay Schedule</option>
                                <option value="Monthly">Monthly</option>
                                <option value="Bi-weekly">Bi-weekly</option>
                                <option value="Weekly">Weekly</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Educational Background Section -->
                <div class="bg-gray-50 rounded-xl p-6 border border-gray-200">
                    <div class="flex items-center mb-6">
                        <div class="w-10 h-10 bg-indigo-100 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-graduation-cap text-indigo-600 text-lg"></i>
                        </div>
                        <div>
                            <h4 class="text-xl font-bold text-gray-900">Educational Background</h4>
                            <p class="text-gray-600 text-sm">Academic qualifications and credentials</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Highest Educational Attainment *</label>
                            <select name="highest_education" required class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all">
                                <option value="">Select Highest Education</option>
                                <option value="High School">High School</option>
                                <option value="Associate Degree">Associate Degree</option>
                                <option value="Bachelor's Degree">Bachelor's Degree</option>
                                <option value="Master's Degree">Master's Degree</option>
                                <option value="Doctorate">Doctorate</option>
                                <option value="Post-Doctorate">Post-Doctorate</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Field of Study *</label>
                            <input type="text" name="field_of_study" required
                                   class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                                   placeholder="Enter field of study">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">School/University *</label>
                            <input type="text" name="school_university" required
                                   class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                                   placeholder="Enter school/university">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Year Graduated</label>
                            <input type="number" name="year_graduated" min="1950" max="2030"
                                   class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                                   placeholder="Enter year graduated">
                        </div>
                    </div>
                </div>

                <!-- Government Information Section -->
                <div class="bg-gray-50 rounded-xl p-6 border border-gray-200">
                    <div class="flex items-center mb-6">
                        <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-id-card text-red-600 text-lg"></i>
                        </div>
                        <div>
                            <h4 class="text-xl font-bold text-gray-900">Government Information</h4>
                            <p class="text-gray-600 text-sm">Government-issued identification numbers</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">TIN Number</label>
                            <input type="text" name="tin_number"
                                   class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                                   placeholder="Enter TIN number">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">SSS Number</label>
                            <input type="text" name="sss_number"
                                   class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                                   placeholder="Enter SSS number">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">PhilHealth Number</label>
                            <input type="text" name="philhealth_number"
                                   class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                                   placeholder="Enter PhilHealth number">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">PAG-IBIG Number</label>
                            <input type="text" name="pagibig_number"
                                   class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                                   placeholder="Enter PAG-IBIG number">
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
                            <button type="button" onclick="closeAddFacultyModal()" 
                                    class="px-8 py-3 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors font-medium border border-gray-300">
                                <i class="fas fa-times mr-2"></i>Cancel
                            </button>
                            <button type="submit" 
                                    class="px-8 py-3 bg-gradient-to-r from-seait-orange to-orange-500 text-white rounded-lg hover:from-orange-500 hover:to-seait-orange transform transition-all hover:scale-105 font-medium shadow-lg">
                                <i class="fas fa-plus mr-2"></i>Add Faculty Member
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
function openAddFacultyModal() {
    document.getElementById('addFacultyModal').classList.remove('hidden');
}

function closeAddFacultyModal() {
    document.getElementById('addFacultyModal').classList.add('hidden');
    
    // Reset form
    const form = document.getElementById('addFacultyForm');
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
    const form = document.getElementById('addFacultyForm');
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
    const form = document.getElementById('addFacultyForm');
    if (form) {
        const fields = form.querySelectorAll('input, select, textarea');
        fields.forEach(field => {
            field.addEventListener('input', updateFormProgress);
            field.addEventListener('change', updateFormProgress);
        });
    }
});

// Form submission with enhanced validation and error handling
document.getElementById('addFacultyForm').addEventListener('submit', function(e) {
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
    
    const formData = new FormData(this);
    
    // Show loading state
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding Faculty Member...';
    submitBtn.disabled = true;
    
    // Disable all form fields during submission
    const formFields = this.querySelectorAll('input, select, textarea');
    formFields.forEach(field => field.disabled = true);
    
    fetch('add-faculty.php', {
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
            showToast('Faculty member added successfully!', 'success');
            closeAddFacultyModal();
            
            // Show success animation
            const successIcon = document.createElement('div');
            successIcon.className = 'fixed inset-0 bg-green-500 bg-opacity-20 flex items-center justify-center z-50';
            successIcon.innerHTML = '<div class="bg-white p-8 rounded-lg shadow-lg text-center"><i class="fas fa-check-circle text-6xl text-green-500 mb-4"></i><h3 class="text-xl font-bold text-gray-900">Faculty Member Added Successfully!</h3></div>';
            document.body.appendChild(successIcon);
            
            setTimeout(() => {
                document.body.removeChild(successIcon);
                window.location.reload();
            }, 2000);
        } else {
            showToast(data.message || 'Error adding faculty member', 'error');
            
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
    const modal = document.getElementById('addFacultyModal');
    if (e.target === modal) {
        closeAddFacultyModal();
    }
});
</script>
