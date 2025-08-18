<?php
/**
 * IntelliEVal - Database Evaluation Data Checker
 * Checks and displays existing evaluation data in the database
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has guidance_officer role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guidance_officer') {
    header('Location: ../login.php');
    exit();
}

// Include the shared header
include 'includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6 sm:mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">
                <i class="fas fa-database text-seait-orange mr-2"></i>
                Database Evaluation Data Checker
            </h2>
            <p class="text-sm sm:text-base text-gray-600">
                Check what evaluation data exists in the database for clustering analysis
            </p>
        </div>
        <div class="text-right">
            <p class="text-sm text-gray-500"><?php echo date('l, F d, Y'); ?></p>
            <p class="text-xs text-gray-400">Database Check: <?php echo date('g:i A'); ?></p>
        </div>
    </div>
</div>

<?php
// Check evaluation sessions
$sessions_query = "SELECT
                    COUNT(*) as total_sessions,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_sessions,
                    COUNT(CASE WHEN status = 'draft' THEN 1 END) as draft_sessions,
                    COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_sessions
                  FROM evaluation_sessions";
$sessions_result = mysqli_query($conn, $sessions_query);
$sessions_data = mysqli_fetch_assoc($sessions_result);

// Check evaluation responses
$responses_query = "SELECT
                     COUNT(*) as total_responses,
                     COUNT(CASE WHEN rating_value IS NOT NULL THEN 1 END) as rated_responses,
                     COUNT(CASE WHEN text_response IS NOT NULL AND text_response != '' THEN 1 END) as text_responses,
                     AVG(rating_value) as avg_rating
                   FROM evaluation_responses";
$responses_result = mysqli_query($conn, $responses_query);
$responses_data = mysqli_fetch_assoc($responses_result);

// Check teachers with evaluations
$teachers_query = "SELECT
                    COUNT(DISTINCT es.evaluatee_id) as teachers_with_evaluations,
                    COUNT(DISTINCT u.id) as total_teachers
                  FROM users u
                  LEFT JOIN evaluation_sessions es ON u.id = es.evaluatee_id AND es.status = 'completed'
                  WHERE u.role = 'teacher'";
$teachers_result = mysqli_query($conn, $teachers_query);
$teachers_data = mysqli_fetch_assoc($teachers_result);

// Check semesters
$semesters_query = "SELECT
                     COUNT(*) as total_semesters,
                     COUNT(CASE WHEN status = 'active' THEN 1 END) as active_semesters
                   FROM semesters";
$semesters_result = mysqli_query($conn, $semesters_query);
$semesters_data = mysqli_fetch_assoc($semesters_result);

// Check evaluation categories
$categories_query = "SELECT
                      COUNT(*) as total_categories,
                      COUNT(DISTINCT mec.id) as main_categories,
                      COUNT(DISTINCT esc.id) as sub_categories
                    FROM main_evaluation_categories mec
                    LEFT JOIN evaluation_sub_categories esc ON mec.id = esc.main_category_id";
$categories_result = mysqli_query($conn, $categories_query);
$categories_data = mysqli_fetch_assoc($categories_result);

// Check subjects
$subjects_query = "SELECT
                    COUNT(*) as total_subjects,
                    COUNT(DISTINCT s.id) as subjects_with_evaluations
                  FROM subjects s
                  LEFT JOIN evaluation_sessions es ON s.id = es.subject_id AND es.status = 'completed'";
$subjects_result = mysqli_query($conn, $subjects_query);
$subjects_data = mysqli_fetch_assoc($subjects_result);
?>

<!-- Database Overview -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
    <!-- Evaluation Sessions -->
    <div class="bg-white shadow rounded-lg p-6">
        <div class="flex items-center mb-4">
            <div class="w-10 h-10 bg-blue-500 rounded-lg flex items-center justify-center mr-3">
                <i class="fas fa-clipboard-check text-white"></i>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Evaluation Sessions</h3>
                <p class="text-sm text-gray-600">Total: <?php echo number_format($sessions_data['total_sessions']); ?></p>
            </div>
        </div>
        <div class="space-y-2">
            <div class="flex justify-between">
                <span class="text-sm text-gray-600">Completed:</span>
                <span class="text-sm font-semibold text-green-600"><?php echo number_format($sessions_data['completed_sessions']); ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-gray-600">Draft:</span>
                <span class="text-sm font-semibold text-yellow-600"><?php echo number_format($sessions_data['draft_sessions']); ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-gray-600">Cancelled:</span>
                <span class="text-sm font-semibold text-red-600"><?php echo number_format($sessions_data['cancelled_sessions']); ?></span>
            </div>
        </div>
    </div>

    <!-- Evaluation Responses -->
    <div class="bg-white shadow rounded-lg p-6">
        <div class="flex items-center mb-4">
            <div class="w-10 h-10 bg-green-500 rounded-lg flex items-center justify-center mr-3">
                <i class="fas fa-star text-white"></i>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Evaluation Responses</h3>
                <p class="text-sm text-gray-600">Total: <?php echo number_format($responses_data['total_responses']); ?></p>
            </div>
        </div>
        <div class="space-y-2">
            <div class="flex justify-between">
                <span class="text-sm text-gray-600">Rated Responses:</span>
                <span class="text-sm font-semibold text-green-600"><?php echo number_format($responses_data['rated_responses']); ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-gray-600">Text Responses:</span>
                <span class="text-sm font-semibold text-blue-600"><?php echo number_format($responses_data['text_responses']); ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-gray-600">Avg Rating:</span>
                <span class="text-sm font-semibold text-orange-600"><?php echo round($responses_data['avg_rating'], 2); ?>/5.0</span>
            </div>
        </div>
    </div>

    <!-- Teachers -->
    <div class="bg-white shadow rounded-lg p-6">
        <div class="flex items-center mb-4">
            <div class="w-10 h-10 bg-purple-500 rounded-lg flex items-center justify-center mr-3">
                <i class="fas fa-users text-white"></i>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Teachers</h3>
                <p class="text-sm text-gray-600">Total: <?php echo number_format($teachers_data['total_teachers']); ?></p>
            </div>
        </div>
        <div class="space-y-2">
            <div class="flex justify-between">
                <span class="text-sm text-gray-600">With Evaluations:</span>
                <span class="text-sm font-semibold text-green-600"><?php echo number_format($teachers_data['teachers_with_evaluations']); ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-gray-600">Without Evaluations:</span>
                <span class="text-sm font-semibold text-red-600"><?php echo number_format($teachers_data['total_teachers'] - $teachers_data['teachers_with_evaluations']); ?></span>
            </div>
        </div>
    </div>

    <!-- Semesters -->
    <div class="bg-white shadow rounded-lg p-6">
        <div class="flex items-center mb-4">
            <div class="w-10 h-10 bg-indigo-500 rounded-lg flex items-center justify-center mr-3">
                <i class="fas fa-calendar text-white"></i>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Semesters</h3>
                <p class="text-sm text-gray-600">Total: <?php echo number_format($semesters_data['total_semesters']); ?></p>
            </div>
        </div>
        <div class="space-y-2">
            <div class="flex justify-between">
                <span class="text-sm text-gray-600">Active:</span>
                <span class="text-sm font-semibold text-green-600"><?php echo number_format($semesters_data['active_semesters']); ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-gray-600">Inactive:</span>
                <span class="text-sm font-semibold text-gray-600"><?php echo number_format($semesters_data['total_semesters'] - $semesters_data['active_semesters']); ?></span>
            </div>
        </div>
    </div>

    <!-- Categories -->
    <div class="bg-white shadow rounded-lg p-6">
        <div class="flex items-center mb-4">
            <div class="w-10 h-10 bg-orange-500 rounded-lg flex items-center justify-center mr-3">
                <i class="fas fa-list-alt text-white"></i>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Categories</h3>
                <p class="text-sm text-gray-600">Total: <?php echo number_format($categories_data['total_categories']); ?></p>
            </div>
        </div>
        <div class="space-y-2">
            <div class="flex justify-between">
                <span class="text-sm text-gray-600">Main Categories:</span>
                <span class="text-sm font-semibold text-blue-600"><?php echo number_format($categories_data['main_categories']); ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-gray-600">Sub Categories:</span>
                <span class="text-sm font-semibold text-green-600"><?php echo number_format($categories_data['sub_categories']); ?></span>
            </div>
        </div>
    </div>

    <!-- Subjects -->
    <div class="bg-white shadow rounded-lg p-6">
        <div class="flex items-center mb-4">
            <div class="w-10 h-10 bg-teal-500 rounded-lg flex items-center justify-center mr-3">
                <i class="fas fa-book text-white"></i>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Subjects</h3>
                <p class="text-sm text-gray-600">Total: <?php echo number_format($subjects_data['total_subjects']); ?></p>
            </div>
        </div>
        <div class="space-y-2">
            <div class="flex justify-between">
                <span class="text-sm text-gray-600">With Evaluations:</span>
                <span class="text-sm font-semibold text-green-600"><?php echo number_format($subjects_data['subjects_with_evaluations']); ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-gray-600">Without Evaluations:</span>
                <span class="text-sm font-semibold text-red-600"><?php echo number_format($subjects_data['total_subjects'] - $subjects_data['subjects_with_evaluations']); ?></span>
            </div>
        </div>
    </div>
</div>

<?php
// Get detailed teacher evaluation data
$teacher_details_query = "SELECT
                          u.first_name,
                          u.last_name,
                          COUNT(es.id) as total_evaluations,
                          AVG(er.rating_value) as avg_rating,
                          COUNT(CASE WHEN er.rating_value = 5 THEN 1 END) as excellent_count,
                          COUNT(CASE WHEN er.rating_value = 4 THEN 1 END) as very_satisfactory_count,
                          COUNT(CASE WHEN er.rating_value = 3 THEN 1 END) as satisfactory_count,
                          COUNT(CASE WHEN er.rating_value = 2 THEN 1 END) as good_count,
                          COUNT(CASE WHEN er.rating_value = 1 THEN 1 END) as poor_count
                        FROM users u
                        LEFT JOIN evaluation_sessions es ON u.id = es.evaluatee_id AND es.status = 'completed'
                        LEFT JOIN evaluation_responses er ON es.id = er.evaluation_session_id AND er.rating_value IS NOT NULL
                        WHERE u.role = 'teacher'
                        GROUP BY u.id, u.first_name, u.last_name
                        HAVING total_evaluations > 0
                        ORDER BY total_evaluations DESC, avg_rating DESC
                        LIMIT 10";
$teacher_details_result = mysqli_query($conn, $teacher_details_query);
?>

<!-- Teacher Evaluation Details -->
<div class="bg-white shadow rounded-lg mb-8">
    <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Top Teachers by Evaluation Count</h3>
        <p class="text-sm text-gray-600">Teachers with the most completed evaluations</p>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teacher</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Evaluations</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Average Rating</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Excellent (5)</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Very Satisfactory (4)</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Satisfactory (3)</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Good (2)</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Poor (1)</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php while ($teacher = mysqli_fetch_assoc($teacher_details_result)): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                        <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?php echo number_format($teacher['total_evaluations']); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <span class="font-semibold"><?php echo round($teacher['avg_rating'], 2); ?>/5.0</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?php echo number_format($teacher['excellent_count']); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?php echo number_format($teacher['very_satisfactory_count']); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?php echo number_format($teacher['satisfactory_count']); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?php echo number_format($teacher['good_count']); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?php echo number_format($teacher['poor_count']); ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Action Buttons -->
<div class="flex flex-wrap gap-4 mb-6">
    <a href="dashboard.php" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition">
        <i class="fas fa-arrow-left mr-2"></i>
        Back to Dashboard
    </a>

    <a href="clustering_visualization.php?action=teacher_clusters" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-seait-orange hover:bg-orange-600 transition">
        <i class="fas fa-chart-pie mr-2"></i>
        Test Teacher Clustering
    </a>

    <a href="clustering_visualization.php?action=pattern_clusters" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 transition">
        <i class="fas fa-chart-line mr-2"></i>
        Test Pattern Clustering
    </a>

    <a href="clustering_visualization.php?action=department_clusters" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-purple-600 hover:bg-purple-700 transition">
        <i class="fas fa-building mr-2"></i>
        Test Department Clustering
    </a>
</div>

<?php
// Include the shared footer
include 'includes/footer.php';
?>