<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has guidance_officer or head role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['guidance_officer', 'head'])) {
    header('Location: ../index.php');
    exit();
}

// Set page title
$page_title = 'Teacher Evaluations';

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
$selected_teacher = isset($_GET['teacher']) ? (int)$_GET['teacher'] : 0;
$selected_status = isset($_GET['status']) ? $_GET['status'] : '';
$selected_type = isset($_GET['type']) ? $_GET['type'] : '';

// Get available semesters for filter
$semesters_query = "SELECT id, name, academic_year FROM semesters WHERE status = 'active' ORDER BY start_date DESC";
$semesters_result = mysqli_query($conn, $semesters_query);

// Get available teachers for filter
$teachers_query = "SELECT DISTINCT u.id, u.first_name, u.last_name
                  FROM users u
                  JOIN evaluation_sessions es ON u.id = es.evaluatee_id
                  WHERE u.role = 'teacher' AND es.evaluatee_type = 'teacher'
                  ORDER BY u.last_name, u.first_name";
$teachers_result = mysqli_query($conn, $teachers_query);

// Build the main query with filters
$where_conditions = ["es.evaluatee_type = 'teacher'"];
$params = [];
$param_types = "";

if ($selected_semester > 0) {
    $where_conditions[] = "es.semester_id = ?";
    $params[] = $selected_semester;
    $param_types .= "i";
}

if ($selected_teacher > 0) {
    $where_conditions[] = "es.evaluatee_id = ?";
    $params[] = $selected_teacher;
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

// Get teacher evaluations with filters
$evaluations_query = "SELECT es.*,
                     s.name as semester_name,
                     sub.name as subject_name,
                     CASE
                         WHEN es.evaluator_type = 'student' THEN evaluator_s.first_name
                         WHEN es.evaluator_type = 'teacher' THEN evaluator_f.first_name
                         WHEN es.evaluator_type = 'head' THEN evaluator_u.first_name
                         ELSE 'Unknown'
                     END as evaluator_first_name,
                     CASE
                         WHEN es.evaluator_type = 'student' THEN evaluator_s.last_name
                         WHEN es.evaluator_type = 'teacher' THEN evaluator_f.last_name
                         WHEN es.evaluator_type = 'head' THEN evaluator_u.last_name
                         ELSE 'Unknown'
                     END as evaluator_last_name,
                     es.evaluator_type as evaluator_role,
                     CASE
                         WHEN es.evaluatee_type = 'teacher' THEN evaluatee_f.first_name
                         ELSE 'Unknown'
                     END as evaluatee_first_name,
                     CASE
                         WHEN es.evaluatee_type = 'teacher' THEN evaluatee_f.last_name
                         ELSE 'Unknown'
                     END as evaluatee_last_name,
                     CASE
                         WHEN es.evaluatee_type = 'teacher' THEN evaluatee_f.email
                         ELSE 'Unknown'
                     END as evaluatee_email,
                     es.evaluatee_type as evaluatee_type,
                     evaluatee_f.id as faculty_id
                     FROM evaluation_sessions es
                     LEFT JOIN semesters s ON es.semester_id = s.id
                     LEFT JOIN subjects sub ON es.subject_id = sub.id
                     LEFT JOIN students evaluator_s ON es.evaluator_id = evaluator_s.id AND es.evaluator_type = 'student'
                     LEFT JOIN faculty evaluator_f ON es.evaluator_id = evaluator_f.id AND es.evaluator_type = 'teacher'
                     LEFT JOIN users evaluator_u ON es.evaluator_id = evaluator_u.id AND es.evaluator_type = 'head'
                     LEFT JOIN faculty evaluatee_f ON es.evaluatee_id = evaluatee_f.id AND es.evaluatee_type = 'teacher'
                     WHERE $where_clause
                     ORDER BY es.evaluation_date DESC, es.created_at DESC";

if (!empty($params)) {
    $stmt = mysqli_prepare($conn, $evaluations_query);
    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
    mysqli_stmt_execute($stmt);
    $evaluations_result = mysqli_stmt_get_result($stmt);
} else {
    $evaluations_result = mysqli_query($conn, $evaluations_query);
}

// Get evaluation statistics
$stats_query = "SELECT
                COUNT(*) as total_evaluations,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_evaluations,
                COUNT(CASE WHEN status = 'draft' THEN 1 END) as draft_evaluations,
                COUNT(CASE WHEN evaluator_type = 'student' THEN 1 END) as student_evaluations,
                COUNT(CASE WHEN evaluator_type = 'teacher' THEN 1 END) as teacher_evaluations,
                COUNT(CASE WHEN evaluator_type = 'head' THEN 1 END) as head_evaluations,
                COUNT(DISTINCT evaluatee_id) as unique_teachers_evaluated
                FROM evaluation_sessions
                WHERE evaluatee_type = 'teacher'";

if ($selected_semester > 0) {
    $stats_query .= " AND semester_id = $selected_semester";
}

$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Get top evaluated teachers
$top_teachers_query = "SELECT
                      es.evaluatee_id,
                      f.first_name,
                      f.last_name,
                      f.email,
                      COUNT(*) as evaluation_count,
                      COUNT(CASE WHEN es.status = 'completed' THEN 1 END) as completed_count
                      FROM evaluation_sessions es
                      LEFT JOIN faculty f ON es.evaluatee_id = f.id AND es.evaluatee_type = 'teacher'
                      WHERE es.evaluatee_type = 'teacher'";

if ($selected_semester > 0) {
    $top_teachers_query .= " AND es.semester_id = $selected_semester";
}

$top_teachers_query .= " GROUP BY es.evaluatee_id, f.first_name, f.last_name, f.email
                        ORDER BY evaluation_count DESC
                        LIMIT 10";

$top_teachers_result = mysqli_query($conn, $top_teachers_query);

// Get evaluation trends by month
$trends_query = "SELECT
                 DATE_FORMAT(evaluation_date, '%Y-%m') as month,
                 COUNT(*) as evaluation_count,
                 COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count
                 FROM evaluation_sessions
                 WHERE evaluatee_type = 'teacher'";

if ($selected_semester > 0) {
    $trends_query .= " AND semester_id = $selected_semester";
}

$trends_query .= " GROUP BY DATE_FORMAT(evaluation_date, '%Y-%m')
                   ORDER BY month DESC
                   LIMIT 12";

$trends_result = mysqli_query($conn, $trends_query);

// Get category-wise statistics
$category_stats_query = "SELECT
                        mec.id as category_id,
                        mec.name as category_name,
                        mec.evaluation_type,
                        COUNT(DISTINCT es.id) as total_evaluations,
                        COUNT(DISTINCT CASE WHEN es.status = 'completed' THEN es.id END) as completed_evaluations,
                        AVG(er.rating_value) as average_rating,
                        COUNT(CASE WHEN er.rating_value = 5 THEN 1 END) as excellent_count,
                        COUNT(CASE WHEN er.rating_value = 4 THEN 1 END) as very_satisfactory_count,
                        COUNT(CASE WHEN er.rating_value = 3 THEN 1 END) as satisfactory_count,
                        COUNT(CASE WHEN er.rating_value = 2 THEN 1 END) as good_count,
                        COUNT(CASE WHEN er.rating_value = 1 THEN 1 END) as poor_count,
                        COUNT(er.rating_value) as total_ratings
                        FROM main_evaluation_categories mec
                        LEFT JOIN evaluation_sessions es ON mec.id = es.main_category_id AND es.evaluatee_type = 'teacher'";

if ($selected_semester > 0) {
    $category_stats_query .= " AND es.semester_id = $selected_semester";
}

$category_stats_query .= " LEFT JOIN evaluation_responses er ON es.id = er.evaluation_session_id
                          WHERE mec.status = 'active'
                          GROUP BY mec.id
                          ORDER BY mec.name";

$category_stats_result = mysqli_query($conn, $category_stats_query);

// Get sub-category statistics
$subcategory_stats_query = "SELECT
                           esc.id as sub_category_id,
                           esc.name as sub_category_name,
                           mec.name as main_category_name,
                           COUNT(DISTINCT es.id) as total_evaluations,
                           COUNT(DISTINCT CASE WHEN es.status = 'completed' THEN es.id END) as completed_evaluations,
                           AVG(er.rating_value) as average_rating,
                           COUNT(CASE WHEN er.rating_value = 5 THEN 1 END) as excellent_count,
                           COUNT(CASE WHEN er.rating_value = 4 THEN 1 END) as very_satisfactory_count,
                           COUNT(CASE WHEN er.rating_value = 3 THEN 1 END) as satisfactory_count,
                           COUNT(CASE WHEN er.rating_value = 2 THEN 1 END) as good_count,
                           COUNT(CASE WHEN er.rating_value = 1 THEN 1 END) as poor_count,
                           COUNT(er.rating_value) as total_ratings
                           FROM evaluation_sub_categories esc
                           JOIN main_evaluation_categories mec ON esc.main_category_id = mec.id
                           LEFT JOIN evaluation_sessions es ON mec.id = es.main_category_id AND es.evaluatee_type = 'teacher'";

if ($selected_semester > 0) {
    $subcategory_stats_query .= " AND es.semester_id = $selected_semester";
}

$subcategory_stats_query .= " LEFT JOIN evaluation_questionnaires eq ON esc.id = eq.sub_category_id
                             LEFT JOIN evaluation_responses er ON eq.id = er.questionnaire_id AND es.id = er.evaluation_session_id
                             WHERE esc.status = 'active' AND mec.status = 'active'
                             GROUP BY esc.id
                             ORDER BY mec.name, esc.order_number";

$subcategory_stats_result = mysqli_query($conn, $subcategory_stats_query);

// Get overall average rating
$overall_avg_query = "SELECT
                     AVG(er.rating_value) as overall_average,
                     COUNT(er.rating_value) as total_ratings,
                     COUNT(DISTINCT es.id) as total_evaluations,
                     COUNT(DISTINCT CASE WHEN es.status = 'completed' THEN es.id END) as completed_evaluations
                     FROM evaluation_sessions es
                     LEFT JOIN evaluation_responses er ON es.id = er.evaluation_session_id
                     WHERE es.evaluatee_type = 'teacher'";

if ($selected_semester > 0) {
    $overall_avg_query .= " AND es.semester_id = $selected_semester";
}

$overall_avg_result = mysqli_query($conn, $overall_avg_query);
$overall_stats = mysqli_fetch_assoc($overall_avg_result);

// Include the shared header
include 'includes/header.php';
?>

<div class="mb-6 sm:mb-8">
    <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Teacher Evaluations</h1>
    <p class="text-sm sm:text-base text-gray-600">Manage and analyze teacher evaluation data</p>
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

<!-- Action Buttons -->
<div class="mb-6 flex flex-col sm:flex-row gap-3">
    <a href="all-evaluations.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition text-center">
        <i class="fas fa-list mr-2"></i>View All Evaluations
    </a>
    <a href="export_evaluation_reports.php?type=teachers&semester=<?php echo $selected_semester; ?>" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition text-center">
        <i class="fas fa-download mr-2"></i>Export Reports
    </a>
    <a href="categories.php" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition text-center">
        <i class="fas fa-cog mr-2"></i>Manage Categories
    </a>
</div>

<!-- Information Alert -->
<div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
    <div class="flex">
        <div class="flex-shrink-0">
            <i class="fas fa-info-circle text-blue-400"></i>
        </div>
        <div class="ml-3">
            <h3 class="text-sm font-medium text-blue-800">Teacher Evaluation Overview</h3>
            <div class="mt-2 text-sm text-blue-700">
                <p>This page provides comprehensive analytics and management tools for teacher evaluations. Use the filters below to analyze specific data, view detailed statistics, and export evaluation reports. <strong>All data is filtered by the selected criteria above.</strong></p>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="mb-6 p-4 bg-white border border-gray-200 rounded-lg">
    <h3 class="text-lg font-medium text-gray-900 mb-4">Filter Evaluations</h3>
    <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
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
            <label class="block text-sm font-medium text-gray-700 mb-2">Teacher</label>
            <select name="teacher" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                <option value="">All Teachers</option>
                <?php while ($teacher = mysqli_fetch_assoc($teachers_result)): ?>
                <option value="<?php echo $teacher['id']; ?>" <?php echo $selected_teacher == $teacher['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($teacher['last_name'] . ', ' . $teacher['first_name']); ?>
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
                        <i class="fas fa-user-tie text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Teachers Evaluated</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['unique_teachers_evaluated']); ?></dd>
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

<!-- Results Statistics -->
<div class="mb-6 sm:mb-8">
    <h2 class="text-xl sm:text-2xl font-bold text-seait-dark mb-4">Results Statistics</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="p-4 sm:p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-10 h-10 rounded-full bg-blue-500 flex items-center justify-center">
                            <i class="fas fa-star text-white"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-gray-900">Overall Average Rating</h3>
                        <p class="text-2xl font-bold text-blue-600"><?php echo $overall_stats['overall_average'] ? round($overall_stats['overall_average'], 2) : 'N/A'; ?></p>
                        <p class="text-xs text-gray-500">Total Ratings: <?php echo number_format($overall_stats['total_ratings']); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="p-4 sm:p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-10 h-10 rounded-full bg-green-500 flex items-center justify-center">
                            <i class="fas fa-check-circle text-white"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-gray-900">Completed Evaluations</h3>
                        <p class="text-2xl font-bold text-green-600"><?php echo number_format($overall_stats['completed_evaluations']); ?></p>
                        <p class="text-xs text-gray-500">Total: <?php echo number_format($overall_stats['total_evaluations']); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="p-4 sm:p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-10 h-10 rounded-full bg-purple-500 flex items-center justify-center">
                            <i class="fas fa-chart-line text-white"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-gray-900">Total Evaluations</h3>
                        <p class="text-2xl font-bold text-purple-600"><?php echo number_format($overall_stats['total_evaluations']); ?></p>
                        <p class="text-xs text-gray-500">Total Ratings: <?php echo number_format($overall_stats['total_ratings']); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Top Evaluated Teachers -->
<div class="mb-6 sm:mb-8">
    <h2 class="text-xl sm:text-2xl font-bold text-seait-dark mb-4">Top Evaluated Teachers</h2>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 sm:gap-8">
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
                <h3 class="text-base sm:text-lg font-medium text-gray-900">Most Evaluated Teachers</h3>
            </div>
            <div class="p-4 sm:p-6">
                <div class="space-y-3">
                    <?php while($teacher = mysqli_fetch_assoc($top_teachers_result)): ?>
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div class="flex items-center">
                            <div class="w-8 h-8 rounded-full bg-seait-orange flex items-center justify-center mr-3">
                                <span class="text-white text-sm font-medium"><?php echo strtoupper(substr($teacher['first_name'], 0, 1) . substr($teacher['last_name'], 0, 1)); ?></span>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($teacher['last_name'] . ', ' . $teacher['first_name']); ?></p>
                                <p class="text-xs text-gray-500"><?php echo htmlspecialchars($teacher['email'] ?? 'N/A'); ?></p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-medium text-gray-900"><?php echo $teacher['evaluation_count']; ?> total</p>
                            <p class="text-xs text-green-600"><?php echo $teacher['completed_count']; ?> completed</p>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>

        <!-- Evaluation Trends Chart -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
                <h3 class="text-base sm:text-lg font-medium text-gray-900">Evaluation Trends</h3>
            </div>
            <div class="p-4 sm:p-6">
                <div class="chart-container">
                    <canvas id="trendsChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Category-wise Statistics -->
<div class="mb-6 sm:mb-8">
    <h2 class="text-xl sm:text-2xl font-bold text-seait-dark mb-4">Category-wise Statistics</h2>

    <?php if (mysqli_num_rows($category_stats_result) > 0): ?>
    <div class="space-y-4 sm:space-y-6">
        <?php while ($category = mysqli_fetch_assoc($category_stats_result)): ?>
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="p-4 sm:p-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4">
                    <div class="mb-3 sm:mb-0">
                        <h3 class="text-base sm:text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($category['category_name']); ?></h3>
                        <p class="text-sm text-gray-600"><?php echo ucwords(str_replace('_', ' ', $category['evaluation_type'])); ?></p>
                    </div>
                    <div class="text-left sm:text-right">
                        <p class="text-2xl font-bold text-seait-orange"><?php echo $category['average_rating'] ? round($category['average_rating'], 2) : 'N/A'; ?></p>
                        <p class="text-sm text-gray-600">Average Rating</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-4">
                    <div class="text-center">
                        <p class="text-2xl font-bold text-green-600"><?php echo $category['excellent_count']; ?></p>
                        <p class="text-xs text-gray-600">Excellent (5)</p>
                    </div>
                    <div class="text-center">
                        <p class="text-2xl font-bold text-blue-600"><?php echo $category['very_satisfactory_count']; ?></p>
                        <p class="text-xs text-gray-600">Very Satisfactory (4)</p>
                    </div>
                    <div class="text-center">
                        <p class="text-2xl font-bold text-yellow-600"><?php echo $category['satisfactory_count']; ?></p>
                        <p class="text-xs text-gray-600">Satisfactory (3)</p>
                    </div>
                    <div class="text-center">
                        <p class="text-2xl font-bold text-orange-600"><?php echo $category['good_count'] + $category['poor_count']; ?></p>
                        <p class="text-xs text-gray-600">Good/Poor (1-2)</p>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between text-sm text-gray-600 space-y-2 sm:space-y-0">
                    <span>Total Evaluations: <?php echo $category['total_evaluations']; ?></span>
                    <span>Completed: <?php echo $category['completed_evaluations']; ?></span>
                    <span>Total Ratings: <?php echo $category['total_ratings']; ?></span>
                </div>

                <?php if ($category['total_ratings'] > 0): ?>
                <div class="mt-3">
                    <div class="flex items-center">
                        <div class="flex-1 bg-gray-200 rounded-full h-2 mr-3">
                            <?php
                            $excellent_percent = ($category['excellent_count'] / $category['total_ratings']) * 100;
                            $very_satisfactory_percent = ($category['very_satisfactory_count'] / $category['total_ratings']) * 100;
                            $satisfactory_percent = ($category['satisfactory_count'] / $category['total_ratings']) * 100;
                            $other_percent = (($category['good_count'] + $category['poor_count']) / $category['total_ratings']) * 100;
                            ?>
                            <div class="flex h-2 rounded-full overflow-hidden">
                                <div class="bg-green-500" style="width: <?php echo $excellent_percent; ?>%"></div>
                                <div class="bg-blue-500" style="width: <?php echo $very_satisfactory_percent; ?>%"></div>
                                <div class="bg-yellow-500" style="width: <?php echo $satisfactory_percent; ?>%"></div>
                                <div class="bg-orange-500" style="width: <?php echo $other_percent; ?>%"></div>
                            </div>
                        </div>
                        <span class="text-xs text-gray-500">Rating Distribution</span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
    <?php else: ?>
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="p-8 text-center">
            <i class="fas fa-chart-bar text-gray-300 text-4xl mb-4"></i>
            <p class="text-gray-500">No category statistics available.</p>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Sub-category Statistics -->
<div class="mb-6 sm:mb-8">
    <h2 class="text-xl sm:text-2xl font-bold text-seait-dark mb-4">Sub-category Performance</h2>

    <?php if (mysqli_num_rows($subcategory_stats_result) > 0): ?>
    <?php
    // Group subcategories by main category
    $main_categories = [];
    mysqli_data_seek($subcategory_stats_result, 0);
    while ($subcategory = mysqli_fetch_assoc($subcategory_stats_result)) {
        $main_category_name = $subcategory['main_category_name'];
        if (!isset($main_categories[$main_category_name])) {
            $main_categories[$main_category_name] = [];
        }
        $main_categories[$main_category_name][] = $subcategory;
    }
    ?>

    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <!-- Tab Navigation -->
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex space-x-1 sm:space-x-2 lg:space-x-8 overflow-x-auto px-2 sm:px-4 lg:px-6" aria-label="Tabs">
                <?php $first_tab = true; ?>
                <?php foreach ($main_categories as $main_category_name => $subcategories): ?>
                <button class="tab-button whitespace-nowrap py-3 sm:py-4 px-2 sm:px-3 border-b-2 font-medium text-xs sm:text-sm <?php echo $first_tab ? 'border-seait-orange text-seait-orange' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>"
                        data-tab="<?php echo htmlspecialchars(str_replace(' ', '-', strtolower($main_category_name))); ?>">
                    <span class="hidden sm:inline"><?php echo htmlspecialchars($main_category_name); ?></span>
                    <span class="sm:hidden"><?php echo htmlspecialchars(substr($main_category_name, 0, 15)) . (strlen($main_category_name) > 15 ? '...' : ''); ?></span>
                    <span class="ml-1 sm:ml-2 bg-gray-100 text-gray-900 py-0.5 px-1.5 sm:px-2.5 rounded-full text-xs"><?php echo count($subcategories); ?></span>
                </button>
                <?php $first_tab = false; ?>
                <?php endforeach; ?>
            </nav>
        </div>

        <!-- Tab Content -->
        <?php $first_tab = true; ?>
        <?php foreach ($main_categories as $main_category_name => $subcategories): ?>
        <div class="tab-content <?php echo $first_tab ? 'block' : 'hidden'; ?>"
             id="tab-<?php echo htmlspecialchars(str_replace(' ', '-', strtolower($main_category_name))); ?>">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-2 sm:px-3 lg:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sub-category</th>
                            <th class="px-2 sm:px-3 lg:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Rating</th>
                            <th class="px-2 sm:px-3 lg:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                            <th class="px-2 sm:px-3 lg:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Distribution</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($subcategories as $subcategory): ?>
                        <tr>
                            <td class="px-2 sm:px-3 lg:px-6 py-2 sm:py-4">
                                <div class="text-xs sm:text-sm font-medium text-gray-900">
                                    <span class="hidden sm:inline"><?php echo htmlspecialchars($subcategory['sub_category_name']); ?></span>
                                    <span class="sm:hidden"><?php echo htmlspecialchars(substr($subcategory['sub_category_name'], 0, 20)) . (strlen($subcategory['sub_category_name']) > 20 ? '...' : ''); ?></span>
                                </div>
                            </td>
                            <td class="px-2 sm:px-3 lg:px-6 py-2 sm:py-4">
                                <div class="text-xs sm:text-sm font-medium text-gray-900">
                                    <?php echo $subcategory['average_rating'] ? round($subcategory['average_rating'], 2) : 'N/A'; ?>
                                </div>
                            </td>
                            <td class="px-2 sm:px-3 lg:px-6 py-2 sm:py-4">
                                <div class="text-xs sm:text-sm text-gray-900"><?php echo $subcategory['total_ratings']; ?></div>
                            </td>
                            <td class="px-2 sm:px-3 lg:px-6 py-2 sm:py-4">
                                <?php if ($subcategory['total_ratings'] > 0): ?>
                                <div class="flex flex-col sm:flex-row sm:items-center sm:space-x-1 space-y-1 sm:space-y-0">
                                    <span class="text-xs text-green-600"><?php echo $subcategory['excellent_count']; ?></span>
                                    <span class="text-xs text-blue-600"><?php echo $subcategory['very_satisfactory_count']; ?></span>
                                    <span class="text-xs text-yellow-600"><?php echo $subcategory['satisfactory_count']; ?></span>
                                    <span class="text-xs text-orange-600"><?php echo $subcategory['good_count'] + $subcategory['poor_count']; ?></span>
                                </div>
                                <?php else: ?>
                                <span class="text-xs text-gray-500">No ratings</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php $first_tab = false; ?>
        <?php endforeach; ?>
    </div>

    <?php else: ?>
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="p-8 text-center">
            <i class="fas fa-chart-pie text-gray-300 text-4xl mb-4"></i>
            <p class="text-gray-500">No sub-category statistics available.</p>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Category Performance Chart -->
<div class="mb-6 sm:mb-8">
    <h2 class="text-xl sm:text-2xl font-bold text-seait-dark mb-4">Category Performance Overview</h2>
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="p-4 sm:p-6">
            <div class="chart-container large">
                <canvas id="categoryChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Teacher Evaluations Table -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center">
            <h2 class="text-base sm:text-lg font-medium text-gray-900">Teacher Evaluations</h2>
            <div class="mt-2 sm:mt-0">
                <a href="export_evaluation_reports.php?type=teachers&semester=<?php echo $selected_semester; ?>"
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
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teacher</th>
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
                                <?php echo htmlspecialchars($evaluation['evaluatee_last_name'] . ', ' . $evaluation['evaluatee_first_name']); ?>
                            </p>
                            <p class="text-xs text-gray-500"><?php echo htmlspecialchars($evaluation['evaluatee_email'] ?? 'N/A'); ?></p>
                        </div>
                    </td>
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
                            <a href="view-evaluation.php?<?php echo $evaluation['evaluatee_type'] === 'teacher' && $evaluation['faculty_id'] ? 'faculty_id=' . $evaluation['faculty_id'] : 'id=' . $evaluation['evaluatee_id']; ?>"
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
        <p class="text-gray-500">No teacher evaluations found with the selected filters.</p>
    </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab functionality for sub-category performance
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');

    tabButtons.forEach(button => {
        button.addEventListener('click', () => {
            const targetTab = button.getAttribute('data-tab');

            // Remove active state from all tabs
            tabButtons.forEach(btn => {
                btn.classList.remove('border-seait-orange', 'text-seait-orange');
                btn.classList.add('border-transparent', 'text-gray-500');
            });

            // Hide all tab contents
            tabContents.forEach(content => {
                content.classList.add('hidden');
            });

            // Add active state to clicked tab
            button.classList.remove('border-transparent', 'text-gray-500');
            button.classList.add('border-seait-orange', 'text-seait-orange');

            // Show target tab content
            const targetContent = document.getElementById('tab-' + targetTab);
            if (targetContent) {
                targetContent.classList.remove('hidden');
            }
        });
    });

    // Evaluation Trends Chart
    const trendsCtx = document.getElementById('trendsChart');
    if (trendsCtx) {
        const trendsData = {
            labels: [
                <?php
                $trends_labels = [];
                $trends_data = [];
                $trends_completed = [];
                mysqli_data_seek($trends_result, 0);
                while($row = mysqli_fetch_assoc($trends_result)) {
                    $trends_labels[] = "'" . date('M Y', strtotime($row['month'] . '-01')) . "'";
                    $trends_data[] = $row['evaluation_count'];
                    $trends_completed[] = $row['completed_count'];
                }
                echo implode(', ', array_reverse($trends_labels));
                ?>
            ],
            datasets: [{
                label: 'Total Evaluations',
                data: [<?php echo implode(', ', array_reverse($trends_data)); ?>],
                borderColor: '#FF6B35',
                backgroundColor: 'rgba(255, 107, 53, 0.1)',
                borderWidth: 2,
                fill: false
            }, {
                label: 'Completed',
                data: [<?php echo implode(', ', array_reverse($trends_completed)); ?>],
                borderColor: '#4CAF50',
                backgroundColor: 'rgba(76, 175, 80, 0.1)',
                borderWidth: 2,
                fill: false
            }]
        };

        new Chart(trendsCtx.getContext('2d'), {
            type: 'line',
            data: trendsData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                },
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }

    // Category Performance Chart
    const categoryCtx = document.getElementById('categoryChart');
    if (categoryCtx) {
        const categoryData = {
            labels: [
                <?php
                $category_labels = [];
                $category_ratings = [];
                $category_counts = [];
                mysqli_data_seek($category_stats_result, 0);
                while($row = mysqli_fetch_assoc($category_stats_result)) {
                    $category_labels[] = "'" . addslashes($row['category_name']) . "'";
                    $category_ratings[] = $row['average_rating'] ? round($row['average_rating'], 2) : 0;
                    $category_counts[] = $row['total_ratings'];
                }
                echo implode(', ', $category_labels);
                ?>
            ],
            datasets: [{
                label: 'Average Rating',
                data: [<?php echo implode(', ', $category_ratings); ?>],
                backgroundColor: [
                    'rgba(255, 107, 53, 0.8)',
                    'rgba(76, 175, 80, 0.8)',
                    'rgba(156, 39, 176, 0.8)',
                    'rgba(33, 150, 243, 0.8)',
                    'rgba(255, 193, 7, 0.8)'
                ],
                borderColor: [
                    'rgba(255, 107, 53, 1)',
                    'rgba(76, 175, 80, 1)',
                    'rgba(156, 39, 176, 1)',
                    'rgba(33, 150, 243, 1)',
                    'rgba(255, 193, 7, 1)'
                ],
                borderWidth: 2,
                borderRadius: 8,
                borderSkipped: false,
            }, {
                label: 'Total Ratings',
                data: [<?php echo implode(', ', $category_counts); ?>],
                backgroundColor: 'rgba(158, 158, 158, 0.3)',
                borderColor: 'rgba(158, 158, 158, 1)',
                borderWidth: 1,
                borderRadius: 4,
                borderSkipped: false,
                yAxisID: 'y1'
            }]
        };

        new Chart(categoryCtx.getContext('2d'), {
            type: 'bar',
            data: categoryData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        beginAtZero: true,
                        max: 5,
                        ticks: {
                            stepSize: 1
                        },
                        title: {
                            display: true,
                            text: 'Average Rating (1-5)'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        beginAtZero: true,
                        grid: {
                            drawOnChartArea: false,
                        },
                        title: {
                            display: true,
                            text: 'Total Ratings'
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            afterLabel: function(context) {
                                const dataIndex = context.dataIndex;
                                const rating = categoryData.datasets[0].data[dataIndex];
                                const count = categoryData.datasets[1].data[dataIndex];
                                return `Average: ${rating}/5 | Total Ratings: ${count}`;
                            }
                        }
                    }
                }
            }
        });
    }
});
</script>

<?php
// Include the shared footer
include 'includes/footer.php';
?>