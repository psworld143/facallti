<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

// Set page title
$page_title = 'Error Logs';

// Pagination settings
$records_per_page = 20;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

// Get filter parameters
$error_type_filter = isset($_GET['error_type']) ? $_GET['error_type'] : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';

// Build query conditions
$where_conditions = ["1=1"];
$params = [];
$param_types = "";

if ($error_type_filter) {
    $where_conditions[] = "error_type = ?";
    $params[] = $error_type_filter;
    $param_types .= "s";
}

if ($date_filter) {
    $where_conditions[] = "DATE(created_at) = ?";
    $params[] = $date_filter;
    $param_types .= "s";
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM error_logs $where_clause";
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

// Get error logs with pagination
$logs_query = "SELECT * FROM error_logs $where_clause ORDER BY created_at DESC LIMIT ? OFFSET ?";

// Add pagination parameters
$pagination_params = array_merge($params, [$records_per_page, $offset]);
$pagination_param_types = $param_types . "ii";

$logs_stmt = mysqli_prepare($conn, $logs_query);
if ($logs_stmt && !empty($pagination_params)) {
    mysqli_stmt_bind_param($logs_stmt, $pagination_param_types, ...$pagination_params);
    mysqli_stmt_execute($logs_stmt);
    $logs_result = mysqli_stmt_get_result($logs_stmt);
} else {
    $logs_result = mysqli_query($conn, $logs_query);
}

$error_logs = [];
while ($row = mysqli_fetch_assoc($logs_result)) {
    $error_logs[] = $row;
}

// Get unique error types for filter
$types_query = "SELECT DISTINCT error_type FROM error_logs ORDER BY error_type";
$types_result = mysqli_query($conn, $types_query);
$error_types = [];
while ($row = mysqli_fetch_assoc($types_result)) {
    $error_types[] = $row['error_type'];
}

// Include the header
include 'includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Error Logs</h1>
            <p class="text-gray-600">Monitor and analyze website errors</p>
        </div>
        <div class="flex space-x-3">
            <button onclick="exportErrorLogs()" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors">
                <i class="fas fa-download mr-2"></i>Export
            </button>
            <button onclick="clearOldLogs()" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors">
                <i class="fas fa-trash mr-2"></i>Clear Old Logs
            </button>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <?php
    // Get statistics
    $stats_query = "SELECT 
        COUNT(*) as total_errors,
        COUNT(CASE WHEN error_type = '404' THEN 1 END) as not_found_errors,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as last_24h,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as last_7days
        FROM error_logs";
    $stats_result = mysqli_query($conn, $stats_query);
    $stats = mysqli_fetch_assoc($stats_result);
    ?>
    
    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mr-4">
                <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['total_errors']); ?></div>
                <div class="text-sm text-gray-600">Total Errors</div>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center mr-4">
                <i class="fas fa-search text-orange-600 text-xl"></i>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['not_found_errors']); ?></div>
                <div class="text-sm text-gray-600">404 Errors</div>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mr-4">
                <i class="fas fa-clock text-blue-600 text-xl"></i>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['last_24h']); ?></div>
                <div class="text-sm text-gray-600">Last 24 Hours</div>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mr-4">
                <i class="fas fa-calendar text-green-600 text-xl"></i>
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['last_7days']); ?></div>
                <div class="text-sm text-gray-600">Last 7 Days</div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6 border border-gray-200">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Error Type</label>
            <select name="error_type" class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all">
                <option value="">All Error Types</option>
                <?php foreach ($error_types as $type): ?>
                    <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $error_type_filter === $type ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($type); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Date</label>
            <input type="date" name="date" value="<?php echo htmlspecialchars($date_filter); ?>"
                   class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all">
        </div>
        <div class="flex items-end">
            <button type="submit" class="w-full bg-gradient-to-r from-seait-orange to-orange-500 text-white px-4 py-2 rounded-lg hover:from-orange-500 hover:to-seait-orange transform transition-all hover:scale-105 hover:shadow-lg font-medium">
                <i class="fas fa-search mr-2"></i>Filter
            </button>
        </div>
    </form>
</div>

<!-- Error Logs Table -->
<div class="bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
        <h3 class="text-lg font-medium text-gray-900">Error Logs (<?php echo $total_records; ?> found)</h3>
    </div>
    
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Error Type</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Requested URL</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP Address</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($error_logs)): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-gray-500">No error logs found</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($error_logs as $log): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-3 py-1 text-xs rounded-full font-semibold <?php echo $log['error_type'] === '404' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                    <?php echo htmlspecialchars($log['error_type']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900 font-medium"><?php echo htmlspecialchars(substr($log['requested_url'], 0, 50)); ?></div>
                                <?php if (strlen($log['requested_url']) > 50): ?>
                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($log['requested_url']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($log['ip_address']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo date('M j, Y g:i A', strtotime($log['created_at'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button onclick="viewErrorDetails(<?php echo $log['id']; ?>)" 
                                       class="text-seait-orange hover:text-seait-dark font-medium mr-3 transition-colors">
                                    <i class="fas fa-eye"></i> View
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
        <div class="flex-1 flex justify-between sm:hidden">
            <?php if ($current_page > 1): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page - 1])); ?>" 
                   class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    Previous
                </a>
            <?php endif; ?>
            <?php if ($current_page < $total_pages): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page + 1])); ?>" 
                   class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    Next
                </a>
            <?php endif; ?>
        </div>
        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
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
                    
                    for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                           class="relative inline-flex items-center px-4 py-2 border text-sm font-medium <?php echo $i === $current_page ? 'z-10 bg-seait-orange border-seait-orange text-white' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
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

<!-- Error Details Modal -->
<div id="errorDetailsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-4xl shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Error Details</h3>
                <button onclick="closeErrorDetailsModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div id="errorDetails" class="space-y-4">
                <!-- Error details will be loaded here -->
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
function viewErrorDetails(errorId) {
    // Load error details via AJAX
    fetch(`get-error-details.php?id=${errorId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const error = data.error;
                document.getElementById('errorDetails').innerHTML = `
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Error Type</label>
                            <p class="text-gray-900">${error.error_type}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Date & Time</label>
                            <p class="text-gray-900">${new Date(error.created_at).toLocaleString()}</p>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Requested URL</label>
                            <p class="text-gray-900 break-all">${error.requested_url}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">IP Address</label>
                            <p class="text-gray-900">${error.ip_address}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">User Agent</label>
                            <p class="text-gray-900 text-sm">${error.user_agent}</p>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Referrer</label>
                            <p class="text-gray-900 break-all">${error.referrer || 'Direct Access'}</p>
                        </div>
                    </div>
                `;
                document.getElementById('errorDetailsModal').classList.remove('hidden');
            } else {
                showToast('Error loading error details', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error loading error details', 'error');
        });
}

function closeErrorDetailsModal() {
    document.getElementById('errorDetailsModal').classList.add('hidden');
}

function exportErrorLogs() {
    const params = new URLSearchParams(window.location.search);
    window.location.href = `export-error-logs.php?${params.toString()}`;
}

function clearOldLogs() {
    showConfirmModal('Are you sure you want to clear error logs older than 30 days? This action cannot be undone.', function() {
        fetch('clear-old-logs.php', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Old logs cleared successfully', 'success');
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                showToast(data.message || 'Error clearing logs', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error clearing logs', 'error');
        });
    });
}

// Close modal when clicking outside
window.addEventListener('click', function(e) {
    const modal = document.getElementById('errorDetailsModal');
    if (e.target === modal) {
        closeErrorDetailsModal();
    }
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

// Modal functions
function showConfirmModal(message, onConfirm) {
    document.getElementById('confirmMessage').textContent = message;
    document.getElementById('confirmModal').classList.remove('hidden');
    
    const confirmBtn = document.getElementById('confirmActionBtn');
    confirmBtn.onclick = function() {
        closeConfirmModal();
        onConfirm();
    };
}

function closeConfirmModal() {
    document.getElementById('confirmModal').classList.add('hidden');
}

// Close confirmation modal when clicking outside
document.addEventListener('DOMContentLoaded', function() {
    const confirmModal = document.getElementById('confirmModal');
    if (confirmModal) {
        confirmModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeConfirmModal();
            }
        });
    }
});
</script>

<!-- Confirmation Modal -->
<div id="confirmModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-md w-full p-6">
        <div class="text-center mb-6">
            <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
            </div>
            <h3 class="text-lg font-semibold text-gray-900 mb-2">Confirm Action</h3>
            <p class="text-gray-600 text-sm" id="confirmMessage">Are you sure you want to proceed?</p>
        </div>
        
        <div class="flex justify-center space-x-3">
            <button onclick="closeConfirmModal()"
                    class="px-6 py-3 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200">
                <i class="fas fa-times mr-2"></i>Cancel
            </button>
            <button id="confirmActionBtn"
                    class="px-6 py-3 bg-gradient-to-r from-red-500 to-red-600 text-white rounded-lg hover:from-red-600 hover:to-red-700 transition-all duration-200 font-semibold">
                <i class="fas fa-check mr-2"></i>Confirm
            </button>
        </div>
    </div>
</div>
