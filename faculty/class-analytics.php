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
$page_title = 'Class Analytics';

// Get date range filter
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01'); // First day of current month
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d'); // Today

// Get class analytics data
$analytics_query = "SELECT
                    tc.id,
                    tc.section,
                    cc.subject_title,
                    cc.subject_code,
                    COUNT(DISTINCT ce.student_id) as total_students,
                    COUNT(DISTINCT CASE WHEN ce.status = 'active' THEN ce.student_id END) as active_students,
                    COUNT(DISTINCT CASE WHEN ce.status = 'dropped' THEN ce.student_id END) as dropped_students,
                    COUNT(DISTINCT ca.id) as total_announcements,
                    COUNT(DISTINCT fe.id) as total_events,
                    tc.created_at
                    FROM teacher_classes tc
                    JOIN course_curriculum cc ON tc.subject_id = cc.id
                    JOIN faculty f ON tc.teacher_id = f.id
                    LEFT JOIN class_enrollments ce ON tc.id = ce.class_id
                    LEFT JOIN class_announcements ca ON tc.id = ca.class_id
                    LEFT JOIN faculty_events fe ON tc.id = fe.class_id
                    WHERE f.email = ?
                    GROUP BY tc.id
                    ORDER BY tc.created_at DESC";
$analytics_stmt = mysqli_prepare($conn, $analytics_query);
mysqli_stmt_bind_param($analytics_stmt, "s", $_SESSION['username']);
mysqli_stmt_execute($analytics_stmt);
$analytics_result = mysqli_stmt_get_result($analytics_stmt);

// Get overall statistics
$overall_stats_query = "SELECT
                        COUNT(DISTINCT tc.id) as total_classes,
                        COUNT(DISTINCT ce.student_id) as total_students,
                        COUNT(DISTINCT CASE WHEN ce.status = 'active' THEN ce.student_id END) as active_students,
                        COUNT(DISTINCT ca.id) as total_announcements,
                        COUNT(DISTINCT fe.id) as total_events,
                        AVG(student_count) as avg_students_per_class
                        FROM teacher_classes tc
                        LEFT JOIN class_enrollments ce ON tc.id = ce.class_id
                        LEFT JOIN class_announcements ca ON tc.id = ca.class_id
                        LEFT JOIN faculty_events fe ON tc.id = fe.class_id
                        LEFT JOIN (
                            SELECT class_id, COUNT(DISTINCT student_id) as student_count
                            FROM class_enrollments
                            WHERE status = 'active'
                            GROUP BY class_id
                        ) sc ON tc.id = sc.class_id
                        WHERE tc.teacher_id = ?";
$overall_stats_stmt = mysqli_prepare($conn, $overall_stats_query);
mysqli_stmt_bind_param($overall_stats_stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($overall_stats_stmt);
$overall_stats = mysqli_fetch_assoc(mysqli_stmt_get_result($overall_stats_stmt));

// Get monthly class creation trend
$monthly_trend_query = "SELECT
                        DATE_FORMAT(created_at, '%Y-%m') as month,
                        COUNT(*) as classes_created
                        FROM teacher_classes
                        WHERE teacher_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                        ORDER BY month DESC";
$monthly_trend_stmt = mysqli_prepare($conn, $monthly_trend_query);
mysqli_stmt_bind_param($monthly_trend_stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($monthly_trend_stmt);
$monthly_trend_result = mysqli_stmt_get_result($monthly_trend_stmt);

// Include the shared header
$sidebar_context = 'main';
include 'includes/unified-header.php';
?>

<div class="mb-6 sm:mb-8">
    <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Class Analytics</h1>
    <p class="text-sm sm:text-base text-gray-600">Comprehensive analytics and insights for your classes</p>
</div>

<!-- Date Range Filter -->
<div class="mb-6 bg-white p-4 rounded-lg shadow-md">
    <form method="GET" class="flex flex-col sm:flex-row gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
            <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>"
                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
            <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>"
                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
        </div>
        <div class="flex items-end">
            <button type="submit" class="bg-seait-orange text-white px-4 py-2 rounded-md hover:bg-orange-600 transition">
                <i class="fas fa-filter mr-2"></i>Apply Filter
            </button>
        </div>
    </form>
</div>

<!-- Overall Statistics -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                <i class="fas fa-chalkboard text-blue-600 text-xl"></i>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-600">Total Classes</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo number_format($overall_stats['total_classes'] ?? 0); ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                <i class="fas fa-users text-green-600 text-xl"></i>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-600">Total Students</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo number_format($overall_stats['total_students'] ?? 0); ?></p>
                <p class="text-sm text-gray-500"><?php echo number_format($overall_stats['avg_students_per_class'] ?? 0, 1); ?> avg per class</p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                <i class="fas fa-bullhorn text-purple-600 text-xl"></i>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-600">Total Announcements</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo number_format($overall_stats['total_announcements'] ?? 0); ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center mr-4">
                <i class="fas fa-calendar-alt text-orange-600 text-xl"></i>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-600">Total Events</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo number_format($overall_stats['total_events'] ?? 0); ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center mr-4">
                <i class="fas fa-chart-line text-yellow-600 text-xl"></i>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-600">Active Students</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo number_format($overall_stats['active_students'] ?? 0); ?></p>
                <p class="text-sm text-gray-500"><?php echo $overall_stats['total_students'] > 0 ? round(($overall_stats['active_students'] / $overall_stats['total_students']) * 100, 1) : 0; ?>% retention</p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center mr-4">
                <i class="fas fa-clock text-indigo-600 text-xl"></i>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-600">Average Class Age</p>
                <p class="text-2xl font-bold text-gray-900"><?php
                    $avg_age_query = "SELECT AVG(DATEDIFF(NOW(), created_at)) as avg_days FROM teacher_classes WHERE teacher_id = ?";
                    $avg_age_stmt = mysqli_prepare($conn, $avg_age_query);
                    mysqli_stmt_bind_param($avg_age_stmt, "i", $_SESSION['user_id']);
                    mysqli_stmt_execute($avg_age_stmt);
                    $avg_days = mysqli_fetch_assoc(mysqli_stmt_get_result($avg_age_stmt))['avg_days'] ?? 0;
                    echo round($avg_days);
                ?> days</p>
            </div>
        </div>
    </div>
</div>

<!-- Class Analytics Table -->
<div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
    <div class="px-6 py-4 border-b border-gray-200">
        <h2 class="text-lg font-medium text-gray-900">Class Performance Analytics</h2>
    </div>

    <?php if (mysqli_num_rows($analytics_result) == 0): ?>
        <div class="p-6 text-center">
            <i class="fas fa-chart-bar text-gray-300 text-4xl mb-4"></i>
            <p class="text-gray-500">No class data available for analytics.</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Students</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Announcements</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Events</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php while ($class = mysqli_fetch_assoc($analytics_result)): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div>
                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($class['subject_title']); ?></div>
                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($class['subject_code'] . ' - Section ' . $class['section']); ?></div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900"><?php echo $class['total_students']; ?> total</div>
                            <div class="text-sm text-gray-500">
                                <?php echo $class['active_students']; ?> active,
                                <?php echo $class['dropped_students']; ?> dropped
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900"><?php echo $class['total_announcements']; ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900"><?php echo $class['total_events']; ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo date('M j, Y', strtotime($class['created_at'])); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <a href="class_dashboard.php?class_id=<?php echo $class['id']; ?>" class="text-seait-orange hover:text-orange-600">
                                <i class="fas fa-chart-line mr-1"></i>View Details
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Monthly Trend Chart -->
<div class="bg-white rounded-lg shadow-md p-6">
    <h3 class="text-lg font-medium text-gray-900 mb-4">Monthly Class Creation Trend</h3>
    <div class="h-64 flex items-end justify-between space-x-2">
        <?php
        $months = [];
        $counts = [];
        while ($trend = mysqli_fetch_assoc($monthly_trend_result)) {
            $months[] = date('M Y', strtotime($trend['month'] . '-01'));
            $counts[] = $trend['classes_created'];
        }
        $max_count = max($counts) ?: 1;
        ?>

        <?php for ($i = 0; $i < count($months); $i++): ?>
        <div class="flex-1 flex flex-col items-center">
            <div class="w-full bg-blue-200 rounded-t" style="height: <?php echo ($counts[$i] / $max_count) * 200; ?>px;">
                <div class="bg-blue-600 h-full rounded-t"></div>
            </div>
            <div class="text-xs text-gray-500 mt-2 text-center"><?php echo $months[$i]; ?></div>
            <div class="text-xs font-medium text-gray-900"><?php echo $counts[$i]; ?></div>
        </div>
        <?php endfor; ?>
    </div>
</div>

<script>
// Add any additional JavaScript for interactive charts or analytics here
document.addEventListener('DOMContentLoaded', function() {
    // Future: Add interactive charts using Chart.js or similar library
    console.log('Class Analytics page loaded');
});
</script>

<?php
// Include the shared footer
include 'includes/footer.php';
?>