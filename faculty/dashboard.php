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
$page_title = 'Dashboard';

// Get teacher's department
$faculty_query = "SELECT f.department FROM faculty f WHERE f.email = ? AND f.is_active = 1";
$faculty_stmt = mysqli_prepare($conn, $faculty_query);
mysqli_stmt_bind_param($faculty_stmt, "s", $_SESSION['username']);
mysqli_stmt_execute($faculty_stmt);
$faculty_result = mysqli_stmt_get_result($faculty_stmt);
$faculty_info = mysqli_fetch_assoc($faculty_result);
$teacher_department = $faculty_info ? $faculty_info['department'] : 'Unknown';

// Get teacher's classes count
$classes_query = "SELECT COUNT(*) as total FROM teacher_classes tc
                  JOIN faculty f ON tc.teacher_id = f.id
                  WHERE f.email = ? AND tc.status = 'active'";
$classes_stmt = mysqli_prepare($conn, $classes_query);
mysqli_stmt_bind_param($classes_stmt, "s", $_SESSION['username']);
mysqli_stmt_execute($classes_stmt);
$classes_result = mysqli_stmt_get_result($classes_stmt);
$classes_count = mysqli_fetch_assoc($classes_result)['total'];

// Get teacher's evaluations count (as evaluator)
$evaluations_query = "SELECT COUNT(*) as total FROM evaluation_sessions es
                      JOIN faculty f ON es.evaluator_id = f.id
                      WHERE f.email = ?";
$evaluations_stmt = mysqli_prepare($conn, $evaluations_query);
mysqli_stmt_bind_param($evaluations_stmt, "s", $_SESSION['username']);
mysqli_stmt_execute($evaluations_stmt);
$evaluations_result = mysqli_stmt_get_result($evaluations_stmt);
$evaluations_count = mysqli_fetch_assoc($evaluations_result)['total'];

// Get teacher's peer evaluations count
$peer_evaluations_query = "SELECT COUNT(*) as total FROM evaluation_sessions es
                          JOIN faculty f ON es.evaluator_id = f.id
                          WHERE f.email = ? AND es.main_category_id IN
                          (SELECT id FROM main_evaluation_categories WHERE evaluation_type = 'peer_to_peer')";
$peer_evaluations_stmt = mysqli_prepare($conn, $peer_evaluations_query);
mysqli_stmt_bind_param($peer_evaluations_stmt, "s", $_SESSION['username']);
mysqli_stmt_execute($peer_evaluations_stmt);
$peer_evaluations_result = mysqli_stmt_get_result($peer_evaluations_stmt);
$peer_evaluations_count = mysqli_fetch_assoc($peer_evaluations_result)['total'];

// Get recent classes
$recent_classes_query = "SELECT tc.*, cc.subject_title
                        FROM teacher_classes tc
                        JOIN course_curriculum cc ON tc.subject_id = cc.id
                        JOIN faculty f ON tc.teacher_id = f.id
                        WHERE f.email = ? AND tc.status = 'active'
                        ORDER BY tc.created_at DESC
                        LIMIT 5";
$recent_classes_stmt = mysqli_prepare($conn, $recent_classes_query);
mysqli_stmt_bind_param($recent_classes_stmt, "s", $_SESSION['username']);
mysqli_stmt_execute($recent_classes_stmt);
$recent_classes_result = mysqli_stmt_get_result($recent_classes_stmt);
$recent_classes = [];
while ($row = mysqli_fetch_assoc($recent_classes_result)) {
    $recent_classes[] = $row;
}

// Get recent evaluations
$recent_evaluations_query = "SELECT es.*, mec.name as category_name, mec.evaluation_type,
                            COALESCE(f.first_name, u.first_name) as evaluatee_first_name,
                            COALESCE(f.last_name, u.last_name) as evaluatee_last_name
                            FROM evaluation_sessions es
                            JOIN main_evaluation_categories mec ON es.main_category_id = mec.id
                            LEFT JOIN faculty f ON es.evaluatee_id = f.id
                            LEFT JOIN users u ON es.evaluatee_id = u.id
                            JOIN faculty evaluator ON es.evaluator_id = evaluator.id
                            WHERE evaluator.email = ?
                            ORDER BY es.created_at DESC
                            LIMIT 5";
$recent_evaluations_stmt = mysqli_prepare($conn, $recent_evaluations_query);
mysqli_stmt_bind_param($recent_evaluations_stmt, "s", $_SESSION['username']);
mysqli_stmt_execute($recent_evaluations_stmt);
$recent_evaluations_result = mysqli_stmt_get_result($recent_evaluations_stmt);
$recent_evaluations = [];
while ($row = mysqli_fetch_assoc($recent_evaluations_result)) {
    $recent_evaluations[] = $row;
}

// Include the shared header
$sidebar_context = 'main';
include 'includes/unified-header.php';
?>

<div class="mb-6 sm:mb-8">
    <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?>!</h1>
    <p class="text-sm sm:text-base text-gray-600">Faculty Dashboard - <?php echo htmlspecialchars($teacher_department); ?></p>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-6 sm:mb-8">
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
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Active Classes</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($classes_count); ?></dd>
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
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Total Evaluations</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($evaluations_count); ?></dd>
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
                        <i class="fas fa-users text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Peer Evaluations</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($peer_evaluations_count); ?></dd>
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
                        <i class="fas fa-building text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Department</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo htmlspecialchars($teacher_department); ?></dd>
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
        <a href="class-management.php" class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                    <i class="fas fa-plus text-blue-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Create New Class</h3>
                    <p class="text-sm text-gray-600">Add a new class with subject and join code</p>
                </div>
            </div>
        </a>

        <a href="peer-evaluations.php" class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                    <i class="fas fa-users text-purple-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Peer Evaluations</h3>
                    <p class="text-sm text-gray-600">Evaluate faculty in your department</p>
                </div>
            </div>
        </a>

        <a href="evaluations.php" class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                    <i class="fas fa-clipboard-list text-green-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">View Evaluations</h3>
                    <p class="text-sm text-gray-600">Check all your evaluations</p>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- Recent Classes -->
<div class="mb-6 sm:mb-8">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl sm:text-2xl font-bold text-seait-dark">Recent Classes</h2>
        <a href="class-management.php" class="text-seait-orange hover:text-orange-600 text-sm font-medium">
            View all classes <i class="fas fa-arrow-right ml-1"></i>
        </a>
    </div>

    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <?php if (empty($recent_classes)): ?>
            <div class="p-6 text-center">
                <i class="fas fa-chalkboard text-gray-300 text-4xl mb-4"></i>
                <p class="text-gray-500">No classes created yet. Start by creating your first class.</p>
                <a href="class-management.php" class="mt-4 inline-block bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition">
                    Create Class
                </a>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Section</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Join Code</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($recent_classes as $class): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($class['subject_title']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($class['section']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900 font-mono"><?php echo htmlspecialchars($class['join_code']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">
                                    <?php echo ucfirst($class['status']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="class-management.php?action=view&id=<?php echo $class['id']; ?>" class="text-seait-orange hover:text-orange-600">
                                    View Details
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Recent Evaluations -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-medium text-gray-900">Recent Evaluations</h2>
            <a href="evaluations.php" class="text-seait-orange hover:text-orange-600 text-sm font-medium">
                View all <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
    </div>

    <div class="p-6">
        <?php if (empty($recent_evaluations)): ?>
            <p class="text-gray-500 text-center py-4">No evaluations conducted yet.</p>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($recent_evaluations as $evaluation): ?>
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                        <div class="flex items-center">
                            <div class="h-10 w-10 rounded-full bg-seait-orange flex items-center justify-center mr-3">
                                <i class="fas
                                    <?php
                                    switch($evaluation['evaluation_type']) {
                                        case 'student_to_teacher':
                                            echo 'fa-user-graduate';
                                            break;
                                        case 'peer_to_peer':
                                            echo 'fa-users';
                                            break;
                                        case 'head_to_teacher':
                                            echo 'fa-user-tie';
                                            break;
                                    }
                                    ?> text-white text-sm"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($evaluation['evaluatee_first_name'] . ' ' . $evaluation['evaluatee_last_name']); ?>
                                </p>
                                <p class="text-sm text-gray-500">
                                    <?php echo htmlspecialchars($evaluation['category_name']); ?> •
                                    <?php echo ucwords(str_replace('_', ' ', $evaluation['evaluation_type'])); ?>
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-4">
                            <span class="px-2 py-1 text-xs rounded-full <?php
                                echo $evaluation['status'] === 'completed' ? 'bg-green-100 text-green-800' :
                                    ($evaluation['status'] === 'draft' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800');
                            ?>">
                                <?php echo ucfirst($evaluation['status']); ?>
                            </span>
                            <span class="text-xs text-gray-400">
                                <?php echo date('M d, Y', strtotime($evaluation['evaluation_date'])); ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Include the unified footer
include 'includes/footer.php';
?>