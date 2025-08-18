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
$page_title = 'Class Management';

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_class':
                $subject_id = (int)$_POST['subject_id'];
                $section = sanitize_input($_POST['section']);
                $description = sanitize_input($_POST['description']);

                if (empty($subject_id) || empty($section)) {
                    $message = "Subject and section are required!";
                    $message_type = "error";
                } else {
                    // Generate unique join code
                    $join_code = generateJoinCode();

                    $insert_query = "INSERT INTO teacher_classes (teacher_id, subject_id, section, description, join_code, status) VALUES (?, ?, ?, ?, ?, 'active')";
                    $insert_stmt = mysqli_prepare($conn, $insert_query);
                    mysqli_stmt_bind_param($insert_stmt, "iisss", $_SESSION['user_id'], $subject_id, $section, $description, $join_code);

                    if (mysqli_stmt_execute($insert_stmt)) {
                        $message = "Class created successfully! Join code: " . $join_code;
                        $message_type = "success";
                    } else {
                        $message = "Error creating class: " . mysqli_error($conn);
                        $message_type = "error";
                    }
                }
                break;

            case 'update_class':
                $class_id = (int)$_POST['class_id'];
                $section = sanitize_input($_POST['section']);
                $description = sanitize_input($_POST['description']);
                $status = sanitize_input($_POST['status']);

                $update_query = "UPDATE teacher_classes SET section = ?, description = ?, status = ? WHERE id = ? AND teacher_id = ?";
                $update_stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($update_stmt, "sssii", $section, $description, $status, $class_id, $_SESSION['user_id']);

                if (mysqli_stmt_execute($update_stmt)) {
                    $message = "Class updated successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error updating class: " . mysqli_error($conn);
                    $message_type = "error";
                }
                break;

            case 'delete_class':
                $class_id = (int)$_POST['class_id'];

                $delete_query = "DELETE FROM teacher_classes WHERE id = ? AND teacher_id = ?";
                $delete_stmt = mysqli_prepare($conn, $delete_query);
                mysqli_stmt_bind_param($delete_stmt, "ii", $class_id, $_SESSION['user_id']);

                if (mysqli_stmt_execute($delete_stmt)) {
                    $message = "Class deleted successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error deleting class: " . mysqli_error($conn);
                    $message_type = "error";
                }
                break;

            case 'regenerate_join_code':
                $class_id = (int)$_POST['class_id'];
                $new_join_code = generateJoinCode();

                $update_code_query = "UPDATE teacher_classes SET join_code = ? WHERE id = ? AND teacher_id = ?";
                $update_code_stmt = mysqli_prepare($conn, $update_code_query);
                mysqli_stmt_bind_param($update_code_stmt, "sii", $new_join_code, $class_id, $_SESSION['user_id']);

                if (mysqli_stmt_execute($update_code_stmt)) {
                    $message = "Join code regenerated successfully! New code: " . $new_join_code;
                    $message_type = "success";
                } else {
                    $message = "Error regenerating join code: " . mysqli_error($conn);
                    $message_type = "error";
                }
                break;
        }
    }
}

// Function to generate unique join code
function generateJoinCode() {
    global $conn;
    do {
        $code = strtoupper(substr(md5(uniqid()), 0, 8));
        $check_query = "SELECT id FROM teacher_classes WHERE join_code = ?";
        $check_stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($check_stmt, "s", $code);
        mysqli_stmt_execute($check_stmt);
        $result = mysqli_stmt_get_result($check_stmt);
    } while (mysqli_num_rows($result) > 0);

    return $code;
}

// Get search and filter parameters
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 12; // Increased for card layout
$offset = ($page - 1) * $per_page;

// Build query
$where_conditions = ["tc.teacher_id = ?"];
$params = [$_SESSION['user_id']];
$param_types = 'i';

if ($search) {
    $where_conditions[] = "(cc.subject_title LIKE ? OR tc.section LIKE ? OR tc.join_code LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
    $param_types .= 'sss';
}

if ($status_filter) {
    $where_conditions[] = "tc.status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM teacher_classes tc
                JOIN course_curriculum cc ON tc.subject_id = cc.id
                $where_clause";
$count_stmt = mysqli_prepare($conn, $count_query);
mysqli_stmt_bind_param($count_stmt, $param_types, ...$params);
mysqli_stmt_execute($count_stmt);
$count_result = mysqli_stmt_get_result($count_stmt);
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $per_page);

// Get classes with student count
$classes_query = "SELECT tc.*, cc.subject_title, cc.subject_code, cc.units,
                  (SELECT COUNT(*) FROM class_enrollments ce WHERE ce.class_id = tc.id AND ce.status = 'active') as student_count
                  FROM teacher_classes tc
                  JOIN course_curriculum cc ON tc.subject_id = cc.id
                  $where_clause
                  ORDER BY tc.created_at DESC
                  LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$param_types .= 'ii';

$classes_stmt = mysqli_prepare($conn, $classes_query);
mysqli_stmt_bind_param($classes_stmt, $param_types, ...$params);
mysqli_stmt_execute($classes_stmt);
$classes_result = mysqli_stmt_get_result($classes_stmt);

// Get subjects for dropdown
$subjects_query = "SELECT id, subject_code, subject_title, units FROM course_curriculum ORDER BY subject_title ASC";
$subjects_result = mysqli_query($conn, $subjects_query);

// Get statistics
$stats_query = "SELECT
                COUNT(*) as total_classes,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_classes,
                COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_classes
                FROM teacher_classes
                WHERE teacher_id = ?";
$stats_stmt = mysqli_prepare($conn, $stats_query);
mysqli_stmt_bind_param($stats_stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($stats_stmt);
$stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stats_stmt));

// Include the shared header
$sidebar_context = 'main';
include 'includes/unified-header.php';
?>

<div class="mb-6 sm:mb-8">
    <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Class Management</h1>
    <p class="text-sm sm:text-base text-gray-600">Create and manage your classes with unique join codes</p>
</div>

<!-- Message Display -->
<?php if ($message): ?>
<div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800'; ?>">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-6 mb-6 sm:mb-8">
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
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['total_classes'] ?? 0); ?></dd>
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
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['active_classes'] ?? 0); ?></dd>
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
                        <i class="fas fa-pause text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Inactive Classes</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['inactive_classes'] ?? 0); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Class Button -->
<div class="mb-6">
    <button onclick="openAddClassModal()" class="bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition">
        <i class="fas fa-plus mr-2"></i>Add New Class
    </button>
</div>

<!-- Search and Filter -->
<div class="mb-6 bg-white p-4 rounded-lg shadow-md">
    <form method="GET" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                   placeholder="Search by subject, section, or join code"
                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                <option value="">All Status</option>
                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
            </select>
        </div>
        <div class="flex items-end">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">
                <i class="fas fa-search mr-2"></i>Search
            </button>
            <?php if ($search || $status_filter): ?>
            <a href="class-management.php" class="ml-2 bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition">
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
            <p class="text-gray-500">No classes found. Create your first class to get started.</p>
        </div>
    <?php else: ?>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <?php while ($class = mysqli_fetch_assoc($classes_result)): ?>
                <div class="bg-white rounded-lg shadow-md overflow-hidden border border-gray-200 hover:shadow-lg transition-shadow class-card">
                    <!-- Header Photo -->
                    <div class="h-32 relative <?php
                        echo $class['status'] === 'active' ? 'bg-gradient-to-br from-blue-500 to-blue-600' : 'bg-gradient-to-br from-red-500 to-red-600';
                    ?>">
                        <div class="absolute inset-0 bg-black bg-opacity-20"></div>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <div class="text-center text-white">
                                <i class="fas fa-chalkboard-teacher text-4xl mb-2"></i>
                                <div class="text-sm font-medium"><?php echo htmlspecialchars($class['subject_code']); ?></div>
                            </div>
                        </div>
                        <!-- Status Badge -->
                        <div class="absolute top-3 right-3">
                            <span class="px-2 py-1 text-xs rounded-full bg-white bg-opacity-90 <?php
                                echo $class['status'] === 'active' ? 'text-green-800' : 'text-red-800';
                            ?>">
                                <?php echo ucfirst($class['status']); ?>
                            </span>
                        </div>
                    </div>

                    <!-- Card Content -->
                    <div class="p-4">
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
                            <div class="flex items-center text-sm text-gray-600">
                                <i class="fas fa-user-graduate w-4 mr-2"></i>
                                <span><?php echo $class['student_count']; ?> students</span>
                            </div>
                        </div>

                        <!-- Join Code -->
                        <div class="flex items-center mb-4 p-3 bg-gray-50 rounded-lg">
                            <div class="flex-1">
                                <div class="text-xs text-gray-500 mb-1">Join Code</div>
                                <div class="flex items-center">
                                    <code class="text-sm font-mono bg-white px-2 py-1 rounded border"><?php echo htmlspecialchars($class['join_code']); ?></code>
                                    <button onclick="copyToClipboard('<?php echo $class['join_code']; ?>')" class="ml-2 text-gray-400 hover:text-gray-600">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Created Date -->
                        <div class="flex items-center text-sm text-gray-500 mb-4">
                            <i class="fas fa-calendar-alt w-4 mr-2"></i>
                            <span>Created <?php echo date('M d, Y', strtotime($class['created_at'])); ?></span>
                        </div>

                        <!-- Actions -->
                        <div class="flex space-x-2">
                            <a href="class_dashboard.php?class_id=<?php echo $class['id']; ?>"
                               class="flex-1 bg-seait-orange text-white text-center py-2 px-3 rounded-md hover:bg-orange-600 transition text-sm font-medium action-btn">
                                <i class="fas fa-tachometer-alt mr-1"></i>Dashboard
                            </a>
                            <button onclick="viewStudents(<?php echo $class['id']; ?>)"
                                    class="bg-blue-600 text-white py-2 px-3 rounded-md hover:bg-blue-700 transition text-sm font-medium action-btn">
                                <i class="fas fa-users"></i>
                            </button>
                            <button onclick="editClass(<?php echo htmlspecialchars(json_encode($class)); ?>)"
                                    class="bg-green-600 text-white py-2 px-3 rounded-md hover:bg-green-700 transition text-sm font-medium action-btn">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>

                        <!-- Secondary Actions -->
                        <div class="flex space-x-2 mt-2">
                            <button onclick="regenerateJoinCode(<?php echo $class['id']; ?>)"
                                    class="flex-1 bg-purple-600 text-white py-2 px-3 rounded-md hover:bg-purple-700 transition text-sm font-medium action-btn">
                                <i class="fas fa-sync-alt mr-1"></i>Regenerate Code
                            </button>
                            <button onclick="deleteClass(<?php echo $class['id']; ?>)"
                                    class="bg-red-600 text-white py-2 px-3 rounded-md hover:bg-red-700 transition text-sm font-medium action-btn">
                                <i class="fas fa-trash"></i>
                            </button>
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

<!-- Add Class Modal -->
<div id="addClassModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-seait-dark">Add New Class</h3>
                <button onclick="closeAddClassModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="add_class">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Subject <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <input type="text" id="subjectSearch" placeholder="Search subjects..."
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"
                               onkeyup="filterSubjects()">
                        <input type="hidden" name="subject_id" id="selected_subject_id" required>
                        <div id="subjectDropdown" class="absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-y-auto hidden">
                            <?php mysqli_data_seek($subjects_result, 0); ?>
                            <?php while($subject = mysqli_fetch_assoc($subjects_result)): ?>
                            <div class="subject-option px-3 py-2 hover:bg-gray-100 cursor-pointer border-b border-gray-100 last:border-b-0"
                                 data-id="<?php echo $subject['id']; ?>"
                                 data-text="<?php echo htmlspecialchars($subject['subject_code'] . ' - ' . $subject['subject_title'] . ' (' . $subject['units'] . ' units)'); ?>">
                                <div class="font-medium text-gray-900"><?php echo htmlspecialchars($subject['subject_code']); ?></div>
                                <div class="text-sm text-gray-600"><?php echo htmlspecialchars($subject['subject_title']); ?></div>
                                <div class="text-xs text-gray-500"><?php echo $subject['units']; ?> units</div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Section <span class="text-red-500">*</span></label>
                    <input type="text" name="section" required placeholder="e.g., A, B, 1A, 2B"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="3" placeholder="Optional description for the class"
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                </div>

                <div class="flex space-x-3">
                    <button type="submit" class="flex-1 bg-seait-orange text-white px-4 py-2 rounded-md hover:bg-orange-600 transition">
                        Create Class
                    </button>
                    <button type="button" onclick="closeAddClassModal()" class="flex-1 bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Class Modal -->
<div id="editClassModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-seait-dark">Edit Class</h3>
                <button onclick="closeEditClassModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="update_class">
                <input type="hidden" name="class_id" id="edit_class_id">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                    <input type="text" id="edit_subject_title" readonly
                           class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Section <span class="text-red-500">*</span></label>
                    <input type="text" name="section" id="edit_section" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" id="edit_description" rows="3"
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" id="edit_status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <div class="flex space-x-3">
                    <button type="submit" class="flex-1 bg-seait-orange text-white px-4 py-2 rounded-md hover:bg-orange-600 transition">
                        Update Class
                    </button>
                    <button type="button" onclick="closeEditClassModal()" class="flex-1 bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.class-card {
    transition: all 0.3s ease;
}

.class-card:hover {
    transform: translateY(-2px);
}

.action-btn {
    transition: all 0.2s ease;
}

.action-btn:hover {
    transform: scale(1.05);
}
</style>

<script>
function openAddClassModal() {
    document.getElementById('addClassModal').classList.remove('hidden');
    // Reset subject search
    document.getElementById('subjectSearch').value = '';
    document.getElementById('selected_subject_id').value = '';
    // Show all subject options
    const options = document.querySelectorAll('.subject-option');
    options.forEach(option => {
        option.style.display = 'block';
    });
}

function closeAddClassModal() {
    document.getElementById('addClassModal').classList.add('hidden');
}

function editClass(classData) {
    document.getElementById('edit_class_id').value = classData.id;
    document.getElementById('edit_subject_title').value = classData.subject_title;
    document.getElementById('edit_section').value = classData.section;
    document.getElementById('edit_description').value = classData.description || '';
    document.getElementById('edit_status').value = classData.status;
    document.getElementById('editClassModal').classList.remove('hidden');
}

function closeEditClassModal() {
    document.getElementById('editClassModal').classList.add('hidden');
}

function deleteClass(classId) {
    if (confirm('Are you sure you want to delete this class? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_class">
            <input type="hidden" name="class_id" value="${classId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function regenerateJoinCode(classId) {
    if (confirm('Are you sure you want to regenerate the join code? The old code will no longer work.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="regenerate_join_code">
            <input type="hidden" name="class_id" value="${classId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function viewStudents(classId) {
    window.location.href = `student-list.php?class_id=${classId}`;
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        // Show a temporary success message
        const button = event.target;
        const originalIcon = button.innerHTML;
        button.innerHTML = '<i class="fas fa-check"></i>';
        button.classList.add('text-green-600');

        setTimeout(() => {
            button.innerHTML = originalIcon;
            button.classList.remove('text-green-600');
        }, 1000);
    });
}

// Close modals when clicking outside
window.onclick = function(event) {
    const addModal = document.getElementById('addClassModal');
    const editModal = document.getElementById('editClassModal');

    if (event.target === addModal) {
        closeAddClassModal();
    }
    if (event.target === editModal) {
        closeEditClassModal();
    }
}

// Function to filter subjects in the dropdown
function filterSubjects() {
    const searchInput = document.getElementById('subjectSearch');
    const dropdown = document.getElementById('subjectDropdown');
    const filter = searchInput.value.toLowerCase();
    const options = dropdown.querySelectorAll('.subject-option');

    if (filter) {
        dropdown.classList.remove('hidden');
        options.forEach(option => {
            const text = option.getAttribute('data-text').toLowerCase();
            if (text.includes(filter)) {
                option.style.display = 'block';
            } else {
                option.style.display = 'none';
            }
        });
    } else {
        dropdown.classList.add('hidden');
    }
}

// Add click event listeners to subject options
document.addEventListener('DOMContentLoaded', function() {
    const subjectOptions = document.querySelectorAll('.subject-option');
    subjectOptions.forEach(option => {
        option.addEventListener('click', function() {
            selectSubject(this);
        });
    });

    // Show dropdown when search input is focused
    const searchInput = document.getElementById('subjectSearch');
    if (searchInput) {
        searchInput.addEventListener('focus', function() {
            document.getElementById('subjectDropdown').classList.remove('hidden');
        });
    }
});

// Function to select a subject from the dropdown
function selectSubject(option) {
    const searchInput = document.getElementById('subjectSearch');
    const selectedSubjectId = option.getAttribute('data-id');
    const selectedSubjectText = option.getAttribute('data-text');

    searchInput.value = selectedSubjectText;
    document.getElementById('selected_subject_id').value = selectedSubjectId;
    document.getElementById('subjectDropdown').classList.add('hidden');
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const subjectSearch = document.getElementById('subjectSearch');
    const subjectDropdown = document.getElementById('subjectDropdown');
    const addModal = document.getElementById('addClassModal');
    const editModal = document.getElementById('editClassModal');

    // Close subject dropdown when clicking outside
    if (subjectSearch && subjectDropdown && !subjectSearch.contains(event.target) && !subjectDropdown.contains(event.target)) {
        subjectDropdown.classList.add('hidden');
    }

    // Close modals when clicking outside
    if (addModal && event.target === addModal) {
        closeAddClassModal();
    }

    if (editModal && event.target === editModal) {
        closeEditClassModal();
    }
});
</script>

<?php
// Include the shared footer
include 'includes/footer.php';
?>