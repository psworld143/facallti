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
                WHERE tc.id = ? AND tc.teacher_id = ?";
$class_stmt = mysqli_prepare($conn, $class_query);
mysqli_stmt_bind_param($class_stmt, "ii", $class_id, $_SESSION['user_id']);
mysqli_stmt_execute($class_stmt);
$class_result = mysqli_stmt_get_result($class_stmt);
$class_data = mysqli_fetch_assoc($class_result);

if (!$class_data) {
    header('Location: class-management.php');
    exit();
}

// Set page title
$page_title = 'Class Announcements';

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_announcement':
                $title = sanitize_input($_POST['title']);
                $content = $_POST['content']; // Don't sanitize HTML content
                $priority = sanitize_input($_POST['priority']);
                $is_pinned = isset($_POST['is_pinned']) ? 1 : 0;

                // Clean HTML content from CKEditor
                $content = strip_tags($content, '<p><br><strong><em><u><s><ul><ol><li><a>');

                if (empty($title) || empty(strip_tags($content))) {
                    $message = "Please provide announcement title and content.";
                    $message_type = "error";
                } else {
                    $insert_query = "INSERT INTO class_announcements (class_id, teacher_id, title, content, priority, is_pinned, created_at)
                                    VALUES (?, ?, ?, ?, ?, ?, NOW())";
                    $insert_stmt = mysqli_prepare($conn, $insert_query);
                    mysqli_stmt_bind_param($insert_stmt, "iissii", $class_id, $_SESSION['user_id'], $title, $content, $priority, $is_pinned);

                    if (mysqli_stmt_execute($insert_stmt)) {
                        $message = "Announcement created successfully!";
                        $message_type = "success";
                    } else {
                        $message = "Error creating announcement: " . mysqli_error($conn);
                        $message_type = "error";
                    }
                }
                break;

            case 'delete_announcement':
                $announcement_id = (int)$_POST['announcement_id'];

                $delete_query = "DELETE FROM class_announcements WHERE id = ? AND class_id = ? AND teacher_id = ?";
                $delete_stmt = mysqli_prepare($conn, $delete_query);
                mysqli_stmt_bind_param($delete_stmt, "iii", $announcement_id, $class_id, $_SESSION['user_id']);

                if (mysqli_stmt_execute($delete_stmt)) {
                    $message = "Announcement deleted successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error deleting announcement: " . mysqli_error($conn);
                    $message_type = "error";
                }
                break;

            case 'toggle_pin':
                $announcement_id = (int)$_POST['announcement_id'];
                $is_pinned = (int)$_POST['is_pinned'];

                $update_query = "UPDATE class_announcements SET is_pinned = ? WHERE id = ? AND class_id = ? AND teacher_id = ?";
                $update_stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($update_stmt, "iiii", $is_pinned, $announcement_id, $class_id, $_SESSION['user_id']);

                if (mysqli_stmt_execute($update_stmt)) {
                    $message = "Announcement " . ($is_pinned ? "pinned" : "unpinned") . " successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error updating announcement: " . mysqli_error($conn);
                    $message_type = "error";
                }
                break;
        }
    }
}

// Get search and filter parameters
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$priority_filter = isset($_GET['priority']) ? sanitize_input($_GET['priority']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query for announcements
$where_conditions = ["ca.class_id = ?"];
$params = [$class_id];
$param_types = 'i';

if ($search) {
    $where_conditions[] = "(ca.title LIKE ? OR ca.content LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param]);
    $param_types .= 'ss';
}

if ($priority_filter) {
    $where_conditions[] = "ca.priority = ?";
    $params[] = $priority_filter;
    $param_types .= 's';
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM class_announcements ca $where_clause";
$count_stmt = mysqli_prepare($conn, $count_query);
mysqli_stmt_bind_param($count_stmt, $param_types, ...$params);
mysqli_stmt_execute($count_stmt);
$count_result = mysqli_stmt_get_result($count_stmt);
$total_records = mysqli_fetch_assoc($count_result)['total'];

$total_pages = ceil($total_records / $per_page);

// Get announcements
$announcements_query = "SELECT ca.*, u.first_name, u.last_name
                        FROM class_announcements ca
                        JOIN users u ON ca.teacher_id = u.id
                        $where_clause
                        ORDER BY ca.is_pinned DESC, ca.created_at DESC
                        LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$param_types .= 'ii';

$announcements_stmt = mysqli_prepare($conn, $announcements_query);
mysqli_stmt_bind_param($announcements_stmt, $param_types, ...$params);
mysqli_stmt_execute($announcements_stmt);
$announcements_result = mysqli_stmt_get_result($announcements_stmt);

// Get statistics
$stats_query = "SELECT
                COUNT(*) as total_announcements,
                COUNT(CASE WHEN is_pinned = 1 THEN 1 END) as pinned_announcements,
                COUNT(CASE WHEN priority = 'high' THEN 1 END) as high_priority_count,
                COUNT(CASE WHEN priority = 'medium' THEN 1 END) as medium_priority_count,
                COUNT(CASE WHEN priority = 'low' THEN 1 END) as low_priority_count
                FROM class_announcements
                WHERE class_id = ?";
$stats_stmt = mysqli_prepare($conn, $stats_query);
mysqli_stmt_bind_param($stats_stmt, "i", $class_id);
mysqli_stmt_execute($stats_stmt);
$stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stats_stmt));

// Include the LMS header
include 'includes/lms_header.php';
?>

<!-- CKEditor Script -->
<script src="https://cdn.ckeditor.com/ckeditor5/40.0.0/classic/ckeditor.js"></script>

<div class="mb-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark">Class Announcements</h1>
            <p class="text-gray-600 mt-1">Manage announcements for <?php echo htmlspecialchars($class_data['subject_title'] . ' - ' . $class_data['section']); ?></p>
        </div>
        <div class="mt-4 sm:mt-0 flex space-x-2">
            <button onclick="openAddAnnouncementModal()" class="bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition">
                <i class="fas fa-plus mr-2"></i>New Announcement
            </button>
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

<!-- Statistics Cards -->
<div class="grid grid-cols-1 sm:grid-cols-5 gap-4 sm:gap-6 mb-6">
    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-4 sm:p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-bullhorn text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Total</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['total_announcements'] ?? 0); ?></dd>
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
                        <i class="fas fa-thumbtack text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Pinned</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['pinned_announcements'] ?? 0); ?></dd>
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
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">High Priority</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['high_priority_count'] ?? 0); ?></dd>
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
                        <i class="fas fa-exclamation-circle text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Medium Priority</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['medium_priority_count'] ?? 0); ?></dd>
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
                        <i class="fas fa-check-circle text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Low Priority</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['low_priority_count'] ?? 0); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Search and Filters -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">

        <div>
            <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
            <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent"
                   placeholder="Search announcements...">
        </div>

        <div>
            <label for="priority" class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
            <select id="priority" name="priority" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                <option value="">All Priorities</option>
                <option value="low" <?php echo $priority_filter === 'low' ? 'selected' : ''; ?>>Low</option>
                <option value="medium" <?php echo $priority_filter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                <option value="high" <?php echo $priority_filter === 'high' ? 'selected' : ''; ?>>High</option>
                <option value="urgent" <?php echo $priority_filter === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
            </select>
        </div>

        <div class="flex items-end">
            <button type="submit" class="w-full bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition">
                <i class="fas fa-search mr-2"></i>Search
            </button>
        </div>

        <div class="flex items-end">
            <a href="?class_id=<?php echo $class_id; ?>" class="w-full bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition text-center">
                <i class="fas fa-times mr-2"></i>Clear
            </a>
        </div>
    </form>
</div>

<!-- Announcements List -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <?php if (mysqli_num_rows($announcements_result) === 0): ?>
    <div class="p-8 text-center">
        <i class="fas fa-bullhorn text-gray-300 text-4xl mb-4"></i>
        <p class="text-gray-500 mb-4">No announcements found.</p>
        <button onclick="openAddAnnouncementModal()" class="bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition">
            Create Your First Announcement
        </button>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Author</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Priority</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php while ($announcement = mysqli_fetch_assoc($announcements_result)): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <div>
                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($announcement['title']); ?></div>
                            <div class="text-sm text-gray-500 mt-1">
                                <?php echo substr(strip_tags($announcement['content']), 0, 100) . (strlen(strip_tags($announcement['content'])) > 100 ? '...' : ''); ?>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="h-8 w-8 rounded-full bg-seait-orange flex items-center justify-center mr-3">
                                <span class="text-white text-xs font-medium"><?php echo strtoupper(substr($announcement['first_name'], 0, 1) . substr($announcement['last_name'], 0, 1)); ?></span>
                            </div>
                            <div>
                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($announcement['first_name'] . ' ' . $announcement['last_name']); ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php
                        $priority_colors = [
                            'low' => 'bg-gray-100 text-gray-800',
                            'medium' => 'bg-blue-100 text-blue-800',
                            'high' => 'bg-yellow-100 text-yellow-800',
                            'urgent' => 'bg-red-100 text-red-800'
                        ];
                        $priority_color = $priority_colors[$announcement['priority']] ?? 'bg-gray-100 text-gray-800';
                        ?>
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $priority_color; ?>">
                            <?php echo ucfirst($announcement['priority']); ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php if ($announcement['is_pinned']): ?>
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                            <i class="fas fa-thumbtack mr-1"></i>Pinned
                        </span>
                        <?php else: ?>
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                            Normal
                        </span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <?php echo date('M j, Y g:i A', strtotime($announcement['created_at'])); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <button onclick="viewAnnouncement(<?php echo $announcement['id']; ?>, '<?php echo htmlspecialchars($announcement['title']); ?>', '<?php echo htmlspecialchars(strip_tags($announcement['content'])); ?>', '<?php echo htmlspecialchars($announcement['priority']); ?>', <?php echo $announcement['is_pinned']; ?>, '<?php echo htmlspecialchars($announcement['first_name'] . ' ' . $announcement['last_name']); ?>', '<?php echo date('M j, Y g:i A', strtotime($announcement['created_at'])); ?>')"
                                class="text-seait-orange hover:text-orange-600 mr-3">
                            <i class="fas fa-eye"></i>
                        </button>
                        <?php if ($announcement['teacher_id'] == $_SESSION['user_id']): ?>
                        <button onclick="togglePin(<?php echo $announcement['id']; ?>, <?php echo $announcement['is_pinned'] ? 0 : 1; ?>)"
                                class="text-yellow-600 hover:text-yellow-800 mr-3">
                            <i class="fas fa-thumbtack"></i>
                        </button>
                        <button onclick="deleteAnnouncement(<?php echo $announcement['id']; ?>)"
                                class="text-red-600 hover:text-red-800">
                            <i class="fas fa-trash"></i>
                        </button>
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
                <a href="?class_id=<?php echo $class_id; ?>&page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&priority=<?php echo urlencode($priority_filter); ?>"
                   class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    Previous
                </a>
                <?php endif; ?>
                <?php if ($page < $total_pages): ?>
                <a href="?class_id=<?php echo $class_id; ?>&page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&priority=<?php echo urlencode($priority_filter); ?>"
                   class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    Next
                </a>
                <?php endif; ?>
            </div>
            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm text-gray-700">
                        Showing <span class="font-medium"><?php echo $offset + 1; ?></span> to <span class="font-medium"><?php echo min($offset + $per_page, $total_records); ?></span> of <span class="font-medium"><?php echo $total_records; ?></span> results
                    </p>
                </div>
                <div>
                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                        <?php if ($page > 1): ?>
                        <a href="?class_id=<?php echo $class_id; ?>&page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&priority=<?php echo urlencode($priority_filter); ?>"
                           class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?class_id=<?php echo $class_id; ?>&page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&priority=<?php echo urlencode($priority_filter); ?>"
                           class="relative inline-flex items-center px-4 py-2 border text-sm font-medium <?php echo $i === $page ? 'z-10 bg-seait-orange border-seait-orange text-white' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'; ?>">
                            <?php echo $i; ?>
                        </a>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                        <a href="?class_id=<?php echo $class_id; ?>&page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&priority=<?php echo urlencode($priority_filter); ?>"
                           class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                        <?php endif; ?>
                    </nav>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Add Announcement Modal -->
<div id="addAnnouncementModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Create New Announcement</h3>
                <button onclick="closeAddAnnouncementModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form method="POST" id="addAnnouncementForm">
                <input type="hidden" name="action" value="add_announcement">

                <div class="mb-4">
                    <label for="title" class="block text-sm font-medium text-gray-700 mb-2">Title</label>
                    <input type="text" id="title" name="title" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange">
                </div>

                <div class="mb-4">
                    <label for="content" class="block text-sm font-medium text-gray-700 mb-2">Content</label>
                    <textarea id="content" name="content" required
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange"
                              rows="6"></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div>
                        <label for="priority" class="block text-sm font-medium text-gray-700 mb-2">Priority</label>
                        <select id="priority" name="priority" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>

                    <div class="flex items-center">
                        <input type="checkbox" id="is_pinned" name="is_pinned" class="h-4 w-4 text-seait-orange focus:ring-seait-orange border-gray-300 rounded">
                        <label for="is_pinned" class="ml-2 block text-sm text-gray-900">Pin to top</label>
                    </div>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeAddAnnouncementModal()"
                            class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-seait-orange text-white rounded-lg hover:bg-orange-600 transition">
                        Create Announcement
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Announcement Modal -->
<div id="viewAnnouncementModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Announcement Details</h3>
                <button onclick="closeViewAnnouncementModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div id="announcementDetails">
                <!-- Content will be loaded here -->
            </div>

            <div class="flex justify-end mt-4">
                <button onclick="closeViewAnnouncementModal()"
                        class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Initialize CKEditor
ClassicEditor
    .create(document.querySelector('#content'), {
        toolbar: ['heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', '|', 'undo', 'redo'],
        placeholder: 'Enter announcement content...'
    })
    .catch(error => {
        console.error(error);
    });

function openAddAnnouncementModal() {
    document.getElementById('addAnnouncementModal').classList.remove('hidden');
}

function closeAddAnnouncementModal() {
    document.getElementById('addAnnouncementModal').classList.add('hidden');
}

function viewAnnouncement(id, title, content, priority, isPinned, author, createdAt) {
    const priorityColors = {
        'low': 'bg-gray-100 text-gray-800',
        'medium': 'bg-blue-100 text-blue-800',
        'high': 'bg-yellow-100 text-yellow-800',
        'urgent': 'bg-red-100 text-red-800'
    };

    const priorityColor = priorityColors[priority] || 'bg-gray-100 text-gray-800';
    const pinnedStatus = isPinned == 1 ?
        '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800"><i class="fas fa-thumbtack mr-1"></i>Pinned</span>' :
        '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Normal</span>';

    const detailsHtml = `
        <div class="space-y-4">
            <div>
                <h4 class="text-lg font-semibold text-gray-900">${title}</h4>
                <div class="flex items-center mt-2 space-x-4">
                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ${priorityColor}">
                        ${priority.charAt(0).toUpperCase() + priority.slice(1)} Priority
                    </span>
                    ${pinnedStatus}
                </div>
            </div>

            <div>
                <h5 class="text-sm font-medium text-gray-700 mb-2">Content:</h5>
                <div class="bg-gray-50 p-4 rounded-lg text-sm text-gray-800">
                    ${content}
                </div>
            </div>

            <div class="border-t pt-4">
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="font-medium text-gray-700">Author:</span>
                        <span class="text-gray-900 ml-2">${author}</span>
                    </div>
                    <div>
                        <span class="font-medium text-gray-700">Created:</span>
                        <span class="text-gray-900 ml-2">${createdAt}</span>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.getElementById('announcementDetails').innerHTML = detailsHtml;
    document.getElementById('viewAnnouncementModal').classList.remove('hidden');
}

function closeViewAnnouncementModal() {
    document.getElementById('viewAnnouncementModal').classList.add('hidden');
}

function togglePin(id, isPinned) {
    if (confirm('Are you sure you want to ' + (isPinned ? 'pin' : 'unpin') + ' this announcement?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="toggle_pin">
            <input type="hidden" name="announcement_id" value="${id}">
            <input type="hidden" name="is_pinned" value="${isPinned}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteAnnouncement(id) {
    if (confirm('Are you sure you want to delete this announcement? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_announcement">
            <input type="hidden" name="announcement_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Close modals when clicking outside
window.onclick = function(event) {
    const addModal = document.getElementById('addAnnouncementModal');
    const viewModal = document.getElementById('viewAnnouncementModal');

    if (event.target === addModal) {
        closeAddAnnouncementModal();
    }
    if (event.target === viewModal) {
        closeViewAnnouncementModal();
    }
}
</script>

<?php include 'includes/lms_footer.php'; ?>