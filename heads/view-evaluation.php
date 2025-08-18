<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has head role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'head') {
    header('Location: ../index.php');
    exit();
}

// Set page title
$page_title = 'Teacher Evaluation Results';

$message = '';
$message_type = '';

// Check for session messages
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Get faculty ID from URL
$faculty_id = isset($_GET['faculty_id']) ? (int)$_GET['faculty_id'] : 0;

if (!$faculty_id) {
    $_SESSION['message'] = 'Invalid faculty ID provided.';
    $_SESSION['message_type'] = 'error';
    header('Location: teachers.php');
    exit();
}

// Get faculty member details
$teacher_query = "SELECT f.id as faculty_id, f.first_name, f.last_name, f.email, 'teacher' as role,
                        f.department, f.position, f.is_active as status
                 FROM faculty f
                 WHERE f.id = ? AND f.is_active = 1";
$teacher_stmt = mysqli_prepare($conn, $teacher_query);
mysqli_stmt_bind_param($teacher_stmt, "i", $faculty_id);
mysqli_stmt_execute($teacher_stmt);
$teacher_result = mysqli_stmt_get_result($teacher_stmt);
$teacher = mysqli_fetch_assoc($teacher_result);

if (!$teacher) {
    $_SESSION['message'] = 'Faculty member not found.';
    $_SESSION['message_type'] = 'error';
    header('Location: teachers.php');
    exit();
}

// Get head information to verify department access
$user_id = $_SESSION['user_id'];
$head_query = "SELECT h.* FROM heads h WHERE h.user_id = ?";
$head_stmt = mysqli_prepare($conn, $head_query);
mysqli_stmt_bind_param($head_stmt, "i", $user_id);
mysqli_stmt_execute($head_stmt);
$head_result = mysqli_stmt_get_result($head_stmt);
$head_info = mysqli_fetch_assoc($head_result);

// Check if head has access to this teacher's department
$head_department = $head_info['department'];
$teacher_department = $teacher['department'];

// Simple department matching - can be enhanced later
$has_access = false;
if ($head_department === $teacher_department) {
    $has_access = true;
} elseif (str_contains($head_department, 'Department of ') && str_contains($teacher_department, 'Department of ')) {
    $has_access = true;
} elseif (str_contains($head_department, 'College of ') && str_contains($teacher_department, 'College of ')) {
    $has_access = true;
}

if (!$has_access) {
    $_SESSION['message'] = 'You do not have access to view this teacher\'s evaluations.';
    $_SESSION['message_type'] = 'error';
    header('Location: teachers.php');
    exit();
}

// Get all semesters for this teacher
$semesters_query = "SELECT DISTINCT s.id, s.name, s.academic_year, s.start_date, s.end_date
                   FROM evaluation_sessions es
                   JOIN semesters s ON es.semester_id = s.id
                   WHERE es.evaluatee_id = ? AND es.status = 'completed'
                   ORDER BY s.start_date DESC";
$semesters_stmt = mysqli_prepare($conn, $semesters_query);
mysqli_stmt_bind_param($semesters_stmt, "i", $faculty_id);
mysqli_stmt_execute($semesters_stmt);
$semesters_result = mysqli_stmt_get_result($semesters_stmt);

$semesters = [];
while ($semester = mysqli_fetch_assoc($semesters_result)) {
    $semesters[] = $semester;
}

// If no semesters found, try alternative query structure
if (empty($semesters)) {
    $semesters_query = "SELECT DISTINCT s.id, s.name, s.academic_year, s.start_date, s.end_date
                       FROM evaluation_sessions es
                       JOIN semesters s ON es.evaluation_date BETWEEN s.start_date AND s.end_date
                       WHERE es.evaluatee_id = ? AND es.status = 'completed'
                       ORDER BY s.start_date DESC";
    $semesters_stmt = mysqli_prepare($conn, $semesters_query);
    mysqli_stmt_bind_param($semesters_stmt, "i", $faculty_id);
    mysqli_stmt_execute($semesters_stmt);
    $semesters_result = mysqli_stmt_get_result($semesters_stmt);

    while ($semester = mysqli_fetch_assoc($semesters_result)) {
        $semesters[] = $semester;
    }
}

// Get evaluation categories and their statistics
$categories_query = "SELECT
    mec.id as category_id,
    mec.name as category_name,
    mec.evaluation_type,
    mec.description as category_description,
    COUNT(DISTINCT es.id) as total_evaluations,
    AVG(er.rating_value) as average_rating,
    MIN(er.rating_value) as min_rating,
    MAX(er.rating_value) as max_rating,
    STDDEV(er.rating_value) as rating_stddev
FROM main_evaluation_categories mec
LEFT JOIN evaluation_sessions es ON es.main_category_id = mec.id AND es.evaluatee_id = ? AND es.status = 'completed'
LEFT JOIN evaluation_responses er ON es.id = er.evaluation_session_id AND er.rating_value IS NOT NULL
GROUP BY mec.id, mec.name, mec.evaluation_type, mec.description
ORDER BY mec.evaluation_type, mec.name";

$categories_stmt = mysqli_prepare($conn, $categories_query);
mysqli_stmt_bind_param($categories_stmt, "i", $faculty_id);
mysqli_stmt_execute($categories_stmt);
$categories_result = mysqli_stmt_get_result($categories_stmt);

$categories = [];
while ($category = mysqli_fetch_assoc($categories_result)) {
    $categories[] = $category;
}

// Get overall statistics
$overall_stats_query = "SELECT
    COUNT(DISTINCT es.id) as total_evaluations,
    AVG(er.rating_value) as overall_average,
    MIN(er.rating_value) as overall_min,
    MAX(er.rating_value) as overall_max,
    STDDEV(er.rating_value) as overall_stddev,
    COUNT(DISTINCT es.semester_id) as total_semesters,
    COUNT(DISTINCT es.main_category_id) as total_categories
FROM evaluation_sessions es
LEFT JOIN evaluation_responses er ON es.id = er.evaluation_session_id AND er.rating_value IS NOT NULL
WHERE es.evaluatee_id = ? AND es.status = 'completed'";

$overall_stats_stmt = mysqli_prepare($conn, $overall_stats_query);
mysqli_stmt_bind_param($overall_stats_stmt, "i", $faculty_id);
mysqli_stmt_execute($overall_stats_stmt);
$overall_stats_result = mysqli_stmt_get_result($overall_stats_stmt);
$overall_stats = mysqli_fetch_assoc($overall_stats_result);

// If no evaluation data exists, set default values
if (!$overall_stats || $overall_stats['total_evaluations'] == 0) {
    $overall_stats = [
        'total_evaluations' => 0,
        'overall_average' => 0,
        'overall_min' => 0,
        'overall_max' => 0,
        'overall_stddev' => 0,
        'total_semesters' => 0,
        'total_categories' => 0
    ];
}

// Include the header
include 'includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Teacher Evaluation Results</h1>
            <p class="text-gray-600">Viewing evaluations for <?php echo $teacher['first_name'] . ' ' . $teacher['last_name']; ?></p>
        </div>
        <div class="flex space-x-3">
            <a href="teachers.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition">
                <i class="fas fa-arrow-left mr-2"></i>Back to Teachers
            </a>
        </div>
    </div>
</div>

<?php if ($message): ?>
    <div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'error' ? 'bg-red-100 text-red-700 border border-red-300' : 'bg-green-100 text-green-700 border border-green-300'; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<!-- Teacher Information Card -->
<div class="bg-white rounded-lg shadow-sm p-6 mb-6">
    <div class="flex items-center">
        <div class="flex-shrink-0 h-16 w-16">
            <div class="h-16 w-16 rounded-full bg-seait-orange flex items-center justify-center">
                <span class="text-xl font-bold text-white">
                    <?php echo strtoupper(substr($teacher['first_name'], 0, 1) . substr($teacher['last_name'], 0, 1)); ?>
                </span>
            </div>
        </div>
        <div class="ml-6">
            <h2 class="text-xl font-semibold text-gray-900">
                <?php echo $teacher['first_name'] . ' ' . $teacher['last_name']; ?>
            </h2>
            <p class="text-gray-600"><?php echo $teacher['position']; ?></p>
            <p class="text-gray-500"><?php echo $teacher['department']; ?></p>
            <p class="text-gray-500"><?php echo $teacher['email']; ?></p>
        </div>
    </div>
</div>

<?php if ($overall_stats['total_evaluations'] > 0): ?>
    <!-- Overall Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-chart-line text-blue-600"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Overall Average</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo number_format($overall_stats['overall_average'], 2); ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-clipboard-list text-green-600"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Total Evaluations</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo $overall_stats['total_evaluations']; ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-calendar-alt text-purple-600"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Semesters</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo $overall_stats['total_semesters']; ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-orange-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-tags text-orange-600"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Categories</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo $overall_stats['total_categories']; ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Evaluation Categories -->
    <div class="bg-white rounded-lg shadow-sm overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Evaluation Categories</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Average Rating</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Evaluations</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Range</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($categories as $category): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div>
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($category['category_name']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($category['category_description']); ?></div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs rounded-full 
                                    <?php echo $category['evaluation_type'] === 'student_to_teacher' ? 'bg-blue-100 text-blue-800' : 
                                        ($category['evaluation_type'] === 'peer_to_peer' ? 'bg-purple-100 text-purple-800' : 'bg-green-100 text-green-800'); ?>">
                                    <?php echo ucwords(str_replace('_', ' ', $category['evaluation_type'])); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <span class="text-sm font-medium text-gray-900">
                                        <?php echo number_format($category['average_rating'], 2); ?>
                                    </span>
                                    <div class="ml-2 flex-1 bg-gray-200 rounded-full h-2">
                                        <div class="bg-seait-orange h-2 rounded-full" 
                                             style="width: <?php echo ($category['average_rating'] / 5) * 100; ?>%"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo $category['total_evaluations']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo $category['min_rating']; ?> - <?php echo $category['max_rating']; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Semesters -->
    <?php if (!empty($semesters)): ?>
        <div class="bg-white rounded-lg shadow-sm overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Evaluation Semesters</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Semester</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Academic Year</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Period</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($semesters as $semester): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($semester['name']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($semester['academic_year']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M d, Y', strtotime($semester['start_date'])); ?> - 
                                    <?php echo date('M d, Y', strtotime($semester['end_date'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="view-evaluation-details.php?faculty_id=<?php echo $faculty_id; ?>&semester_id=<?php echo $semester['id']; ?>" 
                                       class="text-seait-orange hover:text-orange-600">
                                        <i class="fas fa-eye mr-1"></i>View Details
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

<?php else: ?>
    <!-- No Evaluations Found -->
    <div class="bg-white rounded-lg shadow-sm p-8 text-center">
        <div class="flex items-center justify-center mb-4">
            <i class="fas fa-clipboard-list text-gray-400 text-6xl"></i>
        </div>
        <h3 class="text-xl font-semibold text-gray-900 mb-2">No Evaluations Found</h3>
        <p class="text-gray-600 mb-6">
            There are currently no completed evaluations for this teacher.
        </p>
        <p class="text-sm text-gray-500 mt-4">
            Evaluations are conducted by administrators and other authorized personnel.
        </p>
    </div>
<?php endif; ?>

 