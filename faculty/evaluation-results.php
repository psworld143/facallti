<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has teacher role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

// Handle AJAX requests
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
// Set page title
$page_title = 'My Evaluation Results';

$message = '';
$message_type = '';

// Get search and filter parameters
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$type_filter = isset($_GET['type']) ? sanitize_input($_GET['type']) : '';
$selected_category = isset($_GET['category']) ? sanitize_input($_GET['category']) : '';
$semester_filter = isset($_GET['semester']) ? sanitize_input($_GET['semester']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get available semesters for filtering
$semesters_query = "SELECT DISTINCT s.id, s.name, s.academic_year
                    FROM semesters s
                    JOIN evaluation_sessions es ON s.id = es.semester_id
                    WHERE es.evaluatee_id = ?
                    ORDER BY s.academic_year DESC, s.name ASC";
$semesters_stmt = mysqli_prepare($conn, $semesters_query);
mysqli_stmt_bind_param($semesters_stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($semesters_stmt);
$semesters_result = mysqli_stmt_get_result($semesters_stmt);

$semesters = [];
while ($semester = mysqli_fetch_assoc($semesters_result)) {
    $semesters[] = $semester;
}

// Get all main categories that the teacher has evaluations for
$categories_query = "SELECT DISTINCT mec.id, mec.name, mec.evaluation_type, mec.description,
                           COUNT(es.id) as evaluation_count,
                           SUM(CASE WHEN es.status = 'completed' THEN 1 ELSE 0 END) as completed_count
                    FROM main_evaluation_categories mec
                    JOIN evaluation_sessions es ON mec.id = es.main_category_id
                    WHERE es.evaluatee_id = ?
                    GROUP BY mec.id, mec.name, mec.evaluation_type, mec.description
                    ORDER BY mec.name ASC";
$categories_stmt = mysqli_prepare($conn, $categories_query);
mysqli_stmt_bind_param($categories_stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($categories_stmt);
$categories_result = mysqli_stmt_get_result($categories_stmt);

$categories = [];
$first_category_id = null;
while ($category = mysqli_fetch_assoc($categories_result)) {
    $categories[] = $category;
    if ($first_category_id === null) {
        $first_category_id = $category['id'];
    }
}

// If no category is selected, select the first one
if (empty($selected_category) && !empty($categories)) {
    $selected_category = $first_category_id;
}

// Build query for evaluations where teacher is the evaluatee
$where_conditions = ["es.evaluatee_id = ?"];
$params = [$_SESSION['user_id']];
$param_types = 'i';

// Add category filter if selected
if ($selected_category) {
    $where_conditions[] = "es.main_category_id = ?";
    $params[] = $selected_category;
    $param_types .= 'i';
}

if ($search) {
    $where_conditions[] = "mec.name LIKE ?";
    $search_param = "%$search%";
    $params[] = $search_param;
    $param_types .= 's';
}

if ($status_filter) {
    $where_conditions[] = "es.status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

if ($type_filter) {
    $where_conditions[] = "mec.evaluation_type = ?";
    $params[] = $type_filter;
    $param_types .= 's';
}

if ($semester_filter) {
    $where_conditions[] = "es.semester_id = ?";
    $params[] = $semester_filter;
    $param_types .= 'i';
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM evaluation_sessions es
                JOIN main_evaluation_categories mec ON es.main_category_id = mec.id
                LEFT JOIN faculty f ON es.evaluator_id = f.id
                LEFT JOIN users u ON es.evaluator_id = u.id
                $where_clause";
$count_stmt = mysqli_prepare($conn, $count_query);
mysqli_stmt_bind_param($count_stmt, $param_types, ...$params);
mysqli_stmt_execute($count_stmt);
$count_result = mysqli_stmt_get_result($count_stmt);
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $per_page);

// Get evaluation results
$results_query = "SELECT es.*, mec.name as category_name, mec.evaluation_type,
                  COALESCE(f.first_name, u.first_name) as evaluator_first_name,
                  COALESCE(f.last_name, u.last_name) as evaluator_last_name,
                  COALESCE(f.email, u.email) as evaluator_email,
                      COALESCE('teacher', u.role) as evaluator_role,
                      COUNT(er.id) as response_count
                  FROM evaluation_sessions es
                  JOIN main_evaluation_categories mec ON es.main_category_id = mec.id
                  LEFT JOIN faculty f ON es.evaluator_id = f.id
                  LEFT JOIN users u ON es.evaluator_id = u.id
                      LEFT JOIN evaluation_responses er ON es.id = er.evaluation_session_id
                  $where_clause
                      GROUP BY es.id, es.evaluator_id, es.evaluatee_id, es.main_category_id, es.semester_id, es.subject_id, es.evaluation_date, es.status, es.notes, es.created_at, es.updated_at, mec.name, mec.evaluation_type, f.first_name, f.last_name, f.email, u.first_name, u.last_name, u.email, u.role
                      ORDER BY es.created_at DESC
                      LIMIT ? OFFSET ?";
    $params[] = $per_page;
    $params[] = $offset;
    $param_types .= 'ii';

    $results_stmt = mysqli_prepare($conn, $results_query);
    mysqli_stmt_bind_param($results_stmt, $param_types, ...$params);
    mysqli_stmt_execute($results_stmt);
    $results_result = mysqli_stmt_get_result($results_stmt);

    // Define $current_category for AJAX response
    $current_category = null;
    if (!empty($categories)) {
        foreach ($categories as $category) {
            if ($category['id'] == $selected_category) {
                $current_category = $category;
                break;
            }
        }
    }

    // Start output buffering for AJAX response
    ob_start();

    // Include only the results table for AJAX
    include 'evaluation-results-table.php';

    $ajax_content = ob_get_clean();
    echo $ajax_content;
    exit();
}

// Set page title
$page_title = 'My Evaluation Results';

$message = '';
$message_type = '';

// Get search and filter parameters
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$type_filter = isset($_GET['type']) ? sanitize_input($_GET['type']) : '';
$selected_category = isset($_GET['category']) ? sanitize_input($_GET['category']) : '';
$semester_filter = isset($_GET['semester']) ? sanitize_input($_GET['semester']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get available semesters for filtering
$semesters_query = "SELECT DISTINCT s.id, s.name, s.academic_year
                    FROM semesters s
                    JOIN evaluation_sessions es ON s.id = es.semester_id
                    WHERE es.evaluatee_id = ?
                    ORDER BY s.academic_year DESC, s.name ASC";
$semesters_stmt = mysqli_prepare($conn, $semesters_query);
mysqli_stmt_bind_param($semesters_stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($semesters_stmt);
$semesters_result = mysqli_stmt_get_result($semesters_stmt);

$semesters = [];
while ($semester = mysqli_fetch_assoc($semesters_result)) {
    $semesters[] = $semester;
}

// Get all main categories that the teacher has evaluations for
$categories_query = "SELECT DISTINCT mec.id, mec.name, mec.evaluation_type, mec.description,
                           COUNT(es.id) as evaluation_count,
                           SUM(CASE WHEN es.status = 'completed' THEN 1 ELSE 0 END) as completed_count
                    FROM main_evaluation_categories mec
                    JOIN evaluation_sessions es ON mec.id = es.main_category_id
                    WHERE es.evaluatee_id = ?
                    GROUP BY mec.id, mec.name, mec.evaluation_type, mec.description
                    ORDER BY mec.name ASC";
$categories_stmt = mysqli_prepare($conn, $categories_query);
mysqli_stmt_bind_param($categories_stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($categories_stmt);
$categories_result = mysqli_stmt_get_result($categories_stmt);

$categories = [];
$first_category_id = null;
while ($category = mysqli_fetch_assoc($categories_result)) {
    $categories[] = $category;
    if ($first_category_id === null) {
        $first_category_id = $category['id'];
    }
}

// If no category is selected, select the first one
if (empty($selected_category) && !empty($categories)) {
    $selected_category = $first_category_id;
}

// Build query for evaluations where teacher is the evaluatee
$where_conditions = ["es.evaluatee_id = ?"];
$params = [$_SESSION['user_id']];
$param_types = 'i';

// Add category filter if selected
if ($selected_category) {
    $where_conditions[] = "es.main_category_id = ?";
    $params[] = $selected_category;
    $param_types .= 'i';
}

if ($search) {
    $where_conditions[] = "mec.name LIKE ?";
    $search_param = "%$search%";
    $params[] = $search_param;
    $param_types .= 's';
}

if ($status_filter) {
    $where_conditions[] = "es.status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

if ($type_filter) {
    $where_conditions[] = "mec.evaluation_type = ?";
    $params[] = $type_filter;
    $param_types .= 's';
}

if ($semester_filter) {
    $where_conditions[] = "es.semester_id = ?";
    $params[] = $semester_filter;
    $param_types .= 'i';
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM evaluation_sessions es
                JOIN main_evaluation_categories mec ON es.main_category_id = mec.id
                LEFT JOIN faculty f ON es.evaluator_id = f.id
                LEFT JOIN users u ON es.evaluator_id = u.id
                $where_clause";
$count_stmt = mysqli_prepare($conn, $count_query);
mysqli_stmt_bind_param($count_stmt, $param_types, ...$params);
mysqli_stmt_execute($count_stmt);
$count_result = mysqli_stmt_get_result($count_stmt);
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $per_page);

// Get evaluation results
$results_query = "SELECT es.*, mec.name as category_name, mec.evaluation_type,
                  COALESCE(f.first_name, u.first_name) as evaluator_first_name,
                  COALESCE(f.last_name, u.last_name) as evaluator_last_name,
                  COALESCE(f.email, u.email) as evaluator_email,
                  COALESCE('teacher', u.role) as evaluator_role,
                  COUNT(er.id) as response_count
                  FROM evaluation_sessions es
                  JOIN main_evaluation_categories mec ON es.main_category_id = mec.id
                  LEFT JOIN faculty f ON es.evaluator_id = f.id
                  LEFT JOIN users u ON es.evaluator_id = u.id
                  LEFT JOIN evaluation_responses er ON es.id = er.evaluation_session_id
                  $where_clause
                  GROUP BY es.id, es.evaluator_id, es.evaluatee_id, es.main_category_id, es.semester_id, es.subject_id, es.evaluation_date, es.status, es.notes, es.created_at, es.updated_at, mec.name, mec.evaluation_type, f.first_name, f.last_name, f.email, u.first_name, u.last_name, u.email, u.role
                  ORDER BY es.created_at DESC
                  LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$param_types .= 'ii';

$results_stmt = mysqli_prepare($conn, $results_query);
mysqli_stmt_bind_param($results_stmt, $param_types, ...$params);
mysqli_stmt_execute($results_stmt);
$results_result = mysqli_stmt_get_result($results_stmt);

// Get evaluation statistics
$stats_query = "SELECT
                COUNT(*) as total_evaluations,
                COALESCE(SUM(CASE WHEN es.status = 'completed' THEN 1 ELSE 0 END), 0) as completed_evaluations,
                COALESCE(SUM(CASE WHEN es.status = 'draft' THEN 1 ELSE 0 END), 0) as draft_evaluations,
                COALESCE(SUM(CASE WHEN mec.evaluation_type = 'peer_to_peer' THEN 1 ELSE 0 END), 0) as peer_evaluations,
                COALESCE(SUM(CASE WHEN mec.evaluation_type = 'head_to_teacher' THEN 1 ELSE 0 END), 0) as head_evaluations,
                COALESCE(SUM(CASE WHEN mec.evaluation_type = 'student_to_teacher' THEN 1 ELSE 0 END), 0) as student_evaluations,
                COALESCE(AVG(CASE WHEN es.status = 'completed' THEN (
                    SELECT AVG(er.rating_value)
                    FROM evaluation_responses er
                    WHERE er.evaluation_session_id = es.id
                    AND er.rating_value IS NOT NULL
                ) END), 0) as average_score,
                COALESCE(COUNT(CASE WHEN es.status = 'completed' THEN (
                    SELECT COUNT(*)
                    FROM evaluation_responses er
                    WHERE er.evaluation_session_id = es.id
                    AND er.rating_value IS NOT NULL
                ) END), 0) as total_rating_questions
                FROM evaluation_sessions es
                JOIN main_evaluation_categories mec ON es.main_category_id = mec.id
                WHERE es.evaluatee_id = ?";

$stats_params = [$_SESSION['user_id']];
$stats_param_types = 'i';

if ($semester_filter) {
    $stats_query .= " AND es.semester_id = ?";
    $stats_params[] = $semester_filter;
    $stats_param_types .= 'i';
}

$stats_stmt = mysqli_prepare($conn, $stats_query);
mysqli_stmt_bind_param($stats_stmt, $stats_param_types, ...$stats_params);
mysqli_stmt_execute($stats_stmt);
$stats_result = mysqli_stmt_get_result($stats_stmt);
$stats = mysqli_fetch_assoc($stats_result);

// Get sentiment analysis statistics
$sentiment_stats_query = "SELECT
                          COUNT(CASE WHEN er.text_response IS NOT NULL AND er.text_response != '' THEN 1 END) as total_comments,
                          COUNT(CASE WHEN er.rating_value = 5 THEN 1 END) as excellent_count,
                          COUNT(CASE WHEN er.rating_value = 4 THEN 1 END) as very_satisfactory_count,
                          COUNT(CASE WHEN er.rating_value = 3 THEN 1 END) as satisfactory_count,
                          COUNT(CASE WHEN er.rating_value = 2 THEN 1 END) as good_count,
                          COUNT(CASE WHEN er.rating_value = 1 THEN 1 END) as poor_count,
                          AVG(er.rating_value) as overall_average_rating
                          FROM evaluation_sessions es
                          JOIN evaluation_responses er ON es.id = er.evaluation_session_id
                          WHERE es.evaluatee_id = ? AND es.status = 'completed' AND er.rating_value IS NOT NULL";

$sentiment_params = [$_SESSION['user_id']];
$sentiment_param_types = 'i';

if ($semester_filter) {
    $sentiment_stats_query .= " AND es.semester_id = ?";
    $sentiment_params[] = $semester_filter;
    $sentiment_param_types .= 'i';
}

$sentiment_stmt = mysqli_prepare($conn, $sentiment_stats_query);
mysqli_stmt_bind_param($sentiment_stmt, $sentiment_param_types, ...$sentiment_params);
mysqli_stmt_execute($sentiment_stmt);
$sentiment_result = mysqli_stmt_get_result($sentiment_stmt);
$sentiment_stats = mysqli_fetch_assoc($sentiment_result);

// Calculate sentiment percentages
$total_ratings = $sentiment_stats['excellent_count'] + $sentiment_stats['very_satisfactory_count'] +
                 $sentiment_stats['satisfactory_count'] + $sentiment_stats['good_count'] + $sentiment_stats['poor_count'];

$excellent_percentage = $total_ratings > 0 ? round(($sentiment_stats['excellent_count'] / $total_ratings) * 100, 1) : 0;
$very_satisfactory_percentage = $total_ratings > 0 ? round(($sentiment_stats['very_satisfactory_count'] / $total_ratings) * 100, 1) : 0;
$satisfactory_percentage = $total_ratings > 0 ? round(($sentiment_stats['satisfactory_count'] / $total_ratings) * 100, 1) : 0;
$good_percentage = $total_ratings > 0 ? round(($sentiment_stats['good_count'] / $total_ratings) * 100, 1) : 0;
$poor_percentage = $total_ratings > 0 ? round(($sentiment_stats['poor_count'] / $total_ratings) * 100, 1) : 0;

// Include the shared header
$sidebar_context = 'main';
include 'includes/unified-header.php';
?>

<div class="mb-6 sm:mb-8">
    <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">My Evaluation Results</h1>
    <p class="text-sm sm:text-base text-gray-600">View your evaluation results organized by categories</p>
</div>

<!-- Display Messages -->
<?php if (isset($_SESSION['message']) && !empty($_SESSION['message'])): ?>
    <div class="mb-6 p-4 rounded-md <?php echo $_SESSION['message_type'] === 'error' ? 'bg-red-50 border border-red-200' : ($_SESSION['message_type'] === 'warning' ? 'bg-yellow-50 border border-yellow-200' : 'bg-green-50 border border-green-200'); ?>">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas <?php echo $_SESSION['message_type'] === 'error' ? 'fa-exclamation-circle text-red-400' : ($_SESSION['message_type'] === 'warning' ? 'fa-exclamation-triangle text-yellow-400' : 'fa-check-circle text-green-400'); ?>"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm <?php echo $_SESSION['message_type'] === 'error' ? 'text-red-800' : ($_SESSION['message_type'] === 'warning' ? 'text-yellow-800' : 'text-green-800'); ?>">
                    <?php echo htmlspecialchars($_SESSION['message']); ?>
                </p>
            </div>
        </div>
    </div>
    <?php
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
    ?>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4 sm:gap-6 mb-6 sm:mb-8">
    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-4 sm:p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-clipboard-check text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Total Evaluations</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['total_evaluations'] ?? 0); ?></dd>
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
                    <div class="w-8 h-8 bg-purple-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-users text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Peer Evaluations</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['peer_evaluations'] ?? 0); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-4 sm:p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-indigo-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-user-tie text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Head Evaluations</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['head_evaluations'] ?? 0); ?></dd>
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
                        <i class="fas fa-user-graduate text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Student Evaluations</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['student_evaluations'] ?? 0); ?></dd>
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
                        <i class="fas fa-edit text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Drafts</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['draft_evaluations'] ?? 0); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Enhanced Statistics Section -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
    <!-- Score Average Statistics -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200 bg-gradient-to-r from-green-500 to-green-600">
            <h2 class="text-base font-semibold text-white flex items-center">
                <i class="fas fa-chart-line mr-2"></i>Score Performance Overview
            </h2>
        </div>
        <div class="p-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Overall Average Score -->
                <div class="text-center">
                    <div class="text-3xl font-bold text-green-600 mb-1">
                        <?php echo number_format($sentiment_stats['overall_average_rating'] ?? 0, 2); ?>
                    </div>
                    <div class="text-xs text-gray-600">Overall Average Score</div>
                    <div class="text-xs text-gray-500">out of 5.00</div>
                </div>

                <!-- Total Rating Questions -->
                <div class="text-center">
                    <div class="text-3xl font-bold text-blue-600 mb-1">
                        <?php echo number_format($stats['total_rating_questions'] ?? 0); ?>
                    </div>
                    <div class="text-xs text-gray-600">Total Rating Questions</div>
                    <div class="text-xs text-gray-500">answered</div>
                </div>
            </div>

            <!-- Rating Distribution -->
            <div class="mt-4">
                <h3 class="text-sm font-medium text-gray-900 mb-3">Rating Distribution</h3>
                <div class="space-y-2">
                    <!-- Excellent (5) -->
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <span class="text-xs font-medium text-gray-700 w-20">Excellent (5)</span>
                            <div class="flex items-center ml-1">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star text-yellow-400 text-xs"></i>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <div class="flex items-center">
                            <div class="w-16 bg-gray-200 rounded-full h-1.5 mr-2">
                                <div class="bg-green-600 h-1.5 rounded-full" style="width: <?php echo $excellent_percentage; ?>%"></div>
                            </div>
                            <span class="text-xs font-medium text-gray-900 w-8"><?php echo $excellent_percentage; ?>%</span>
                            <span class="text-xs text-gray-500 ml-1">(<?php echo $sentiment_stats['excellent_count']; ?>)</span>
                        </div>
                    </div>

                    <!-- Very Satisfactory (4) -->
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <span class="text-xs font-medium text-gray-700 w-20">Very Satisfactory (4)</span>
                            <div class="flex items-center ml-1">
                                <?php for ($i = 1; $i <= 4; $i++): ?>
                                    <i class="fas fa-star text-yellow-400 text-xs"></i>
                                <?php endfor; ?>
                                <i class="fas fa-star text-gray-300 text-xs"></i>
                            </div>
                        </div>
                        <div class="flex items-center">
                            <div class="w-16 bg-gray-200 rounded-full h-1.5 mr-2">
                                <div class="bg-blue-600 h-1.5 rounded-full" style="width: <?php echo $very_satisfactory_percentage; ?>%"></div>
                            </div>
                            <span class="text-xs font-medium text-gray-900 w-8"><?php echo $very_satisfactory_percentage; ?>%</span>
                            <span class="text-xs text-gray-500 ml-1">(<?php echo $sentiment_stats['very_satisfactory_count']; ?>)</span>
                        </div>
                    </div>

                    <!-- Satisfactory (3) -->
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <span class="text-xs font-medium text-gray-700 w-20">Satisfactory (3)</span>
                            <div class="flex items-center ml-1">
                                <?php for ($i = 1; $i <= 3; $i++): ?>
                                    <i class="fas fa-star text-yellow-400 text-xs"></i>
                                <?php endfor; ?>
                                <?php for ($i = 1; $i <= 2; $i++): ?>
                                    <i class="fas fa-star text-gray-300 text-xs"></i>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <div class="flex items-center">
                            <div class="w-16 bg-gray-200 rounded-full h-1.5 mr-2">
                                <div class="bg-yellow-600 h-1.5 rounded-full" style="width: <?php echo $satisfactory_percentage; ?>%"></div>
                            </div>
                            <span class="text-xs font-medium text-gray-900 w-8"><?php echo $satisfactory_percentage; ?>%</span>
                            <span class="text-xs text-gray-500 ml-1">(<?php echo $sentiment_stats['satisfactory_count']; ?>)</span>
                        </div>
                    </div>

                    <!-- Good (2) -->
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <span class="text-xs font-medium text-gray-700 w-20">Good (2)</span>
                            <div class="flex items-center ml-1">
                                <?php for ($i = 1; $i <= 2; $i++): ?>
                                    <i class="fas fa-star text-yellow-400 text-xs"></i>
                                <?php endfor; ?>
                                <?php for ($i = 1; $i <= 3; $i++): ?>
                                    <i class="fas fa-star text-gray-300 text-xs"></i>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <div class="flex items-center">
                            <div class="w-16 bg-gray-200 rounded-full h-1.5 mr-2">
                                <div class="bg-orange-600 h-1.5 rounded-full" style="width: <?php echo $good_percentage; ?>%"></div>
                            </div>
                            <span class="text-xs font-medium text-gray-900 w-8"><?php echo $good_percentage; ?>%</span>
                            <span class="text-xs text-gray-500 ml-1">(<?php echo $sentiment_stats['good_count']; ?>)</span>
                        </div>
                    </div>

                    <!-- Poor (1) -->
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <span class="text-xs font-medium text-gray-700 w-20">Poor (1)</span>
                            <div class="flex items-center ml-1">
                                <i class="fas fa-star text-yellow-400 text-xs"></i>
                                <?php for ($i = 1; $i <= 4; $i++): ?>
                                    <i class="fas fa-star text-gray-300 text-xs"></i>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <div class="flex items-center">
                            <div class="w-16 bg-gray-200 rounded-full h-1.5 mr-2">
                                <div class="bg-red-600 h-1.5 rounded-full" style="width: <?php echo $poor_percentage; ?>%"></div>
                            </div>
                            <span class="text-xs font-medium text-gray-900 w-8"><?php echo $poor_percentage; ?>%</span>
                            <span class="text-xs text-gray-500 ml-1">(<?php echo $sentiment_stats['poor_count']; ?>)</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sentiment Analysis Statistics -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200 bg-gradient-to-r from-purple-500 to-purple-600">
            <h2 class="text-base font-semibold text-white flex items-center">
                <i class="fas fa-chart-pie mr-2"></i>Sentiment Analysis Overview
            </h2>
        </div>
        <div class="p-4">
            <!-- Overall Sentiment Summary -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
                <div class="text-center">
                    <div class="text-2xl font-bold text-green-600 mb-1">
                        <?php echo $excellent_percentage + $very_satisfactory_percentage; ?>%
                    </div>
                    <div class="text-xs text-gray-600">Positive Ratings</div>
                    <div class="text-xs text-gray-500">(4-5 stars)</div>
                </div>

                <div class="text-center">
                    <div class="text-2xl font-bold text-yellow-600 mb-1">
                        <?php echo $satisfactory_percentage; ?>%
                    </div>
                    <div class="text-xs text-gray-600">Neutral Ratings</div>
                    <div class="text-xs text-gray-500">(3 stars)</div>
                </div>

                <div class="text-center">
                    <div class="text-2xl font-bold text-red-600 mb-1">
                        <?php echo $good_percentage + $poor_percentage; ?>%
                    </div>
                    <div class="text-xs text-gray-600">Negative Ratings</div>
                    <div class="text-xs text-gray-500">(1-2 stars)</div>
                </div>
            </div>

            <!-- Sentiment Breakdown -->
            <div class="space-y-3">
                <h3 class="text-sm font-medium text-gray-900 mb-3">Detailed Sentiment Breakdown</h3>

                <!-- Positive Sentiment -->
                <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center">
                            <i class="fas fa-smile text-green-600 mr-2"></i>
                            <span class="text-xs font-medium text-green-800">Positive Sentiment</span>
                        </div>
                        <span class="text-xs font-bold text-green-800"><?php echo $excellent_percentage + $very_satisfactory_percentage; ?>%</span>
                    </div>
                    <div class="w-full bg-green-200 rounded-full h-1.5">
                        <div class="bg-green-600 h-1.5 rounded-full" style="width: <?php echo $excellent_percentage + $very_satisfactory_percentage; ?>%"></div>
                    </div>
                    <div class="flex justify-between text-xs text-green-600 mt-1">
                        <span>Excellent: <?php echo $excellent_percentage; ?>%</span>
                        <span>Very Satisfactory: <?php echo $very_satisfactory_percentage; ?>%</span>
                    </div>
                </div>

                <!-- Neutral Sentiment -->
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center">
                            <i class="fas fa-meh text-yellow-600 mr-2"></i>
                            <span class="text-xs font-medium text-yellow-800">Neutral Sentiment</span>
                        </div>
                        <span class="text-xs font-bold text-yellow-800"><?php echo $satisfactory_percentage; ?>%</span>
                    </div>
                    <div class="w-full bg-yellow-200 rounded-full h-1.5">
                        <div class="bg-yellow-600 h-1.5 rounded-full" style="width: <?php echo $satisfactory_percentage; ?>%"></div>
                    </div>
                    <div class="text-xs text-yellow-600 mt-1">
                        <span>Satisfactory: <?php echo $satisfactory_percentage; ?>%</span>
                    </div>
                </div>

                <!-- Negative Sentiment -->
                <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center">
                            <i class="fas fa-frown text-red-600 mr-2"></i>
                            <span class="text-xs font-medium text-red-800">Negative Sentiment</span>
                        </div>
                        <span class="text-xs font-bold text-red-800"><?php echo $good_percentage + $poor_percentage; ?>%</span>
                    </div>
                    <div class="w-full bg-red-200 rounded-full h-1.5">
                        <div class="bg-red-600 h-1.5 rounded-full" style="width: <?php echo $good_percentage + $poor_percentage; ?>%"></div>
                    </div>
                    <div class="flex justify-between text-xs text-red-600 mt-1">
                        <span>Good: <?php echo $good_percentage; ?>%</span>
                        <span>Poor: <?php echo $poor_percentage; ?>%</span>
                    </div>
                </div>
            </div>

            <!-- Performance Insights -->
            <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                <h4 class="text-xs font-medium text-blue-800 mb-2">Performance Insights:</h4>
                <ul class="text-xs text-blue-700 space-y-1">
                    <li>• <strong><?php echo $excellent_percentage + $very_satisfactory_percentage; ?>%</strong> of ratings are positive (4-5 stars)</li>
                    <li>• <strong><?php echo $satisfactory_percentage; ?>%</strong> of ratings are neutral (3 stars)</li>
                    <li>• <strong><?php echo $good_percentage + $poor_percentage; ?>%</strong> of ratings need improvement (1-2 stars)</li>
                    <li>• Overall average score: <strong><?php echo number_format($sentiment_stats['overall_average_rating'] ?? 0, 2); ?>/5.00</strong></li>
                    <?php if (($excellent_percentage + $very_satisfactory_percentage) >= 80): ?>
                        <li>• <span class="text-green-600 font-medium">Excellent performance! Keep up the great work!</span></li>
                    <?php elseif (($excellent_percentage + $very_satisfactory_percentage) >= 60): ?>
                        <li>• <span class="text-blue-600 font-medium">Good performance with room for improvement</span></li>
                    <?php else: ?>
                        <li>• <span class="text-orange-600 font-medium">Consider focusing on areas for improvement</span></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Performance Improvement Recommendations -->
<?php
// Get areas that need improvement based on low ratings
$improvement_areas_query = "SELECT esc.name as subcategory_name,
                                  esc.description as subcategory_description,
                                  AVG(er.rating_value) as average_rating,
                                  COUNT(er.id) as response_count
                           FROM evaluation_responses er
                           JOIN evaluation_questionnaires eq ON er.questionnaire_id = eq.id
                           JOIN evaluation_sub_categories esc ON eq.sub_category_id = esc.id
                           JOIN evaluation_sessions es ON er.evaluation_session_id = es.id
                           WHERE es.evaluatee_id = ?
                           AND es.status = 'completed'
                           AND er.rating_value IS NOT NULL
                           GROUP BY esc.id, esc.name, esc.description
                           HAVING AVG(er.rating_value) < 4.0
                           ORDER BY AVG(er.rating_value) ASC
                           LIMIT 3";

$improvement_areas_stmt = mysqli_prepare($conn, $improvement_areas_query);
mysqli_stmt_bind_param($improvement_areas_stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($improvement_areas_stmt);
$improvement_areas_result = mysqli_stmt_get_result($improvement_areas_stmt);

$improvement_areas = [];
while ($area = mysqli_fetch_assoc($improvement_areas_result)) {
    $improvement_areas[] = $area;
}
?>

<?php if (!empty($improvement_areas)): ?>
<div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
    <div class="px-4 py-3 border-b border-gray-200 bg-gradient-to-r from-orange-500 to-orange-600">
        <h2 class="text-base font-semibold text-white flex items-center">
            <i class="fas fa-lightbulb mr-2"></i>Areas for Improvement
        </h2>
    </div>
    <div class="p-4">
        <div class="mb-3">
            <p class="text-sm text-gray-600">
                Based on your evaluation results, consider focusing on these areas for professional growth:
            </p>
        </div>

        <div class="space-y-3">
            <?php foreach ($improvement_areas as $area): ?>
                <div class="border border-orange-200 rounded-lg p-3 bg-orange-50">
                    <div class="flex items-start justify-between mb-2">
                        <div class="flex-1">
                            <h3 class="text-sm font-medium text-orange-800 mb-1">
                                <?php echo htmlspecialchars($area['subcategory_name']); ?>
                            </h3>
                            <?php if ($area['subcategory_description']): ?>
                                <p class="text-xs text-orange-700 mb-2">
                                    <?php echo htmlspecialchars($area['subcategory_description']); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="text-right ml-3">
                            <div class="text-lg font-bold text-orange-600">
                                <?php echo number_format($area['average_rating'], 2); ?>
                            </div>
                            <div class="text-xs text-orange-600">Average Score</div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="flex items-center">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?php echo $i <= $area['average_rating'] ? 'text-yellow-400' : 'text-gray-300'; ?> text-xs mr-0.5"></i>
                                <?php endfor; ?>
                            </div>
                            <span class="text-xs text-orange-600 ml-2">
                                Based on <?php echo $area['response_count']; ?> responses
                            </span>
                        </div>

                        <div class="flex space-x-2">
                            <a href="trainings.php?category=<?php echo urlencode($area['subcategory_name']); ?>"
                               class="inline-flex items-center px-2 py-1 text-xs font-medium text-orange-700 bg-orange-100 border border-orange-200 rounded hover:bg-orange-200 transition">
                                <i class="fas fa-search mr-1"></i>
                                Find Trainings
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
            <h4 class="text-xs font-medium text-blue-800 mb-2">💡 Improvement Tips:</h4>
            <ul class="text-xs text-blue-700 space-y-1">
                <li>• Focus on one area at a time for better results</li>
                <li>• Attend relevant trainings and workshops</li>
                <li>• Seek feedback from colleagues and mentors</li>
                <li>• Practice new techniques in your daily work</li>
                <li>• Track your progress over time</li>
            </ul>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Suggested Trainings and Seminars -->
<div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
    <div class="px-4 py-3 border-b border-gray-200 bg-gradient-to-r from-teal-500 to-teal-600">
        <h2 class="text-base font-semibold text-white flex items-center">
            <i class="fas fa-graduation-cap mr-2"></i>Suggested Trainings & Seminars
        </h2>
    </div>
    <div class="p-4">
        <?php
        // Get suggested trainings based on evaluation performance
        $suggested_trainings_query = "SELECT DISTINCT ts.*,
                                            CASE
                                                WHEN ts.sub_category_id IS NOT NULL THEN esc.name
                                                ELSE 'General Professional Development'
                                            END as category_name
                                     FROM trainings_seminars ts
                                     LEFT JOIN evaluation_sub_categories esc ON ts.sub_category_id = esc.id
                                     WHERE ts.status = 'published'
                                     AND ts.start_date > NOW()
                                     AND (
                                         ts.sub_category_id IN (
                                             SELECT DISTINCT eq.sub_category_id
                                             FROM evaluation_responses er
                                             JOIN evaluation_questionnaires eq ON er.questionnaire_id = eq.id
                                             JOIN evaluation_sessions es ON er.evaluation_session_id = es.id
                                             WHERE es.evaluatee_id = ?
                                             AND es.status = 'completed'
                                             AND er.rating_value IS NOT NULL
                                             AND er.rating_value < 4.0
                                         )
                                         OR ts.sub_category_id IS NULL
                                     )
                                     ORDER BY ts.start_date ASC
                                     LIMIT 6";

        $suggested_trainings_stmt = mysqli_prepare($conn, $suggested_trainings_query);
        mysqli_stmt_bind_param($suggested_trainings_stmt, "i", $_SESSION['user_id']);
        mysqli_stmt_execute($suggested_trainings_stmt);
        $suggested_trainings_result = mysqli_stmt_get_result($suggested_trainings_stmt);

        $suggested_trainings = [];
        while ($training = mysqli_fetch_assoc($suggested_trainings_result)) {
            $suggested_trainings[] = $training;
        }

        // If no specific trainings found, get general trainings
        if (empty($suggested_trainings)) {
            $general_trainings_query = "SELECT ts.*, 'General Professional Development' as category_name
                                       FROM trainings_seminars ts
                                       WHERE ts.status = 'published'
                                       AND ts.start_date > NOW()
                                       ORDER BY ts.start_date ASC
                                       LIMIT 6";
            $general_trainings_stmt = mysqli_prepare($conn, $general_trainings_query);
            mysqli_stmt_execute($general_trainings_stmt);
            $general_trainings_result = mysqli_stmt_get_result($general_trainings_stmt);

            while ($training = mysqli_fetch_assoc($general_trainings_result)) {
                $suggested_trainings[] = $training;
            }
        }
        ?>

        <?php if (!empty($suggested_trainings)): ?>
            <div class="mb-4">
                <p class="text-sm text-gray-600 mb-3">
                    Based on your evaluation results, here are some relevant training opportunities to enhance your professional development:
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($suggested_trainings as $training): ?>
                    <div class="border border-gray-200 rounded-lg p-3 hover:shadow-md transition-shadow">
                        <div class="flex items-start justify-between mb-2">
                            <div class="flex-1">
                                <h3 class="text-sm font-medium text-gray-900 mb-1">
                                    <?php echo htmlspecialchars($training['title']); ?>
                                </h3>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-teal-100 text-teal-800">
                                    <?php echo htmlspecialchars($training['category_name']); ?>
                                </span>
                            </div>
                            <div class="ml-2">
                                <?php if ($training['cost'] > 0): ?>
                                    <span class="text-xs font-medium text-gray-900">₱<?php echo number_format($training['cost'], 2); ?></span>
                                <?php else: ?>
                                    <span class="text-xs font-medium text-green-600">Free</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($training['description']): ?>
                            <p class="text-xs text-gray-600 mb-3 line-clamp-2">
                                <?php echo htmlspecialchars(substr($training['description'], 0, 100)); ?>
                                <?php if (strlen($training['description']) > 100): ?>...<?php endif; ?>
                            </p>
                        <?php endif; ?>

                        <div class="grid grid-cols-2 gap-2 mb-3 text-xs text-gray-500">
                            <?php if ($training['duration_hours']): ?>
                                <div class="flex items-center">
                                    <i class="fas fa-clock mr-1"></i>
                                    <span><?php echo $training['duration_hours']; ?> hours</span>
                                </div>
                            <?php endif; ?>

                            <div class="flex items-center">
                                <i class="fas fa-calendar-alt mr-1"></i>
                                <span><?php echo date('M d, Y', strtotime($training['start_date'])); ?></span>
                            </div>

                            <?php if ($training['venue']): ?>
                                <div class="flex items-center col-span-2">
                                    <i class="fas fa-map-marker-alt mr-1"></i>
                                    <span class="truncate"><?php echo htmlspecialchars($training['venue']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                <?php echo $training['type'] === 'workshop' ? 'bg-blue-100 text-blue-800' :
                                    ($training['type'] === 'seminar' ? 'bg-purple-100 text-purple-800' : 'bg-gray-100 text-gray-800'); ?>">
                                <i class="fas
                                    <?php echo $training['type'] === 'workshop' ? 'fa-tools' :
                                        ($training['type'] === 'seminar' ? 'fa-chalkboard-teacher' : 'fa-graduation-cap'); ?>
                                    mr-1"></i>
                                <?php echo ucfirst($training['type']); ?>
                            </span>

                            <a href="view-training.php?id=<?php echo $training['id']; ?>"
                               class="inline-flex items-center px-3 py-1 text-xs font-medium text-teal-700 bg-teal-100 border border-teal-200 rounded hover:bg-teal-200 transition">
                                <i class="fas fa-external-link-alt mr-1"></i>
                                View Details
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-4 text-center">
                <a href="trainings.php" class="inline-flex items-center px-4 py-2 text-sm font-medium text-teal-700 bg-teal-100 border border-teal-200 rounded-lg hover:bg-teal-200 transition">
                    <i class="fas fa-list mr-2"></i>
                    View All Available Trainings
                </a>
            </div>

        <?php else: ?>
            <div class="text-center py-6">
                <i class="fas fa-graduation-cap text-gray-300 text-3xl mb-3"></i>
                <p class="text-gray-500 text-sm">No training opportunities are currently available.</p>
                <p class="text-gray-400 text-xs mt-1">Check back later for new professional development opportunities.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Category-Specific Statistics -->
<?php if (!empty($categories)): ?>
<div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
    <div class="px-4 py-3 border-b border-gray-200 bg-gradient-to-r from-indigo-500 to-indigo-600">
        <h2 class="text-base font-semibold text-white flex items-center">
            <i class="fas fa-chart-bar mr-2"></i>Category-Specific Performance Statistics
        </h2>
    </div>
    <div class="p-4">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($categories as $category): ?>
                <?php
                // Get category-specific statistics
                $category_stats_query = "SELECT
                                        COUNT(*) as total_evaluations,
                                        COALESCE(SUM(CASE WHEN es.status = 'completed' THEN 1 ELSE 0 END), 0) as completed_evaluations,
                                        COALESCE(AVG(CASE WHEN es.status = 'completed' THEN (
                                            SELECT AVG(er.rating_value)
                                            FROM evaluation_responses er
                                            WHERE er.evaluation_session_id = es.id
                                            AND er.rating_value IS NOT NULL
                                        ) END), 0) as average_score,
                                        COALESCE(COUNT(CASE WHEN es.status = 'completed' THEN (
                                            SELECT COUNT(*)
                                            FROM evaluation_responses er
                                            WHERE er.evaluation_session_id = es.id
                                            AND er.rating_value IS NOT NULL
                                        ) END), 0) as total_rating_questions,
                                        COALESCE(COUNT(CASE WHEN es.status = 'completed' THEN (
                                            SELECT COUNT(*)
                                            FROM evaluation_responses er
                                            WHERE er.evaluation_session_id = es.id
                                            AND er.rating_value = 5
                                        ) END), 0) as excellent_count,
                                        COALESCE(COUNT(CASE WHEN es.status = 'completed' THEN (
                                            SELECT COUNT(*)
                                            FROM evaluation_responses er
                                            WHERE er.evaluation_session_id = es.id
                                            AND er.rating_value = 4
                                        ) END), 0) as very_satisfactory_count,
                                        COALESCE(COUNT(CASE WHEN es.status = 'completed' THEN (
                                            SELECT COUNT(*)
                                            FROM evaluation_responses er
                                            WHERE er.evaluation_session_id = es.id
                                            AND er.rating_value = 3
                                        ) END), 0) as satisfactory_count,
                                        COALESCE(COUNT(CASE WHEN es.status = 'completed' THEN (
                                            SELECT COUNT(*)
                                            FROM evaluation_responses er
                                            WHERE er.evaluation_session_id = es.id
                                            AND er.rating_value = 2
                                        ) END), 0) as good_count,
                                        COALESCE(COUNT(CASE WHEN es.status = 'completed' THEN (
                                            SELECT COUNT(*)
                                            FROM evaluation_responses er
                                            WHERE er.evaluation_session_id = es.id
                                            AND er.rating_value = 1
                                        ) END), 0) as poor_count
                                        FROM evaluation_sessions es
                                        WHERE es.evaluatee_id = ? AND es.main_category_id = ?";

                $category_stats_stmt = mysqli_prepare($conn, $category_stats_query);
                mysqli_stmt_bind_param($category_stats_stmt, "ii", $_SESSION['user_id'], $category['id']);
                mysqli_stmt_execute($category_stats_stmt);
                $category_stats_result = mysqli_stmt_get_result($category_stats_stmt);
                $category_stats = mysqli_fetch_assoc($category_stats_result);

                // Calculate category percentages
                $category_total_ratings = $category_stats['excellent_count'] + $category_stats['very_satisfactory_count'] +
                                         $category_stats['satisfactory_count'] + $category_stats['good_count'] + $category_stats['poor_count'];

                $category_excellent_percentage = $category_total_ratings > 0 ? round(($category_stats['excellent_count'] / $category_total_ratings) * 100, 1) : 0;
                $category_very_satisfactory_percentage = $category_total_ratings > 0 ? round(($category_stats['very_satisfactory_count'] / $category_total_ratings) * 100, 1) : 0;
                $category_satisfactory_percentage = $category_total_ratings > 0 ? round(($category_stats['satisfactory_count'] / $category_total_ratings) * 100, 1) : 0;
                $category_good_percentage = $category_total_ratings > 0 ? round(($category_stats['good_count'] / $category_total_ratings) * 100, 1) : 0;
                $category_poor_percentage = $category_total_ratings > 0 ? round(($category_stats['poor_count'] / $category_total_ratings) * 100, 1) : 0;

                $category_positive_percentage = $category_excellent_percentage + $category_very_satisfactory_percentage;
                ?>

                <div class="border border-gray-200 rounded-lg p-3">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($category['name']); ?></h3>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                            <?php
                            switch($category['evaluation_type']) {
                                case 'student_to_teacher':
                                    echo 'bg-orange-100 text-orange-800';
                                    break;
                                case 'peer_to_peer':
                                    echo 'bg-purple-100 text-purple-800';
                                    break;
                                case 'head_to_teacher':
                                    echo 'bg-indigo-100 text-indigo-800';
                                    break;
                            }
                            ?>">
                            <?php echo ucwords(str_replace('_', ' ', $category['evaluation_type'])); ?>
                        </span>
                    </div>

                    <!-- Category Overview -->
                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <div class="text-center">
                            <div class="text-xl font-bold <?php echo $category_stats['average_score'] >= 4.0 ? 'text-green-600' : ($category_stats['average_score'] >= 3.0 ? 'text-yellow-600' : 'text-red-600'); ?>">
                                <?php echo number_format($category_stats['average_score'], 2); ?>
                            </div>
                            <div class="text-xs text-gray-600">Average Score</div>
                        </div>
                        <div class="text-center">
                            <div class="text-xl font-bold text-blue-600">
                                <?php echo $category_stats['completed_evaluations']; ?>
                            </div>
                            <div class="text-xs text-gray-600">Completed</div>
                        </div>
                    </div>

                    <!-- Sentiment Summary -->
                    <div class="mb-3">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-medium text-gray-700">Positive (4-5★)</span>
                            <span class="text-xs font-bold text-green-600"><?php echo $category_positive_percentage; ?>%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-1.5">
                            <div class="bg-green-600 h-1.5 rounded-full" style="width: <?php echo $category_positive_percentage; ?>%"></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-medium text-gray-700">Neutral (3★)</span>
                            <span class="text-xs font-bold text-yellow-600"><?php echo $category_satisfactory_percentage; ?>%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-1.5">
                            <div class="bg-yellow-600 h-1.5 rounded-full" style="width: <?php echo $category_satisfactory_percentage; ?>%"></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-medium text-gray-700">Negative (1-2★)</span>
                            <span class="text-xs font-bold text-red-600"><?php echo $category_good_percentage + $category_poor_percentage; ?>%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-1.5">
                            <div class="bg-red-600 h-1.5 rounded-full" style="width: <?php echo $category_good_percentage + $category_poor_percentage; ?>%"></div>
                        </div>
                    </div>

                    <!-- Performance Indicator -->
                    <div class="text-center">
                        <?php if ($category_positive_percentage >= 80): ?>
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                <i class="fas fa-star mr-1"></i>Excellent
                            </span>
                        <?php elseif ($category_positive_percentage >= 60): ?>
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                <i class="fas fa-thumbs-up mr-1"></i>Good
                            </span>
                        <?php elseif ($category_positive_percentage >= 40): ?>
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                <i class="fas fa-meh mr-1"></i>Average
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                <i class="fas fa-exclamation-triangle mr-1"></i>Needs Improvement
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Category Tabs -->
<?php
// Define $current_category outside of the conditional block so it's always available
$current_category = null;
if (!empty($categories)) {
    foreach ($categories as $category) {
        if ($category['id'] == $selected_category) {
            $current_category = $category;
            break;
        }
    }
}
?>

<?php if (!empty($categories)): ?>
<div class="mb-6 bg-white rounded-lg shadow-md overflow-hidden">
    <div class="border-b border-gray-200">
        <nav class="-mb-px flex space-x-4 overflow-x-auto px-6 py-4" aria-label="Tabs">
            <?php foreach ($categories as $category): ?>
                <button onclick="switchCategory(<?php echo $category['id']; ?>)"
                   class="category-tab inline-flex items-center px-4 py-2 rounded-full text-sm font-medium transition-colors duration-200 <?php echo $selected_category == $category['id'] ? 'bg-seait-orange text-white shadow-md' : 'bg-gray-100 text-black hover:bg-gray-200'; ?>"
                   id="tab-<?php echo $category['id']; ?>"
                   title="<?php echo htmlspecialchars($category['name']); ?>">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium mr-2
                        <?php
                        if ($selected_category == $category['id']) {
                            echo 'text-white';
                        } else {
                            echo 'text-black';
                        }
                        ?>">
                        <i class="fas
                            <?php
                            switch($category['evaluation_type']) {
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
                            ?> mr-1"></i>
                        <?php echo ucwords(str_replace('_', ' ', $category['evaluation_type'])); ?>
                    </span>
                    <span class="<?php echo $selected_category == $category['id'] ? 'bg-white text-gray-700' : 'bg-gray-200 text-gray-700'; ?> px-2 py-1 rounded-full text-xs font-bold">
                        <?php echo $category['evaluation_count']; ?>
                    </span>
                </button>
            <?php endforeach; ?>
        </nav>
    </div>

    <!-- Category Description -->
    <?php if ($current_category && $current_category['description']): ?>
    <div class="px-6 py-4 bg-white">
        <div class="flex items-center mb-2">
            <h3 class="text-lg font-medium text-gray-900"><?php echo htmlspecialchars($current_category['name']); ?></h3>
        </div>
        <p class="text-sm text-gray-700"><?php echo htmlspecialchars($current_category['description']); ?></p>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Search and Filter -->
<div class="mb-6 bg-white p-4 rounded-lg shadow-md">
    <form method="GET" class="grid grid-cols-1 sm:grid-cols-5 gap-4">
        <?php if ($selected_category): ?>
        <input type="hidden" name="category" value="<?php echo htmlspecialchars($selected_category); ?>">
        <?php endif; ?>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                   placeholder="Search by category"
                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                <option value="">All Status</option>
                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
            <select name="type" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                <option value="">All Types</option>
                <option value="student_to_teacher" <?php echo $type_filter === 'student_to_teacher' ? 'selected' : ''; ?>>Student to Teacher</option>
                <option value="peer_to_peer" <?php echo $type_filter === 'peer_to_peer' ? 'selected' : ''; ?>>Peer to Peer</option>
                <option value="head_to_teacher" <?php echo $type_filter === 'head_to_teacher' ? 'selected' : ''; ?>>Head to Teacher</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Semester</label>
            <select name="semester" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                <option value="">All Semesters</option>
                <?php foreach ($semesters as $semester): ?>
                <option value="<?php echo $semester['id']; ?>" <?php echo $semester_filter == $semester['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($semester['name'] . ' (' . $semester['academic_year'] . ')'); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex items-end">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">
                <i class="fas fa-search mr-2"></i>Search
            </button>
            <?php if ($search || $status_filter || $type_filter || $semester_filter): ?>
            <a href="evaluation-results.php<?php echo $selected_category ? '?category=' . $selected_category : ''; ?>" class="ml-2 bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition">
                <i class="fas fa-times mr-2"></i>Clear
            </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Evaluation Results Table -->
<?php include 'evaluation-results-table.php'; ?>

                                <?php
// Include the shared footer
include 'includes/footer.php';
?>

<script>
// Function to switch categories without page reload
function switchCategory(categoryId) {
    // Update URL parameters without page reload
    const url = new URL(window.location);
    url.searchParams.set('category', categoryId);

    // Preserve other search parameters
    const search = document.querySelector('input[name="search"]').value;
    const status = document.querySelector('select[name="status"]').value;
    const type = document.querySelector('select[name="type"]').value;
    const semester = document.querySelector('select[name="semester"]').value;

    if (search) url.searchParams.set('search', search);
    if (status) url.searchParams.set('status', status);
    if (type) url.searchParams.set('type', type);
    if (semester) url.searchParams.set('semester', semester);

    // Remove page parameter when switching categories
    url.searchParams.delete('page');

    // Update browser history without reloading
    window.history.pushState({}, '', url);

    // Update tab appearance
    updateTabAppearance(categoryId);

    // Reload the page content via AJAX
    loadCategoryContent(categoryId);
}

// Function to update tab appearance
function updateTabAppearance(selectedCategoryId) {
    // Remove active state from all tabs
    document.querySelectorAll('.category-tab').forEach(tab => {
        tab.classList.remove('bg-seait-orange', 'text-white', 'shadow-md');
        tab.classList.add('bg-gray-100', 'text-black');

        // Update the badge colors
        const badge = tab.querySelector('span:last-child');
        if (badge) {
            badge.classList.remove('bg-white', 'text-gray-700');
            badge.classList.add('bg-gray-200', 'text-gray-700');
        }

        // Update the type badge colors
        const typeBadge = tab.querySelector('span:first-child');
        if (typeBadge) {
            typeBadge.classList.remove('text-white');
            typeBadge.classList.add('text-black');
        }
    });

    // Add active state to selected tab
    const selectedTab = document.getElementById('tab-' + selectedCategoryId);
    if (selectedTab) {
        selectedTab.classList.remove('bg-gray-100', 'text-black');
        selectedTab.classList.add('bg-seait-orange', 'text-white', 'shadow-md');

        // Update the badge colors
        const badge = selectedTab.querySelector('span:last-child');
        if (badge) {
            badge.classList.remove('bg-gray-200', 'text-gray-700');
            badge.classList.add('bg-white', 'text-gray-700');
        }

        // Update the type badge colors
        const typeBadge = selectedTab.querySelector('span:first-child');
        if (typeBadge) {
            typeBadge.classList.remove('text-black');
            typeBadge.classList.add('text-white');
        }
    }
}

// Function to load category content via AJAX
function loadCategoryContent(categoryId) {
    // Show loading state
    const resultsContainer = document.getElementById('evaluation-results-container');
    if (resultsContainer) {
        resultsContainer.innerHTML = '<div class="p-6 text-center"><i class="fas fa-spinner fa-spin text-gray-400 text-2xl mb-2"></i><p class="text-gray-500">Loading...</p></div>';
    }

    // Build the AJAX URL
    const url = new URL(window.location);
    url.searchParams.set('category', categoryId);
    url.searchParams.set('ajax', '1'); // Add AJAX parameter

    // Make AJAX request
    fetch(url.toString())
        .then(response => response.text())
        .then(html => {
            // Extract the results table from the response
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newResultsTable = doc.querySelector('#evaluation-results-container');

            if (newResultsTable && resultsContainer) {
                resultsContainer.innerHTML = newResultsTable.innerHTML;
            }
        })
        .catch(error => {
            console.error('Error loading category content:', error);
            // Fallback: reload the page
            window.location.reload();
        });
}

// Handle browser back/forward buttons
window.addEventListener('popstate', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const category = urlParams.get('category');
    if (category) {
        updateTabAppearance(category);
        loadCategoryContent(category);
    }
});

// Initialize tabs on page load
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const category = urlParams.get('category');
    if (category) {
        updateTabAppearance(category);
    }
});
</script>