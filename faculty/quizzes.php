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
$page_title = 'Quiz Management';

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'delete_quiz':
                $quiz_id = (int)$_POST['quiz_id'];

                // Start transaction
                mysqli_begin_transaction($conn);

                try {
                    // Delete class assignments first (due to foreign key constraint)
                    $delete_assignments_query = "DELETE FROM quiz_class_assignments WHERE quiz_id = ?";
                    $delete_assignments_stmt = mysqli_prepare($conn, $delete_assignments_query);
                    mysqli_stmt_bind_param($delete_assignments_stmt, "i", $quiz_id);
                    mysqli_stmt_execute($delete_assignments_stmt);

                    // Delete quiz
                    $delete_query = "DELETE FROM quizzes WHERE id = ? AND teacher_id = ?";
                    $delete_stmt = mysqli_prepare($conn, $delete_query);
                    mysqli_stmt_bind_param($delete_stmt, "ii", $quiz_id, $_SESSION['user_id']);

                    if (mysqli_stmt_execute($delete_stmt)) {
                        mysqli_commit($conn);
                        $message = "Quiz deleted successfully!";
                        $message_type = "success";
                    } else {
                        throw new Exception("Error deleting quiz: " . mysqli_error($conn));
                    }
                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    $message = $e->getMessage();
                    $message_type = "error";
                }
                break;

            case 'duplicate_quiz':
                $quiz_id = (int)$_POST['quiz_id'];

                // Start transaction
                mysqli_begin_transaction($conn);

                try {
                    // Get original quiz data
                    $quiz_query = "SELECT * FROM quizzes WHERE id = ? AND teacher_id = ?";
                    $quiz_stmt = mysqli_prepare($conn, $quiz_query);
                    mysqli_stmt_bind_param($quiz_stmt, "ii", $quiz_id, $_SESSION['user_id']);
                    mysqli_stmt_execute($quiz_stmt);
                    $quiz_result = mysqli_stmt_get_result($quiz_stmt);

                    if ($quiz_data = mysqli_fetch_assoc($quiz_result)) {
                        // Create duplicate quiz
                        $duplicate_query = "INSERT INTO quizzes (teacher_id, title, description, quiz_type, lesson_id, time_limit, passing_score, max_attempts, status, created_at)
                                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'draft', NOW())";
                        $duplicate_stmt = mysqli_prepare($conn, $duplicate_query);
                        $new_title = $quiz_data['title'] . ' (Copy)';
                        mysqli_stmt_bind_param($duplicate_stmt, "issisiii", $_SESSION['user_id'], $new_title, $quiz_data['description'],
                                             $quiz_data['quiz_type'], $quiz_data['lesson_id'], $quiz_data['time_limit'],
                                             $quiz_data['passing_score'], $quiz_data['max_attempts']);

                        if (mysqli_stmt_execute($duplicate_stmt)) {
                            $new_quiz_id = mysqli_insert_id($conn);

                            // Duplicate questions
                            $questions_query = "SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY order_number";
                            $questions_stmt = mysqli_prepare($conn, $questions_query);
                            mysqli_stmt_bind_param($questions_stmt, "i", $quiz_id);
                            mysqli_stmt_execute($questions_stmt);
                            $questions_result = mysqli_stmt_get_result($questions_stmt);

                            while ($question = mysqli_fetch_assoc($questions_result)) {
                                // Insert question
                                $insert_question_query = "INSERT INTO quiz_questions (quiz_id, question_text, question_type, points, order_number)
                                                        VALUES (?, ?, ?, ?, ?)";
                                $insert_question_stmt = mysqli_prepare($conn, $insert_question_query);
                                mysqli_stmt_bind_param($insert_question_stmt, "issii", $new_quiz_id, $question['question_text'],
                                                     $question['question_type'], $question['points'], $question['order_number']);
                                mysqli_stmt_execute($insert_question_stmt);

                                $new_question_id = mysqli_insert_id($conn);

                                // Duplicate answers
                                $answers_query = "SELECT * FROM quiz_question_options WHERE question_id = ?";
                                $answers_stmt = mysqli_prepare($conn, $answers_query);
                                mysqli_stmt_bind_param($answers_stmt, "i", $question['id']);
                                mysqli_stmt_execute($answers_stmt);
                                $answers_result = mysqli_stmt_get_result($answers_stmt);

                                while ($answer = mysqli_fetch_assoc($answers_result)) {
                                    $insert_answer_query = "INSERT INTO quiz_question_options (question_id, option_text, is_correct, option_order) VALUES (?, ?, ?, ?)";
                                    $insert_answer_stmt = mysqli_prepare($conn, $insert_answer_query);
                                    mysqli_stmt_bind_param($insert_answer_stmt, "isii", $new_question_id, $answer['option_text'], $answer['is_correct'], $answer['option_order']);
                                    mysqli_stmt_execute($insert_answer_stmt);
                                }
                            }

                            mysqli_commit($conn);
                            $message = "Quiz duplicated successfully!";
                            $message_type = "success";
                        } else {
                            throw new Exception("Error duplicating quiz: " . mysqli_error($conn));
                        }
                    } else {
                        throw new Exception("Quiz not found or access denied.");
                    }
                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    $message = $e->getMessage();
                    $message_type = "error";
                }
                break;

            case 'toggle_status':
                $quiz_id = (int)$_POST['quiz_id'];
                $new_status = $_POST['new_status'];

                $update_query = "UPDATE quizzes SET status = ?, updated_at = NOW() WHERE id = ? AND teacher_id = ?";
                $update_stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($update_stmt, "sii", $new_status, $quiz_id, $_SESSION['user_id']);

                if (mysqli_stmt_execute($update_stmt)) {
                    $message = "Quiz status updated successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error updating quiz status: " . mysqli_error($conn);
                    $message_type = "error";
                }
                break;
        }
    }
}

// Get search and filter parameters
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$class_filter = isset($_GET['class_id']) ? (int)$_GET['class_id'] : '';
$type_filter = isset($_GET['quiz_type']) ? sanitize_input($_GET['quiz_type']) : '';
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query for quizzes
$where_conditions = ["q.teacher_id = ?"];
$params = [$_SESSION['user_id']];
$param_types = 'i';

if ($search) {
    $where_conditions[] = "(q.title LIKE ? OR q.description LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param]);
    $param_types .= 'ss';
}

if ($class_filter) {
    $where_conditions[] = "qca.class_id = ?";
    $params[] = $class_filter;
    $param_types .= 'i';
}

if ($type_filter) {
    $where_conditions[] = "q.quiz_type = ?";
    $params[] = $type_filter;
    $param_types .= 's';
}

if ($status_filter) {
    $where_conditions[] = "q.status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get total count for pagination
$count_query = "SELECT COUNT(DISTINCT CONCAT(q.title, '|', q.description, '|', q.quiz_type, '|', COALESCE(q.lesson_id, 0), '|', q.time_limit, '|', q.passing_score, '|', q.max_attempts)) as total
                FROM quizzes q
                LEFT JOIN quiz_class_assignments qca ON q.id = qca.quiz_id
                $where_clause";
$count_stmt = mysqli_prepare($conn, $count_query);
mysqli_stmt_bind_param($count_stmt, $param_types, ...$params);
mysqli_stmt_execute($count_stmt);
$total_records = mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt))['total'];

// Get quizzes with pagination
$quizzes_query = "SELECT DISTINCT
                  q.id,
                  q.title,
                  q.description,
                  q.quiz_type,
                  q.lesson_id,
                  q.time_limit,
                  q.passing_score,
                  q.max_attempts,
                  q.status,
                  q.created_at,
                  GROUP_CONCAT(DISTINCT CONCAT(cc.subject_code, ' - ', cc.subject_title, ' (', tc.section, ')') ORDER BY cc.subject_code, tc.section SEPARATOR ', ') as assigned_classes,
                  l.title as assigned_lesson,
                  COUNT(DISTINCT qca.class_id) as class_count
                  FROM quizzes q
                  LEFT JOIN quiz_class_assignments qca ON q.id = qca.quiz_id
                  LEFT JOIN teacher_classes tc ON qca.class_id = tc.id
                  LEFT JOIN course_curriculum cc ON tc.subject_id = cc.id
                  LEFT JOIN lessons l ON q.lesson_id = l.id
                  $where_clause
                  GROUP BY q.title, q.description, q.quiz_type, q.lesson_id, q.time_limit, q.passing_score, q.max_attempts
                  ORDER BY q.created_at DESC
                  LIMIT ? OFFSET ?";

$params[] = $per_page;
$params[] = $offset;
$param_types .= 'ii';

$quizzes_stmt = mysqli_prepare($conn, $quizzes_query);
mysqli_stmt_bind_param($quizzes_stmt, $param_types, ...$params);
mysqli_stmt_execute($quizzes_stmt);
$quizzes_result = mysqli_stmt_get_result($quizzes_stmt);

// Get classes for filter dropdown
$classes_query = "SELECT tc.id, cc.subject_code, cc.subject_title, tc.section
                  FROM teacher_classes tc
                  JOIN course_curriculum cc ON tc.subject_id = cc.id
                  WHERE tc.teacher_id = ?
                  ORDER BY cc.subject_code, tc.section";
$classes_stmt = mysqli_prepare($conn, $classes_query);
mysqli_stmt_bind_param($classes_stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($classes_stmt);
$classes_result = mysqli_stmt_get_result($classes_stmt);

// Get quiz statistics
$stats_query = "SELECT
                  COUNT(*) as total_quizzes,
                  COUNT(CASE WHEN status = 'published' THEN 1 END) as published_quizzes,
                  COUNT(CASE WHEN status = 'draft' THEN 1 END) as draft_quizzes,
                  COUNT(CASE WHEN quiz_type = 'general' THEN 1 END) as general_quizzes,
                  COUNT(CASE WHEN quiz_type = 'lesson_specific' THEN 1 END) as lesson_quizzes
                FROM quizzes
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
    <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Quiz Management</h1>
    <p class="text-sm sm:text-base text-gray-600">Create and manage quizzes for your classes and lessons</p>
</div>

<!-- Message Display -->
<?php if ($message): ?>
<div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800'; ?>">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-6 sm:mb-8">
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
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Total Quizzes</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['total_quizzes'] ?? 0); ?></dd>
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
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['published_quizzes'] ?? 0); ?></dd>
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
                        <i class="fas fa-edit text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Drafts</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['draft_quizzes'] ?? 0); ?></dd>
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
                        <i class="fas fa-book text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Lesson Specific</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['lesson_quizzes'] ?? 0); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="mb-6 sm:mb-8">
    <h2 class="text-xl sm:text-2xl font-bold text-seait-dark mb-4">Quick Actions</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <a href="create-quiz.php" class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                    <i class="fas fa-plus text-blue-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Create New Quiz</h3>
                    <p class="text-sm text-gray-600">Add a new quiz with questions and settings</p>
                </div>
            </div>
        </a>

        <a href="create-quiz.php?type=lesson_specific" class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                    <i class="fas fa-book text-green-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Lesson Quiz</h3>
                    <p class="text-sm text-gray-600">Create a quiz for specific lesson content</p>
                </div>
            </div>
        </a>

        <a href="quizzes.php?status=draft" class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                    <i class="fas fa-edit text-purple-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Manage Drafts</h3>
                    <p class="text-sm text-gray-600">View and edit your draft quizzes</p>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- Search and Filter -->
<div class="mb-6 bg-white p-6 rounded-lg shadow-md">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Search & Filter</h3>
    <form method="GET" class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                   placeholder="Search quizzes..."
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
            <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
            <select name="quiz_type" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                <option value="">All Types</option>
                <option value="general" <?php echo $type_filter === 'general' ? 'selected' : ''; ?>>General</option>
                <option value="lesson_specific" <?php echo $type_filter === 'lesson_specific' ? 'selected' : ''; ?>>Lesson Specific</option>
            </select>
        </div>
        <div class="flex items-end space-x-2">
            <button type="submit" class="bg-seait-orange text-white px-4 py-2 rounded-md hover:bg-orange-600 transition">
                <i class="fas fa-search mr-2"></i>Search
            </button>
            <?php if ($search || $class_filter || $type_filter || $status_filter): ?>
            <a href="quizzes.php" class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition">
                <i class="fas fa-times mr-2"></i>Clear
            </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Quizzes Table -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h2 class="text-lg font-medium text-gray-900">Quizzes (<?php echo $total_records; ?>)</h2>
            <a href="create-quiz.php" class="bg-seait-orange text-white px-4 py-2 rounded-md hover:bg-orange-600 transition text-sm">
                <i class="fas fa-plus mr-2"></i>Add Quiz
            </a>
        </div>
    </div>

    <?php if (mysqli_num_rows($quizzes_result) == 0): ?>
        <div class="p-8 text-center">
            <i class="fas fa-question-circle text-gray-300 text-5xl mb-6"></i>
            <p class="text-gray-500 text-lg mb-6">No quizzes found matching your criteria.</p>
            <a href="create-quiz.php" class="inline-block bg-seait-orange text-white px-6 py-3 rounded-md hover:bg-orange-600 transition text-base font-medium">
                <i class="fas fa-plus mr-2"></i>Create Your First Quiz
            </a>
        </div>
    <?php else: ?>
        <!-- Mobile Card View -->
        <div class="block sm:hidden">
            <?php
            mysqli_data_seek($quizzes_result, 0);
            while ($quiz = mysqli_fetch_assoc($quizzes_result)):
            ?>
            <div class="border-b border-gray-200 p-4">
                <div class="flex justify-between items-start mb-3">
                    <div class="flex-1 min-w-0">
                        <h3 class="text-sm font-medium text-gray-900 truncate"><?php echo htmlspecialchars($quiz['title']); ?></h3>
                        <p class="text-xs text-gray-500 truncate"><?php echo htmlspecialchars($quiz['description']); ?></p>
                    </div>
                    <div class="flex flex-col items-end space-y-1">
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                            <?php
                            echo $quiz['status'] === 'published' ? 'bg-green-100 text-green-800' :
                                ($quiz['status'] === 'draft' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800');
                            ?>">
                            <?php echo ucfirst($quiz['status']); ?>
                        </span>
                        <?php if ($quiz['class_count'] > 0): ?>
                        <span class="text-xs text-gray-500"><?php echo $quiz['class_count']; ?> class<?php echo $quiz['class_count'] > 1 ? 'es' : ''; ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 text-xs text-gray-600 mb-3">
                    <div>
                        <span class="font-medium">Type:</span>
                        <span class="inline-flex px-1 py-0.5 text-xs rounded-full
                            <?php echo $quiz['quiz_type'] === 'general' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'; ?>">
                            <?php echo ucwords(str_replace('_', ' ', $quiz['quiz_type'])); ?>
                        </span>
                    </div>
                    <div>
                        <span class="font-medium">Time:</span> <?php echo $quiz['time_limit']; ?> min
                    </div>
                    <div>
                        <span class="font-medium">Pass:</span> <?php echo $quiz['passing_score']; ?>%
                    </div>
                    <div>
                        <span class="font-medium">Created:</span> <?php echo date('M j, Y', strtotime($quiz['created_at'])); ?>
                    </div>
                </div>

                <?php if ($quiz['assigned_classes'] || $quiz['assigned_lesson']): ?>
                <div class="text-xs text-gray-600 mb-3">
                    <?php if ($quiz['assigned_classes']): ?>
                        <div class="truncate"><span class="font-medium">Classes:</span> <?php echo htmlspecialchars($quiz['assigned_classes']); ?></div>
                    <?php endif; ?>
                    <?php if ($quiz['assigned_lesson']): ?>
                        <div class="truncate"><span class="font-medium">Lesson:</span> <?php echo htmlspecialchars($quiz['assigned_lesson']); ?></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="flex justify-between items-center">
                    <div class="flex space-x-2">
                        <a href="view-quiz.php?id=<?php echo $quiz['id']; ?>" class="text-seait-orange hover:text-orange-600 p-1" title="View">
                            <i class="fas fa-eye text-sm"></i>
                        </a>
                        <a href="edit-quiz.php?id=<?php echo $quiz['id']; ?>" class="text-blue-600 hover:text-blue-900 p-1" title="Edit">
                            <i class="fas fa-edit text-sm"></i>
                        </a>
                        <button onclick="duplicateQuiz(<?php echo $quiz['id']; ?>)" class="text-purple-600 hover:text-purple-900 p-1" title="Duplicate">
                            <i class="fas fa-copy text-sm"></i>
                        </button>
                        <a href="quiz-statistics.php?id=<?php echo $quiz['id']; ?>" class="text-indigo-600 hover:text-indigo-900 p-1" title="Stats">
                            <i class="fas fa-chart-bar text-sm"></i>
                        </a>
                    </div>
                    <div class="flex space-x-1">
                        <button onclick="toggleQuizStatus(<?php echo $quiz['id']; ?>, '<?php echo $quiz['status']; ?>')"
                                class="<?php echo $quiz['status'] === 'published' ? 'text-yellow-600 hover:text-yellow-900' : 'text-green-600 hover:text-green-900'; ?> p-1"
                                title="<?php echo $quiz['status'] === 'published' ? 'Unpublish' : 'Publish'; ?>">
                            <i class="fas fa-<?php echo $quiz['status'] === 'published' ? 'eye-slash' : 'eye'; ?> text-sm"></i>
                        </button>
                        <button onclick="deleteQuiz(<?php echo $quiz['id']; ?>)" class="text-red-600 hover:text-red-900 p-1" title="Delete">
                            <i class="fas fa-trash text-sm"></i>
                        </button>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>

        <!-- Desktop Table View -->
        <div class="hidden sm:block">
            <table class="w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quiz</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class/Lesson</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Settings</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php
                    mysqli_data_seek($quizzes_result, 0);
                    while ($quiz = mysqli_fetch_assoc($quizzes_result)):
                    ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-4">
                            <div class="min-w-0">
                                <div class="text-sm font-medium text-gray-900 truncate max-w-[200px]"><?php echo htmlspecialchars($quiz['title']); ?></div>
                                <div class="text-sm text-gray-500 truncate max-w-[200px]"><?php echo htmlspecialchars($quiz['description']); ?></div>
                            </div>
                        </td>
                        <td class="px-3 py-4">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                <?php echo $quiz['quiz_type'] === 'general' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'; ?>">
                                <?php
                                if ($quiz['quiz_type'] === 'lesson_specific' && $quiz['assigned_lesson']) {
                                    echo htmlspecialchars(substr($quiz['assigned_lesson'], 0, 12)) . (strlen($quiz['assigned_lesson']) > 12 ? '...' : '');
                                } else {
                                    echo ucwords(str_replace('_', ' ', $quiz['quiz_type']));
                                }
                                ?>
                            </span>
                        </td>
                        <td class="px-3 py-4">
                            <div class="min-w-0 max-w-[150px]">
                                <?php if ($quiz['assigned_classes']): ?>
                                    <div class="text-sm text-gray-900 truncate"><?php echo htmlspecialchars($quiz['assigned_classes']); ?></div>
                                    <div class="text-xs text-gray-500"><?php echo $quiz['class_count']; ?> class<?php echo $quiz['class_count'] > 1 ? 'es' : ''; ?></div>
                                <?php endif; ?>
                                <?php if ($quiz['assigned_lesson']): ?>
                                    <div class="text-sm text-gray-500 truncate"><?php echo htmlspecialchars($quiz['assigned_lesson']); ?></div>
                                <?php endif; ?>
                                <?php if (!$quiz['assigned_classes'] && !$quiz['assigned_lesson']): ?>
                                    <span class="text-gray-400 text-sm">Not assigned</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="px-3 py-4">
                            <div class="text-sm text-gray-900"><?php echo $quiz['time_limit']; ?> min</div>
                            <div class="text-sm text-gray-500"><?php echo $quiz['passing_score']; ?>% pass</div>
                        </td>
                        <td class="px-3 py-4">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                <?php
                                echo $quiz['status'] === 'published' ? 'bg-green-100 text-green-800' :
                                    ($quiz['status'] === 'draft' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800');
                                ?>">
                                <?php echo ucfirst($quiz['status']); ?>
                            </span>
                        </td>
                        <td class="px-3 py-4 text-sm text-gray-500">
                            <?php echo date('M j, Y', strtotime($quiz['created_at'])); ?>
                        </td>
                        <td class="px-3 py-4">
                            <div class="flex flex-wrap gap-1">
                                <a href="view-quiz.php?id=<?php echo $quiz['id']; ?>" class="text-seait-orange hover:text-orange-600 p-1" title="View Quiz">
                                    <i class="fas fa-eye text-sm"></i>
                                </a>
                                <a href="edit-quiz.php?id=<?php echo $quiz['id']; ?>" class="text-blue-600 hover:text-blue-900 p-1" title="Edit Quiz">
                                    <i class="fas fa-edit text-sm"></i>
                                </a>
                                <button onclick="duplicateQuiz(<?php echo $quiz['id']; ?>)" class="text-purple-600 hover:text-purple-900 p-1" title="Duplicate Quiz">
                                    <i class="fas fa-copy text-sm"></i>
                                </button>
                                <button onclick="toggleQuizStatus(<?php echo $quiz['id']; ?>, '<?php echo $quiz['status']; ?>')"
                                        class="<?php echo $quiz['status'] === 'published' ? 'text-yellow-600 hover:text-yellow-900' : 'text-green-600 hover:text-green-900'; ?> p-1"
                                        title="<?php echo $quiz['status'] === 'published' ? 'Unpublish' : 'Publish'; ?>">
                                    <i class="fas fa-<?php echo $quiz['status'] === 'published' ? 'eye-slash' : 'eye'; ?> text-sm"></i>
                                </button>
                                <a href="quiz-statistics.php?id=<?php echo $quiz['id']; ?>" class="text-indigo-600 hover:text-indigo-900 p-1" title="View Statistics">
                                    <i class="fas fa-chart-bar text-sm"></i>
                                </a>
                                <button onclick="deleteQuiz(<?php echo $quiz['id']; ?>)" class="text-red-600 hover:text-red-900 p-1" title="Delete Quiz">
                                    <i class="fas fa-trash text-sm"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_records > $per_page): ?>
        <div class="px-4 sm:px-6 py-4 border-t border-gray-200">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="text-sm text-gray-700">
                    Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total_records); ?> of <?php echo $total_records; ?> results
                </div>
                <div class="flex space-x-2">
                    <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>"
                       class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                        Previous
                    </a>
                    <?php endif; ?>

                    <?php if ($page * $per_page < $total_records): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>"
                       class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                        Next
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php
// Include the unified footer
include 'includes/footer.php';
?>

<!-- Enhanced Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 overflow-y-auto h-full w-full hidden z-50 modal-backdrop transition-all duration-300">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full transform transition-all duration-300 scale-95 opacity-0" id="deleteModalContent">
            <div class="p-6 text-center">
                <div class="mb-4">
                                            <div class="p-4 rounded-full bg-red-100 text-red-600 inline-block mb-4">
                            <i class="fas fa-exclamation-triangle text-3xl modal-icon"></i>
                        </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">Delete Quiz</h3>
                    <p class="text-gray-600 mb-4">Are you sure you want to delete this quiz? This action cannot be undone.</p>
                    <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                        <div class="flex items-center text-red-800">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            <span class="text-sm font-medium">Warning:</span>
                        </div>
                        <ul class="text-sm text-red-700 mt-2 text-left space-y-1">
                            <li class="flex items-center">
                                <i class="fas fa-trash mr-2 text-red-500"></i>
                                Quiz will be permanently removed
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-eye-slash mr-2 text-red-500"></i>
                                All questions and answers will be deleted
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-undo mr-2 text-red-500"></i>
                                Cannot be recovered
                            </li>
                        </ul>
                    </div>
                </div>
                <form id="deleteForm" method="POST" class="space-y-3">
                    <input type="hidden" name="action" value="delete_quiz">
                    <input type="hidden" name="quiz_id" id="deleteQuizId">
                    <div class="flex justify-center space-x-3">
                        <button type="button" onclick="closeDeleteModal()"
                                class="px-6 py-3 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200 modal-button">
                            <i class="fas fa-times mr-2"></i>Cancel
                        </button>
                        <button type="submit"
                                class="px-6 py-3 bg-gradient-to-r from-red-500 to-red-600 text-white rounded-lg hover:from-red-600 hover:to-red-700 transition-all duration-200 font-semibold modal-button">
                            <i class="fas fa-trash mr-2"></i>Delete Permanently
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Enhanced Duplicate Confirmation Modal -->
<div id="duplicateModal" class="fixed inset-0 bg-black bg-opacity-50 overflow-y-auto h-full w-full hidden z-50 modal-backdrop transition-all duration-300">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full transform transition-all duration-300 scale-95 opacity-0" id="duplicateModalContent">
            <div class="p-6 text-center">
                <div class="mb-4">
                                            <div class="p-4 rounded-full bg-blue-100 text-blue-600 inline-block mb-4">
                            <i class="fas fa-copy text-3xl modal-icon"></i>
                        </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">Duplicate Quiz</h3>
                    <p class="text-gray-600 mb-4">Are you sure you want to duplicate this quiz? This will create a copy with all questions and answers.</p>
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4">
                        <div class="flex items-center text-blue-800">
                            <i class="fas fa-info-circle mr-2"></i>
                            <span class="text-sm font-medium">This action will:</span>
                        </div>
                        <ul class="text-sm text-blue-700 mt-2 text-left space-y-1">
                            <li class="flex items-center">
                                <i class="fas fa-copy mr-2 text-blue-500"></i>
                                Create an exact copy of the quiz
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-question-circle mr-2 text-blue-500"></i>
                                Include all questions and answer options
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-edit mr-2 text-blue-500"></i>
                                Set status to "Draft" for editing
                            </li>
                        </ul>
                    </div>
                </div>
                <form id="duplicateForm" method="POST" class="space-y-3">
                    <input type="hidden" name="action" value="duplicate_quiz">
                    <input type="hidden" name="quiz_id" id="duplicateQuizId">
                    <div class="flex justify-center space-x-3">
                        <button type="button" onclick="closeDuplicateModal()"
                                class="px-6 py-3 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200 modal-button">
                            <i class="fas fa-times mr-2"></i>Cancel
                        </button>
                        <button type="submit"
                                class="px-6 py-3 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-lg hover:from-blue-600 hover:to-blue-700 transition-all duration-200 font-semibold modal-button">
                            <i class="fas fa-copy mr-2"></i>Duplicate Quiz
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Enhanced Status Toggle Confirmation Modal -->
<div id="statusModal" class="fixed inset-0 bg-black bg-opacity-50 overflow-y-auto h-full w-full hidden z-50 modal-backdrop transition-all duration-300">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full transform transition-all duration-300 scale-95 opacity-0" id="statusModalContent">
            <div class="p-6 text-center">
                <div class="mb-4">
                                            <div class="p-4 rounded-full bg-yellow-100 text-yellow-600 inline-block mb-4" id="statusIcon">
                            <i class="fas fa-eye text-3xl modal-icon"></i>
                        </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2" id="statusTitle">Toggle Quiz Status</h3>
                    <p class="text-gray-600 mb-4" id="statusMessage">Are you sure you want to change the status of this quiz?</p>
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-4" id="statusWarning">
                        <div class="flex items-center text-yellow-800">
                            <i class="fas fa-info-circle mr-2"></i>
                            <span class="text-sm font-medium">This action will:</span>
                        </div>
                        <ul class="text-sm text-yellow-700 mt-2 text-left space-y-1" id="statusWarningList">
                            <li class="flex items-center">
                                <i class="fas fa-eye mr-2 text-yellow-500"></i>
                                Make quiz available to students
                            </li>
                        </ul>
                    </div>
                </div>
                <form id="statusForm" method="POST" class="space-y-3">
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="quiz_id" id="statusQuizId">
                    <input type="hidden" name="new_status" id="statusNewStatus">
                    <div class="flex justify-center space-x-3">
                        <button type="button" onclick="closeStatusModal()"
                                class="px-6 py-3 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200 modal-button">
                            <i class="fas fa-times mr-2"></i>Cancel
                        </button>
                        <button type="submit" id="statusSubmitBtn"
                                class="px-6 py-3 bg-gradient-to-r from-yellow-500 to-yellow-600 text-white rounded-lg hover:from-yellow-600 hover:to-yellow-700 transition-all duration-200 font-semibold modal-button">
                            <i class="fas fa-check mr-2"></i>Confirm
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Success Notification -->
<div id="successNotification" class="fixed top-4 right-4 bg-green-500 text-white px-6 py-4 rounded-lg shadow-lg transform translate-x-full transition-transform duration-300 z-50">
    <div class="flex items-center">
        <i class="fas fa-check-circle mr-3"></i>
        <span id="successMessage">Action completed successfully!</span>
    </div>
</div>

<style>
/* Modal Animation Styles */
@keyframes modalFadeIn {
    from {
        opacity: 0;
        transform: scale(0.95) translateY(-20px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

@keyframes modalFadeOut {
    from {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
    to {
        opacity: 0;
        transform: scale(0.95) translateY(-20px);
    }
}

@keyframes backdropFadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

@keyframes backdropFadeOut {
    from {
        opacity: 1;
    }
    to {
        opacity: 0;
    }
}

.modal-enter {
    animation: backdropFadeIn 0.3s ease-out;
}

.modal-enter .modal-content {
    animation: modalFadeIn 0.3s ease-out;
}

.modal-exit {
    animation: backdropFadeOut 0.3s ease-in;
}

.modal-exit .modal-content {
    animation: modalFadeOut 0.3s ease-in;
}

/* Button hover animations */
.modal-button {
    transition: all 0.2s ease-in-out;
}

.modal-button:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.modal-button:active {
    transform: translateY(0);
}

/* Icon animations */
.modal-icon {
    animation: iconPulse 2s infinite;
}

@keyframes iconPulse {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.05);
    }
}

/* Success notification animation */
.notification-enter {
    animation: slideInRight 0.3s ease-out;
}

.notification-exit {
    animation: slideOutRight 0.3s ease-in;
}

@keyframes slideInRight {
    from {
        transform: translateX(100%);
    }
    to {
        transform: translateX(0);
    }
}

@keyframes slideOutRight {
    from {
        transform: translateX(0);
    }
    to {
        transform: translateX(100%);
    }
}
</style>

<script>
// Modal functions
function deleteQuiz(quizId) {
    document.getElementById('deleteQuizId').value = quizId;
    const modal = document.getElementById('deleteModal');
    const modalContent = document.getElementById('deleteModalContent');
    
    modal.classList.remove('hidden');
    modal.classList.add('modal-enter');
    
    // Trigger animation after a small delay
    setTimeout(() => {
        modalContent.style.transform = 'scale(1) translateY(0)';
        modalContent.style.opacity = '1';
    }, 10);
}

function closeDeleteModal() {
    const modal = document.getElementById('deleteModal');
    const modalContent = document.getElementById('deleteModalContent');
    
    modal.classList.remove('modal-enter');
    modal.classList.add('modal-exit');
    modalContent.style.transform = 'scale(0.95) translateY(-20px)';
    modalContent.style.opacity = '0';
    
    setTimeout(() => {
        modal.classList.add('hidden');
        modal.classList.remove('modal-exit');
    }, 300);
}

function duplicateQuiz(quizId) {
    document.getElementById('duplicateQuizId').value = quizId;
    const modal = document.getElementById('duplicateModal');
    const modalContent = document.getElementById('duplicateModalContent');
    
    modal.classList.remove('hidden');
    modal.classList.add('modal-enter');
    
    // Trigger animation after a small delay
    setTimeout(() => {
        modalContent.style.transform = 'scale(1) translateY(0)';
        modalContent.style.opacity = '1';
    }, 10);
}

function closeDuplicateModal() {
    const modal = document.getElementById('duplicateModal');
    const modalContent = document.getElementById('duplicateModalContent');
    
    modal.classList.remove('modal-enter');
    modal.classList.add('modal-exit');
    modalContent.style.transform = 'scale(0.95) translateY(-20px)';
    modalContent.style.opacity = '0';
    
    setTimeout(() => {
        modal.classList.add('hidden');
        modal.classList.remove('modal-exit');
    }, 300);
}

function toggleQuizStatus(quizId, currentStatus) {
    const newStatus = currentStatus === 'published' ? 'draft' : 'published';
    const actionText = currentStatus === 'published' ? 'unpublish' : 'publish';
    
    document.getElementById('statusQuizId').value = quizId;
    document.getElementById('statusNewStatus').value = newStatus;
    
    // Update modal content based on action
    const statusIcon = document.getElementById('statusIcon');
    const statusTitle = document.getElementById('statusTitle');
    const statusMessage = document.getElementById('statusMessage');
    const statusWarning = document.getElementById('statusWarning');
    const statusWarningList = document.getElementById('statusWarningList');
    const statusSubmitBtn = document.getElementById('statusSubmitBtn');
    
    if (currentStatus === 'published') {
        // Unpublishing
        statusIcon.className = 'p-4 rounded-full bg-yellow-100 text-yellow-600 inline-block mb-4';
        statusIcon.innerHTML = '<i class="fas fa-eye-slash text-3xl modal-icon"></i>';
        statusTitle.textContent = 'Unpublish Quiz';
        statusMessage.textContent = 'Are you sure you want to unpublish this quiz? Students will no longer be able to access it.';
        statusWarning.className = 'bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-4';
        statusWarningList.innerHTML = `
            <li class="flex items-center">
                <i class="fas fa-eye-slash mr-2 text-yellow-500"></i>
                Hide quiz from students
            </li>
            <li class="flex items-center">
                <i class="fas fa-lock mr-2 text-yellow-500"></i>
                Prevent new attempts
            </li>
        `;
        statusSubmitBtn.className = 'px-6 py-3 bg-gradient-to-r from-yellow-500 to-yellow-600 text-white rounded-lg hover:from-yellow-600 hover:to-yellow-700 transition-all duration-200 font-semibold';
        statusSubmitBtn.innerHTML = '<i class="fas fa-eye-slash mr-2"></i>Unpublish Quiz';
    } else {
        // Publishing
        statusIcon.className = 'p-4 rounded-full bg-green-100 text-green-600 inline-block mb-4';
        statusIcon.innerHTML = '<i class="fas fa-eye text-3xl modal-icon"></i>';
        statusTitle.textContent = 'Publish Quiz';
        statusMessage.textContent = 'Are you sure you want to publish this quiz? Students will be able to access and take it.';
        statusWarning.className = 'bg-green-50 border border-green-200 rounded-lg p-3 mb-4';
        statusWarningList.innerHTML = `
            <li class="flex items-center">
                <i class="fas fa-eye mr-2 text-green-500"></i>
                Make quiz visible to students
            </li>
            <li class="flex items-center">
                <i class="fas fa-check mr-2 text-green-500"></i>
                Allow students to take the quiz
            </li>
        `;
        statusSubmitBtn.className = 'px-6 py-3 bg-gradient-to-r from-green-500 to-green-600 text-white rounded-lg hover:from-green-600 hover:to-green-700 transition-all duration-200 font-semibold';
        statusSubmitBtn.innerHTML = '<i class="fas fa-eye mr-2"></i>Publish Quiz';
    }
    
    const modal = document.getElementById('statusModal');
    const modalContent = document.getElementById('statusModalContent');
    
    modal.classList.remove('hidden');
    modal.classList.add('modal-enter');
    
    // Trigger animation after a small delay
    setTimeout(() => {
        modalContent.style.transform = 'scale(1) translateY(0)';
        modalContent.style.opacity = '1';
    }, 10);
}

function closeStatusModal() {
    const modal = document.getElementById('statusModal');
    const modalContent = document.getElementById('statusModalContent');
    
    modal.classList.remove('modal-enter');
    modal.classList.add('modal-exit');
    modalContent.style.transform = 'scale(0.95) translateY(-20px)';
    modalContent.style.opacity = '0';
    
    setTimeout(() => {
        modal.classList.add('hidden');
        modal.classList.remove('modal-exit');
    }, 300);
}

function showSuccessNotification(message) {
    const notification = document.getElementById('successNotification');
    const messageElement = document.getElementById('successMessage');
    messageElement.textContent = message;

    notification.classList.remove('translate-x-full');
    notification.classList.add('translate-x-0', 'notification-enter');

    setTimeout(() => {
        notification.classList.remove('translate-x-0', 'notification-enter');
        notification.classList.add('translate-x-full', 'notification-exit');
    }, 3000);
}

// Close modals when clicking outside
window.onclick = function(event) {
    const deleteModal = document.getElementById('deleteModal');
    const duplicateModal = document.getElementById('duplicateModal');
    const statusModal = document.getElementById('statusModal');

    if (event.target === deleteModal) {
        closeDeleteModal();
    }
    if (event.target === duplicateModal) {
        closeDuplicateModal();
    }
    if (event.target === statusModal) {
        closeStatusModal();
    }
}

// Show success notification if there's a success message
<?php if ($message && $message_type === 'success'): ?>
document.addEventListener('DOMContentLoaded', function() {
    showSuccessNotification('<?php echo addslashes($message); ?>');
});
<?php endif; ?>

// Add tooltip functionality
document.addEventListener('DOMContentLoaded', function() {
    // Add tooltips to action buttons
    const actionButtons = document.querySelectorAll('[title]');
    actionButtons.forEach(button => {
        button.addEventListener('mouseenter', function(e) {
            const tooltip = document.createElement('div');
            tooltip.className = 'absolute z-50 px-2 py-1 text-xs text-white bg-gray-900 rounded shadow-lg';
            tooltip.textContent = this.getAttribute('title');
            tooltip.style.left = e.pageX + 10 + 'px';
            tooltip.style.top = e.pageY - 30 + 'px';
            document.body.appendChild(tooltip);

            this.addEventListener('mouseleave', function() {
                if (tooltip.parentNode) {
                    tooltip.parentNode.removeChild(tooltip);
                }
            });
        });
    });
});
</script>