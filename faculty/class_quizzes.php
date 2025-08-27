<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/id_encryption.php';

// Check if user is logged in and has teacher role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

// Get class_id from URL
$class_id = safe_decrypt_id($_GET['class_id']);

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
$page_title = 'Class Quizzes';

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'assign_quiz':
                $quiz_id = (int)$_POST['quiz_id'];
                $due_date = sanitize_input($_POST['due_date']);
                $due_time = sanitize_input($_POST['due_time']);
                $time_limit = (int)$_POST['time_limit'];
                $max_attempts = (int)$_POST['max_attempts'];

                // Check if quiz is already assigned to this class
                $check_query = "SELECT id FROM quiz_class_assignments WHERE quiz_id = ? AND class_id = ?";
                $check_stmt = mysqli_prepare($conn, $check_query);
                mysqli_stmt_bind_param($check_stmt, "ii", $quiz_id, $class_id);
                mysqli_stmt_execute($check_stmt);
                $check_result = mysqli_stmt_get_result($check_stmt);

                if (mysqli_num_rows($check_result) > 0) {
                    $message = "This quiz is already assigned to this class.";
                    $message_type = "error";
                } else {
                    $due_datetime = $due_date . ' ' . $due_time;
                    $insert_query = "INSERT INTO quiz_class_assignments (quiz_id, class_id, due_date, time_limit, max_attempts, assigned_by, assigned_at)
                                    VALUES (?, ?, ?, ?, ?, ?, NOW())";
                    $insert_stmt = mysqli_prepare($conn, $insert_query);
                    mysqli_stmt_bind_param($insert_stmt, "iisiii", $quiz_id, $class_id, $due_datetime, $time_limit, $max_attempts, $_SESSION['user_id']);

                    if (mysqli_stmt_execute($insert_stmt)) {
                        $message = "Quiz assigned to class successfully!";
                        $message_type = "success";
                    } else {
                        $message = "Error assigning quiz: " . mysqli_error($conn);
                        $message_type = "error";
                    }
                }
                break;

            case 'unassign_quiz':
                $assignment_id = (int)$_POST['assignment_id'];

                $delete_query = "DELETE FROM quiz_class_assignments WHERE id = ? AND class_id = ?";
                $delete_stmt = mysqli_prepare($conn, $delete_query);
                mysqli_stmt_bind_param($delete_stmt, "ii", $assignment_id, $class_id);

                if (mysqli_stmt_execute($delete_stmt)) {
                    $message = "Quiz unassigned from class successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error unassigning quiz: " . mysqli_error($conn);
                    $message_type = "error";
                }
                break;

            case 'extend_due_date':
                $assignment_id = (int)$_POST['assignment_id'];
                $new_due_date = sanitize_input($_POST['new_due_date']);
                $new_due_time = sanitize_input($_POST['new_due_time']);

                $new_due_datetime = $new_due_date . ' ' . $new_due_time;

                $update_query = "UPDATE quiz_class_assignments SET due_date = ? WHERE id = ? AND class_id = ?";
                $update_stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($update_stmt, "sii", $new_due_datetime, $assignment_id, $class_id);

                if (mysqli_stmt_execute($update_stmt)) {
                    $message = "Due date extended successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error extending due date: " . mysqli_error($conn);
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

// Build query for assigned quizzes
$where_conditions = ["qca.class_id = ?"];
$params = [$class_id];
$param_types = 'i';

if ($search) {
    $where_conditions[] = "(q.title LIKE ? OR q.description LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param]);
    $param_types .= 'ss';
}

if ($status_filter) {
    $where_conditions[] = "q.status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM quiz_class_assignments qca
                LEFT JOIN quizzes q ON qca.quiz_id = q.id
                $where_clause";
$count_stmt = mysqli_prepare($conn, $count_query);
if ($count_stmt) {
    mysqli_stmt_bind_param($count_stmt, $param_types, ...$params);
    mysqli_stmt_execute($count_stmt);
    $count_result = mysqli_stmt_get_result($count_stmt);
    $total_records = mysqli_fetch_assoc($count_result)['total'];
} else {
    $total_records = 0;
    $message = "Error preparing count query: " . mysqli_error($conn);
    $message_type = "error";
}

$total_pages = ceil($total_records / $per_page);

// Get assigned quizzes
$quizzes_query = "SELECT qca.*, q.title, q.description, q.quiz_type, q.status, q.created_at as quiz_created,
                  l.title as lesson_title, l.id as lesson_id,
                  COUNT(DISTINCT qs.id) as total_submissions,
                  COUNT(DISTINCT CASE WHEN qs.status = 'completed' THEN qs.id END) as completed_submissions
                  FROM quiz_class_assignments qca
                  LEFT JOIN quizzes q ON qca.quiz_id = q.id
                  LEFT JOIN lessons l ON q.lesson_id = l.id
                  LEFT JOIN quiz_submissions qs ON qca.id = qs.assignment_id
                  $where_clause
                  GROUP BY qca.id, qca.quiz_id, qca.class_id, qca.due_date, qca.time_limit, qca.max_attempts, qca.assigned_by, qca.assigned_at, qca.status, q.title, q.description, q.quiz_type, q.status, q.created_at, l.title, l.id
                  ORDER BY qca.assigned_at DESC
                  LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$param_types .= 'ii';

$quizzes_stmt = mysqli_prepare($conn, $quizzes_query);
if ($quizzes_stmt) {
    mysqli_stmt_bind_param($quizzes_stmt, $param_types, ...$params);
    mysqli_stmt_execute($quizzes_stmt);
    $quizzes_result = mysqli_stmt_get_result($quizzes_stmt);
} else {
    $quizzes_result = null;
    $message = "Error preparing quiz query: " . mysqli_error($conn);
    $message_type = "error";
}

// Get available quizzes for assignment
$available_quizzes_query = "SELECT q.*, l.title as lesson_title FROM quizzes q
                           LEFT JOIN lessons l ON q.lesson_id = l.id
                           WHERE q.teacher_id = ? AND q.status = 'active'
                           AND q.id NOT IN (
                               SELECT qca.quiz_id FROM quiz_class_assignments qca
                               WHERE qca.class_id = ?
                           )
                           ORDER BY q.title";
$available_quizzes_stmt = mysqli_prepare($conn, $available_quizzes_query);
if ($available_quizzes_stmt) {
    mysqli_stmt_bind_param($available_quizzes_stmt, "ii", $_SESSION['user_id'], $class_id);
    mysqli_stmt_execute($available_quizzes_stmt);
    $available_quizzes_result = mysqli_stmt_get_result($available_quizzes_stmt);
} else {
    $available_quizzes_result = null;
    $message = "Error preparing available quizzes query: " . mysqli_error($conn);
    $message_type = "error";
}

// Get statistics
$stats_query = "SELECT
                COUNT(DISTINCT qca.id) as total_assignments,
                COUNT(DISTINCT qs.id) as total_submissions,
                COUNT(DISTINCT CASE WHEN qs.status = 'completed' THEN qs.id END) as completed_submissions,
                AVG(CASE WHEN qs.score IS NOT NULL THEN qs.score END) as average_score
                FROM quiz_class_assignments qca
                LEFT JOIN quiz_submissions qs ON qca.id = qs.assignment_id
                WHERE qca.class_id = ?";
$stats_stmt = mysqli_prepare($conn, $stats_query);
if ($stats_stmt) {
    mysqli_stmt_bind_param($stats_stmt, "i", $class_id);
    mysqli_stmt_execute($stats_stmt);
    $stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stats_stmt));
} else {
    $stats = [
        'total_assignments' => 0,
        'total_submissions' => 0,
        'completed_submissions' => 0,
        'average_score' => null
    ];
    $message = "Error preparing stats query: " . mysqli_error($conn);
    $message_type = "error";
}

// Include the LMS header
include 'includes/lms_header.php';
?>

<div class="mb-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark">Class Quizzes</h1>
            <p class="text-gray-600 mt-1">Manage quizzes for <?php echo htmlspecialchars($class_data['subject_title'] . ' - ' . $class_data['section']); ?></p>
        </div>
        <div class="mt-4 sm:mt-0 flex space-x-2">
            <button onclick="openAssignQuizModal()" class="bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition">
                <i class="fas fa-plus mr-2"></i>Assign Quiz
            </button>
            <a href="quizzes.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                <i class="fas fa-cog mr-2"></i>Manage Quizzes
            </a>
            <a href="class_dashboard.php?class_id=<?php echo encrypt_id($class_id); ?>" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition">
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
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-6">
    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-4 sm:p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-question-circle text-white"></i>
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
                        <i class="fas fa-check-circle text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Completed Submissions</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['completed_submissions'] ?? 0); ?></dd>
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
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Total Submissions</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['total_submissions'] ?? 0); ?></dd>
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
                        <i class="fas fa-chart-line text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Average Score</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo $stats['average_score'] ? number_format($stats['average_score'], 1) . '%' : 'N/A'; ?></dd>
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
                   placeholder="Search quizzes...">
        </div>

        <div>
            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select id="status" name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                <option value="">All Status</option>
                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                <option value="archived" <?php echo $status_filter === 'archived' ? 'selected' : ''; ?>>Archived</option>
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

<!-- Quizzes Table -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <?php if (!$quizzes_result || mysqli_num_rows($quizzes_result) === 0): ?>
    <div class="p-8 text-center">
        <i class="fas fa-question-circle text-gray-300 text-4xl mb-4"></i>
        <p class="text-gray-500 mb-4">No quizzes assigned to this class yet.</p>
        <button onclick="openAssignQuizModal()" class="bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition">
            Assign Your First Quiz
        </button>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quiz</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Submissions</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php while ($quiz = mysqli_fetch_assoc($quizzes_result)): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div>
                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($quiz['title'] ?? 'Unknown Quiz'); ?></div>
                            <?php if ($quiz['description']): ?>
                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars(substr($quiz['description'], 0, 50)) . (strlen($quiz['description']) > 50 ? '...' : ''); ?></div>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                            <?php
                            if ($quiz['quiz_type'] === 'lesson_specific' && $quiz['lesson_title']) {
                                echo htmlspecialchars($quiz['lesson_title']);
                            } else {
                                echo ucfirst($quiz['quiz_type'] ?? 'Unknown');
                            }
                            ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?php echo date('M j, Y g:i A', strtotime($quiz['due_date'])); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?php echo $quiz['completed_submissions']; ?> / <?php echo $quiz['total_submissions']; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo ($quiz['status'] ?? 'draft') === 'active' ? 'bg-green-100 text-green-800' : (($quiz['status'] ?? 'draft') === 'draft' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800'); ?>">
                            <?php echo ucfirst($quiz['status'] ?? 'draft'); ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <div class="flex space-x-2">
                            <a href="view-quiz.php?id=<?php echo encrypt_id($quiz['quiz_id']); ?>"
                               class="text-blue-600 hover:text-blue-900" title="View Quiz">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="edit-quiz.php?id=<?php echo encrypt_id($quiz['quiz_id']); ?>"
                               class="text-green-600 hover:text-green-900" title="Edit Quiz">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="quiz-leaderboard.php?assignment_id=<?php echo $quiz['id']; ?>"
                               class="text-purple-600 hover:text-purple-900" title="View Results">
                                <i class="fas fa-trophy"></i>
                            </a>
                            <a href="quiz-submissions.php?assignment_id=<?php echo $quiz['id']; ?>"
                               class="text-indigo-600 hover:text-indigo-900" title="View Submissions">
                                <i class="fas fa-list-alt"></i>
                            </a>
                            <button onclick="extendDueDate('<?php echo encrypt_id($quiz['id']); ?>')"
                                    class="text-yellow-600 hover:text-yellow-900" title="Extend Due Date">
                                <i class="fas fa-clock"></i>
                            </button>
                            <button onclick="unassignQuiz('<?php echo encrypt_id($quiz['id']); ?>')"
                                    class="text-red-600 hover:text-red-900" title="Unassign Quiz">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Assign Quiz Modal -->
<div id="assignQuizModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Assign Quiz to Class</h3>
                <button onclick="closeAssignQuizModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form method="POST" id="assignQuizForm">
                <input type="hidden" name="action" value="assign_quiz">

                <div class="mb-4">
                    <label for="quiz_id" class="block text-sm font-medium text-gray-700 mb-2">Select Quiz *</label>
                    <select id="quiz_id" name="quiz_id" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        <option value="">Choose a quiz...</option>
                        <?php while ($quiz = mysqli_fetch_assoc($available_quizzes_result)): ?>
                        <option value="<?php echo $quiz['id']; ?>">
                            <?php
                            echo htmlspecialchars($quiz['title']);
                            if ($quiz['quiz_type'] === 'lesson_specific' && $quiz['lesson_title']) {
                                echo ' (' . htmlspecialchars($quiz['lesson_title']) . ')';
                            } else {
                                echo ' (' . ucfirst($quiz['quiz_type']) . ')';
                            }
                            ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="due_date" class="block text-sm font-medium text-gray-700 mb-2">Due Date *</label>
                        <input type="date" id="due_date" name="due_date" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div>
                        <label for="due_time" class="block text-sm font-medium text-gray-700 mb-2">Due Time *</label>
                        <input type="time" id="due_time" name="due_time" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="time_limit" class="block text-sm font-medium text-gray-700 mb-2">Time Limit (minutes)</label>
                        <input type="number" id="time_limit" name="time_limit" min="0" value="0"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        <p class="text-xs text-gray-500 mt-1">0 = No time limit</p>
                    </div>

                    <div>
                        <label for="max_attempts" class="block text-sm font-medium text-gray-700 mb-2">Max Attempts</label>
                        <input type="number" id="max_attempts" name="max_attempts" min="1" value="1"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeAssignQuizModal()"
                            class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-seait-orange text-white rounded-lg hover:bg-orange-600 transition">
                        Assign Quiz
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Extend Due Date Modal -->
<div id="extendDueDateModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Extend Due Date</h3>
                <button onclick="closeExtendDueDateModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form method="POST" id="extendDueDateForm">
                <input type="hidden" name="action" value="extend_due_date">
                <input type="hidden" name="assignment_id" id="extendAssignmentId">

                <div class="mb-4">
                    <p class="text-sm text-gray-600 mb-4">Set a new due date and time for this quiz assignment.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="new_due_date" class="block text-sm font-medium text-gray-700 mb-2">New Due Date *</label>
                        <input type="date" id="new_due_date" name="new_due_date" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div>
                        <label for="new_due_time" class="block text-sm font-medium text-gray-700 mb-2">New Due Time *</label>
                        <input type="time" id="new_due_time" name="new_due_time" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeExtendDueDateModal()"
                            class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-seait-orange text-white rounded-lg hover:bg-orange-600 transition">
                        Extend Due Date
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openAssignQuizModal() {
    document.getElementById('assignQuizModal').classList.remove('hidden');
}

function closeAssignQuizModal() {
    document.getElementById('assignQuizModal').classList.add('hidden');
    document.getElementById('assignQuizForm').reset();
}

function unassignQuiz(assignmentId) {
    if (confirm('Are you sure you want to unassign this quiz from the class? This will remove it from all students.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="unassign_quiz">
            <input type="hidden" name="assignment_id" value="${assignmentId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function extendDueDate(assignmentId) {
    document.getElementById('extendAssignmentId').value = assignmentId;
    document.getElementById('extendDueDateModal').classList.remove('hidden');
}

function closeExtendDueDateModal() {
    document.getElementById('extendDueDateModal').classList.add('hidden');
    document.getElementById('extendDueDateForm').reset();
}

// Close modal when clicking outside
window.onclick = function(event) {
    const assignModal = document.getElementById('assignQuizModal');
    const extendModal = document.getElementById('extendDueDateModal');
    if (event.target === assignModal) {
        closeAssignQuizModal();
    }
    if (event.target === extendModal) {
        closeExtendDueDateModal();
    }
}
</script>

<?php include 'includes/lms_footer.php'; ?>