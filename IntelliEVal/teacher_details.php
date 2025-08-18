<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Helper function to format position/department names
function formatDisplayName($name) {
    if (empty($name)) return '';
    // Replace underscores with spaces and capitalize words
    return ucwords(str_replace('_', ' ', $name));
}

// Check if user is logged in and has guidance_officer role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guidance_officer') {
    header('Location: ../login.php');
    exit();
}

// Get teacher ID from URL
$teacher_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($teacher_id <= 0) {
    header('Location: teachers.php');
    exit();
}

// Get teacher information
$teacher_query = "SELECT
    f.id,
    f.first_name,
    f.last_name,
    f.email,
    f.position,
    f.department,
    f.bio,
    f.is_active,
    f.created_at,
    u.username,
    u.status
FROM faculty f
LEFT JOIN users u ON f.email = u.email
WHERE f.id = ?";

$stmt = mysqli_prepare($conn, $teacher_query);
mysqli_stmt_bind_param($stmt, "i", $teacher_id);
mysqli_stmt_execute($stmt);
$teacher_result = mysqli_stmt_get_result($stmt);
$teacher = mysqli_fetch_assoc($teacher_result);

if (!$teacher) {
    header('Location: teachers.php');
    exit();
}

// Get teacher's evaluation statistics
$evaluation_stats_query = "SELECT
    COUNT(DISTINCT es.id) as total_evaluations,
    COUNT(CASE WHEN es.status = 'completed' THEN 1 END) as completed_evaluations,
    COUNT(CASE WHEN es.status = 'draft' THEN 1 END) as draft_evaluations,
    COUNT(CASE WHEN es.status = 'cancelled' THEN 1 END) as cancelled_evaluations,
    (SELECT AVG(er.rating_value) FROM evaluation_responses er
     JOIN evaluation_sessions es2 ON er.evaluation_session_id = es2.id
     WHERE es2.evaluatee_id = es.evaluatee_id AND es2.evaluatee_type = 'teacher') as avg_rating,
    (SELECT COUNT(er.id) FROM evaluation_responses er
     JOIN evaluation_sessions es2 ON er.evaluation_session_id = es2.id
     WHERE es2.evaluatee_id = es.evaluatee_id AND es2.evaluatee_type = 'teacher') as total_responses,
    (SELECT COUNT(CASE WHEN er.rating_value = 5 THEN 1 END) FROM evaluation_responses er
     JOIN evaluation_sessions es2 ON er.evaluation_session_id = es2.id
     WHERE es2.evaluatee_id = es.evaluatee_id AND es2.evaluatee_type = 'teacher') as excellent_count,
    (SELECT COUNT(CASE WHEN er.rating_value = 4 THEN 1 END) FROM evaluation_responses er
     JOIN evaluation_sessions es2 ON er.evaluation_session_id = es2.id
     WHERE es2.evaluatee_id = es.evaluatee_id AND es2.evaluatee_type = 'teacher') as very_satisfactory_count,
    (SELECT COUNT(CASE WHEN er.rating_value = 3 THEN 1 END) FROM evaluation_responses er
     JOIN evaluation_sessions es2 ON er.evaluation_session_id = es2.id
     WHERE es2.evaluatee_id = es.evaluatee_id AND es2.evaluatee_type = 'teacher') as satisfactory_count,
    (SELECT COUNT(CASE WHEN er.rating_value = 2 THEN 1 END) FROM evaluation_responses er
     JOIN evaluation_sessions es2 ON er.evaluation_session_id = es2.id
     WHERE es2.evaluatee_id = es.evaluatee_id AND es2.evaluatee_type = 'teacher') as good_count,
    (SELECT COUNT(CASE WHEN er.rating_value = 1 THEN 1 END) FROM evaluation_responses er
     JOIN evaluation_sessions es2 ON er.evaluation_session_id = es2.id
     WHERE es2.evaluatee_id = es.evaluatee_id AND es2.evaluatee_type = 'teacher') as poor_count,
    (SELECT COUNT(CASE WHEN er.text_response IS NOT NULL AND er.text_response != '' THEN 1 END) FROM evaluation_responses er
     JOIN evaluation_sessions es2 ON er.evaluation_session_id = es2.id
     WHERE es2.evaluatee_id = es.evaluatee_id AND es2.evaluatee_type = 'teacher') as text_responses_count
FROM evaluation_sessions es
WHERE es.evaluatee_id = ? AND es.evaluatee_type = 'teacher'";

$stmt = mysqli_prepare($conn, $evaluation_stats_query);
mysqli_stmt_bind_param($stmt, "i", $teacher_id);
mysqli_stmt_execute($stmt);
$evaluation_stats_result = mysqli_stmt_get_result($stmt);
$evaluation_stats = mysqli_fetch_assoc($evaluation_stats_result);

// Get teacher's evaluation history
$evaluation_history_query = "SELECT
    es.id,
    es.evaluation_date,
    es.status,
    es.created_at,
    s.name as semester_name,
    s.academic_year,
    sub.name as subject_name,
    mec.name as category_name,
    CASE
        WHEN es.evaluator_type = 'student' THEN CONCAT(evaluator_s.first_name, ' ', evaluator_s.last_name)
        WHEN es.evaluator_type = 'teacher' THEN CONCAT(evaluator_f.first_name, ' ', evaluator_f.last_name)
        WHEN es.evaluator_type = 'head' THEN CONCAT(evaluator_u.first_name, ' ', evaluator_u.last_name)
        ELSE 'Unknown Evaluator'
    END as evaluator_name,
    es.evaluator_type as evaluator_role,
    AVG(er.rating_value) as avg_rating,
    COUNT(er.id) as total_responses
FROM evaluation_sessions es
LEFT JOIN semesters s ON es.semester_id = s.id
LEFT JOIN subjects sub ON es.subject_id = sub.id
LEFT JOIN main_evaluation_categories mec ON es.main_category_id = mec.id
LEFT JOIN students evaluator_s ON es.evaluator_id = evaluator_s.id AND es.evaluator_type = 'student'
LEFT JOIN faculty evaluator_f ON es.evaluator_id = evaluator_f.id AND es.evaluator_type = 'teacher'
LEFT JOIN users evaluator_u ON es.evaluator_id = evaluator_u.id AND es.evaluator_type = 'head'
LEFT JOIN evaluation_responses er ON es.id = er.evaluation_session_id
WHERE es.evaluatee_id = ? AND es.evaluatee_type = 'teacher'
GROUP BY es.id
ORDER BY es.evaluation_date DESC, es.created_at DESC
LIMIT 20";

$stmt = mysqli_prepare($conn, $evaluation_history_query);
mysqli_stmt_bind_param($stmt, "i", $teacher_id);
mysqli_stmt_execute($stmt);
$evaluation_history_result = mysqli_stmt_get_result($stmt);

// Get teacher's performance by category
$category_performance_query = "SELECT
    mec.name as category_name,
    esc.name as subcategory_name,
    AVG(er.rating_value) as avg_rating,
    COUNT(DISTINCT es.id) as total_evaluations,
    COUNT(er.id) as total_responses,
    COUNT(CASE WHEN er.rating_value = 5 THEN 1 END) as excellent_count,
    COUNT(CASE WHEN er.rating_value = 4 THEN 1 END) as very_satisfactory_count,
    COUNT(CASE WHEN er.rating_value = 3 THEN 1 END) as satisfactory_count,
    COUNT(CASE WHEN er.rating_value = 2 THEN 1 END) as good_count,
    COUNT(CASE WHEN er.rating_value = 1 THEN 1 END) as poor_count
FROM evaluation_responses er
JOIN evaluation_sessions es ON er.evaluation_session_id = es.id
JOIN evaluation_questionnaires eq ON er.questionnaire_id = eq.id
JOIN evaluation_sub_categories esc ON eq.sub_category_id = esc.id
JOIN main_evaluation_categories mec ON esc.main_category_id = mec.id
WHERE es.evaluatee_id = ? AND es.evaluatee_type = 'teacher' AND er.rating_value IS NOT NULL
GROUP BY mec.id, mec.name, esc.id, esc.name
HAVING COUNT(DISTINCT es.id) >= 1
ORDER BY avg_rating DESC";

$stmt = mysqli_prepare($conn, $category_performance_query);
mysqli_stmt_bind_param($stmt, "i", $teacher_id);
mysqli_stmt_execute($stmt);
$category_performance_result = mysqli_stmt_get_result($stmt);

// Get teacher's subjects
$teacher_subjects_query = "SELECT DISTINCT
    s.name as subject_name,
    s.code as subject_code,
    COUNT(DISTINCT es.id) as evaluation_count
FROM evaluation_sessions es
LEFT JOIN subjects s ON es.subject_id = s.id
WHERE es.evaluatee_id = ? AND es.evaluatee_type = 'teacher' AND s.id IS NOT NULL
GROUP BY s.id, s.name, s.code
ORDER BY evaluation_count DESC";

$stmt = mysqli_prepare($conn, $teacher_subjects_query);
mysqli_stmt_bind_param($stmt, "i", $teacher_id);
mysqli_stmt_execute($stmt);
$teacher_subjects_result = mysqli_stmt_get_result($stmt);

// Get recent text feedback
$text_feedback_query = "SELECT
    er.text_response,
    er.created_at,
    es.evaluation_date,
    CASE
        WHEN es.evaluator_type = 'student' THEN CONCAT(evaluator_s.first_name, ' ', evaluator_s.last_name)
        WHEN es.evaluator_type = 'teacher' THEN CONCAT(evaluator_f.first_name, ' ', evaluator_f.last_name)
        WHEN es.evaluator_type = 'head' THEN CONCAT(evaluator_u.first_name, ' ', evaluator_u.last_name)
        ELSE 'Unknown Evaluator'
    END as evaluator_name,
    es.evaluator_type as evaluator_role
FROM evaluation_responses er
JOIN evaluation_sessions es ON er.evaluation_session_id = es.id
LEFT JOIN students evaluator_s ON es.evaluator_id = evaluator_s.id AND es.evaluator_type = 'student'
LEFT JOIN faculty evaluator_f ON es.evaluator_id = evaluator_f.id AND es.evaluator_type = 'teacher'
LEFT JOIN users evaluator_u ON es.evaluator_id = evaluator_u.id AND es.evaluator_type = 'head'
WHERE es.evaluatee_id = ? AND es.evaluatee_type = 'teacher'
AND er.text_response IS NOT NULL AND er.text_response != ''
ORDER BY er.created_at DESC
LIMIT 10";

$stmt = mysqli_prepare($conn, $text_feedback_query);
mysqli_stmt_bind_param($stmt, "i", $teacher_id);
mysqli_stmt_execute($stmt);
$text_feedback_result = mysqli_stmt_get_result($stmt);

// Set page title
$page_title = 'Teacher Details - ' . $teacher['first_name'] . ' ' . $teacher['last_name'];

// Include the shared header
include 'includes/header.php';
?>

<div class="mb-6 sm:mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">
                Teacher Details
            </h1>
            <p class="text-sm sm:text-base text-gray-600">
                Comprehensive information and performance analysis for <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
            </p>
        </div>
        <div class="flex items-center space-x-3">
            <a href="teachers.php" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to Teachers
            </a>
            <a href="reports.php?report_type=performance" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-seait-orange hover:bg-orange-600 transition">
                <i class="fas fa-chart-bar mr-2"></i>
                View Reports
            </a>
        </div>
    </div>
</div>

<!-- Teacher Information Card -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <div class="flex items-start justify-between">
        <div class="flex items-center">
            <div class="w-16 h-16 bg-seait-orange rounded-full flex items-center justify-center mr-4">
                <span class="text-white text-xl font-bold">
                    <?php echo strtoupper(substr($teacher['first_name'], 0, 1) . substr($teacher['last_name'], 0, 1)); ?>
                </span>
            </div>
            <div>
                <h2 class="text-xl font-bold text-gray-900">
                    <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                </h2>
                <p class="text-gray-600"><?php echo htmlspecialchars(formatDisplayName($teacher['position']) ?: 'Teacher'); ?></p>
                <p class="text-sm text-gray-500">
                    <?php echo htmlspecialchars(formatDisplayName($teacher['department']) ?: 'Department not specified'); ?>
                </p>
                <?php if (!empty($teacher['bio'])): ?>
                <p class="text-sm text-gray-600 mt-2"><?php echo htmlspecialchars($teacher['bio']); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="text-right">
            <span class="px-3 py-1 text-xs font-medium rounded-full <?php echo $teacher['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                <?php echo $teacher['is_active'] ? 'Active' : 'Inactive'; ?>
            </span>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div>
            <p class="text-sm font-medium text-gray-500">Email</p>
            <p class="text-sm text-gray-900"><?php echo htmlspecialchars($teacher['email']); ?></p>
        </div>
        <div>
            <p class="text-sm font-medium text-gray-500">Username</p>
            <p class="text-sm text-gray-900"><?php echo htmlspecialchars($teacher['username'] ?? 'N/A'); ?></p>
        </div>
        <div>
            <p class="text-sm font-medium text-gray-500">Member Since</p>
            <p class="text-sm text-gray-900"><?php echo date('M d, Y', strtotime($teacher['created_at'])); ?></p>
        </div>
    </div>
</div>

<!-- Performance Overview -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <!-- Overall Performance -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-star text-yellow-500 mr-2"></i>
            Overall Performance
        </h3>
        <div class="text-center">
            <div class="text-3xl font-bold text-gray-900 mb-2">
                <?php echo number_format($evaluation_stats['avg_rating'], 2); ?>/5.0
            </div>
            <div class="flex justify-center mb-4">
                <?php
                $rating = round($evaluation_stats['avg_rating']);
                for ($i = 1; $i <= 5; $i++) {
                    echo $i <= $rating ? '<i class="fas fa-star text-yellow-500"></i>' : '<i class="far fa-star text-gray-300"></i>';
                }
                ?>
            </div>
            <p class="text-sm text-gray-600">
                Based on <?php echo number_format($evaluation_stats['total_responses']); ?> responses
            </p>
        </div>
    </div>

    <!-- Evaluation Statistics -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-chart-bar text-blue-500 mr-2"></i>
            Evaluation Statistics
        </h3>
        <div class="space-y-3">
            <div class="flex justify-between">
                <span class="text-sm text-gray-600">Total Evaluations</span>
                <span class="text-sm font-medium"><?php echo number_format($evaluation_stats['total_evaluations']); ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-gray-600">Completed</span>
                <span class="text-sm font-medium text-green-600"><?php echo number_format($evaluation_stats['completed_evaluations']); ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-gray-600">Draft</span>
                <span class="text-sm font-medium text-yellow-600"><?php echo number_format($evaluation_stats['draft_evaluations']); ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-gray-600">Cancelled</span>
                <span class="text-sm font-medium text-red-600"><?php echo number_format($evaluation_stats['cancelled_evaluations']); ?></span>
            </div>
        </div>
    </div>

    <!-- Rating Distribution -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
            <i class="fas fa-chart-pie text-purple-500 mr-2"></i>
            Rating Distribution
        </h3>
        <div class="space-y-2">
            <?php
            $total = $evaluation_stats['total_responses'];
            $ratings = [
                5 => ['count' => $evaluation_stats['excellent_count'], 'label' => 'Excellent', 'color' => 'bg-green-500'],
                4 => ['count' => $evaluation_stats['very_satisfactory_count'], 'label' => 'Very Satisfactory', 'color' => 'bg-blue-500'],
                3 => ['count' => $evaluation_stats['satisfactory_count'], 'label' => 'Satisfactory', 'color' => 'bg-yellow-500'],
                2 => ['count' => $evaluation_stats['good_count'], 'label' => 'Good', 'color' => 'bg-orange-500'],
                1 => ['count' => $evaluation_stats['poor_count'], 'label' => 'Poor', 'color' => 'bg-red-500']
            ];

            foreach ($ratings as $rating => $data):
                $percentage = $total > 0 ? ($data['count'] / $total) * 100 : 0;
            ?>
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="w-3 h-3 <?php echo $data['color']; ?> rounded-full mr-2"></div>
                    <span class="text-sm text-gray-600"><?php echo $data['label']; ?></span>
                </div>
                <div class="flex items-center">
                    <div class="w-16 bg-gray-200 rounded-full h-2 mr-2">
                        <div class="<?php echo $data['color']; ?> h-2 rounded-full" style="width: <?php echo $percentage; ?>%"></div>
                    </div>
                    <span class="text-sm font-medium"><?php echo number_format($percentage, 1); ?>%</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Performance by Category -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">
        <i class="fas fa-list-alt text-green-500 mr-2"></i>
        Performance by Category
    </h3>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subcategory</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Rating</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Evaluations</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Performance</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php while ($category = mysqli_fetch_assoc($category_performance_result)):
                    $excellent_pct = ($category['total_responses'] > 0) ? ($category['excellent_count'] / $category['total_responses']) * 100 : 0;
                    $very_satisfactory_pct = ($category['total_responses'] > 0) ? ($category['very_satisfactory_count'] / $category['total_responses']) * 100 : 0;
                    $satisfactory_pct = ($category['total_responses'] > 0) ? ($category['satisfactory_count'] / $category['total_responses']) * 100 : 0;
                    $good_pct = ($category['total_responses'] > 0) ? ($category['good_count'] / $category['total_responses']) * 100 : 0;
                    $poor_pct = ($category['total_responses'] > 0) ? ($category['poor_count'] / $category['total_responses']) * 100 : 0;

                    $performance_class = $category['avg_rating'] >= 4.5 ? 'bg-green-100 text-green-800' :
                                       ($category['avg_rating'] >= 4.0 ? 'bg-yellow-100 text-yellow-800' :
                                       ($category['avg_rating'] >= 3.5 ? 'bg-orange-100 text-orange-800' : 'bg-red-100 text-red-800'));
                    $performance_text = $category['avg_rating'] >= 4.5 ? 'Excellent' :
                                      ($category['avg_rating'] >= 4.0 ? 'Good' :
                                      ($category['avg_rating'] >= 3.5 ? 'Satisfactory' : 'Needs Improvement'));
                ?>
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                        <?php echo htmlspecialchars($category['category_name']); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?php echo htmlspecialchars($category['subcategory_name']); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <span class="text-yellow-500 text-sm mr-2">
                                <?php echo str_repeat('★', round($category['avg_rating'])) . str_repeat('☆', 5 - round($category['avg_rating'])); ?>
                            </span>
                            <span class="font-semibold text-gray-900"><?php echo number_format($category['avg_rating'], 2); ?>/5.0</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?php echo number_format($category['total_evaluations']); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 py-1 text-xs rounded-full font-medium <?php echo $performance_class; ?>">
                            <?php echo $performance_text; ?>
                        </span>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Recent Evaluation History -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">
        <i class="fas fa-history text-blue-500 mr-2"></i>
        Recent Evaluation History
    </h3>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Evaluator</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rating</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php while ($evaluation = mysqli_fetch_assoc($evaluation_history_result)): ?>
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?php echo $evaluation['evaluation_date'] ? date('M d, Y', strtotime($evaluation['evaluation_date'])) : 'N/A'; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?php echo htmlspecialchars($evaluation['subject_name'] ?? 'N/A'); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?php echo htmlspecialchars($evaluation['category_name']); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <div>
                            <div class="font-medium"><?php echo htmlspecialchars($evaluation['evaluator_name'] ?? 'N/A'); ?></div>
                            <div class="text-xs text-gray-500"><?php echo ucfirst($evaluation['evaluator_role'] ?? 'Unknown'); ?></div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php if ($evaluation['avg_rating']): ?>
                        <div class="flex items-center">
                            <span class="text-yellow-500 text-sm mr-2">
                                <?php echo str_repeat('★', round($evaluation['avg_rating'])) . str_repeat('☆', 5 - round($evaluation['avg_rating'])); ?>
                            </span>
                            <span class="font-semibold text-gray-900"><?php echo number_format($evaluation['avg_rating'], 2); ?>/5.0</span>
                        </div>
                        <?php else: ?>
                        <span class="text-gray-400">N/A</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 py-1 text-xs rounded-full font-medium <?php
                            echo $evaluation['status'] === 'completed' ? 'bg-green-100 text-green-800' :
                                ($evaluation['status'] === 'draft' ? 'bg-yellow-100 text-yellow-800' :
                                'bg-red-100 text-red-800');
                        ?>">
                            <?php echo ucfirst($evaluation['status']); ?>
                        </span>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Teacher Subjects -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">
        <i class="fas fa-book text-indigo-500 mr-2"></i>
        Subjects Taught
    </h3>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php while ($subject = mysqli_fetch_assoc($teacher_subjects_result)): ?>
        <div class="border border-gray-200 rounded-lg p-4">
            <div class="flex items-center justify-between">
                <div>
                    <h4 class="font-medium text-gray-900"><?php echo htmlspecialchars($subject['subject_name']); ?></h4>
                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($subject['subject_code']); ?></p>
                </div>
                <div class="text-right">
                    <span class="text-sm font-medium text-gray-900"><?php echo number_format($subject['evaluation_count']); ?></span>
                    <p class="text-xs text-gray-500">evaluations</p>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
</div>

<!-- Recent Text Feedback -->
<?php if (mysqli_num_rows($text_feedback_result) > 0): ?>
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">
        <i class="fas fa-comments text-purple-500 mr-2"></i>
        Recent Text Feedback
    </h3>
    <div class="space-y-4">
        <?php while ($feedback = mysqli_fetch_assoc($text_feedback_result)): ?>
        <div class="border border-gray-200 rounded-lg p-4">
            <div class="flex items-start justify-between mb-2">
                <div>
                    <p class="font-medium text-gray-900"><?php echo htmlspecialchars($feedback['evaluator_name'] ?? 'Anonymous'); ?></p>
                    <p class="text-sm text-gray-500"><?php echo ucfirst($feedback['evaluator_role'] ?? 'Unknown'); ?></p>
                </div>
                <span class="text-xs text-gray-400">
                    <?php echo date('M d, Y', strtotime($feedback['created_at'])); ?>
                </span>
            </div>
            <p class="text-gray-700"><?php echo htmlspecialchars($feedback['text_response']); ?></p>
        </div>
        <?php endwhile; ?>
    </div>
</div>
<?php endif; ?>

<?php
// Include the shared footer
include 'includes/footer.php';
?>