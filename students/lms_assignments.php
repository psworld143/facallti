<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has student role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

// Get class_id from URL
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : null;

if (!$class_id) {
    header('Location: my-classes.php');
    exit();
}

// Get student_id for verification
$student_id = get_student_id($conn, $_SESSION['email']);

// Verify student is enrolled in this class
$class_query = "SELECT ce.*, tc.section, tc.join_code, tc.status as class_status,
                cc.subject_title, cc.subject_code, cc.units, cc.description as subject_description,
                f.id as teacher_id, f.first_name as teacher_first_name, f.last_name as teacher_last_name,
                f.email as teacher_email
                FROM class_enrollments ce
                JOIN teacher_classes tc ON ce.class_id = tc.id
                JOIN course_curriculum cc ON tc.subject_id = cc.id
                JOIN faculty f ON tc.teacher_id = f.id
                WHERE ce.class_id = ? AND ce.student_id = ? AND ce.status = 'enrolled'";
$class_stmt = mysqli_prepare($conn, $class_query);
mysqli_stmt_bind_param($class_stmt, "ii", $class_id, $student_id);
mysqli_stmt_execute($class_stmt);
$class_result = mysqli_stmt_get_result($class_stmt);
$class_data = mysqli_fetch_assoc($class_result);

if (!$class_data) {
    header('Location: my-classes.php');
    exit();
}

// Set page title
$page_title = 'Assignments - ' . $class_data['subject_title'];

// Get search and filter parameters
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';

// Get assignment categories
$categories_query = "SELECT id, name, description, color FROM lms_assignment_categories WHERE status = 'active' ORDER BY name";
$categories_result = mysqli_query($conn, $categories_query);

// Build assignments query
$where_conditions = ["a.class_id = ?", "a.status = 'published'"];
$params = [$class_id];
$param_types = 'i';

if ($search) {
    $where_conditions[] = "(a.title LIKE ? OR a.description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'ss';
}

if ($category_filter) {
    $where_conditions[] = "a.category_id = ?";
    $params[] = $category_filter;
    $param_types .= 'i';
}

if ($status_filter) {
    if ($status_filter === 'upcoming') {
        $where_conditions[] = "a.due_date > NOW()";
    } elseif ($status_filter === 'overdue') {
        $where_conditions[] = "a.due_date < NOW()";
    }
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get assignments
$assignments_query = "SELECT a.*, ac.name as category_name, ac.color as category_color,
                     COUNT(s.id) as submission_count,
                     COUNT(CASE WHEN s.status = 'graded' THEN 1 END) as graded_count,
                     f.first_name as created_by_name, f.last_name as created_by_last_name,
                     s.id as submission_id, s.status as submission_status, s.score, s.submitted_at,
                     CASE
                         WHEN a.due_date < NOW() THEN 'overdue'
                         WHEN s.id IS NOT NULL THEN 'completed'
                         ELSE 'pending'
                     END as status,
                     CASE
                         WHEN a.due_date < NOW() THEN 'Overdue'
                         WHEN a.due_date <= DATE_ADD(NOW(), INTERVAL 1 DAY) THEN 'Due Today'
                         WHEN a.due_date <= DATE_ADD(NOW(), INTERVAL 3 DAY) THEN 'Due Soon'
                         ELSE CONCAT('Due in ', DATEDIFF(a.due_date, NOW()), ' days')
                     END as time_remaining
                     FROM lms_assignments a
                     JOIN lms_assignment_categories ac ON a.category_id = ac.id
                     JOIN faculty f ON a.created_by = f.id
                     LEFT JOIN lms_assignment_submissions s ON a.id = s.assignment_id AND s.student_id = ?
                     $where_clause
                     GROUP BY a.id
                     ORDER BY a.due_date ASC";

$params[] = $student_id;
$param_types .= 'i';

$assignments_stmt = mysqli_prepare($conn, $assignments_query);
if (!empty($params)) {
    mysqli_stmt_bind_param($assignments_stmt, $param_types, ...$params);
}
mysqli_stmt_execute($assignments_stmt);
$assignments_result = mysqli_stmt_get_result($assignments_stmt);

// Get assignment statistics
$stats_query = "SELECT
                COUNT(DISTINCT a.id) as total_assignments,
                COUNT(DISTINCT s.assignment_id) as submitted_assignments,
                COUNT(CASE WHEN s.status = 'graded' THEN 1 END) as graded_assignments,
                COUNT(CASE WHEN a.due_date < NOW() AND s.id IS NULL THEN 1 END) as overdue_assignments
                FROM lms_assignments a
                LEFT JOIN lms_assignment_submissions s ON a.id = s.assignment_id AND s.student_id = ?
                WHERE a.class_id = ? AND a.status = 'published'";
$stats_stmt = mysqli_prepare($conn, $stats_query);
mysqli_stmt_bind_param($stats_stmt, "ii", $student_id, $class_id);
mysqli_stmt_execute($stats_stmt);
$stats_result = mysqli_stmt_get_result($stats_stmt);
$stats = mysqli_fetch_assoc($stats_result);

// Include the shared LMS header
include 'includes/lms_header.php';
?>

<div class="mb-6 sm:mb-8">
    <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Class Assignments</h1>
    <p class="text-sm sm:text-base text-gray-600">View and submit your assignments for <?php echo htmlspecialchars($class_data['subject_title']); ?></p>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 sm:grid-cols-4 gap-4 sm:gap-6 mb-6 sm:mb-8">
    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-4 sm:p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-tasks text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Total Assignments</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['total_assignments'] ?? 0); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-4 sm:p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-check text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Completed</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['submitted_assignments'] ?? 0); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-4 sm:p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-yellow-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-clock text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Pending</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format(($stats['total_assignments'] ?? 0) - ($stats['submitted_assignments'] ?? 0)); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-4 sm:p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-red-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Overdue</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['overdue_assignments'] ?? 0); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Search and Filter -->
<div class="bg-white rounded-lg shadow-md overflow-hidden mb-6 sm:mb-8">
    <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-medium text-gray-900">Search & Filter Assignments</h3>
    </div>
    <div class="p-6">
        <form method="GET" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                       placeholder="Search assignments..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="overdue" <?php echo $status_filter === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">
                    <i class="fas fa-search mr-2"></i>Search
                </button>
                <?php if ($search || $status_filter): ?>
                <a href="lms_assignments.php?class_id=<?php echo $class_id; ?>" class="ml-2 bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition">
                    <i class="fas fa-times mr-2"></i>Clear
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Assignments List -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-medium text-gray-900">Course Assignments (<?php echo mysqli_num_rows($assignments_result); ?>)</h3>
            <button onclick="createAssignment()" class="bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition">
                <i class="fas fa-plus-circle mr-2"></i>New Assignment
            </button>
        </div>
    </div>

    <?php if (mysqli_num_rows($assignments_result) == 0): ?>
        <div class="p-6 text-center">
            <i class="fas fa-tasks text-gray-300 text-4xl mb-4"></i>
            <p class="text-gray-500">No assignments found.</p>
            <p class="text-sm text-gray-400 mt-2">Check back later for new assignments.</p>
        </div>
    <?php else: ?>
        <div class="divide-y divide-gray-200">
            <?php while ($assignment = mysqli_fetch_assoc($assignments_result)): ?>
            <div class="p-6 hover:bg-gray-50 transition-colors">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center mb-2">
                            <?php if (isset($assignment['is_urgent']) && $assignment['is_urgent']): ?>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800 mr-2">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>Urgent
                                </span>
                            <?php endif; ?>
                            <h3 class="text-lg font-medium text-gray-900">
                                <a href="assignment_detail.php?assignment_id=<?php echo $assignment['id']; ?>&class_id=<?php echo $class_id; ?>"
                                   class="hover:text-seait-orange transition-colors">
                                    <?php echo htmlspecialchars($assignment['title']); ?>
                                </a>
                            </h3>
                        </div>
                        <p class="text-gray-600 mb-3"><?php echo htmlspecialchars(substr($assignment['description'], 0, 150)) . (strlen($assignment['description']) > 150 ? '...' : ''); ?></p>
                        <div class="flex items-center text-sm text-gray-500 space-x-4">
                            <span class="flex items-center">
                                <i class="fas fa-calendar mr-1"></i>
                                Due: <?php echo date('M d, Y', strtotime($assignment['due_date'])); ?>
                            </span>
                            <span class="flex items-center">
                                <i class="fas fa-clock mr-1"></i>
                                <?php echo isset($assignment['time_remaining']) ? $assignment['time_remaining'] : 'N/A'; ?>
                            </span>
                            <span class="flex items-center">
                                <i class="fas fa-percentage mr-1"></i>
                                <?php echo isset($assignment['weight']) ? $assignment['weight'] : '0'; ?>% of grade
                            </span>
                        </div>
                    </div>
                    <div class="ml-4">
                        <span class="px-2 py-1 text-xs rounded-full <?php
                            $status = isset($assignment['status']) ? $assignment['status'] : 'pending';
                            echo $status === 'completed' ? 'bg-green-100 text-green-800' :
                                ($status === 'overdue' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800');
                        ?>">
                            <?php echo ucfirst($status); ?>
                        </span>
                        <div class="mt-2">
                            <a href="assignment_detail.php?assignment_id=<?php echo $assignment['id']; ?>&class_id=<?php echo $class_id; ?>"
                               class="inline-flex items-center px-3 py-2 bg-seait-orange text-white text-sm rounded-lg hover:bg-orange-600 transition">
                                <i class="fas fa-eye mr-2"></i>View
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function createAssignment() {
    // This would typically open a modal or redirect to a create assignment page
    alert('Create assignment functionality would be implemented here.');
}
</script>

<?php
// Include the shared LMS footer
include 'includes/lms_footer.php';
?>