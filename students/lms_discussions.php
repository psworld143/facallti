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
                u.id as teacher_id, u.first_name as teacher_first_name, u.last_name as teacher_last_name,
                u.email as teacher_email
                FROM class_enrollments ce
                JOIN teacher_classes tc ON ce.class_id = tc.id
                JOIN course_curriculum cc ON tc.subject_id = cc.id
                JOIN users u ON tc.teacher_id = u.id
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
$page_title = 'Discussions - ' . $class_data['subject_title'];

// Get search parameter
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';

// Build discussions query
$where_conditions = ["d.class_id = ?", "d.status = 'active'"];
$params = [$class_id];
$param_types = 'i';

if ($search) {
    $where_conditions[] = "(d.title LIKE ? OR d.description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'ss';
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get discussions
$discussions_query = "SELECT d.*,
                     COUNT(p.id) as post_count,
                     COUNT(DISTINCT p.author_id) as participant_count,
                     MAX(p.created_at) as last_activity,
                     u.first_name as created_by_name, u.last_name as created_by_last_name
                     FROM lms_discussions d
                     JOIN users u ON d.created_by = u.id
                     LEFT JOIN lms_discussion_posts p ON d.id = p.discussion_id AND p.status = 'active'
                     $where_clause
                     GROUP BY d.id
                     ORDER BY d.is_pinned DESC, last_activity DESC, d.created_at DESC";

$discussions_stmt = mysqli_prepare($conn, $discussions_query);
if (!empty($params)) {
    mysqli_stmt_bind_param($discussions_stmt, $param_types, ...$params);
}
mysqli_stmt_execute($discussions_stmt);
$discussions_result = mysqli_stmt_get_result($discussions_stmt);

// Get discussion statistics
$stats_query = "SELECT
                COUNT(DISTINCT d.id) as total_discussions,
                COUNT(DISTINCT p.discussion_id) as active_discussions,
                COUNT(p.id) as total_posts,
                COUNT(DISTINCT p.author_id) as total_participants
                FROM lms_discussions d
                LEFT JOIN lms_discussion_posts p ON d.id = p.discussion_id AND p.status = 'active'
                WHERE d.class_id = ? AND d.status = 'active'";
$stats_stmt = mysqli_prepare($conn, $stats_query);
mysqli_stmt_bind_param($stats_stmt, "i", $class_id);
mysqli_stmt_execute($stats_stmt);
$stats_result = mysqli_stmt_get_result($stats_stmt);
$stats = mysqli_fetch_assoc($stats_result);

// Include the shared LMS header
include 'includes/lms_header.php';
?>

<div class="mb-6 sm:mb-8">
    <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Class Discussions</h1>
    <p class="text-sm sm:text-base text-gray-600">Participate in class discussions and forums for <?php echo htmlspecialchars($class_data['subject_title']); ?></p>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 sm:grid-cols-4 gap-4 sm:gap-6 mb-6 sm:mb-8">
    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-4 sm:p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-comments text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Total Discussions</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['total_discussions'] ?? 0); ?></dd>
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
                        <i class="fas fa-comment-dots text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Total Posts</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['total_posts'] ?? 0); ?></dd>
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
                        <i class="fas fa-users text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Participants</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['total_participants'] ?? 0); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-4 sm:p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-orange-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-fire text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Active Discussions</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['active_discussions'] ?? 0); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Search Bar -->
<div class="bg-white rounded-lg shadow-md overflow-hidden mb-6 sm:mb-8">
    <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-medium text-gray-900">Search Discussions</h3>
    </div>
    <div class="p-6">
        <form method="GET" class="flex gap-4">
            <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
            <div class="flex-1">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                       placeholder="Search discussions..."
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange">
            </div>
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition">
                <i class="fas fa-search mr-2"></i>Search
            </button>
            <?php if ($search): ?>
            <a href="lms_discussions.php?class_id=<?php echo $class_id; ?>" class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600 transition">
                <i class="fas fa-times mr-2"></i>Clear
            </a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Discussions List -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-medium text-gray-900">Course Discussions (<?php echo mysqli_num_rows($discussions_result); ?>)</h3>
            <button onclick="createDiscussion()" class="bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition">
                <i class="fas fa-plus-circle mr-2"></i>New Discussion
            </button>
        </div>
    </div>

    <?php if (mysqli_num_rows($discussions_result) == 0): ?>
        <div class="p-6 text-center">
            <i class="fas fa-comments text-gray-300 text-4xl mb-4"></i>
            <p class="text-gray-500">No discussions found.</p>
            <p class="text-sm text-gray-400 mt-2">Start the first discussion to engage with your classmates.</p>
        </div>
    <?php else: ?>
        <div class="divide-y divide-gray-200">
            <?php while ($discussion = mysqli_fetch_assoc($discussions_result)): ?>
            <div class="p-6 hover:bg-gray-50 transition-colors">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center mb-2">
                            <?php if ($discussion['is_pinned']): ?>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 mr-2">
                                    <i class="fas fa-thumbtack mr-1"></i>Pinned
                                </span>
                            <?php endif; ?>
                            <h3 class="text-lg font-medium text-gray-900">
                                <a href="discussion_detail.php?discussion_id=<?php echo $discussion['id']; ?>&class_id=<?php echo $class_id; ?>"
                                   class="hover:text-seait-orange transition-colors">
                                    <?php echo htmlspecialchars($discussion['title']); ?>
                                </a>
                            </h3>
                        </div>
                        <p class="text-gray-600 mb-3"><?php echo htmlspecialchars(substr($discussion['description'], 0, 150)) . (strlen($discussion['description']) > 150 ? '...' : ''); ?></p>
                        <div class="flex items-center text-sm text-gray-500 space-x-4">
                            <span class="flex items-center">
                                <i class="fas fa-user mr-1"></i>
                                <?php echo htmlspecialchars($discussion['created_by_name'] . ' ' . $discussion['created_by_last_name']); ?>
                            </span>
                            <span class="flex items-center">
                                <i class="fas fa-comment mr-1"></i>
                                <?php echo number_format($discussion['post_count']); ?> posts
                            </span>
                            <span class="flex items-center">
                                <i class="fas fa-users mr-1"></i>
                                <?php echo number_format($discussion['participant_count']); ?> participants
                            </span>
                            <span class="flex items-center">
                                <i class="fas fa-clock mr-1"></i>
                                <?php echo $discussion['last_activity'] ? date('M d, Y', strtotime($discussion['last_activity'])) : 'No activity'; ?>
                            </span>
                        </div>
                    </div>
                    <div class="ml-4">
                        <a href="discussion_detail.php?discussion_id=<?php echo $discussion['id']; ?>&class_id=<?php echo $class_id; ?>"
                           class="inline-flex items-center px-3 py-2 bg-seait-orange text-white text-sm rounded-lg hover:bg-orange-600 transition">
                            <i class="fas fa-eye mr-2"></i>View
                        </a>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function createDiscussion() {
    // This would typically open a modal or redirect to a create discussion page
    alert('Create discussion functionality would be implemented here.');
}
</script>

<?php
// Include the shared LMS footer
include 'includes/lms_footer.php';
?>