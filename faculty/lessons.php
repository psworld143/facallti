<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has teacher role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

// Set page title
$page_title = 'Lesson Management';

$message = '';
$message_type = '';

// Handle messages from redirects
if (isset($_GET['message'])) {
    $message = $_GET['message'];
    $message_type = $_GET['type'] ?? 'success';
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'delete_lesson':
                $lesson_id = (int)$_POST['lesson_id'];

                // Get file path before deleting
                $file_query = "SELECT file_path FROM lessons WHERE id = ? AND teacher_id = ?";
                $file_stmt = mysqli_prepare($conn, $file_query);
                mysqli_stmt_bind_param($file_stmt, "ii", $lesson_id, $_SESSION['user_id']);
                mysqli_stmt_execute($file_stmt);
                $file_result = mysqli_stmt_get_result($file_stmt);
                $file_data = mysqli_fetch_assoc($file_result);

                // Start transaction
                mysqli_begin_transaction($conn);

                try {
                    // Delete class assignments first (due to foreign key constraint)
                    $delete_assignments_query = "DELETE FROM lesson_class_assignments WHERE lesson_id = ?";
                    $delete_assignments_stmt = mysqli_prepare($conn, $delete_assignments_query);
                    mysqli_stmt_bind_param($delete_assignments_stmt, "i", $lesson_id);
                    mysqli_stmt_execute($delete_assignments_stmt);

                    // Delete lesson
                    $delete_query = "DELETE FROM lessons WHERE id = ? AND teacher_id = ?";
                    $delete_stmt = mysqli_prepare($conn, $delete_query);
                    mysqli_stmt_bind_param($delete_stmt, "ii", $lesson_id, $_SESSION['user_id']);

                    if (mysqli_stmt_execute($delete_stmt)) {
                        // Delete file if exists
                        if ($file_data && $file_data['file_path'] && file_exists($file_data['file_path'])) {
                            unlink($file_data['file_path']);
                        }
                        mysqli_commit($conn);
                        $message = "Lesson deleted successfully!";
                        $message_type = "success";
                    } else {
                        throw new Exception("Error deleting lesson: " . mysqli_error($conn));
                    }
                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    $message = $e->getMessage();
                    $message_type = "error";
                }
                break;
        }
    }
}

// Get search and filter parameters
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$class_filter = isset($_GET['class_id']) ? (int)$_GET['class_id'] : '';
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$type_filter = isset($_GET['lesson_type']) ? sanitize_input($_GET['lesson_type']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Handle messages from redirects
if (isset($_GET['message'])) {
    $message = $_GET['message'];
    $message_type = $_GET['type'] ?? 'success';
}

// Build query for lessons
$where_conditions = ["l.teacher_id = ?"];
$params = [$_SESSION['user_id']];
$param_types = 'i';

if ($search) {
    $where_conditions[] = "(l.title LIKE ? OR l.description LIKE ? OR l.content LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
    $param_types .= 'sss';
}

if ($class_filter) {
    $where_conditions[] = "lca.class_id = ?";
    $params[] = $class_filter;
    $param_types .= 'i';
}

if ($status_filter) {
    $where_conditions[] = "l.status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

if ($type_filter) {
    $where_conditions[] = "l.lesson_type = ?";
    $params[] = $type_filter;
    $param_types .= 's';
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get total count for pagination
$count_query = "SELECT COUNT(DISTINCT l.id) as total FROM lessons l
                LEFT JOIN lesson_class_assignments lca ON l.id = lca.lesson_id
                $where_clause";
$count_stmt = mysqli_prepare($conn, $count_query);
mysqli_stmt_bind_param($count_stmt, $param_types, ...$params);
mysqli_stmt_execute($count_stmt);
$total_records = mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt))['total'];
$total_pages = ceil($total_records / $per_page);

// Get lessons with class information
$lessons_query = "SELECT DISTINCT l.*,
                  GROUP_CONCAT(CONCAT(cc.subject_code, ' - ', cc.subject_title, ' (', tc.section, ')') SEPARATOR ', ') as assigned_classes,
                  COUNT(lca.class_id) as class_count
                  FROM lessons l
                  LEFT JOIN lesson_class_assignments lca ON l.id = lca.lesson_id
                  LEFT JOIN teacher_classes tc ON lca.class_id = tc.id
                  LEFT JOIN course_curriculum cc ON tc.subject_id = cc.id
                  $where_clause
                  GROUP BY l.id
                  ORDER BY l.order_number, l.created_at DESC
                  LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$param_types .= 'ii';

$lessons_stmt = mysqli_prepare($conn, $lessons_query);
mysqli_stmt_bind_param($lessons_stmt, $param_types, ...$params);
mysqli_stmt_execute($lessons_stmt);
$lessons_result = mysqli_stmt_get_result($lessons_stmt);

// Get classes for filter dropdown and form
$classes_query = "SELECT tc.id, tc.section, cc.subject_title, cc.subject_code
                  FROM teacher_classes tc
                  JOIN course_curriculum cc ON tc.subject_id = cc.id
                  WHERE tc.teacher_id = ?
                  ORDER BY cc.subject_title, tc.section";
$classes_stmt = mysqli_prepare($conn, $classes_query);
mysqli_stmt_bind_param($classes_stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($classes_stmt);
$classes_result = mysqli_stmt_get_result($classes_stmt);

// Get statistics
$stats_query = "SELECT
                COUNT(DISTINCT l.id) as total_lessons,
                COUNT(DISTINCT CASE WHEN l.status = 'published' THEN l.id END) as published_lessons,
                COUNT(DISTINCT CASE WHEN l.status = 'draft' THEN l.id END) as draft_lessons,
                COUNT(DISTINCT lca.class_id) as total_class_assignments
                FROM lessons l
                LEFT JOIN lesson_class_assignments lca ON l.id = lca.lesson_id
                WHERE l.teacher_id = ?";
$stats_stmt = mysqli_prepare($conn, $stats_query);
mysqli_stmt_bind_param($stats_stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($stats_stmt);
$stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stats_stmt));

// Include the shared header
$sidebar_context = 'main';
include 'includes/unified-header.php';
?>

<div class="mb-6 sm:mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Lesson Management</h1>
            <p class="text-sm sm:text-base text-gray-600">Create and manage lesson materials for your classes</p>
        </div>
        <a href="create-lesson.php" class="bg-seait-orange text-white px-4 py-2 rounded-md hover:bg-orange-600 transition">
            <i class="fas fa-plus mr-2"></i>Add Lesson
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
                        <i class="fas fa-book text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Total Lessons</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['total_lessons'] ?? 0); ?></dd>
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
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Published</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['published_lessons'] ?? 0); ?></dd>
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
                        <i class="fas fa-edit text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Drafts</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['draft_lessons'] ?? 0); ?></dd>
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
                        <i class="fas fa-chalkboard text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Class Assignments</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['total_class_assignments'] ?? 0); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Search and Filter -->
<div class="mb-6 bg-white p-4 rounded-lg shadow-md">
    <form method="GET" class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                   placeholder="Search lessons..."
                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Class</label>
            <select name="class_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                <option value="">All Classes</option>
                <?php while ($class = mysqli_fetch_assoc($classes_result)): ?>
                <option value="<?php echo $class['id']; ?>" <?php echo $class_filter == $class['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($class['subject_code'] . ' - ' . $class['subject_title'] . ' (' . $class['section'] . ')'); ?>
                </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                <option value="">All Status</option>
                <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                <option value="published" <?php echo $status_filter === 'published' ? 'selected' : ''; ?>>Published</option>
                <option value="archived" <?php echo $status_filter === 'archived' ? 'selected' : ''; ?>>Archived</option>
            </select>
        </div>
        <div class="flex items-end">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">
                <i class="fas fa-search mr-2"></i>Search
            </button>
            <?php if ($search || $class_filter || $status_filter || $type_filter): ?>
            <a href="lessons.php" class="ml-2 bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition">
                <i class="fas fa-times mr-2"></i>Clear
            </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Lessons Grid -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200">
        <h2 class="text-lg font-medium text-gray-900">Lessons (<?php echo $total_records; ?>)</h2>
    </div>

    <?php if (mysqli_num_rows($lessons_result) == 0): ?>
        <div class="p-8 text-center">
            <i class="fas fa-book text-gray-300 text-5xl mb-6"></i>
            <p class="text-gray-500 text-lg mb-6">No lessons found matching your criteria.</p>
            <a href="create-lesson.php" class="inline-block bg-seait-orange text-white px-6 py-3 rounded-md hover:bg-orange-600 transition text-base font-medium">
                <i class="fas fa-plus mr-2"></i>Create Your First Lesson
            </a>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 p-6">
            <?php while ($lesson = mysqli_fetch_assoc($lessons_result)): ?>
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow lesson-card">
                <div class="p-4">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex items-center">
                            <div class="w-10 h-10 rounded-lg bg-seait-orange flex items-center justify-center mr-3">
                                <i class="fas fa-book text-white"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($lesson['title']); ?></h3>
                                <p class="text-xs text-gray-500"><?php echo $lesson['class_count']; ?> class(es) assigned</p>
                            </div>
                        </div>
                        <div class="flex space-x-1">
                            <button onclick="viewLesson(<?php echo $lesson['id']; ?>)" class="text-blue-600 hover:text-blue-800" title="View">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button onclick="editLesson(<?php echo $lesson['id']; ?>)" class="text-green-600 hover:text-green-800" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="reuseLesson(<?php echo $lesson['id']; ?>)" class="text-purple-600 hover:text-purple-800" title="Reuse">
                                <i class="fas fa-copy"></i>
                            </button>
                            <button onclick="deleteLesson(<?php echo $lesson['id']; ?>)" class="text-red-600 hover:text-red-800" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>

                    <p class="text-sm text-gray-600 mb-3 line-clamp-2"><?php echo htmlspecialchars($lesson['description']); ?></p>

                    <div class="flex items-center justify-between text-xs text-gray-500 mb-3">
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php echo $lesson['status'] === 'published' ? 'bg-green-100 text-green-800' : ($lesson['status'] === 'draft' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800'); ?>">
                            <?php echo ucfirst($lesson['status']); ?>
                        </span>
                        <span class="capitalize"><?php echo $lesson['lesson_type']; ?></span>
                    </div>

                    <?php if ($lesson['assigned_classes']): ?>
                    <div class="text-xs text-gray-500 mb-3">
                        <div class="font-medium mb-1">Assigned to:</div>
                        <div class="line-clamp-2"><?php echo htmlspecialchars($lesson['assigned_classes']); ?></div>
                    </div>
                    <?php endif; ?>

                    <?php if ($lesson['file_name']): ?>
                    <div class="flex items-center text-xs text-gray-500 mb-3">
                        <i class="fas fa-paperclip mr-1"></i>
                        <span class="truncate"><?php echo htmlspecialchars($lesson['file_name']); ?></span>
                    </div>
                    <?php endif; ?>

                    <div class="text-xs text-gray-400">
                        Created: <?php echo date('M j, Y', strtotime($lesson['created_at'])); ?>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
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
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&class_id=<?php echo urlencode($class_filter); ?>&status=<?php echo urlencode($status_filter); ?>&lesson_type=<?php echo urlencode($type_filter); ?>"
                       class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                        Previous
                    </a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&class_id=<?php echo urlencode($class_filter); ?>&status=<?php echo urlencode($status_filter); ?>&lesson_type=<?php echo urlencode($type_filter); ?>"
                       class="px-3 py-2 text-sm <?php echo $i === $page ? 'bg-seait-orange text-white' : 'bg-white border border-gray-300 hover:bg-gray-50'; ?> rounded-md">
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&class_id=<?php echo urlencode($class_filter); ?>&status=<?php echo urlencode($status_filter); ?>&lesson_type=<?php echo urlencode($type_filter); ?>"
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
function viewLesson(lessonId) {
    window.location.href = 'view-lesson.php?id=' + lessonId;
}

function editLesson(lessonId) {
    window.location.href = 'edit-lesson.php?id=' + lessonId;
}

function reuseLesson(lessonId) {
    window.location.href = 'create-lesson.php?reuse_id=' + lessonId;
}

function deleteLesson(lessonId) {
    if (confirm('Are you sure you want to delete this lesson? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_lesson">
            <input type="hidden" name="lesson_id" value="${lessonId}">
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

<style>
/* Line clamp utilities for text truncation */
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.line-clamp-3 {
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Ensure proper spacing for lesson cards */
.lesson-card {
    min-height: 200px;
}

.lesson-card .flex {
    align-items: flex-start;
}

.lesson-card .text-sm {
    line-height: 1.4;
}

/* Fix for button spacing in lesson cards */
.lesson-card .flex.space-x-1 > * {
    margin-right: 0.25rem;
}

.lesson-card .flex.space-x-1 > *:last-child {
    margin-right: 0;
}

/* Ensure proper grid spacing */
.grid.grid-cols-1.md\:grid-cols-2.lg\:grid-cols-3 {
    gap: 1.5rem;
}

/* Fix for lesson card content spacing */
.lesson-card .p-4 {
    padding: 1rem;
}

.lesson-card .mb-3 {
    margin-bottom: 0.75rem;
}

/* Ensure proper text wrapping */
.lesson-card .text-sm {
    word-wrap: break-word;
    overflow-wrap: break-word;
}

/* Fix for assigned classes text */
.lesson-card .line-clamp-2 {
    max-height: 2.5rem;
}

/* Ensure proper button alignment */
.lesson-card .flex.items-start.justify-between {
    align-items: flex-start;
    gap: 0.5rem;
}

.lesson-card .flex.space-x-1 {
    flex-shrink: 0;
}
</style>