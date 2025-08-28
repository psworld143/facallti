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

// Set page title
$page_title = 'Manage Colleges';

// Pagination settings
$records_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

// Get filter parameters
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Build query conditions
$where_conditions = ["1=1"]; // Start with a true condition
$params = [];
$param_types = "";

if ($search) {
    $where_conditions[] = "(name LIKE ? OR description LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param]);
    $param_types .= "ss";
}

if ($status_filter) {
    $where_conditions[] = "is_active = ?";
    $params[] = $status_filter === 'active' ? 1 : 0;
    $param_types .= "i";
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM colleges c $where_clause";
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

// Get colleges with pagination
$colleges_query = "SELECT c.*, 
                     (SELECT COUNT(*) FROM faculty f WHERE f.department = c.name AND f.is_active = 1) as faculty_count
                     FROM colleges c 
                     $where_clause 
                     ORDER BY c.name LIMIT ? OFFSET ?";

// Add pagination parameters
$pagination_params = array_merge($params, [$records_per_page, $offset]);
$pagination_param_types = $param_types . "ii";

$colleges_stmt = mysqli_prepare($conn, $colleges_query);
if ($colleges_stmt && !empty($pagination_params)) {
    mysqli_stmt_bind_param($colleges_stmt, $pagination_param_types, ...$pagination_params);
    mysqli_stmt_execute($colleges_stmt);
    $colleges_result = mysqli_stmt_get_result($colleges_stmt);
} else {
    $colleges_result = mysqli_query($conn, $colleges_query);
}

$colleges = [];
while ($row = mysqli_fetch_assoc($colleges_result)) {
    $colleges[] = $row;
}

// Include the header
include 'includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Manage Colleges</h1>
            <p class="text-gray-600">View and manage organizational colleges</p>
        </div>
        <button onclick="openAddCollegeModal()" class="bg-gradient-to-r from-seait-orange to-orange-500 text-white px-4 py-2 rounded-lg hover:from-orange-500 hover:to-seait-orange transform transition-all hover:scale-105 hover:shadow-lg font-medium">
            <i class="fas fa-plus mr-2"></i>Add College
        </button>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6 border border-gray-200">
    <form method="GET" id="college-filters" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
            <input type="text" name="search" id="college-search" value="<?php echo htmlspecialchars($search); ?>" 
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

<!-- Colleges Table -->
<div class="bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
        <h3 class="text-lg font-medium text-gray-900">Colleges (<?php echo $total_records; ?> found)</h3>
    </div>
    
    <!-- Desktop Table View -->
    <div class="hidden lg:block overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 hr-table">
            <thead class="bg-seait-dark">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">College</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Short Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Faculty Count</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($colleges)): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-gray-500">No colleges found</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($colleges as $college): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-blue-600 rounded-full flex items-center justify-center text-white font-bold text-sm">
                                            <?php echo strtoupper(substr($college['name'], 0, 2)); ?>
                                        </div>
                                    </div>
                                    <div class="ml-4 min-w-0 flex-1">
                                        <div class="text-sm font-semibold text-gray-900 mb-1">
                                            <?php echo htmlspecialchars($college['name']); ?>
                                        </div>

                                    </div>
                                </div>
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo htmlspecialchars($college['short_name']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    <?php echo $college['faculty_count']; ?> faculty
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-3 py-1 text-xs rounded-full font-semibold <?php echo $college['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo $college['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm font-medium">
                                <div class="flex items-center space-x-2">
                                    <button onclick="viewCollege('<?php echo encrypt_id($college['id']); ?>')" 
                                           class="inline-flex items-center px-3 py-1.5 text-seait-orange hover:text-seait-dark hover:bg-orange-50 rounded-md transition-colors font-medium">
                                        <i class="fas fa-eye mr-1.5"></i> View
                                    </button>
                                    <button onclick="editCollege('<?php echo encrypt_id($college['id']); ?>')" 
                                           class="inline-flex items-center px-3 py-1.5 text-blue-500 hover:text-blue-700 hover:bg-blue-50 rounded-md transition-colors font-medium">
                                        <i class="fas fa-edit mr-1.5"></i> Edit
                                    </button>
                                    <button onclick="toggleCollegeStatus('<?php echo encrypt_id($college['id']); ?>', <?php echo $college['is_active'] ? 0 : 1; ?>)" 
                                           class="inline-flex items-center px-3 py-1.5 text-<?php echo $college['is_active'] ? 'red' : 'green'; ?>-500 hover:text-<?php echo $college['is_active'] ? 'red' : 'green'; ?>-700 hover:bg-<?php echo $college['is_active'] ? 'red' : 'green'; ?>-50 rounded-md transition-colors font-medium">
                                        <i class="fas fa-<?php echo $college['is_active'] ? 'ban' : 'check'; ?> mr-1.5"></i> 
                                        <?php echo $college['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Mobile/Tablet Card View -->
    <div class="lg:hidden">
        <?php if (empty($colleges)): ?>
            <div class="px-6 py-4 text-center text-gray-500">No colleges found</div>
        <?php else: ?>
            <div class="divide-y divide-gray-200">
                <?php foreach ($colleges as $college): ?>
                    <div class="p-6 hover:bg-gray-50 transition-colors border-b border-gray-100 last:border-b-0">
                        <!-- Header Row -->
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center space-x-4">
                                <div class="flex-shrink-0">
                                    <div class="w-14 h-14 bg-gradient-to-r from-blue-500 to-blue-600 rounded-full flex items-center justify-center text-white font-bold text-lg">
                                        <?php echo strtoupper(substr($college['name'], 0, 2)); ?>
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h3 class="text-lg font-semibold text-gray-900 mb-1">
                                        <?php echo htmlspecialchars($college['name']); ?>
                                    </h3>
                                    <p class="text-sm text-gray-600 font-medium">
                                        <?php echo htmlspecialchars($college['short_name']); ?>
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-3">
                                <span class="px-3 py-1 text-xs rounded-full font-semibold <?php echo $college['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo $college['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                                <div class="flex space-x-1">
                                    <button onclick="viewCollege('<?php echo encrypt_id($college['id']); ?>')" 
                                           class="p-2 text-seait-orange hover:text-seait-dark hover:bg-orange-50 rounded-lg transition-colors" 
                                           title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button onclick="editCollege('<?php echo encrypt_id($college['id']); ?>')" 
                                           class="p-2 text-blue-500 hover:text-blue-700 hover:bg-blue-50 rounded-lg transition-colors" 
                                           title="Edit College">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="toggleCollegeStatus('<?php echo encrypt_id($college['id']); ?>', <?php echo $college['is_active'] ? 0 : 1; ?>)" 
                                           class="p-2 text-<?php echo $college['is_active'] ? 'red' : 'green'; ?>-500 hover:text-<?php echo $college['is_active'] ? 'red' : 'green'; ?>-700 hover:bg-<?php echo $college['is_active'] ? 'red' : 'green'; ?>-50 rounded-lg transition-colors" 
                                           title="<?php echo $college['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                        <i class="fas fa-<?php echo $college['is_active'] ? 'ban' : 'check'; ?>"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Content Row -->
                        <div class="mt-4">
                            <div class="flex items-center space-x-2">
                                <div class="w-2 h-2 bg-blue-500 rounded-full"></div>
                                <span class="text-sm font-medium text-gray-700">Faculty Count</span>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    <?php echo $college['faculty_count']; ?> faculty
                                </span>
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

<!-- Add College Modal -->
<div id="addCollegeModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Add New College</h3>
                <button onclick="closeAddCollegeModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <form id="addCollegeForm" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">College Name</label>
                    <input type="text" name="college_name" required
                           class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                           placeholder="Enter college name">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Short Name</label>
                    <input type="text" name="short_name" required
                           class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                           placeholder="Enter short name (e.g., CICT)">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <textarea name="description" rows="3"
                              class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                              placeholder="Enter college description"></textarea>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeAddCollegeModal()" 
                            class="px-4 py-2 text-gray-600 bg-gray-200 rounded-lg hover:bg-gray-300 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-gradient-to-r from-seait-orange to-orange-500 text-white rounded-lg hover:from-orange-500 hover:to-seait-orange transform transition-all hover:scale-105">
                        Add College
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View College Modal -->
<div id="viewCollegeModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">College Details</h3>
                <button onclick="closeViewCollegeModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div id="collegeDetails" class="space-y-4">
                <!-- College details will be loaded here -->
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<!-- Custom JavaScript for Department Management -->
<script>
// Modal functions
function openAddCollegeModal() {
    document.getElementById('addCollegeModal').classList.remove('hidden');
}

function closeAddCollegeModal() {
    document.getElementById('addCollegeModal').classList.add('hidden');
    document.getElementById('addCollegeForm').reset();
}

function closeViewCollegeModal() {
    document.getElementById('viewCollegeModal').classList.add('hidden');
}

// College management functions
function viewCollege(collegeId) {
    // Load college details via AJAX
    fetch(`get-college-details.php?id=${encodeURIComponent(collegeId)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const college = data.college;
                document.getElementById('collegeDetails').innerHTML = `
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">College Name</label>
                            <p class="text-gray-900">${college.name}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Short Name</label>
                            <p class="text-gray-900">${college.short_name}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Description</label>
                            <p class="text-gray-900">${college.description || 'No description available'}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Status</label>
                            <span class="px-3 py-1 text-xs rounded-full font-semibold ${college.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                                ${college.is_active ? 'Active' : 'Inactive'}
                            </span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Created Date</label>
                            <p class="text-gray-900">${new Date(college.created_at).toLocaleDateString()}</p>
                        </div>
                    </div>
                `;
                document.getElementById('viewCollegeModal').classList.remove('hidden');
            } else {
                showToast('Error loading college details', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error loading college details', 'error');
        });
}

function editCollege(collegeId) {
    // Redirect to edit page or open edit modal
    window.location.href = `edit-college.php?id=${encodeURIComponent(collegeId)}`;
}

function toggleCollegeStatus(collegeId, newStatus) {
    const action = newStatus ? 'activate' : 'deactivate';
    if (confirm(`Are you sure you want to ${action} this college?`)) {
        fetch('toggle-college-status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                college_id: collegeId,
                status: newStatus
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(`College ${action}d successfully`, 'success');
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                showToast(data.message || 'Error updating college status', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error updating college status', 'error');
        });
    }
}

// Form submission
document.getElementById('addCollegeForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('add-college.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('College added successfully', 'success');
            closeAddCollegeModal();
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showToast(data.message || 'Error adding college', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error adding college', 'error');
    });
});

// Toast notification function
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg transform transition-all duration-300 translate-x-full ${type === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white'}`;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.remove('translate-x-full');
    }, 100);
    
    setTimeout(() => {
        toast.classList.add('translate-x-full');
        setTimeout(() => {
            document.body.removeChild(toast);
        }, 300);
    }, 3000);
}

// Close modals when clicking outside
window.addEventListener('click', function(e) {
    const addModal = document.getElementById('addCollegeModal');
    const viewModal = document.getElementById('viewCollegeModal');
    
    if (e.target === addModal) {
        closeAddCollegeModal();
    }
    if (e.target === viewModal) {
        closeViewCollegeModal();
    }
});
</script>
