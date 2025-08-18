<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has teacher role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

$page_title = 'Create New Quiz';
$message = '';
$message_type = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lesson_id = !empty($_POST['lesson_id']) ? (int)$_POST['lesson_id'] : null;
    $title = sanitize_input($_POST['title']);
    $description = sanitize_input($_POST['description']);
    $quiz_type = sanitize_input($_POST['quiz_type']);
    $time_limit = !empty($_POST['time_limit']) ? (int)$_POST['time_limit'] : null;
    $passing_score = (int)$_POST['passing_score'];
    $max_attempts = (int)$_POST['max_attempts'];
    $status = sanitize_input($_POST['status']);
    $selected_classes = isset($_POST['selected_classes']) ? $_POST['selected_classes'] : [];

    // Validate quiz type and related fields
    if ($quiz_type === 'lesson_specific' && !$lesson_id) {
        $message = "Please select a lesson for lesson-specific quiz.";
        $message_type = "error";
    } elseif (empty($selected_classes)) {
        $message = "Please select at least one class to assign this quiz to.";
        $message_type = "error";
    } else {
        // Start transaction
        mysqli_begin_transaction($conn);

        try {
            // Insert quiz (class_id will be null for multiple assignments)
            $quiz_query = "INSERT INTO quizzes (teacher_id, class_id, lesson_id, title, description, quiz_type, time_limit, passing_score, max_attempts, status)
                          VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?)";
            $quiz_stmt = mysqli_prepare($conn, $quiz_query);
            mysqli_stmt_bind_param($quiz_stmt, "iisssiiis", $_SESSION['user_id'], $lesson_id, $title, $description, $quiz_type, $time_limit, $passing_score, $max_attempts, $status);

            if (mysqli_stmt_execute($quiz_stmt)) {
                $quiz_id = mysqli_insert_id($conn);

                // Insert class assignments
                foreach ($selected_classes as $class_id) {
                    $assignment_query = "INSERT INTO quiz_class_assignments (quiz_id, class_id) VALUES (?, ?)";
                    $assignment_stmt = mysqli_prepare($conn, $assignment_query);
                    mysqli_stmt_bind_param($assignment_stmt, "ii", $quiz_id, $class_id);
                    mysqli_stmt_execute($assignment_stmt);
                }

                mysqli_commit($conn);
                $success_message = "Quiz created successfully and assigned to " . count($selected_classes) . " class(es)!";
                header('Location: quizzes.php?message=' . urlencode($success_message) . '&type=success');
                exit();
            } else {
                throw new Exception("Error creating quiz: " . mysqli_error($conn));
            }
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $message = $e->getMessage();
            $message_type = "error";
        }
    }
}

// Get lessons for form with all their assigned classes
$lessons_query = "SELECT DISTINCT l.id, l.title, l.description,
                  GROUP_CONCAT(CONCAT(tc.id, ':', cc.subject_code, ' - ', cc.subject_title, ' (', tc.section, ')') SEPARATOR '|') as class_info
                  FROM lessons l
                  JOIN lesson_class_assignments lca ON l.id = lca.lesson_id
                  JOIN teacher_classes tc ON lca.class_id = tc.id
                  JOIN course_curriculum cc ON tc.subject_id = cc.id
                  WHERE l.teacher_id = ? AND l.status = 'published'
                  GROUP BY l.id
                  ORDER BY l.title";
$lessons_stmt = mysqli_prepare($conn, $lessons_query);
mysqli_stmt_bind_param($lessons_stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($lessons_stmt);
$lessons_result = mysqli_stmt_get_result($lessons_stmt);

// Get all classes for general quiz type
$all_classes_query = "SELECT tc.id, tc.section, cc.subject_title, cc.subject_code
                      FROM teacher_classes tc
                      JOIN course_curriculum cc ON tc.subject_id = cc.id
                      WHERE tc.teacher_id = ?
                      ORDER BY cc.subject_title, tc.section";
$all_classes_stmt = mysqli_prepare($conn, $all_classes_query);
mysqli_stmt_bind_param($all_classes_stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($all_classes_stmt);
$all_classes_result = mysqli_stmt_get_result($all_classes_stmt);

// Include the shared header
$sidebar_context = 'main';
include 'includes/unified-header.php';
?>

<div class="mb-6 sm:mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Create New Quiz</h1>
            <p class="text-sm sm:text-base text-gray-600">Create a new quiz and assign it to multiple classes</p>
        </div>
        <a href="quizzes.php" class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition">
            <i class="fas fa-arrow-left mr-2"></i>Back to Quizzes
        </a>
    </div>
</div>

<?php if ($message): ?>
<div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800'; ?>">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<div class="bg-white rounded-lg shadow-md">
    <div class="px-6 py-4 border-b border-gray-200">
        <h2 class="text-lg font-medium text-gray-900">Quiz Information</h2>
    </div>

    <form method="POST" class="p-6 space-y-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Quiz Type *</label>
                    <select name="quiz_type" id="quizType" onchange="toggleQuizTypeFields()" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        <option value="">Select Quiz Type</option>
                        <option value="general" <?php echo (isset($_POST['quiz_type']) && $_POST['quiz_type'] === 'general') ? 'selected' : ''; ?>>General Quiz</option>
                        <option value="lesson_specific" <?php echo (isset($_POST['quiz_type']) && $_POST['quiz_type'] === 'lesson_specific') ? 'selected' : ''; ?>>Lesson Specific Quiz</option>
                    </select>
                </div>

                <div id="lessonField" class="hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Lesson *</label>
                    <select name="lesson_id" id="lessonSelect" onchange="updateClassOptions()"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        <option value="">Select Lesson</option>
                        <?php while ($lesson = mysqli_fetch_assoc($lessons_result)): ?>
                        <option value="<?php echo $lesson['id']; ?>"
                                data-class-info="<?php echo htmlspecialchars($lesson['class_info']); ?>"
                                <?php echo (isset($_POST['lesson_id']) && $_POST['lesson_id'] == $lesson['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($lesson['title']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Title *</label>
                    <input type="text" name="title" required
                           value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <textarea name="description" rows="3"
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"
                              placeholder="Brief description of the quiz..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                </div>
            </div>

            <!-- Sidebar Settings -->
            <div class="space-y-6">
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h3 class="text-sm font-medium text-gray-900 mb-4">Quiz Settings</h3>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Time Limit (minutes)</label>
                            <input type="number" name="time_limit" min="1"
                                   value="<?php echo isset($_POST['time_limit']) ? (int)$_POST['time_limit'] : ''; ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"
                                   placeholder="No limit">
                            <p class="text-xs text-gray-500 mt-1">Leave empty for no time limit</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Passing Score (%)</label>
                            <input type="number" name="passing_score" value="<?php echo isset($_POST['passing_score']) ? (int)$_POST['passing_score'] : 70; ?>"
                                   min="1" max="100"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Max Attempts</label>
                            <input type="number" name="max_attempts" value="<?php echo isset($_POST['max_attempts']) ? (int)$_POST['max_attempts'] : 1; ?>"
                                   min="1"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                                <option value="draft" <?php echo (isset($_POST['status']) && $_POST['status'] === 'draft') ? 'selected' : ''; ?>>Draft</option>
                                <option value="published" <?php echo (isset($_POST['status']) && $_POST['status'] === 'published') ? 'selected' : ''; ?>>Published</option>
                                <option value="archived" <?php echo (isset($_POST['status']) && $_POST['status'] === 'archived') ? 'selected' : ''; ?>>Archived</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 p-4 rounded-lg">
                    <h3 class="text-sm font-medium text-gray-900 mb-4">Class Assignment *</h3>
                    <div id="classOptions" class="max-h-60 overflow-y-auto space-y-2">
                        <p class="text-sm text-gray-500">Select a quiz type first to see available classes.</p>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">Select at least one class to assign this quiz to</p>
                </div>

                <!-- Hidden data for JavaScript -->
                <div id="classData" style="display: none;">
                    <?php
                    mysqli_data_seek($all_classes_result, 0);
                    while ($class = mysqli_fetch_assoc($all_classes_result)):
                    ?>
                    <div data-class-id="<?php echo $class['id']; ?>" data-class-name="<?php echo htmlspecialchars($class['subject_code'] . ' - ' . $class['subject_title'] . ' (' . $class['section'] . ')'); ?>"></div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>

        <div class="flex justify-end space-x-3 pt-6 border-t border-gray-200">
            <a href="quizzes.php" class="bg-gray-500 text-white px-6 py-2 rounded-md hover:bg-gray-600 transition">
                Cancel
            </a>
            <button type="submit" class="bg-seait-orange text-white px-6 py-2 rounded-md hover:bg-orange-600 transition">
                <i class="fas fa-save mr-2"></i>Create Quiz
            </button>
        </div>
    </form>
</div>

<script>
function toggleQuizTypeFields() {
    const quizType = document.getElementById('quizType').value;
    const lessonField = document.getElementById('lessonField');
    const classOptions = document.getElementById('classOptions');

    // Hide all fields first
    lessonField.classList.add('hidden');
    classOptions.innerHTML = '<p class="text-sm text-gray-500">Select a quiz type first to see available classes.</p>';

    // Show appropriate field based on selection
    if (quizType === 'general') {
        showAllClasses();
    } else if (quizType === 'lesson_specific') {
        lessonField.classList.remove('hidden');
    }
}

function showAllClasses() {
    const classOptionsDiv = document.getElementById('classOptions');
    const classDataDiv = document.getElementById('classData');
    const classDivs = classDataDiv.querySelectorAll('[data-class-id]');

    classOptionsDiv.innerHTML = '';

    classDivs.forEach(classDiv => {
        const classId = classDiv.getAttribute('data-class-id');
        const className = classDiv.getAttribute('data-class-name');

        const label = document.createElement('label');
        label.className = 'flex items-center';
        label.innerHTML = `
            <input type="checkbox" name="selected_classes[]" value="${classId}" class="mr-2 rounded border-gray-300 text-seait-orange focus:ring-seait-orange">
            <span class="text-sm text-gray-700">${className}</span>
        `;
        classOptionsDiv.appendChild(label);
    });
}

function updateClassOptions() {
    const lessonSelect = document.getElementById('lessonSelect');
    const classOptionsDiv = document.getElementById('classOptions');
    const selectedOption = lessonSelect.options[lessonSelect.selectedIndex];

    if (lessonSelect.value && selectedOption.dataset.classInfo) {
        const classInfo = selectedOption.dataset.classInfo;
        const classes = classInfo.split('|');

        classOptionsDiv.innerHTML = '';

        classes.forEach(classData => {
            const [classId, className] = classData.split(':', 2);

            const label = document.createElement('label');
            label.className = 'flex items-center';
            label.innerHTML = `
                <input type="checkbox" name="selected_classes[]" value="${classId}" class="mr-2 rounded border-gray-300 text-seait-orange focus:ring-seait-orange">
                <span class="text-sm text-gray-700">${className}</span>
            `;
            classOptionsDiv.appendChild(label);
        });
    } else {
        classOptionsDiv.innerHTML = '<p class="text-sm text-gray-500">Select a lesson first to see available classes.</p>';
    }
}

// Initialize form state on page load
document.addEventListener('DOMContentLoaded', function() {
    const quizType = document.getElementById('quizType').value;
    if (quizType) {
        toggleQuizTypeFields();

        // If lesson-specific and lesson is selected, update class options
        if (quizType === 'lesson_specific') {
            const lessonSelect = document.getElementById('lessonSelect');
            if (lessonSelect.value) {
                updateClassOptions();
            }
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?>