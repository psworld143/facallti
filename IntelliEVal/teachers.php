<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has guidance_officer role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guidance_officer') {
    header('Location: ../login.php');
    exit();
}

// Set page title
$page_title = 'Faculty Management';

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_teacher':
                $first_name = sanitize_input($_POST['first_name']);
                $last_name = sanitize_input($_POST['last_name']);
                $email = sanitize_input($_POST['email']);
                $phone = sanitize_input($_POST['phone']);
                $department = sanitize_input($_POST['department']);
                $position = sanitize_input($_POST['position']);
                $password = $_POST['password'];

                // Check if email already exists in faculty table
                $check_query = "SELECT id FROM faculty WHERE email = ?";
                $check_stmt = mysqli_prepare($conn, $check_query);
                mysqli_stmt_bind_param($check_stmt, "s", $email);
                mysqli_stmt_execute($check_stmt);
                $check_result = mysqli_stmt_get_result($check_stmt);

                if (mysqli_num_rows($check_result) > 0) {
                    $message = "Email already exists. Please use a different email address.";
                    $message_type = "error";
                } else {
                    // Hash password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                    // Insert into faculty table
                    $insert_faculty_query = "INSERT INTO faculty (first_name, last_name, email, password, position, department, bio, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)";
                    $bio = "Teacher added through IntelliEVal system"; // Default bio
                    $insert_faculty_stmt = mysqli_prepare($conn, $insert_faculty_query);
                    mysqli_stmt_bind_param($insert_faculty_stmt, "sssssss", $first_name, $last_name, $email, $hashed_password, $position, $department, $bio);

                    if (mysqli_stmt_execute($insert_faculty_stmt)) {
                        $message = "Teacher added successfully to faculty!";
                        $message_type = "success";
                    } else {
                        $message = "Error adding teacher: " . mysqli_error($conn);
                        $message_type = "error";
                    }
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

// Build query
$where_conditions = ["1=1"]; // All faculty members
$params = [];
$param_types = '';

if ($search) {
    $where_conditions[] = "(f.first_name LIKE ? OR f.last_name LIKE ? OR f.email LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
    $param_types .= 'sss';
}

if ($department_filter) {
    $where_conditions[] = "f.department = ?";
    $params[] = $department_filter;
    $param_types .= 's';
}

if ($status_filter) {
    $where_conditions[] = "f.is_active = ?";
    $status_value = ($status_filter === 'active') ? 1 : 0;
    $params[] = $status_value;
    $param_types .= 'i';
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM faculty f $where_clause";
if (!empty($params)) {
    $count_stmt = mysqli_prepare($conn, $count_query);
    mysqli_stmt_bind_param($count_stmt, $param_types, ...$params);
    mysqli_stmt_execute($count_stmt);
    $count_result = mysqli_stmt_get_result($count_stmt);
} else {
    $count_result = mysqli_query($conn, $count_query);
}
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $per_page);

// Get teachers from faculty table
$teachers_query = "SELECT f.id as faculty_id, f.first_name, f.last_name, f.email, f.position, f.department, f.bio, f.is_active, f.created_at,
                          u.id as user_id
                   FROM faculty f
                   LEFT JOIN users u ON f.email = u.email
                   $where_clause
                   ORDER BY f.created_at DESC
                   LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$param_types .= 'ii';

$teachers_stmt = mysqli_prepare($conn, $teachers_query);
mysqli_stmt_bind_param($teachers_stmt, $param_types, ...$params);
mysqli_stmt_execute($teachers_stmt);
$teachers_result = mysqli_stmt_get_result($teachers_stmt);

$teachers = [];
while ($row = mysqli_fetch_assoc($teachers_result)) {
    $teachers[] = $row;
}

// Get unique departments for filter
$departments_query = "SELECT DISTINCT department FROM faculty WHERE department IS NOT NULL AND department != '' ORDER BY department";
$departments_result = mysqli_query($conn, $departments_query);
$departments = [];
while ($row = mysqli_fetch_assoc($departments_result)) {
    $departments[] = $row['department'];
}

// Get colleges for department dropdown
$colleges_query = "SELECT id, name, short_name FROM colleges WHERE is_active = 1 ORDER BY sort_order, name";
$colleges_result = mysqli_query($conn, $colleges_query);
$colleges = [];
while ($row = mysqli_fetch_assoc($colleges_result)) {
    $colleges[] = $row;
}

// Include the shared header
include 'includes/header.php';
?>

<div class="mb-6 sm:mb-8">
    <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Faculty Management</h1>
    <p class="text-sm sm:text-base text-gray-600">Manage faculty members who will be evaluated in the system</p>
</div>

<?php if ($message): ?>
    <div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200'; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<!-- Information Alert -->
<div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
    <div class="flex">
        <div class="flex-shrink-0">
            <i class="fas fa-info-circle text-blue-400"></i>
        </div>
        <div class="ml-3">
            <h3 class="text-sm font-medium text-blue-800">Faculty Management Updates</h3>
            <div class="mt-2 text-sm text-blue-700">
                <p><strong>Faculty Integration:</strong> Teachers are now added to the faculty table for unified management across the system.</p>
                <p class="mt-1"><strong>Login Credentials:</strong> Teachers use their email address as their login username with the default password "Seait123".</p>
                <p class="mt-1"><strong>Department Selection:</strong> Department options are populated from the colleges database.</p>
            </div>
        </div>
    </div>
</div>

<!-- Search and Filter -->
<div class="bg-white rounded-lg shadow-md p-4 sm:p-6 mb-6">
    <form method="GET" class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                   placeholder="Search by name, email, or username..."
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Department</label>
            <select name="department" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange text-sm">
                <option value="">All Departments</option>
                <?php foreach ($colleges as $college): ?>
                    <option value="<?php echo htmlspecialchars($college['name']); ?>" <?php echo $department_filter === $college['name'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($college['name']); ?> (<?php echo htmlspecialchars($college['short_name']); ?>)
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
            <a href="teachers.php" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition text-sm">
                <i class="fas fa-times"></i>
            </a>
        </div>
    </form>
</div>

<!-- Add Teacher Button -->
<div class="mb-6">
    <button onclick="showAddModal()" class="w-full sm:w-auto bg-seait-orange text-white px-4 sm:px-6 py-2 rounded-lg hover:bg-orange-600 transition text-sm sm:text-base">
        <i class="fas fa-plus mr-2"></i>Add Faculty Member
    </button>
</div>

<!-- Teachers Table -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
        <h2 class="text-base sm:text-lg font-medium text-gray-900">Teachers (<?php echo number_format($total_records); ?>)</h2>
    </div>

    <!-- Desktop Table -->
    <div class="hidden lg:block overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teacher</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($teachers)): ?>
                <tr>
                    <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                        No teachers found. <?php if ($search || $department_filter || $status_filter): ?>Try adjusting your search criteria.<?php else: ?>Add your first teacher to get started.<?php endif; ?>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($teachers as $teacher): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="h-10 w-10 rounded-full bg-seait-orange flex items-center justify-center mr-3">
                                    <span class="text-white font-medium"><?php echo strtoupper(substr($teacher['first_name'], 0, 1) . substr($teacher['last_name'], 0, 1)); ?></span>
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($teacher['email']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($teacher['email']); ?></div>
                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($teacher['position'] ?? 'N/A'); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($teacher['department'] ?? 'N/A'); ?></div>
                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($teacher['position'] ?? 'N/A'); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs rounded-full <?php
                                echo $teacher['is_active'] === 1 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                            ?>">
                                <?php echo $teacher['is_active'] === 1 ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <a href="teacher-subjects.php?teacher_id=<?php echo $teacher['faculty_id']; ?>" class="text-green-600 hover:text-green-900 mr-3" title="View Teacher Subjects">
                                <i class="fas fa-book"></i>
                            </a>
                            <a href="view-evaluation.php?faculty_id=<?php echo $teacher['faculty_id']; ?>"
                               class="bg-purple-600 text-white px-3 py-1 rounded text-sm hover:bg-purple-700 transition-colors duration-200 cursor-pointer inline-flex items-center"
                               title="View Evaluation Results - Click to see evaluation data or 'No Data Available' message">
                                <i class="fas fa-chart-line mr-1"></i>Evaluation
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Mobile Cards -->
    <div class="lg:hidden">
        <?php if (empty($teachers)): ?>
            <div class="p-4 text-center text-gray-500">
                No teachers found. <?php if ($search || $department_filter || $status_filter): ?>Try adjusting your search criteria.<?php else: ?>Add your first teacher to get started.<?php endif; ?>
            </div>
        <?php else: ?>
            <div class="p-4 space-y-4">
                <?php foreach ($teachers as $teacher): ?>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center">
                                <div class="h-10 w-10 rounded-full bg-seait-orange flex items-center justify-center mr-3">
                                    <span class="text-white font-medium"><?php echo strtoupper(substr($teacher['first_name'], 0, 1) . substr($teacher['last_name'], 0, 1)); ?></span>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></p>
                                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($teacher['email']); ?></p>
                                </div>
                            </div>
                            <span class="px-2 py-1 text-xs rounded-full <?php
                                echo $teacher['is_active'] === 1 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                            ?>">
                                <?php echo $teacher['is_active'] === 1 ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>

                        <div class="grid grid-cols-2 gap-4 text-sm mb-3">
                            <div>
                                <p class="text-gray-500">Email</p>
                                <p class="text-gray-900"><?php echo htmlspecialchars($teacher['email']); ?></p>
                            </div>
                            <div>
                                <p class="text-gray-500">Position</p>
                                <p class="text-gray-900"><?php echo htmlspecialchars($teacher['position'] ?? 'N/A'); ?></p>
                            </div>
                            <div>
                                <p class="text-gray-500">Department</p>
                                <p class="text-gray-900"><?php echo htmlspecialchars($teacher['department'] ?? 'N/A'); ?></p>
                            </div>
                            <div>
                                <p class="text-gray-500">Status</p>
                                <p class="text-gray-900"><?php echo $teacher['is_active'] === 1 ? 'Active' : 'Inactive'; ?></p>
                            </div>
                        </div>

                        <div class="flex space-x-2">
                            <a href="teacher-subjects.php?teacher_id=<?php echo $teacher['faculty_id']; ?>" class="text-green-600 hover:text-green-900" title="View Teacher Subjects">
                                <i class="fas fa-book"></i>
                            </a>
                            <a href="view-evaluation.php?faculty_id=<?php echo $teacher['faculty_id']; ?>"
                               class="bg-purple-600 text-white px-3 py-1 rounded text-sm hover:bg-purple-700 transition-colors duration-200 cursor-pointer inline-flex items-center"
                               title="View Evaluation Results - Click to see evaluation data or 'No Data Available' message">
                                <i class="fas fa-chart-line mr-1"></i>Evaluation
                            </a>
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

<!-- Add Teacher Modal -->
<div id="addTeacherModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-lg mx-auto max-h-screen overflow-y-auto">
        <div class="flex items-center justify-between p-4 sm:p-6 border-b border-gray-200">
            <h3 class="text-lg sm:text-xl font-semibold text-seait-dark">Add New Faculty Member</h3>
            <button onclick="closeAddModal()" class="text-gray-400 hover:text-gray-600 transition">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <form method="POST" class="p-4 sm:p-6">
            <input type="hidden" name="action" value="add_teacher">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">First Name</label>
                    <input type="text" name="first_name" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Last Name</label>
                    <input type="text" name="last_name" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange text-sm">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                    <input type="email" name="email" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange text-sm">
                    <p class="text-xs text-gray-500 mt-1">Email will be used as the login username</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Phone</label>
                    <input type="tel" name="phone"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange text-sm">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Department</label>
                    <select name="department" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange text-sm">
                        <option value="">Select Department</option>
                        <?php foreach ($colleges as $college): ?>
                            <option value="<?php echo htmlspecialchars($college['name']); ?>">
                                <?php echo htmlspecialchars($college['name']); ?> (<?php echo htmlspecialchars($college['short_name']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Position</label>
                    <input type="text" name="position" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange text-sm">
                </div>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                <input type="text" name="password" value="Seait123" required
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange text-sm">
                <p class="text-xs text-gray-500 mt-1">Default password: Seait123 (can be changed)</p>
            </div>

            <div class="flex flex-col sm:flex-row justify-end space-y-2 sm:space-y-0 sm:space-x-3">
                <button type="button" onclick="closeAddModal()"
                        class="w-full sm:w-auto px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition text-sm">
                    Cancel
                </button>
                <button type="submit"
                        class="w-full sm:w-auto px-4 py-2 bg-seait-orange text-white rounded-lg hover:bg-orange-600 transition text-sm">
                    Add Faculty Member
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showAddModal() {
    document.getElementById('addTeacherModal').classList.remove('hidden');
}

function closeAddModal() {
    document.getElementById('addTeacherModal').classList.add('hidden');
}

// Close modal when clicking outside
document.addEventListener('click', function(event) {
    const modal = document.getElementById('addTeacherModal');
    if (event.target === modal) {
        modal.classList.add('hidden');
    }
});
</script>

<?php
// Include the shared footer
include 'includes/footer.php';
?>