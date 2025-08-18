<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has student role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

// Set page title
$page_title = 'Evaluate Teacher';

$message = '';
$message_type = '';

// Get class_id from URL if provided
$selected_class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : null;

// Get student_id for queries
$student_id = get_student_id($conn, $_SESSION['email']);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class_id = (int)$_POST['class_id'];
    $category_id = (int)$_POST['category_id'];

    // Verify student is enrolled in this class
    $student_id = get_student_id($conn, $_SESSION['email']);

    $enrollment_check = "SELECT ce.id FROM class_enrollments ce
                        JOIN teacher_classes tc ON ce.class_id = tc.id
                        WHERE ce.student_id = ? AND ce.class_id = ? AND ce.status = 'enrolled'";
    $enrollment_stmt = mysqli_prepare($conn, $enrollment_check);
    mysqli_stmt_bind_param($enrollment_stmt, "ii", $student_id, $class_id);
    mysqli_stmt_execute($enrollment_stmt);
    $enrollment_result = mysqli_stmt_get_result($enrollment_stmt);

    if (mysqli_num_rows($enrollment_result) == 0) {
        $message = "You are not enrolled in this class!";
        $message_type = "error";
    } else {
        // Get teacher ID from the class
        $teacher_query = "SELECT teacher_id FROM teacher_classes WHERE id = ?";
        $teacher_stmt = mysqli_prepare($conn, $teacher_query);
        mysqli_stmt_bind_param($teacher_stmt, "i", $class_id);
        mysqli_stmt_execute($teacher_stmt);
        $teacher_result = mysqli_stmt_get_result($teacher_stmt);
        $teacher = mysqli_fetch_assoc($teacher_result);

        if ($teacher) {
            // Get evaluation category info to check schedule
            $category_query = "SELECT evaluation_type FROM main_evaluation_categories WHERE id = ?";
            $category_stmt = mysqli_prepare($conn, $category_query);
            mysqli_stmt_bind_param($category_stmt, "i", $category_id);
            mysqli_stmt_execute($category_stmt);
            $category_result = mysqli_stmt_get_result($category_stmt);
            $category = mysqli_fetch_assoc($category_result);

            if ($category) {
                // Check if there's an active evaluation schedule started by guidance officer
                $schedule_check = "SELECT es.*, s.name as semester_name
                                   FROM evaluation_schedules es
                                   JOIN semesters s ON es.semester_id = s.id
                                   WHERE es.evaluation_type = ?
                                   AND es.status = 'active'
                                   AND NOW() BETWEEN es.start_date AND es.end_date";
                $schedule_stmt = mysqli_prepare($conn, $schedule_check);
                mysqli_stmt_bind_param($schedule_stmt, "s", $category['evaluation_type']);
                mysqli_stmt_execute($schedule_stmt);
                $schedule_result = mysqli_stmt_get_result($schedule_stmt);
                $active_schedule = mysqli_fetch_assoc($schedule_result);

                if (!$active_schedule) {
                    $message = "No active evaluation period found for this category. Please wait for the guidance officer to start the evaluation period.";
                    $message_type = "error";
                } else {
                    // Check if evaluation already exists
                    $existing_eval = "SELECT id FROM evaluation_sessions
                                    WHERE evaluator_id = ? AND evaluatee_id = ? AND main_category_id = ?";
                    $existing_stmt = mysqli_prepare($conn, $existing_eval);
                    mysqli_stmt_bind_param($existing_stmt, "iii", $_SESSION['user_id'], $teacher['teacher_id'], $category_id);
                    mysqli_stmt_execute($existing_stmt);
                    $existing_result = mysqli_stmt_get_result($existing_stmt);

                    if (mysqli_num_rows($existing_result) > 0) {
                        $message = "You have already evaluated this teacher for this category!";
                        $message_type = "error";
                    } else {
                        // Create evaluation session only if there's an active schedule
                        $create_eval = "INSERT INTO evaluation_sessions (evaluator_id, evaluatee_id, main_category_id, status, evaluation_date)
                                      VALUES (?, ?, ?, 'draft', CURDATE())";
                        $create_stmt = mysqli_prepare($conn, $create_eval);
                        mysqli_stmt_bind_param($create_stmt, "iii", $_SESSION['user_id'], $teacher['teacher_id'], $category_id);

                        if (mysqli_stmt_execute($create_stmt)) {
                            $evaluation_id = mysqli_insert_id($conn);
                            // Redirect to conduct evaluation
                            header("Location: conduct-evaluation.php?session_id=" . $evaluation_id);
                            exit();
                        } else {
                            $message = "Error creating evaluation: " . mysqli_error($conn);
                            $message_type = "error";
                        }
                    }
                }
            } else {
                $message = "Evaluation category not found!";
                $message_type = "error";
            }
        } else {
            $message = "Teacher not found for this class!";
            $message_type = "error";
        }
    }
}

// Get student's enrolled classes where they can evaluate teachers
$enrolled_classes_query = "SELECT ce.class_id, tc.section, tc.join_code,
                          cc.subject_title, cc.subject_code, cc.units,
                          f.id as teacher_id, f.first_name as teacher_first_name, f.last_name as teacher_last_name
                          FROM class_enrollments ce
                          JOIN teacher_classes tc ON ce.class_id = tc.id
                          JOIN course_curriculum cc ON tc.subject_id = cc.id
                          JOIN faculty f ON tc.teacher_id = f.id
                          WHERE ce.student_id = ? AND ce.status = 'enrolled' AND tc.status = 'active' AND f.is_active = 1
                          ORDER BY cc.subject_title, tc.section";
$enrolled_classes_stmt = mysqli_prepare($conn, $enrolled_classes_query);
mysqli_stmt_bind_param($enrolled_classes_stmt, "i", $student_id);
mysqli_stmt_execute($enrolled_classes_stmt);
$enrolled_classes_result = mysqli_stmt_get_result($enrolled_classes_stmt);

// Get evaluation categories for student to teacher evaluations
$categories_query = "SELECT id, name, description FROM main_evaluation_categories
                    WHERE evaluation_type = 'student_to_teacher' AND status = 'active'
                    ORDER BY name";
$categories_result = mysqli_query($conn, $categories_query);

// Include the shared header
include 'includes/header.php';
?>

<div class="mb-6 sm:mb-8">
    <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Evaluate Teacher</h1>
    <p class="text-sm sm:text-base text-gray-600">Provide feedback for your teachers</p>
</div>

<!-- Message Display -->
<?php if ($message): ?>
<div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800'; ?>">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<!-- Evaluation Form -->
<div class="bg-white rounded-lg shadow-md overflow-hidden mb-6 sm:mb-8">
    <div class="px-6 py-4 border-b border-gray-200">
        <h2 class="text-lg font-medium text-gray-900">Select Class and Evaluation Category</h2>
    </div>

    <div class="p-6">
        <form method="POST" class="space-y-6">
            <!-- Class Selection -->
            <div>
                <label for="class_id" class="block text-sm font-medium text-gray-700 mb-2">Select Class <span class="text-red-500">*</span></label>
                <select name="class_id" id="class_id" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    <option value="">Choose a class...</option>
                    <?php while ($class = mysqli_fetch_assoc($enrolled_classes_result)): ?>
                    <option value="<?php echo $class['class_id']; ?>"
                            <?php echo $selected_class_id == $class['class_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($class['subject_title'] . ' - ' . $class['subject_code'] . ' (Section ' . $class['section'] . ') - ' . $class['teacher_first_name'] . ' ' . $class['teacher_last_name']); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
                <p class="text-xs text-gray-500 mt-1">Select the class for which you want to evaluate the teacher</p>
            </div>

            <!-- Evaluation Category Selection -->
            <div>
                <label for="category_id" class="block text-sm font-medium text-gray-700 mb-2">Evaluation Category <span class="text-red-500">*</span></label>
                <select name="category_id" id="category_id" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    <option value="">Choose evaluation category...</option>
                    <?php while ($category = mysqli_fetch_assoc($categories_result)): ?>
                    <option value="<?php echo $category['id']; ?>">
                        <?php echo htmlspecialchars($category['name']); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
                <p class="text-xs text-gray-500 mt-1">Select the type of evaluation you want to conduct</p>
            </div>

            <button type="submit" class="w-full bg-seait-orange text-white px-4 py-2 rounded-md hover:bg-orange-600 transition">
                <i class="fas fa-clipboard-check mr-2"></i>Start Evaluation
            </button>
        </form>
    </div>
</div>

<!-- Evaluation Guidelines -->
<div class="bg-blue-50 rounded-lg p-6 mb-6 sm:mb-8">
    <h3 class="text-lg font-medium text-blue-900 mb-4">
        <i class="fas fa-info-circle mr-2"></i>Evaluation Guidelines
    </h3>
    <div class="space-y-3 text-sm text-blue-800">
        <div class="flex items-start">
            <span class="bg-blue-200 text-blue-800 rounded-full w-6 h-6 flex items-center justify-center text-xs font-medium mr-3 mt-0.5">1</span>
            <p>Select the class and teacher you want to evaluate</p>
        </div>
        <div class="flex items-start">
            <span class="bg-blue-200 text-blue-800 rounded-full w-6 h-6 flex items-center justify-center text-xs font-medium mr-3 mt-0.5">2</span>
            <p>Choose the appropriate evaluation category</p>
        </div>
        <div class="flex items-start">
            <span class="bg-blue-200 text-blue-800 rounded-full w-6 h-6 flex items-center justify-center text-xs font-medium mr-3 mt-0.5">3</span>
            <p>Provide honest and constructive feedback</p>
        </div>
        <div class="flex items-start">
            <span class="bg-blue-200 text-blue-800 rounded-full w-6 h-6 flex items-center justify-center text-xs font-medium mr-3 mt-0.5">4</span>
            <p>Your evaluation will help improve teaching quality</p>
        </div>
    </div>
</div>

<!-- Recent Evaluations -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-medium text-gray-900">Your Recent Evaluations</h3>
            <a href="evaluations.php" class="text-seait-orange hover:text-orange-600 text-sm font-medium">
                View all evaluations <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
    </div>

    <?php
    // Get student's recent evaluations
    $recent_evaluations_query = "SELECT es.*, mec.name as category_name,
                                u.first_name as teacher_first_name, u.last_name as teacher_last_name
                                FROM evaluation_sessions es
                                JOIN main_evaluation_categories mec ON es.main_category_id = mec.id
                                JOIN users u ON es.evaluatee_id = u.id
                                WHERE es.evaluator_id = ? AND mec.evaluation_type = 'student_to_teacher'
                                ORDER BY es.created_at DESC
                                LIMIT 5";
    $recent_evaluations_stmt = mysqli_prepare($conn, $recent_evaluations_query);
    mysqli_stmt_bind_param($recent_evaluations_stmt, "i", $_SESSION['user_id']);
    mysqli_stmt_execute($recent_evaluations_stmt);
    $recent_evaluations_result = mysqli_stmt_get_result($recent_evaluations_stmt);
    ?>

    <?php if (mysqli_num_rows($recent_evaluations_result) == 0): ?>
        <div class="p-6 text-center">
            <i class="fas fa-clipboard-list text-gray-300 text-4xl mb-4"></i>
            <p class="text-gray-500">No evaluations completed yet.</p>
            <p class="text-sm text-gray-400 mt-2">Start by evaluating one of your teachers above.</p>
        </div>
    <?php else: ?>
        <div class="divide-y divide-gray-200">
            <?php while ($evaluation = mysqli_fetch_assoc($recent_evaluations_result)): ?>
            <div class="p-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="h-10 w-10 rounded-full bg-green-500 flex items-center justify-center mr-4">
                            <i class="fas fa-check text-white"></i>
                        </div>
                        <div>
                            <h4 class="text-sm sm:text-base font-medium text-gray-900"><?php echo htmlspecialchars($evaluation['category_name']); ?></h4>
                            <p class="text-sm text-gray-500">Teacher: <?php echo htmlspecialchars($evaluation['teacher_first_name'] . ' ' . $evaluation['teacher_last_name']); ?></p>
                            <p class="text-xs text-gray-400"><?php echo date('M d, Y', strtotime($evaluation['created_at'])); ?></p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="px-2 py-1 text-xs rounded-full <?php echo $evaluation['status'] === 'completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                            <?php echo ucfirst($evaluation['status']); ?>
                        </span>
                        <?php if ($evaluation['status'] === 'draft'): ?>
                        <div class="mt-2">
                            <a href="conduct-evaluation.php?session_id=<?php echo $evaluation['id']; ?>"
                               class="text-xs text-blue-600 hover:text-blue-800">
                                Continue Evaluation
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>
</div>

<script>
// Auto-submit form when both selections are made
document.addEventListener('DOMContentLoaded', function() {
    const classSelect = document.getElementById('class_id');
    const categorySelect = document.getElementById('category_id');
    const form = document.querySelector('form');

    function checkForm() {
        if (classSelect.value && categorySelect.value) {
            // Form is complete, but let user click submit button
        }
    }

    classSelect.addEventListener('change', checkForm);
    categorySelect.addEventListener('change', checkForm);
});
</script>

<?php
// Include the shared footer
include 'includes/footer.php';
?>