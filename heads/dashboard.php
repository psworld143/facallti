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

// Get pending evaluations count
$pending_eval_query = "SELECT COUNT(*) as total FROM evaluation_sessions es 
                       JOIN faculty f ON es.evaluatee_id = f.id 
                       WHERE es.evaluatee_type = 'teacher' AND es.status = 'draft'
                       AND f.department = ?";
$pending_eval_stmt = mysqli_prepare($conn, $pending_eval_query);
mysqli_stmt_bind_param($pending_eval_stmt, $param_types, ...$params);
mysqli_stmt_execute($pending_eval_stmt);
$pending_eval_result = mysqli_stmt_get_result($pending_eval_stmt);
$stats['pending_evaluations'] = mysqli_fetch_assoc($pending_eval_result)['total'];

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

// Get recent activities (last 7 days)
$recent_activities_query = "SELECT 
    'evaluation' as type,
    es.created_at,
    CONCAT('Evaluation completed for ', f.first_name, ' ', f.last_name) as description,
    'text-green-600' as color_class
    FROM evaluation_sessions es
    JOIN faculty f ON es.evaluatee_id = f.id
    WHERE es.evaluatee_type = 'teacher' AND es.status = 'completed'
    AND f.department = ? AND es.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY es.created_at DESC
    LIMIT 10";
$recent_activities_stmt = mysqli_prepare($conn, $recent_activities_query);
mysqli_stmt_bind_param($recent_activities_stmt, $param_types, ...$params);
mysqli_stmt_execute($recent_activities_stmt);
$recent_activities_result = mysqli_stmt_get_result($recent_activities_stmt);

$recent_activities = [];
while ($row = mysqli_fetch_assoc($recent_activities_result)) {
    $recent_activities[] = $row;
}

// Include the header
include 'includes/header.php';
?>

<!-- Welcome Section -->
<div class="mb-8">
    <div class="bg-white rounded-xl shadow-sm p-8 border border-gray-200">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold mb-2 text-gray-900">Welcome back, <?php echo $first_name; ?>! 👋</h1>
                <p class="text-gray-600 text-lg">Head of <?php echo $head_info['department']; ?> Department</p>
                <p class="text-gray-500 mt-2">Here's what's happening in your department today</p>
            </div>
            <div class="hidden md:block">
                <div class="w-20 h-20 bg-seait-orange rounded-full flex items-center justify-center">
                    <i class="fas fa-user-tie text-4xl text-white"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="mb-8">
    <h2 class="text-xl font-bold text-gray-900 mb-4">Quick Actions</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <a href="teachers.php" class="bg-white rounded-lg shadow-sm p-6 hover:shadow-md transition-all duration-200 transform hover:scale-105 border-l-4 border-blue-500">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-users text-2xl text-blue-500"></i>
                </div>
                <div class="ml-4">
                    <h3 class="text-sm font-medium text-gray-900">Manage Teachers</h3>
                    <p class="text-xs text-gray-500">View and manage faculty</p>
                </div>
            </div>
        </a>

        <a href="evaluate-faculty.php" class="bg-white rounded-lg shadow-sm p-6 hover:shadow-md transition-all duration-200 transform hover:scale-105 border-l-4 border-green-500">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-clipboard-check text-2xl text-green-500"></i>
                </div>
                <div class="ml-4">
                    <h3 class="text-sm font-medium text-gray-900">Evaluate Faculty</h3>
                    <p class="text-xs text-gray-500">Conduct evaluations</p>
                </div>
            </div>
        </a>

        <a href="reports.php" class="bg-white rounded-lg shadow-sm p-6 hover:shadow-md transition-all duration-200 transform hover:scale-105 border-l-4 border-purple-500">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-chart-bar text-2xl text-purple-500"></i>
                </div>
                <div class="ml-4">
                    <h3 class="text-sm font-medium text-gray-900">View Reports</h3>
                    <p class="text-xs text-gray-500">Analytics & insights</p>
                </div>
            </div>
        </a>

        <a href="schedule-management.php" class="bg-white rounded-lg shadow-sm p-6 hover:shadow-md transition-all duration-200 transform hover:scale-105 border-l-4 border-yellow-500">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-calendar-alt text-2xl text-yellow-500"></i>
                </div>
                <div class="ml-4">
                    <h3 class="text-sm font-medium text-gray-900">Schedules</h3>
                    <p class="text-xs text-gray-500">Manage timetables</p>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- Statistics Overview -->
<div class="mb-8">
    <h2 class="text-xl font-bold text-gray-900 mb-4">Department Overview</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white rounded-lg shadow-sm p-6 border-l-4 border-blue-500">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-chalkboard-teacher text-xl text-blue-600"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Active Teachers</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total_teachers']; ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-6 border-l-4 border-green-500">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-clipboard-check text-xl text-green-600"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Completed Evaluations</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total_evaluations']; ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-6 border-l-4 border-yellow-500">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-star text-xl text-yellow-600"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Average Rating</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['avg_rating'], 1); ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-6 border-l-4 border-orange-500">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-clock text-xl text-orange-600"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Pending Evaluations</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo $stats['pending_evaluations']; ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Content Grid -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Top Performing Teachers -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-lg shadow-sm">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-medium text-gray-900">Top Performing Teachers</h3>
                    <a href="teachers.php" class="text-seait-orange hover:text-orange-600 text-sm font-medium">View All</a>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teacher</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Evaluations</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rating</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($recent_teachers)): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-4 text-center text-gray-500">No teachers with evaluations found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_teachers as $teacher): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 bg-seait-orange rounded-full flex items-center justify-center text-white text-sm font-medium">
                                                <?php echo strtoupper(substr($teacher['first_name'], 0, 1) . substr($teacher['last_name'], 0, 1)); ?>
                                            </div>
                                            <div class="ml-3">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo $teacher['first_name'] . ' ' . $teacher['last_name']; ?>
                                                </div>
                                                <div class="text-sm text-gray-500"><?php echo $teacher['email']; ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            <?php echo $teacher['evaluation_count']; ?> completed
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <span class="text-sm font-medium text-gray-900 mr-2">
                                                <?php echo number_format($teacher['avg_rating'], 1); ?>
                                            </span>
                                            <div class="flex items-center">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star text-xs <?php echo $i <= $teacher['avg_rating'] ? 'text-yellow-400' : 'text-gray-300'; ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="view-teacher-profile.php?faculty_id=<?php echo $teacher['id']; ?>" 
                                           class="text-seait-orange hover:text-orange-600 mr-3">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="view-evaluation.php?faculty_id=<?php echo $teacher['id']; ?>" 
                                           class="text-blue-600 hover:text-blue-800">
                                            <i class="fas fa-chart-bar"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Activities & Quick Access -->
    <div class="space-y-6">
        <!-- Recent Activities -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Recent Activities</h3>
            </div>
            <div class="p-6">
                <?php if (empty($recent_activities)): ?>
                    <p class="text-gray-500 text-sm text-center py-4">No recent activities</p>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach (array_slice($recent_activities, 0, 5) as $activity): ?>
                            <div class="flex items-start space-x-3">
                                <div class="flex-shrink-0">
                                    <div class="w-2 h-2 bg-green-400 rounded-full mt-2"></div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm text-gray-900"><?php echo $activity['description']; ?></p>
                                    <p class="text-xs text-gray-500"><?php echo date('M j, g:i A', strtotime($activity['created_at'])); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>



        </div>
    </div>
</div>

<!-- Full Width Department Performance Section -->
<div class="w-full rounded-lg p-8 my-8">
    <div class="text-center max-w-4xl mx-auto">
        <i class="fas fa-trophy text-5xl mb-4 text-seait-orange"></i>
        <h3 class="text-2xl font-semibold mb-3 text-seait-dark">Department Performance</h3>
        <div class="text-5xl font-bold mb-3 text-seait-orange">
            <?php 
            $performance_score = $stats['avg_rating'] >= 4.0 ? 'Excellent' : 
                              ($stats['avg_rating'] >= 3.5 ? 'Very Good' : 
                              ($stats['avg_rating'] >= 3.0 ? 'Good' : 
                              ($stats['avg_rating'] >= 2.5 ? 'Fair' : 'Needs Improvement'))); 
            echo $performance_score;
            ?>
        </div>
        <p class="text-gray-600 text-lg">Based on <?php echo $stats['total_evaluations']; ?> evaluations</p>
    </div>
</div>

<!-- Bottom Section -->
<div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-6">
    <!-- Department Summary -->
    <div class="bg-white rounded-lg shadow-sm p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Department Summary</h3>
        <div class="space-y-3">
            <div class="flex justify-between items-center">
                <span class="text-sm text-gray-600">Total Faculty Members</span>
                <span class="text-sm font-medium text-gray-900"><?php echo $stats['total_teachers']; ?></span>
            </div>
            <div class="flex justify-between items-center">
                <span class="text-sm text-gray-600">Evaluation Completion Rate</span>
                <span class="text-sm font-medium text-gray-900">
                    <?php 
                    $completion_rate = $stats['total_teachers'] > 0 ? 
                        round(($stats['total_evaluations'] / $stats['total_teachers']) * 100) : 0;
                    echo $completion_rate . '%';
                    ?>
                </span>
            </div>
            <div class="flex justify-between items-center">
                <span class="text-sm text-gray-600">Quality Score</span>
                <span class="text-sm font-medium text-gray-900">
                    <?php echo $stats['avg_rating'] >= 4.0 ? 'High' : ($stats['avg_rating'] >= 3.0 ? 'Medium' : 'Low'); ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Action Items -->
    <div class="bg-white rounded-lg shadow-sm p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Action Items</h3>
        <div class="space-y-3">
            <?php if ($stats['pending_evaluations'] > 0): ?>
                <div class="flex items-center p-3 bg-yellow-50 rounded-lg">
                    <i class="fas fa-exclamation-triangle text-yellow-600 mr-3"></i>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-yellow-800">Pending Evaluations</p>
                        <p class="text-xs text-yellow-600"><?php echo $stats['pending_evaluations']; ?> evaluations need attention</p>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="flex items-center p-3 bg-blue-50 rounded-lg">
                <i class="fas fa-info-circle text-blue-600 mr-3"></i>
                <div class="flex-1">
                    <p class="text-sm font-medium text-blue-800">Regular Monitoring</p>
                    <p class="text-xs text-blue-600">Check teacher performance regularly</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Add interactive elements
document.addEventListener('DOMContentLoaded', function() {
    // Add hover effects to quick action cards
    const quickActionCards = document.querySelectorAll('.grid a');
    quickActionCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
    
    // Add animation to statistics cards
    const statCards = document.querySelectorAll('.border-l-4');
    statCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            card.style.transition = 'all 0.6s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
});
</script>


