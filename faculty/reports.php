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
$page_title = 'Reports & Analytics';

// Get date range filter
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01'); // First day of current month
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-t'); // Last day of current month

// Get teacher's department
$faculty_query = "SELECT f.department FROM faculty f WHERE f.email = ? AND f.is_active = 1";
$faculty_stmt = mysqli_prepare($conn, $faculty_query);
mysqli_stmt_bind_param($faculty_stmt, "s", $_SESSION['username']);
mysqli_stmt_execute($faculty_stmt);
$faculty_result = mysqli_stmt_get_result($faculty_stmt);
$faculty_info = mysqli_fetch_assoc($faculty_result);
$teacher_department = $faculty_info ? $faculty_info['department'] : 'Unknown';

// Get class statistics
$class_stats_query = "SELECT
                        COUNT(*) as total_classes,
                        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_classes,
                        COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_classes
                     FROM teacher_classes
                     WHERE teacher_id = ? AND created_at BETWEEN ? AND ?";
$class_stats_stmt = mysqli_prepare($conn, $class_stats_query);
mysqli_stmt_bind_param($class_stats_stmt, "iss", $_SESSION['user_id'], $date_from, $date_to);
mysqli_stmt_execute($class_stats_stmt);
$class_stats = mysqli_fetch_assoc(mysqli_stmt_get_result($class_stats_stmt));

// Get student enrollment statistics
$enrollment_stats_query = "SELECT
                            COUNT(*) as total_enrollments,
                            COUNT(CASE WHEN ce.status = 'active' THEN 1 END) as active_enrollments,
                            COUNT(CASE WHEN ce.status = 'dropped' THEN 1 END) as dropped_enrollments
                          FROM class_enrollments ce
                          JOIN teacher_classes tc ON ce.class_id = tc.id
                          WHERE tc.teacher_id = ? AND ce.created_at BETWEEN ? AND ?";
$enrollment_stats_stmt = mysqli_prepare($conn, $enrollment_stats_query);
mysqli_stmt_bind_param($enrollment_stats_stmt, "iss", $_SESSION['user_id'], $date_from, $date_to);
mysqli_stmt_execute($enrollment_stats_stmt);
$enrollment_stats = mysqli_fetch_assoc(mysqli_stmt_get_result($enrollment_stats_stmt));

// Get evaluation statistics
$evaluation_stats_query = "SELECT
                            COUNT(*) as total_evaluations,
                            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_evaluations,
                            COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress_evaluations,
                            COUNT(CASE WHEN status = 'draft' THEN 1 END) as draft_evaluations
                          FROM evaluation_sessions
                          WHERE evaluator_id = ? AND created_at BETWEEN ? AND ?";
$evaluation_stats_stmt = mysqli_prepare($conn, $evaluation_stats_query);
mysqli_stmt_bind_param($evaluation_stats_stmt, "iss", $_SESSION['user_id'], $date_from, $date_to);
mysqli_stmt_execute($evaluation_stats_stmt);
$evaluation_stats = mysqli_fetch_assoc(mysqli_stmt_get_result($evaluation_stats_stmt));

// Get peer evaluation statistics
$peer_evaluation_stats_query = "SELECT
                                  COUNT(*) as total_peer_evaluations,
                                  COUNT(CASE WHEN es.status = 'completed' THEN 1 END) as completed_peer_evaluations
                                FROM evaluation_sessions es
                                JOIN main_evaluation_categories mec ON es.main_category_id = mec.id
                                WHERE es.evaluator_id = ? AND mec.evaluation_type = 'peer_to_peer'
                                AND es.created_at BETWEEN ? AND ?";
$peer_evaluation_stats_stmt = mysqli_prepare($conn, $peer_evaluation_stats_query);
mysqli_stmt_bind_param($peer_evaluation_stats_stmt, "iss", $_SESSION['user_id'], $date_from, $date_to);
mysqli_stmt_execute($peer_evaluation_stats_stmt);
$peer_evaluation_stats = mysqli_fetch_assoc(mysqli_stmt_get_result($peer_evaluation_stats_stmt));

// Get top performing classes (by enrollment)
$top_classes_query = "SELECT
                        tc.section,
                        cc.subject_title,
                        cc.subject_code,
                        COUNT(ce.id) as enrollment_count
                      FROM teacher_classes tc
                      JOIN course_curriculum cc ON tc.subject_id = cc.id
                      LEFT JOIN class_enrollments ce ON tc.id = ce.class_id AND ce.status = 'active'
                      WHERE tc.teacher_id = ? AND tc.created_at BETWEEN ? AND ?
                      GROUP BY tc.id
                      ORDER BY enrollment_count DESC
                      LIMIT 5";
$top_classes_stmt = mysqli_prepare($conn, $top_classes_query);
mysqli_stmt_bind_param($top_classes_stmt, "iss", $_SESSION['user_id'], $date_from, $date_to);
mysqli_stmt_execute($top_classes_stmt);
$top_classes_result = mysqli_stmt_get_result($top_classes_stmt);

// Get evaluation categories breakdown
$evaluation_categories_query = "SELECT
                                  mec.name as category_name,
                                  COUNT(es.id) as evaluation_count
                                FROM evaluation_sessions es
                                JOIN main_evaluation_categories mec ON es.main_category_id = mec.id
                                WHERE es.evaluator_id = ? AND es.created_at BETWEEN ? AND ?
                                GROUP BY mec.id
                                ORDER BY evaluation_count DESC";
$evaluation_categories_stmt = mysqli_prepare($conn, $evaluation_categories_query);
mysqli_stmt_bind_param($evaluation_categories_stmt, "iss", $_SESSION['user_id'], $date_from, $date_to);
mysqli_stmt_execute($evaluation_categories_stmt);
$evaluation_categories_result = mysqli_stmt_get_result($evaluation_categories_stmt);

// Get monthly trends
$monthly_trends_query = "SELECT
                          DATE_FORMAT(created_at, '%Y-%m') as month,
                          COUNT(*) as class_count
                        FROM teacher_classes
                        WHERE teacher_id = ? AND created_at BETWEEN ? AND ?
                        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                        ORDER BY month DESC
                        LIMIT 6";
$monthly_trends_stmt = mysqli_prepare($conn, $monthly_trends_query);
mysqli_stmt_bind_param($monthly_trends_stmt, "iss", $_SESSION['user_id'], $date_from, $date_to);
mysqli_stmt_execute($monthly_trends_stmt);
$monthly_trends_result = mysqli_stmt_get_result($monthly_trends_stmt);

// Include the shared header
$sidebar_context = 'main';
include 'includes/unified-header.php';
?>

<div class="mb-6 sm:mb-8">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark">Reports & Analytics</h1>
            <p class="text-gray-600 mt-1">View comprehensive statistics and performance metrics</p>
        </div>
        <div class="mt-4 sm:mt-0">
            <form method="GET" class="flex space-x-2">
                <input type="date" name="date_from" value="<?php echo $date_from; ?>"
                       class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                <span class="flex items-center text-gray-500">to</span>
                <input type="date" name="date_to" value="<?php echo $date_to; ?>"
                       class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                <button type="submit" class="bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition">
                    <i class="fas fa-filter mr-2"></i>Filter
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <!-- Class Statistics -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                <i class="fas fa-chalkboard text-blue-600 text-xl"></i>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-600">Total Classes</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $class_stats['total_classes']; ?></p>
            </div>
        </div>
        <div class="mt-4 flex justify-between text-sm">
            <span class="text-green-600"><?php echo $class_stats['active_classes']; ?> Active</span>
            <span class="text-gray-500"><?php echo $class_stats['inactive_classes']; ?> Inactive</span>
        </div>
    </div>

    <!-- Student Enrollment -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                <i class="fas fa-users text-green-600 text-xl"></i>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-600">Total Enrollments</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $enrollment_stats['total_enrollments']; ?></p>
            </div>
        </div>
        <div class="mt-4 flex justify-between text-sm">
            <span class="text-green-600"><?php echo $enrollment_stats['active_enrollments']; ?> Active</span>
            <span class="text-red-500"><?php echo $enrollment_stats['dropped_enrollments']; ?> Dropped</span>
        </div>
    </div>

    <!-- Evaluations -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                <i class="fas fa-clipboard-check text-purple-600 text-xl"></i>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-600">Total Evaluations</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $evaluation_stats['total_evaluations']; ?></p>
            </div>
        </div>
        <div class="mt-4 flex justify-between text-sm">
            <span class="text-green-600"><?php echo $evaluation_stats['completed_evaluations']; ?> Completed</span>
            <span class="text-yellow-600"><?php echo $evaluation_stats['in_progress_evaluations']; ?> In Progress</span>
        </div>
    </div>

    <!-- Peer Evaluations -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center mr-4">
                <i class="fas fa-user-friends text-orange-600 text-xl"></i>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-600">Peer Evaluations</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $peer_evaluation_stats['total_peer_evaluations']; ?></p>
            </div>
        </div>
        <div class="mt-4 flex justify-between text-sm">
            <span class="text-green-600"><?php echo $peer_evaluation_stats['completed_peer_evaluations']; ?> Completed</span>
            <span class="text-gray-500"><?php echo $peer_evaluation_stats['total_peer_evaluations'] - $peer_evaluation_stats['completed_peer_evaluations']; ?> Pending</span>
        </div>
    </div>
</div>

<!-- Charts and Detailed Analytics -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <!-- Top Performing Classes -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Top Performing Classes</h3>
        <?php if (mysqli_num_rows($top_classes_result) > 0): ?>
        <div class="space-y-4">
            <?php while ($class = mysqli_fetch_assoc($top_classes_result)): ?>
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                <div>
                    <p class="font-medium text-gray-900"><?php echo htmlspecialchars($class['subject_title']); ?></p>
                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($class['subject_code'] . ' - ' . $class['section']); ?></p>
                </div>
                <div class="text-right">
                    <p class="text-lg font-bold text-seait-orange"><?php echo $class['enrollment_count']; ?></p>
                    <p class="text-xs text-gray-500">Students</p>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        <?php else: ?>
        <div class="text-center py-8">
            <i class="fas fa-chart-bar text-gray-300 text-4xl mb-4"></i>
            <p class="text-gray-500">No class data available for the selected period.</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Evaluation Categories Breakdown -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Evaluation Categories</h3>
        <?php if (mysqli_num_rows($evaluation_categories_result) > 0): ?>
        <div class="space-y-4">
            <?php while ($category = mysqli_fetch_assoc($evaluation_categories_result)): ?>
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-700"><?php echo htmlspecialchars($category['category_name']); ?></span>
                <div class="flex items-center">
                    <div class="w-24 bg-gray-200 rounded-full h-2 mr-3">
                        <?php
                        $percentage = $evaluation_stats['total_evaluations'] > 0 ?
                            ($category['evaluation_count'] / $evaluation_stats['total_evaluations']) * 100 : 0;
                        ?>
                        <div class="bg-seait-orange h-2 rounded-full" style="width: <?php echo $percentage; ?>%"></div>
                    </div>
                    <span class="text-sm font-medium text-gray-900"><?php echo $category['evaluation_count']; ?></span>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        <?php else: ?>
        <div class="text-center py-8">
            <i class="fas fa-chart-pie text-gray-300 text-4xl mb-4"></i>
            <p class="text-gray-500">No evaluation data available for the selected period.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Monthly Trends -->
<div class="bg-white rounded-lg shadow-md p-6 mt-8">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Monthly Class Creation Trends</h3>
    <?php if (mysqli_num_rows($monthly_trends_result) > 0): ?>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
        <?php while ($trend = mysqli_fetch_assoc($monthly_trends_result)): ?>
        <div class="text-center p-4 bg-gray-50 rounded-lg">
            <p class="text-sm font-medium text-gray-600"><?php echo date('M Y', strtotime($trend['month'] . '-01')); ?></p>
            <p class="text-2xl font-bold text-seait-orange"><?php echo $trend['class_count']; ?></p>
            <p class="text-xs text-gray-500">Classes</p>
        </div>
        <?php endwhile; ?>
    </div>
    <?php else: ?>
    <div class="text-center py-8">
        <i class="fas fa-chart-line text-gray-300 text-4xl mb-4"></i>
        <p class="text-gray-500">No trend data available for the selected period.</p>
    </div>
    <?php endif; ?>
</div>

<!-- Department Information -->
<div class="bg-white rounded-lg shadow-md p-6 mt-8">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Department Information</h3>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
        <div>
            <p class="text-sm font-medium text-gray-600">Department</p>
            <p class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($teacher_department); ?></p>
        </div>
        <div>
            <p class="text-sm font-medium text-gray-600">Report Period</p>
            <p class="text-lg font-semibold text-gray-900">
                <?php echo date('M j, Y', strtotime($date_from)); ?> - <?php echo date('M j, Y', strtotime($date_to)); ?>
            </p>
        </div>
    </div>
</div>

<!-- Export Options -->
<div class="bg-white rounded-lg shadow-md p-6 mt-8">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Export Reports</h3>
    <div class="flex flex-wrap gap-4">
        <button onclick="exportReport('pdf')" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition flex items-center">
            <i class="fas fa-file-pdf mr-2"></i>Export as PDF
        </button>
        <a href="../IntelliEVal/export_excel_reports.php?type=performance&semester=<?php echo isset($_GET['semester']) ? $_GET['semester'] : 0; ?>&year=<?php echo date('Y'); ?>"
           class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition flex items-center">
            <i class="fas fa-file-excel mr-2"></i>Export as Excel
        </a>
    </div>
</div>

<script>
function exportReport(format) {
    if (format === 'pdf') {
        // Show loading state
        const button = event.target;
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Generating PDF...';
        button.disabled = true;

        // Generate PDF using the existing Excel export functionality as base
        const url = `../IntelliEVal/export_excel_reports.php?type=performance&semester=<?php echo isset($_GET['semester']) ? $_GET['semester'] : 0; ?>&year=<?php echo date('Y'); ?>&format=pdf`;

        fetch(url)
            .then(response => {
                if (response.ok) {
                    return response.blob();
                }
                throw new Error('PDF generation failed');
            })
            .then(blob => {
                // Create download link
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.style.display = 'none';
                a.href = url;
                a.download = `faculty_report_${new Date().toISOString().split('T')[0]}.pdf`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
            })
            .catch(error => {
                console.error('Error:', error);
                alert('PDF export failed. Please try again or use Excel export instead.');
            })
            .finally(() => {
                // Restore button state
                button.innerHTML = originalText;
                button.disabled = false;
            });
    }
}

// Real-time data refresh functionality
let refreshInterval;

function startAutoRefresh() {
    refreshInterval = setInterval(refreshData, 300000); // 5 minutes
}

function stopAutoRefresh() {
    if (refreshInterval) {
        clearInterval(refreshInterval);
    }
}

function refreshData() {
    const dateFrom = '<?php echo $date_from; ?>';
    const dateTo = '<?php echo $date_to; ?>';

    // Show refresh indicator
    const refreshIndicator = document.getElementById('refreshIndicator');
    if (refreshIndicator) {
        refreshIndicator.classList.remove('hidden');
    }

    fetch(`../api/refresh-reports-data.php?date_from=${dateFrom}&date_to=${dateTo}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateReportData(data.data);
            }
        })
        .catch(error => {
            console.error('Error refreshing data:', error);
        })
        .finally(() => {
            // Hide refresh indicator
            if (refreshIndicator) {
                refreshIndicator.classList.add('hidden');
            }
        });
}

function updateReportData(data) {
    // Update statistics
    if (data.stats) {
        const statsElements = {
            'totalClasses': data.stats.total_classes || 0,
            'totalStudents': data.stats.total_students || 0,
            'totalMaterials': data.stats.total_materials || 0,
            'totalAnnouncements': data.stats.total_announcements || 0,
            'totalEvents': data.stats.total_events || 0
        };

        Object.keys(statsElements).forEach(key => {
            const element = document.getElementById(key);
            if (element) {
                element.textContent = statsElements[key];
            }
        });
    }

    // Update evaluation statistics
    if (data.evaluation_stats) {
        const evalElements = {
            'totalEvaluations': data.evaluation_stats.total_evaluations || 0,
            'averageRating': data.evaluation_stats.average_rating ? parseFloat(data.evaluation_stats.average_rating).toFixed(2) : '0.00',
            'uniqueEvaluators': data.evaluation_stats.unique_evaluators || 0
        };

        Object.keys(evalElements).forEach(key => {
            const element = document.getElementById(key);
            if (element) {
                element.textContent = evalElements[key];
            }
        });
    }

    // Update last updated timestamp
    const lastUpdatedElement = document.getElementById('lastUpdated');
    if (lastUpdatedElement && data.last_updated) {
        lastUpdatedElement.textContent = new Date(data.last_updated).toLocaleString();
    }
}

// Start auto-refresh when page loads
document.addEventListener('DOMContentLoaded', function() {
    startAutoRefresh();
});

// Stop auto-refresh when page is hidden
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        stopAutoRefresh();
    } else {
        startAutoRefresh();
    }
});
</script>

<?php include 'includes/footer.php'; ?>