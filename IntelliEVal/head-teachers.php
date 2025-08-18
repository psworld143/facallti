<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has guidance_officer or head role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['guidance_officer', 'head'])) {
    header('Location: ../index.php');
    exit();
}

// Check if head_id is provided
if (!isset($_GET['head_id']) || !is_numeric($_GET['head_id'])) {
    header('Location: heads.php');
    exit();
}

$head_id = (int)$_GET['head_id'];

// Get head information
$head_query = "SELECT u.*, h.department, h.position, h.phone as head_phone
               FROM users u
               LEFT JOIN heads h ON u.id = h.user_id
               WHERE u.id = ? AND u.role = 'head'";
$head_stmt = mysqli_prepare($conn, $head_query);
mysqli_stmt_bind_param($head_stmt, "i", $head_id);
mysqli_stmt_execute($head_stmt);
$head_result = mysqli_stmt_get_result($head_stmt);

if (mysqli_num_rows($head_result) === 0) {
    header('Location: heads.php');
    exit();
}

$head = mysqli_fetch_assoc($head_result);

// Set page title
$page_title = 'Teachers under ' . $head['first_name'] . ' ' . $head['last_name'];

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'assign_teacher':
                $teacher_id = (int)$_POST['teacher_id'];

                // Check if teacher is already assigned to this head
                $check_query = "SELECT id FROM head_teacher_assignments WHERE head_id = ? AND teacher_id = ?";
                $check_stmt = mysqli_prepare($conn, $check_query);
                mysqli_stmt_bind_param($check_stmt, "ii", $head_id, $teacher_id);
                mysqli_stmt_execute($check_stmt);
                $check_result = mysqli_stmt_get_result($check_stmt);

                if (mysqli_num_rows($check_result) > 0) {
                    $message = "Teacher is already assigned to this head.";
                    $message_type = "error";
                } else {
                    // Assign teacher to head
                    $assign_query = "INSERT INTO head_teacher_assignments (head_id, teacher_id, assigned_date) VALUES (?, ?, NOW())";
                    $assign_stmt = mysqli_prepare($conn, $assign_query);
                    mysqli_stmt_bind_param($assign_stmt, "ii", $head_id, $teacher_id);

                    if (mysqli_stmt_execute($assign_stmt)) {
                        $message = "Teacher assigned successfully!";
                        $message_type = "success";
                    } else {
                        $message = "Error assigning teacher: " . mysqli_error($conn);
                        $message_type = "error";
                    }
                }
                break;

            case 'remove_teacher':
                $assignment_id = (int)$_POST['assignment_id'];

                $remove_query = "DELETE FROM head_teacher_assignments WHERE id = ? AND head_id = ?";
                $remove_stmt = mysqli_prepare($conn, $remove_query);
                mysqli_stmt_bind_param($remove_stmt, "ii", $assignment_id, $head_id);

                if (mysqli_stmt_execute($remove_stmt)) {
                    $message = "Teacher removed from head successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error removing teacher: " . mysqli_error($conn);
                    $message_type = "error";
                }
                break;
        }
    }
}

// Get search and filter parameters
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$department_filter = isset($_GET['department']) ? sanitize_input($_GET['department']) : '';
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query for assigned teachers
$where_conditions = ["hta.head_id = ?"];
$params = [$head_id];
$param_types = 'i';

if ($search) {
    $where_conditions[] = "(f.first_name LIKE ? OR f.last_name LIKE ? OR f.email LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
    $param_types .= 'sss';
}

if ($department_filter) {
    // Handle department name variations more comprehensively
    $department_conditions = [];
    
    // Add exact match
    $department_conditions[] = "f.department = ?";
    $params[] = $department_filter;
    $param_types .= 's';
    
    // Handle "Department of X" pattern
    if (!str_contains($department_filter, 'Department of ')) {
        $department_conditions[] = "f.department = ?";
        $params[] = 'Department of ' . $department_filter;
        $param_types .= 's';
    }
    
    // Handle "X Department" pattern  
    if (!str_contains($department_filter, ' Department')) {
        $department_conditions[] = "f.department = ?";
        $params[] = $department_filter . ' Department';
        $param_types .= 's';
    }
    
    // Handle "College of X" pattern
    if (!str_contains($department_filter, 'College of ')) {
        $department_conditions[] = "f.department = ?";
        $params[] = 'College of ' . $department_filter;
        $param_types .= 's';
    }
    
    // Handle reverse patterns (if filter has "College of X", check for just "X")
    if (str_contains($department_filter, 'College of ')) {
        $simple_name = str_replace('College of ', '', $department_filter);
        $department_conditions[] = "f.department = ?";
        $params[] = $simple_name;
        $param_types .= 's';
        
        $department_conditions[] = "f.department = ?";
        $params[] = 'Department of ' . $simple_name;
        $param_types .= 's';
    }
    
    // Handle partial matches for complex department names
    // If filter has "College of Business and Good Governance", also check for "College of Business"
    if (str_contains($department_filter, ' and ')) {
        $parts = explode(' and ', $department_filter);
        if (count($parts) >= 2) {
            $first_part = trim($parts[0]);
            $department_conditions[] = "f.department = ?";
            $params[] = $first_part;
            $param_types .= 's';
        }
    }
    
    // Handle "Information and Communication Technology" vs "Information Technology" variations
    if (str_contains($department_filter, 'Information and Communication Technology')) {
        $department_conditions[] = "f.department = ?";
        $params[] = 'College of Information Technology';
        $param_types .= 's';
        
        $department_conditions[] = "f.department = ?";
        $params[] = 'Department of Information Technology';
        $param_types .= 's';
    }
    
    $where_conditions[] = "(" . implode(' OR ', $department_conditions) . ")";
}

if ($status_filter) {
    $where_conditions[] = "f.is_active = ?";
    $status_value = ($status_filter === 'active') ? 1 : 0;
    $params[] = $status_value;
    $param_types .= 'i';
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total
                FROM head_teacher_assignments hta
                JOIN faculty f ON hta.teacher_id = f.id
                $where_clause";
$count_stmt = mysqli_prepare($conn, $count_query);
mysqli_stmt_bind_param($count_stmt, $param_types, ...$params);
mysqli_stmt_execute($count_stmt);
$count_result = mysqli_stmt_get_result($count_stmt);
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $per_page);

// Get assigned teachers
$teachers_query = "SELECT f.*, hta.id as assignment_id, hta.assigned_date
                   FROM head_teacher_assignments hta
                   JOIN faculty f ON hta.teacher_id = f.id
                   $where_clause
                   ORDER BY f.last_name, f.first_name
                   LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$param_types .= 'ii';

$teachers_stmt = mysqli_prepare($conn, $teachers_query);
mysqli_stmt_bind_param($teachers_stmt, $param_types, ...$params);
mysqli_stmt_execute($teachers_stmt);
$teachers_result = mysqli_stmt_get_result($teachers_stmt);

$assigned_teachers = [];
while ($row = mysqli_fetch_assoc($teachers_result)) {
    $assigned_teachers[] = $row;
}

// Get available teachers for assignment (not already assigned to this head)
$available_teachers_query = "SELECT f.* FROM faculty f
                            WHERE f.is_active = 1
                            AND f.id NOT IN (
                                SELECT teacher_id FROM head_teacher_assignments WHERE head_id = ?
                            )
                            ORDER BY f.last_name, f.first_name";
$available_teachers_stmt = mysqli_prepare($conn, $available_teachers_query);
mysqli_stmt_bind_param($available_teachers_stmt, "i", $head_id);
mysqli_stmt_execute($available_teachers_stmt);
$available_teachers_result = mysqli_stmt_get_result($available_teachers_stmt);

$available_teachers = [];
while ($row = mysqli_fetch_assoc($available_teachers_result)) {
    $available_teachers[] = $row;
}

// Get unique departments for filter
$departments_query = "SELECT DISTINCT department FROM faculty WHERE department IS NOT NULL AND department != '' ORDER BY department";
$departments_result = mysqli_query($conn, $departments_query);
$departments = [];
while ($row = mysqli_fetch_assoc($departments_result)) {
    $departments[] = $row['department'];
}

// Include the shared header
include 'includes/header.php';
?>

<div class="mb-6 sm:mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Teachers under <?php echo htmlspecialchars($head['first_name'] . ' ' . $head['last_name']); ?></h1>
            <p class="text-sm sm:text-base text-gray-600">
                Department: <?php echo htmlspecialchars($head['department'] ?? 'N/A'); ?> |
                Position: <?php echo htmlspecialchars($head['position'] ?? 'N/A'); ?>
            </p>
        </div>
        <a href="heads.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition text-sm">
            <i class="fas fa-arrow-left mr-2"></i>Back to Heads
        </a>
    </div>
</div>

<?php if ($message): ?>
    <div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200'; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<!-- Head Information Card -->
<div class="bg-white rounded-lg shadow-md p-4 sm:p-6 mb-6">
    <div class="flex items-center">
        <div class="h-16 w-16 rounded-full bg-purple-600 flex items-center justify-center mr-4">
            <span class="text-white font-medium text-xl"><?php echo strtoupper(substr($head['first_name'], 0, 1) . substr($head['last_name'], 0, 1)); ?></span>
        </div>
        <div class="flex-1">
            <h3 class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($head['first_name'] . ' ' . $head['last_name']); ?></h3>
            <p class="text-gray-600"><?php echo htmlspecialchars($head['email']); ?></p>
            <div class="mt-2 flex flex-wrap gap-4 text-sm text-gray-500">
                <span><i class="fas fa-building mr-1"></i><?php echo htmlspecialchars($head['department'] ?? 'N/A'); ?></span>
                <span><i class="fas fa-user-tie mr-1"></i><?php echo htmlspecialchars($head['position'] ?? 'N/A'); ?></span>
                <span><i class="fas fa-phone mr-1"></i><?php echo htmlspecialchars($head['head_phone'] ?? 'N/A'); ?></span>
            </div>
        </div>
        <div class="text-right">
            <div class="text-2xl font-bold text-seait-orange"><?php echo $total_records; ?></div>
            <div class="text-sm text-gray-500">Assigned Teachers</div>
        </div>
    </div>
</div>

<!-- Search and Filter -->
<div class="bg-white rounded-lg shadow-md p-4 sm:p-6 mb-6">
    <form method="GET" class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <input type="hidden" name="head_id" value="<?php echo $head_id; ?>">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                   placeholder="Search by name or email..."
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Department</label>
            <select name="department" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange text-sm">
                <option value="">All Departments</option>
                <?php foreach ($departments as $dept): ?>
                    <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo $department_filter === $dept ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($dept); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
            <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange text-sm">
                <option value="">All Status</option>
                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
            </select>
        </div>
        <div class="flex items-end space-x-2">
            <button type="submit" class="flex-1 bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition text-sm">
                <i class="fas fa-search mr-2"></i>Search
            </button>
            <a href="head-teachers.php?head_id=<?php echo $head_id; ?>" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition text-sm">
                <i class="fas fa-times"></i>
            </a>
        </div>
    </form>
</div>

<!-- Action Buttons -->
<div class="mb-6 flex flex-col sm:flex-row gap-4">
    <button onclick="openAssignTeacherModal()" class="w-full sm:w-auto bg-seait-orange text-white px-4 sm:px-6 py-2 rounded-lg hover:bg-orange-600 transition text-sm sm:text-base">
        <i class="fas fa-plus mr-2"></i>Assign Teacher
    </button>
    <div class="text-sm text-gray-600 flex items-center">
        <i class="fas fa-info-circle mr-2"></i>
        <?php echo count($available_teachers); ?> teachers available for assignment
    </div>
</div>

<!-- Assigned Teachers Table -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
        <h2 class="text-base sm:text-lg font-medium text-gray-900">Assigned Teachers (<?php echo number_format($total_records); ?>)</h2>
    </div>

    <!-- Desktop Table -->
    <div class="hidden lg:block overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teacher</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assigned Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($assigned_teachers)): ?>
                <tr>
                    <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                        No teachers assigned yet. <?php if ($search || $department_filter || $status_filter): ?>Try adjusting your search criteria.<?php else: ?>Assign teachers to get started.<?php endif; ?>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($assigned_teachers as $teacher): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="h-10 w-10 rounded-full bg-blue-600 flex items-center justify-center mr-3">
                                    <span class="text-white font-medium"><?php echo strtoupper(substr($teacher['first_name'], 0, 1) . substr($teacher['last_name'], 0, 1)); ?></span>
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($teacher['position']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($teacher['email']); ?></div>
                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($teacher['phone'] ?? 'N/A'); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($teacher['department']); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900"><?php echo date('M j, Y', strtotime($teacher['assigned_date'])); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs rounded-full <?php
                                echo $teacher['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                            ?>">
                                <?php echo $teacher['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <a href="teachers.php" class="text-blue-600 hover:text-blue-900 mr-3">
                                <i class="fas fa-eye"></i>
                            </a>
                            <button onclick="removeTeacher(<?php echo $teacher['assignment_id']; ?>)" class="text-red-600 hover:text-red-900">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Mobile Cards -->
    <div class="lg:hidden">
        <?php if (empty($assigned_teachers)): ?>
            <div class="p-4 text-center text-gray-500">
                No teachers assigned yet. <?php if ($search || $department_filter || $status_filter): ?>Try adjusting your search criteria.<?php else: ?>Assign teachers to get started.<?php endif; ?>
            </div>
        <?php else: ?>
            <div class="p-4 space-y-4">
                <?php foreach ($assigned_teachers as $teacher): ?>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center">
                                <div class="h-10 w-10 rounded-full bg-blue-600 flex items-center justify-center mr-3">
                                    <span class="text-white font-medium"><?php echo strtoupper(substr($teacher['first_name'], 0, 1) . substr($teacher['last_name'], 0, 1)); ?></span>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></p>
                                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($teacher['position']); ?></p>
                                </div>
                            </div>
                            <span class="px-2 py-1 text-xs rounded-full <?php
                                echo $teacher['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                            ?>">
                                <?php echo $teacher['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>

                        <div class="grid grid-cols-2 gap-4 text-sm mb-3">
                            <div>
                                <p class="text-gray-500">Email</p>
                                <p class="text-gray-900"><?php echo htmlspecialchars($teacher['email']); ?></p>
                            </div>
                            <div>
                                <p class="text-gray-500">Phone</p>
                                <p class="text-gray-900"><?php echo htmlspecialchars($teacher['phone'] ?? 'N/A'); ?></p>
                            </div>
                            <div>
                                <p class="text-gray-500">Department</p>
                                <p class="text-gray-900"><?php echo htmlspecialchars($teacher['department']); ?></p>
                            </div>
                            <div>
                                <p class="text-gray-500">Assigned</p>
                                <p class="text-gray-900"><?php echo date('M j, Y', strtotime($teacher['assigned_date'])); ?></p>
                            </div>
                        </div>

                        <div class="flex space-x-2">
                            <a href="teachers.php" class="text-blue-600 hover:text-blue-900">
                                <i class="fas fa-eye"></i>
                            </a>
                            <button onclick="removeTeacher(<?php echo $teacher['assignment_id']; ?>)" class="text-red-600 hover:text-red-900">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
<div class="mt-6 flex items-center justify-between">
    <div class="text-sm text-gray-700">
        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total_records); ?> of <?php echo number_format($total_records); ?> results
    </div>
    <div class="flex space-x-2">
        <?php if ($page > 1): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>"
               class="px-3 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                Previous
            </a>
        <?php endif; ?>

        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"
               class="px-3 py-2 text-sm border border-gray-300 rounded-lg <?php echo $i === $page ? 'bg-seait-orange text-white' : 'hover:bg-gray-50'; ?> transition">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>

        <?php if ($page < $total_pages): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>"
               class="px-3 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                Next
            </a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Assign Teacher Modal -->
<div id="assignTeacherModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-lg mx-auto max-h-screen overflow-y-auto">
        <div class="flex items-center justify-between p-4 sm:p-6 border-b border-gray-200">
            <h3 class="text-lg sm:text-xl font-semibold text-seait-dark">Assign Teacher to <?php echo htmlspecialchars($head['first_name'] . ' ' . $head['last_name']); ?></h3>
            <button onclick="closeAssignTeacherModal()" class="text-gray-400 hover:text-gray-600 transition">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <form method="POST" class="p-4 sm:p-6">
            <input type="hidden" name="action" value="assign_teacher">

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Select Teacher</label>
                <select name="teacher_id" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange text-sm">
                    <option value="">Choose a teacher...</option>
                    <?php foreach ($available_teachers as $teacher): ?>
                        <option value="<?php echo $teacher['id']; ?>">
                            <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                            (<?php echo htmlspecialchars($teacher['department']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($available_teachers)): ?>
                    <p class="text-sm text-gray-500 mt-2">No teachers available for assignment.</p>
                <?php endif; ?>
            </div>

            <div class="flex flex-col sm:flex-row justify-end space-y-2 sm:space-y-0 sm:space-x-3">
                <button type="button" onclick="closeAssignTeacherModal()"
                        class="w-full sm:w-auto px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition text-sm">
                    Cancel
                </button>
                <button type="submit" <?php echo empty($available_teachers) ? 'disabled' : ''; ?>
                        class="w-full sm:w-auto px-4 py-2 bg-seait-orange text-white rounded-lg hover:bg-orange-600 transition text-sm <?php echo empty($available_teachers) ? 'opacity-50 cursor-not-allowed' : ''; ?>">
                    Assign Teacher
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openAssignTeacherModal() {
    document.getElementById('assignTeacherModal').classList.remove('hidden');
}

function closeAssignTeacherModal() {
    document.getElementById('assignTeacherModal').classList.add('hidden');
}

function removeTeacher(assignmentId) {
    if (confirm('Are you sure you want to remove this teacher from the head? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="remove_teacher">
            <input type="hidden" name="assignment_id" value="${assignmentId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Close modal when clicking outside
document.addEventListener('click', function(event) {
    const modal = document.getElementById('assignTeacherModal');

    if (event.target === modal) {
        modal.classList.add('hidden');
    }
});
</script>

<?php
// Include the shared footer
include 'includes/footer.php';
?>