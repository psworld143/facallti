<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has student role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

// Set page title
$page_title = 'My Classes';

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'drop_class':
                $enrollment_id = (int)$_POST['enrollment_id'];

                // Get the student_id from students table
                $student_id = get_student_id($conn, $_SESSION['email']);

                $drop_query = "UPDATE class_enrollments SET status = 'dropped' WHERE id = ? AND student_id = ?";
                $drop_stmt = mysqli_prepare($conn, $drop_query);
                mysqli_stmt_bind_param($drop_stmt, "ii", $enrollment_id, $student_id);

                if (mysqli_stmt_execute($drop_stmt)) {
                    $message = "Successfully dropped from class!";
                    $message_type = "success";
                } else {
                    $message = "Error dropping class: " . mysqli_error($conn);
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
$student_id = get_student_id($conn, $_SESSION['email']);
$where_conditions = ["ce.student_id = ?"];
$params = [$student_id];
$param_types = 'i';

if ($search) {
    $where_conditions[] = "(cc.subject_title LIKE ? OR cc.subject_code LIKE ? OR f.first_name LIKE ? OR f.last_name LIKE ?)";
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
                JOIN teacher_classes tc ON ce.class_id = tc.id
                JOIN course_curriculum cc ON tc.subject_id = cc.id
                JOIN faculty f ON tc.teacher_id = f.id
                $where_clause";
$count_stmt = mysqli_prepare($conn, $count_query);
mysqli_stmt_bind_param($count_stmt, $param_types, ...$params);
mysqli_stmt_execute($count_stmt);
$count_result = mysqli_stmt_get_result($count_stmt);
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $per_page);

// Get enrolled classes
$classes_query = "SELECT ce.*, tc.section, tc.join_code, tc.status as class_status,
                  cc.subject_title, cc.subject_code, cc.units,
                  f.first_name as teacher_first_name, f.last_name as teacher_last_name
                  FROM class_enrollments ce
                  JOIN teacher_classes tc ON ce.class_id = tc.id
                  JOIN course_curriculum cc ON tc.subject_id = cc.id
                  JOIN faculty f ON tc.teacher_id = f.id
                  $where_clause
                  ORDER BY ce.join_date DESC
                  LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$param_types .= 'ii';

$classes_stmt = mysqli_prepare($conn, $classes_query);
mysqli_stmt_bind_param($classes_stmt, $param_types, ...$params);
mysqli_stmt_execute($classes_stmt);
$classes_result = mysqli_stmt_get_result($classes_stmt);

// Get enrollment statistics
$stats_query = "SELECT
                COUNT(*) as total_enrollments,
                COALESCE(SUM(CASE WHEN ce.status = 'enrolled' THEN 1 ELSE 0 END), 0) as active_enrollments,
                COALESCE(SUM(CASE WHEN ce.status = 'dropped' THEN 1 ELSE 0 END), 0) as dropped_enrollments,
                COALESCE(SUM(CASE WHEN ce.status = 'completed' THEN 1 ELSE 0 END), 0) as completed_enrollments
                FROM class_enrollments ce
                WHERE ce.student_id = ?";
$stats_stmt = mysqli_prepare($conn, $stats_query);
mysqli_stmt_bind_param($stats_stmt, "i", $student_id);
mysqli_stmt_execute($stats_stmt);
$stats_result = mysqli_stmt_get_result($stats_stmt);
$stats = mysqli_fetch_assoc($stats_result);

// Include the shared header
include 'includes/header.php';
?>

<div class="mb-6 sm:mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">My Classes</h1>
            <p class="text-sm sm:text-base text-gray-600">View and manage your enrolled classes</p>
        </div>
        <a href="join-class.php" class="bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition">
            <i class="fas fa-plus-circle mr-2"></i>Join New Class
        </a>
    </div>
</div>

<!-- Message Display -->
<?php if ($message): ?>
<div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800'; ?>">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 sm:grid-cols-4 gap-4 sm:gap-6 mb-6 sm:mb-8">
    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-4 sm:p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-chalkboard text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Total Classes</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['total_enrollments'] ?? 0); ?></dd>
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
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Active Classes</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['active_enrollments'] ?? 0); ?></dd>
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
                        <i class="fas fa-times text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Dropped Classes</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['dropped_enrollments'] ?? 0); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-4 sm:p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-purple-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-graduation-cap text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Completed Classes</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['completed_enrollments'] ?? 0); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Search and Filter -->
<div class="mb-6 bg-white p-4 rounded-lg shadow-md">
    <form method="GET" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                   placeholder="Search by subject or teacher"
                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                <option value="">All Status</option>
                <option value="enrolled" <?php echo $status_filter === 'enrolled' ? 'selected' : ''; ?>>Enrolled</option>
                <option value="dropped" <?php echo $status_filter === 'dropped' ? 'selected' : ''; ?>>Dropped</option>
                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
            </select>
        </div>
        <div class="flex items-end">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">
                <i class="fas fa-search mr-2"></i>Search
            </button>
            <?php if ($search || $status_filter): ?>
            <a href="my-classes.php" class="ml-2 bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition">
                <i class="fas fa-times mr-2"></i>Clear
            </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Classes Cards -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200">
        <h2 class="text-lg font-medium text-gray-900">Your Classes (<?php echo $total_records; ?>)</h2>
    </div>

    <?php if (mysqli_num_rows($classes_result) == 0): ?>
        <div class="p-6 text-center">
            <i class="fas fa-chalkboard text-gray-300 text-4xl mb-4"></i>
            <p class="text-gray-500">No classes found. Join a class to get started.</p>
            <a href="join-class.php" class="mt-4 inline-block bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition">
                Join Your First Class
            </a>
        </div>
    <?php else: ?>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php while ($class = mysqli_fetch_assoc($classes_result)): ?>
                <div class="bg-white rounded-lg shadow-md overflow-hidden border border-gray-200 hover:shadow-lg transition-shadow class-card flex flex-col h-full">
                    <!-- Header Photo -->
                    <div class="h-32 relative <?php
                        echo $class['status'] === 'enrolled' ? 'header-gradient' :
                            ($class['status'] === 'dropped' ? 'bg-gradient-to-br from-red-500 to-red-600' : 'bg-gradient-to-br from-purple-500 to-purple-600');
                    ?>">
                        <div class="absolute inset-0 card-header-overlay"></div>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <div class="text-center text-white">
                                <i class="fas fa-chalkboard-teacher text-4xl mb-2"></i>
                                <div class="text-sm font-medium"><?php echo htmlspecialchars($class['subject_code']); ?></div>
                            </div>
                        </div>
                        <!-- Status Badge -->
                        <div class="absolute top-3 right-3">
                            <span class="px-2 py-1 text-xs rounded-full status-badge <?php
                                echo $class['status'] === 'enrolled' ? 'text-green-800' :
                                    ($class['status'] === 'dropped' ? 'text-red-800' : 'text-purple-800');
                            ?>">
                                <?php echo ucfirst($class['status']); ?>
                            </span>
                        </div>
                    </div>

                    <!-- Card Content -->
                    <div class="p-4 flex-1 flex flex-col">
                        <!-- Subject Title -->
                        <h3 class="text-lg font-semibold text-gray-900 mb-2 line-clamp-2">
                            <?php echo htmlspecialchars($class['subject_title']); ?>
                        </h3>

                        <!-- Subject Details -->
                        <div class="space-y-2 mb-4">
                            <div class="flex items-center text-sm text-gray-600">
                                <i class="fas fa-hashtag w-4 mr-2"></i>
                                <span><?php echo htmlspecialchars($class['subject_code']); ?></span>
                            </div>
                            <div class="flex items-center text-sm text-gray-600">
                                <i class="fas fa-users w-4 mr-2"></i>
                                <span>Section <?php echo htmlspecialchars($class['section']); ?></span>
                            </div>
                            <div class="flex items-center text-sm text-gray-600">
                                <i class="fas fa-graduation-cap w-4 mr-2"></i>
                                <span><?php echo $class['units']; ?> units</span>
                            </div>
                        </div>

                        <!-- Teacher Info -->
                        <div class="flex items-center mb-4 p-3 bg-gray-50 rounded-lg">
                            <div class="h-10 w-10 rounded-full teacher-avatar flex items-center justify-center mr-3">
                                <span class="text-white text-sm font-medium">
                                    <?php echo strtoupper(substr($class['teacher_first_name'], 0, 1) . substr($class['teacher_last_name'], 0, 1)); ?>
                                </span>
                            </div>
                            <div>
                                <div class="text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($class['teacher_first_name'] . ' ' . $class['teacher_last_name']); ?>
                                </div>
                                <div class="text-xs text-gray-500">Teacher</div>
                            </div>
                        </div>

                        <!-- Join Date -->
                        <div class="flex items-center text-sm text-gray-500 mb-4">
                            <i class="fas fa-calendar-alt w-4 mr-2"></i>
                            <span>Joined <?php echo date('M d, Y', strtotime($class['join_date'])); ?></span>
                        </div>

                        <!-- Actions or Status at the bottom -->
                        <div class="mt-auto">
                            <?php if ($class['status'] === 'enrolled'): ?>
                            <div class="flex space-x-2">
                                <a href="class_dashboard.php?class_id=<?php echo $class['class_id']; ?>"
                                   class="flex-1 bg-blue-600 text-white text-center py-2 px-3 rounded-md hover:bg-blue-700 transition text-sm font-medium action-btn">
                                    <i class="fas fa-door-open mr-1"></i>Open Class
                                </a>
                                <button onclick="dropClass(<?php echo $class['id']; ?>)"
                                        class="bg-red-600 text-white py-2 px-3 rounded-md hover:bg-red-700 transition text-sm font-medium action-btn">
                                    <i class="fas fa-user-minus"></i>
                                </button>
                            </div>
                            <?php else: ?>
                            <div class="text-center py-2 text-sm text-gray-500">
                                <?php if ($class['status'] === 'dropped'): ?>
                                    <i class="fas fa-times-circle mr-1"></i>Dropped
                                <?php else: ?>
                                    <i class="fas fa-check-circle mr-1"></i>Completed
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="px-6 py-4 border-t border-gray-200">
            <div class="flex items-center justify-between">
                <div class="text-sm text-gray-700">
                    Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total_records); ?> of <?php echo $total_records; ?> results
                </div>
                <div class="flex space-x-2">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>"
                       class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                        Previous
                    </a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>"
                       class="px-3 py-2 text-sm <?php echo $i === $page ? 'bg-seait-orange text-white' : 'bg-white border border-gray-300 hover:bg-gray-50'; ?> rounded-md">
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>"
                       class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                        Next
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
function dropClass(enrollmentId) {
    if (confirm('Are you sure you want to drop this class? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="drop_class">
            <input type="hidden" name="enrollment_id" value="${enrollmentId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php
// Include the shared footer
include 'includes/footer.php';
?>