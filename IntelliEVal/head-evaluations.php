<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has guidance_officer role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guidance_officer') {
    header('Location: ../login.php');
    exit();
}

// Get head_id from URL parameter
$head_id = isset($_GET['head_id']) ? (int)$_GET['head_id'] : 0;

if ($head_id <= 0) {
    header('Location: heads.php');
    exit();
}

// Get head information
$head_query = "SELECT * FROM users WHERE id = ? AND role = 'head'";
$head_stmt = mysqli_prepare($conn, $head_query);
mysqli_stmt_bind_param($head_stmt, "i", $head_id);
mysqli_stmt_execute($head_stmt);
$head_result = mysqli_stmt_get_result($head_stmt);
$head = mysqli_fetch_assoc($head_result);

if (!$head) {
    header('Location: heads.php');
    exit();
}

// Set page title
$page_title = 'Head Evaluations - ' . $head['first_name'] . ' ' . $head['last_name'];

$message = '';
$message_type = '';

// Check for session messages
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Get filter parameters
$selected_semester = isset($_GET['semester']) ? (int)$_GET['semester'] : 0;
$selected_status = isset($_GET['status']) ? $_GET['status'] : '';
$selected_type = isset($_GET['type']) ? $_GET['type'] : '';

// Get available semesters for filter
$semesters_query = "SELECT id, name, academic_year FROM semesters WHERE status = 'active' ORDER BY start_date DESC";
$semesters_result = mysqli_query($conn, $semesters_query);

// Build the main query with filters
$where_conditions = ["es.evaluatee_id = ?"];
$params = [$head_id];
$param_types = "i";

if ($selected_semester > 0) {
    $where_conditions[] = "es.semester_id = ?";
    $params[] = $selected_semester;
    $param_types .= "i";
}

if ($selected_status !== '') {
    $where_conditions[] = "es.status = ?";
    $params[] = $selected_status;
    $param_types .= "s";
}

if ($selected_type !== '') {
    $where_conditions[] = "es.evaluator_type = ?";
    $params[] = $selected_type;
    $param_types .= "s";
}

$where_clause = implode(" AND ", $where_conditions);

// Get head evaluations with filters
$evaluations_query = "SELECT es.*,
                     s.name as semester_name,
                     sub.name as subject_name,
                     COALESCE(evaluator_f.first_name, evaluator_u.first_name) as evaluator_first_name,
                     COALESCE(evaluator_f.last_name, evaluator_u.last_name) as evaluator_last_name,
                     COALESCE('teacher', evaluator_u.role) as evaluator_role,
                     COALESCE(evaluatee_f.first_name, evaluatee_u.first_name) as evaluatee_first_name,
                     COALESCE(evaluatee_f.last_name, evaluatee_u.last_name) as evaluatee_last_name,
                     COALESCE(evaluatee_f.email, evaluatee_u.email) as evaluatee_email
                     FROM evaluation_sessions es
                     LEFT JOIN semesters s ON es.semester_id = s.id
                     LEFT JOIN subjects sub ON es.subject_id = sub.id
                     LEFT JOIN faculty evaluator_f ON es.evaluator_id = evaluator_f.id
                     LEFT JOIN users evaluator_u ON es.evaluator_id = evaluator_u.id
                     LEFT JOIN faculty evaluatee_f ON es.evaluatee_id = evaluatee_f.id
                     LEFT JOIN users evaluatee_u ON es.evaluatee_id = evaluatee_u.id
                     WHERE $where_clause
                     ORDER BY es.evaluation_date DESC, es.created_at DESC";

$stmt = mysqli_prepare($conn, $evaluations_query);
mysqli_stmt_bind_param($stmt, $param_types, ...$params);
mysqli_stmt_execute($stmt);
$evaluations_result = mysqli_stmt_get_result($stmt);

// Get evaluation statistics for this head
$stats_query = "SELECT
                COUNT(*) as total_evaluations,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_evaluations,
                COUNT(CASE WHEN status = 'draft' THEN 1 END) as draft_evaluations,
                COUNT(CASE WHEN evaluator_type = 'student' THEN 1 END) as student_evaluations,
                COUNT(CASE WHEN evaluator_type = 'teacher' THEN 1 END) as teacher_evaluations,
                COUNT(CASE WHEN evaluator_type = 'head' THEN 1 END) as head_evaluations
                FROM evaluation_sessions
                WHERE evaluatee_id = ?";

if ($selected_semester > 0) {
    $stats_query .= " AND semester_id = $selected_semester";
}

$stats_stmt = mysqli_prepare($conn, $stats_query);
mysqli_stmt_bind_param($stats_stmt, "i", $head_id);
mysqli_stmt_execute($stats_stmt);
$stats_result = mysqli_stmt_get_result($stats_stmt);
$stats = mysqli_fetch_assoc($stats_result);

// Get overall average rating for this head
$overall_avg_query = "SELECT
                     AVG(er.rating_value) as overall_average,
                     COUNT(er.rating_value) as total_ratings,
                     COUNT(DISTINCT es.id) as total_evaluations,
                     COUNT(DISTINCT CASE WHEN es.status = 'completed' THEN es.id END) as completed_evaluations
                     FROM evaluation_sessions es
                     LEFT JOIN evaluation_responses er ON es.id = er.evaluation_session_id
                     WHERE es.evaluatee_id = ?";

if ($selected_semester > 0) {
    $overall_avg_query .= " AND es.semester_id = $selected_semester";
}

$overall_avg_stmt = mysqli_prepare($conn, $overall_avg_query);
mysqli_stmt_bind_param($overall_avg_stmt, "i", $head_id);
mysqli_stmt_execute($overall_avg_stmt);
$overall_avg_result = mysqli_stmt_get_result($overall_avg_stmt);
$overall_stats = mysqli_fetch_assoc($overall_avg_result);

// Include the shared header
include 'includes/header.php';
?>

<div class="mb-6 sm:mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Head Evaluations</h1>
            <p class="text-sm sm:text-base text-gray-600">Evaluation data for <?php echo htmlspecialchars($head['first_name'] . ' ' . $head['last_name']); ?></p>
        </div>
        <div class="flex space-x-2">
            <a href="heads.php" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition">
                <i class="fas fa-arrow-left mr-2"></i>Back to Heads
            </a>
        </div>
    </div>
</div>

<?php if ($message): ?>
    <div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200'; ?> message-slide-in">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> text-lg"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm font-medium"><?php echo htmlspecialchars($message); ?></p>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Head Information Card -->
<div class="mb-6 bg-white rounded-lg shadow-md overflow-hidden">
    <div class="p-6">
        <div class="flex items-center">
            <div class="h-16 w-16 rounded-full bg-purple-600 flex items-center justify-center mr-4">
                <span class="text-white text-xl font-bold"><?php echo strtoupper(substr($head['first_name'], 0, 1) . substr($head['last_name'], 0, 1)); ?></span>
            </div>
            <div class="flex-1">
                <h2 class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars($head['first_name'] . ' ' . $head['last_name']); ?></h2>
                <p class="text-gray-600"><?php echo htmlspecialchars($head['email']); ?></p>
                <p class="text-sm text-gray-500">Department Head</p>
            </div>
            <div class="text-right">
                <span class="px-3 py-1 text-sm rounded-full <?php echo $head['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                    <?php echo ucfirst($head['status']); ?>
                </span>
            </div>
        </div>
    </div>
</div>

<!-- Action Buttons -->
<div class="mb-6 flex flex-col sm:flex-row gap-3">
    <a href="all-evaluations.php?evaluatee_id=<?php echo $head_id; ?>" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition text-center">
        <i class="fas fa-list mr-2"></i>View All Evaluations
    </a>
    <a href="export_evaluation_reports.php?type=head&head_id=<?php echo $head_id; ?>&semester=<?php echo $selected_semester; ?>" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition text-center">
        <i class="fas fa-download mr-2"></i>Export Reports
    </a>
    <a href="conduct-evaluation.php?evaluatee_id=<?php echo $head_id; ?>&evaluatee_type=head" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition text-center">
        <i class="fas fa-plus mr-2"></i>Conduct Evaluation
    </a>
</div>

<!-- Filters -->
<div class="mb-6 p-4 bg-white border border-gray-200 rounded-lg">
    <h3 class="text-lg font-medium text-gray-900 mb-4">Filter Evaluations</h3>
    <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <input type="hidden" name="head_id" value="<?php echo $head_id; ?>">

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Semester</label>
            <select name="semester" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                <option value="">All Semesters</option>
                <?php while ($semester = mysqli_fetch_assoc($semesters_result)): ?>
                <option value="<?php echo $semester['id']; ?>" <?php echo $selected_semester == $semester['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($semester['name'] . ' (' . $semester['academic_year'] . ')'); ?>
                </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
            <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                <option value="">All Status</option>
                <option value="draft" <?php echo $selected_status === 'draft' ? 'selected' : ''; ?>>Draft</option>
                <option value="completed" <?php echo $selected_status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                <option value="archived" <?php echo $selected_status === 'archived' ? 'selected' : ''; ?>>Archived</option>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Evaluator Type</label>
            <select name="type" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                <option value="">All Types</option>
                <option value="student" <?php echo $selected_type === 'student' ? 'selected' : ''; ?>>Student</option>
                <option value="teacher" <?php echo $selected_type === 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                <option value="head" <?php echo $selected_type === 'head' ? 'selected' : ''; ?>>Head</option>
            </select>
        </div>

        <div class="flex items-end">
            <button type="submit" class="w-full bg-seait-orange text-white px-4 py-2 rounded-md hover:bg-orange-600 transition-colors">
                <i class="fas fa-filter mr-2"></i>Apply Filters
            </button>
        </div>
    </form>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-6 sm:mb-8">
    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-4 sm:p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-chart-bar text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Total Evaluations</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['total_evaluations']); ?></dd>
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
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Completed</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['completed_evaluations']); ?></dd>
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
                        <i class="fas fa-star text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Average Rating</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo $overall_stats['overall_average'] ? round($overall_stats['overall_average'], 2) : 'N/A'; ?></dd>
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
                        <i class="fas fa-users text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Student Evaluations</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['student_evaluations']); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Head Evaluations Table -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center">
            <h2 class="text-base sm:text-lg font-medium text-gray-900">Head Evaluations</h2>
            <div class="mt-2 sm:mt-0">
                <a href="export_evaluation_reports.php?type=head&head_id=<?php echo $head_id; ?>&semester=<?php echo $selected_semester; ?>"
                   class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 transition-colors text-sm">
                    <i class="fas fa-download mr-2"></i>Export
                </a>
            </div>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Evaluator</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Semester</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php while($evaluation = mysqli_fetch_assoc($evaluations_result)): ?>
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div>
                            <p class="text-sm font-medium text-gray-900">
                                <?php echo htmlspecialchars($evaluation['evaluator_last_name'] . ', ' . $evaluation['evaluator_first_name']); ?>
                            </p>
                            <span class="px-2 py-1 text-xs rounded <?php
                                echo ($evaluation['evaluator_role'] ?? '') == 'student' ? 'bg-blue-100 text-blue-800' :
                                    (($evaluation['evaluator_role'] ?? '') == 'teacher' ? 'bg-green-100 text-green-800' : 'bg-purple-100 text-purple-800');
                            ?>">
                                <?php echo ucwords($evaluation['evaluator_role'] ?? 'Unknown'); ?>
                            </span>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?php echo htmlspecialchars($evaluation['subject_name'] ?? 'N/A'); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?php echo htmlspecialchars($evaluation['semester_name'] ?? 'N/A'); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 py-1 text-xs rounded <?php
                            echo ($evaluation['status'] ?? '') == 'completed' ? 'bg-green-100 text-green-800' :
                                (($evaluation['status'] ?? '') == 'draft' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800');
                        ?>">
                            <?php echo ucwords($evaluation['status'] ?? 'Unknown'); ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <?php echo $evaluation['evaluation_date'] ? date('M d, Y', strtotime($evaluation['evaluation_date'])) : 'N/A'; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <div class="flex space-x-2">
                            <a href="view-evaluation.php?id=<?php echo $evaluation['evaluatee_id']; ?>"
                               class="text-seait-orange hover:text-orange-600">
                                <i class="fas fa-eye"></i>
                            </a>
                            <?php if (($evaluation['status'] ?? '') === 'draft'): ?>
                            <a href="conduct-evaluation.php?session_id=<?php echo $evaluation['id']; ?>"
                               class="text-blue-600 hover:text-blue-800">
                                <i class="fas fa-edit"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <?php if (mysqli_num_rows($evaluations_result) == 0): ?>
    <div class="p-8 text-center">
        <i class="fas fa-inbox text-gray-400 text-4xl mb-4"></i>
        <p class="text-gray-500">No evaluations found for this head with the selected filters.</p>
    </div>
    <?php endif; ?>
</div>

<?php
// Include the shared footer
include 'includes/footer.php';
?>