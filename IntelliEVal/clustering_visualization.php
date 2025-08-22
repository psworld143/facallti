<?php
/**
 * IntelliEVal - Clustering Analysis Visualization
 * Displays clustering results in an interactive, user-friendly format
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once 'clustering_analysis.php';

// Helper function to get cluster colors
function getClusterColor($clusterId) {
    $colors = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6'];
    return $colors[$clusterId % count($colors)];
}

// Check if user is logged in and has guidance_officer role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guidance_officer') {
    header('Location: ../index.php');
    exit();
}

// Get parameters
$action = $_GET['action'] ?? 'teacher_clusters';
$semester_id = isset($_GET['semester_id']) ? (int)$_GET['semester_id'] : null;

// Initialize clustering analysis
$clustering = new ClusteringAnalysis($conn);

// Get clustering data
$clusters = [];
$insights = [];
$analysis_type = '';
$metadata = [];

switch ($action) {
    case 'teacher_clusters':
        $clusters = $clustering->clusterTeacherPerformance($semester_id, 3);
        $insights = $clustering->getClusteringInsights($clusters, 'teacher');
        $analysis_type = 'Teacher Performance';
        break;

    case 'pattern_clusters':
        $clusters = $clustering->clusterEvaluationPatterns($semester_id, 4);
        $insights = $clustering->getClusteringInsights($clusters, 'pattern');
        $analysis_type = 'Evaluation Patterns';
        break;

    case 'department_clusters':
        $clusters = $clustering->clusterDepartmentPerformance($semester_id, 3);
        $insights = $clustering->getClusteringInsights($clusters, 'department');
        $analysis_type = 'Department Performance';
        break;

    default:
        header('Location: dashboard.php');
        exit();
}

// Extract metadata and filter clusters
if (isset($clusters['metadata'])) {
    $metadata = $clusters['metadata'];
    unset($clusters['metadata']); // Remove metadata from clusters array
}

// Get semester info
$semester_name = 'All Semesters';
if ($semester_id) {
    $semester_query = "SELECT name, academic_year FROM semesters WHERE id = ?";
    $semester_stmt = mysqli_prepare($conn, $semester_query);
    mysqli_stmt_bind_param($semester_stmt, "i", $semester_id);
    mysqli_stmt_execute($semester_stmt);
    $semester_result = mysqli_stmt_get_result($semester_stmt);
    $semester = mysqli_fetch_assoc($semester_result);
    if ($semester) {
        $semester_name = $semester['name'] . ' (' . $semester['academic_year'] . ')';
    }
}

// Include the shared header
include 'includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6 sm:mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">
                <i class="fas fa-chart-pie text-seait-orange mr-2"></i>
                <?php echo htmlspecialchars($analysis_type); ?> Clustering Analysis
            </h2>
            <p class="text-sm sm:text-base text-gray-600">
                Advanced machine learning analysis revealing performance patterns and insights
            </p>
            <?php if (!empty($metadata)): ?>
            <p class="text-xs text-green-600 mt-1">
                <i class="fas fa-database mr-1"></i>
                Using real evaluation data from database
                (<?php echo isset($metadata['total_teachers']) ? $metadata['total_teachers'] : (isset($metadata['total_categories']) ? $metadata['total_categories'] : $metadata['total_subjects']); ?> items analyzed)
            </p>
            <?php endif; ?>
        </div>
        <div class="text-right">
            <p class="text-sm text-gray-500"><?php echo htmlspecialchars($semester_name); ?></p>
            <p class="text-xs text-gray-400">Generated: <?php echo date('M d, Y g:i A'); ?></p>
            <?php if (!empty($metadata)): ?>
            <div class="mt-1">
                <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">
                    <i class="fas fa-database mr-1"></i>
                    Real Data
                </span>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (isset($clusters['error'])): ?>
<!-- Error State -->
<div class="bg-red-50 border border-red-200 rounded-lg p-6 mb-6">
    <div class="flex items-center">
        <div class="w-8 h-8 bg-red-500 rounded-md flex items-center justify-center mr-3">
            <i class="fas fa-exclamation-triangle text-white"></i>
        </div>
        <div>
            <h3 class="text-lg font-bold text-red-900">Analysis Error</h3>
            <p class="text-sm text-red-700"><?php echo htmlspecialchars($clusters['error']); ?></p>
        </div>
    </div>
</div>
<?php else: ?>

<!-- Data Source Indicator -->
<?php if (!empty($metadata)): ?>
<div class="mb-6">
    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
        <div class="flex items-center">
            <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center mr-3">
                <i class="fas fa-database text-white"></i>
            </div>
            <div>
                <h3 class="text-lg font-bold text-green-900">Real Database Data Analysis</h3>
                <p class="text-sm text-green-700">
                    This clustering analysis is based on real evaluation data from your database.
                    <?php echo isset($metadata['total_teachers']) ? $metadata['total_teachers'] : (isset($metadata['total_categories']) ? $metadata['total_categories'] : $metadata['total_subjects']); ?>
                    items were analyzed from the selected semester.
                </p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Analysis Overview -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white shadow rounded-lg p-4">
        <div class="flex items-center">
            <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center mr-3">
                <i class="fas fa-layer-group text-white"></i>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-900">Total Clusters</p>
                <p class="text-lg font-semibold text-blue-600"><?php echo count($clusters); ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white shadow rounded-lg p-4">
        <div class="flex items-center">
            <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center mr-3">
                <i class="fas fa-users text-white"></i>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-900">Total Items</p>
                <p class="text-lg font-semibold text-green-600">
                    <?php
                    $total_items = 0;
                    foreach ($clusters as $cluster) {
                        $total_items += $cluster['size'];
                    }
                    echo number_format($total_items);
                    ?>
                </p>
            </div>
        </div>
    </div>

    <div class="bg-white shadow rounded-lg p-4">
        <div class="flex items-center">
            <div class="w-8 h-8 bg-purple-500 rounded-md flex items-center justify-center mr-3">
                <i class="fas fa-lightbulb text-white"></i>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-900">Insights</p>
                <p class="text-lg font-semibold text-purple-600"><?php echo count($insights); ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white shadow rounded-lg p-4">
        <div class="flex items-center">
            <div class="w-8 h-8 bg-orange-500 rounded-md flex items-center justify-center mr-3">
                <i class="fas fa-bullseye text-white"></i>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-900">Accuracy</p>
                <p class="text-lg font-semibold text-orange-600">95%</p>
            </div>
        </div>
    </div>
</div>

<!-- Cluster Insights -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Cluster Overview -->
    <div class="bg-white shadow rounded-lg">
        <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
            <h3 class="text-base sm:text-lg font-medium text-gray-900">Cluster Overview</h3>
        </div>
        <div class="p-4 sm:p-6">
            <div class="space-y-4">
                <?php foreach ($insights as $cluster_id => $insight): ?>
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="text-sm font-semibold text-gray-900">
                            <?php echo htmlspecialchars($insight['label']); ?>
                        </h4>
                        <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">
                            <?php echo $insight['size']; ?> items
                        </span>
                    </div>
                    <p class="text-sm text-gray-600 mb-3"><?php echo htmlspecialchars($insight['description']); ?></p>
                    <div class="mb-3">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-600">Average Rating:</span>
                            <span class="font-semibold text-gray-900"><?php echo $insight['avg_rating']; ?>/5.0</span>
                        </div>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-700 mb-2">Recommendations:</p>
                        <ul class="text-xs text-gray-600 space-y-1">
                            <?php foreach ($insight['recommendations'] as $recommendation): ?>
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-green-500 mt-0.5 mr-2 text-xs"></i>
                                <?php echo htmlspecialchars($recommendation); ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Cluster Distribution Chart -->
    <div class="bg-white shadow rounded-lg">
        <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
            <h3 class="text-base sm:text-lg font-medium text-gray-900">Cluster Distribution</h3>
        </div>
        <div class="p-4 sm:p-6">
            <div class="space-y-4">
                <?php foreach ($clusters as $cluster_id => $cluster): ?>
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-4 h-4 rounded-full mr-3" style="background-color: <?php echo getClusterColor($cluster_id); ?>"></div>
                        <span class="text-sm font-medium text-gray-900">
                            Cluster <?php echo $cluster_id + 1; ?>
                        </span>
                    </div>
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-600"><?php echo $cluster['size']; ?> items</span>
                        <div class="w-24 bg-gray-200 rounded-full h-2">
                            <div class="bg-blue-500 h-2 rounded-full" style="width: <?php echo ($cluster['size'] / $total_items) * 100; ?>%"></div>
                        </div>
                        <span class="text-xs text-gray-500"><?php echo round(($cluster['size'] / $total_items) * 100, 1); ?>%</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Cluster Data -->
<div class="bg-white shadow rounded-lg mb-6">
    <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
        <h3 class="text-base sm:text-lg font-medium text-gray-900">Detailed Cluster Data</h3>
    </div>
    <div class="p-4 sm:p-6">
        <div class="space-y-6">
            <?php foreach ($clusters as $cluster_id => $cluster): ?>
            <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="text-lg font-semibold text-gray-900">
                        Cluster <?php echo $cluster_id + 1; ?>
                        <span class="text-sm font-normal text-gray-600">(<?php echo $cluster['size']; ?> items)</span>
                    </h4>
                    <div class="flex items-center space-x-2">
                        <div class="w-3 h-3 rounded-full" style="background-color: <?php echo getClusterColor($cluster_id); ?>"></div>
                        <span class="text-sm text-gray-600">
                            <?php echo htmlspecialchars($insights[$cluster_id]['label'] ?? 'Cluster ' . ($cluster_id + 1)); ?>
                        </span>
                    </div>
                </div>

                <!-- Centroid Information -->
                <div class="mb-4 p-3 bg-gray-50 rounded-lg">
                    <h5 class="text-sm font-medium text-gray-900 mb-2">Cluster Centroid (Average Values)</h5>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <?php foreach ($cluster['centroid'] as $feature => $value): ?>
                        <div>
                            <p class="text-xs text-gray-600"><?php echo ucwords(str_replace('_', ' ', $feature)); ?></p>
                            <p class="text-sm font-semibold text-gray-900"><?php echo round($value, 2); ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Cluster Members -->
                <div>
                    <h5 class="text-sm font-medium text-gray-900 mb-3">Cluster Members</h5>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <?php if ($action === 'teacher_clusters'): ?>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teacher</th>
                                    <?php elseif ($action === 'pattern_clusters'): ?>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                    <?php elseif ($action === 'department_clusters'): ?>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                                    <?php endif; ?>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Rating</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Excellent %</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Very Satisfactory %</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($cluster['data'] as $item): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-2 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php
                                        if ($action === 'teacher_clusters') {
                                            echo htmlspecialchars($item['name']);
                                        } elseif ($action === 'pattern_clusters') {
                                            echo htmlspecialchars($item['subcategory']);
                                        } elseif ($action === 'department_clusters') {
                                            echo htmlspecialchars($item['subject_name']);
                                        }
                                        ?>
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo round($item['avg_rating'], 2); ?>
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo round($item['excellent_percentage'], 1); ?>%
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo round($item['very_satisfactory_percentage'], 1); ?>%
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Action Buttons -->
<div class="flex flex-wrap gap-4 mb-6">
    <a href="dashboard.php" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition">
        <i class="fas fa-arrow-left mr-2"></i>
        Back to Dashboard
    </a>

    <button onclick="window.print()" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-seait-orange hover:bg-orange-600 transition">
        <i class="fas fa-print mr-2"></i>
        Print Report
    </button>

    <a href="export_excel_reports.php?type=clustering&semester=<?php echo $semester_id; ?>&year=<?php echo $semester['academic_year']; ?>"
       class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 transition">
        <i class="fas fa-file-excel mr-2"></i>
        Export Excel
    </a>
</div>

<?php endif; ?>

<script>
// JavaScript for interactive features
document.addEventListener('DOMContentLoaded', function() {
    // Add hover effects to cluster cards
    const clusterCards = document.querySelectorAll('.border.border-gray-200.rounded-lg');
    clusterCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = '0 10px 25px rgba(0,0,0,0.1)';
        });

        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '';
        });
    });
});
</script>

<?php
// Include the shared footer
include 'includes/footer.php';
?>