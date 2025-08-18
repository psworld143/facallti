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
$page_title = 'Reports';

// Get user information
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$first_name = $_SESSION['first_name'];
$last_name = $_SESSION['last_name'];

// Get head information from heads table
$head_query = "SELECT h.* FROM heads h WHERE h.user_id = ?";
$head_stmt = mysqli_prepare($conn, $head_query);
mysqli_stmt_bind_param($head_stmt, "i", $user_id);
mysqli_stmt_execute($head_stmt);
$head_result = mysqli_stmt_get_result($head_stmt);
$head_info = mysqli_fetch_assoc($head_result);

// Get filter parameters
$selected_semester = isset($_GET['semester']) ? (int)$_GET['semester'] : 0;
$selected_teacher = isset($_GET['teacher']) ? (int)$_GET['teacher'] : 0;

// Get available semesters for filter
$semesters_query = "SELECT id, name, academic_year FROM semesters WHERE status = 'active' ORDER BY start_date DESC";
$semesters_result = mysqli_query($conn, $semesters_query);

// Get teachers under this head's department
// Handle department name variations more comprehensively
$head_department = $head_info['department'];
$department_conditions = [];
$params = [];
$param_types = "";

// Add exact match
$department_conditions[] = "f.department = ?";
$params[] = $head_department;
$param_types .= "s";

// Handle "Department of X" pattern
if (!str_contains($head_department, 'Department of ')) {
    $department_conditions[] = "f.department = ?";
    $params[] = 'Department of ' . $head_department;
    $param_types .= "s";
}

// Handle "X Department" pattern  
if (!str_contains($head_department, ' Department')) {
    $department_conditions[] = "f.department = ?";
    $params[] = $head_department . ' Department';
    $param_types .= "s";
}

// Handle "College of X" pattern
if (!str_contains($head_department, 'College of ')) {
    $department_conditions[] = "f.department = ?";
    $params[] = 'College of ' . $head_department;
    $param_types .= "s";
}

// Handle reverse patterns (if head has "College of X", check for just "X")
if (str_contains($head_department, 'College of ')) {
    $simple_name = str_replace('College of ', '', $head_department);
    $department_conditions[] = "f.department = ?";
    $params[] = $simple_name;
    $param_types .= "s";
    
    $department_conditions[] = "f.department = ?";
    $params[] = 'Department of ' . $simple_name;
    $param_types .= "s";
}

// Handle partial matches for complex department names
// If head has "College of Business and Good Governance", also check for "College of Business"
if (str_contains($head_department, ' and ')) {
    $parts = explode(' and ', $head_department);
    if (count($parts) >= 2) {
        $first_part = trim($parts[0]);
        $department_conditions[] = "f.department = ?";
        $params[] = $first_part;
        $param_types .= "s";
    }
}

// Handle "Information and Communication Technology" vs "Information Technology" variations
if (str_contains($head_department, 'Information and Communication Technology')) {
    $department_conditions[] = "f.department = ?";
    $params[] = 'College of Information Technology';
    $param_types .= "s";
    
    $department_conditions[] = "f.department = ?";
    $params[] = 'Department of Information Technology';
    $param_types .= "s";
}

$teachers_query = "SELECT f.id, f.first_name, f.last_name, f.email 
                   FROM faculty f 
                   WHERE (" . implode(' OR ', $department_conditions) . ") AND f.is_active = 1 
                   ORDER BY f.last_name, f.first_name";
$teachers_stmt = mysqli_prepare($conn, $teachers_query);
mysqli_stmt_bind_param($teachers_stmt, $param_types, ...$params);
mysqli_stmt_execute($teachers_stmt);
$teachers_result = mysqli_stmt_get_result($teachers_stmt);

// Get evaluation statistics
$stats_query = "SELECT 
                    COUNT(*) as total_evaluations,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_evaluations,
                    SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as active_evaluations,
                    SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_evaluations
                FROM evaluation_sessions 
                WHERE evaluator_id = ? AND evaluator_type = 'head'";

if ($selected_semester > 0) {
    $stats_query .= " AND semester_id = $selected_semester";
}

$stats_stmt = mysqli_prepare($conn, $stats_query);
mysqli_stmt_bind_param($stats_stmt, "i", $user_id);
mysqli_stmt_execute($stats_stmt);
$stats_result = mysqli_stmt_get_result($stats_stmt);
$stats = mysqli_fetch_assoc($stats_result);

// Get recent evaluation results
$recent_results_query = "SELECT es.*, 
                         CASE 
                             WHEN es.evaluatee_type = 'teacher' THEN f.first_name
                             ELSE u.first_name
                         END as evaluatee_first_name,
                         CASE 
                             WHEN es.evaluatee_type = 'teacher' THEN f.last_name
                             ELSE u.last_name
                         END as evaluatee_last_name,
                         es.evaluatee_type,
                         mec.name as category_name,
                         s.name as semester_name,
                         (SELECT AVG(er.rating_value) FROM evaluation_responses er WHERE er.evaluation_session_id = es.id) as average_rating
                         FROM evaluation_sessions es
                         LEFT JOIN faculty f ON es.evaluatee_id = f.id AND es.evaluatee_type = 'teacher'
                         LEFT JOIN users u ON es.evaluatee_id = u.id AND es.evaluatee_type != 'teacher'
                         LEFT JOIN main_evaluation_categories mec ON es.main_category_id = mec.id
                         LEFT JOIN semesters s ON es.semester_id = s.id
                         WHERE es.evaluator_id = ? AND es.evaluator_type = 'head' AND es.status = 'completed'";

if ($selected_semester > 0) {
    $recent_results_query .= " AND es.semester_id = $selected_semester";
}

if ($selected_teacher > 0) {
    $recent_results_query .= " AND es.evaluatee_id = $selected_teacher";
}

$recent_results_query .= " ORDER BY es.updated_at DESC LIMIT 10";

$recent_stmt = mysqli_prepare($conn, $recent_results_query);
mysqli_stmt_bind_param($recent_stmt, "i", $user_id);
mysqli_stmt_execute($recent_stmt);
$recent_result = mysqli_stmt_get_result($recent_stmt);

$recent_results = [];
while ($row = mysqli_fetch_assoc($recent_result)) {
    $recent_results[] = $row;
}

// Include the header
include 'includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Evaluation Reports</h1>
    <p class="text-gray-600">View comprehensive reports of your teacher evaluations</p>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow-sm p-6 mb-6">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Semester</label>
            <select name="semester" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange">
                <option value="">All Semesters</option>
                <?php while ($semester = mysqli_fetch_assoc($semesters_result)): ?>
                    <option value="<?php echo $semester['id']; ?>" <?php echo $selected_semester == $semester['id'] ? 'selected' : ''; ?>>
                        <?php echo $semester['name'] . ' (' . $semester['academic_year'] . ')'; ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Teacher</label>
            <select name="teacher" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange">
                <option value="">All Teachers</option>
                <?php while ($teacher = mysqli_fetch_assoc($teachers_result)): ?>
                    <option value="<?php echo $teacher['id']; ?>" <?php echo $selected_teacher == $teacher['id'] ? 'selected' : ''; ?>>
                        <?php echo $teacher['first_name'] . ' ' . $teacher['last_name']; ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="flex items-end">
            <button type="submit" class="w-full bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition">
                <i class="fas fa-filter mr-2"></i>Filter
            </button>
        </div>
    </form>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-white rounded-lg shadow-sm p-6">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <i class="fas fa-clipboard-check text-3xl text-blue-500"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Total Evaluations</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total_evaluations']; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm p-6">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <i class="fas fa-check-circle text-3xl text-green-500"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Completed</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $stats['completed_evaluations']; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm p-6">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <i class="fas fa-clock text-3xl text-yellow-500"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Active</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $stats['active_evaluations']; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm p-6">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <i class="fas fa-edit text-3xl text-gray-500"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Draft</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $stats['draft_evaluations']; ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Recent Evaluation Results -->
<div class="bg-white rounded-lg shadow-sm">
    <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-medium text-gray-900">Recent Evaluation Results</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teacher</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Semester</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Average Rating</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Completed</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($recent_results)): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">No completed evaluations found</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recent_results as $result): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">
                                    <?php echo $result['evaluatee_first_name'] . ' ' . $result['evaluatee_last_name']; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?php echo $result['category_name']; ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo $result['semester_name']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($result['average_rating']): ?>
                                    <div class="flex items-center">
                                        <span class="text-sm font-medium text-gray-900">
                                            <?php echo number_format($result['average_rating'], 1); ?>
                                        </span>
                                        <div class="ml-2 flex items-center">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star text-xs <?php echo $i <= $result['average_rating'] ? 'text-yellow-400' : 'text-gray-300'; ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span class="text-sm text-gray-500">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo date('M d, Y', strtotime($result['updated_at'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <?php if ($result['evaluatee_type'] === 'teacher'): ?>
                                    <a href="view-evaluation.php?faculty_id=<?php echo $result['evaluatee_id']; ?>" 
                                       class="text-seait-orange hover:text-orange-600">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                <?php else: ?>
                                    <a href="../IntelliEVal/view-evaluation.php?id=<?php echo $result['evaluatee_id']; ?>" 
                                       class="text-seait-orange hover:text-orange-600">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Export Options -->
<div class="mt-6 bg-white rounded-lg shadow-sm p-6">
    <h3 class="text-lg font-medium text-gray-900 mb-4">Export Reports</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <a href="../IntelliEVal/export_evaluation_reports.php?head_id=<?php echo $user_id; ?>&semester=<?php echo $selected_semester; ?>" 
           class="flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-seait-orange hover:bg-orange-600 transition">
            <i class="fas fa-download mr-2"></i>Export Evaluation Report
        </a>
        <a href="../IntelliEVal/export_teacher_ratings.php?head_id=<?php echo $user_id; ?>&semester=<?php echo $selected_semester; ?>" 
           class="flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 transition">
            <i class="fas fa-chart-line mr-2"></i>Export Teacher Ratings
        </a>
    </div>
</div>


