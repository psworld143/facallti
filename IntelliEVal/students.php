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
$page_title = 'Students';

$message = '';
$message_type = '';

// Get courses for dropdown
$courses_query = "SELECT id, name, short_name FROM courses WHERE is_active = 1 ORDER BY name ASC";
$courses_result = mysqli_query($conn, $courses_query);
$courses = [];
while ($row = mysqli_fetch_assoc($courses_result)) {
    $courses[] = $row;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_student':
                $first_name = sanitize_input($_POST['first_name']);
                $last_name = sanitize_input($_POST['last_name']);
                $student_id = sanitize_input($_POST['student_id']);
                $email = sanitize_input($_POST['email']);
                $phone = sanitize_input($_POST['phone']);
                $course_id = (int)$_POST['course'];
                $year_level = sanitize_input($_POST['year_level']);

                // Get course name for display
                $course_name = '';
                if ($course_id > 0) {
                    $course_query = "SELECT name FROM courses WHERE id = ?";
                    $course_stmt = mysqli_prepare($conn, $course_query);
                    mysqli_stmt_bind_param($course_stmt, "i", $course_id);
                    mysqli_stmt_execute($course_stmt);
                    $course_result = mysqli_stmt_get_result($course_stmt);
                    $course_data = mysqli_fetch_assoc($course_result);
                    $course_name = $course_data['name'] ?? '';
                }

                $insert_query = "INSERT INTO students (first_name, last_name, student_id, email, phone, course, year_level, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')";
                $insert_stmt = mysqli_prepare($conn, $insert_query);
                mysqli_stmt_bind_param($insert_stmt, "sssssss", $first_name, $last_name, $student_id, $email, $phone, $course_name, $year_level);

                if (mysqli_stmt_execute($insert_stmt)) {
                    $message = "Student added successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error adding student: " . mysqli_error($conn);
                    $message_type = "error";
                }
                break;
        }
    }
}

// Get search and filter parameters
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query
$where_conditions = [];
$params = [];
$param_types = '';

if ($search) {
    $where_conditions[] = "(first_name LIKE ? OR last_name LIKE ? OR student_id LIKE ? OR email LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $param_types .= 'ssss';
}

if ($status_filter) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM students s $where_clause";
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

// Get students
$students_query = "SELECT s.*, sp.phone, sai.year_level, sai.section
                   FROM students s
                   LEFT JOIN student_profiles sp ON s.id = sp.student_id
                   LEFT JOIN student_academic_info sai ON s.id = sai.student_id
                   $where_clause ORDER BY s.created_at DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$param_types .= 'ii';

$students_stmt = mysqli_prepare($conn, $students_query);
mysqli_stmt_bind_param($students_stmt, $param_types, ...$params);
mysqli_stmt_execute($students_stmt);
$students_result = mysqli_stmt_get_result($students_stmt);

$students = [];
while ($row = mysqli_fetch_assoc($students_result)) {
    $students[] = $row;
}

// Include the shared header
include 'includes/header.php';
?>

<div class="mb-6 sm:mb-8">
    <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Student Management</h1>
    <p class="text-sm sm:text-base text-gray-600">Manage student registrations and information</p>
</div>

<?php if ($message): ?>
    <div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200'; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<!-- Search and Filter -->
<div class="bg-white rounded-lg shadow-md p-4 sm:p-6 mb-6">
    <form method="GET" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                   placeholder="Search by name, ID, or email..."
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
            <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange text-sm">
                <option value="">All Status</option>
                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
            </select>
        </div>
        <div class="flex items-end space-x-2">
            <button type="submit" class="flex-1 bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition text-sm">
                <i class="fas fa-search mr-2"></i>Search
            </button>
            <a href="students.php" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition text-sm">
                <i class="fas fa-times"></i>
            </a>
        </div>
    </form>
</div>

<!-- Add Student Button -->
<div class="mb-6">
    <button onclick="openAddStudentModal()" class="w-full sm:w-auto bg-seait-orange text-white px-4 sm:px-6 py-2 rounded-lg hover:bg-orange-600 transition text-sm sm:text-base">
        <i class="fas fa-plus mr-2"></i>Add New Student
    </button>
</div>

<!-- Students Table -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
        <h2 class="text-base sm:text-lg font-medium text-gray-900">Students (<?php echo number_format($total_records); ?>)</h2>
    </div>

    <!-- Desktop Table -->
    <div class="hidden lg:block overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Registered</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($students)): ?>
                <tr>
                    <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                        No students found. <?php if ($search || $status_filter): ?>Try adjusting your search criteria.<?php else: ?>Add your first student to get started.<?php endif; ?>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($students as $student): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="h-10 w-10 rounded-full bg-seait-orange flex items-center justify-center mr-3">
                                    <span class="text-white font-medium"><?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?></span>
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($student['student_id']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($student['email'] ?? ''); ?></div>
                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($student['phone'] ?? 'N/A'); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">Not specified</div>
                            <div class="text-sm text-gray-500">Year <?php echo htmlspecialchars($student['year_level'] ?? 'Not specified'); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs rounded-full <?php
                                echo $student['status'] === 'active' ? 'bg-green-100 text-green-800' :
                                    ($student['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800');
                            ?>">
                                <?php echo ucfirst($student['status']); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo date('M d, Y', strtotime($student['created_at'])); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <a href="view-student.php?id=<?php echo $student['id']; ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="edit-student.php?id=<?php echo $student['id']; ?>" class="text-green-600 hover:text-green-900">
                                <i class="fas fa-edit"></i>
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
        <?php if (empty($students)): ?>
            <div class="p-4 text-center text-gray-500">
                No students found. <?php if ($search || $status_filter): ?>Try adjusting your search criteria.<?php else: ?>Add your first student to get started.<?php endif; ?>
            </div>
        <?php else: ?>
            <div class="p-4 space-y-4">
                <?php foreach ($students as $student): ?>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center">
                                <div class="h-10 w-10 rounded-full bg-seait-orange flex items-center justify-center mr-3">
                                    <span class="text-white font-medium"><?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?></span>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></p>
                                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($student['student_id']); ?></p>
                                </div>
                            </div>
                            <span class="px-2 py-1 text-xs rounded-full <?php
                                echo $student['status'] === 'active' ? 'bg-green-100 text-green-800' :
                                    ($student['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800');
                            ?>">
                                <?php echo ucfirst($student['status']); ?>
                            </span>
                        </div>

                        <div class="grid grid-cols-2 gap-4 text-sm mb-3">
                            <div>
                                <p class="text-gray-500">Email</p>
                                <p class="text-gray-900"><?php echo htmlspecialchars($student['email'] ?? ''); ?></p>
                            </div>
                            <div>
                                <p class="text-gray-500">Phone</p>
                                <p class="text-gray-900"><?php echo htmlspecialchars($student['phone'] ?? 'N/A'); ?></p>
                            </div>
                            <div>
                                <p class="text-gray-500">Course</p>
                                <p class="text-gray-900">Not specified</p>
                            </div>
                            <div>
                                <p class="text-gray-500">Year Level</p>
                                <p class="text-gray-900">Year <?php echo htmlspecialchars($student['year_level'] ?? 'Not specified'); ?></p>
                            </div>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-xs text-gray-400">Registered: <?php echo date('M d, Y', strtotime($student['created_at'])); ?></span>
                            <div class="flex space-x-2">
                                <a href="view-student.php?id=<?php echo $student['id']; ?>" class="text-blue-600 hover:text-blue-900">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit-student.php?id=<?php echo $student['id']; ?>" class="text-green-600 hover:text-green-900">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </div>
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

<!-- Add Student Modal -->
<div id="addStudentModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-lg mx-auto max-h-screen overflow-y-auto">
        <div class="flex items-center justify-between p-4 sm:p-6 border-b border-gray-200">
            <h3 class="text-lg sm:text-xl font-semibold text-seait-dark">Add New Student</h3>
            <button onclick="closeAddStudentModal()" class="text-gray-400 hover:text-gray-600 transition">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <form method="POST" class="p-4 sm:p-6">
            <input type="hidden" name="action" value="add_student">

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
                    <label class="block text-sm font-medium text-gray-700 mb-2">Student ID</label>
                    <input type="text" name="student_id" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                    <input type="email" name="email" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange text-sm">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Phone</label>
                    <input type="tel" name="phone"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Course</label>
                    <select name="course" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange text-sm">
                        <option value="">Select Course</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Year Level</label>
                <select name="year_level" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange text-sm">
                    <option value="">Select Year Level</option>
                    <option value="1">1st Year</option>
                    <option value="2">2nd Year</option>
                    <option value="3">3rd Year</option>
                    <option value="4">4th Year</option>
                </select>
            </div>

            <div class="flex flex-col sm:flex-row justify-end space-y-2 sm:space-y-0 sm:space-x-3">
                <button type="button" onclick="closeAddStudentModal()"
                        class="w-full sm:w-auto px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition text-sm">
                    Cancel
                </button>
                <button type="submit"
                        class="w-full sm:w-auto px-4 py-2 bg-seait-orange text-white rounded-lg hover:bg-orange-600 transition text-sm">
                    Add Student
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddStudentModal() {
    document.getElementById('addStudentModal').classList.remove('hidden');
}

function closeAddStudentModal() {
    document.getElementById('addStudentModal').classList.add('hidden');
}

// Close modal when clicking outside
document.addEventListener('click', function(event) {
    const modal = document.getElementById('addStudentModal');
    if (event.target === modal) {
        modal.classList.add('hidden');
    }
});
</script>

<?php
// Include the shared footer
include 'includes/footer.php';
?>