<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has teacher role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

$quiz_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'update_quiz') {
        $title = sanitize_input($_POST['title']);
        $description = sanitize_input($_POST['description']);
        $quiz_type = sanitize_input($_POST['quiz_type']);
        $lesson_id = !empty($_POST['lesson_id']) ? (int)$_POST['lesson_id'] : null;
        $time_limit = !empty($_POST['time_limit']) ? (int)$_POST['time_limit'] : null;
        $passing_score = (int)$_POST['passing_score'];
        $max_attempts = (int)$_POST['max_attempts'];
        $status = sanitize_input($_POST['status']);
        $selected_classes = isset($_POST['class_ids']) ? $_POST['class_ids'] : [];

        if (empty($title) || $passing_score <= 0 || $max_attempts <= 0) {
            $message = "Please provide valid quiz information.";
            $message_type = "error";
        } else {
            // Start transaction
            mysqli_begin_transaction($conn);

            try {
                // Update quiz
                $update_query = "UPDATE quizzes SET
                                title = ?, description = ?, quiz_type = ?, lesson_id = ?,
                                time_limit = ?, passing_score = ?, max_attempts = ?, status = ?, updated_at = NOW()
                                WHERE id = ? AND teacher_id = ?";
                $update_stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($update_stmt, "sssiisiiii", $title, $description, $quiz_type, $lesson_id,
                                     $time_limit, $passing_score, $max_attempts, $status, $quiz_id, $_SESSION['user_id']);

                if (mysqli_stmt_execute($update_stmt)) {
                    // Delete existing class assignments
                    $delete_assignments_query = "DELETE FROM quiz_class_assignments WHERE quiz_id = ?";
                    $delete_assignments_stmt = mysqli_prepare($conn, $delete_assignments_query);
                    mysqli_stmt_bind_param($delete_assignments_stmt, "i", $quiz_id);
                    mysqli_stmt_execute($delete_assignments_stmt);

                    // Insert new class assignments
                    if (!empty($selected_classes)) {
                        $insert_assignment_query = "INSERT INTO quiz_class_assignments (quiz_id, class_id) VALUES (?, ?)";
                        $insert_assignment_stmt = mysqli_prepare($conn, $insert_assignment_query);

                        foreach ($selected_classes as $class_id) {
                            mysqli_stmt_bind_param($insert_assignment_stmt, "ii", $quiz_id, $class_id);
                            mysqli_stmt_execute($insert_assignment_stmt);
                        }
                    }

                    mysqli_commit($conn);
                    $message = "Quiz updated successfully!";
                    $message_type = "success";
                } else {
                    throw new Exception("Error updating quiz: " . mysqli_error($conn));
                }
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $message = $e->getMessage();
                $message_type = "error";
            }
        }
    }
}

// Handle additional actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'duplicate_quiz':
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
                        $message = "Quiz duplicated successfully! Redirecting to the new quiz...";
                        $message_type = "success";

                        // Redirect to the new quiz after a short delay
                        echo "<script>setTimeout(function() { window.location.href = 'edit-quiz.php?id=" . $new_quiz_id . "'; }, 2000);</script>";
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
            $new_status = sanitize_input($_POST['new_status']);

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

// Get quiz details
$quiz_query = "SELECT q.*, l.title as lesson_title FROM quizzes q
               LEFT JOIN lessons l ON q.lesson_id = l.id
               WHERE q.id = ? AND q.teacher_id = ?";
$quiz_stmt = mysqli_prepare($conn, $quiz_query);
mysqli_stmt_bind_param($quiz_stmt, "ii", $quiz_id, $_SESSION['user_id']);
mysqli_stmt_execute($quiz_stmt);
$quiz_result = mysqli_stmt_get_result($quiz_stmt);

if (mysqli_num_rows($quiz_result) == 0) {
    header('Location: quizzes.php?message=' . urlencode('Quiz not found or access denied.') . '&type=error');
    exit();
}

$quiz = mysqli_fetch_assoc($quiz_result);

// Get current class assignments
$current_assignments_query = "SELECT class_id FROM quiz_class_assignments WHERE quiz_id = ?";
$current_assignments_stmt = mysqli_prepare($conn, $current_assignments_query);
mysqli_stmt_bind_param($current_assignments_stmt, "i", $quiz_id);
mysqli_stmt_execute($current_assignments_stmt);
$current_assignments_result = mysqli_stmt_get_result($current_assignments_stmt);

$current_class_ids = [];
while ($assignment = mysqli_fetch_assoc($current_assignments_result)) {
    $current_class_ids[] = $assignment['class_id'];
}

// Get all classes for the teacher
$classes_query = "SELECT tc.id, tc.section, cc.subject_title, cc.subject_code
                  FROM teacher_classes tc
                  JOIN course_curriculum cc ON tc.subject_id = cc.id
                  WHERE tc.teacher_id = ?
                  ORDER BY cc.subject_title, tc.section";
$classes_stmt = mysqli_prepare($conn, $classes_query);
mysqli_stmt_bind_param($classes_stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($classes_stmt);
$classes_result = mysqli_stmt_get_result($classes_stmt);

// Get lessons for lesson-specific quizzes
$lessons_query = "SELECT l.id, l.title,
                  GROUP_CONCAT(CONCAT(cc.subject_code, ' - ', cc.subject_title, ' (', tc.section, ')') SEPARATOR ', ') as class_info
                  FROM lessons l
                  LEFT JOIN lesson_class_assignments lca ON l.id = lca.lesson_id
                  LEFT JOIN teacher_classes tc ON lca.class_id = tc.id
                  LEFT JOIN course_curriculum cc ON tc.subject_id = cc.id
                  WHERE l.teacher_id = ?
                  GROUP BY l.id
                  ORDER BY l.title";
$lessons_stmt = mysqli_prepare($conn, $lessons_query);
mysqli_stmt_bind_param($lessons_stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($lessons_stmt);
$lessons_result = mysqli_stmt_get_result($lessons_stmt);

$page_title = 'Edit Quiz: ' . $quiz['title'];

// Include the shared header
$sidebar_context = 'main';
include 'includes/unified-header.php';
?>

<div class="mb-6 sm:mb-8">
    <div class="flex flex-col lg:flex-row lg:justify-between lg:items-center gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-seait-dark mb-2">Edit Quiz</h1>
            <p class="text-sm sm:text-base text-gray-600">Modify quiz settings and assignments</p>
        </div>
        <div class="flex flex-wrap gap-2 lg:gap-3">
            <a href="view-quiz.php?id=<?php echo $quiz_id; ?>" class="bg-blue-600 text-white px-3 sm:px-4 py-2 rounded-md hover:bg-blue-700 transition text-sm">
                <i class="fas fa-eye mr-1 sm:mr-2"></i><span class="hidden sm:inline">View Quiz</span><span class="sm:hidden">View</span>
            </a>
            <button onclick="duplicateQuiz(<?php echo $quiz_id; ?>)" class="bg-purple-600 text-white px-3 sm:px-4 py-2 rounded-md hover:bg-purple-700 transition text-sm">
                <i class="fas fa-copy mr-1 sm:mr-2"></i><span class="hidden sm:inline">Duplicate</span><span class="sm:hidden">Copy</span>
            </button>
            <button onclick="toggleQuizStatus(<?php echo $quiz_id; ?>, '<?php echo $quiz['status']; ?>')"
                    class="<?php echo $quiz['status'] === 'published' ? 'bg-yellow-600 hover:bg-yellow-700' : 'bg-green-600 hover:bg-green-700'; ?> text-white px-3 sm:px-4 py-2 rounded-md transition text-sm">
                <i class="fas fa-<?php echo $quiz['status'] === 'published' ? 'eye-slash' : 'eye'; ?> mr-1 sm:mr-2"></i>
                <span class="hidden sm:inline"><?php echo $quiz['status'] === 'published' ? 'Unpublish' : 'Publish'; ?></span>
                <span class="sm:hidden"><?php echo $quiz['status'] === 'published' ? 'Hide' : 'Show'; ?></span>
            </button>
            <a href="quiz-statistics.php?id=<?php echo $quiz_id; ?>" class="bg-indigo-600 text-white px-3 sm:px-4 py-2 rounded-md hover:bg-indigo-700 transition text-sm">
                <i class="fas fa-chart-bar mr-1 sm:mr-2"></i><span class="hidden sm:inline">Statistics</span><span class="sm:hidden">Stats</span>
            </a>
            <a href="quizzes.php" class="bg-gray-500 text-white px-3 sm:px-4 py-2 rounded-md hover:bg-gray-600 transition text-sm">
                <i class="fas fa-arrow-left mr-1 sm:mr-2"></i><span class="hidden sm:inline">Back to Quizzes</span><span class="sm:hidden">Back</span>
            </a>
        </div>
    </div>
</div>

<?php if ($message): ?>
<div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800'; ?>">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-4 sm:gap-6">
    <!-- Main Form -->
    <div class="xl:col-span-2">
        <div class="bg-white rounded-lg shadow-md">
            <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-medium text-gray-900">Quiz Information</h2>
            </div>

            <form method="POST" class="p-4 sm:p-6 space-y-4 sm:space-y-6">
                <input type="hidden" name="action" value="update_quiz">

                <!-- Basic Information -->
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Quiz Title *</label>
                        <input type="text" name="title" value="<?php echo htmlspecialchars($quiz['title']); ?>" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange text-sm">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea name="description" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange text-sm"
                                  placeholder="Enter quiz description..."><?php echo htmlspecialchars($quiz['description']); ?></textarea>
                    </div>
                </div>

                <!-- Quiz Type and Lesson -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Quiz Type *</label>
                        <select name="quiz_type" id="quizType" onchange="toggleLessonField()" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange text-sm">
                            <option value="general" <?php echo $quiz['quiz_type'] === 'general' ? 'selected' : ''; ?>>General Quiz</option>
                            <option value="lesson_specific" <?php echo $quiz['quiz_type'] === 'lesson_specific' ? 'selected' : ''; ?>>Lesson Specific Quiz</option>
                        </select>
                    </div>

                    <div id="lessonField" class="<?php echo $quiz['quiz_type'] === 'lesson_specific' ? '' : 'hidden'; ?>">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Lesson</label>
                        <select name="lesson_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange text-sm">
                            <option value="">Select Lesson</option>
                            <?php while ($lesson = mysqli_fetch_assoc($lessons_result)): ?>
                            <option value="<?php echo $lesson['id']; ?>"
                                    <?php echo $quiz['lesson_id'] == $lesson['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($lesson['title']); ?>
                                <?php if ($lesson['class_info']): ?>
                                    (<?php echo htmlspecialchars($lesson['class_info']); ?>)
                                <?php endif; ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <!-- Quiz Settings -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Time Limit (minutes)</label>
                        <input type="number" name="time_limit" value="<?php echo $quiz['time_limit']; ?>" min="1" max="480"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange text-sm"
                               placeholder="No limit">
                        <p class="text-xs text-gray-500 mt-1">Leave empty for no time limit</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Passing Score (%) *</label>
                        <input type="number" name="passing_score" value="<?php echo $quiz['passing_score']; ?>" min="1" max="100" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange text-sm">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Max Attempts *</label>
                        <input type="number" name="max_attempts" value="<?php echo $quiz['max_attempts']; ?>" min="1" max="10" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange text-sm">
                    </div>
                </div>

                <!-- Status -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange text-sm">
                        <option value="draft" <?php echo $quiz['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="published" <?php echo $quiz['status'] === 'published' ? 'selected' : ''; ?>>Published</option>
                        <option value="archived" <?php echo $quiz['status'] === 'archived' ? 'selected' : ''; ?>>Archived</option>
                    </select>
                </div>

                <!-- Class Assignment -->
                <div class="bg-gray-50 p-3 sm:p-4 rounded-lg">
                    <h3 class="text-sm font-medium text-gray-900 mb-3 sm:mb-4">Class Assignment</h3>
                    <div class="space-y-2 max-h-48 sm:max-h-60 overflow-y-auto">
                        <?php while ($class = mysqli_fetch_assoc($classes_result)): ?>
                        <label class="flex items-center p-2 hover:bg-gray-100 rounded cursor-pointer">
                            <input type="checkbox" name="class_ids[]" value="<?php echo $class['id']; ?>"
                                   <?php echo in_array($class['id'], $current_class_ids) ? 'checked' : ''; ?>
                                   class="mr-3 text-seait-orange focus:ring-seait-orange">
                            <span class="text-sm text-gray-700 break-words">
                                <?php echo htmlspecialchars($class['subject_code'] . ' - ' . $class['subject_title'] . ' (' . $class['section'] . ')'); ?>
                            </span>
                        </label>
                        <?php endwhile; ?>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">Select the classes that should have access to this quiz</p>
                </div>

                <!-- Submit Buttons -->
                <div class="flex flex-col sm:flex-row justify-end gap-2 sm:gap-3 pt-4 border-t border-gray-200">
                    <a href="quizzes.php" class="bg-gray-500 text-white px-4 sm:px-6 py-2 rounded-md hover:bg-gray-600 transition text-sm text-center">
                        Cancel
                    </a>
                    <button type="submit" class="bg-seait-orange text-white px-4 sm:px-6 py-2 rounded-md hover:bg-orange-600 transition text-sm">
                        <i class="fas fa-save mr-2"></i>Update Quiz
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="space-y-4 sm:space-y-6">
        <!-- Quiz Summary -->
        <div class="bg-white rounded-lg shadow-md">
            <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-medium text-gray-900">Quiz Summary</h2>
            </div>
            <div class="p-4 sm:p-6 space-y-3 sm:space-y-4">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Current Status:</span>
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                 <?php echo $quiz['status'] === 'published' ? 'bg-green-100 text-green-800' :
                                        ($quiz['status'] === 'draft' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800'); ?>">
                        <?php echo ucfirst($quiz['status']); ?>
                    </span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Quiz Type:</span>
                    <span class="text-sm font-medium text-gray-900">
                        <?php
                        if ($quiz['quiz_type'] === 'lesson_specific' && $quiz['lesson_title']) {
                            echo htmlspecialchars($quiz['lesson_title']);
                        } else {
                            echo ucfirst(str_replace('_', ' ', $quiz['quiz_type']));
                        }
                        ?>
                    </span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Assigned Classes:</span>
                    <span class="text-sm font-medium text-gray-900"><?php echo count($current_class_ids); ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Created:</span>
                    <span class="text-sm text-gray-900"><?php echo date('M j, Y', strtotime($quiz['created_at'])); ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Last Updated:</span>
                    <span class="text-sm text-gray-900"><?php echo $quiz['updated_at'] ? date('M j, Y', strtotime($quiz['updated_at'])) : 'Never'; ?></span>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white rounded-lg shadow-md">
            <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-medium text-gray-900">Quick Actions</h2>
            </div>
            <div class="p-4 sm:p-6 space-y-3">
                <a href="view-quiz.php?id=<?php echo $quiz_id; ?>" class="flex items-center text-blue-600 hover:text-blue-800 text-sm">
                    <i class="fas fa-eye mr-2"></i>
                    <span>View Questions</span>
                </a>
                <a href="quizzes.php" class="flex items-center text-gray-600 hover:text-gray-800 text-sm">
                    <i class="fas fa-list mr-2"></i>
                    <span>Back to Quizzes</span>
                </a>
            </div>
        </div>
    </div>
</div>

<script>
function toggleLessonField() {
    const quizType = document.getElementById('quizType').value;
    const lessonField = document.getElementById('lessonField');

    if (quizType === 'lesson_specific') {
        lessonField.classList.remove('hidden');
    } else {
        lessonField.classList.add('hidden');
        // Clear lesson selection
        lessonField.querySelector('select').value = '';
    }
}

function duplicateQuiz(quizId) {
    if (confirm('Are you sure you want to duplicate this quiz? This will create a copy with all questions and answers.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="duplicate_quiz">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function toggleQuizStatus(quizId, currentStatus) {
    const newStatus = currentStatus === 'published' ? 'draft' : 'published';
    const actionText = currentStatus === 'published' ? 'unpublish' : 'publish';

    if (confirm(`Are you sure you want to ${actionText} this quiz?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="toggle_status">
            <input type="hidden" name="new_status" value="${newStatus}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleLessonField();
});
</script>

<?php include 'includes/footer.php'; ?>