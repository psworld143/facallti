<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/id_encryption.php';

// Check if user is logged in and has human_resource role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'human_resource') {
    header('Location: ../index.php');
    exit();
}

// Pagination settings
$records_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

// Get filter parameters
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Build query conditions
$where_conditions = ["1=1"];
$params = [];
$param_types = "";

if ($search) {
    $where_conditions[] = "(d.name LIKE ? OR d.description LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param]);
    $param_types .= "ss";
}

if ($status_filter) {
    $where_conditions[] = "d.is_active = ?";
    $params[] = $status_filter === 'active' ? 1 : 0;
    $param_types .= "i";
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM departments d $where_clause";
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

// Get departments with pagination
$departments_query = "SELECT d.*, u.first_name, u.last_name as created_by_name 
                     FROM departments d 
                     LEFT JOIN users u ON d.created_by = u.id 
                     $where_clause 
                     ORDER BY d.sort_order, d.name 
                     LIMIT ? OFFSET ?";

// Add pagination parameters
$pagination_params = array_merge($params, [$records_per_page, $offset]);
$pagination_param_types = $param_types . "ii";

$departments_stmt = mysqli_prepare($conn, $departments_query);
if ($departments_stmt && !empty($pagination_params)) {
    mysqli_stmt_bind_param($departments_stmt, $pagination_param_types, ...$pagination_params);
    mysqli_stmt_execute($departments_stmt);
    $departments_result = mysqli_stmt_get_result($departments_stmt);
} else {
    $departments_result = mysqli_query($conn, $departments_query);
}

$departments = [];
while ($row = mysqli_fetch_assoc($departments_result)) {
    $departments[] = $row;
}

// Set page title
$page_title = 'Manage Departments';

// Include the header
include 'includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Manage Departments</h1>
            <p class="text-gray-600">View and manage department information</p>
        </div>
        <div class="flex space-x-3">
            <button onclick="openAddDepartmentModal()" class="bg-gradient-to-r from-seait-orange to-orange-500 text-white px-4 py-2 rounded-lg hover:from-orange-500 hover:to-seait-orange transform transition-all hover:scale-105 hover:shadow-lg font-medium">
                <i class="fas fa-plus mr-2"></i>Add Department
            </button>
            <a href="manage-colleges.php" class="bg-seait-dark text-white px-4 py-2 rounded-lg hover:bg-gray-800 transform transition-all hover:scale-105 font-medium">
                <i class="fas fa-university mr-2"></i>Manage Colleges
            </a>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6 border border-gray-200">
    <form method="GET" id="department-filters" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
            <input type="text" name="search" id="department-search" value="<?php echo htmlspecialchars($search); ?>" 
                   placeholder="Search by name or description..."
                   class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all">
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

<!-- Department Table -->
<div class="bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
        <h3 class="text-lg font-medium text-gray-900">Departments (<?php echo $total_records; ?> found)</h3>
    </div>
    
    <!-- Desktop Table View -->
    <div class="hidden lg:block overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-hr-secondary">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Department</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Description</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Icon</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Sort Order</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Created By</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($departments)): ?>
                    <tr>
                        <td colspan="7" class="px-6 py-4 text-center text-gray-500">No departments found</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($departments as $department): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <div class="w-12 h-12 rounded-lg flex items-center justify-center text-white font-bold text-sm" 
                                             style="background-color: <?php echo $department['color_theme']; ?>">
                                            <i class="<?php echo $department['icon']; ?>"></i>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo $department['name']; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                <div class="max-w-xs truncate">
                                    <?php echo $department['description']; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <i class="<?php echo $department['icon']; ?> text-lg"></i>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo $department['sort_order']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-3 py-1 text-xs rounded-full font-semibold <?php echo $department['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo $department['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo $department['created_by_name'] ? $department['created_by_name'] : 'System'; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="view-department.php?id=<?php echo encrypt_id($department['id']); ?>" 
                                   class="text-seait-orange hover:text-seait-dark font-medium mr-3 transition-colors">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <a href="edit-department.php?id=<?php echo encrypt_id($department['id']); ?>" 
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
        <?php if (empty($departments)): ?>
            <div class="px-6 py-4 text-center text-gray-500">No departments found</div>
        <?php else: ?>
            <div class="divide-y divide-gray-200">
                <?php foreach ($departments as $department): ?>
                    <div class="p-4 hover:bg-gray-50 transition-colors">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="flex-shrink-0">
                                    <div class="w-12 h-12 rounded-lg flex items-center justify-center text-white font-bold text-sm" 
                                         style="background-color: <?php echo $department['color_theme']; ?>">
                                        <i class="<?php echo $department['icon']; ?>"></i>
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-medium text-gray-900 truncate">
                                        <?php echo $department['name']; ?>
                                    </div>
                                    <div class="text-sm text-gray-500 truncate">
                                        <?php echo $department['description']; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="flex flex-col items-end space-y-2">
                                <span class="px-3 py-1 text-xs rounded-full font-semibold <?php echo $department['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo $department['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                                <div class="flex space-x-2">
                                    <a href="view-department.php?id=<?php echo encrypt_id($department['id']); ?>" 
                                       class="text-seait-orange hover:text-seait-dark font-medium text-sm transition-colors">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="edit-department.php?id=<?php echo encrypt_id($department['id']); ?>" 
                                       class="text-seait-dark hover:text-gray-700 font-medium text-sm transition-colors">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3 space-y-1">
                            <div class="text-sm text-gray-500">
                                <span class="font-medium">Sort Order:</span> <?php echo $department['sort_order']; ?>
                            </div>
                            <div class="text-sm text-gray-500">
                                <span class="font-medium">Created By:</span> <?php echo $department['created_by_name'] ? $department['created_by_name'] : 'System'; ?>
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

<!-- Add Department Modal -->
<div id="addDepartmentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-4 mx-auto p-6 border w-full max-w-2xl shadow-2xl rounded-xl bg-white max-h-[95vh] overflow-y-auto">
        <div class="mt-2">
            <div class="flex items-center justify-between mb-6 pb-4 border-b border-gray-200">
                <div>
                    <h3 class="text-2xl font-bold text-gray-900">Add New Department</h3>
                    <p class="text-gray-600 mt-1">Create a new department for the organization</p>
                </div>
                <button onclick="closeAddDepartmentModal()" class="text-gray-400 hover:text-gray-600 p-2 hover:bg-gray-100 rounded-lg transition-colors">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            <form id="addDepartmentForm" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Department Name *</label>
                        <input type="text" name="name" required
                               class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                               placeholder="Enter department name">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Sort Order</label>
                        <input type="number" name="sort_order" min="0"
                               class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                               placeholder="0" value="0">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <textarea name="description" rows="3"
                              class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                              placeholder="Enter department description"></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Icon Class *</label>
                        <input type="text" name="icon" required
                               class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                               placeholder="fas fa-building" value="fas fa-building">
                        <p class="text-xs text-gray-500 mt-1">FontAwesome icon class (e.g., fas fa-building)</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Color Theme</label>
                        <input type="color" name="color_theme"
                               class="w-full h-10 px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                               value="#FF6B35">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select name="is_active" class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>

                <div class="bg-white border-t border-gray-200 mt-6 pt-6">
                    <div class="flex justify-between items-center">
                        <div class="text-sm text-gray-600">
                            <i class="fas fa-info-circle mr-2"></i>
                            Fields marked with * are required
                        </div>
                        <div class="flex space-x-4">
                            <button type="button" onclick="closeAddDepartmentModal()" 
                                    class="px-8 py-3 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors font-medium border border-gray-300">
                                <i class="fas fa-times mr-2"></i>Cancel
                            </button>
                            <button type="submit" 
                                    class="px-8 py-3 bg-gradient-to-r from-seait-orange to-orange-500 text-white rounded-lg hover:from-orange-500 hover:to-seait-orange transform transition-all hover:scale-105 font-medium shadow-lg">
                                <i class="fas fa-plus mr-2"></i>Add Department
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
function openAddDepartmentModal() {
    document.getElementById('addDepartmentModal').classList.remove('hidden');
}

function closeAddDepartmentModal() {
    document.getElementById('addDepartmentModal').classList.add('hidden');
    
    // Reset form
    const form = document.getElementById('addDepartmentForm');
    if (form) {
        form.reset();
        
        // Remove validation styling
        const fields = form.querySelectorAll('input, select, textarea');
        fields.forEach(field => {
            field.classList.remove('border-red-500');
            field.disabled = false;
        });
    }
}

// Form submission with validation
document.getElementById('addDepartmentForm').addEventListener('submit', function(e) {
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
    
    const formData = new FormData(this);
    
    // Show loading state
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding Department...';
    submitBtn.disabled = true;
    
    // Disable all form fields during submission
    const formFields = this.querySelectorAll('input, select, textarea');
    formFields.forEach(field => field.disabled = true);
    
    fetch('add-department.php', {
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
            showToast('Department added successfully!', 'success');
            closeAddDepartmentModal();
            
            // Show success animation
            const successIcon = document.createElement('div');
            successIcon.className = 'fixed inset-0 bg-green-500 bg-opacity-20 flex items-center justify-center z-50';
            successIcon.innerHTML = '<div class="bg-white p-8 rounded-lg shadow-lg text-center"><i class="fas fa-check-circle text-6xl text-green-500 mb-4"></i><h3 class="text-xl font-bold text-gray-900">Department Added Successfully!</h3></div>';
            document.body.appendChild(successIcon);
            
            setTimeout(() => {
                document.body.removeChild(successIcon);
                // Reload page to show new department
                window.location.reload();
            }, 2000);
        } else {
            showToast(data.message || 'Error adding department', 'error');
            
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
    const modal = document.getElementById('addDepartmentModal');
    if (e.target === modal) {
        closeAddDepartmentModal();
    }
});
</script>
