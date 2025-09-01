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



// Handle success and error messages
$message = '';
$message_type = '';

if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'faculty_added':
            $faculty_name = isset($_GET['name']) ? htmlspecialchars($_GET['name']) : 'Faculty member';
            $message = "Faculty member '{$faculty_name}' has been successfully added to your department!";
            $message_type = 'success';
            break;
        case 'faculty_updated':
            $faculty_name = isset($_GET['name']) ? htmlspecialchars($_GET['name']) : 'Faculty member';
            $message = "Faculty member '{$faculty_name}' has been successfully updated!";
            $message_type = 'success';
            break;
    }
}

if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'missing_fields':
            $message = "Please fill in all required fields (QR Code, First Name, Last Name).";
            $message_type = 'error';
            break;
        case 'qrcode_exists':
            $message = "This QR Code already exists. Please use a different QR Code.";
            $message_type = 'error';
            break;
        case 'unauthorized_department':
            $message = "You can only add faculty to your own department.";
            $message_type = 'error';
            break;
        case 'database_error':
            $message = "There was an error adding the faculty member. Please try again.";
            $message_type = 'error';
            break;
        case 'unauthorized':
            $message = "You are not authorized to perform this action.";
            $message_type = 'error';
            break;
        case 'faculty_deactivated':
            $faculty_name = isset($_GET['name']) ? htmlspecialchars($_GET['name']) : 'Faculty member';
            $message = "Faculty member '{$faculty_name}' has been deactivated successfully.";
            $message_type = 'success';
            break;
        case 'faculty_reactivated':
            $faculty_name = isset($_GET['name']) ? htmlspecialchars($_GET['name']) : 'Faculty member';
            $message = "Faculty member '{$faculty_name}' has been reactivated successfully.";
            $message_type = 'success';
            break;
        case 'faculty_not_found':
            $message = "Faculty member not found.";
            $message_type = 'error';
            break;
        case 'status_update_error':
            $message = "There was an error updating the faculty status. Please try again.";
            $message_type = 'error';
            break;
        case 'invalid_action':
            $message = "Invalid action specified.";
            $message_type = 'error';
            break;
        case 'already_active':
            $faculty_name = isset($_GET['name']) ? htmlspecialchars($_GET['name']) : 'Faculty member';
            $message = "Faculty member '{$faculty_name}' is already active.";
            $message_type = 'error';
            break;
        case 'already_inactive':
            $faculty_name = isset($_GET['name']) ? htmlspecialchars($_GET['name']) : 'Faculty member';
            $message = "Faculty member '{$faculty_name}' is already inactive.";
            $message_type = 'error';
            break;
        case 'invalid_faculty':
            $message = "Invalid faculty member specified.";
            $message_type = 'error';
            break;
        case 'faculty_not_found':
            $message = "Faculty member not found or you don't have permission to edit them.";
            $message_type = 'error';
            break;
    }
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

<?php if ($message): ?>
    <div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'; ?>">
        <div class="flex items-center">
            <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-3"></i>
            <?php echo $message; ?>
        </div>
    </div>
<?php endif; ?>

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
                                        <?php if (!empty($teacher['image_url']) && file_exists('../' . $teacher['image_url'])): ?>
                                            <img class="h-10 w-10 rounded-full object-cover" 
                                                 src="../<?php echo htmlspecialchars($teacher['image_url']); ?>" 
                                                 alt="<?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>">
                                        <?php else: ?>
                                            <div class="h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center">
                                                <span class="text-sm font-medium text-gray-700">
                                                    <?php echo strtoupper(substr($teacher['first_name'], 0, 1) . substr($teacher['last_name'], 0, 1)); ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
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
                                       class="inline-flex items-center justify-center w-10 h-10 bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 transition-all duration-200 transform hover:scale-105"
                                       title="View Evaluations">
                                        <i class="fas fa-eye text-lg font-bold"></i>
                                    </a>
                                    
                                    <a href="edit-faculty.php?id=<?php echo $teacher['id']; ?>" 
                                       class="inline-flex items-center justify-center w-10 h-10 bg-yellow-50 text-yellow-700 rounded-lg hover:bg-yellow-100 transition-all duration-200 transform hover:scale-105"
                                       title="Edit Faculty">
                                        <i class="fas fa-edit text-lg font-bold"></i>
                                    </a>
                                    
                                    <?php if (!empty($teacher['qrcode'])): ?>
                                        <a href="../generate-teacher-qr.php?id=<?php echo $teacher['id']; ?>" 
                                           target="_blank" 
                                           class="inline-flex items-center justify-center w-10 h-10 bg-purple-50 text-purple-700 rounded-lg hover:bg-purple-100 transition-all duration-200 transform hover:scale-105"
                                           title="View QR Code">
                                            <i class="fas fa-qrcode text-lg font-bold"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="inline-flex items-center justify-center w-10 h-10 bg-gray-50 text-gray-400 rounded-lg cursor-not-allowed"
                                              title="No QR Code Available">
                                            <i class="fas fa-qrcode text-lg font-bold"></i>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if ($teacher['is_active']): ?>
                                        <button onclick="confirmDeactivate(<?php echo $teacher['id']; ?>, '<?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name'], ENT_QUOTES); ?>')" 
                                                class="inline-flex items-center justify-center w-10 h-10 bg-red-50 text-red-700 rounded-lg hover:bg-red-100 transition-all duration-200 transform hover:scale-105"
                                                title="Deactivate Faculty">
                                            <i class="fas fa-user-slash text-lg font-bold"></i>
                                        </button>
                                    <?php else: ?>
                                        <button onclick="confirmReactivate(<?php echo $teacher['id']; ?>, '<?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name'], ENT_QUOTES); ?>')" 
                                                class="inline-flex items-center justify-center w-10 h-10 bg-green-50 text-green-700 rounded-lg hover:bg-green-100 transition-all duration-200 transform hover:scale-105"
                                                title="Reactivate Faculty">
                                            <i class="fas fa-user-check text-lg font-bold"></i>
                                        </button>
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
        
        <form id="addFacultyForm" method="POST" action="add-faculty.php" enctype="multipart/form-data" class="p-6">
            <input type="hidden" name="department" value="<?php echo htmlspecialchars($head_info['department']); ?>">
            
            <div class="space-y-4">
                <!-- Faculty Photo Upload -->
                <div class="text-center">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Faculty Photo</label>
                    <div class="flex flex-col items-center space-y-3">
                        <!-- Photo Preview Area -->
                        <div id="photoPreview" class="w-32 h-32 rounded-full bg-gray-200 border-2 border-dashed border-gray-300 flex items-center justify-center overflow-hidden">
                            <i class="fas fa-camera text-gray-400 text-xl"></i>
                        </div>
                        
                        <!-- Camera View (Hidden by default) -->
                        <div id="cameraView" class="hidden w-full max-w-sm">
                            <video id="cameraVideo" class="w-full h-48 bg-gray-900 rounded-lg" autoplay playsinline></video>
                            <canvas id="cameraCanvas" class="hidden"></canvas>
                            
                            <div id="cameraError" class="hidden text-red-600 text-sm mt-2 p-2 bg-red-50 rounded-lg">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                <span id="cameraErrorMessage">Unable to access camera</span>
                            </div>
                            
                            <!-- Camera compatibility note -->
                            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-2 mt-2">
                                <div class="flex items-start">
                                    <i class="fas fa-info-circle text-yellow-600 mt-0.5 mr-2 text-sm"></i>
                                    <div class="text-xs text-yellow-700">
                                        <p><strong>Note:</strong> Camera access works best on HTTPS. If you're on HTTP, you may need to allow camera permissions manually.</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex justify-center space-x-2 mt-3">
                                <button type="button" id="cancelCamera" class="px-3 py-1 text-gray-600 bg-gray-100 rounded-lg text-sm hover:bg-gray-200 transition-colors">
                                    <i class="fas fa-times mr-1"></i>Cancel
                                </button>
                                <button type="button" id="capturePhoto" class="px-3 py-1 bg-green-500 text-white rounded-lg text-sm hover:bg-green-600 transition-colors">
                                    <i class="fas fa-camera mr-1"></i>Capture
                                </button>
                            </div>
                        </div>
                        
                        <!-- Photo Controls -->
                        <div id="photoControls" class="flex flex-wrap justify-center gap-2">
                            <label for="faculty_photo" class="cursor-pointer bg-blue-500 text-white px-3 py-1 rounded-lg text-sm hover:bg-blue-600 transition-colors">
                                <i class="fas fa-upload mr-1"></i>Choose Photo
                            </label>
                            <button type="button" id="takePhoto" class="bg-green-500 text-white px-3 py-1 rounded-lg text-sm hover:bg-green-600 transition-colors">
                                <i class="fas fa-camera mr-1"></i>Take Photo
                            </button>
                            <button type="button" id="removePhoto" class="bg-red-500 text-white px-3 py-1 rounded-lg text-sm hover:bg-red-600 transition-colors hidden">
                                <i class="fas fa-trash mr-1"></i>Remove
                            </button>
                        </div>
                        
                        <input type="file" id="faculty_photo" name="faculty_photo" accept="image/*" class="hidden">
                    <input type="hidden" id="captured_photo" name="captured_photo">
                        <p class="text-xs text-gray-500">Optional. Max 2MB. JPG, PNG, GIF allowed.</p>
                    </div>
                </div>
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

<!-- Confirmation Modal -->
<div id="confirmationModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 transform transition-all duration-300 scale-95" id="confirmationModalContent">
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div id="confirmationIcon" class="w-10 h-10 rounded-full flex items-center justify-center mr-3">
                        <i id="confirmationIconSymbol" class="text-xl"></i>
                    </div>
                    <h3 id="confirmationTitle" class="text-lg font-semibold text-seait-dark"></h3>
                </div>
                <button onclick="closeConfirmationModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        
        <div class="p-6">
            <p id="confirmationMessage" class="text-gray-700 mb-6"></p>
            
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeConfirmationModal()" 
                        class="px-4 py-2 text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-all duration-200">
                    Cancel
                </button>
                <button type="button" id="confirmationAction" 
                        class="px-4 py-2 text-white rounded-lg transition-all duration-200 transform hover:scale-105">
                    <i id="confirmationActionIcon" class="mr-2"></i>
                    <span id="confirmationActionText"></span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function openAddFacultyModal() {
    document.getElementById('addFacultyModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    // Auto-focus on Faculty ID field
    setTimeout(() => {
        document.getElementById('qrcode').focus();
    }, 100); // Small delay to ensure modal is fully rendered
}

function closeAddFacultyModal() {
    document.getElementById('addFacultyModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
    document.getElementById('addFacultyForm').reset();
    
    // Stop camera if running and reset photo preview
    stopCamera();
    resetPhotoPreview();
    hideCameraView();
}

function resetPhotoPreview() {
    const preview = document.getElementById('photoPreview');
    const removeBtn = document.getElementById('removePhoto');
    const fileInput = document.getElementById('faculty_photo');
    const capturedPhoto = document.getElementById('captured_photo');
    
    preview.innerHTML = '<i class="fas fa-camera text-gray-400 text-xl"></i>';
    preview.className = 'w-32 h-32 rounded-full bg-gray-200 border-2 border-dashed border-gray-300 flex items-center justify-center overflow-hidden';
    removeBtn.classList.add('hidden');
    fileInput.value = '';
    capturedPhoto.value = '';
}

function showCameraView() {
    document.getElementById('cameraView').classList.remove('hidden');
    document.getElementById('photoControls').classList.add('hidden');
}

function hideCameraView() {
    document.getElementById('cameraView').classList.add('hidden');
    document.getElementById('photoControls').classList.remove('hidden');
}

// Camera functionality
let currentStream = null;

function startCameraCapture() {
    showCameraView();
    startCamera();
}

function cancelCamera() {
    stopCamera();
    hideCameraView();
}

function startCamera() {
    const video = document.getElementById('cameraVideo');
    const errorDiv = document.getElementById('cameraError');
    const errorMessage = document.getElementById('cameraErrorMessage');
    
    // Hide any previous errors
    errorDiv.classList.add('hidden');
    
    // Check if getUserMedia is supported
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        // Fallback for older browsers
        if (navigator.getUserMedia || navigator.webkitGetUserMedia || navigator.mozGetUserMedia || navigator.msGetUserMedia) {
            const getUserMedia = navigator.getUserMedia || navigator.webkitGetUserMedia || navigator.mozGetUserMedia || navigator.msGetUserMedia;
            
            getUserMedia.call(navigator, { video: true }, function(stream) {
                currentStream = stream;
                video.srcObject = stream;
                video.play();
            }, function(error) {
                handleCameraError(error);
            });
            return;
        } else {
            errorMessage.textContent = 'Camera access not supported in this browser. Please use a modern browser.';
            errorDiv.classList.remove('hidden');
            return;
        }
    }
    
    // Modern approach with constraints
    const constraints = {
        video: {
            facingMode: 'user', // Front camera preferred
            width: { ideal: 640, min: 320, max: 1280 },
            height: { ideal: 480, min: 240, max: 720 }
        }
    };
    
    // Try with constraints first
    navigator.mediaDevices.getUserMedia(constraints)
        .then(function(stream) {
            currentStream = stream;
            video.srcObject = stream;
            video.play();
        })
        .catch(function(error) {
            console.error('Camera access error with constraints:', error);
            
            // Fallback: try with basic video constraint
            navigator.mediaDevices.getUserMedia({ video: true })
                .then(function(stream) {
                    currentStream = stream;
                    video.srcObject = stream;
                    video.play();
                })
                .catch(function(fallbackError) {
                    console.error('Camera access error with fallback:', fallbackError);
                    handleCameraError(fallbackError);
                });
        });
}

function handleCameraError(error) {
    const errorDiv = document.getElementById('cameraError');
    const errorMessage = document.getElementById('cameraErrorMessage');
    
    console.error('Camera access error:', error);
    let message = 'Unable to access camera. ';
    
    if (error.name === 'NotAllowedError' || error.name === 'PermissionDeniedError') {
        message += 'Please allow camera permissions in your browser settings.';
    } else if (error.name === 'NotFoundError' || error.name === 'DevicesNotFoundError') {
        message += 'No camera found on this device.';
    } else if (error.name === 'NotSupportedError' || error.name === 'NotReadableError') {
        message += 'Camera not supported or already in use by another application.';
    } else if (error.name === 'OverconstrainedError') {
        message += 'Camera constraints not supported. Trying basic access...';
    } else if (error.name === 'SecurityError') {
        message += 'Camera access blocked due to security restrictions. Please check if you\'re on a secure connection or try refreshing the page.';
    } else {
        message += 'Please check your camera settings and try again.';
    }
    
    errorMessage.textContent = message;
    errorDiv.classList.remove('hidden');
}

function stopCamera() {
    if (currentStream) {
        currentStream.getTracks().forEach(track => track.stop());
        currentStream = null;
    }
    
    const video = document.getElementById('cameraVideo');
    video.srcObject = null;
}

function capturePhoto() {
    const video = document.getElementById('cameraVideo');
    const canvas = document.getElementById('cameraCanvas');
    const context = canvas.getContext('2d');
    
    // Set canvas dimensions to match video
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    
    // Draw video frame to canvas
    context.drawImage(video, 0, 0);
    
    // Convert to base64 and store in hidden input
    const base64Data = canvas.toDataURL('image/jpeg', 0.8);
    document.getElementById('captured_photo').value = base64Data;
    
    // Clear file input when photo is captured
    document.getElementById('faculty_photo').value = '';
    
    // Show preview
    const photoPreview = document.getElementById('photoPreview');
    photoPreview.innerHTML = `<img src="${base64Data}" alt="Preview" class="w-full h-full object-cover rounded-full">`;
    photoPreview.className = 'w-32 h-32 rounded-full bg-gray-200 border-2 border-solid border-green-300 flex items-center justify-center overflow-hidden';
    
    // Show remove button
    document.getElementById('removePhoto').classList.remove('hidden');
    
    // Stop camera and hide camera view
    stopCamera();
    hideCameraView();
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

// Photo upload and preview functionality
document.addEventListener('DOMContentLoaded', function() {
    const photoInput = document.getElementById('faculty_photo');
    const photoPreview = document.getElementById('photoPreview');
    const removeBtn = document.getElementById('removePhoto');
    
    // Handle photo selection
    photoInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        
        if (file) {
            // Clear captured photo when file is selected
            document.getElementById('captured_photo').value = '';
            
            // Validate file type
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            if (!allowedTypes.includes(file.type)) {
                alert('Please select a valid image file (JPG, PNG, GIF).');
                photoInput.value = '';
                return;
            }
            
            // Validate file size (2MB max)
            const maxSize = 2 * 1024 * 1024; // 2MB in bytes
            if (file.size > maxSize) {
                alert('Photo size must be less than 2MB.');
                photoInput.value = '';
                return;
            }
            
            // Create preview
            const reader = new FileReader();
            reader.onload = function(e) {
                photoPreview.innerHTML = `<img src="${e.target.result}" alt="Preview" class="w-full h-full object-cover rounded-full">`;
                photoPreview.className = 'w-32 h-32 rounded-full bg-gray-200 border-2 border-solid border-green-300 flex items-center justify-center overflow-hidden';
                removeBtn.classList.remove('hidden');
            };
            reader.readAsDataURL(file);
        }
    });
    
    // Handle photo removal
    removeBtn.addEventListener('click', function() {
        resetPhotoPreview();
    });
    
    // Handle take photo button
    const takePhotoBtn = document.getElementById('takePhoto');
    takePhotoBtn.addEventListener('click', function() {
        startCameraCapture();
    });
    
    // Handle capture photo button
    const captureBtn = document.getElementById('capturePhoto');
    captureBtn.addEventListener('click', function() {
        capturePhoto();
    });
    
    // Handle cancel camera button
    const cancelBtn = document.getElementById('cancelCamera');
    cancelBtn.addEventListener('click', function() {
        cancelCamera();
    });
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
    
    // Auto-hide success messages after 5 seconds
    const successMessages = document.querySelectorAll('.bg-green-100');
    successMessages.forEach(function(message) {
        setTimeout(function() {
            message.style.transition = 'opacity 0.5s ease-out';
            message.style.opacity = '0';
            setTimeout(function() {
                message.remove();
            }, 500);
        }, 5000);
    });
});

// Form validation
document.getElementById('addFacultyForm').addEventListener('submit', function(e) {
    const qrcode = document.getElementById('qrcode').value.trim();
    const firstName = document.getElementById('first_name').value.trim();
    const lastName = document.getElementById('last_name').value.trim();
    const capturedPhoto = document.getElementById('captured_photo').value;
    
    if (!qrcode || !firstName || !lastName) {
        e.preventDefault();
        alert('Please fill in all required fields (QR Code, First Name, Last Name).');
        return false;
    }
    
    // Validate QR code format (YYYY-NNNN)
    const qrcodePattern = /^\d{4}-\d{4}$/;
    if (!qrcodePattern.test(qrcode)) {
        e.preventDefault();
        alert('QR Code must be in format YYYY-NNNN (e.g., 2025-0001).');
        return false;
    }
    
    return true;
});

// Faculty status management functions
function confirmDeactivate(facultyId, facultyName) {
    showConfirmationModal({
        title: 'Deactivate Faculty',
        message: `Are you sure you want to deactivate <strong>${facultyName}</strong>? This will set their status to inactive and they will no longer appear in active faculty lists.`,
        icon: 'fas fa-exclamation-triangle',
        iconColor: 'bg-red-100 text-red-600',
        actionText: 'Deactivate',
        actionColor: 'bg-red-600 hover:bg-red-700',
        actionIcon: 'fas fa-trash',
        onConfirm: () => updateFacultyStatus(facultyId, 'deactivate', facultyName)
    });
}

function confirmReactivate(facultyId, facultyName) {
    showConfirmationModal({
        title: 'Reactivate Faculty',
        message: `Are you sure you want to reactivate <strong>${facultyName}</strong>? This will set their status to active and they will appear in active faculty lists again.`,
        icon: 'fas fa-check-circle',
        iconColor: 'bg-green-100 text-green-600',
        actionText: 'Reactivate',
        actionColor: 'bg-green-600 hover:bg-green-700',
        actionIcon: 'fas fa-undo',
        onConfirm: () => updateFacultyStatus(facultyId, 'reactivate', facultyName)
    });
}

function updateFacultyStatus(facultyId, action, facultyName) {
    // Create a form to submit the status update
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'update-faculty-status.php';
    form.style.display = 'none';
    
    // Add faculty ID
    const idInput = document.createElement('input');
    idInput.type = 'hidden';
    idInput.name = 'faculty_id';
    idInput.value = facultyId;
    form.appendChild(idInput);
    
    // Add action
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = action;
    form.appendChild(actionInput);
    
    // Add faculty name for success message
    const nameInput = document.createElement('input');
    nameInput.type = 'hidden';
    nameInput.name = 'faculty_name';
    nameInput.value = facultyName;
    form.appendChild(nameInput);
    
    // Submit form
    document.body.appendChild(form);
    form.submit();
}

// Beautiful confirmation modal functions
function showConfirmationModal(options) {
    const modal = document.getElementById('confirmationModal');
    const modalContent = document.getElementById('confirmationModalContent');
    const title = document.getElementById('confirmationTitle');
    const message = document.getElementById('confirmationMessage');
    const icon = document.getElementById('confirmationIcon');
    const iconSymbol = document.getElementById('confirmationIconSymbol');
    const actionBtn = document.getElementById('confirmationAction');
    const actionIcon = document.getElementById('confirmationActionIcon');
    const actionText = document.getElementById('confirmationActionText');
    
    // Set modal content
    title.textContent = options.title;
    message.innerHTML = options.message;
    
    // Set icon
    icon.className = `w-10 h-10 rounded-full flex items-center justify-center mr-3 ${options.iconColor}`;
    iconSymbol.className = `text-xl ${options.icon}`;
    
    // Set action button
    actionBtn.className = `px-4 py-2 text-white rounded-lg transition-all duration-200 transform hover:scale-105 ${options.actionColor}`;
    actionIcon.className = `mr-2 ${options.actionIcon}`;
    actionText.textContent = options.actionText;
    
    // Set action button click handler
    actionBtn.onclick = function() {
        options.onConfirm();
        closeConfirmationModal();
    };
    
    // Show modal with animation
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    // Trigger animation
    setTimeout(() => {
        modalContent.classList.remove('scale-95');
        modalContent.classList.add('scale-100');
    }, 10);
}

function closeConfirmationModal() {
    const modal = document.getElementById('confirmationModal');
    const modalContent = document.getElementById('confirmationModalContent');
    
    // Animate out
    modalContent.classList.remove('scale-100');
    modalContent.classList.add('scale-95');
    
    // Hide modal after animation
    setTimeout(() => {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }, 300);
}

// Close modal when clicking outside
document.getElementById('confirmationModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeConfirmationModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && !document.getElementById('confirmationModal').classList.contains('hidden')) {
        closeConfirmationModal();
    }
});
</script>

<?php include 'includes/footer.php'; ?>
