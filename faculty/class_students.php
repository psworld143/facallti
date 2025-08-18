<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has teacher role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

// Get class_id from URL
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : null;

if (!$class_id) {
    header('Location: class-management.php');
    exit();
}

// Verify the class belongs to the logged-in teacher
$class_query = "SELECT tc.*, cc.subject_title, cc.subject_code, cc.units, cc.description as subject_description
                FROM teacher_classes tc
                JOIN course_curriculum cc ON tc.subject_id = cc.id
                JOIN faculty f ON tc.teacher_id = f.id
                WHERE tc.id = ? AND f.email = ? AND f.is_active = 1";
$class_stmt = mysqli_prepare($conn, $class_query);
mysqli_stmt_bind_param($class_stmt, "is", $class_id, $_SESSION['username']);
mysqli_stmt_execute($class_stmt);
$class_result = mysqli_stmt_get_result($class_stmt);
$class_data = mysqli_fetch_assoc($class_result);

if (!$class_data) {
    header('Location: class-management.php');
    exit();
}

// Set page title
$page_title = 'Students';

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'remove_student':
                $enrollment_id = (int)$_POST['enrollment_id'];

                $remove_query = "UPDATE class_enrollments SET status = 'dropped' WHERE id = ? AND class_id = ?";
                $remove_stmt = mysqli_prepare($conn, $remove_query);
                mysqli_stmt_bind_param($remove_stmt, "ii", $enrollment_id, $class_id);

                if (mysqli_stmt_execute($remove_stmt)) {
                    $message = "Student removed from class successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error removing student: " . mysqli_error($conn);
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

// Build query for enrolled students
$where_conditions = ["ce.class_id = ?"];
$params = [$class_id];
$param_types = 'i';

if ($search) {
    $where_conditions[] = "(s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_id LIKE ? OR s.email LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $param_types .= 'ssss';
}

if ($status_filter) {
    $where_conditions[] = "ce.status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM class_enrollments ce
                JOIN students s ON ce.student_id = s.id
                $where_clause";
$count_stmt = mysqli_prepare($conn, $count_query);
mysqli_stmt_bind_param($count_stmt, $param_types, ...$params);
mysqli_stmt_execute($count_stmt);
$count_result = mysqli_stmt_get_result($count_stmt);
$total_records = mysqli_fetch_assoc($count_result)['total'];

$total_pages = ceil($total_records / $per_page);

// Get enrolled students
$students_query = "SELECT ce.*, s.first_name, s.last_name, s.student_id, s.email
                  FROM class_enrollments ce
                  JOIN students s ON ce.student_id = s.id
                  $where_clause
                  ORDER BY s.last_name, s.first_name
                  LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$param_types .= 'ii';

$students_stmt = mysqli_prepare($conn, $students_query);
mysqli_stmt_bind_param($students_stmt, $param_types, ...$params);
mysqli_stmt_execute($students_stmt);
$students_result = mysqli_stmt_get_result($students_stmt);

// Include the LMS header
include 'includes/lms_header.php';
?>

<div class="mb-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark">Class Students</h1>
            <p class="text-gray-600 mt-1">Manage enrolled students in <?php echo htmlspecialchars($class_data['subject_title'] . ' - ' . $class_data['section']); ?></p>
        </div>
        <div class="mt-4 sm:mt-0 flex space-x-2">
            <a href="class_dashboard.php?class_id=<?php echo $class_id; ?>" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition">
                <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
            </a>
        </div>
    </div>
</div>

<?php if ($message): ?>
<div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
    <?php echo $message; ?>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <form method="GET" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">

        <div>
            <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
            <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent"
                   placeholder="Search by name, student ID, or email...">
        </div>

        <div>
            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select id="status" name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                <option value="">All Status</option>
                <option value="enrolled" <?php echo $status_filter === 'enrolled' ? 'selected' : ''; ?>>Enrolled</option>
                <option value="dropped" <?php echo $status_filter === 'dropped' ? 'selected' : ''; ?>>Dropped</option>
                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
            </select>
        </div>

        <div class="flex items-end">
            <button type="submit" class="w-full bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition">
                <i class="fas fa-search mr-2"></i>Filter
            </button>
        </div>
    </form>
</div>

<!-- Students List -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900">Enrolled Students (<?php echo $total_records; ?>)</h2>
            <div class="text-sm text-gray-500">
                Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total_records); ?> of <?php echo $total_records; ?> students
            </div>
        </div>
    </div>

    <?php if (mysqli_num_rows($students_result) === 0): ?>
    <div class="p-8 text-center">
        <i class="fas fa-users text-gray-300 text-4xl mb-4"></i>
        <p class="text-gray-500 mb-4">No students found matching your criteria.</p>
        <?php if ($search || $status_filter): ?>
        <a href="?class_id=<?php echo $class_id; ?>" class="bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition">
            Clear Filters
        </a>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Enrolled</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php while ($student = mysqli_fetch_assoc($students_result)): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="h-10 w-10 rounded-full bg-seait-orange flex items-center justify-center mr-3">
                                <span class="text-white font-medium"><?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?></span>
                            </div>
                            <div>
                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($student['email']); ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($student['student_id']); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($student['email']); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                            <?php
                            switch($student['status']) {
                                case 'enrolled':
                                    echo 'bg-green-100 text-green-800';
                                    break;
                                case 'dropped':
                                    echo 'bg-red-100 text-red-800';
                                    break;
                                case 'completed':
                                    echo 'bg-blue-100 text-blue-800';
                                    break;
                                default:
                                    echo 'bg-gray-100 text-gray-800';
                            }
                            ?>">
                            <?php echo ucfirst($student['status']); ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <?php echo date('M j, Y', strtotime($student['created_at'])); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <?php if ($student['status'] === 'enrolled'): ?>
                        <button onclick="removeStudent(<?php echo $student['id']; ?>)"
                                class="text-red-600 hover:text-red-800" title="Remove from class">
                            <i class="fas fa-user-minus"></i>
                        </button>
                        <?php else: ?>
                        <span class="text-gray-400">Removed</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
        <div class="flex items-center justify-between">
            <div class="flex-1 flex justify-between sm:hidden">
                <?php if ($page > 1): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>"
                   class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    Previous
                </a>
                <?php endif; ?>
                <?php if ($page < $total_pages): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>"
                   class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    Next
                </a>
                <?php endif; ?>
            </div>
            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm text-gray-700">
                        Showing <span class="font-medium"><?php echo $offset + 1; ?></span> to
                        <span class="font-medium"><?php echo min($offset + $per_page, $total_records); ?></span> of
                        <span class="font-medium"><?php echo $total_records; ?></span> results
                    </p>
                </div>
                <div>
                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"
                           class="relative inline-flex items-center px-4 py-2 border text-sm font-medium <?php echo $i === $page ? 'z-10 bg-seait-orange border-seait-orange text-white' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'; ?>">
                            <?php echo $i; ?>
                        </a>
                        <?php endfor; ?>
                    </nav>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<script>
function removeStudent(enrollmentId) {
    if (confirm('Are you sure you want to remove this student from the class? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="remove_student">
            <input type="hidden" name="enrollment_id" value="${enrollmentId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include 'includes/lms_footer.php'; ?>