<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has student role
check_login();
if ($_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

// Set page title
$page_title = 'Dashboard';

// Get student_id for queries
$student_id = get_student_id($conn, $_SESSION['email']);

// Get student's enrolled classes
$enrolled_classes_query = "SELECT ce.*, tc.section, tc.join_code, tc.status as class_status,
                          cc.subject_title, cc.subject_code, cc.units,
                          u.first_name as teacher_first_name, u.last_name as teacher_last_name
                          FROM class_enrollments ce
                          JOIN teacher_classes tc ON ce.class_id = tc.id
                          JOIN course_curriculum cc ON tc.subject_id = cc.id
                          JOIN users u ON tc.teacher_id = u.id
                          WHERE ce.student_id = ? AND ce.status = 'enrolled'
                          ORDER BY ce.join_date DESC
                          LIMIT 5";
$enrolled_stmt = mysqli_prepare($conn, $enrolled_classes_query);
mysqli_stmt_bind_param($enrolled_stmt, "i", $student_id);
mysqli_stmt_execute($enrolled_stmt);
$enrolled_result = mysqli_stmt_get_result($enrolled_stmt);

// Get total enrolled classes count
$total_classes_query = "SELECT COUNT(*) as total FROM class_enrollments
                       WHERE student_id = ? AND status = 'enrolled'";
$total_classes_stmt = mysqli_prepare($conn, $total_classes_query);
mysqli_stmt_bind_param($total_classes_stmt, "i", $student_id);
mysqli_stmt_execute($total_classes_stmt);
$total_classes = mysqli_fetch_assoc(mysqli_stmt_get_result($total_classes_stmt))['total'];

// Get evaluation opportunities (classes where student can evaluate teacher)
$evaluation_opportunities_query = "SELECT ce.*, tc.section, tc.join_code,
                                  cc.subject_title, cc.subject_code,
                                  u.first_name as teacher_first_name, u.last_name as teacher_last_name
                                  FROM class_enrollments ce
                                  JOIN teacher_classes tc ON ce.class_id = tc.id
                                  JOIN course_curriculum cc ON tc.subject_id = cc.id
                                  JOIN users u ON tc.teacher_id = u.id
                                  WHERE ce.student_id = ? AND ce.status = 'enrolled'
                                  AND tc.status = 'active'
                                  ORDER BY ce.join_date DESC
                                  LIMIT 5";
$eval_opp_stmt = mysqli_prepare($conn, $evaluation_opportunities_query);
mysqli_stmt_bind_param($eval_opp_stmt, "i", $student_id);
mysqli_stmt_execute($eval_opp_stmt);
$eval_opp_result = mysqli_stmt_get_result($eval_opp_stmt);

// Get student's evaluation history
$evaluation_history_query = "SELECT es.*, mec.name as category_name,
                            u.first_name as teacher_first_name, u.last_name as teacher_last_name
                            FROM evaluation_sessions es
                            JOIN main_evaluation_categories mec ON es.main_category_id = mec.id
                            JOIN users u ON es.evaluatee_id = u.id
                            WHERE es.evaluator_id = ? AND mec.evaluation_type = 'student_to_teacher'
                            ORDER BY es.created_at DESC
                            LIMIT 5";
$eval_history_stmt = mysqli_prepare($conn, $evaluation_history_query);
mysqli_stmt_bind_param($eval_history_stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($eval_history_stmt);
$eval_history_result = mysqli_stmt_get_result($eval_history_stmt);

// Get statistics
$stats_query = "SELECT
                (SELECT COUNT(*) FROM class_enrollments WHERE student_id = ? AND status = 'enrolled') as total_classes,
                (SELECT COUNT(*) FROM evaluation_sessions es
                 JOIN main_evaluation_categories mec ON es.main_category_id = mec.id
                 WHERE es.evaluator_id = ? AND mec.evaluation_type = 'student_to_teacher' AND es.status = 'completed') as completed_evaluations,
                (SELECT COUNT(*) FROM evaluation_sessions es
                 JOIN main_evaluation_categories mec ON es.main_category_id = mec.id
                 WHERE es.evaluator_id = ? AND mec.evaluation_type = 'student_to_teacher' AND es.status = 'draft') as pending_evaluations";
$stats_stmt = mysqli_prepare($conn, $stats_query);
mysqli_stmt_bind_param($stats_stmt, "iii", $student_id, $_SESSION['user_id'], $_SESSION['user_id']);
mysqli_stmt_execute($stats_stmt);
$stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stats_stmt));

// Include the shared header
include 'includes/header.php';
?>

<div class="mb-6 sm:mb-8">
    <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Student Dashboard</h1>
    <p class="text-sm sm:text-base text-gray-600">Welcome back! Here's what's happening with your classes and evaluations.</p>
</div>

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
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Enrolled Classes</dt>
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
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Completed Evaluations</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['completed_evaluations'] ?? 0); ?></dd>
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
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Pending Evaluations</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['pending_evaluations'] ?? 0); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6 mb-6 sm:mb-8">
    <a href="join-class.php" class="flex items-center p-4 sm:p-6 bg-blue-50 rounded-lg hover:bg-blue-100 transition">
        <div class="flex-shrink-0">
            <div class="w-10 h-10 bg-blue-500 rounded-lg flex items-center justify-center">
                <i class="fas fa-plus-circle text-white"></i>
            </div>
        </div>
        <div class="ml-4">
            <p class="text-sm sm:text-base font-medium text-gray-900">Join New Class</p>
            <p class="text-xs sm:text-sm text-gray-500">Use join code to enroll</p>
        </div>
    </a>

    <a href="evaluate-teacher.php" class="flex items-center p-4 sm:p-6 bg-green-50 rounded-lg hover:bg-green-100 transition">
        <div class="flex-shrink-0">
            <div class="w-10 h-10 bg-green-500 rounded-lg flex items-center justify-center">
                <i class="fas fa-user-tie text-white"></i>
            </div>
        </div>
        <div class="ml-4">
            <p class="text-sm sm:text-base font-medium text-gray-900">Evaluate Teacher</p>
            <p class="text-xs sm:text-sm text-gray-500">Provide feedback</p>
        </div>
    </a>

    <a href="my-classes.php" class="flex items-center p-4 sm:p-6 bg-purple-50 rounded-lg hover:bg-purple-100 transition">
        <div class="flex-shrink-0">
            <div class="w-10 h-10 bg-purple-500 rounded-lg flex items-center justify-center">
                <i class="fas fa-chalkboard text-white"></i>
            </div>
        </div>
        <div class="ml-4">
            <p class="text-sm sm:text-base font-medium text-gray-900">View My Classes</p>
            <p class="text-xs sm:text-sm text-gray-500">See all enrolled classes</p>
        </div>
    </a>
</div>

<!-- Recent Enrolled Classes -->
<div class="bg-white rounded-lg shadow-md overflow-hidden mb-6 sm:mb-8">
    <div class="px-6 py-4 border-b border-gray-200">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-medium text-gray-900">Recent Enrolled Classes</h3>
            <a href="my-classes.php" class="text-seait-orange hover:text-orange-600 text-sm font-medium">
                View all classes <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
    </div>

    <?php if (mysqli_num_rows($enrolled_result) == 0): ?>
        <div class="p-6 text-center">
            <i class="fas fa-chalkboard text-gray-300 text-4xl mb-4"></i>
            <p class="text-gray-500">You haven't joined any classes yet.</p>
            <a href="join-class.php" class="mt-4 inline-block bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition">
                Join Your First Class
            </a>
        </div>
    <?php else: ?>
        <div class="divide-y divide-gray-200">
            <?php while ($class = mysqli_fetch_assoc($enrolled_result)): ?>
            <div class="p-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="h-10 w-10 rounded-full bg-seait-orange flex items-center justify-center mr-4">
                            <span class="text-white font-medium"><?php echo strtoupper(substr($class['teacher_first_name'], 0, 1) . substr($class['teacher_last_name'], 0, 1)); ?></span>
                        </div>
                        <div>
                            <h4 class="text-sm sm:text-base font-medium text-gray-900"><?php echo htmlspecialchars($class['subject_title']); ?></h4>
                            <p class="text-sm text-gray-500"><?php echo htmlspecialchars($class['subject_code']); ?> - Section <?php echo htmlspecialchars($class['section']); ?></p>
                            <p class="text-xs text-gray-400">Teacher: <?php echo htmlspecialchars($class['teacher_first_name'] . ' ' . $class['teacher_last_name']); ?></p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">
                            Enrolled
                        </span>
                        <p class="text-xs text-gray-500 mt-1">Joined <?php echo date('M d, Y', strtotime($class['join_date'])); ?></p>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Evaluation Opportunities -->
<div class="bg-white rounded-lg shadow-md overflow-hidden mb-6 sm:mb-8">
    <div class="px-6 py-4 border-b border-gray-200">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-medium text-gray-900">Evaluation Opportunities</h3>
            <a href="evaluate-teacher.php" class="text-seait-orange hover:text-orange-600 text-sm font-medium">
                Evaluate teachers <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
    </div>

    <?php if (mysqli_num_rows($eval_opp_result) == 0): ?>
        <div class="p-6 text-center">
            <i class="fas fa-clipboard-check text-gray-300 text-4xl mb-4"></i>
            <p class="text-gray-500">No evaluation opportunities available.</p>
        </div>
    <?php else: ?>
        <div class="divide-y divide-gray-200">
            <?php while ($opportunity = mysqli_fetch_assoc($eval_opp_result)): ?>
            <div class="p-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="h-10 w-10 rounded-full bg-blue-500 flex items-center justify-center mr-4">
                            <i class="fas fa-user-tie text-white"></i>
                        </div>
                        <div>
                            <h4 class="text-sm sm:text-base font-medium text-gray-900"><?php echo htmlspecialchars($opportunity['subject_title']); ?></h4>
                            <p class="text-sm text-gray-500">Teacher: <?php echo htmlspecialchars($opportunity['teacher_first_name'] . ' ' . $opportunity['teacher_last_name']); ?></p>
                            <p class="text-xs text-gray-400">Section <?php echo htmlspecialchars($opportunity['section']); ?></p>
                        </div>
                    </div>
                    <div class="text-right">
                        <a href="evaluate-teacher.php?class_id=<?php echo $opportunity['class_id']; ?>"
                           class="inline-block bg-seait-orange text-white px-3 py-1 rounded text-sm hover:bg-orange-600 transition">
                            Evaluate
                        </a>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Recent Evaluations -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-medium text-gray-900">Recent Evaluations</h3>
            <a href="evaluations.php" class="text-seait-orange hover:text-orange-600 text-sm font-medium">
                View all evaluations <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
    </div>

    <?php if (mysqli_num_rows($eval_history_result) == 0): ?>
        <div class="p-6 text-center">
            <i class="fas fa-clipboard-list text-gray-300 text-4xl mb-4"></i>
            <p class="text-gray-500">No evaluations completed yet.</p>
        </div>
    <?php else: ?>
        <div class="divide-y divide-gray-200">
            <?php while ($evaluation = mysqli_fetch_assoc($eval_history_result)): ?>
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
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>
</div>

<?php
// Include the shared footer
include 'includes/footer.php';
?>