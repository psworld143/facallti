<?php
session_start();
// error_reporting(E_ALL);
// ini_set('display_errors', 1);
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has head role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'head') {
    header('Location: ../index.php');
    exit();
}

// Set page title
$page_title = 'My Teachers';

// Get user information
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$first_name = $_SESSION['first_name'];
$last_name = $_SESSION['last_name'];

// Get head information from heads table
$head_query = "SELECT h.* FROM heads h WHERE h.user_id = ?";
$head_stmt = mysqli_prepare($conn, $head_query);
if (!$head_stmt) {
    die("Error preparing head statement: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($head_stmt, "i", $user_id);
if (!mysqli_stmt_execute($head_stmt)) {
    die("Error executing head statement: " . mysqli_stmt_error($head_stmt));
}

$head_result = mysqli_stmt_get_result($head_stmt);
if (!$head_result) {
    die("Error getting head result: " . mysqli_stmt_error($head_stmt));
}

$head_info = mysqli_fetch_assoc($head_result);

// Check if head info exists
if (!$head_info) {
    // Redirect to login or show error
    header('Location: ../index.php?error=unauthorized');
    exit();
}

// Get filter parameters
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Build the main query with filters
// Use exact department matching only
$head_department = $head_info['department'];
$params = [$head_department];
$param_types = "s";

$where_conditions = ["f.department = ?"];

if ($search) {
    $where_conditions[] = "(f.first_name LIKE ? OR f.last_name LIKE ? OR f.email LIKE ? OR f.qrcode LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $param_types .= "ssss";
}

if ($status_filter) {
    $where_conditions[] = "f.is_active = ?";
    $params[] = $status_filter === 'active' ? 1 : 0;
    $param_types .= "i";
} else {
    // Default to showing only active teachers if no status filter is applied
    $where_conditions[] = "f.is_active = ?";
    $params[] = 1;
    $param_types .= "i";
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get teachers
$teachers_query = "SELECT f.*, 
                   (SELECT COUNT(*) FROM evaluation_sessions es WHERE es.evaluatee_id = f.id AND es.evaluatee_type = 'teacher' AND es.evaluator_id = ? AND es.evaluator_type = 'head') as evaluation_count,
                   (SELECT COUNT(*) FROM evaluation_sessions es WHERE es.evaluatee_id = f.id AND es.evaluatee_type = 'teacher' AND es.evaluator_id = ? AND es.evaluator_type = 'head' AND es.status = 'completed') as completed_evaluations
                   FROM faculty f
                   $where_clause
                   ORDER BY f.last_name, f.first_name";



$teachers_stmt = mysqli_prepare($conn, $teachers_query);
if (!$teachers_stmt) {
    die("Error preparing statement: " . mysqli_error($conn));
}

// Fix parameter binding order: user_id parameters first (for subqueries), then department parameters
$all_params = array_merge([$user_id, $user_id], $params);
$all_param_types = "ii" . $param_types;

mysqli_stmt_bind_param($teachers_stmt, $all_param_types, ...$all_params);

if (!mysqli_stmt_execute($teachers_stmt)) {
    die("Error executing statement: " . mysqli_stmt_error($teachers_stmt));
}

$teachers_result = mysqli_stmt_get_result($teachers_stmt);
if (!$teachers_result) {
    die("Error getting result: " . mysqli_stmt_error($teachers_stmt));
}

$teachers = [];
while ($row = mysqli_fetch_assoc($teachers_result)) {
    $teachers[] = $row;
}



// Include the header
include 'includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">My Teachers</h1>
            <p class="text-gray-600">Teachers under <?php echo $head_info['department']; ?> department</p>
        </div>
        <div>
            <button onclick="openAddFacultyModal()" class="bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition-all duration-200 transform hover:scale-105 hover:shadow-lg">
                <i class="fas fa-plus mr-2"></i>Add Faculty
            </button>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow-sm p-6 mb-6">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                   placeholder="Search by name, email, or QR code..."
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
            <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange">
                <option value="">All Status</option>
                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
            </select>
        </div>
        <div class="flex items-end">
            <button type="submit" class="w-full bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition">
                <i class="fas fa-search mr-2"></i>Search
            </button>
        </div>
    </form>
</div>

<!-- Teachers Table -->
<div class="bg-white rounded-lg shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-medium text-gray-900">Teachers (<?php echo count($teachers); ?> found)</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teacher</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Position</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">QR Code</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Evaluations</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($teachers)): ?>
                    <tr>
                        <td colspan="7" class="px-6 py-4 text-center text-gray-500">No teachers found</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($teachers as $teacher): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <div class="h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center">
                                            <span class="text-sm font-medium text-gray-700">
                                                <?php echo strtoupper(substr($teacher['first_name'], 0, 1) . substr($teacher['last_name'], 0, 1)); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo $teacher['first_name'] . ' ' . $teacher['last_name']; ?>
                                        </div>
                                        <div class="text-sm text-gray-500"><?php echo $teacher['email']; ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo $teacher['position']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if (!empty($teacher['qrcode'])): ?>
                                    <div class="flex items-center space-x-2">
                                        <span class="text-sm font-mono text-gray-900 bg-gray-100 px-2 py-1 rounded">
                                            <?php echo htmlspecialchars($teacher['qrcode']); ?>
                                        </span>
                                        <a href="../generate-teacher-qr.php?id=<?php echo $teacher['id']; ?>" 
                                           target="_blank" 
                                           class="text-seait-orange hover:text-orange-600" 
                                           title="View QR Code">
                                            <i class="fas fa-qrcode"></i>
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <span class="text-sm text-gray-400">No QR Code</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo $teacher['email']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    <span class="font-medium"><?php echo $teacher['evaluation_count']; ?></span> total
                                </div>
                                <div class="text-sm text-gray-500">
                                    <?php echo $teacher['completed_evaluations']; ?> completed
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs rounded-full <?php echo $teacher['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo $teacher['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex space-x-3">
                                    <a href="view-evaluation.php?faculty_id=<?php echo $teacher['id']; ?>" 
                                       class="text-seait-orange hover:text-orange-600">
                                        <i class="fas fa-eye"></i> View Evaluations
                                    </a>
                                    <?php if (!empty($teacher['qrcode'])): ?>
                                        <a href="../generate-teacher-qr.php?id=<?php echo $teacher['id']; ?>" 
                                           target="_blank" 
                                           class="text-blue-600 hover:text-blue-800">
                                            <i class="fas fa-qrcode"></i> QR Code
                                        </a>
                                    <?php else: ?>
                                        <span class="text-gray-400">
                                            <i class="fas fa-qrcode"></i> No QR Code
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Faculty Modal -->
<div id="addFacultyModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-seait-dark">Add New Faculty</h3>
                <button onclick="closeAddFacultyModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        
        <form id="addFacultyForm" method="POST" action="add-faculty.php" class="p-6">
            <input type="hidden" name="department" value="<?php echo htmlspecialchars($head_info['department']); ?>">
            
            <div class="space-y-4">
                <div>
                    <label for="qrcode" class="block text-sm font-medium text-gray-700 mb-2">Faculty ID (QR Code) *</label>
                    <input type="text" id="qrcode" name="qrcode" required 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent transition-all duration-200"
                           placeholder="e.g., 2025-0008">
                    <p class="text-xs text-gray-500 mt-1">Unique identifier for the faculty member</p>
                </div>
                
                <div>
                    <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2">First Name *</label>
                    <input type="text" id="first_name" name="first_name" required 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent transition-all duration-200"
                           placeholder="Enter first name">
                </div>
                
                <div>
                    <label for="last_name" class="block text-sm font-medium text-gray-700 mb-2">Last Name *</label>
                    <input type="text" id="last_name" name="last_name" required 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent transition-all duration-200"
                           placeholder="Enter last name">
                </div>
                
                <div>
                    <label for="middle_name" class="block text-sm font-medium text-gray-700 mb-2">Middle Name</label>
                    <input type="text" id="middle_name" name="middle_name" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent transition-all duration-200"
                           placeholder="Enter middle name (optional)">
                </div>
                
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle text-blue-500 mt-0.5 mr-3"></i>
                        <div class="text-sm text-blue-700">
                            <p class="font-medium">Note:</p>
                            <p>Other details (email, position, etc.) will be filled with sample data. The HR department will update them later.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end space-x-3 mt-6">
                <button type="button" onclick="closeAddFacultyModal()" 
                        class="px-4 py-2 text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                    Cancel
                </button>
                <button type="submit" 
                        class="px-4 py-2 bg-seait-orange text-white rounded-lg hover:bg-orange-600 transition-all duration-200 transform hover:scale-105">
                    <i class="fas fa-plus mr-2"></i>Add Faculty
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddFacultyModal() {
    document.getElementById('addFacultyModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeAddFacultyModal() {
    document.getElementById('addFacultyModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
    document.getElementById('addFacultyForm').reset();
}

// Close modal when clicking outside
document.getElementById('addFacultyModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeAddFacultyModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && !document.getElementById('addFacultyModal').classList.contains('hidden')) {
        closeAddFacultyModal();
    }
});

// Auto-generate QR code suggestion
document.addEventListener('DOMContentLoaded', function() {
    const qrcodeInput = document.getElementById('qrcode');
    const currentYear = new Date().getFullYear();
    
    // Generate next available QR code
    fetch('get-next-qrcode.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                qrcodeInput.placeholder = `e.g., ${data.next_qrcode}`;
            }
        })
        .catch(error => console.log('Could not fetch next QR code'));
});
</script>

<?php include 'includes/footer.php'; ?>
