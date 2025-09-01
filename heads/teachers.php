


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
        <div class="flex space-x-3">
            <a href="../generate-teacher-qr.php?id=1" target="_blank" 
               class="bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition flex items-center">
                <i class="fas fa-qrcode mr-2"></i>View Sample QR
            </a>
            <a href="../seed-faculty-qr-codes.php" target="_blank" 
               class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition flex items-center">
                <i class="fas fa-magic mr-2"></i>Generate All QR Codes
            </a>
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


