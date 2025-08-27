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

$quiz_id = safe_decrypt_id($_GET['id']);
$message = '';
$message_type = '';

// Validate quiz_id
if ($quiz_id <= 0) {
    header('Location: quizzes.php?message=' . urlencode('Invalid quiz ID.') . '&type=error');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_question':
                $question_text = $_POST['question_text']; // Don't sanitize HTML content
                $question_type = sanitize_input($_POST['question_type']);
                $points = (int)$_POST['points'];
                $order_number = (int)$_POST['order_number'];

                // Clean HTML content from CKEditor - allow specific tags
                $question_text = strip_tags($question_text, '<p><br><strong><em><u><s><ul><ol><li><a>');

                if (empty(strip_tags($question_text)) || $points <= 0) {
                    $message = "Please provide question text and valid points.";
                    $message_type = "error";
                } else {
                    // Insert question
                    $question_query = "INSERT INTO quiz_questions (quiz_id, question_text, question_type, points, order_number)
                                      VALUES (?, ?, ?, ?, ?)";
                    $question_stmt = mysqli_prepare($conn, $question_query);
                    mysqli_stmt_bind_param($question_stmt, "issii", $quiz_id, $question_text, $question_type, $points, $order_number);

                    if (mysqli_stmt_execute($question_stmt)) {
                        $question_id = mysqli_insert_id($conn);

                        // Handle answers based on question type
                        if ($question_type === 'multiple_choice') {
                            $answers = $_POST['answers'];
                            $correct_answer = (int)$_POST['correct_answer'];

                            foreach ($answers as $index => $answer_text) {
                                if (!empty($answer_text)) {
                                    $is_correct = ($index == $correct_answer) ? 1 : 0;
                                    $answer_query = "INSERT INTO quiz_question_options (question_id, option_text, is_correct, option_order) VALUES (?, ?, ?, ?)";
                                    $answer_stmt = mysqli_prepare($conn, $answer_query);
                                    $order_number = $index + 1;
                                    mysqli_stmt_bind_param($answer_stmt, "isii", $question_id, $answer_text, $is_correct, $order_number);
                                    mysqli_stmt_execute($answer_stmt);
                                }
                            }
                        } elseif ($question_type === 'true_false') {
                            $correct_answer = sanitize_input($_POST['true_false_answer']);

                            // Insert True option
                            $is_correct_true = ($correct_answer === 'true') ? 1 : 0;
                            $answer_query = "INSERT INTO quiz_question_options (question_id, option_text, is_correct, option_order) VALUES (?, 'True', ?, 1)";
                            $answer_stmt = mysqli_prepare($conn, $answer_query);
                            mysqli_stmt_bind_param($answer_stmt, "ii", $question_id, $is_correct_true);
                            mysqli_stmt_execute($answer_stmt);

                            // Insert False option
                            $is_correct_false = ($correct_answer === 'false') ? 1 : 0;
                            $answer_query = "INSERT INTO quiz_question_options (question_id, option_text, is_correct, option_order) VALUES (?, 'False', ?, 2)";
                            $answer_stmt = mysqli_prepare($conn, $answer_query);
                            mysqli_stmt_bind_param($answer_stmt, "ii", $question_id, $is_correct_false);
                            mysqli_stmt_execute($answer_stmt);
                        } elseif ($question_type === 'fill_blank') {
                            $correct_answer = sanitize_input($_POST['fill_blank_answer']);

                            $answer_query = "INSERT INTO quiz_question_options (question_id, option_text, is_correct, option_order) VALUES (?, ?, 1, 1)";
                            $answer_stmt = mysqli_prepare($conn, $answer_query);
                            mysqli_stmt_bind_param($answer_stmt, "is", $question_id, $correct_answer);
                            mysqli_stmt_execute($answer_stmt);
                        }

                        $message = "Question added successfully!";
                        $message_type = "success";
                    } else {
                        $message = "Error adding question: " . mysqli_error($conn);
                        $message_type = "error";
                    }
                }
                break;

            case 'delete_question':
                $question_id = (int)$_POST['question_id'];

                // Delete answers first (due to foreign key constraint)
                $delete_answers_query = "DELETE FROM quiz_question_options WHERE question_id = ?";
                $delete_answers_stmt = mysqli_prepare($conn, $delete_answers_query);
                mysqli_stmt_bind_param($delete_answers_stmt, "i", $question_id);
                mysqli_stmt_execute($delete_answers_stmt);

                // Delete question
                $delete_question_query = "DELETE FROM quiz_questions WHERE id = ? AND quiz_id = ?";
                $delete_question_stmt = mysqli_prepare($conn, $delete_question_query);
                mysqli_stmt_bind_param($delete_question_stmt, "ii", $question_id, $quiz_id);

                if (mysqli_stmt_execute($delete_question_stmt)) {
                    $message = "Question deleted successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error deleting question: " . mysqli_error($conn);
                    $message_type = "error";
                }
                break;

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
                            echo "<script>setTimeout(function() { window.location.href = 'view-quiz.php?id=" . $new_quiz_id . "'; }, 2000);</script>";
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
                $new_status = sanitize_input($_POST['new_status']); // Sanitize input

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

// Get quiz details
$quiz_query = "SELECT q.*, l.title as lesson_title
               FROM quizzes q
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

// Get class assignments separately
$class_assignments_query = "SELECT CONCAT(cc.subject_code, ' - ', cc.subject_title, ' (', tc.section, ')') as class_name
                           FROM quiz_class_assignments qca
                           JOIN teacher_classes tc ON qca.class_id = tc.id
                           JOIN course_curriculum cc ON tc.subject_id = cc.id
                           WHERE qca.quiz_id = ?";
$class_assignments_stmt = mysqli_prepare($conn, $class_assignments_query);
mysqli_stmt_bind_param($class_assignments_stmt, "i", $quiz_id);
mysqli_stmt_execute($class_assignments_stmt);
$class_assignments_result = mysqli_stmt_get_result($class_assignments_stmt);

$assigned_classes = [];
$class_count = 0;
while ($class = mysqli_fetch_assoc($class_assignments_result)) {
    $assigned_classes[] = $class['class_name'];
    $class_count++;
}

$quiz['assigned_classes'] = implode(', ', $assigned_classes);
$quiz['class_count'] = $class_count;

// Get questions for this quiz
$questions_query = "SELECT qq.*, COUNT(qa.id) as answer_count
                    FROM quiz_questions qq
                    LEFT JOIN quiz_question_options qa ON qq.id = qa.question_id
                    WHERE qq.quiz_id = ?
                    GROUP BY qq.id
                    ORDER BY qq.order_number, qq.created_at";
$questions_stmt = mysqli_prepare($conn, $questions_query);
mysqli_stmt_bind_param($questions_stmt, "i", $quiz_id);
mysqli_stmt_execute($questions_stmt);
$questions_result = mysqli_stmt_get_result($questions_stmt);

// Get statistics
$stats_query = "SELECT
                COUNT(*) as total_questions,
                SUM(points) as total_points,
                COUNT(CASE WHEN question_type = 'multiple_choice' THEN 1 END) as mc_questions,
                COUNT(CASE WHEN question_type = 'true_false' THEN 1 END) as tf_questions,
                COUNT(CASE WHEN question_type = 'fill_blank' THEN 1 END) as fb_questions
                FROM quiz_questions
                WHERE quiz_id = ?";
$stats_stmt = mysqli_prepare($conn, $stats_query);
mysqli_stmt_bind_param($stats_stmt, "i", $quiz_id);
mysqli_stmt_execute($stats_stmt);
$stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stats_stmt));

$page_title = 'View Quiz: ' . $quiz['title'];

// Get automatic values for new questions
$auto_values_query = "SELECT
                      COALESCE(MAX(order_number), 0) + 1 as next_order,
                      COALESCE(AVG(points), 1) as avg_points
                      FROM quiz_questions
                      WHERE quiz_id = ?";
$auto_values_stmt = mysqli_prepare($conn, $auto_values_query);
mysqli_stmt_bind_param($auto_values_stmt, "i", $quiz_id);
mysqli_stmt_execute($auto_values_stmt);
$auto_values = mysqli_fetch_assoc(mysqli_stmt_get_result($auto_values_stmt));

$next_order = $auto_values['next_order'];
$avg_points = round($auto_values['avg_points']);

// Include the shared header
$sidebar_context = 'main';
include 'includes/unified-header.php'; ?>

<!-- CKEditor Script -->
<script src="https://cdn.ckeditor.com/ckeditor5/40.0.0/classic/ckeditor.js"></script>

<div class="mb-6 sm:mb-8">
    <div class="text-center mb-6">
        <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-seait-dark mb-2"><?php echo htmlspecialchars($quiz['title']); ?></h1>
        <p class="text-sm sm:text-base text-gray-600">Quiz Details & Questions</p>
    </div>
    
    <div class="flex flex-wrap justify-center gap-2 lg:gap-3 mb-6">
        <button onclick="openAddQuestionModal()" class="bg-seait-orange text-white px-4 sm:px-6 py-2 sm:py-3 rounded-md hover:bg-orange-600 transition text-sm font-medium shadow-md hover:shadow-lg">
            <i class="fas fa-plus mr-1 sm:mr-2"></i><span class="hidden sm:inline">Add Question</span><span class="sm:hidden">Add</span>
        </button>
        <a href="edit-quiz.php?id=<?php echo encrypt_id($quiz_id); ?>" class="bg-blue-600 text-white px-4 sm:px-6 py-2 sm:py-3 rounded-md hover:bg-blue-700 transition text-sm font-medium shadow-md hover:shadow-lg">
            <i class="fas fa-edit mr-1 sm:mr-2"></i><span class="hidden sm:inline">Edit Quiz</span><span class="sm:hidden">Edit</span>
        </a>
        <button onclick="duplicateQuiz(<?php echo $quiz_id; ?>)" class="bg-purple-600 text-white px-4 sm:px-6 py-2 sm:py-3 rounded-md hover:bg-purple-700 transition text-sm font-medium shadow-md hover:shadow-lg">
            <i class="fas fa-copy mr-1 sm:mr-2"></i><span class="hidden sm:inline">Duplicate</span><span class="sm:hidden">Copy</span>
        </button>
        <button onclick="toggleQuizStatus(<?php echo $quiz_id; ?>, '<?php echo $quiz['status']; ?>')"
                class="<?php echo $quiz['status'] === 'published' ? 'bg-yellow-600 hover:bg-yellow-700' : 'bg-green-600 hover:bg-green-700'; ?> text-white px-4 sm:px-6 py-2 sm:py-3 rounded-md transition text-sm font-medium shadow-md hover:shadow-lg">
            <i class="fas fa-<?php echo $quiz['status'] === 'published' ? 'eye-slash' : 'eye'; ?> mr-1 sm:mr-2"></i>
            <span class="hidden sm:inline"><?php echo $quiz['status'] === 'published' ? 'Unpublish' : 'Publish'; ?></span>
            <span class="sm:hidden"><?php echo $quiz['status'] === 'published' ? 'Hide' : 'Show'; ?></span>
        </button>
        <a href="quiz-statistics.php?id=<?php echo encrypt_id($quiz_id); ?>" class="bg-indigo-600 text-white px-4 sm:px-6 py-2 sm:py-3 rounded-md hover:bg-indigo-700 transition text-sm font-medium shadow-md hover:shadow-lg">
            <i class="fas fa-chart-bar mr-1 sm:mr-2"></i><span class="hidden sm:inline">Statistics</span><span class="sm:hidden">Stats</span>
        </a>
        <a href="quizzes.php" class="bg-gray-500 text-white px-4 sm:px-6 py-2 sm:py-3 rounded-md hover:bg-gray-600 transition text-sm font-medium shadow-md hover:shadow-lg">
            <i class="fas fa-arrow-left mr-1 sm:mr-2"></i><span class="hidden sm:inline">Back to Quizzes</span><span class="sm:hidden">Back</span>
        </a>
    </div>
</div>

<?php if ($message): ?>
<div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800'; ?>">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-4 sm:gap-6">
    <!-- Main Content -->
    <div class="xl:col-span-2 space-y-4 sm:space-y-6">
        <!-- Quiz Information -->
        <div class="bg-white rounded-lg shadow-md">
            
            <div class="p-4 sm:p-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
                    <div class="space-y-3">
                        <div>
                            <h3 class="text-sm font-medium text-gray-700 mb-1">Description</h3>
                            <p class="text-sm text-gray-900"><?php echo $quiz['description'] ? htmlspecialchars($quiz['description']) : 'No description provided'; ?></p>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-700 mb-1">Quiz Type</h3>
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php echo $quiz['quiz_type'] === 'general' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800'; ?>">
                                <?php
                                if ($quiz['quiz_type'] === 'lesson_specific' && $quiz['lesson_title']) {
                                    echo htmlspecialchars($quiz['lesson_title']);
                                } else {
                                    echo ucfirst(str_replace('_', ' ', $quiz['quiz_type']));
                                }
                                ?>
                            </span>
                        </div>
                    </div>
                    <div class="space-y-3">
                        <div>
                            <h3 class="text-sm font-medium text-gray-700 mb-1">Assigned Classes</h3>
                            <p class="text-sm text-gray-900"><?php echo $quiz['assigned_classes'] ? htmlspecialchars($quiz['assigned_classes']) : 'Not assigned'; ?></p>
                            <p class="text-xs text-gray-500"><?php echo $quiz['class_count']; ?> class(es) assigned</p>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-700 mb-1">Settings</h3>
                            <div class="text-sm text-gray-900 space-y-1">
                                <div>Time Limit: <?php echo $quiz['time_limit'] ? $quiz['time_limit'] . ' minutes' : 'No limit'; ?></div>
                                <div>Passing Score: <?php echo $quiz['passing_score']; ?>%</div>
                                <div>Max Attempts: <?php echo $quiz['max_attempts']; ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Questions List -->
        <div class="bg-white rounded-lg shadow-md">
            

            <?php if (mysqli_num_rows($questions_result) == 0): ?>
                <div class="p-6 sm:p-8 text-center">
                    <i class="fas fa-question-circle text-gray-300 text-4xl sm:text-5xl mb-4 sm:mb-6"></i>
                    <p class="text-gray-500 text-base sm:text-lg mb-4 sm:mb-6">No questions added to this quiz yet.</p>
                    <button onclick="openAddQuestionModal()" class="inline-block bg-seait-orange text-white px-4 sm:px-6 py-2 sm:py-3 rounded-md hover:bg-orange-600 transition text-sm sm:text-base font-medium">
                        <i class="fas fa-plus mr-2"></i>Add Your First Question
                    </button>
                </div>
            <?php else: ?>
                <div class="divide-y divide-gray-200">
                    <?php $question_number = 1; ?>
                    <?php while ($question = mysqli_fetch_assoc($questions_result)): ?>
                    <div class="p-4 sm:p-6 hover:bg-gray-50 transition-colors duration-200">
                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 sm:gap-4 mb-4">
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-2 sm:gap-3 mb-3">
                                    <span class="inline-flex items-center justify-center w-6 h-6 bg-seait-orange text-white text-xs font-bold rounded-full"><?php echo $question_number; ?></span>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium
                                                 <?php echo $question['question_type'] === 'multiple_choice' ? 'bg-blue-100 text-blue-800 border border-blue-200' :
                                                        ($question['question_type'] === 'true_false' ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-purple-100 text-purple-800 border border-purple-200'); ?>">
                                        <i class="fas fa-<?php echo $question['question_type'] === 'multiple_choice' ? 'list-ul' : ($question['question_type'] === 'true_false' ? 'check-circle' : 'pencil-alt'); ?> mr-1"></i>
                                        <?php echo ucfirst(str_replace('_', ' ', $question['question_type'])); ?>
                                    </span>
                                    <span class="inline-flex items-center px-2 py-1 bg-yellow-100 text-yellow-800 text-xs font-medium rounded-md border border-yellow-200">
                                        <i class="fas fa-star mr-1"></i><?php echo $question['points']; ?> points
                                    </span>
                                </div>
                                <div class="text-sm text-gray-900 break-words leading-relaxed bg-gray-50 p-3 rounded-md border-l-4 border-seait-orange">
                                    <?php echo html_entity_decode($question['question_text']); ?>
                                </div>
                            </div>
                            <div class="flex gap-2 sm:gap-3 flex-shrink-0">
                                <button onclick="editQuestion(<?php echo $question['id']; ?>)" 
                                        class="inline-flex items-center px-3 py-2 text-sm font-medium text-green-600 bg-green-50 border border-green-200 rounded-md hover:bg-green-100 hover:text-green-700 transition-colors duration-200" 
                                        title="Edit Question">
                                    <i class="fas fa-edit mr-1"></i>
                                    <span class="hidden sm:inline">Edit</span>
                                </button>
                                <button onclick="deleteQuestion(<?php echo $question['id']; ?>)" 
                                        class="inline-flex items-center px-3 py-2 text-sm font-medium text-red-600 bg-red-50 border border-red-200 rounded-md hover:bg-red-100 hover:text-red-700 transition-colors duration-200" 
                                        title="Delete Question">
                                    <i class="fas fa-trash mr-1"></i>
                                    <span class="hidden sm:inline">Delete</span>
                                </button>
                            </div>
                        </div>

                        <!-- Show answers for multiple choice and true/false -->
                        <?php if (in_array($question['question_type'], ['multiple_choice', 'true_false'])): ?>
                        <div class="ml-4 sm:ml-6 mt-4">
                            <?php
                            $answers_query = "SELECT * FROM quiz_question_options WHERE question_id = ? ORDER BY option_order";
                            $answers_stmt = mysqli_prepare($conn, $answers_query);
                            mysqli_stmt_bind_param($answers_stmt, "i", $question['id']);
                            mysqli_stmt_execute($answers_stmt);
                            $answers_result = mysqli_stmt_get_result($answers_stmt);
                            ?>
                            <div class="space-y-2">
                                <?php $option_letter = 'A'; ?>
                                <?php while ($answer = mysqli_fetch_assoc($answers_result)): ?>
                                <div class="flex items-start gap-3 p-2 rounded-md <?php echo $answer['is_correct'] ? 'bg-green-50 border border-green-200' : 'bg-gray-50 border border-gray-200'; ?>">
                                    <div class="flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold flex-shrink-0
                                                <?php echo $answer['is_correct'] ? 'bg-green-500 text-white' : 'bg-gray-300 text-gray-700'; ?>">
                                        <?php echo $option_letter; ?>
                                    </div>
                                    <span class="text-sm text-gray-700 <?php echo $answer['is_correct'] ? 'font-semibold' : ''; ?> break-words flex-1">
                                        <?php echo htmlspecialchars($answer['option_text']); ?>
                                    </span>
                                    <?php if ($answer['is_correct']): ?>
                                        <span class="inline-flex items-center px-2 py-1 text-xs font-medium text-green-800 bg-green-100 rounded-full flex-shrink-0">
                                            <i class="fas fa-check mr-1"></i>Correct
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <?php $option_letter++; ?>
                                <?php endwhile; ?>
                            </div>
                        </div>
                        <?php elseif ($question['question_type'] === 'fill_blank'): ?>
                        <div class="ml-4 sm:ml-6 mt-4">
                            <?php
                            $answer_query = "SELECT * FROM quiz_question_options WHERE question_id = ? LIMIT 1";
                            $answer_stmt = mysqli_prepare($conn, $answer_query);
                            mysqli_stmt_bind_param($answer_stmt, "i", $question['id']);
                            mysqli_stmt_execute($answer_stmt);
                            $answer_result = mysqli_stmt_get_result($answer_stmt);
                            $answer = mysqli_fetch_assoc($answer_result);
                            ?>
                            <div class="flex items-center gap-3 p-3 bg-blue-50 border border-blue-200 rounded-md">
                                <div class="flex items-center justify-center w-6 h-6 bg-blue-500 text-white text-xs font-bold rounded-full flex-shrink-0">
                                    <i class="fas fa-key"></i>
                                </div>
                                <div class="flex-1">
                                    <span class="text-sm font-medium text-blue-800">Correct Answer:</span>
                                    <span class="text-sm text-gray-700 ml-2 font-mono bg-white px-2 py-1 rounded border"><?php echo htmlspecialchars($answer['option_text']); ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php $question_number++; ?>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="space-y-4 sm:space-y-6">
        <!-- Quiz Statistics -->
        <div class="bg-white rounded-lg shadow-md">
            <div class="p-4 sm:p-6 space-y-3 sm:space-y-4">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Total Questions:</span>
                    <span class="text-sm font-medium text-gray-900"><?php echo $stats['total_questions'] ?? 0; ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Total Points:</span>
                    <span class="text-sm font-medium text-gray-900"><?php echo $stats['total_points'] ?? 0; ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Multiple Choice:</span>
                    <span class="text-sm font-medium text-gray-900"><?php echo $stats['mc_questions'] ?? 0; ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">True/False:</span>
                    <span class="text-sm font-medium text-gray-900"><?php echo $stats['tf_questions'] ?? 0; ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Fill in Blanks:</span>
                    <span class="text-sm font-medium text-gray-900"><?php echo $stats['fb_questions'] ?? 0; ?></span>
                </div>
            </div>
        </div>

        <!-- Quiz Status -->
        <div class="bg-white rounded-lg shadow-md">
            <div class="p-4 sm:p-6">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium <?php echo $quiz['status'] === 'published' ? 'bg-green-100 text-green-800' : ($quiz['status'] === 'draft' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800'); ?>">
                    <?php echo ucfirst($quiz['status']); ?>
                </span>
                <p class="text-sm text-gray-600 mt-2">Created: <?php echo date('M j, Y', strtotime($quiz['created_at'])); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Add Question Modal -->
<div id="addQuestionModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-4 sm:top-10 mx-auto p-4 sm:p-5 border w-11/12 md:w-4/5 lg:w-3/4 xl:w-2/3 shadow-lg rounded-md bg-white max-h-[95vh] sm:max-h-[90vh] overflow-y-auto">
        <div class="mt-3">
            <!-- Modal Header -->
            <div class="flex items-center justify-between mb-4 sm:mb-6 pb-4 border-b border-gray-200">
                <div>
                    <h3 class="text-lg sm:text-xl font-semibold text-gray-900">Add New Question</h3>
                    <p class="text-xs sm:text-sm text-gray-600 mt-1">Create a new question for your quiz</p>
                </div>
                <button onclick="closeAddQuestionModal()" class="text-gray-400 hover:text-gray-600 transition-colors p-1">
                    <i class="fas fa-times text-lg sm:text-xl"></i>
                </button>
            </div>

            <form method="POST" class="space-y-4 sm:space-y-6" onsubmit="prepareFormSubmission(event)">
                <input type="hidden" name="action" value="add_question">
                <input type="hidden" name="question_text" id="questionTextHidden">

                <!-- Question Type Selection -->
                <div class="bg-gray-50 p-3 sm:p-4 rounded-lg">
                    <h4 class="text-sm font-semibold text-gray-700 mb-2 sm:mb-3">Question Type</h4>
                    <select name="question_type" id="questionType" onchange="toggleQuestionFields()" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange bg-white text-sm">
                        <option value="">Select Question Type</option>
                        <option value="multiple_choice">Multiple Choice</option>
                        <option value="true_false">True/False</option>
                        <option value="fill_blank">Fill in the Blank</option>
                    </select>
                    <p class="text-xs text-gray-500 mt-2">Choose the type of question you want to create</p>
                </div>

                <!-- Question Content -->
                <div class="bg-gray-50 p-4 sm:p-6 rounded-lg border border-gray-200">
                    <div class="flex items-center mb-3 sm:mb-4">
                        <div class="w-6 h-6 sm:w-8 sm:h-8 bg-seait-orange rounded-full flex items-center justify-center mr-2 sm:mr-3">
                            <i class="fas fa-edit text-white text-xs sm:text-sm"></i>
                        </div>
                        <div>
                            <h4 class="text-sm sm:text-base font-semibold text-gray-900">Question Content</h4>
                            <p class="text-xs text-gray-600">Write your question and configure settings</p>
                        </div>
                    </div>

                    <div class="space-y-4 sm:space-y-6">
                        <!-- Question Text Section -->
                        <div class="bg-white p-3 sm:p-4 rounded-lg border border-gray-200">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 sm:gap-0 mb-2 sm:mb-3">
                                <label class="text-sm font-medium text-gray-700">Question Text *</label>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">Rich Text Editor</span>
                                    <button type="button" onclick="toggleQuestionPreview()" class="text-xs text-seait-orange hover:text-orange-600 flex items-center">
                                        <i class="fas fa-eye mr-1"></i> Preview
                                    </button>
                                </div>
                            </div>
                            <div class="relative">
                                <textarea id="questionTextEditor" rows="4"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange bg-white text-sm"
                                          placeholder="Enter your question here..."></textarea>
                                <div class="absolute top-2 right-2 text-xs text-gray-400">
                                    <i class="fas fa-edit"></i>
                                </div>
                            </div>
                            <div class="mt-2 flex items-start text-xs text-gray-500">
                                <i class="fas fa-info-circle mr-1 mt-0.5 flex-shrink-0"></i>
                                <span>Write a clear and concise question. You can use rich text formatting including bold, italic, lists, and links.</span>
                            </div>
                        </div>

                        <!-- Question Settings Section -->
                        <div class="bg-white p-3 sm:p-4 rounded-lg border border-gray-200">
                            <div class="flex items-center mb-2 sm:mb-3">
                                <i class="fas fa-cog text-gray-500 mr-2"></i>
                                <h5 class="text-sm font-medium text-gray-700">Question Settings</h5>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                                <!-- Points Configuration -->
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-gray-700">Points *</label>
                                    <div class="relative">
                                        <input type="number" name="points" id="pointsInput" value="<?php echo $avg_points; ?>" min="1" max="100" required
                                               class="w-full px-3 py-2 pl-8 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange bg-white text-sm">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-star text-yellow-500 text-sm"></i>
                                        </div>
                                    </div>
                                    <div class="flex items-center text-xs text-gray-500">
                                        <i class="fas fa-chart-line mr-1"></i>
                                        <span>Points awarded for correct answer (average: <?php echo $avg_points; ?>)</span>
                                    </div>
                                </div>

                                <!-- Order Configuration -->
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-gray-700">Order Number</label>
                                    <div class="relative">
                                        <input type="number" name="order_number" id="orderNumberInput" value="<?php echo $next_order; ?>" min="1" readonly
                                               class="w-full px-3 py-2 pl-8 border border-gray-300 rounded-md bg-gray-100 text-gray-600 text-sm">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-sort-numeric-up text-gray-400 text-sm"></i>
                                        </div>
                                    </div>
                                    <div class="flex items-center text-xs text-gray-500">
                                        <i class="fas fa-magic mr-1"></i>
                                        <span>Automatically set based on existing questions</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Question Preview Section (Hidden by default, can be toggled) -->
                        <div class="bg-white p-3 sm:p-4 rounded-lg border border-gray-200 hidden" id="questionPreviewSection">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 sm:gap-0 mb-2 sm:mb-3">
                                <div class="flex items-center">
                                    <i class="fas fa-eye text-gray-500 mr-2"></i>
                                    <h5 class="text-sm font-medium text-gray-700">Question Preview</h5>
                                </div>
                                <button type="button" onclick="toggleQuestionPreview()" class="text-xs text-seait-orange hover:text-orange-600">
                                    <i class="fas fa-times"></i> Hide
                                </button>
                            </div>
                            <div id="questionPreviewContent" class="text-sm text-gray-700 bg-gray-50 p-3 rounded border">
                                <p class="text-gray-500 italic">Question preview will appear here...</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Answer Options Section -->
                <div id="answerOptionsSection" class="hidden">
                    <!-- Multiple Choice Fields -->
                    <div id="multipleChoiceFields" class="hidden bg-gray-50 p-3 sm:p-4 rounded-lg">
                        <h4 class="text-sm font-semibold text-gray-700 mb-2 sm:mb-3">Answer Options</h4>
                        <p class="text-xs text-gray-500 mb-3 sm:mb-4">Provide 4 answer options and select the correct one</p>
                        <div class="space-y-3">
                            <?php for ($i = 0; $i < 4; $i++): ?>
                            <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3 p-3 bg-white rounded-md border border-gray-200">
                                <div class="flex items-center gap-2">
                                    <input type="radio" name="correct_answer" value="<?php echo $i; ?>" required
                                           class="text-seait-orange focus:ring-seait-orange">
                                    <span class="text-xs text-gray-500 w-12 sm:w-16 text-center">Option <?php echo $i + 1; ?></span>
                                </div>
                                <input type="text" name="answers[]" placeholder="Option <?php echo $i + 1; ?>"
                                       class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange bg-white text-sm">
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <!-- True/False Fields -->
                    <div id="trueFalseFields" class="hidden bg-gray-50 p-3 sm:p-4 rounded-lg">
                        <h4 class="text-sm font-semibold text-gray-700 mb-2 sm:mb-3">Correct Answer</h4>
                        <p class="text-xs text-gray-500 mb-3 sm:mb-4">Select whether the statement is True or False</p>
                        <div class="space-y-3">
                            <label class="flex items-center p-3 bg-white rounded-md border border-gray-200 hover:bg-gray-50 cursor-pointer">
                                <input type="radio" name="true_false_answer" value="true" required
                                       class="mr-3 text-seait-orange focus:ring-seait-orange">
                                <span class="text-sm text-gray-700 font-medium">True</span>
                            </label>
                            <label class="flex items-center p-3 bg-white rounded-md border border-gray-200 hover:bg-gray-50 cursor-pointer">
                                <input type="radio" name="true_false_answer" value="false" required
                                       class="mr-3 text-seait-orange focus:ring-seait-orange">
                                <span class="text-sm text-gray-700 font-medium">False</span>
                            </label>
                        </div>
                    </div>

                    <!-- Fill in Blank Fields -->
                    <div id="fillBlankFields" class="hidden bg-gray-50 p-3 sm:p-4 rounded-lg">
                        <h4 class="text-sm font-semibold text-gray-700 mb-2 sm:mb-3">Correct Answer</h4>
                        <p class="text-xs text-gray-500 mb-3 sm:mb-4">Enter the correct answer for the blank</p>
                        <div>
                            <input type="text" name="fill_blank_answer"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange bg-white text-sm"
                                   placeholder="Enter the correct answer">
                            <p class="text-xs text-gray-500 mt-1">Students must type this exact answer to get points</p>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="flex flex-col sm:flex-row justify-end gap-2 sm:gap-3 pt-4 border-t border-gray-200">
                    <button type="button" onclick="closeAddQuestionModal()"
                            class="bg-gray-500 text-white px-4 sm:px-6 py-2 rounded-md hover:bg-gray-600 transition-colors text-sm">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </button>
                    <button type="submit" id="submitQuestionBtn" disabled
                            class="bg-seait-orange text-white px-4 sm:px-6 py-2 rounded-md hover:bg-orange-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed text-sm">
                        <i class="fas fa-save mr-2"></i>Add Question
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let questionEditor = null;

function openAddQuestionModal() {
    document.getElementById('addQuestionModal').classList.remove('hidden');
    // Reset form state
    resetQuestionForm();
    // Initialize CKEditor
    initializeCKEditor();
    // Update automatic values
    updateAutomaticValues();
}

function closeAddQuestionModal() {
    document.getElementById('addQuestionModal').classList.add('hidden');
    // Reset form
    resetQuestionForm();
    // Destroy CKEditor
    if (questionEditor) {
        questionEditor.destroy();
        questionEditor = null;
    }
}

function resetQuestionForm() {
    // Reset form fields
    document.getElementById('questionType').value = '';
    document.getElementById('multipleChoiceFields').classList.add('hidden');
    document.getElementById('trueFalseFields').classList.add('hidden');
    document.getElementById('fillBlankFields').classList.add('hidden');
    document.getElementById('answerOptionsSection').classList.add('hidden');
    document.getElementById('questionPreviewSection').classList.add('hidden'); // Hide preview on reset

    // Reset form inputs
    if (questionEditor) {
        questionEditor.setData('');
    } else {
        document.querySelector('#questionTextEditor').value = '';
    }

    // Reset to automatic values
    document.querySelector('input[name="points"]').value = '<?php echo $avg_points; ?>';
    document.querySelector('input[name="order_number"]').value = '<?php echo $next_order; ?>';

    // Reset radio buttons and remove required attributes
    document.querySelectorAll('input[type="radio"]').forEach(radio => {
        radio.checked = false;
        radio.removeAttribute('required');
    });

    // Reset text inputs
    document.querySelectorAll('input[name="answers[]"]').forEach(input => {
        input.value = '';
    });
    const fillBlankInput = document.querySelector('input[name="fill_blank_answer"]');
    if (fillBlankInput) {
        fillBlankInput.value = '';
        fillBlankInput.removeAttribute('required');
    }

    // Disable submit button
    document.getElementById('submitQuestionBtn').disabled = true;
}

function toggleQuestionFields() {
    const questionType = document.getElementById('questionType').value;
    const answerOptionsSection = document.getElementById('answerOptionsSection');
    const multipleChoiceFields = document.getElementById('multipleChoiceFields');
    const trueFalseFields = document.getElementById('trueFalseFields');
    const fillBlankFields = document.getElementById('fillBlankFields');

    // Hide all fields first
    answerOptionsSection.classList.add('hidden');
    multipleChoiceFields.classList.add('hidden');
    trueFalseFields.classList.add('hidden');
    fillBlankFields.classList.add('hidden');

    // Remove required attribute from all question type inputs initially
    document.querySelectorAll('input[name="correct_answer"]').forEach(radio => {
        radio.removeAttribute('required');
    });
    document.querySelectorAll('input[name="true_false_answer"]').forEach(radio => {
        radio.removeAttribute('required');
    });
    document.querySelector('input[name="fill_blank_answer"]').removeAttribute('required');

    // Show appropriate fields based on selection
    if (questionType === 'multiple_choice') {
        answerOptionsSection.classList.remove('hidden');
        multipleChoiceFields.classList.remove('hidden');
        // Add required attribute to multiple choice radio buttons
        document.querySelectorAll('input[name="correct_answer"]').forEach(radio => {
            radio.setAttribute('required', 'required');
        });
    } else if (questionType === 'true_false') {
        answerOptionsSection.classList.remove('hidden');
        trueFalseFields.classList.remove('hidden');
        // Add required attribute to true/false radio buttons
        document.querySelectorAll('input[name="true_false_answer"]').forEach(radio => {
            radio.setAttribute('required', 'required');
        });
    } else if (questionType === 'fill_blank') {
        answerOptionsSection.classList.remove('hidden');
        fillBlankFields.classList.remove('hidden');
        // Add required attribute to fill in blank input
        document.querySelector('input[name="fill_blank_answer"]').setAttribute('required', 'required');
    }

    // Validate form
    validateQuestionForm();
}

function validateQuestionForm() {
    const questionType = document.getElementById('questionType').value;

    // Get question text from CKEditor or textarea, and strip HTML tags for validation
    let questionText = '';
    if (questionEditor) {
        questionText = questionEditor.getData().trim();
        // Strip HTML tags for validation (keep only text content)
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = questionText;
        questionText = tempDiv.textContent || tempDiv.innerText || '';
    } else {
        questionText = document.querySelector('#questionTextEditor').value.trim();
    }

    const points = document.querySelector('input[name="points"]').value;
    const submitBtn = document.getElementById('submitQuestionBtn');

    let isValid = questionType && questionText && points > 0;

    // Additional validation based on question type
    if (questionType === 'multiple_choice') {
        const answers = document.querySelectorAll('input[name="answers[]"]');
        const correctAnswer = document.querySelector('input[name="correct_answer"]:checked');
        let hasAnswers = false;

        answers.forEach(answer => {
            if (answer.value.trim()) hasAnswers = true;
        });

        isValid = isValid && hasAnswers && correctAnswer;

        // Remove required attribute from hidden radio buttons to prevent validation errors
        document.querySelectorAll('input[name="true_false_answer"]').forEach(radio => {
            radio.removeAttribute('required');
        });
        document.querySelector('input[name="fill_blank_answer"]').removeAttribute('required');
    } else if (questionType === 'true_false') {
        const correctAnswer = document.querySelector('input[name="true_false_answer"]:checked');
        isValid = isValid && correctAnswer;

        // Remove required attribute from other question type inputs
        document.querySelectorAll('input[name="correct_answer"]').forEach(radio => {
            radio.removeAttribute('required');
        });
        document.querySelector('input[name="fill_blank_answer"]').removeAttribute('required');
    } else if (questionType === 'fill_blank') {
        const correctAnswer = document.querySelector('input[name="fill_blank_answer"]').value.trim();
        isValid = isValid && correctAnswer;

        // Remove required attribute from radio buttons
        document.querySelectorAll('input[name="correct_answer"]').forEach(radio => {
            radio.removeAttribute('required');
        });
        document.querySelectorAll('input[name="true_false_answer"]').forEach(radio => {
            radio.removeAttribute('required');
        });
    } else {
        // No question type selected, remove all required attributes
        document.querySelectorAll('input[name="correct_answer"]').forEach(radio => {
            radio.removeAttribute('required');
        });
        document.querySelectorAll('input[name="true_false_answer"]').forEach(radio => {
            radio.removeAttribute('required');
        });
        document.querySelector('input[name="fill_blank_answer"]').removeAttribute('required');
    }

    submitBtn.disabled = !isValid;
}

// Add event listeners for form validation
document.addEventListener('DOMContentLoaded', function() {
    // Question type change
    document.getElementById('questionType').addEventListener('change', validateQuestionForm);

    // Points change
    document.querySelector('input[name="points"]').addEventListener('input', validateQuestionForm);

    // Multiple choice answers change
    document.querySelectorAll('input[name="answers[]"]').forEach(input => {
        input.addEventListener('input', validateQuestionForm);
    });

    // Radio button changes
    document.querySelectorAll('input[type="radio"]').forEach(radio => {
        radio.addEventListener('change', validateQuestionForm);
    });

    // Fill in blank answer change
    document.querySelector('input[name="fill_blank_answer"]').addEventListener('input', validateQuestionForm);

    // Question text change (for preview updates)
    document.querySelector('#questionTextEditor').addEventListener('input', function() {
        validateQuestionForm();
        updateQuestionPreview();
    });
});

function editQuestion(questionId) {
    alert('Edit question functionality will be implemented here.');
}

function deleteQuestion(questionId) {
    if (confirm('Are you sure you want to delete this question? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_question">
            <input type="hidden" name="question_id" value="${questionId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Close modal when clicking outside
document.getElementById('addQuestionModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeAddQuestionModal();
    }
});

function prepareFormSubmission(event) {
    // Get CKEditor content and put it in hidden field
    if (questionEditor) {
        const editorContent = questionEditor.getData().trim();
        if (editorContent) {
            document.getElementById('questionTextHidden').value = editorContent;
        } else {
            // If CKEditor is empty, prevent form submission
            event.preventDefault();
            alert('Please enter a question.');
            return false;
        }
    } else {
        // Fallback for when CKEditor is not active
        const textareaContent = document.querySelector('#questionTextEditor').value.trim();
        if (textareaContent) {
            document.getElementById('questionTextHidden').value = textareaContent;
        } else {
            // If textarea is empty, prevent form submission
            event.preventDefault();
            alert('Please enter a question.');
            return false;
        }
    }

    // Allow form submission
    return true;
}

function initializeCKEditor() {
    if (questionEditor) {
        questionEditor.destroy();
    }

    ClassicEditor
        .create(document.querySelector('#questionTextEditor'), {
            toolbar: ['heading', '|', 'bold', 'italic', 'underline', 'strikethrough', '|', 'bulletedList', 'numberedList', '|', 'link', '|', 'undo', 'redo'],
            placeholder: 'Enter your question here...',
            height: '200px'
        })
        .then(editor => {
            questionEditor = editor;
            // Update validation when content changes
            editor.model.document.on('change:data', () => {
                validateQuestionForm();
                updateQuestionPreview(); // Update preview in real-time
            });
        })
        .catch(error => {
            console.error(error);
        });
}

function updateAutomaticValues() {
    // Fetch updated automatic values from server
    fetch(`get-quiz-auto-values.php?quiz_id=<?php echo $quiz_id; ?>`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('pointsInput').value = data.avg_points;
                document.getElementById('orderNumberInput').value = data.next_order;
                // Update help text - fix the selector to target the span element
                const helpTextElement = document.querySelector('#pointsInput').closest('.space-y-2').querySelector('.text-xs.text-gray-500 span');
                if (helpTextElement) {
                    helpTextElement.textContent = `Points awarded for correct answer (average: ${data.avg_points})`;
                }
            }
        })
        .catch(error => {
            console.error('Error fetching automatic values:', error);
        });
}

function toggleQuestionPreview() {
    const previewSection = document.getElementById('questionPreviewSection');
    const previewContent = document.getElementById('questionPreviewContent');
    const previewButton = document.querySelector('button[onclick="toggleQuestionPreview()"]');

    if (previewSection.classList.contains('hidden')) {
        // Show preview
        previewSection.classList.remove('hidden');

        // Get content from CKEditor or textarea
        let content = '';
        if (questionEditor) {
            content = questionEditor.getData();
        } else {
            content = document.querySelector('#questionTextEditor').value;
        }

        // Update preview content
        if (content.trim()) {
            previewContent.innerHTML = content;
        } else {
            previewContent.innerHTML = '<p class="text-gray-500 italic">No content to preview. Start typing your question...</p>';
        }

        // Update button
        previewButton.innerHTML = '<i class="fas fa-eye-slash mr-1"></i> Hide Preview';
    } else {
        // Hide preview
        previewSection.classList.add('hidden');
        previewContent.innerHTML = '<p class="text-gray-500 italic">Question preview will appear here...</p>';

        // Update button
        previewButton.innerHTML = '<i class="fas fa-eye mr-1"></i> Preview';
    }
}

// Function to update preview content in real-time
function updateQuestionPreview() {
    const previewSection = document.getElementById('questionPreviewSection');
    const previewContent = document.getElementById('questionPreviewContent');

    if (!previewSection.classList.contains('hidden')) {
        let content = '';
        if (questionEditor) {
            content = questionEditor.getData();
        } else {
            content = document.querySelector('#questionTextEditor').value;
        }

        if (content.trim()) {
            previewContent.innerHTML = content;
        } else {
            previewContent.innerHTML = '<p class="text-gray-500 italic">No content to preview. Start typing your question...</p>';
        }
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
</script>

<?php include 'includes/footer.php'; ?>

<style>
/* Ensure proper spacing for quiz information */
.grid.grid-cols-1.md\:grid-cols-2 {
    gap: 1.5rem;
}

/* Fix for question display */
.divide-y.divide-gray-200 > div {
    padding: 1.5rem;
}

/* Ensure proper spacing for answer options */
.space-y-2 > div {
    margin-bottom: 0.5rem;
}

.space-y-2 > div:last-child {
    margin-bottom: 0;
}

/* Fix for correct answer indicators */
.w-4.h-4.rounded-full {
    display: inline-block;
    vertical-align: middle;
}

/* Ensure proper button spacing */
.flex.space-x-2 > * {
    margin-right: 0.5rem;
}

.flex.space-x-2 > *:last-child {
    margin-right: 0;
}

/* Fix for status badges */
.inline-flex.items-center.px-2.py-1 {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.5rem;
}

/* Ensure proper text wrapping */
.text-sm {
    line-height: 1.4;
}

/* Fix for question type badges */
.inline-flex.items-center.px-2.py-1.rounded-full {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.5rem;
    border-radius: 9999px;
}

/* Ensure proper modal spacing */
.space-y-4 > div {
    margin-bottom: 1rem;
}

.space-y-4 > div:last-child {
    margin-bottom: 0;
}

/* Fix for form fields */
.space-y-3 > div {
    margin-bottom: 0.75rem;
}

.space-y-3 > div:last-child {
    margin-bottom: 0;
}

/* Ensure proper icon spacing */
.fas {
    display: inline-block;
    width: 1em;
    text-align: center;
}

/* Fix for statistics display */
.space-y-4 > div {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

/* Ensure proper hover states */
.hover\:bg-orange-600:hover {
    background-color: #ea580c;
}

.hover\:bg-gray-600:hover {
    background-color: #4b5563;
}

/* Fix for empty state */
.p-8.text-center {
    padding: 2rem;
    text-align: center;
}

.text-5xl {
    font-size: 3rem;
    line-height: 1;
}

.text-lg {
    font-size: 1.125rem;
    line-height: 1.75;
}

/* Modal specific styles */
.max-h-\[90vh\] {
    max-height: 90vh;
}

/* Form section styling */
.bg-gray-50 {
    background-color: #f9fafb;
}

.bg-gray-50 .bg-white {
    background-color: #ffffff;
}

/* Input focus states */
.focus\:ring-2:focus {
    box-shadow: 0 0 0 2px rgba(255, 107, 53, 0.2);
}

/* Button states */
.disabled\:opacity-50:disabled {
    opacity: 0.5;
}

.disabled\:cursor-not-allowed:disabled {
    cursor: not-allowed;
}

/* Hover effects for interactive elements */
.hover\:bg-gray-50:hover {
    background-color: #f9fafb;
}

.cursor-pointer {
    cursor: pointer;
}

/* Transition effects */
.transition-colors {
    transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out;
}

/* Form validation visual feedback */
input:invalid, textarea:invalid, select:invalid {
    border-color: #ef4444;
}

input:valid, textarea:valid, select:valid {
    border-color: #10b981;
}

/* Modal overlay */
.fixed.inset-0 {
    position: fixed;
    top: 0;
    right: 0;
    bottom: 0;
    left: 0;
}

/* Ensure proper z-index stacking */
.z-50 {
    z-index: 50;
}

/* Responsive modal sizing */
@media (max-width: 640px) {
    .w-11\/12 {
        width: 91.666667%;
    }
}

@media (min-width: 768px) {
    .md\:w-4\/5 {
        width: 80%;
    }
}

@media (min-width: 1024px) {
    .lg\:w-3\/4 {
        width: 75%;
    }
}

@media (min-width: 1280px) {
    .xl\:w-2\/3 {
        width: 66.666667%;
    }
}

/* Question Content Container specific styles */
.bg-gray-50.p-6.rounded-lg.border {
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
}

/* Icon container styles */
.w-8.h-8.bg-seait-orange.rounded-full {
    box-shadow: 0 2px 4px 0 rgba(255, 107, 53, 0.3);
}

/* Section card styles */
.bg-white.p-4.rounded-lg.border {
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    transition: box-shadow 0.15s ease-in-out;
}

.bg-white.p-4.rounded-lg.border:hover {
    box-shadow: 0 2px 4px 0 rgba(0, 0, 0, 0.1);
}

/* Input icon positioning */
.relative .absolute.inset-y-0.left-0 {
    z-index: 10;
}

/* Preview section styles */
#questionPreviewSection {
    transition: all 0.3s ease-in-out;
}

#questionPreviewContent {
    min-height: 60px;
    max-height: 200px;
    overflow-y: auto;
}

/* Rich text editor indicator */
.text-xs.text-gray-500.bg-gray-100.px-2.py-1.rounded {
    font-weight: 500;
    letter-spacing: 0.025em;
}

/* Settings section header */
.flex.items-center.mb-3 .fas.fa-cog {
    animation: spin 2s linear infinite;
    animation-play-state: paused;
}

.flex.items-center.mb-3:hover .fas.fa-cog {
    animation-play-state: running;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Points input star icon */
.fas.fa-star.text-yellow-500 {
    filter: drop-shadow(0 1px 2px rgba(245, 158, 11, 0.3));
}

/* Order input icon */
.fas.fa-sort-numeric-up.text-gray-400 {
    opacity: 0.7;
}

/* Preview button styles */
button[onclick="toggleQuestionPreview()"] {
    transition: all 0.2s ease-in-out;
}

button[onclick="toggleQuestionPreview()"]:hover {
    transform: translateY(-1px);
}

/* Info text styles */
.flex.items-center.text-xs.text-gray-500 .fas {
    opacity: 0.8;
}

/* Question text area focus enhancement */
#questionTextEditor:focus {
    box-shadow: 0 0 0 3px rgba(255, 107, 53, 0.1);
    border-color: #ff6b35;
}

/* Settings grid responsive enhancement */
@media (max-width: 768px) {
    .grid.grid-cols-1.md\:grid-cols-2 {
        grid-template-columns: 1fr;
    }

    .space-y-6 > div {
        margin-bottom: 1.5rem;
    }
}

/* Enhanced Question Card Styles */
.divide-y.divide-gray-200 > div {
    transition: all 0.2s ease-in-out;
}

.divide-y.divide-gray-200 > div:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

/* Question number badge enhancement */
.inline-flex.items-center.justify-center.w-6.h-6.bg-seait-orange {
    box-shadow: 0 2px 4px 0 rgba(255, 107, 53, 0.3);
    transition: transform 0.2s ease-in-out;
}

.divide-y.divide-gray-200 > div:hover .inline-flex.items-center.justify-center.w-6.h-6.bg-seait-orange {
    transform: scale(1.1);
}

/* Question type badge enhancement */
.inline-flex.items-center.px-2\.5.py-1.rounded-full.text-xs.font-medium {
    transition: all 0.2s ease-in-out;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
}

.inline-flex.items-center.px-2\.5.py-1.rounded-full.text-xs.font-medium:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px 0 rgba(0, 0, 0, 0.1);
}

/* Points badge enhancement */
.inline-flex.items-center.px-2.py-1.bg-yellow-100 {
    transition: all 0.2s ease-in-out;
    box-shadow: 0 1px 2px 0 rgba(245, 158, 11, 0.2);
}

.inline-flex.items-center.px-2.py-1.bg-yellow-100:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px 0 rgba(245, 158, 11, 0.3);
}

/* Question text container enhancement */
.text-sm.text-gray-900.break-words.leading-relaxed.bg-gray-50.p-3.rounded-md.border-l-4.border-seait-orange {
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
    transition: all 0.2s ease-in-out;
}

.divide-y.divide-gray-200 > div:hover .text-sm.text-gray-900.break-words.leading-relaxed.bg-gray-50.p-3.rounded-md.border-l-4.border-seait-orange {
    box-shadow: 0 2px 6px 0 rgba(0, 0, 0, 0.15);
    border-left-width: 6px;
}

/* Answer option enhancement */
.flex.items-start.gap-3.p-2.rounded-md {
    transition: all 0.2s ease-in-out;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
}

.flex.items-start.gap-3.p-2.rounded-md:hover {
    transform: translateX(2px);
    box-shadow: 0 2px 4px 0 rgba(0, 0, 0, 0.1);
}

/* Correct answer option enhancement */
.flex.items-start.gap-3.p-2.rounded-md.bg-green-50.border.border-green-200 {
    box-shadow: 0 1px 3px 0 rgba(34, 197, 94, 0.2);
}

.flex.items-start.gap-3.p-2.rounded-md.bg-green-50.border.border-green-200:hover {
    box-shadow: 0 2px 6px 0 rgba(34, 197, 94, 0.3);
}

/* Answer option letter enhancement */
.flex.items-center.justify-center.w-6.h-6.rounded-full.text-xs.font-bold.flex-shrink-0 {
    transition: all 0.2s ease-in-out;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.1);
}

.flex.items-start.gap-3.p-2.rounded-md:hover .flex.items-center.justify-center.w-6.h-6.rounded-full.text-xs.font-bold.flex-shrink-0 {
    transform: scale(1.1);
}

/* Correct answer badge enhancement */
.inline-flex.items-center.px-2.py-1.text-xs.font-medium.text-green-800.bg-green-100.rounded-full.flex-shrink-0 {
    animation: pulse 2s infinite;
    box-shadow: 0 1px 3px 0 rgba(34, 197, 94, 0.3);
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.8; }
}

/* Fill in blank answer enhancement */
.flex.items-center.gap-3.p-3.bg-blue-50.border.border-blue-200.rounded-md {
    box-shadow: 0 1px 3px 0 rgba(59, 130, 246, 0.2);
    transition: all 0.2s ease-in-out;
}

.flex.items-center.gap-3.p-3.bg-blue-50.border.border-blue-200.rounded-md:hover {
    box-shadow: 0 2px 6px 0 rgba(59, 130, 246, 0.3);
    transform: translateY(-1px);
}

/* Action button enhancement */
.inline-flex.items-center.px-3.py-2.text-sm.font-medium {
    transition: all 0.2s ease-in-out;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
}

.inline-flex.items-center.px-3.py-2.text-sm.font-medium:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px 0 rgba(0, 0, 0, 0.1);
}

/* Empty state enhancement */
.p-6.sm\:p-8.text-center {
    background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
    border-radius: 0.5rem;
    margin: 1rem;
}

.p-6.sm\:p-8.text-center .fas.fa-question-circle {
    background: linear-gradient(135deg, #ff6b35 0%, #ff8c42 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

/* Statistics card enhancement */
.bg-white.rounded-lg.shadow-md {
    transition: all 0.2s ease-in-out;
}

.bg-white.rounded-lg.shadow-md:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

/* Status badge enhancement */
.inline-flex.items-center.px-3.py-1.rounded-full.text-sm.font-medium {
    transition: all 0.2s ease-in-out;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
}

.inline-flex.items-center.px-3.py-1.rounded-full.text-sm.font-medium:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px 0 rgba(0, 0, 0, 0.1);
}

/* Responsive improvements */
@media (max-width: 640px) {
    .flex.flex-col.sm\:flex-row.sm\:items-start.sm\:justify-between {
        gap: 1rem;
    }
    
    .flex.gap-2.sm\:gap-3.flex-shrink-0 {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .inline-flex.items-center.px-3.py-2.text-sm.font-medium {
        width: 100%;
        justify-content: center;
    }
}

/* Loading state for buttons */
.inline-flex.items-center.px-3.py-2.text-sm.font-medium:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none !important;
}

/* Focus states for accessibility */
.inline-flex.items-center.px-3.py-2.text-sm.font-medium:focus {
    outline: 2px solid #ff6b35;
    outline-offset: 2px;
}

/* Print styles */
@media print {
    .flex.gap-2.sm\:gap-3.flex-shrink-0,
    .inline-flex.items-center.px-3.py-2.text-sm.font-medium {
        display: none !important;
    }
    
    .divide-y.divide-gray-200 > div {
        break-inside: avoid;
        page-break-inside: avoid;
    }
}

/* Modal Enhancement Styles */
#addQuestionModal {
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
}

#addQuestionModal .relative.top-4.sm\:top-10.mx-auto {
    animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-20px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

/* Form section enhancement */
.bg-gray-50.p-3.sm\:p-4.rounded-lg {
    background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
    border: 1px solid #e5e7eb;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
}

.bg-gray-50.p-4.sm\:p-6.rounded-lg.border.border-gray-200 {
    background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
    box-shadow: 0 2px 4px 0 rgba(0, 0, 0, 0.1);
}

/* Question content section enhancement */
.bg-white.p-3.sm\:p-4.rounded-lg.border.border-gray-200 {
    transition: all 0.2s ease-in-out;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
}

.bg-white.p-3.sm\:p-4.rounded-lg.border.border-gray-200:hover {
    box-shadow: 0 2px 4px 0 rgba(0, 0, 0, 0.1);
    transform: translateY(-1px);
}

/* Icon container enhancement */
.w-6.h-6.sm\:w-8.sm\:h-8.bg-seait-orange.rounded-full {
    box-shadow: 0 2px 4px 0 rgba(255, 107, 53, 0.3);
    animation: iconPulse 2s infinite;
}

@keyframes iconPulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

/* Input field enhancement */
.w-full.px-3.py-2.border.border-gray-300.rounded-md.focus\:outline-none.focus\:ring-2.focus\:ring-seait-orange {
    transition: all 0.2s ease-in-out;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
}

.w-full.px-3.py-2.border.border-gray-300.rounded-md.focus\:outline-none.focus\:ring-2.focus\:ring-seait-orange:focus {
    box-shadow: 0 0 0 3px rgba(255, 107, 53, 0.1), 0 2px 4px 0 rgba(0, 0, 0, 0.1);
    transform: translateY(-1px);
}

/* Select field enhancement */
select.w-full.px-3.py-2.border.border-gray-300.rounded-md.focus\:outline-none.focus\:ring-2.focus\:ring-seait-orange {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 0.5rem center;
    background-repeat: no-repeat;
    background-size: 1.5em 1.5em;
    padding-right: 2.5rem;
}

/* Rich text editor indicator enhancement */
.text-xs.text-gray-500.bg-gray-100.px-2.py-1.rounded {
    background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
    border: 1px solid #d1d5db;
    font-weight: 600;
    letter-spacing: 0.025em;
}

/* Preview button enhancement */
button[onclick="toggleQuestionPreview()"] {
    background: linear-gradient(135deg, #ff6b35 0%, #ff8c42 100%);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 0.375rem;
    font-weight: 500;
    transition: all 0.2s ease-in-out;
    box-shadow: 0 1px 2px 0 rgba(255, 107, 53, 0.3);
}

button[onclick="toggleQuestionPreview()"]:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px 0 rgba(255, 107, 53, 0.4);
}

/* Settings icon enhancement */
.flex.items-center.mb-2.sm\:mb-3 .fas.fa-cog {
    animation: spin 3s linear infinite;
    animation-play-state: paused;
    transition: all 0.2s ease-in-out;
}

.flex.items-center.mb-2.sm\:mb-3:hover .fas.fa-cog {
    animation-play-state: running;
    color: #ff6b35;
}

/* Points input star enhancement */
.fas.fa-star.text-yellow-500 {
    filter: drop-shadow(0 1px 2px rgba(245, 158, 11, 0.3));
    animation: starTwinkle 2s infinite;
}

@keyframes starTwinkle {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.8; transform: scale(1.1); }
}

/* Order input icon enhancement */
.fas.fa-sort-numeric-up.text-gray-400 {
    opacity: 0.7;
    transition: all 0.2s ease-in-out;
}

.relative:hover .fas.fa-sort-numeric-up.text-gray-400 {
    opacity: 1;
    color: #ff6b35;
}

/* Answer options enhancement */
.space-y-3 > div {
    transition: all 0.2s ease-in-out;
    border-radius: 0.5rem;
}

.space-y-3 > div:hover {
    transform: translateX(4px);
    box-shadow: 0 2px 4px 0 rgba(0, 0, 0, 0.1);
}

/* Radio button enhancement */
input[type="radio"] {
    transition: all 0.2s ease-in-out;
}

input[type="radio"]:checked {
    transform: scale(1.1);
}

/* Submit button enhancement */
#submitQuestionBtn {
    background: linear-gradient(135deg, #ff6b35 0%, #ff8c42 100%);
    box-shadow: 0 2px 4px 0 rgba(255, 107, 53, 0.3);
    transition: all 0.2s ease-in-out;
}

#submitQuestionBtn:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px 0 rgba(255, 107, 53, 0.4);
}

#submitQuestionBtn:disabled {
    background: linear-gradient(135deg, #9ca3af 0%, #6b7280 100%);
    box-shadow: none;
    transform: none;
}

/* Cancel button enhancement */
button[onclick="closeAddQuestionModal()"] {
    background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
    box-shadow: 0 2px 4px 0 rgba(107, 114, 128, 0.3);
    transition: all 0.2s ease-in-out;
}

button[onclick="closeAddQuestionModal()"]:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px 0 rgba(107, 114, 128, 0.4);
}

/* Modal footer enhancement */
.flex.flex-col.sm\:flex-row.justify-end.gap-2.sm\:gap-3.pt-4.border-t.border-gray-200 {
    background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
    margin: 1rem -1.5rem -1.5rem -1.5rem;
    padding: 1rem 1.5rem 1.5rem 1.5rem;
    border-top: 2px solid #e5e7eb;
}

/* Loading state enhancement */
.loading {
    position: relative;
    overflow: hidden;
}

.loading::after {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
    animation: loading 1.5s infinite;
}

@keyframes loading {
    0% { left: -100%; }
    100% { left: 100%; }
}

/* Accessibility improvements */
@media (prefers-reduced-motion: reduce) {
    * {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}

/* High contrast mode support */
@media (prefers-contrast: high) {
    .bg-gray-50 {
        background-color: #ffffff !important;
        border: 2px solid #000000 !important;
    }
    
    .text-gray-600 {
        color: #000000 !important;
    }
}

/* Questions Header Enhancement */
.py-4.border-b.border-gray-200 {
    background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
}

.py-4.border-b.border-gray-200 .text-center h2 {
    color: #1f2937;
    font-weight: 600;
    letter-spacing: -0.025em;
    position: relative;
    display: inline-block;
}

.py-4.border-b.border-gray-200 .text-center h2::after {
    content: '';
    position: absolute;
    bottom: -8px;
    left: 50%;
    transform: translateX(-50%);
    width: 40px;
    height: 3px;
    background: linear-gradient(135deg, #ff6b35 0%, #ff8c42 100%);
    border-radius: 2px;
}

/* Ensure proper text wrapping */
.truncate {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Prevent container overflow */
.min-w-0 {
    min-width: 0;
}

.flex-shrink-0 {
    flex-shrink: 0;
}
</style>