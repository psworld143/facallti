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
$page_title = 'Dashboard';

// Get user information
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$first_name = $_SESSION['first_name'];
$last_name = $_SESSION['last_name'];
$role = $_SESSION['role'];

// Get head information from heads table
$head_query = "SELECT h.* FROM heads h WHERE h.user_id = ?";
$head_stmt = mysqli_prepare($conn, $head_query);
mysqli_stmt_bind_param($head_stmt, "i", $user_id);
mysqli_stmt_execute($head_stmt);
$head_result = mysqli_stmt_get_result($head_stmt);
$head_info = mysqli_fetch_assoc($head_result);

// Get comprehensive statistics for dashboard
$stats = [];

// Get teachers under this head's department
// Use exact department matching only
$head_department = $head_info['department'];
$params = [$head_department];
$param_types = "s";

$teachers_query = "SELECT COUNT(*) as total FROM faculty f 
                   WHERE f.department = ? AND f.is_active = 1";
$teachers_stmt = mysqli_prepare($conn, $teachers_query);
mysqli_stmt_bind_param($teachers_stmt, $param_types, ...$params);
mysqli_stmt_execute($teachers_stmt);
$teachers_result = mysqli_stmt_get_result($teachers_stmt);
$stats['total_teachers'] = mysqli_fetch_assoc($teachers_result)['total'];

// Get total evaluations for teachers in this department
$evaluations_query = "SELECT COUNT(*) as total FROM evaluation_sessions es 
                      JOIN faculty f ON es.evaluatee_id = f.id 
                      WHERE es.evaluatee_type = 'teacher' AND es.status = 'completed'
                      AND f.department = ?";
$evaluations_stmt = mysqli_prepare($conn, $evaluations_query);
mysqli_stmt_bind_param($evaluations_stmt, $param_types, ...$params);
mysqli_stmt_execute($evaluations_stmt);
$evaluations_result = mysqli_stmt_get_result($evaluations_stmt);
$stats['total_evaluations'] = mysqli_fetch_assoc($evaluations_result)['total'];

// Get average rating for department teachers
$avg_rating_query = "SELECT AVG(er.rating_value) as avg_rating FROM evaluation_responses er
                     JOIN evaluation_sessions es ON er.evaluation_session_id = es.id
                     JOIN faculty f ON es.evaluatee_id = f.id
                     WHERE es.evaluatee_type = 'teacher' AND es.status = 'completed'
                     AND f.department = ?
                     AND er.rating_value IS NOT NULL";
$avg_rating_stmt = mysqli_prepare($conn, $avg_rating_query);
mysqli_stmt_bind_param($avg_rating_stmt, $param_types, ...$params);
mysqli_stmt_execute($avg_rating_stmt);
$avg_rating_result = mysqli_stmt_get_result($avg_rating_stmt);
$stats['avg_rating'] = mysqli_fetch_assoc($avg_rating_result)['avg_rating'] ?? 0;

// Get teachers with recent evaluations
$recent_teachers_query = "SELECT DISTINCT f.id, f.first_name, f.last_name, f.email,
                          (SELECT COUNT(*) FROM evaluation_sessions es2 WHERE es2.evaluatee_id = f.id AND es2.status = 'completed') as evaluation_count,
                          (SELECT AVG(er2.rating_value) FROM evaluation_responses er2 
                           JOIN evaluation_sessions es3 ON er2.evaluation_session_id = es3.id 
                           WHERE es3.evaluatee_id = f.id AND es3.status = 'completed' AND er2.rating_value IS NOT NULL) as avg_rating
                          FROM faculty f
                          WHERE f.department = ? AND f.is_active = 1
                          HAVING evaluation_count > 0
                          ORDER BY evaluation_count DESC, avg_rating DESC
                          LIMIT 5";
$recent_teachers_stmt = mysqli_prepare($conn, $recent_teachers_query);
mysqli_stmt_bind_param($recent_teachers_stmt, $param_types, ...$params);
mysqli_stmt_execute($recent_teachers_stmt);
$recent_teachers_result = mysqli_stmt_get_result($recent_teachers_stmt);

$recent_teachers = [];
while ($row = mysqli_fetch_assoc($recent_teachers_result)) {
    $recent_teachers[] = $row;
}

// Include the header
include 'includes/header.php';
?>

<!-- Welcome Section -->
<div class="mb-6">
    <div class="bg-white rounded-lg shadow-sm p-6">
        <h2 class="text-2xl font-bold text-gray-900 mb-2">Welcome, <?php echo $first_name; ?>!</h2>
        <p class="text-gray-600">You are logged in as the Head of <?php echo $head_info['department']; ?> department.</p>
    </div>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-white rounded-lg shadow-sm p-6">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <i class="fas fa-chalkboard-teacher text-3xl text-blue-500"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Teachers Under Me</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total_teachers']; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm p-6">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <i class="fas fa-clipboard-check text-3xl text-green-500"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Department Evaluations</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total_evaluations']; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm p-6">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <i class="fas fa-star text-3xl text-yellow-500"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Average Rating</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['avg_rating'], 1); ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm p-6">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <i class="fas fa-chart-line text-3xl text-blue-500"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Performance</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $stats['avg_rating'] >= 4.0 ? 'Good' : ($stats['avg_rating'] >= 3.0 ? 'Fair' : 'Needs Improvement'); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Top Performing Teachers -->
<div class="bg-white rounded-lg shadow-sm">
    <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-medium text-gray-900">Top Performing Teachers</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teacher</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Evaluations</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Average Rating</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($recent_teachers)): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-gray-500">No teachers with evaluations found</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recent_teachers as $teacher): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">
                                    <?php echo $teacher['first_name'] . ' ' . $teacher['last_name']; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo $teacher['email']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo $teacher['evaluation_count']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <span class="text-sm font-medium text-gray-900">
                                        <?php echo number_format($teacher['avg_rating'], 1); ?>
                                    </span>
                                    <div class="ml-2 flex items-center">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star text-xs <?php echo $i <= $teacher['avg_rating'] ? 'text-yellow-400' : 'text-gray-300'; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="view-evaluation.php?faculty_id=<?php echo $teacher['id']; ?>" 
                                   class="text-seait-orange hover:text-orange-600">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>


