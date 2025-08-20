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
                f.id as teacher_id, f.first_name as teacher_first_name, f.last_name as teacher_last_name,
                f.email as teacher_email
                FROM class_enrollments ce
                JOIN teacher_classes tc ON ce.class_id = tc.id
                JOIN course_curriculum cc ON tc.subject_id = cc.id
                JOIN faculty f ON tc.teacher_id = f.id
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
$page_title = $class_data['subject_title'] . ' - Class Dashboard';

// Get class statistics
$stats_query = "SELECT
                COUNT(DISTINCT ce.student_id) as total_students,
                COUNT(DISTINCT es.id) as total_evaluations,
                COUNT(DISTINCT CASE WHEN es.status = 'completed' THEN es.id END) as completed_evaluations
                FROM class_enrollments ce
                LEFT JOIN evaluation_sessions es ON es.evaluator_id = ? AND es.evaluatee_id = ?
                WHERE ce.class_id = ? AND ce.status = 'enrolled'";
$stats_stmt = mysqli_prepare($conn, $stats_query);
mysqli_stmt_bind_param($stats_stmt, "iii", $_SESSION['user_id'], $class_data['teacher_id'], $class_id);
mysqli_stmt_execute($stats_stmt);
$stats_result = mysqli_stmt_get_result($stats_stmt);
$class_stats = mysqli_fetch_assoc($stats_result);

// Get recent activities (placeholder for future features)
$recent_activities = [];

// Get evaluation opportunities
$evaluation_opportunities = [];
$eval_categories_query = "SELECT id, name, description FROM main_evaluation_categories
                         WHERE evaluation_type = 'student_to_teacher' AND status = 'active'
                         ORDER BY name";
$eval_categories_result = mysqli_query($conn, $eval_categories_query);
while ($category = mysqli_fetch_assoc($eval_categories_result)) {
    // Check if evaluation already exists
    $existing_eval_query = "SELECT id, status FROM evaluation_sessions
                           WHERE evaluator_id = ? AND evaluatee_id = ? AND main_category_id = ?";
    $existing_eval_stmt = mysqli_prepare($conn, $existing_eval_query);
    mysqli_stmt_bind_param($existing_eval_stmt, "iii", $_SESSION['user_id'], $class_data['teacher_id'], $category['id']);
    mysqli_stmt_execute($existing_eval_stmt);
    $existing_eval_result = mysqli_stmt_get_result($existing_eval_stmt);
    $existing_eval = mysqli_fetch_assoc($existing_eval_result);

    $evaluation_opportunities[] = [
        'category' => $category,
        'existing_eval' => $existing_eval,
        'can_evaluate' => !$existing_eval || $existing_eval['status'] === 'draft'
    ];
}

// Include the shared LMS header
include 'includes/lms_header.php';
?>

<div class="mb-6 sm:mb-8">
    <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Class Dashboard</h1>
    <p class="text-sm sm:text-base text-gray-600">Welcome to <?php echo htmlspecialchars($class_data['subject_title']); ?> - Section <?php echo htmlspecialchars($class_data['section']); ?></p>
</div>

<!-- Class Overview Cards -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-6 mb-6 sm:mb-8">
    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-4 sm:p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-users text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Class Size</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($class_stats['total_students'] ?? 0); ?> students</dd>
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
                        <i class="fas fa-clipboard-check text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Evaluations</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($class_stats['total_evaluations'] ?? 0); ?> total</dd>
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
                        <i class="fas fa-graduation-cap text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Units</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($class_data['units']); ?> units</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-6 sm:mb-8">
    <a href="lms_materials.php?class_id=<?php echo $class_id; ?>" class="flex items-center p-4 sm:p-6 bg-blue-50 rounded-lg hover:bg-blue-100 transition">
        <div class="flex-shrink-0">
            <div class="w-10 h-10 bg-blue-500 rounded-lg flex items-center justify-center">
                <i class="fas fa-book text-white"></i>
            </div>
        </div>
        <div class="ml-4">
            <p class="text-sm sm:text-base font-medium text-gray-900">Learning Materials</p>
            <p class="text-xs sm:text-sm text-gray-500">Access course content</p>
        </div>
    </a>

    <a href="class_syllabus.php?class_id=<?php echo $class_id; ?>" class="flex items-center p-4 sm:p-6 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition">
        <div class="flex-shrink-0">
            <div class="w-10 h-10 bg-indigo-500 rounded-lg flex items-center justify-center">
                <i class="fas fa-file-alt text-white"></i>
            </div>
        </div>
        <div class="ml-4">
            <p class="text-sm sm:text-base font-medium text-gray-900">Course Syllabus</p>
            <p class="text-xs sm:text-sm text-gray-500">View course information</p>
        </div>
    </a>

    <a href="lms_assignments.php?class_id=<?php echo $class_id; ?>" class="flex items-center p-4 sm:p-6 bg-green-50 rounded-lg hover:bg-green-100 transition">
        <div class="flex-shrink-0">
            <div class="w-10 h-10 bg-green-500 rounded-lg flex items-center justify-center">
                <i class="fas fa-tasks text-white"></i>
            </div>
        </div>
        <div class="ml-4">
            <p class="text-sm sm:text-base font-medium text-gray-900">Assignments</p>
            <p class="text-xs sm:text-sm text-gray-500">View and submit work</p>
        </div>
    </a>

    <a href="lms_discussions.php?class_id=<?php echo $class_id; ?>" class="flex items-center p-4 sm:p-6 bg-purple-50 rounded-lg hover:bg-purple-100 transition">
        <div class="flex-shrink-0">
            <div class="w-10 h-10 bg-purple-500 rounded-lg flex items-center justify-center">
                <i class="fas fa-comments text-white"></i>
            </div>
        </div>
        <div class="ml-4">
            <p class="text-sm sm:text-base font-medium text-gray-900">Discussions</p>
            <p class="text-xs sm:text-sm text-gray-500">Join class discussions</p>
        </div>
    </a>

    <a href="evaluate-teacher.php?class_id=<?php echo $class_id; ?>" class="flex items-center p-4 sm:p-6 bg-orange-50 rounded-lg hover:bg-orange-100 transition">
        <div class="flex-shrink-0">
            <div class="w-10 h-10 bg-seait-orange rounded-lg flex items-center justify-center">
                <i class="fas fa-clipboard-check text-white"></i>
            </div>
        </div>
        <div class="ml-4">
            <p class="text-sm sm:text-base font-medium text-gray-900">Evaluate Teacher</p>
            <p class="text-xs sm:text-sm text-gray-500">Provide feedback</p>
        </div>
    </a>
</div>

<!-- Teacher Information -->
<div class="bg-white rounded-lg shadow-md overflow-hidden mb-6 sm:mb-8">
    <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-medium text-gray-900">Teacher Information</h3>
    </div>
    <div class="p-6">
        <div class="flex items-center">
            <div class="h-16 w-16 rounded-full bg-seait-orange flex items-center justify-center mr-6">
                <span class="text-white font-bold text-xl"><?php echo strtoupper(substr($class_data['teacher_first_name'], 0, 1) . substr($class_data['teacher_last_name'], 0, 1)); ?></span>
            </div>
            <div class="flex-1">
                <h4 class="text-xl font-semibold text-gray-900"><?php echo htmlspecialchars($class_data['teacher_first_name'] . ' ' . $class_data['teacher_last_name']); ?></h4>
                <p class="text-gray-600"><?php echo htmlspecialchars($class_data['teacher_email']); ?></p>
                <p class="text-sm text-gray-500 mt-1">Course Instructor</p>
            </div>
            <div class="text-right">
                <span class="px-3 py-1 text-sm rounded-full bg-green-100 text-green-800">
                    Active
                </span>
            </div>
        </div>
    </div>
</div>

<!-- Evaluation Opportunities -->
<?php if (!empty($evaluation_opportunities)): ?>
<div class="bg-white rounded-lg shadow-md overflow-hidden mb-6 sm:mb-8">
    <div class="px-6 py-4 border-b border-gray-200">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-medium text-gray-900">Evaluation Opportunities</h3>
            <a href="evaluate-teacher.php?class_id=<?php echo $class_id; ?>" class="text-seait-orange hover:text-orange-600 text-sm font-medium">
                View all evaluations <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
    </div>
    <div class="divide-y divide-gray-200">
        <?php foreach ($evaluation_opportunities as $opportunity): ?>
        <div class="p-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="h-10 w-10 rounded-full bg-blue-500 flex items-center justify-center mr-4">
                        <i class="fas fa-clipboard-check text-white"></i>
                    </div>
                    <div>
                        <h4 class="text-sm sm:text-base font-medium text-gray-900"><?php echo htmlspecialchars($opportunity['category']['name']); ?></h4>
                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($opportunity['category']['description']); ?></p>
                    </div>
                </div>
                <div class="text-right">
                    <?php if ($opportunity['existing_eval']): ?>
                        <?php if ($opportunity['existing_eval']['status'] === 'completed'): ?>
                            <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">
                                Completed
                            </span>
                        <?php else: ?>
                            <a href="conduct-evaluation.php?session_id=<?php echo $opportunity['existing_eval']['id']; ?>"
                               class="inline-block bg-yellow-500 text-white px-3 py-1 rounded text-sm hover:bg-yellow-600 transition">
                                Continue
                            </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <a href="evaluate-teacher.php?class_id=<?php echo $class_id; ?>&category_id=<?php echo $opportunity['category']['id']; ?>"
                           class="inline-block bg-seait-orange text-white px-3 py-1 rounded text-sm hover:bg-orange-600 transition">
                            Start
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Recent Activities (Placeholder) -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-medium text-gray-900">Recent Activities</h3>
    </div>
    <div class="p-6">
        <div class="text-center py-8">
            <i class="fas fa-clock text-gray-300 text-4xl mb-4"></i>
            <p class="text-gray-500">No recent activities to display.</p>
            <p class="text-sm text-gray-400 mt-2">Check back later for updates on class activities.</p>
        </div>
    </div>
</div>

<?php
// Include the shared LMS footer
include 'includes/lms_footer.php';
?>