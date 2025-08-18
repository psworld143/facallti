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
$page_title = 'Announcements';

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_announcement':
                $class_id = (int)$_POST['class_id'];
                $title = sanitize_input($_POST['title']);
                $content = $_POST['content']; // Don't sanitize HTML content
                $priority = sanitize_input($_POST['priority']);

                // Verify the class belongs to the logged-in teacher
                $verify_class_query = "SELECT id FROM teacher_classes WHERE id = ? AND teacher_id = ?";
                $verify_class_stmt = mysqli_prepare($conn, $verify_class_query);
                mysqli_stmt_bind_param($verify_class_stmt, "ii", $class_id, $_SESSION['user_id']);
                mysqli_stmt_execute($verify_class_stmt);
                $verify_class_result = mysqli_stmt_get_result($verify_class_stmt);

                if (mysqli_num_rows($verify_class_result) > 0) {
                    $insert_query = "INSERT INTO class_announcements (class_id, teacher_id, title, content, priority, created_at)
                                   VALUES (?, ?, ?, ?, ?, NOW())";
                    $insert_stmt = mysqli_prepare($conn, $insert_query);
                    mysqli_stmt_bind_param($insert_stmt, "iiss", $class_id, $_SESSION['user_id'], $title, $content, $priority);

                    if (mysqli_stmt_execute($insert_stmt)) {
                        $message = "Announcement created successfully!";
                        $message_type = "success";
                    } else {
                        $message = "Error creating announcement: " . mysqli_error($conn);
                        $message_type = "error";
                    }
                } else {
                    $message = "Invalid class selected.";
                    $message_type = "error";
                }
                break;

            case 'delete_announcement':
                $announcement_id = (int)$_POST['announcement_id'];

                $delete_query = "DELETE FROM class_announcements WHERE id = ? AND teacher_id = ?";
                $delete_stmt = mysqli_prepare($conn, $delete_query);
                mysqli_stmt_bind_param($delete_stmt, "ii", $announcement_id, $_SESSION['user_id']);

                if (mysqli_stmt_execute($delete_stmt)) {
                    $message = "Announcement deleted successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error deleting announcement: " . mysqli_error($conn);
                    $message_type = "error";
                }
                break;
        }
    }
}

// Get search and filter parameters
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$class_filter = isset($_GET['class_id']) ? (int)$_GET['class_id'] : '';
$priority_filter = isset($_GET['priority']) ? sanitize_input($_GET['priority']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query for announcements
$where_conditions = ["ca.teacher_id = ?"];
$params = [$_SESSION['user_id']];
$param_types = 'i';

if ($search) {
    $where_conditions[] = "(ca.title LIKE ? OR ca.content LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param]);
    $param_types .= 'ss';
}

if ($class_filter) {
    $where_conditions[] = "ca.class_id = ?";
    $params[] = $class_filter;
    $param_types .= 'i';
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
$announcements_query = "SELECT ca.*, tc.section, cc.subject_title, cc.subject_code
                       FROM class_announcements ca
                       JOIN teacher_classes tc ON ca.class_id = tc.id
                       JOIN course_curriculum cc ON tc.subject_id = cc.id
                       $where_clause
                       ORDER BY ca.created_at DESC
                       LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$param_types .= 'ii';

$announcements_stmt = mysqli_prepare($conn, $announcements_query);
mysqli_stmt_bind_param($announcements_stmt, $param_types, ...$params);
mysqli_stmt_execute($announcements_stmt);
$announcements_result = mysqli_stmt_get_result($announcements_stmt);

// Get teacher's classes for filter and form
$classes_query = "SELECT tc.*, cc.subject_title, cc.subject_code
                 FROM teacher_classes tc
                 JOIN course_curriculum cc ON tc.subject_id = cc.id
                 WHERE tc.teacher_id = ? AND tc.status = 'active'
                 ORDER BY cc.subject_title, tc.section";
$classes_stmt = mysqli_prepare($conn, $classes_query);
mysqli_stmt_bind_param($classes_stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($classes_stmt);
$classes_result = mysqli_stmt_get_result($classes_stmt);

// Include the shared header
$sidebar_context = 'main';
include 'includes/unified-header.php';
?>

<div class="mb-6 sm:mb-8">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark">Announcements</h1>
            <p class="text-gray-600 mt-1">Create and manage announcements for your classes</p>
        </div>
        <button onclick="showCreateModal()" class="mt-4 sm:mt-0 bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition flex items-center">
            <i class="fas fa-plus mr-2"></i>Create Announcement
        </button>
    </div>
</div>

<?php if ($message): ?>
<div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
    <?php echo $message; ?>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div>
            <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
            <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent"
                   placeholder="Search announcements...">
        </div>

        <div>
            <label for="class_id" class="block text-sm font-medium text-gray-700 mb-1">Class</label>
            <select id="class_id" name="class_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                <option value="">All Classes</option>
                <?php while ($class = mysqli_fetch_assoc($classes_result)): ?>
                <option value="<?php echo $class['id']; ?>" <?php echo $class_filter == $class['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($class['subject_title'] . ' - ' . $class['section']); ?>
                </option>
                <?php endwhile; ?>
            </select>
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
            <button type="submit" class="w-full bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition">
                <i class="fas fa-search mr-2"></i>Filter
            </button>
        </div>
    </form>
</div>

<!-- Announcements List -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <?php if (mysqli_num_rows($announcements_result) === 0): ?>
    <div class="p-8 text-center">
        <i class="fas fa-bullhorn text-gray-300 text-4xl mb-4"></i>
        <p class="text-gray-500 mb-4">No announcements found.</p>
        <button onclick="showCreateModal()" class="bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition">
            Create Your First Announcement
        </button>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Priority</th>
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
                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($announcement['subject_title']); ?></div>
                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($announcement['section']); ?></div>
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
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <?php echo date('M j, Y g:i A', strtotime($announcement['created_at'])); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <button onclick="viewAnnouncement(<?php echo $announcement['id']; ?>)"
                                class="text-seait-orange hover:text-orange-600 mr-3">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button onclick="deleteAnnouncement(<?php echo $announcement['id']; ?>)"
                                class="text-red-600 hover:text-red-800">
                            <i class="fas fa-trash"></i>
                        </button>
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

<!-- Create Announcement Modal -->
<div id="createModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Create New Announcement</h3>
            <form method="POST">
                <input type="hidden" name="action" value="create_announcement">

                <div class="mb-4">
                    <label for="modal_class_id" class="block text-sm font-medium text-gray-700 mb-1">Class *</label>
                    <select id="modal_class_id" name="class_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                        <option value="">Select a class</option>
                        <?php
                        mysqli_data_seek($classes_result, 0);
                        while ($class = mysqli_fetch_assoc($classes_result)):
                        ?>
                        <option value="<?php echo $class['id']; ?>">
                            <?php echo htmlspecialchars($class['subject_title'] . ' - ' . $class['section']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="mb-4">
                    <label for="modal_title" class="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                    <input type="text" id="modal_title" name="title" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent"
                           placeholder="Enter announcement title">
                </div>

                <div class="mb-4">
                    <label for="modal_content" class="block text-sm font-medium text-gray-700 mb-1">Content *</label>
                    <textarea id="modal_content" name="content" required rows="4"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent"
                              placeholder="Enter announcement content"></textarea>
                </div>

                <div class="mb-4">
                    <label for="modal_priority" class="block text-sm font-medium text-gray-700 mb-1">Priority *</label>
                    <select id="modal_priority" name="priority" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                        <option value="">Select priority</option>
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="hideCreateModal()"
                            class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
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
<div id="viewModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Announcement Details</h3>
            <div id="announcementDetails"></div>
            <div class="flex justify-end mt-4">
                <button onclick="hideViewModal()"
                        class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function showCreateModal() {
    document.getElementById('createModal').classList.remove('hidden');
}

function hideCreateModal() {
    document.getElementById('createModal').classList.add('hidden');
}

function viewAnnouncement(id) {
    // Show loading state
    document.getElementById('announcementDetails').innerHTML = '<p class="text-gray-500">Loading...</p>';
    document.getElementById('viewModal').classList.remove('hidden');

    // Fetch announcement details via AJAX
    fetch(`../api/get-announcement-details.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const announcement = data.announcement;
                const priorityColors = {
                    'low': 'bg-blue-100 text-blue-800',
                    'medium': 'bg-yellow-100 text-yellow-800',
                    'high': 'bg-orange-100 text-orange-800',
                    'urgent': 'bg-red-100 text-red-800'
                };

                document.getElementById('announcementDetails').innerHTML = `
                    <div class="space-y-4">
                        <div>
                            <h4 class="text-lg font-semibold text-gray-900">${announcement.title}</h4>
                            <span class="inline-block px-2 py-1 text-xs rounded-full ${priorityColors[announcement.priority]} mt-1">
                                ${announcement.priority.charAt(0).toUpperCase() + announcement.priority.slice(1)} Priority
                            </span>
                            ${announcement.is_pinned ? '<span class="inline-block px-2 py-1 text-xs rounded-full bg-purple-100 text-purple-800 ml-2">Pinned</span>' : ''}
                        </div>

                        <div>
                            <p class="text-sm text-gray-600 mb-2">Class: ${announcement.class_name}</p>
                            <p class="text-sm text-gray-600">Posted: ${announcement.created_at}</p>
                        </div>

                        <div class="border-t pt-4">
                            <h5 class="text-sm font-medium text-gray-700 mb-2">Content:</h5>
                            <div class="text-gray-900 text-sm leading-relaxed">
                                ${announcement.content}
                            </div>
                        </div>
                    </div>
                `;
            } else {
                document.getElementById('announcementDetails').innerHTML = `
                    <div class="text-red-600">
                        <p>Error: ${data.message}</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            document.getElementById('announcementDetails').innerHTML = `
                <div class="text-red-600">
                    <p>Error loading announcement details. Please try again.</p>
                </div>
            `;
            console.error('Error:', error);
        });
}

function hideViewModal() {
    document.getElementById('viewModal').classList.add('hidden');
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
document.addEventListener('click', function(event) {
    const createModal = document.getElementById('createModal');
    const viewModal = document.getElementById('viewModal');

    if (event.target === createModal) {
        hideCreateModal();
    }
    if (event.target === viewModal) {
        hideViewModal();
    }
});
</script>

<?php include 'includes/footer.php'; ?>